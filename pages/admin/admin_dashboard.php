<?php
// META: {"title": "Dashboard", "order": 10, "nav": true, "hidden": false}
// Get stats
$stmt = $pdo->query("SELECT COUNT(*) FROM users");
$total_users = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM resellers WHERE role='reseller'");
$total_resellers = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM admin_consoles");
$total_consoles = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT SUM(balance) FROM resellers WHERE role='reseller'");
$total_balance = (float)$stmt->fetchColumn();

// Get recent activities
$stmt = $pdo->query("
    SELECT a.*, r.username AS reseller_name 
    FROM audit_log a 
    LEFT JOIN resellers r ON a.reseller_id=r.id 
    ORDER BY a.created_at DESC 
    LIMIT 10
");
$recent_activities = $stmt->fetchAll();

// Get announcements for super admin
$announcements = get_announcements($pdo, 'super_admin');
$active_announcements = array_filter($announcements, fn($a) => $a['is_active'] == 1);
?>

<!-- Welcome Section -->
<div class="card" style="margin-bottom:20px; background:linear-gradient(135deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.02) 100%)">
    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:15px">
        <div>
            <h2 style="margin:0 0 5px 0">Welcome back, <?= e($_SESSION['username']) ?>! 👋</h2>
            <p class="muted"><?= date('l, F j, Y') ?></p>
        </div>
        <div class="inline">
            <span class="badge b-info">System Online</span>
            <span class="badge b-success">All Systems Go</span>
        </div>
    </div>
</div>

<!-- Announcements Section - MOVED TO TOP FOR VISIBILITY -->
<?php if (!empty($active_announcements)): ?>
<div class="card" style="margin-bottom:20px; border-left:4px solid #93c5fd; background:rgba(147,197,253,0.05)">
    <div style="display:flex; align-items:center; gap:10px; margin-bottom:15px">
        <span style="font-size:24px">📢</span>
        <h3 style="margin:0">Active Announcements</h3>
        <span class="badge b-info"><?= count($active_announcements) ?> new</span>
    </div>
    
    <div style="display:flex; flex-direction:column; gap:12px">
        <?php foreach (array_slice($active_announcements, 0, 3) as $a): 
            $badge = 'b-info';
            $icon = 'ℹ️';
            $border_color = '#93c5fd';
            
            if ($a['type'] === 'success') {
                $badge = 'b-success';
                $icon = '✅';
                $border_color = '#4ade80';
            } elseif ($a['type'] === 'warning') {
                $badge = 'b-warning';
                $icon = '⚠️';
                $border_color = '#f6c177';
            } elseif ($a['type'] === 'danger') {
                $badge = 'b-danger';
                $icon = '🚨';
                $border_color = '#ff4d4d';
            }
        ?>
            <div style="padding:12px; background:rgba(255,255,255,0.03); border-radius:12px; border-left:3px solid <?= $border_color ?>">
                <div style="display:flex; align-items:center; gap:8px; margin-bottom:5px">
                    <span><?= $icon ?></span>
                    <b><?= e($a['title']) ?></b>
                    <span class="badge <?= $badge ?>" style="font-size:10px"><?= e($a['type']) ?></span>
                    <?php if ($a['target'] === 'super_admin'): ?>
                        <span class="badge" style="background:rgba(192,132,252,0.2); color:#c084fc">👑 Admin Only</span>
                    <?php elseif ($a['target'] === 'reseller'): ?>
                        <span class="badge" style="background:rgba(246,193,119,0.2); color:#f6c177">💼 Resellers</span>
                    <?php else: ?>
                        <span class="badge" style="background:rgba(147,197,253,0.2); color:#93c5fd">👥 All</span>
                    <?php endif; ?>
                </div>
                <p class="muted small" style="margin:5px 0 0 25px; white-space:pre-wrap"><?= e(substr($a['content'], 0, 150)) ?><?= strlen($a['content']) > 150 ? '...' : '' ?></p>
                <div style="margin-top:5px; margin-left:25px; font-size:11px; color:var(--muted2)">
                    Posted <?= time_elapsed_string($a['created_at']) ?>
                    <?php if (!empty($a['expires_at'])): ?>
                        • Expires <?= date('M j', strtotime($a['expires_at'])) ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        
        <?php if (count($active_announcements) > 3): ?>
        <a href="?page=admin_announcements" class="btn btn-small" style="align-self:flex-end; margin-top:5px">
            View all <?= count($active_announcements) ?> announcements →
        </a>
        <?php endif; ?>
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
            <div style="font-size:32px">💼</div>
            <div>
                <div style="font-size:28px; font-weight:bold"><?= $total_resellers ?></div>
                <div class="muted">Active Resellers</div>
            </div>
        </div>
    </div>
    
    <div class="col card" style="padding:20px">
        <div style="display:flex; align-items:center; gap:15px">
            <div style="font-size:32px">🏢</div>
            <div>
                <div style="font-size:28px; font-weight:bold"><?= $total_consoles ?></div>
                <div class="muted">Organizations</div>
            </div>
        </div>
    </div>
    
    <div class="col card" style="padding:20px">
        <div style="display:flex; align-items:center; gap:15px">
            <div style="font-size:32px">💰</div>
            <div>
                <div style="font-size:28px; font-weight:bold">$<?= money_fmt($total_balance) ?></div>
                <div class="muted">Total Balance</div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions and Recent Activity -->
<div class="row">
    <div class="col">
        <div class="card">
            <h3>⚡ Quick Actions</h3>
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(150px,1fr)); gap:10px; margin-top:15px">
                <a href="?page=admin_consoles" class="btn" style="justify-content:flex-start">
                    <span style="margin-right:8px">➕</span> Add Console
                </a>
                <a href="?page=admin_resellers" class="btn" style="justify-content:flex-start">
                    <span style="margin-right:8px">👤</span> Add Reseller
                </a>
                <a href="?page=admin_announcements" class="btn" style="justify-content:flex-start">
                    <span style="margin-right:8px">📢</span> Post Announcement
                </a>
                <a href="?page=admin_migration" class="btn" style="justify-content:flex-start">
                    <span style="margin-right:8px">🔄</span> Run Migration
                </a>
            </div>
        </div>
    </div>
    
    <div class="col">
        <div class="card">
            <h3>📊 System Status</h3>
            <div style="margin-top:15px">
                <?php
                $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM admin_consoles GROUP BY status");
                $console_status = $stmt->fetchAll();
                foreach ($console_status as $s):
                    $color = $s['status'] === 'active' ? '#4ade80' : ($s['status'] === 'inactive' ? '#f6c177' : '#ff4d4d');
                ?>
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px">
                    <span>Consoles <?= e($s['status']) ?></span>
                    <span style="color:<?= $color ?>; font-weight:bold"><?= $s['count'] ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="card">
    <h3>🕒 Recent Activity</h3>
    <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Action</th>
                    <th>User</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_activities as $log): ?>
                <tr>
                    <td><?= e(date('M j, H:i', strtotime($log['created_at']))) ?></td>
                    <td><?= e($log['action_type']) ?></td>
                    <td><?= e($log['performed_by'] ?? $log['reseller_name'] ?? 'System') ?></td>
                    <td>
                        <span class="badge <?= $log['status'] === 'success' ? 'b-success' : 'b-danger' ?>">
                            <?= e($log['status']) ?>
                        </span>
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
