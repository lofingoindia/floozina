<?php
// META: {"title": "Users", "order": 20, "nav": true, "hidden": false}
// NOTE: session_start() mat lagana, app.php already start karta hai

$rid    = (int)($_SESSION['user_id'] ?? 0);
$role   = (string)($_SESSION['role'] ?? '');
$status = (string)($_SESSION['status'] ?? '');

if ($rid <= 0 || $role !== 'reseller') {
    echo "<div class='card' style='padding:12px'>Please login as reseller again.</div>";
    return;
}

$q = trim((string)($_GET['q'] ?? ''));
$users = get_my_users($pdo, $rid, $q);

// Helper: refundable status
function user_refund_status(PDO $pdo, int $rid, array $u): array {
    // returns: [label, cssClass]
    $userId = (int)($u['id'] ?? 0);

    try {
        $st = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE reseller_id=? AND user_id=? AND type='refund'");
        $st->execute([$rid, $userId]);
        $already = (int)$st->fetchColumn() > 0;
        if ($already) return ['Already Refunded', '#e74c3c'];
    } catch (Throwable $e) {}

    $until = (string)($u['refund_eligible_until'] ?? '');
    if ($until === '') return ['Not Refundable', '#e74c3c'];

    $ts = strtotime($until);
    if (!$ts) return ['Not Refundable', '#e74c3c'];

    if (time() <= $ts) {
        $left = $ts - time();
        $hrs = (int)floor($left / 3600);
        if ($hrs < 0) $hrs = 0;
        return ["Refundable (~{$hrs}h)", '#2ecc71'];
    }

    return ['Expired Window', '#e74c3c'];
}

$lang = app_lang() ?? 'en';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* Hide everything default layout provided */
.sidebar, .topbar { display: none !important; }
main.main { margin-left: 0 !important; padding: 0 !important; background: #060606 !important; width: 100vw !important; height: 100vh !important; overflow-y: auto; overflow-x: hidden; }
div.content { padding: 0 !important; margin: 0 !important; max-width: 100% !important; border: none !important; box-shadow: none !important; border-radius: 0 !important;}

/* Mobile first layout */
.u-app-container {
    width: 100%;
    min-height: 100vh;
    background: #060606;
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
    color: #fff;
    padding-bottom: 90px;
}

.u-header {
    background: linear-gradient(145deg, #7b51e0 0%, #9061ff 100%);
    border-bottom-left-radius: 36px;
    border-bottom-right-radius: 36px;
    padding: 24px 20px 48px 20px;
    position: relative;
}

.u-header-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.u-header-title {
    font-size: 26px;
    font-weight: 700;
    margin: 0;
    color: #fff;
}

.u-header-actions {
    display: flex;
    gap: 10px;
}

.u-icon-btn {
    background: rgba(255,255,255,0.2);
    border: none;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    transition: background 0.3s;
}
.u-icon-btn.active {
    background: #fff;
    color: #7b51e0;
}

.u-search-bar {
    display: none;
    width: 100%;
    margin-top: 16px;
    flex-wrap: nowrap;
}
.u-search-bar.active {
    display: flex;
}

.u-input {
    width: 100%;
    padding: 12px 20px;
    border-radius: 20px;
    border: none;
    background: rgba(255,255,255,0.95);
    color: #333;
    font-size: 15px;
    outline: none;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}

.u-filters {
    display: flex;
    gap: 10px;
    padding: 0 20px;
    margin-top: -24px;
    margin-bottom: 24px;
    overflow-x: auto;
    scrollbar-width: none;
    z-index: 10;
    position: relative;
}
.u-filters::-webkit-scrollbar { display: none; }

.u-filter-btn {
    background: #20242d;
    color: #8f95a3;
    border: none;
    padding: 10px 24px;
    border-radius: 24px;
    font-weight: 600;
    font-size: 14px;
    white-space: nowrap;
    cursor: pointer;
    text-decoration: none;
}
.u-filter-btn.active {
    background: #383d47;
    color: #fff;
}

.u-list {
    padding: 0 20px;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.u-card {
    background: #ffffff;
    border-radius: 24px;
    padding: 20px;
    color: #1a1a24;
    position: relative;
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

.u-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 16px;
}

.u-email-col {
    display:flex;
    flex-direction: column;
    gap: 4px;
}

.u-email {
    font-size: 16px;
    font-weight: 800;
    color: #111;
    margin: 0;
    word-break: break-all;
}

.u-profile {
    font-size: 14px;
    color: #555;
    margin: 0;
}

.u-status {
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 700;
    background: #f0f0f0;
    white-space: nowrap;
}
.u-status.active { color: #2ecc71; background: rgba(46,204,113,0.1); }
.u-status.expired { color: #e74c3c; background: rgba(231,76,60,0.1); }
.u-status.expiring { color: #f39c12; background: rgba(243,156,18,0.1); }

.u-details {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 16px;
}

.u-detail-row {
    display: flex;
    align-items: center;
    gap: 8px;
}
.u-detail-icon {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f5f5f5;
    border-radius: 50%;
    color: #555;
    font-size: 12px;
}
.u-detail-text {
    font-size: 14px;
    color: #444;
}

.u-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
    border-top: 1px dashed #eee;
    padding-top: 16px;
}

.u-extend-form {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 100%;
    margin-bottom: 10px;
}

.u-date-input {
    flex: 1;
    padding: 10px 14px;
    border-radius: 12px;
    border: 1px solid #ddd;
    font-size: 13px;
    outline: none;
    color: #333;
}

.u-btn {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 6px;
    background: #fafafa;
    border: none;
    padding: 14px 0;
    border-radius: 16px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    color: #555;
    transition: background 0.3s;
    text-decoration: none;
}
.u-btn:hover { filter: brightness(0.95); }

.u-btn.extend { color: #2ecc71; background: rgba(46,204,113,0.1); flex: unset; padding: 10px 20px; border-radius: 12px; }
.u-btn.delete { color: #e74c3c; background: rgba(231,76,60,0.1); }
.u-btn.view { color: #7b51e0; background: rgba(123,81,224,0.1); }

/* Floating Bottom Nav */
.u-bottom-nav {
    position: fixed;
    bottom: 24px;
    left: 50%;
    transform: translateX(-50%);
    background: #2a2d36;
    border-radius: 36px;
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 12px 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    z-index: 100;
}
.u-nav-item {
    color: #8f95a3;
    font-size: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    text-decoration: none;
    transition: all 0.3s;
}
.u-nav-item.active {
    background: rgba(255,255,255,0.15);
    color: #fff;
}
</style>

<div class="u-app-container">
    <div class="u-header">
        <div class="u-header-top">
            <h1 class="u-header-title"><?= t('Users', 'المستخدمون') ?></h1>
            <div class="u-header-actions">
                <a href="?page=reseller_users&lang=en" class="u-icon-btn <?= $lang==='en'?'active':'' ?>">EN</a>
                <a href="?page=reseller_users&lang=ar" class="u-icon-btn <?= $lang==='ar'?'active':'' ?>">AR</a>
                <button type="button" class="u-icon-btn" onclick="document.getElementById('searchBar').classList.toggle('active')">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>
        
        <form method="get" class="u-search-bar <?= $q ? 'active' : '' ?>" id="searchBar">
            <input type="hidden" name="page" value="reseller_users">
            <input type="text" name="q" value="<?= e($q) ?>" class="u-input" placeholder="Search email...">
        </form>
    </div>

    <!-- Replicating the Tabs from the Image -->
    <div class="u-filters">
        <a href="?page=reseller_users" class="u-filter-btn active">All</a>
        <a href="?page=reseller_users&status=active" class="u-filter-btn">Active</a>
        <a href="?page=reseller_users&status=expired" class="u-filter-btn">Expired</a>
    </div>

    <?php if ($status === 'suspended'): ?>
    <div style="margin: 0 20px 20px; background: rgba(231,76,60,0.2); border: 1px solid rgba(231,76,60,0.4); color: #ff6b6b; padding: 12px 16px; border-radius: 12px; font-weight: 600; display:flex; align-items:center; gap:10px;">
        <i class="fas fa-ban"></i> Account is suspended. Actions disabled.
    </div>
    <?php endif; ?>

    <div class="u-list">
        <?php if (!$users || count($users) === 0): ?>
            <div style="text-align: center; color: #8f95a3; padding: 40px; font-size: 15px; background: #20242d; border-radius: 20px;">
                <i class="fas fa-folder-open" style="font-size: 40px; margin-bottom: 10px; opacity: 0.5;"></i><br>
                No users found.
            </div>
        <?php else: ?>
            <?php foreach ($users as $u):
                $st = (string)($u['status'] ?? '');
                $uid = (int)($u['id'] ?? 0);
                [$refundLabel, $refundColor] = user_refund_status($pdo, $rid, $u);
            ?>
            <div class="u-card">
                <div class="u-card-header">
                    <div class="u-email-col">
                        <h3 class="u-email"><?= e((string)($u['email'] ?? '')) ?></h3>
                        <p class="u-profile"><?= e((string)($u['product_profile'] ?? 'N/A')) ?></p>
                    </div>
                    <span class="u-status <?= $st ?>"><?= ucfirst(e($st)) ?></span>
                </div>

                <div class="u-details">
                    <div class="u-detail-row">
                        <div class="u-detail-icon"><i class="fas fa-calendar-alt"></i></div>
                        <span class="u-detail-text"><b>Expires:</b> <?= e((string)($u['expires_at'] ?? 'N/A')) ?></span>
                    </div>
                    <div class="u-detail-row">
                        <div class="u-detail-icon" style="color: <?= $refundColor ?>;"><i class="fas fa-undo-alt"></i></div>
                        <span class="u-detail-text" style="color: <?= $refundColor ?>; font-weight: 600;"><?= $refundLabel ?></span>
                    </div>
                </div>

                <div class="u-actions">
                    <form method="post" class="u-extend-form">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="reseller_extend_user">
                        <input type="hidden" name="user_id" value="<?= $uid ?>">
                        <input name="expires_at" type="date" class="u-date-input" value="<?= e(!empty($u['expires_at']) ? date('Y-m-d', strtotime((string)$u['expires_at'].' +1 month')) : date('Y-m-d', strtotime('+1 month'))) ?>" <?= ($status === 'suspended') ? 'disabled' : '' ?>>
                        <button type="submit" class="u-btn extend" <?= ($status === 'suspended') ? 'disabled' : '' ?>><i class="fas fa-check-circle"></i> Extend</button>
                    </form>

                    <div style="display:flex; gap:10px; width:100%;">
                        <button type="button" class="u-btn view"><i class="fas fa-info-circle"></i> Details</button>
                        
                        <form method="post" style="flex:1; margin:0;" onsubmit="return confirm('Delete user and refund?');">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="reseller_delete_user">
                            <input type="hidden" name="user_id" value="<?= $uid ?>">
                            <input type="hidden" name="remove_from_org" value="1">
                            <button type="submit" class="u-btn delete" style="width:100%;" <?= ($status === 'suspended') ? 'disabled' : '' ?>><i class="fas fa-times-circle"></i> Delete</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Bottom Navigation Bar -->
<div class="u-bottom-nav">
    <a href="?page=reseller_dashboard" class="u-nav-item"><i class="fas fa-home"></i></a>
    <a href="?page=reseller_users" class="u-nav-item active"><i class="fas fa-users"></i></a>
    <a href="?page=reseller_assign" class="u-nav-item u-nav-fab"><i class="fas fa-plus"></i></a>
    <a href="?page=reseller_billing" class="u-nav-item"><i class="fas fa-receipt"></i></a>
    <a href="javascript:void(0)" class="u-nav-item" onclick="document.querySelector('.logout-btn').click();"><i class="fas fa-user-circle"></i></a>
</div>

<script>
// Prevent default app styles from bleeding
document.body.style.backgroundColor = '#060606';
</script>