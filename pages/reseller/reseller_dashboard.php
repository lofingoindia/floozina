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
/* Premium Dashboard Specific Styles (Desktop) */
@media (max-width: 767px) {
    .desktop-dashboard { display: none !important; }
}
@media (min-width: 768px) {
    .mobile-dashboard { display: none !important; }
}

.premium-dashboard {
    --bg-main: transparent;
    --card-bg: #0f1219;
    --card-border: rgba(59, 130, 246, 0.1);
    --text-main: #e2e8f0;
    --text-muted: #8b98a5;
    --accent-blue: #2563eb;
    --accent-blue-hover: #1d4ed8;
    --accent-blue-glow: rgba(37, 99, 235, 0.2);
    --radius-lg: 16px;
    --radius-md: 12px;
    --transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    
    color: var(--text-main);
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
}

.premium-dashboard .premium-card {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: var(--radius-lg);
    padding: 24px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    transition: var(--transition);
    position: relative;
    overflow: hidden;
    animation: fadeUp 0.6s ease backwards;
    margin-bottom: 24px;
}

.premium-dashboard .premium-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(59, 130, 246, 0.2), transparent);
    opacity: 0;
    transition: var(--transition);
}

.premium-dashboard .premium-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.4), 0 0 20px var(--accent-blue-glow);
    border-color: rgba(59, 130, 246, 0.3);
}

.premium-dashboard .premium-card:hover::before {
    opacity: 1;
}

@keyframes fadeUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes pulseGlow {
    0% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.4); }
    70% { box-shadow: 0 0 0 10px rgba(59, 130, 246, 0); }
    100% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0); }
}

.delay-1 { animation-delay: 0.1s; }
.delay-2 { animation-delay: 0.2s; }
.delay-3 { animation-delay: 0.3s; }
.delay-4 { animation-delay: 0.4s; }

.premium-dashboard .flex-between { display: flex; align-items: center; justify-content: space-between; gap: 15px; flex-wrap: wrap; }
.premium-dashboard .flex-center { display: flex; align-items: center; gap: 10px; }

/* Status Badges */
.premium-dashboard .status-badge {
    padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 600; letter-spacing: 0.5px;
    display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; border: 1px solid transparent;
    transition: var(--transition);
}
.premium-dashboard .status-badge:hover { filter: brightness(1.1); transform: scale(1.02); }

.b-glow-blue { background: rgba(37, 99, 235, 0.1); color: #60A5FA; border-color: rgba(37, 99, 235, 0.2); }
.b-glow-green { background: rgba(16, 185, 129, 0.1); color: #34D399; border-color: rgba(16, 185, 129, 0.2); }
.b-glow-red { background: rgba(239, 68, 68, 0.1); color: #F87171; border-color: rgba(239, 68, 68, 0.2); }
.b-glow-yellow { background: rgba(245, 158, 11, 0.1); color: #FBBF24; border-color: rgba(245, 158, 11, 0.2); }

/* Typography */
.premium-dashboard .text-muted { color: var(--text-muted); font-size: 14px; }
.premium-dashboard h2, .premium-dashboard h3 { margin: 0; font-weight: 700; color: #FFFFFF; letter-spacing: -0.5px; }
.premium-dashboard .gradient-text {
    background: linear-gradient(90deg, #FFFFFF, #60A5FA);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
}

/* Stats */
.premium-dashboard .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 24px; }
.premium-dashboard .stat-icon {
    width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 26px;
    background: rgba(37, 99, 235, 0.05); border: 1px solid rgba(37, 99, 235, 0.15); box-shadow: inset 0 0 20px rgba(37, 99, 235, 0.02);
    transition: var(--transition);
}
.premium-dashboard .premium-card:hover .stat-icon {
    transform: scale(1.05) rotate(-2deg);
    border-color: rgba(37, 99, 235, 0.3);
}

.premium-dashboard .stat-val { font-size: 34px; font-weight: 800; margin: 4px 0 0 0; line-height: 1.1; letter-spacing: -1px; }

/* Grid Layout */
.premium-dashboard .split-row { display: grid; grid-template-columns: 1fr; gap: 24px; margin-bottom: 24px; }
@media(min-width: 900px) { .premium-dashboard .split-row { grid-template-columns: 1fr 1fr; } }

/* Buttons */
.premium-dashboard .action-btn {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    background: rgba(37, 99, 235, 0.05); border: 1px solid rgba(37, 99, 235, 0.15); padding: 14px;
    border-radius: var(--radius-md); color: var(--text-main); text-decoration: none; font-weight: 600; font-size: 14px; 
    transition: var(--transition); position: relative; overflow: hidden;
}
.premium-dashboard .action-btn::after {
    content: ''; position: absolute; top: 0; left: -100%; width: 50%; height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.05), transparent);
    transform: skewX(-20deg); transition: 0.5s;
}
.premium-dashboard .action-btn:hover { 
    background: var(--accent-blue); border-color: var(--accent-blue-hover); 
    box-shadow: 0 4px 15px var(--accent-blue-glow); color: #fff; 
}
.premium-dashboard .action-btn:hover::after { left: 150%; }

.premium-dashboard .btn-small { padding: 8px 16px; font-size: 13px; border-radius: 8px; }

/* Tables */
.premium-dashboard table { width: 100%; border-collapse: separate; border-spacing: 0; min-width: 600px; }
.premium-dashboard th, .premium-dashboard td { padding: 16px 20px; text-align: left; font-size: 14px; }
.premium-dashboard th { 
    background: rgba(37, 99, 235, 0.05); font-size: 12px; text-transform: uppercase; 
    color: #60A5FA; font-weight: 700; letter-spacing: 1px;
    border-bottom: 2px solid rgba(37, 99, 235, 0.1);
}
.premium-dashboard tbody tr { transition: var(--transition); border-bottom: 1px solid rgba(255,255,255,0.02); display: table-row; }
.premium-dashboard tbody tr td { border-bottom: 1px solid rgba(255,255,255,0.03); }
.premium-dashboard tbody tr:last-child td { border-bottom: none; }
.premium-dashboard tbody tr:hover td { background: rgba(37, 99, 235, 0.03); color: #fff; }

/* Announcements */
.premium-dashboard .announcement-card {
    padding: 18px 20px; border-radius: var(--radius-md); position: relative;
    border: 1px solid rgba(37, 99, 235, 0.1); margin-bottom: 12px; transition: var(--transition);
    background: rgba(15, 18, 25, 0.8);
}
.premium-dashboard .announcement-card:hover { 
    transform: translateX(6px); background: rgba(37, 99, 235, 0.03); 
    border-color: rgba(37, 99, 235, 0.3);
}
.premium-dashboard .a-new { 
    position: absolute; top: -8px; right: 16px; background: var(--accent-blue); color: #fff; 
    padding: 3px 10px; border-radius: 6px; font-size: 10px; font-weight: bold; 
    box-shadow: 0 2px 10px rgba(37, 99, 235, 0.4); animation: pulseGlow 2s infinite;
}

.a-info { border-left: 3px solid #60A5FA; }
.a-success { border-left: 3px solid #34D399; }
.a-warning { border-left: 3px solid #FBBF24; }
.a-danger { border-left: 3px solid #F87171; }
</style>

<div class="premium-dashboard desktop-dashboard">

    <!-- Welcome Section -->
    <div class="premium-card delay-1" style="background: linear-gradient(135deg, #0f1219 0%, #0a0d14 100%); border-color: rgba(37, 99, 235, 0.3); box-shadow: 0 8px 32px var(--accent-blue-glow);">
        <div class="flex-between">
            <div>
                <h2 class="gradient-text" style="font-size: 28px; margin-bottom: 6px;">Welcome back, <?= e($_SESSION['username']) ?>! <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; margin-left:4px;"><path d="M18 8h1a4 4 0 0 1 0 8h-1"/><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/></svg></h2>
                <div class="text-muted" style="display:flex; align-items:center; gap:8px;">
                    <span style="display:inline-block; width:8px; height:8px; background:#4ADE80; border-radius:50%; box-shadow:0 0 10px rgba(74,222,128,0.5);"></span>
                    <?= date('l, F j, Y') ?>
                </div>
            </div>
            <div class="flex-center">
                <span class="status-badge <?= $balance >= 0 ? 'b-glow-green' : 'b-glow-red' ?>" style="font-size: 14px; padding: 10px 16px;">
                    <span style="font-size: 16px;"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/><path d="M16 16h.01"/></svg></span> Balance: $<?= money_fmt($balance) ?>
                </span>
                <span class="status-badge b-glow-blue" style="font-size: 14px; padding: 10px 16px;">
                    <span style="font-size: 16px;"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></span> Rate: $<?= money_fmt($monthly_rate) ?>/mo
                </span>
            </div>
        </div>
    </div>

    <!-- Announcements -->
    <?php if (!empty($announcements)): ?>
    <div class="premium-card delay-1">
        <div class="flex-between" style="margin-bottom: 20px;">
            <div class="flex-center">
                <div class="stat-icon" style="width: 40px; height: 40px; font-size: 20px; background: rgba(59,130,246,0.1); border-color: rgba(59,130,246,0.2); color: #60A5FA;"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg></div>
                <h3 style="font-size: 20px;">Announcements & Updates</h3>
            </div>
            <span class="status-badge b-glow-blue"><?= count($announcements) ?> New</span>
        </div>
        
        <div>
            <?php foreach (array_slice($announcements, 0, 3) as $a): 
                $a_class = 'a-info';
                $badge = 'b-glow-blue';
                $icon = '<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>';
                
                if ($a['type'] === 'success') { $badge = 'b-glow-green'; $icon = '<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>'; $a_class = 'a-success'; }
                elseif ($a['type'] === 'warning') { $badge = 'b-glow-yellow'; $icon = '<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>'; $a_class = 'a-warning'; }
                elseif ($a['type'] === 'danger') { $badge = 'b-glow-red'; $icon = '<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><circle cx="12" cy="12" r="4"/><path d="m12 8 4 4-4 4-4-4z"/></svg>'; $a_class = 'a-danger'; }
                
                $is_new = (time() - strtotime($a['created_at'])) < (2 * 24 * 60 * 60);
            ?>
                <div class="announcement-card <?= $a_class ?>">
                    <?php if ($is_new): ?>
                        <span class="a-new">NEW</span>
                    <?php endif; ?>
                    
                    <div class="flex-center" style="margin-bottom: 8px;">
                        <span style="font-size:18px"><?= $icon ?></span>
                        <b style="font-size: 15px; color: #FFFFFF;"><?= e($a['title']) ?></b>
                        <span class="status-badge <?= $badge ?>" style="font-size:10px; padding: 2px 8px;"><?= e($a['type']) ?></span>
                    </div>
                    
                    <div style="margin-left: 28px; line-height: 1.6; font-size: 14px; color: var(--text-main); opacity: 0.9; margin-bottom: 12px; white-space: pre-wrap;">
                        <?= e(substr($a['content'], 0, 200)) ?><?= strlen($a['content']) > 200 ? '...' : '' ?>
                    </div>
                    
                    <div class="text-muted" style="margin-left: 28px; display:flex; gap:16px; font-size:12px;">
                        <span class="flex-center" style="gap:4px">📅 <?= date('M j, Y', strtotime($a['created_at'])) ?></span>
                        <span class="flex-center" style="gap:4px">⏱️ <?= time_elapsed_string($a['created_at']) ?></span>
                        <?php if (!empty($a['expires_at'])): ?>
                            <span class="flex-center" style="gap:4px; color:#F87171">⏰ Expires <?= date('M j', strtotime($a['expires_at'])) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if (count($announcements) > 3): ?>
            <div style="text-align: center; margin-top: 15px;">
                <a href="?page=reseller_announcements" class="action-btn" style="display: inline-flex; background: transparent; border-color: transparent; color: #60A5FA;">
                    View all <?= count($announcements) ?> announcements →
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="premium-card delay-1" style="border: 1px dashed rgba(255,255,255,0.1); background: transparent; box-shadow: none;">
        <div class="flex-center" style="justify-content: center; padding: 20px; color: var(--text-muted);">
            <span style="font-size:24px; opacity:0.5"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></span>
            <p style="margin:0; font-size: 15px;">No announcements at this time. Check back later!</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Stats Grid -->
    <div class="stats-grid delay-2">
        <div class="premium-card" style="padding: 20px; margin-bottom: 0;">
            <div class="flex-center" style="gap: 16px;">
                <div class="stat-icon" style="color: #A5B4FC; background: rgba(165, 180, 252, 0.1); border-color: rgba(165, 180, 252, 0.2);"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
                <div>
                    <div class="stat-val"><?= $total_users ?></div>
                    <div class="text-muted" style="font-size: 13px; font-weight: 500; margin-top: 4px;">Total Users</div>
                </div>
            </div>
        </div>
        
        <div class="premium-card" style="padding: 20px; margin-bottom: 0;">
            <div class="flex-center" style="gap: 16px;">
                <div class="stat-icon" style="color: #FBBF24; background: rgba(251, 191, 36, 0.1); border-color: rgba(251, 191, 36, 0.2);"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
                <div>
                    <div class="stat-val" style="color: #FBBF24; font-size: 28px;"><?= $expiring_soon ?></div>
                    <div class="text-muted" style="font-size: 13px; font-weight: 500; margin-top: 4px;">Expiring Soon</div>
                </div>
            </div>
        </div>
        
        <div class="premium-card" style="padding: 20px; margin-bottom: 0;">
            <div class="flex-center" style="gap: 16px;">
                <div class="stat-icon" style="color: #F87171; background: rgba(248, 113, 113, 0.1); border-color: rgba(248, 113, 113, 0.2);"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></div>
                <div>
                    <div class="stat-val" style="color: #F87171; font-size: 28px;"><?= $expired_users ?></div>
                    <div class="text-muted" style="font-size: 13px; font-weight: 500; margin-top: 4px;">Expired</div>
                </div>
            </div>
        </div>
        
        <div class="premium-card" style="padding: 20px; margin-bottom: 0;">
            <div class="flex-center" style="gap: 16px;">
                <div class="stat-icon" style="color: <?= $balance >= 0 ? '#4ADE80' : '#F87171' ?>; background: <?= $balance >= 0 ? 'rgba(74, 222, 128, 0.1)' : 'rgba(248, 113, 113, 0.1)' ?>; border-color: <?= $balance >= 0 ? 'rgba(74, 222, 128, 0.2)' : 'rgba(248, 113, 113, 0.2)' ?>;"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/><path d="M16 16h.01"/></svg></div>
                <div>
                    <div class="stat-val" style="color: <?= $balance >= 0 ? '#4ADE80' : '#F87171' ?>; font-size: 26px;">$<?= money_fmt($balance) ?></div>
                    <div class="text-muted" style="font-size: 13px; font-weight: 500; margin-top: 4px;">Current Balance</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions and Overview Split -->
    <div class="split-row delay-3">
        <div class="premium-card" style="margin-bottom: 0; min-height: 100%;">
            <div class="flex-center" style="margin-bottom: 20px;">
                <div class="stat-icon" style="width: 36px; height: 36px; font-size: 18px; background: rgba(250, 204, 21, 0.1); color: #FBBF24; border-color: rgba(250, 204, 21, 0.2);"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg></div>
                <h3 style="font-size: 18px;">Quick Actions</h3>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px,1fr)); gap: 12px;">
                <a href="?page=reseller_assign" class="action-btn">
                    <span style="font-size: 16px;"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></span> Assign User
                </a>
                <a href="?page=reseller_bulk_assign" class="action-btn">
                    <span style="font-size: 16px;"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg></span> Bulk Assign
                </a>
                <a href="?page=reseller_users" class="action-btn">
                    <span style="font-size: 16px;"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span> View Users
                </a>
                <a href="?page=reseller_billing" class="action-btn">
                    <span style="font-size: 16px;"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg></span> Billing
                </a>
            </div>
        </div>
        
        <div class="premium-card" style="margin-bottom: 0; min-height: 100%; position: relative;">
            <div style="position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: radial-gradient(circle, rgba(59,130,246,0.15) 0%, transparent 70%); border-radius: 50%; pointer-events: none;"></div>
            
            <div class="flex-center" style="margin-bottom: 20px;">
                <div class="stat-icon" style="width: 36px; height: 36px; font-size: 18px; background: rgba(16, 185, 129, 0.1); color: #34D399; border-color: rgba(16, 185, 129, 0.2);"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></div>
                <h3 style="font-size: 18px;">Usage Overview</h3>
            </div>
            
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <div style="display: flex; justify-content: space-between; padding: 12px 16px; background: rgba(0,0,0,0.2); border-radius: 12px;">
                    <span class="text-muted" style="font-weight: 500;">Active Users</span>
                    <span style="color:#4ADE80; font-weight:700; font-size: 16px;"><?= $total_users - $expired_users ?> <span style="font-size:12px; opacity:0.6; font-weight:normal;">/ <?= $total_users ?></span></span>
                </div>
                <div style="width: 100%; height: 6px; background: rgba(255,255,255,0.05); border-radius: 10px; overflow: hidden; margin-top: -6px; margin-bottom: 6px;">
                    <div style="width: <?= $total_users > 0 ? (($total_users - $expired_users)/$total_users)*100 : 0 ?>%; height: 100%; background: linear-gradient(90deg, #34D399, #10B981); border-radius: 10px; box-shadow: 0 0 10px rgba(52,211,153,0.5);"></div>
                </div>

                <div style="display: flex; justify-content: space-between; padding: 12px 16px; background: rgba(0,0,0,0.2); border-radius: 12px;">
                    <span class="text-muted" style="font-weight: 500;">Monthly Rate</span>
                    <span style="color:#60A5FA; font-weight:700; font-size: 16px;">$<?= money_fmt($monthly_rate) ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 12px 16px; background: linear-gradient(90deg, rgba(251, 191, 36, 0.1), transparent); border-radius: 12px; border-left: 2px solid #FBBF24;">
                    <span style="font-weight: 600; color: #FBBF24;">Est. Monthly Cost</span>
                    <span style="color:#FBBF24; font-weight:800; font-size: 18px;">$<?= money_fmt($total_users * $monthly_rate) ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Users Table -->
    <div class="premium-card delay-4">
        <div class="flex-between" style="margin-bottom: 20px;">
            <div class="flex-center">
                <div class="stat-icon" style="width: 36px; height: 36px; font-size: 18px; background: rgba(165, 180, 252, 0.1); color: #A5B4FC;"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
                <h3 style="font-size: 18px;">Recent Users</h3>
            </div>
            <a href="?page=reseller_users" class="action-btn btn-small">View All →</a>
        </div>
        
        <div style="overflow-x: auto;">
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
                        <td style="font-weight: 500; color: #fff;"><?= e($u['email']) ?></td>
                        <td class="text-muted"><?= e($u['product_profile'] ?: 'N/A') ?></td>
                        <td class="text-muted"><?= e($u['expires_at'] ?: 'N/A') ?></td>
                        <td><span class="status-badge <?= $status_class ?>"><?= $status_text ?></span></td>
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

    <!-- Recent Transactions Table -->
    <div class="premium-card delay-4">
        <div class="flex-between" style="margin-bottom: 20px;">
            <div class="flex-center">
                <div class="stat-icon" style="width: 36px; height: 36px; font-size: 18px; background: rgba(96, 165, 250, 0.1); color: #60A5FA;"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/><path d="M16 16h.01"/></svg></div>
                <h3 style="font-size: 18px;">Recent Transactions</h3>
            </div>
            <a href="?page=reseller_billing" class="action-btn btn-small">View All →</a>
        </div>
        
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th style="text-align: right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_transactions as $t): ?>
                    <tr>
                        <td class="text-muted"><?= e(date('M j, Y • H:i', strtotime($t['created_at']))) ?></td>
                        <td>
                            <span class="status-badge <?= 
                                $t['type'] === 'payment' ? 'b-glow-green' : 
                                ($t['type'] === 'charge' ? 'b-glow-yellow' : 'b-glow-blue') 
                            ?>">
                                <?= e(ucfirst($t['type'])) ?>
                            </span>
                        </td>
                        <td class="text-muted"><?= e($t['description']) ?></td>
                        <td style="text-align: right; font-weight: 700; color:<?= $t['type'] === 'payment' ? '#4ADE80' : '#F87171' ?>">
                            <?= $t['type'] === 'payment' ? '+' : '-' ?> $<?= money_fmt($t['amount']) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($recent_transactions)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 30px;">No recent transactions.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- MOBILE DASHBOARD -->
<style>
.mobile-dashboard {
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
    background: #ffffff;
    min-height: 100vh;
    padding-bottom: 80px; /* space for bottom nav if any */
}

/* Header */
.md-header {
    background: linear-gradient(135deg, #7b61f8, #6345ec);
    border-bottom-left-radius: 20px;
    border-bottom-right-radius: 20px;
    padding: 24px 20px 30px;
    color: white;
}
.md-topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}
.md-profile {
    display: flex;
    align-items: center;
    gap: 12px;
}
.md-avatar {
    width: 44px; height: 44px;
    border-radius: 50%;
    background: #ccc;
    object-fit: cover;
}
.md-greeting {
    font-size: 13px;
    opacity: 0.9;
    margin: 0;
}
.md-name {
    font-size: 18px;
    font-weight: 700;
    margin: 0;
    line-height: 1.2;
}
.md-top-actions {
    display: flex;
    gap: 12px;
}
.md-icon-btn {
    width: 40px; height: 40px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.15);
    display: flex; align-items: center; justify-content: center;
    transition: 0.2s;
    text-decoration: none;
    color: white;
}

.md-revenue-label {
    font-size: 14px;
    opacity: 0.9;
    margin-bottom: 4px;
}
.md-revenue-val {
    font-size: 34px;
    font-weight: 800;
    margin: 0 0 20px;
    letter-spacing: -1px;
}

/* Quick Actions (4 top buttons) */
.md-quick-actions {
    display: flex;
    gap: 10px;
    justify-content: space-between;
}
.md-q-action {
    flex: 1;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 12px 6px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    color: white;
    font-size: 11px;
    font-weight: 600;
    text-align: center;
}
.md-q-action svg { stroke-width: 1.5; font-size: 20px; }

/* Main Content Area */
.md-content {
    padding: 20px;
    margin-top: -10px;
}

/* Banner */
.md-banner {
    background: linear-gradient(135deg, #a797ff 0%, #b8aaf9 100%);
    border-radius: 12px;
    padding: 20px;
    color: white;
    margin-bottom: 20px;
    position: relative;
    border: 2px solid #5ab0ff;
    overflow: hidden;
}
.md-banner h3 {
    margin: 0 0 16px;
    font-size: 16px;
    font-weight: 700;
    max-width: 180px;
    line-height: 1.3;
}
.md-banner a {
    display: inline-flex; align-items: center; gap: 8px;
    background: white; color: #333;
    padding: 6px 14px; border-radius: 20px;
    font-size: 12px; font-weight: 700; text-decoration: none;
}
.md-banner a svg {
    background: #111; color: white; border-radius: 50%; padding: 3px; font-size: 14px;
}

/* Tabs */
.md-tabs {
    background: #33363f;
    border-radius: 30px;
    display: flex;
    margin-bottom: 20px;
    padding: 4px;
}
.md-tab {
    flex: 1; text-align: center;
    font-size: 13px; font-weight: 600;
    color: #999; padding: 10px 0;
    border-radius: 24px; transition: 0.2s;
}
.md-tab.active {
    background: #474a54; color: white;
}

/* 4 Grid Cards */
.md-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 24px;
}
.md-metric-card {
    border-radius: 16px;
    padding: 16px;
    color: white;
    position: relative;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 140px;
}
.md-mc-1 { background: linear-gradient(180deg, #6c5a61, #514450); }
.md-mc-2 { background: linear-gradient(180deg, #445963, #33444e); }
.md-mc-3 { background: linear-gradient(180deg, #515c6e, #3a4454); }
.md-mc-4 { background: linear-gradient(180deg, #4b3658, #34243f); }

.md-metric-top { display: flex; align-items: flex-start; gap: 8px; margin-bottom: 12px; font-size: 13px; font-weight: 600; opacity: 0.8; }
.md-metric-top .icon { background: rgba(255,255,255,0.2); width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
.md-metric-val { font-size: 26px; font-weight: 800; margin-bottom: 4px; }
.md-metric-sub { font-size: 11px; opacity: 0.7; }

/* Section Title */
.md-section-title {
    font-size: 18px;
    font-weight: 800;
    color: #222;
    margin: 0 0 16px;
}

/* Horizontal Scroll - Recent Users */
.md-h-scroll {
    display: flex;
    overflow-x: auto;
    gap: 12px;
    padding-bottom: 10px;
    margin-bottom: 14px;
    margin-right: -20px;
    padding-right: 20px;
}
.md-h-scroll::-webkit-scrollbar { display: none; }
.md-item-card {
    min-width: 140px;
    background: #392842;
    border-radius: 16px;
    padding: 4px;
    color: white;
    flex-shrink: 0;
}
.md-item-img {
    height: 100px;
    background: linear-gradient(45deg, #184c4e, #1b6859);
    border-radius: 12px;
    margin-bottom: 8px;
    display: flex; align-items: center; justify-content: center; font-size: 32px;
}
.md-item-info {
    padding: 4px 8px 8px;
}
.md-item-title { font-size: 14px; font-weight: 700; margin: 0 0 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.md-item-subtitle { font-size: 11px; opacity: 0.6; display: flex; justify-content: space-between; }

/* Order Analytics (Usage Overview) */
.md-analytics {
    display: flex;
    gap: 12px;
    margin-bottom: 24px;
}
.md-stat-box {
    flex: 1;
    background: #8e80d4;
    border-radius: 12px;
    padding: 12px;
    color: white;
    display: flex; flex-direction: column; justify-content: space-between;
    min-height: 80px;
}
.md-stat-box-1 { background: #887bbb; }
.md-stat-box-2 { background: #6e58b8; }
.md-stat-box-3 { background: #bbaae1; color: #333; }

.md-stat-icon { font-size: 18px; margin-bottom: 10px; }
.md-stat-bottom { text-align: right; }
.md-stat-num { font-size: 20px; font-weight: 800; line-height: 1; }
.md-stat-text { font-size: 11px; opacity: 0.9; }

/* Table Performance (Transactions) */
.md-table-perf {
    background: #2b2829;
    border-radius: 16px;
    padding: 20px;
    color: white;
    margin-bottom: 20px;
}
.md-tp-title {
    font-size: 16px; font-weight: 700; margin: 0 0 16px;
}
.md-tp-cards {
    display: flex;
    gap: 12px;
    overflow-x: auto;
}
.md-tp-cards::-webkit-scrollbar { display: none; }
.md-tp-card {
    min-width: 140px;
    background: rgba(255,255,255,0.05);
    border-radius: 12px;
    padding: 12px;
    position: relative;
    overflow: hidden;
}
.md-tp-num { 
    background: #46344d; display: inline-flex; width: 24px; height: 24px;
    align-items: center; justify-content: center; border-radius: 6px; font-size: 12px; font-weight: 700; 
    margin-bottom: 20px;
}
.md-tp-stat { font-size: 12px; opacity: 0.7; margin-bottom: 4px; }
.md-tp-val { font-size: 18px; font-weight: 800; }
.md-tp-img { position: absolute; right: -10px; bottom: 0; opacity: 0.5; font-size: 50px; }

/* Announcements Mobile Theme */
.md-ann-list { margin-bottom: 24px; }
.md-ann-card { background: #f8f9fa; border-left: 4px solid #4a90e2; padding: 12px; border-radius: 8px; margin-bottom: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
.md-ann-title { font-size: 14px; font-weight: 700; margin: 0 0 4px; color: #333; }
.md-ann-desc { font-size: 12px; color: #666; margin: 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

/* Bottom Nav (Mock) */
.md-bottom-nav {
    position: fixed;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    background: #333;
    border-radius: 30px;
    display: flex;
    padding: 8px 16px;
    gap: 20px;
    box-shadow: 0 10px 20px rgba(0,0,0,0.3);
    z-index: 100;
}
.md-nav-item {
    width: 44px; height: 44px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    color: white; font-size: 20px; text-decoration: none;
}
.md-nav-item.active { background: rgba(255,255,255,0.2); }
</style>

<div class="mobile-dashboard">
    <div class="md-header">
        <div class="md-topbar">
            <div class="md-profile">
                <!-- Use a generic avatar SVG since we don't have user pictures -->
                <div class="md-avatar" style="background: #e2e8f0; display:flex; align-items:center; justify-content:center;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                </div>
                <div>
                    <h5 class="md-greeting">hello</h5>
                    <h2 class="md-name"><?= e($_SESSION['username']) ?></h2>
                </div>
            </div>
            <div class="md-top-actions">
                <a href="#" class="md-icon-btn"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><rect x="7" y="7" width="3" height="3"></rect><rect x="14" y="7" width="3" height="3"></rect><rect x="7" y="14" width="3" height="3"></rect><rect x="14" y="14" width="3" height="3"></rect></svg></a>
                <a href="#" class="md-icon-btn"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg></a>
            </div>
        </div>
        
        <div class="md-revenue-label">Current Balance</div>
        <h1 class="md-revenue-val">$<?= money_fmt($balance) ?></h1>
        
        <div class="md-quick-actions">
            <a href="?page=reseller_assign" class="md-q-action">
                <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Assign User
            </a>
            <a href="?page=reseller_bulk_assign" class="md-q-action">
                <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                Bulk Assign
            </a>
            <a href="?page=reseller_users" class="md-q-action">
                <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Users
            </a>
            <a href="?page=reseller_billing" class="md-q-action">
                <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                Billing
            </a>
        </div>
    </div>
    
    <div class="md-content">
        <!-- Banner (Mapping Announcements here if any) -->
        <div class="md-banner">
            <?php if (!empty($announcements)): $a = $announcements[0]; ?>
                <h3 style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= e($a['title']) ?></h3>
                <a href="?page=reseller_announcements">
                    Read Update <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </a>
            <?php else: ?>
                <h3>Your Business Will Rise, Before Next Sunrise</h3>
                <a href="?page=reseller_assign">
                    Make It Yours <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </a>
            <?php endif; ?>
        </div>

        <!-- Filter Tabs (Static visual copy) -->
        <div class="md-tabs">
            <div class="md-tab active">Today</div>
            <div class="md-tab">Week</div>
            <div class="md-tab">Month</div>
            <div class="md-tab">Year</div>
        </div>

        <!-- 4 Grid Stats -->
        <div class="md-grid">
            <div class="md-metric-card md-mc-1">
                <div class="md-metric-top">
                    <div class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="8" width="18" height="12" rx="2"/><path d="M7 8v-2a5 5 0 0 1 10 0v2"/></svg></div>
                    Total Users
                </div>
                <div>
                    <div class="md-metric-val"><?= $total_users ?></div>
                    <div class="md-metric-sub"><?= $total_users - $expired_users ?> Active</div>
                </div>
            </div>
            
            <div class="md-metric-card md-mc-2">
                <div class="md-metric-top">
                    <div class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
                    Monthly Rate
                </div>
                <div>
                    <div class="md-metric-val">$<?= money_fmt($monthly_rate) ?></div>
                    <div class="md-metric-sub">Est. Cost: $<?= money_fmt($total_users * $monthly_rate) ?></div>
                </div>
            </div>

            <div class="md-metric-card md-mc-3">
                <div class="md-metric-top">
                    <div class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
                    Expiring Soon
                </div>
                <div>
                    <div class="md-metric-val"><?= $expiring_soon ?></div>
                    <div class="md-metric-sub">Within 7 days</div>
                </div>
            </div>

            <div class="md-metric-card md-mc-4">
                <div class="md-metric-top">
                    <div class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
                    Expired
                </div>
                <div>
                    <div class="md-metric-val"><?= $expired_users ?></div>
                    <div class="md-metric-sub">Need renewal</div>
                </div>
            </div>
        </div>

        <!-- Recent Users (Top Selling Items style) -->
        <h3 class="md-section-title">Recent Users</h3>
        <div class="md-h-scroll">
            <?php foreach ($recent_users as $u): 
                $today = date('Y-m-d');
                $is_expired = (!empty($u['expires_at']) && $u['expires_at'] < $today);
                $status_color = $is_expired ? '#f87171' : '#4ade80';
            ?>
            <div class="md-item-card">
                <div class="md-item-img" style="background: <?= $is_expired ? 'linear-gradient(45deg, #4e1818, #681b1b)' : 'linear-gradient(45deg, #184c4e, #1b6859)' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity:0.3; color:white;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                </div>
                <div class="md-item-info">
                    <h4 class="md-item-title"><?= e(explode('@', $u['email'])[0]) ?></h4>
                    <div class="md-item-subtitle">
                        <span><?= e($u['product_profile'] ?: 'No App') ?></span>
                        <span style="color: <?= $status_color ?>; font-weight:700;">
                            <?= $is_expired ? 'Expired' : 'Active' ?>
                        </span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if(empty($recent_users)): ?>
                <div style="padding: 20px; text-align: center; width: 100%; color: #999;">No recent users</div>
            <?php endif; ?>
        </div>

        <!-- Order Analytics (Usage Specs) -->
        <h3 class="md-section-title">Overview Stats</h3>
        <div class="md-analytics">
            <div class="md-stat-box md-stat-box-1">
                <div class="md-stat-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
                <div class="md-stat-bottom">
                    <div class="md-stat-num"><?= $total_users - $expired_users ?></div>
                    <div class="md-stat-text">Active Users</div>
                </div>
            </div>
            <div class="md-stat-box md-stat-box-2">
                <div class="md-stat-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
                <div class="md-stat-bottom">
                    <div class="md-stat-num"><?= $expiring_soon ?></div>
                    <div class="md-stat-text">Expiring Soon</div>
                </div>
            </div>
            <div class="md-stat-box md-stat-box-3">
                <div class="md-stat-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/><path d="M16 16h.01"/></svg></div>
                <div class="md-stat-bottom">
                    <div class="md-stat-num">$<?= money_fmt($total_users * $monthly_rate) ?></div>
                    <div class="md-stat-text">Monthly Cost</div>
                </div>
            </div>
        </div>

        <!-- Table Performance (Recent Transactions) -->
        <div class="md-table-perf">
            <h3 class="md-tp-title">Recent Transactions</h3>
            <div class="md-tp-cards">
                <?php foreach ($recent_transactions as $index => $t): ?>
                <div class="md-tp-card">
                    <div class="md-tp-num"><?= $index + 1 ?></div>
                    <div class="md-tp-stat"><?= e(ucfirst($t['type'])) ?></div>
                    <div class="md-tp-val" style="color: <?= $t['type'] === 'payment' ? '#4ade80' : ($t['type'] === 'charge' ? '#fbbf24' : '#60a5fa') ?>">
                        <?= $t['type'] === 'payment' ? '+' : '-' ?>$<?= money_fmt($t['amount']) ?>
                    </div>
                    <!-- Icon backdrop -->
                    <div class="md-tp-img"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
                </div>
                <?php endforeach; ?>
                <?php if(empty($recent_transactions)): ?>
                    <div style="opacity: 0.5; font-size: 13px;">No recent transactions.</div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Announcements mapped as list if more than 1 -->
        <?php if (count($announcements) > 1): ?>
        <h3 class="md-section-title" style="margin-top: 20px;">Announcements</h3>
        <div class="md-ann-list">
            <?php foreach (array_slice($announcements, 1, 3) as $a): ?>
            <div class="md-ann-card">
                <h4 class="md-ann-title"><?= e($a['title']) ?></h4>
                <p class="md-ann-desc"><?= e($a['content']) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>
    
    <!-- Fix dummy bottom nav just for presentation matches screenshot -->
    <div class="md-bottom-nav">
        <a href="?page=reseller_dashboard" class="md-nav-item active"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></a>
        <a href="?page=reseller_users" class="md-nav-item"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></a>
        <a href="?page=reseller_billing" class="md-nav-item"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/><path d="M16 16h.01"/></svg></a>
        <a href="?page=reseller_announcements" class="md-nav-item"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg></a>
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