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

<style>
/* Ultra-Premium Monochrome Dashboard Styles */
:root {
    --bg-main: #0B0F19;
    --card-bg: #131826;
    --card-border: #1E293B;
    --text-main: #F3F4F6;
    --text-muted: #94A3B8;
    --accent-blue: #3B82F6;
    --accent-green: #10B981;
    --accent-red: #EF4444;
    --accent-yellow: #F59E0B;
    --radius-lg: 16px;
    --radius-md: 12px;
    --transition: all 0.2s ease-in-out;
}

.premium-dashboard {
    color: var(--text-main);
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
    padding-bottom: 40px;
}

.premium-dashboard .premium-card {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: var(--radius-lg);
    padding: 24px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
    margin-bottom: 24px;
    transition: var(--transition);
}

.premium-dashboard .premium-card:hover {
    border-color: #334155;
}

.premium-dashboard .flex-between { display: flex; align-items: center; justify-content: space-between; gap: 15px; flex-wrap: wrap; }
.premium-dashboard .flex-center { display: flex; align-items: center; gap: 10px; }

/* Status Badges */
.premium-dashboard .status-badge {
    padding: 6px 14px; 
    border-radius: 20px; 
    font-size: 13px; 
    font-weight: 600; 
    display: inline-flex; 
    align-items: center; 
    gap: 8px; 
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.08);
}
.b-glow-blue { color: #60A5FA; background: rgba(59, 130, 246, 0.08); border-color: rgba(59, 130, 246, 0.2); }
.b-glow-green { color: #4ADE80; background: rgba(74, 222, 128, 0.08); border-color: rgba(74, 222, 128, 0.2); }
.b-glow-red { color: #F87171; background: rgba(248, 113, 113, 0.08); border-color: rgba(248, 113, 113, 0.2); }
.b-glow-yellow { color: #FACC15; background: rgba(250, 204, 21, 0.08); border-color: rgba(250, 204, 21, 0.2); }

/* Typography */
.premium-dashboard .text-muted { color: var(--text-muted); font-size: 14px; }
.premium-dashboard h2, .premium-dashboard h3 { margin: 0; font-weight: 600; color: #FFFFFF; }

/* Stats */
.premium-dashboard .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 24px; }
.premium-dashboard .stat-item {
    display: flex;
    align-items: center;
    gap: 16px;
}
.premium-dashboard .stat-icon-box {
    width: 48px; height: 48px; 
    border-radius: 12px; 
    display: flex; align-items: center; justify-content: center; 
    font-size: 20px;
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.05);
}
.stat-icon-blue { background: rgba(59, 130, 246, 0.05); color: #60A5FA; border-color: rgba(59, 130, 246, 0.1); }
.stat-icon-yellow { background: rgba(250, 204, 21, 0.05); color: #FACC15; border-color: rgba(250, 204, 21, 0.1); }
.stat-icon-red { background: rgba(248, 113, 113, 0.05); color: #F87171; border-color: rgba(248, 113, 113, 0.1); }

.premium-dashboard .stat-val { font-size: 24px; font-weight: 700; line-height: 1.2; }
.premium-dashboard .stat-label { font-size: 13px; color: var(--text-muted); margin-top: 4px; font-weight: 500; }

/* Grid Layout */
.premium-dashboard .split-row { display: grid; grid-template-columns: 1fr; gap: 24px; margin-bottom: 24px; }
@media(min-width: 900px) { .premium-dashboard .split-row { grid-template-columns: 1fr 1fr; } }

/* Buttons */
.premium-dashboard .action-btn {
    display: flex; align-items: center; justify-content: center; gap: 10px;
    background: transparent; 
    border: 1px solid rgba(255,255,255,0.08); 
    padding: 12px 16px;
    border-radius: var(--radius-md); 
    color: var(--text-main); 
    text-decoration: none; 
    font-weight: 600; 
    font-size: 14px; 
    transition: var(--transition);
}
.premium-dashboard .action-btn:hover { 
    background: rgba(255,255,255,0.03); 
    border-color: rgba(255,255,255,0.15); 
}
.premium-dashboard .btn-small { padding: 8px 16px; font-size: 13px; border-radius: 8px; }

/* Tables */
.premium-dashboard table { width: 100%; border-collapse: collapse; min-width: 600px; }
.premium-dashboard th, .premium-dashboard td { padding: 16px 20px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.04); font-size: 14px; }
.premium-dashboard th { font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 700; letter-spacing: 0.5px; padding-bottom: 12px; }
.premium-dashboard tbody tr:last-child td { border-bottom: none; }

/* Dashboard Specific Icons */
.svg-icon { width: 1.2em; height: 1.2em; stroke-width: 2; stroke: currentColor; fill: none; stroke-linecap: round; stroke-linejoin: round; }

</style>

<div class="premium-dashboard">

    <!-- Welcome Section -->
    <div class="premium-card">
        <div class="flex-between">
            <div>
                <h2 style="font-size: 26px; margin-bottom: 8px; display: flex; align-items: center; gap: 10px;">
                    Welcome back, <?= e($_SESSION['username']) ?>! 
                    <svg class="svg-icon" style="color: #E2E8F0;" viewBox="0 0 24 24"><path d="M17 8h1a4 4 0 1 1 0 8h-1"/><path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4Z"/><line x1="6" y1="2" x2="6" y2="4"/><line x1="10" y1="2" x2="10" y2="4"/><line x1="14" y1="2" x2="14" y2="4"/></svg>
                </h2>
                <div class="text-muted flex-center" style="font-size: 13px;">
                    <span style="display:inline-block; width:6px; height:6px; background:#4ADE80; border-radius:50%; margin-right:4px;"></span>
                    <?= date('l, F j, Y') ?>
                </div>
            </div>
            <div class="flex-center">
                <span class="status-badge b-glow-red">
                    <svg class="svg-icon" viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/><path d="M16 16h.01"/></svg>
                    Balance: $<?= money_fmt($balance) ?>
                </span>
                <span class="status-badge b-glow-blue">
                    <svg class="svg-icon" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    Rate: $<?= money_fmt($monthly_rate) ?>/mo
                </span>
            </div>
        </div>
    </div>

    <!-- Announcements -->
    <?php if (!empty($announcements)): ?>
    <div class="premium-card" style="border: 1px dashed var(--card-border);">
        <div class="flex-between" style="margin-bottom: 20px;">
            <div class="flex-center">
                <svg class="svg-icon" style="color: var(--text-muted)" viewBox="0 0 24 24"><path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>
                <h3 style="font-size: 16px;">Announcements</h3>
            </div>
        </div>
        
        <div>
            <?php foreach (array_slice($announcements, 0, 3) as $a): 
                $badge = 'b-glow-blue';
                
                if ($a['type'] === 'success') { $badge = 'b-glow-green'; }
                elseif ($a['type'] === 'warning') { $badge = 'b-glow-yellow'; }
                elseif ($a['type'] === 'danger') { $badge = 'b-glow-red'; }
            ?>
                <div style="padding: 16px; background: rgba(255,255,255,0.02); border-radius: 8px; margin-bottom: 12px; border: 1px solid rgba(255,255,255,0.03);">
                    <div class="flex-center" style="margin-bottom: 8px;">
                        <b style="font-size: 15px;"><?= e($a['title']) ?></b>
                        <span class="status-badge <?= $badge ?>" style="font-size:10px; padding: 2px 8px;"><?= e($a['type']) ?></span>
                    </div>
                    
                    <div style="line-height: 1.5; font-size: 14px; color: var(--text-muted); margin-bottom: 12px;">
                        <?= e(substr($a['content'], 0, 200)) ?><?= strlen($a['content']) > 200 ? '...' : '' ?>
                    </div>
                    
                    <div class="text-muted flex-center" style="font-size:12px; gap: 16px;">
                        <span><?= date('M j, Y', strtotime($a['created_at'])) ?></span>
                        <span><?= time_elapsed_string($a['created_at']) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="premium-card" style="border: 1px dashed rgba(255,255,255,0.08); display: flex; align-items: center; justify-content: center; padding: 30px;">
        <div class="text-muted flex-center" style="font-size: 14px;">
            <svg class="svg-icon" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            No announcements at this time. Check back later!
        </div>
    </div>
    <?php endif; ?>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="premium-card" style="padding: 20px; margin-bottom: 0;">
            <div class="stat-item">
                <div class="stat-icon-box stat-icon-blue" style="color: #94A3B8; background: rgba(255,255,255,0.03);">
                    <svg class="svg-icon" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
                <div>
                    <div class="stat-val"><?= $total_users ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
            </div>
        </div>
        
        <div class="premium-card" style="padding: 20px; margin-bottom: 0;">
            <div class="stat-item">
                <div class="stat-icon-box stat-icon-yellow">
                    <svg class="svg-icon" viewBox="0 0 24 24"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </div>
                <div>
                    <div class="stat-val" style="color: #FACC15;"><?= $expiring_soon ?></div>
                    <div class="stat-label">Expiring Soon</div>
                </div>
            </div>
        </div>
        
        <div class="premium-card" style="padding: 20px; margin-bottom: 0;">
            <div class="stat-item">
                <div class="stat-icon-box stat-icon-red">
                    <svg class="svg-icon" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </div>
                <div>
                    <div class="stat-val" style="color: #F87171;"><?= $expired_users ?></div>
                    <div class="stat-label">Expired</div>
                </div>
            </div>
        </div>
        
        <div class="premium-card" style="padding: 20px; margin-bottom: 0;">
            <div class="stat-item">
                <div class="stat-icon-box stat-icon-red">
                    <svg class="svg-icon" viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/><path d="M16 16h.01"/></svg>
                </div>
                <div>
                    <div class="stat-val" style="color: #F87171;">$<?= money_fmt($balance) ?></div>
                    <div class="stat-label">Current Balance</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions & Overview -->
    <div class="split-row">
        <!-- Quick Actions -->
        <div class="premium-card" style="margin-bottom: 0;">
            <div class="flex-center" style="margin-bottom: 24px;">
                <div class="stat-icon-box stat-icon-yellow" style="width: 32px; height: 32px; border-radius: 50%; font-size: 16px;">
                    <svg class="svg-icon" viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                </div>
                <h3 style="font-size: 16px;">Quick Actions</h3>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px,1fr)); gap: 16px;">
                <a href="?page=reseller_assign" class="action-btn">
                    <svg class="svg-icon" style="color: var(--text-muted)" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Assign User
                </a>
                <a href="?page=reseller_bulk_assign" class="action-btn">
                    <svg class="svg-icon" style="color: var(--text-muted)" viewBox="0 0 24 24"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                    Bulk Assign
                </a>
                <a href="?page=reseller_users" class="action-btn">
                    <svg class="svg-icon" style="color: var(--text-muted)" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    View Users
                </a>
                <a href="?page=reseller_billing" class="action-btn" style="justify-content: flex-start; padding-left: 20px;">
                    <svg class="svg-icon" style="color: var(--text-muted)" viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                    Billing
                </a>
            </div>
        </div>
        
        <!-- Usage Overview -->
        <div class="premium-card" style="margin-bottom: 0;">
            <div class="flex-center" style="margin-bottom: 24px;">
                <div class="stat-icon-box stat-icon-green" style="width: 32px; height: 32px; border-radius: 50%; font-size: 16px; background: rgba(16, 185, 129, 0.1); color: #10B981;">
                    <svg class="svg-icon" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                </div>
                <h3 style="font-size: 16px;">Usage Overview</h3>
            </div>
            
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <!-- Active Users -->
                <div style="padding-bottom: 4px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px;">
                        <span class="text-muted">Active Users</span>
                        <span><strong style="color: #10B981; font-weight: 700;"><?= $total_users - $expired_users ?></strong> <span style="color: #10B981; font-size: 12px; opacity: 0.7;">/ <?= $total_users ?></span></span>
                    </div>
                    <div style="width: 100%; height: 6px; background: rgba(255,255,255,0.05); border-radius: 4px; overflow: hidden;">
                        <div style="width: <?= $total_users > 0 ? (($total_users - $expired_users)/$total_users)*100 : 0 ?>%; height: 100%; background: #10B981; border-radius: 4px;"></div>
                    </div>
                </div>

                <!-- Monthly Rate -->
                <div style="display: flex; justify-content: space-between; font-size: 14px; padding-bottom: 16px; border-bottom: 1px solid rgba(255,255,255,0.04);">
                    <span class="text-muted">Monthly Rate</span>
                    <strong style="color: #60A5FA;">$<?= money_fmt($monthly_rate) ?></strong>
                </div>

                <!-- Est Monthly Cost -->
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px; background: transparent; border-radius: 8px; border: 1px solid #F59E0B; box-shadow: 0 0 10px rgba(245, 158, 11, 0.05);">
                    <span style="color: var(--text-main); font-size: 14px; font-weight: 600;">Est. Monthly Cost</span>
                    <strong style="color: #F59E0B; font-size: 18px;">$<?= money_fmt($total_users * $monthly_rate) ?></strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Users Table -->
    <div class="premium-card">
        <div class="flex-between" style="margin-bottom: 20px;">
            <div class="flex-center">
                <div class="stat-icon-box" style="width: 32px; height: 32px; border-radius: 50%; font-size: 16px; background: rgba(255,255,255,0.05); color: #FFF;">
                    <svg class="svg-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <h3 style="font-size: 16px;">Recent Users</h3>
            </div>
            <a href="?page=reseller_users" class="action-btn btn-small">View All &rarr;</a>
        </div>
        
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th style="padding-left: 0;">EMAIL</th>
                        <th>PROFILE</th>
                        <th>EXPIRES</th>
                        <th style="text-align: right; padding-right: 0;">STATUS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_users as $u): 
                        $today = date('Y-m-d');
                        $status_class = 'b-glow-green';
                        $status_text = 'Active';
                        
                        if (!empty($u['expires_at']) && $u['expires_at'] < $today) {
                            $status_class = 'b-glow-red';
                            $status_text = 'Expired';
                        } elseif (!empty($u['expires_at']) && $u['expires_at'] <= date('Y-m-d', strtotime('+7 days'))) {
                            $status_class = 'b-glow-yellow';
                            $status_text = 'Expiring Soon';
                        }
                    ?>
                    <tr>
                        <td style="font-weight: 500; padding-left: 0; color: #FFF;"><?= e($u['email']) ?></td>
                        <td class="text-muted"><?= e($u['product_profile'] ?: 'N/A') ?></td>
                        <td class="text-muted"><?= e($u['expires_at'] ?: 'N/A') ?></td>
                        <td style="text-align: right; padding-right: 0;"><span class="status-badge <?= $status_class ?>" style="border:none; padding:4px 10px;"><?= $status_text ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($recent_users)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 30px;">No users found.</td>
                    </tr>
                    <?php endif; ?>
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