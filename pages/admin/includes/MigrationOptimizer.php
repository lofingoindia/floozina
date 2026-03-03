<?php
/**
 * MigrationOptimizer.php (Background-safe)
 * - Stores job state on disk (not PHP session) so it keeps running without browser.
 * - Designed for shared hosting: run via cron OR spawn via exec (if allowed).
 *
 * IMPORTANT:
 * You must update your admin_migration.php to call:
 *   MigrationOptimizer::initJobState($job_id, $users, $params);
 * and your migration_step/status must call:
 *   MigrationOptimizer::getJobStatusFromState($pdo, $job_id);
 *
 * This file DOES NOT implement your UMAPI business logic.
 * You MUST plug your existing "add to UMAPI / remove from org" calls into
 *   MigrationOptimizer::processOneUser(...)
 */

class MigrationOptimizer
{
    /** Where to store state files (must be writable). */
    private static function stateDir(): string
    {
        // Prefer project-local storage if exists, else system temp.
        $candidates = [
            realpath(__DIR__ . '/../../storage/migration'),
            realpath(__DIR__ . '/../../storage'),
            sys_get_temp_dir(),
        ];
        foreach ($candidates as $dir) {
            if ($dir && is_dir($dir) && is_writable($dir)) {
                $path = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . 'migration';
                if (!is_dir($path)) @mkdir($path, 0775, true);
                if (is_dir($path) && is_writable($path)) return $path;
            }
        }
        // last resort
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'migration';
        if (!is_dir($path)) @mkdir($path, 0775, true);
        return $path;
    }

    private static function statePath(int $job_id): string
    {
        return self::stateDir() . DIRECTORY_SEPARATOR . "job_{$job_id}.json";
    }

    private static function lockPath(int $job_id): string
    {
        return self::stateDir() . DIRECTORY_SEPARATOR . "job_{$job_id}.lock";
    }

    /** Create initial state file for a job */
    public static function initJobState(int $job_id, array $users, array $params = []): void
    {
        $state = [
            'job_id'     => $job_id,
            'params'     => $params,
            'queue'      => array_values($users),  // array of user payloads
            'processed'  => 0,
            'failed'     => 0,
            'status'     => 'running',
            'warnings'   => [],
            'error'      => null,
            'started_at' => time(),
            'updated_at' => time(),
        ];
        self::atomicWrite(self::statePath($job_id), json_encode($state, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    }

    /** Read state safely */
    public static function loadState(int $job_id): ?array
    {
        $path = self::statePath($job_id);
        if (!is_file($path)) return null;
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') return null;
        $data = json_decode($raw, true);
        if (!is_array($data)) return null;
        return $data;
    }

    /** Persist state safely */
    private static function saveState(int $job_id, array $state): void
    {
        $state['updated_at'] = time();
        self::atomicWrite(self::statePath($job_id), json_encode($state, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    }

    /** Atomic write helper */
    private static function atomicWrite(string $path, string $content): void
    {
        $tmp = $path . '.tmp.' . bin2hex(random_bytes(4));
        @file_put_contents($tmp, $content, LOCK_EX);
        @rename($tmp, $path);
        @chmod($path, 0664);
    }

    /**
     * DB status helper (no schema change needed, but if you have processed/failed columns, update them too)
     */
    private static function updateJobRow(PDO $pdo, int $job_id, array $state): void
    {
        // If columns don't exist, ignore.
        try {
            $st = $pdo->prepare("UPDATE migration_jobs SET processed=?, failed=?, status=?, updated_at=NOW() WHERE id=?");
            $st->execute([
                (int)($state['processed'] ?? 0),
                (int)($state['failed'] ?? 0),
                (string)($state['status'] ?? 'running'),
                $job_id
            ]);
        } catch (Throwable $e) {
            // ignore
        }
    }

    /** Public: read job status for AJAX */
    public static function getJobStatusFromState(PDO $pdo, int $job_id): array
    {
        $st = self::loadState($job_id);
        if (!$st) {
            return [
                'processed' => 0,
                'total'     => 0,
                'failed'    => 0,
                'status'    => 'not_found',
                'warnings'  => [],
                'error'     => 'Job state not found',
            ];
        }
        $total = (int)($st['processed'] ?? 0) + (is_array($st['queue'] ?? null) ? count($st['queue']) : 0);
        return [
            'processed' => (int)($st['processed'] ?? 0),
            'total'     => $total,
            'failed'    => (int)($st['failed'] ?? 0),
            'status'    => (string)($st['status'] ?? 'running'),
            'warnings'  => $st['warnings'] ?? [],
            'error'     => $st['error'] ?? null,
        ];
    }

    /**
     * Process a chunk (call this from CRON or background worker)
     * @return array updated status
     */
    public static function processJobChunk(PDO $pdo, int $job_id, int $max_per_run = 80): array
    {
        $lockFp = @fopen(self::lockPath($job_id), 'c+');
        if (!$lockFp) {
            return ['ok' => false, 'error' => 'Cannot open lock file'];
        }
        // Prevent concurrent workers
        if (!flock($lockFp, LOCK_EX | LOCK_NB)) {
            fclose($lockFp);
            return ['ok' => true, 'status' => 'running', 'note' => 'locked'];
        }

        try {
            $state = self::loadState($job_id);
            if (!$state) return ['ok' => false, 'error' => 'State not found'];

            if (($state['status'] ?? '') !== 'running') {
                self::updateJobRow($pdo, $job_id, $state);
                return ['ok' => true] + self::getJobStatusFromState($pdo, $job_id);
            }

            $queue = $state['queue'] ?? [];
            if (!is_array($queue)) $queue = [];

            $take = min($max_per_run, count($queue));
            $batch = array_splice($queue, 0, $take);

            foreach ($batch as $u) {
                try {
                    self::processOneUser($pdo, $job_id, $u, $state['params'] ?? []);
                    $state['processed'] = (int)($state['processed'] ?? 0) + 1;
                } catch (Throwable $e) {
                    $state['failed'] = (int)($state['failed'] ?? 0) + 1;
                    $msg = $e->getMessage();
                    $state['warnings'][] = $msg;
                    // cap warnings to avoid huge state file
                    if (count($state['warnings']) > 50) {
                        $state['warnings'] = array_slice($state['warnings'], -50);
                    }
                }
            }

            $state['queue'] = $queue;

            if (count($queue) === 0) {
                $state['status'] = 'done';
            }

            self::saveState($job_id, $state);
            self::updateJobRow($pdo, $job_id, $state);

            return ['ok' => true] + self::getJobStatusFromState($pdo, $job_id);
        } finally {
            flock($lockFp, LOCK_UN);
            fclose($lockFp);
        }
    }

    /**
     * CANCEL job (safe)
     */
    public static function cancelJob(PDO $pdo, int $job_id): void
    {
        $state = self::loadState($job_id);
        if (!$state) return;
        $state['status'] = 'cancelled';
        self::saveState($job_id, $state);
        self::updateJobRow($pdo, $job_id, $state);
    }

    /**
     * === PLUG YOUR REAL UMAPI LOGIC HERE ===
     * This method should:
     * - Add user to target org/profile (UMAPI)
     * - Optionally remove from source org
     * - Update your local DB mapping (console_id/profile)
     * - Write migration_logs rows
     */
    private static function processOneUser(PDO $pdo, int $job_id, $userRow, array $params): void
    {
        // --- Example skeleton (REPLACE THIS with your real implementation) ---
        // $from = (int)$params['from_id'];
        // $to   = (int)$params['to_id'];
        // $profile = ... mapping logic ...
        // call your existing functions from app.php that hit UMAPI

        // If you don't replace this, it will do nothing (but count as processed)
        return;
    }
}
