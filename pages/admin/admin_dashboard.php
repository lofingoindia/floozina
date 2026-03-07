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

<!-- Premium Dashboard Styles -->
<style>
/* Premium Dark Theme & Animations */
.premium-dashboard {
    --card-bg: rgba(22, 27, 34, 0.6);
    --card-border: rgba(255, 255, 255, 0.08);
    --hover-bg: rgba(33, 38, 45, 0.8);
    --accent-glow: 0 0 20px rgba(59, 130, 246, 0.15);
    color: #e6edf3;
    animation: fadeIn 0.5s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes float {
    0% { transform: translateY(0px); }
    50% { transform: translateY(-3px); }
    100% { transform: translateY(0px); }
}

.premium-card {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: 16px;
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.premium-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
}

.premium-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4), var(--accent-glow);
    border-color: rgba(255, 255, 255, 0.15);
}

.welcome-card {
    background: linear-gradient(135deg, rgba(30, 58, 138, 0.2) 0%, rgba(17, 24, 39, 0.4) 100%);
    border-left: 4px solid #3b82f6;
}

.stat-icon-wrapper {
    width: 56px;
    height: 56px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 14px;
    background: linear-gradient(135deg, rgba(255,255,255,0.05), rgba(255,255,255,0.01));
    border: 1px solid rgba(255,255,255,0.05);
    font-size: 28px;
    box-shadow: inset 0 2px 10px rgba(255,255,255,0.02);
    animation: float 4s ease-in-out infinite;
}

.stat-value {
    font-size: 32px;
    font-weight: 800;
    background: linear-gradient(to right, #ffffff, #94a3b8);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 4px;
}

.premium-btn {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    padding: 12px 16px;
    display: flex;
    align-items: center;
    color: #e6edf3;
    text-decoration: none;
    transition: all 0.2s;
    font-weight: 500;
}

.premium-btn:hover {
    background: rgba(59, 130, 246, 0.15);
    border-color: rgba(59, 130, 246, 0.4);
    transform: translateX(4px);
    color: #fff;
}

.premium-badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

.premium-table {
    width: 100%;
    border-collapse: collapse;
}

.premium-table th {
    text-align: left;
    padding: 16px;
    color: #8b949e;
    font-weight: 600;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid var(--card-border);
    background: rgba(0, 0, 0, 0.2);
}

.premium-table td {
    padding: 16px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.02);
    color: #c9d1d9;
    transition: background 0.2s;
}

.premium-table tr:hover td {
    background: rgba(255, 255, 255, 0.02);
}
</style>

<div class="premium-dashboard">

<!-- Welcome Section -->
<div class="card premium-card welcome-card" style="margin-bottom:24px; padding:24px;">
    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:15px">
        <div>
            <h2 style="margin:0 0 8px 0; font-size: 28px; font-weight: 700;">Welcome back, <?= e($_SESSION['username']) ?>! <span style="display:inline-block; animation: float 2.5s ease-in-out infinite;">👋</span></h2>
            <p class="muted" style="color: #8b949e; font-size: 15px; margin: 0;"><?= date('l, F j, Y') ?></p>
        </div>
        <div class="inline" style="display: flex; gap: 10px;">
            <span class="badge b-info premium-badge" style="background: rgba(56, 189, 248, 0.1); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.2);">System Online</span>
            <span class="badge b-success premium-badge" style="background: rgba(74, 222, 128, 0.1); color: #4ade80; border: 1px solid rgba(74, 222, 128, 0.2);">All Systems Go</span>
        </div>
    </div>
</div>

<!-- Announcements Section -->
<?php if (!empty($active_announcements)): ?>
<div class="card premium-card" style="margin-bottom:24px; padding: 24px;">
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:20px">
        <div class="stat-icon-wrapper" style="width: 40px; height: 40px; font-size: 20px;">📢</div>
        <h3 style="margin:0; font-size: 20px;">Active Announcements</h3>
        <span class="badge b-info premium-badge" style="background: rgba(56, 189, 248, 0.1); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.2);"><?= count($active_announcements) ?> new</span>
    </div>
    
    <div style="display:flex; flex-direction:column; gap:16px">
        <?php foreach (array_slice($active_announcements, 0, 3) as $a): 
            $badge = 'b-info';
            $icon = 'ℹ️';
            $border_color = '#38bdf8';
            $bg_tint = 'rgba(56, 189, 248, 0.05)';
            
            if ($a['type'] === 'success') {
                $badge = 'b-success';
                $icon = '✅';
                $border_color = '#4ade80';
                $bg_tint = 'rgba(74, 222, 128, 0.05)';
            } elseif ($a['type'] === 'warning') {
                $badge = 'b-warning';
                $icon = '⚠️';
                $border_color = '#f6c177';
                $bg_tint = 'rgba(246, 193, 119, 0.05)';
            } elseif ($a['type'] === 'danger') {
                $badge = 'b-danger';
                $icon = '🚨';
                $border_color = '#ff4d4d';
                $bg_tint = 'rgba(255, 77, 77, 0.05)';
            }
        ?>
            <div style="padding:16px; background:<?= $bg_tint ?>; border-radius:12px; border-left:4px solid <?= $border_color ?>; transition: transform 0.2s; cursor: pointer;" onmouseover="this.style.transform='translateX(4px)'" onmouseout="this.style.transform='translateX(0)'">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px">
                    <span style="font-size: 18px;"><?= $icon ?></span>
                    <b style="font-size: 16px; color: #fff;"><?= e($a['title']) ?></b>
                    <span class="badge <?= $badge ?> premium-badge" style="font-size:10px; padding: 2px 8px;"><?= e($a['type']) ?></span>
                    <?php if ($a['target'] === 'super_admin'): ?>
                        <span class="badge premium-badge" style="background:rgba(192,132,252,0.1); color:#c084fc; border: 1px solid rgba(192,132,252,0.2);">👑 Admin Only</span>
                    <?php elseif ($a['target'] === 'reseller'): ?>
                        <span class="badge premium-badge" style="background:rgba(246,193,119,0.1); color:#f6c177; border: 1px solid rgba(246,193,119,0.2);">💼 Resellers</span>
                    <?php else: ?>
                        <span class="badge premium-badge" style="background:rgba(147,197,253,0.1); color:#93c5fd; border: 1px solid rgba(147,197,253,0.2);">👥 All</span>
                    <?php endif; ?>
                </div>
                <p class="muted small" style="margin:5px 0 0 32px; white-space:pre-wrap; color: #a3b3c4; line-height: 1.5;"><?= e(substr($a['content'], 0, 150)) ?><?= strlen($a['content']) > 150 ? '...' : '' ?></p>
                <div style="margin-top:10px; margin-left:32px; font-size:12px; color:#6e7681; display: flex; gap: 8px; align-items: center;">
                    <span style="display:flex; align-items:center; gap:4px">🕒 Posted <?= time_elapsed_string($a['created_at']) ?></span>
                    <?php if (!empty($a['expires_at'])): ?>
                        <span>•</span>
                        <span style="display:flex; align-items:center; gap:4px">⌛ Expires <?= date('M j', strtotime($a['expires_at'])) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        
        <?php if (count($active_announcements) > 3): ?>
        <a href="?page=admin_announcements" class="premium-btn" style="align-self:flex-start; margin-top:8px; justify-content: center;">
            View all <?= count($active_announcements) ?> announcements <span style="margin-left:8px">→</span>
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Stats Grid -->
<div class="row stats" style="margin-bottom:24px; display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px;">
    <div class="col premium-card" style="padding:24px">
        <div style="display:flex; align-items:center; gap:20px">
            <div class="stat-icon-wrapper" style="color: #60a5fa; text-shadow: 0 0 15px rgba(96, 165, 250, 0.4);">👥</div>
            <div>
                <div class="stat-value"><?= number_format($total_users) ?></div>
                <div style="color: #8b949e; font-size: 14px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">Total Users</div>
            </div>
        </div>
    </div>
    
    <div class="col premium-card" style="padding:24px">
        <div style="display:flex; align-items:center; gap:20px">
            <div class="stat-icon-wrapper" style="color: #c084fc; text-shadow: 0 0 15px rgba(192, 132, 252, 0.4); animation-delay: 0.5s;">💼</div>
            <div>
                <div class="stat-value"><?= number_format($total_resellers) ?></div>
                <div style="color: #8b949e; font-size: 14px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">Active Resellers</div>
            </div>
        </div>
    </div>
    
    <div class="col premium-card" style="padding:24px">
        <div style="display:flex; align-items:center; gap:20px">
            <div class="stat-icon-wrapper" style="color: #34d399; text-shadow: 0 0 15px rgba(52, 211, 153, 0.4); animation-delay: 1s;">🏢</div>
            <div>
                <div class="stat-value"><?= number_format($total_consoles) ?></div>
                <div style="color: #8b949e; font-size: 14px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">Organizations</div>
            </div>
        </div>
    </div>
    
    <div class="col premium-card" style="padding:24px">
        <div style="display:flex; align-items:center; gap:20px">
            <div class="stat-icon-wrapper" style="color: #fbbf24; text-shadow: 0 0 15px rgba(251, 191, 36, 0.4); animation-delay: 1.5s;">💰</div>
            <div>
                <div class="stat-value" style="background: linear-gradient(to right, #fbbf24, #f59e0b); -webkit-background-clip: text;">$<?= money_fmt($total_balance) ?></div>
                <div style="color: #8b949e; font-size: 14px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">Total Balance</div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions and System Status -->
<div class="row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 24px;">
    <div class="col premium-card" style="padding: 24px;">
        <h3 style="margin: 0 0 20px 0; font-size: 18px; display: flex; align-items: center; gap: 8px;">
            <span style="color: #fcd34d">⚡</span> Quick Actions
        </h3>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(160px,1fr)); gap:12px;">
            <a href="?page=admin_consoles" class="premium-btn">
                <span style="margin-right:12px; font-size: 18px;">➕</span> Add Console
            </a>
            <a href="?page=admin_resellers" class="premium-btn">
                <span style="margin-right:12px; font-size: 18px;">👤</span> Add Reseller
            </a>
            <a href="?page=admin_announcements" class="premium-btn">
                <span style="margin-right:12px; font-size: 18px;">📢</span> Post News
            </a>
            <a href="?page=admin_migration" class="premium-btn" style="background: rgba(99, 102, 241, 0.1); border-color: rgba(99, 102, 241, 0.2);">
                <span style="margin-right:12px; font-size: 18px;">🔄</span> Run Migration
            </a>
        </div>
    </div>
    
    <div class="col premium-card" style="padding: 24px;">
        <h3 style="margin: 0 0 20px 0; font-size: 18px; display: flex; align-items: center; gap: 8px;">
            <span style="color: #60a5fa">📊</span> System Status
        </h3>
        <div style="display: flex; flex-direction: column; gap: 16px;">
            <?php
            $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM admin_consoles GROUP BY status");
            $console_status = $stmt->fetchAll();
            foreach ($console_status as $s):
                $color = $s['status'] === 'active' ? '#4ade80' : ($s['status'] === 'inactive' ? '#fcd34d' : '#f87171');
                $bg = $s['status'] === 'active' ? 'rgba(74, 222, 128, 0.1)' : ($s['status'] === 'inactive' ? 'rgba(252, 211, 77, 0.1)' : 'rgba(248, 113, 113, 0.1)');
            ?>
            <div style="display:flex; align-items:center; justify-content:space-between; padding: 12px 16px; background: rgba(255,255,255,0.02); border-radius: 10px; border: 1px solid rgba(255,255,255,0.05);">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 10px; height: 10px; border-radius: 50%; background: <?= $color ?>; box-shadow: 0 0 8px <?= $color ?>;"></div>
                    <span style="text-transform: capitalize; color: #c9d1d9; font-weight: 500;">Consoles <?= e($s['status']) ?></span>
                </div>
                <span style="color:<?= $color ?>; font-weight:700; background: <?= $bg ?>; padding: 4px 12px; border-radius: 20px; font-size: 14px;"><?= $s['count'] ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="premium-card" style="padding: 24px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3 style="margin: 0; font-size: 18px; display: flex; align-items: center; gap: 8px;">
            <span style="color: #a78bfa">🕒</span> Recent Activity
        </h3>
        <a href="?page=admin_logs" class="premium-btn" style="padding: 6px 12px; font-size: 12px;">View All Logs</a>
    </div>
    <div style="overflow-x:auto; margin: -10px;">
        <table class="premium-table">
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
                    <td style="color: #8b949e; font-size: 13px;">
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <span>⏱️</span> <?= e(date('M j, H:i', strtotime($log['created_at']))) ?>
                        </div>
                    </td>
                    <td style="font-weight: 500; color: #e6edf3;"><?= e($log['action_type']) ?></td>
                    <td>
                        <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.05); padding: 4px 10px; border-radius: 20px; font-size: 13px;">
                            <span style="width: 16px; height: 16px; background: #3b82f6; border-radius: 50%; display: inline-block;"></span>
                            <?= e($log['performed_by'] ?? $log['reseller_name'] ?? 'System') ?>
                        </div>
                    </td>
                    <td>
                        <span class="premium-badge" style="background: <?= $log['status'] === 'success' ? 'rgba(74, 222, 128, 0.1)' : 'rgba(255, 77, 77, 0.1)' ?>; color: <?= $log['status'] === 'success' ? '#4ade80' : '#ff4d4d' ?>; border: 1px solid <?= $log['status'] === 'success' ? 'rgba(74, 222, 128, 0.2)' : 'rgba(255, 77, 77, 0.2)' ?>;">
                            <?= e($log['status']) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
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
