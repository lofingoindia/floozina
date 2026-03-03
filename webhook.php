<?php
/**
 * GitHub Auto-Deploy Webhook
 * Listens for GitHub push events and runs `git pull` automatically.
 * Secured with HMAC SHA-256 signature validation.
 *
 * Setup: Set WEBHOOK_SECRET below to match the secret you set in GitHub.
 */

// ─────────────────────────────────────────────────────────────────────────────
// CONFIGURATION — change these values
// ─────────────────────────────────────────────────────────────────────────────

/** Must match the "Secret" you set in GitHub → Settings → Webhooks */
define('WEBHOOK_SECRET', 'zQ9!vT7@Lx3#Kp8$Rw2^Md6&Hs4*Ny5%');

/** Absolute path to the repository root on the server */
define('REPO_PATH', __DIR__);

/** Branch that should trigger a pull (e.g. 'main' or 'master') */
define('DEPLOY_BRANCH', 'main');

/** Path to the git binary (leave as 'git' for most servers) */
define('GIT_BIN', 'git');

/** Where to write deploy logs (must be writable by the web server) */
define('LOG_FILE', __DIR__ . '/logs/webhook.log');

// ─────────────────────────────────────────────────────────────────────────────
// SECURITY — only allow GitHub webhook delivery IPs (optional extra layer)
// ─────────────────────────────────────────────────────────────────────────────
// GitHub publishes its IP ranges at https://api.github.com/meta
// Uncomment the block below to additionally restrict by IP.
/*


// ─────────────────────────────────────────────────────────────────────────────
// MAIN HANDLER
// ─────────────────────────────────────────────────────────────────────────────

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// Read raw payload
$raw_payload = file_get_contents('php://input');
if ($raw_payload === false || $raw_payload === '') {
    http_response_code(400);
    exit('Empty payload');
}

// ── 1. Validate GitHub signature ─────────────────────────────────────────────
$signature_header = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
if (empty($signature_header)) {
    webhook_log('REJECTED', 'Missing X-Hub-Signature-256 header');
    http_response_code(401);
    exit('Unauthorized');
}

$expected_sig = 'sha256=' . hash_hmac('sha256', $raw_payload, WEBHOOK_SECRET);
if (!hash_equals($expected_sig, $signature_header)) {
    webhook_log('REJECTED', 'Invalid signature');
    http_response_code(403);
    exit('Forbidden');
}

// ── 2. Parse event type ───────────────────────────────────────────────────────
$event = $_SERVER['HTTP_X_GITHUB_EVENT'] ?? 'unknown';
$delivery = $_SERVER['HTTP_X_GITHUB_DELIVERY'] ?? 'n/a';

// We only care about push events
if ($event !== 'push') {
    webhook_log('IGNORED', "Event: {$event} | Delivery: {$delivery} (not a push, skipping)");
    http_response_code(200);
    echo json_encode(['status' => 'ignored', 'reason' => 'Not a push event']);
    exit;
}

// ── 3. Parse JSON body ────────────────────────────────────────────────────────
$payload = json_decode($raw_payload, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    webhook_log('ERROR', 'Failed to parse JSON payload');
    http_response_code(400);
    exit('Invalid JSON');
}

// ── 4. Check branch ───────────────────────────────────────────────────────────
$pushed_ref    = $payload['ref'] ?? '';
$expected_ref  = 'refs/heads/' . DEPLOY_BRANCH;

if ($pushed_ref !== $expected_ref) {
    webhook_log('IGNORED', "Pushed to {$pushed_ref}, deploy branch is {$expected_ref} — skipping");
    http_response_code(200);
    echo json_encode(['status' => 'ignored', 'reason' => 'Branch not monitored']);
    exit;
}

// ── 5. Run git pull ───────────────────────────────────────────────────────────
$pusher   = $payload['pusher']['name']  ?? 'unknown';
$head_sha = $payload['head_commit']['id'] ?? 'unknown';
$message  = $payload['head_commit']['message'] ?? '';

webhook_log('DEPLOY', "Triggered by {$pusher} | SHA: {$head_sha} | Message: " . trim($message));

chdir(REPO_PATH);

$cmd    = escapeshellcmd(GIT_BIN) . ' pull origin ' . escapeshellarg(DEPLOY_BRANCH) . ' 2>&1';
$output = [];
$return_code = 0;
exec($cmd, $output, $return_code);

$output_str = implode("\n", $output);

if ($return_code === 0) {
    webhook_log('SUCCESS', "git pull completed:\n{$output_str}");
    http_response_code(200);
    echo json_encode([
        'status'   => 'success',
        'output'   => $output,
        'pusher'   => $pusher,
        'sha'      => $head_sha,
    ]);
} else {
    webhook_log('FAILED', "git pull failed (exit {$return_code}):\n{$output_str}");
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'output' => $output,
        'code'   => $return_code,
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// HELPERS
// ─────────────────────────────────────────────────────────────────────────────

function webhook_log(string $level, string $message): void
{
    $log_dir = dirname(LOG_FILE);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    $ip        = $_SERVER['REMOTE_ADDR'] ?? '?';
    $line      = "[{$timestamp}] [{$level}] [IP:{$ip}] {$message}" . PHP_EOL;
    file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

/**
 * Check if an IP belongs to any of the given CIDR ranges.
 * (Enable this if you uncomment the IP-restriction block above.)
 */
function ip_in_cidrs(string $ip, array $cidrs): bool
{
    $ip_long = ip2long($ip);
    if ($ip_long === false) {
        return false;
    }
    foreach ($cidrs as $cidr) {
        [$subnet, $bits] = explode('/', $cidr);
        $subnet_long = ip2long($subnet);
        $mask        = -1 << (32 - (int)$bits);
        if (($ip_long & $mask) === ($subnet_long & $mask)) {
            return true;
        }
    }
    return false;
}
