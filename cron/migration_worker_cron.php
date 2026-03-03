<?php
/**
 * migration_worker_cron.php
 * Run this from CRON every minute (or every 2 minutes).
 *
 * Example cron (cPanel):
 *  * * * * * /usr/local/bin/php -q /home/USER/domains/YOURDOMAIN/public_html/cron/migration_worker_cron.php >/dev/null 2>&1
 *
 * It will process up to 80 users per run PER running job (you can adjust).
 */

$ROOT = realpath(__DIR__ . '/..'); // public_html/cron -> public_html
$app = $ROOT . '/app/app.php';
if (!is_file($app)) { echo "Missing app.php: $app\n"; exit(1); }
require_once $app;

// Update path if you keep MigrationOptimizer elsewhere:
$opt = $ROOT . '/pages/admin/includes/MigrationOptimizer.php';
if (!is_file($opt)) {
    // fallback: maybe it sits directly in pages/admin/
    $opt = $ROOT . '/pages/admin/MigrationOptimizer.php';
}
if (!is_file($opt)) { echo "Missing MigrationOptimizer.php: $opt\n"; exit(1); }
require_once $opt;

if (!function_exists('get_db_connection')) {
    echo "get_db_connection() not found\n";
    exit(1);
}
$pdo = get_db_connection();

// Pick running jobs (limit to avoid overload)
$jobs = [];
try {
    $st = $pdo->query("SELECT id FROM migration_jobs WHERE status='running' ORDER BY created_at ASC LIMIT 3");
    $jobs = $st->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    echo "DB error: " . $e->getMessage() . "\n";
    exit(1);
}

$limitPerRun = 80;

foreach ($jobs as $job_id) {
    $job_id = (int)$job_id;
    try {
        $res = MigrationOptimizer::processJobChunk($pdo, $job_id, $limitPerRun);
        echo "Job {$job_id}: " . json_encode($res) . "\n";
    } catch (Throwable $e) {
        echo "Job {$job_id} failed: " . $e->getMessage() . "\n";
    }
}

exit(0);
