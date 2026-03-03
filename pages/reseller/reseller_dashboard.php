<?php
// META: {"title": "Dashboard", "order": 10, "nav": true, "hidden": false}

// ============================================================
// TEMPORARY DEBUG SYSTEM - REMOVE AFTER FIXING
// ============================================================
$debug_log = [];
$debug_errors = [];

function debug_add($key, $value) {
    global $debug_log;
    $debug_log[$key] = $value;
}

function debug_error($msg) {
    global $debug_errors;
    $debug_errors[] = $msg;
}

// Capture all PHP errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    global $debug_errors;
    $debug_errors[] = "PHP Error [$errno]: $errstr in $errfile on line $errline";
    return false;
});

// System Info
debug_add('PHP Version', phpversion());
debug_add('Server Software', $_SERVER['SERVER_SOFTWARE'] ?? 'unknown');
debug_add('Document Root', $_SERVER['DOCUMENT_ROOT'] ?? 'unknown');
debug_add('Script Name', $_SERVER['SCRIPT_NAME'] ?? 'unknown');
debug_add('Request URI', $_SERVER['REQUEST_URI'] ?? 'unknown');
debug_add('HTTP Host', $_SERVER['HTTP_HOST'] ?? 'unknown');

// Session Info
debug_add('Session ID', session_id());
debug_add('Session Name', session_name());
debug_add('Session Status', session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : 'INACTIVE');
debug_add('Session user_id', $_SESSION['user_id'] ?? 'NOT SET');
debug_add('Session username', $_SESSION['username'] ?? 'NOT SET');
debug_add('Session role', $_SESSION['role'] ?? 'NOT SET');
debug_add('Session is_super_admin', isset($_SESSION['is_super_admin']) ? ($_SESSION['is_super_admin'] ? 'true' : 'false') : 'NOT SET');
debug_add('Session status', $_SESSION['status'] ?? 'NOT SET');

// Cookie Info
debug_add('Cookies received', !empty($_COOKIE) ? implode(', ', array_keys($_COOKIE)) : 'NONE');
debug_add('Session cookie path', ini_get('session.cookie_path'));

// Database connection test
try {
    $pdo->query("SELECT 1");
    debug_add('DB Connection', 'OK');
} catch (Throwable $e) {
    debug_add('DB Connection', 'FAILED: ' . $e->getMessage());
    debug_error('Database connection failed: ' . $e->getMessage());
}

$reseller_id = (int)($_SESSION['user_id'] ?? 0);
debug_add('Reseller ID', $reseller_id);

if ($reseller_id === 0) {
    debug_error('CRITICAL: reseller_id is 0 - Session user_id not set!');
}

// Test each query separately with error catching
$total_users = 0;
$expired_users = 0;
$expiring_soon = 0;
$recent_users = [];
$reseller_data = null;
$balance = 0;
$monthly_rate = 1.0;
$recent_transactions = [];
$announcements = [];

// Query 1: Total users
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE reseller_id=?");
    $stmt->execute([$reseller_id]);
    $total_users = (int)$stmt->fetchColumn();
    debug_add('Query: Total Users', "OK ($total_users)");
} catch (Throwable $e) {
    debug_add('Query: Total Users', 'FAILED');
    debug_error('Total Users Query: ' . $e->getMessage());
}

// Query 2: Expired users
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE reseller_id=? AND expires_at < CURDATE()");
    $stmt->execute([$reseller_id]);
    $expired_users = (int)$stmt->fetchColumn();
    debug_add('Query: Expired Users', "OK ($expired_users)");
} catch (Throwable $e) {
    debug_add('Query: Expired Users', 'FAILED');
    debug_error('Expired Users Query: ' . $e->getMessage());
}

// Query 3: Expiring soon
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE reseller_id=? AND expires_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)");
    $stmt->execute([$reseller_id]);
    $expiring_soon = (int)$stmt->fetchColumn();
    debug_add('Query: Expiring Soon', "OK ($expiring_soon)");
} catch (Throwable $e) {
    debug_add('Query: Expiring Soon', 'FAILED');
    debug_error('Expiring Soon Query: ' . $e->getMessage());
}

// Query 4: Recent users
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE reseller_id=? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$reseller_id]);
    $recent_users = $stmt->fetchAll();
    debug_add('Query: Recent Users', 'OK (' . count($recent_users) . ' rows)');
} catch (Throwable $e) {
    debug_add('Query: Recent Users', 'FAILED');
    debug_error('Recent Users Query: ' . $e->getMessage());
}

// Query 5: Reseller balance
try {
    $stmt = $pdo->prepare("SELECT balance, monthly_rate FROM resellers WHERE id=?");
    $stmt->execute([$reseller_id]);
    $reseller_data = $stmt->fetch();
    $balance = (float)($reseller_data['balance'] ?? 0);
    $monthly_rate = (float)($reseller_data['monthly_rate'] ?? 1.0);
    debug_add('Query: Reseller Data', $reseller_data ? 'OK' : 'NO DATA');
} catch (Throwable $e) {
    debug_add('Query: Reseller Data', 'FAILED');
    debug_error('Reseller Data Query: ' . $e->getMessage());
}

// Query 6: Recent transactions
try {
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE reseller_id=? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$reseller_id]);
    $recent_transactions = $stmt->fetchAll();
    debug_add('Query: Transactions', 'OK (' . count($recent_transactions) . ' rows)');
} catch (Throwable $e) {
    debug_add('Query: Transactions', 'FAILED');
    debug_error('Transactions Query: ' . $e->getMessage());
}

// Query 7: Announcements
try {
    $announcements = get_announcements($pdo, 'reseller');
    debug_add('Query: Announcements', 'OK (' . count($announcements) . ' rows)');
} catch (Throwable $e) {
    debug_add('Query: Announcements', 'FAILED');
    debug_error('Announcements Query: ' . $e->getMessage());
}

// Check tables exist
$tables_to_check = ['users', 'resellers', 'transactions', 'announcements', 'admin_consoles', 'audit_log'];
foreach ($tables_to_check as $tbl) {
    try {
        $pdo->query("SELECT 1 FROM $tbl LIMIT 1");
        debug_add("Table: $tbl", 'EXISTS');
    } catch (Throwable $e) {
        debug_add("Table: $tbl", 'MISSING or ERROR');
        debug_error("Table '$tbl' check failed: " . $e->getMessage());
    }
}

// Restore error handler
restore_error_handler();

// Build debug output
$debug_output = "=== FLOOZINA DEBUG LOG ===\n";
$debug_output .= "Generated: " . date('Y-m-d H:i:s T') . "\n\n";

$debug_output .= "--- SYSTEM INFO ---\n";
foreach ($debug_log as $k => $v) {
    $debug_output .= "$k: $v\n";
}

if (!empty($debug_errors)) {
    $debug_output .= "\n--- ERRORS FOUND (" . count($debug_errors) . ") ---\n";
    foreach ($debug_errors as $i => $err) {
        $debug_output .= ($i + 1) . ". $err\n";
    }
} else {
    $debug_output .= "\n--- NO ERRORS DETECTED ---\n";
}

$has_errors = !empty($debug_errors);
?>

<!-- DEBUG BOX - TEMPORARY -->
<div id="debugBox" style="
    background: <?= $has_errors ? '#2d1b1b' : '#1b2d1b' ?>;
    border: 2px solid <?= $has_errors ? '#ff4444' : '#44ff44' ?>;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    font-family: 'Consolas', 'Monaco', monospace;
">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
        <h3 style="margin:0; color:<?= $has_errors ? '#ff6666' : '#66ff66' ?>;">
            🔧 DEBUG LOG <?= $has_errors ? '⚠️ ERRORS FOUND' : '✅ ALL OK' ?>
        </h3>
        <div>
            <button onclick="copyDebug()" style="
                background: #2563eb;
                color: white;
                border: none;
                padding: 8px 16px;
                border-radius: 6px;
                cursor: pointer;
                font-weight: bold;
                margin-right: 8px;
            ">📋 Copy Debug Log</button>
            <button onclick="document.getElementById('debugBox').style.display='none'" style="
                background: #666;
                color: white;
                border: none;
                padding: 8px 16px;
                border-radius: 6px;
                cursor: pointer;
            ">✕ Hide</button>
        </div>
    </div>
    
    <pre id="debugContent" style="
        background: #0a0a0a;
        color: #00ff00;
        padding: 15px;
        border-radius: 8px;
        overflow-x: auto;
        white-space: pre-wrap;
        word-wrap: break-word;
        max-height: 400px;
        overflow-y: auto;
        font-size: 12px;
        line-height: 1.5;
    "><?= htmlspecialchars($debug_output) ?></pre>
    
    <p style="margin:10px 0 0 0; color:#999; font-size:11px;">
        ⚠️ TEMPORARY DEBUG BOX - Remove after fixing the issue
    </p>
</div>

<script>
function copyDebug() {
    const text = document.getElementById('debugContent').innerText;
    navigator.clipboard.writeText(text).then(() => {
        alert('Debug log copied to clipboard!');
    }).catch(() => {
        // Fallback
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        alert('Debug log copied!');
    });
}
</script>
<!-- END DEBUG BOX -->

<!-- Welcome Section -->
<div class="card" style="margin-bottom:20px; background:linear-gradient(135deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.02) 100%)">
    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:15px">
        <div>
            <h2 style="margin:0 0 5px 0">Welcome back, <?= e($_SESSION['username']) ?>! 👋</h2>
            <p class="muted"><?= date('l, F j, Y') ?></p>
        </div>
        <div class="inline">
            <span class="badge <?= $balance >= 0 ? 'b-success' : 'b-danger' ?>">
                Balance: $<?= money_fmt($balance) ?>
            </span>
            <span class="badge b-info">Rate: $<?= money_fmt($monthly_rate) ?>/mo</span>
        </div>
    </div>
</div>

<!-- ANNOUNCEMENTS SECTION - MOVED TO TOP FOR VISIBILITY -->
<?php if (!empty($announcements)): ?>
<div class="card" style="margin-bottom:20px; border-left:4px solid #93c5fd; background:rgba(147,197,253,0.05)">
    <div style="display:flex; align-items:center; gap:10px; margin-bottom:15px">
        <span style="font-size:24px">📢</span>
        <h3 style="margin:0">Announcements & Updates</h3>
        <span class="badge b-info"><?= count($announcements) ?> new</span>
    </div>
    
    <div style="display:flex; flex-direction:column; gap:12px">
        <?php foreach (array_slice($announcements, 0, 3) as $a): 
            $badge = 'b-info';
            $icon = 'ℹ️';
            $border_color = '#93c5fd';
            $bg_color = 'rgba(147,197,253,0.03)';
            
            if ($a['type'] === 'success') {
                $badge = 'b-success';
                $icon = '✅';
                $border_color = '#4ade80';
                $bg_color = 'rgba(74,222,128,0.03)';
            } elseif ($a['type'] === 'warning') {
                $badge = 'b-warning';
                $icon = '⚠️';
                $border_color = '#f6c177';
                $bg_color = 'rgba(246,193,119,0.03)';
            } elseif ($a['type'] === 'danger') {
                $badge = 'b-danger';
                $icon = '🚨';
                $border_color = '#ff4d4d';
                $bg_color = 'rgba(255,77,77,0.03)';
            }
            
            // Check if new (less than 2 days old)
            $is_new = (time() - strtotime($a['created_at'])) < (2 * 24 * 60 * 60);
        ?>
            <div style="padding:15px; background:<?= $bg_color ?>; border-radius:12px; border-left:3px solid <?= $border_color ?>; position:relative">
                <?php if ($is_new): ?>
                    <span style="position:absolute; top:-8px; right:15px; background:<?= $border_color ?>; color:#000; padding:2px 10px; border-radius:20px; font-size:10px; font-weight:bold">NEW</span>
                <?php endif; ?>
                
                <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px">
                    <span style="font-size:20px"><?= $icon ?></span>
                    <b><?= e($a['title']) ?></b>
                    <span class="badge <?= $badge ?>" style="font-size:10px"><?= e($a['type']) ?></span>
                </div>
                
                <div style="margin-left:28px; color:var(--text); white-space:pre-wrap; line-height:1.5">
                    <?= e(substr($a['content'], 0, 200)) ?><?= strlen($a['content']) > 200 ? '...' : '' ?>
                </div>
                
                <div style="margin-top:8px; margin-left:28px; display:flex; align-items:center; gap:15px; font-size:11px; color:var(--muted2)">
                    <span>📅 <?= date('M j, Y', strtotime($a['created_at'])) ?></span>
                    <span>⏱️ <?= time_elapsed_string($a['created_at']) ?></span>
                    <?php if (!empty($a['expires_at'])): ?>
                        <span>⏰ Expires <?= date('M j', strtotime($a['expires_at'])) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        
        <?php if (count($announcements) > 3): ?>
        <a href="?page=reseller_announcements" class="btn btn-small" style="align-self:flex-end; margin-top:5px">
            View all <?= count($announcements) ?> announcements →
        </a>
        <?php endif; ?>
    </div>
</div>
<?php else: ?>
<!-- Show empty state if no announcements -->
<div class="card" style="margin-bottom:20px; background:rgba(255,255,255,0.02); border:1px dashed var(--border2)">
    <div style="display:flex; align-items:center; gap:15px; padding:10px">
        <span style="font-size:24px; opacity:0.5">📪</span>
        <div>
            <p style="margin:0">No announcements at this time. Check back later!</p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Stats Grid -->
<div class="row stats" style="margin-bottom:20px">
    <div class="col card" style="padding:20px">
        <div style="display:flex; align-items:center; gap:15px">
            <div style="font-size:32px">👥</div>
            <div>
                <div style="font-size:28px; font-weight:bold"><?= $total_users ?></div>
                <div class="muted">Total Users</div>
            </div>
        </div>
    </div>
    
    <div class="col card" style="padding:20px">
        <div style="display:flex; align-items:center; gap:15px">
            <div style="font-size:32px">⚠️</div>
            <div>
                <div style="font-size:28px; font-weight:bold; color:#f6c177"><?= $expiring_soon ?></div>
                <div class="muted">Expiring Soon</div>
            </div>
        </div>
    </div>
    
    <div class="col card" style="padding:20px">
        <div style="display:flex; align-items:center; gap:15px">
            <div style="font-size:32px">❌</div>
            <div>
                <div style="font-size:28px; font-weight:bold; color:#ff4d4d"><?= $expired_users ?></div>
                <div class="muted">Expired</div>
            </div>
        </div>
    </div>
    
    <div class="col card" style="padding:20px">
        <div style="display:flex; align-items:center; gap:15px">
            <div style="font-size:32px">💰</div>
            <div>
                <div style="font-size:28px; font-weight:bold; color:<?= $balance >= 0 ? '#4ade80' : '#ff4d4d' ?>">
                    $<?= money_fmt($balance) ?>
                </div>
                <div class="muted">Current Balance</div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions and Recent Users -->
<div class="row">
    <div class="col">
        <div class="card">
            <h3>⚡ Quick Actions</h3>
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(140px,1fr)); gap:10px; margin-top:15px">
                <a href="?page=reseller_assign" class="btn" style="justify-content:flex-start">
                    <span style="margin-right:8px">➕</span> Assign User
                </a>
                <a href="?page=reseller_bulk_assign" class="btn" style="justify-content:flex-start">
                    <span style="margin-right:8px">📦</span> Bulk Assign
                </a>
                <a href="?page=reseller_users" class="btn" style="justify-content:flex-start">
                    <span style="margin-right:8px">👥</span> View Users
                </a>
                <a href="?page=reseller_billing" class="btn" style="justify-content:flex-start">
                    <span style="margin-right:8px">💳</span> Billing
                </a>
            </div>
        </div>
    </div>
    
    <div class="col">
        <div class="card">
            <h3>📊 Usage Overview</h3>
            <div style="margin-top:15px">
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px">
                    <span>Active Users</span>
                    <span style="color:#4ade80; font-weight:bold"><?= $total_users - $expired_users ?></span>
                </div>
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px">
                    <span>Monthly Rate</span>
                    <span style="color:#93c5fd; font-weight:bold">$<?= money_fmt($monthly_rate) ?></span>
                </div>
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px">
                    <span>Est. Monthly Cost</span>
                    <span style="color:#f6c177; font-weight:bold">$<?= money_fmt($total_users * $monthly_rate) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Users -->
<div class="card">
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:15px">
        <h3 style="margin:0">🕒 Recent Users</h3>
        <a href="?page=reseller_users" class="btn btn-small">View All →</a>
    </div>
    
    <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th>Email</th>
                    <th>Profile</th>
                    <th>Expires</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_users as $u): 
                    $today = date('Y-m-d');
                    $status_class = 'b-success';
                    $status_text = 'Active';
                    
                    if (!empty($u['expires_at']) && $u['expires_at'] < $today) {
                        $status_class = 'b-danger';
                        $status_text = 'Expired';
                    } elseif (!empty($u['expires_at']) && $u['expires_at'] <= date('Y-m-d', strtotime('+7 days'))) {
                        $status_class = 'b-warning';
                        $status_text = 'Expiring Soon';
                    }
                ?>
                <tr>
                    <td><?= e($u['email']) ?></td>
                    <td><?= e($u['product_profile'] ?: 'N/A') ?></td>
                    <td><?= e($u['expires_at'] ?: 'N/A') ?></td>
                    <td><span class="badge <?= $status_class ?>"><?= $status_text ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Recent Transactions -->
<div class="card">
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:15px">
        <h3 style="margin:0">💰 Recent Transactions</h3>
        <a href="?page=reseller_billing" class="btn btn-small">View All →</a>
    </div>
    
    <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_transactions as $t): ?>
                <tr>
                    <td><?= e(date('M j, H:i', strtotime($t['created_at']))) ?></td>
                    <td>
                        <span class="badge <?= 
                            $t['type'] === 'payment' ? 'b-success' : 
                            ($t['type'] === 'charge' ? 'b-warning' : 'b-info') 
                        ?>">
                            <?= e($t['type']) ?>
                        </span>
                    </td>
                    <td><?= e($t['description']) ?></td>
                    <td style="color:<?= $t['type'] === 'payment' ? '#4ade80' : '#ff4d4d' ?>">
                        <?= $t['type'] === 'payment' ? '+' : '-' ?> $<?= money_fmt($t['amount']) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Helper function for time elapsed -->
<?php
function time_elapsed_string($datetime) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'just now';
}
?>
