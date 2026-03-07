<?php
// META: {"title": "Dashboard", "order": 10, "nav": true, "hidden": false}
$reseller_id = (int)$_SESSION['user_id'];

// Get user stats
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE reseller_id=?");
$stmt->execute([$reseller_id]);
$total_users = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE reseller_id=? AND expires_at < CURDATE()");
$stmt->execute([$reseller_id]);
$expired_users = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE reseller_id=? AND expires_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)");
$stmt->execute([$reseller_id]);
$expiring_soon = (int)$stmt->fetchColumn();

// Get recent users
$stmt = $pdo->prepare("SELECT * FROM users WHERE reseller_id=? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$reseller_id]);
$recent_users = $stmt->fetchAll();

// Get balance
$stmt = $pdo->prepare("SELECT balance, monthly_rate FROM resellers WHERE id=?");
$stmt->execute([$reseller_id]);
$reseller_data = $stmt->fetch();
$balance = (float)($reseller_data['balance'] ?? 0);
$monthly_rate = (float)($reseller_data['monthly_rate'] ?? 1.0);

// Get recent transactions
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE reseller_id=? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$reseller_id]);
$recent_transactions = $stmt->fetchAll();

// GET ANNOUNCEMENTS FOR RESELLER - CRITICAL FIX
$announcements = get_announcements($pdo, 'reseller');
?>

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
