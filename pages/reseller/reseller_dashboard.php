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
/* Beyond Imagination - Ultra Premium Glass & Cyan-Blue Dashboard */
.premium-dashboard {
    --bg-main: transparent;
    --card-bg: rgba(15, 18, 25, 0.45);
    --card-border: rgba(70, 177, 247, 0.15);
    --text-main: #f1f5f9;
    --text-muted: #94a3b8;
    --accent-blue: #46B1F7;
    --accent-blue-hover: #2b9bee;
    --accent-blue-glow: rgba(70, 177, 247, 0.35);
    --radius-lg: 24px;
    --radius-md: 16px;
    --transition: all 0.5s cubic-bezier(0.2, 0.8, 0.2, 1);
    
    color: var(--text-main);
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
    position: relative;
    z-index: 1;
}

/* Ambient Background Glows */
.premium-dashboard::before,
.premium-dashboard::after {
    content: '';
    position: fixed;
    border-radius: 50%;
    filter: blur(120px);
    z-index: -1;
    opacity: 0.15;
    pointer-events: none;
    animation: floatingGlow 15s ease-in-out infinite alternate;
}
.premium-dashboard::before {
    top: -10%; left: -10%;
    width: 600px; height: 600px;
    background: radial-gradient(circle, var(--accent-blue) 0%, transparent 70%);
}
.premium-dashboard::after {
    bottom: -10%; right: -10%;
    width: 500px; height: 500px;
    background: radial-gradient(circle, #8b5cf6 0%, transparent 70%);
    animation-delay: -5s;
}

@keyframes floatingGlow {
    0% { transform: translate(0, 0) scale(1); opacity: 0.1; }
    50% { transform: translate(50px, 30px) scale(1.1); opacity: 0.2; }
    100% { transform: translate(-30px, 50px) scale(0.9); opacity: 0.15; }
}

.premium-dashboard .premium-card {
    background: var(--card-bg);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border: 1px solid var(--card-border);
    border-radius: var(--radius-lg);
    padding: 28px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2), inset 0 1px 0 rgba(255, 255, 255, 0.05);
    transition: var(--transition);
    position: relative;
    overflow: hidden;
    animation: fadeUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) backwards;
    margin-bottom: 24px;
}

.premium-dashboard .premium-card::after {
    content: '';
    position: absolute;
    top: 0; left: -150%; width: 50%; height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.03), transparent);
    transform: skewX(-20deg);
    transition: 0.7s;
}

.premium-dashboard .premium-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4), 0 0 30px var(--accent-blue-glow);
    border-color: rgba(70, 177, 247, 0.4);
    background: rgba(20, 25, 35, 0.6);
}

.premium-dashboard .premium-card:hover::after {
    left: 200%;
}

@keyframes fadeUp {
    from { opacity: 0; transform: translateY(40px) scale(0.98); filter: blur(5px); }
    to { opacity: 1; transform: translateY(0) scale(1); filter: blur(0); }
}

@keyframes pulseGlow {
    0% { box-shadow: 0 0 0 0 rgba(70, 177, 247, 0.5); }
    70% { box-shadow: 0 0 0 12px rgba(70, 177, 247, 0); }
    100% { box-shadow: 0 0 0 0 rgba(70, 177, 247, 0); }
}

.delay-1 { animation-delay: 0.1s; }
.delay-2 { animation-delay: 0.2s; }
.delay-3 { animation-delay: 0.3s; }
.delay-4 { animation-delay: 0.4s; }

.premium-dashboard .flex-between { display: flex; align-items: center; justify-content: space-between; gap: 15px; flex-wrap: wrap; }
.premium-dashboard .flex-center { display: flex; align-items: center; gap: 12px; }

/* Status Badges */
.premium-dashboard .status-badge {
    padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; letter-spacing: 0.5px;
    display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; border: 1px solid transparent;
    transition: var(--transition);
    backdrop-filter: blur(10px);
}
.premium-dashboard .status-badge:hover { transform: scale(1.05); }

.b-glow-blue { background: rgba(70, 177, 247, 0.1); color: #46B1F7; border-color: rgba(70, 177, 247, 0.3); box-shadow: 0 0 15px rgba(70, 177, 247, 0.15); }
.b-glow-green { background: rgba(52, 211, 153, 0.1); color: #34d399; border-color: rgba(52, 211, 153, 0.3); box-shadow: 0 0 15px rgba(52, 211, 153, 0.15); }
.b-glow-red { background: rgba(248, 113, 113, 0.1); color: #f87171; border-color: rgba(248, 113, 113, 0.3); box-shadow: 0 0 15px rgba(248, 113, 113, 0.15); }
.b-glow-yellow { background: rgba(251, 191, 36, 0.1); color: #fbbf24; border-color: rgba(251, 191, 36, 0.3); box-shadow: 0 0 15px rgba(251, 191, 36, 0.15); }

/* Typography */
.premium-dashboard .text-muted { color: var(--text-muted); font-size: 14px; font-weight: 400; }
.premium-dashboard h2, .premium-dashboard h3 { margin: 0; font-weight: 800; color: #FFFFFF; letter-spacing: -0.5px; }
.premium-dashboard .gradient-text {
    background: linear-gradient(135deg, #ffffff 10%, #46B1F7 100%);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    text-shadow: 0 2px 20px rgba(70, 177, 247, 0.2);
}

/* Stats */
.premium-dashboard .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 24px; margin-bottom: 24px; }
.premium-dashboard .stat-icon {
    width: 64px; height: 64px; border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 28px;
    background: linear-gradient(135deg, rgba(70, 177, 247, 0.1) 0%, rgba(70, 177, 247, 0.02) 100%);
    border: 1px solid rgba(70, 177, 247, 0.25); 
    box-shadow: inset 0 0 20px rgba(70, 177, 247, 0.05), 0 8px 16px rgba(0,0,0,0.2);
    transition: var(--transition);
}
.premium-dashboard .premium-card:hover .stat-icon {
    transform: scale(1.1) rotate(5deg);
    border-color: rgba(70, 177, 247, 0.5);
    box-shadow: inset 0 0 20px rgba(70, 177, 247, 0.15), 0 8px 24px rgba(70, 177, 247, 0.2);
}

.premium-dashboard .stat-val { font-size: 38px; font-weight: 800; margin: 4px 0 0 0; line-height: 1.1; letter-spacing: -1.5px; }

/* Grid Layout */
.premium-dashboard .split-row { display: grid; grid-template-columns: 1fr; gap: 24px; margin-bottom: 24px; }
@media(min-width: 900px) { .premium-dashboard .split-row { grid-template-columns: 1fr 1fr; } }

/* Buttons */
.premium-dashboard .action-btn {
    display: flex; align-items: center; justify-content: center; gap: 10px;
    background: rgba(70, 177, 247, 0.05); border: 1px solid rgba(70, 177, 247, 0.2); padding: 16px;
    border-radius: var(--radius-md); color: var(--text-main); text-decoration: none; font-weight: 600; font-size: 15px; 
    transition: var(--transition); position: relative; overflow: hidden; backdrop-filter: blur(8px);
}
.premium-dashboard .action-btn::before {
    content: ''; position: absolute; inset: 0; background: linear-gradient(135deg, var(--accent-blue), #8b5cf6);
    opacity: 0; transition: var(--transition); z-index: -1;
}
.premium-dashboard .action-btn:hover { 
    border-color: transparent; box-shadow: 0 8px 25px var(--accent-blue-glow); color: #fff; transform: translateY(-3px);
}
.premium-dashboard .action-btn:hover::before { opacity: 1; }

.premium-dashboard .btn-small { padding: 10px 20px; font-size: 14px; border-radius: 12px; }

/* Tables */
.premium-dashboard table { width: 100%; border-collapse: separate; border-spacing: 0; min-width: 600px; }
.premium-dashboard th, .premium-dashboard td { padding: 18px 20px; text-align: left; font-size: 15px; }
.premium-dashboard th { 
    background: rgba(70, 177, 247, 0.08); font-size: 12px; text-transform: uppercase; 
    color: #46B1F7; font-weight: 800; letter-spacing: 1.5px;
    border-bottom: 1px solid rgba(70, 177, 247, 0.2);
}
.premium-dashboard th:first-child { border-top-left-radius: 12px; }
.premium-dashboard th:last-child { border-top-right-radius: 12px; }
.premium-dashboard tbody tr { transition: var(--transition); border-bottom: 1px solid rgba(255,255,255,0.02); }
.premium-dashboard tbody tr td { border-bottom: 1px solid rgba(255,255,255,0.03); transition: var(--transition); }
.premium-dashboard tbody tr:last-child td { border-bottom: none; }
.premium-dashboard tbody tr:hover td { background: rgba(70, 177, 247, 0.05); color: #fff; }
.premium-dashboard tbody tr:hover td:first-child { border-top-left-radius: 8px; border-bottom-left-radius: 8px; }
.premium-dashboard tbody tr:hover td:last-child { border-top-right-radius: 8px; border-bottom-right-radius: 8px; }

/* Announcements */
.premium-dashboard .announcement-card {
    padding: 20px; border-radius: var(--radius-md); position: relative;
    border: 1px solid rgba(70, 177, 247, 0.1); margin-bottom: 16px; transition: var(--transition);
    background: linear-gradient(135deg, rgba(20, 25, 35, 0.6) 0%, rgba(15, 18, 25, 0.8) 100%);
    backdrop-filter: blur(12px);
}
.premium-dashboard .announcement-card:hover { 
    transform: translateX(8px) translateY(-2px); 
    background: linear-gradient(135deg, rgba(30, 40, 55, 0.7) 0%, rgba(20, 25, 35, 0.9) 100%);
    border-color: rgba(70, 177, 247, 0.4);
    box-shadow: 0 10px 30px rgba(0,0,0,0.2), -5px 0 20px var(--accent-blue-glow);
}
.premium-dashboard .a-new { 
    position: absolute; top: -10px; right: 20px; background: linear-gradient(135deg, #46B1F7, #2563eb); 
    color: #fff; padding: 4px 12px; border-radius: 8px; font-size: 11px; font-weight: 800; 
    box-shadow: 0 4px 15px rgba(70, 177, 247, 0.5); animation: pulseGlow 2s infinite; letter-spacing: 1px;
}

.a-info { border-left: 4px solid #46B1F7; }
.a-success { border-left: 4px solid #34d399; }
.a-warning { border-left: 4px solid #fbbf24; }
.a-danger { border-left: 4px solid #f87171; }
</style>

<div class="premium-dashboard">

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
                <div class="stat-icon" style="width: 44px; height: 44px; font-size: 20px; background: rgba(70,177,247,0.1); border-color: rgba(70,177,247,0.2); color: #46B1F7;"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg></div>
                <h3 style="font-size: 22px;">Announcements & Updates</h3>
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
                <a href="?page=reseller_announcements" class="action-btn" style="display: inline-flex; background: rgba(70, 177, 247, 0.05); border-color: rgba(70, 177, 247, 0.2); color: #46B1F7;">
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
                <div class="stat-icon" style="color: #46B1F7; background: rgba(70, 177, 247, 0.1); border-color: rgba(70, 177, 247, 0.2);"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
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
                    <span style="color:#46B1F7; font-weight:700; font-size: 16px;">$<?= money_fmt($monthly_rate) ?></span>
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
                <div class="stat-icon" style="width: 40px; height: 40px; font-size: 20px; background: rgba(70, 177, 247, 0.1); color: #46B1F7;"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
                <h3 style="font-size: 20px;">Recent Users</h3>
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
                <div class="stat-icon" style="width: 40px; height: 40px; font-size: 20px; background: rgba(70, 177, 247, 0.1); color: #46B1F7;"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/><path d="M16 16h.01"/></svg></div>
                <h3 style="font-size: 20px;">Recent Transactions</h3>
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