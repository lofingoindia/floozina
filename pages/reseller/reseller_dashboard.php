<?php
// META: {"title": "Dashboard", "order": 10, "nav": true, "hidden": false}
$reseller_id = (int)$_SESSION['user_id'];

// Stats
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE reseller_id=?");
$stmt->execute([$reseller_id]);
$total_users = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE reseller_id=? AND expires_at < CURDATE()");
$stmt->execute([$reseller_id]);
$expired_users = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE reseller_id=? AND expires_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)");
$stmt->execute([$reseller_id]);
$expiring_soon = (int)$stmt->fetchColumn();

$active_users = max(0, $total_users - $expired_users);

// Balance + rate
$stmt = $pdo->prepare("SELECT balance, monthly_rate FROM resellers WHERE id=?");
$stmt->execute([$reseller_id]);
$reseller_data = $stmt->fetch();
$balance       = (float)($reseller_data['balance'] ?? 0);
$monthly_rate  = (float)($reseller_data['monthly_rate'] ?? 1.0);

// Recent users
$stmt = $pdo->prepare("SELECT * FROM users WHERE reseller_id=? ORDER BY created_at DESC LIMIT 8");
$stmt->execute([$reseller_id]);
$recent_users = $stmt->fetchAll();

// Recent transactions
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE reseller_id=? ORDER BY created_at DESC LIMIT 6");
$stmt->execute([$reseller_id]);
$recent_txns = $stmt->fetchAll();

// Announcements
$announcements = get_announcements($pdo, 'reseller');

// Time-ago helper (define only if not yet defined by another page in the same request)
if (!function_exists('time_elapsed_string')) {
    function time_elapsed_string(string $datetime): string {
        $now  = new DateTime;
        $ago  = new DateTime($datetime);
        $diff = $now->diff($ago);
        if ($diff->y > 0) return $diff->y . 'y ago';
        if ($diff->m > 0) return $diff->m . 'mo ago';
        if ($diff->d > 0) return $diff->d . 'd ago';
        if ($diff->h > 0) return $diff->h . 'h ago';
        if ($diff->i > 0) return $diff->i . 'm ago';
        return 'just now';
    }
}
?>

<!-- ═══════════════════════════════════════
     DASHBOARD STYLES (scoped to this page)
════════════════════════════════════════ -->
<style>
  /* KPI grid */
  .kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 14px;
    margin-bottom: 24px;
  }

  /* Welcome bar */
  .welcome-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    padding: 20px 22px;
    background: var(--surface-1);
    border: 1px solid var(--border-1);
    border-radius: var(--r-xl);
    margin-bottom: 24px;
    position: relative;
    overflow: hidden;
  }
  .welcome-bar::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--brand), var(--brand-hover), #a78bfa);
  }
  .welcome-name { font-size: 18px; font-weight: 700; color: var(--text-1); }
  .welcome-date { font-size: 13px; color: var(--text-3); margin-top: 3px; }
  .welcome-badges { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }

  /* Quick actions */
  .qa-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 10px;
  }
  .qa-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 16px 10px;
    background: var(--surface-2);
    border: 1px solid var(--border-2);
    border-radius: var(--r-lg);
    color: var(--text-1);
    font-size: 12px;
    font-weight: 600;
    text-align: center;
    cursor: pointer;
    transition: background var(--t), border-color var(--t), transform var(--t), box-shadow var(--t);
    text-decoration: none;
  }
  .qa-btn:hover {
    background: var(--brand-glow);
    border-color: rgba(99,102,241,.3);
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
    color: var(--text-1);
  }
  .qa-icon { font-size: 22px; }

  /* Section header */
  .section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
    gap: 8px;
    flex-wrap: wrap;
  }
  .section-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-1);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  /* Table inside card */
  .table-wrap { overflow-x: auto; border-radius: var(--r); }
  .table-wrap table { margin: 0; }

  /* Announcement card */
  .ann-item {
    padding: 14px 16px;
    border-radius: var(--r);
    border-left: 3px solid var(--brand);
    background: var(--surface-2);
    margin-bottom: 10px;
    position: relative;
    transition: background var(--t);
  }
  .ann-item:last-child { margin-bottom: 0; }
  .ann-item:hover { background: var(--surface-3); }
  .ann-item--info    { border-left-color: var(--info); }
  .ann-item--success { border-left-color: var(--success); }
  .ann-item--warning { border-left-color: var(--warning); }
  .ann-item--danger  { border-left-color: var(--danger); }
  .ann-new-tag {
    position: absolute;
    top: -7px; right: 14px;
    font-size: 9px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 99px;
    background: var(--brand);
    color: #fff;
    letter-spacing: .06em;
  }
  .ann-title { font-size: 13px; font-weight: 600; color: var(--text-1); display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
  .ann-meta  { font-size: 11px; color: var(--text-3); margin-top: 4px; display: flex; gap: 12px; flex-wrap: wrap; }
  .ann-body  { font-size: 12px; color: var(--text-2); margin-top: 8px; line-height: 1.6; white-space: pre-wrap; }

  /* Two-col layout */
  .dash-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 16px;
  }
  @media (max-width: 860px) { .dash-grid { grid-template-columns: 1fr; } }
  @media (max-width: 480px) {
    .kpi-grid { grid-template-columns: 1fr 1fr; }
    .qa-grid  { grid-template-columns: 1fr 1fr; }
    .welcome-name { font-size: 15px; }
  }
</style>

<!-- ═══════════════════════════ WELCOME BAR ═══════════════════════════ -->
<div class="welcome-bar">
  <div>
    <div class="welcome-name">Welcome back, <?= e($_SESSION['username']) ?> 👋</div>
    <div class="welcome-date"><?= date('l, F j, Y') ?></div>
  </div>
  <div class="welcome-badges">
    <span class="badge <?= $balance >= 0 ? 'b-success' : 'b-danger' ?>">
      Balance: $<?= money_fmt($balance) ?>
    </span>
    <span class="badge b-info">Rate: $<?= money_fmt($monthly_rate) ?>/mo</span>
    <?php if (!empty($announcements)): ?>
      <a href="?page=reseller_announcements" class="badge b-warning" style="cursor:pointer;text-decoration:none">
        📢 <?= count($announcements) ?> announcement<?= count($announcements) !== 1 ? 's' : '' ?>
      </a>
    <?php endif; ?>
  </div>
</div>

<!-- ═══════════════════════════ KPI CARDS ════════════════════════════ -->
<div class="kpi-grid">
  <div class="kpi-card">
    <span class="kpi-label">Total Users</span>
    <span class="kpi-value"><?= $total_users ?></span>
    <span class="kpi-delta"><?= $active_users ?> active</span>
    <span class="kpi-icon">👥</span>
  </div>
  <div class="kpi-card">
    <span class="kpi-label">Active Users</span>
    <span class="kpi-value" style="color:var(--success)"><?= $active_users ?></span>
    <span class="kpi-delta">of <?= $total_users ?> total</span>
    <span class="kpi-icon">✅</span>
  </div>
  <div class="kpi-card">
    <span class="kpi-label">Expiring in 7 Days</span>
    <span class="kpi-value" style="color:var(--warning)"><?= $expiring_soon ?></span>
    <span class="kpi-delta">needs renewal</span>
    <span class="kpi-icon">⏰</span>
  </div>
  <div class="kpi-card">
    <span class="kpi-label">Expired</span>
    <span class="kpi-value" style="color:var(--danger)"><?= $expired_users ?></span>
    <span class="kpi-delta">users expired</span>
    <span class="kpi-icon">❌</span>
  </div>
  <div class="kpi-card">
    <span class="kpi-label">Current Balance</span>
    <span class="kpi-value" style="color:<?= $balance >= 0 ? 'var(--success)' : 'var(--danger)' ?>">
      $<?= money_fmt($balance) ?>
    </span>
    <span class="kpi-delta">monthly rate $<?= money_fmt($monthly_rate) ?></span>
    <span class="kpi-icon">💰</span>
  </div>
  <div class="kpi-card">
    <span class="kpi-label">Est. Monthly Cost</span>
    <span class="kpi-value">$<?= money_fmt($total_users * $monthly_rate) ?></span>
    <span class="kpi-delta"><?= $total_users ?> × $<?= money_fmt($monthly_rate) ?></span>
    <span class="kpi-icon">📊</span>
  </div>
</div>

<!-- ═══════════════════════════ QUICK ACTIONS + ANNOUNCEMENTS ════════ -->
<div class="dash-grid">

  <!-- Quick Actions -->
  <div class="card" style="margin:0">
    <div class="section-header">
      <h3 class="section-title">⚡ Quick Actions</h3>
    </div>
    <div class="qa-grid">
      <a href="?page=reseller_assign" class="qa-btn">
        <span class="qa-icon">➕</span>
        <span>Assign User</span>
      </a>
      <a href="?page=reseller_bulk_assign" class="qa-btn">
        <span class="qa-icon">📦</span>
        <span>Bulk Assign</span>
      </a>
      <a href="?page=reseller_users" class="qa-btn">
        <span class="qa-icon">👥</span>
        <span>View Users</span>
      </a>
      <a href="?page=reseller_billing" class="qa-btn">
        <span class="qa-icon">💳</span>
        <span>Billing</span>
      </a>
      <a href="?page=reseller_bulk_history" class="qa-btn">
        <span class="qa-icon">📜</span>
        <span>Bulk History</span>
      </a>
      <a href="?page=reseller_announcements" class="qa-btn">
        <span class="qa-icon">📢</span>
        <span>Announcements</span>
      </a>
    </div>
  </div>

  <!-- Announcements -->
  <div class="card" style="margin:0">
    <div class="section-header">
      <h3 class="section-title">
        📢 Announcements
        <?php if (!empty($announcements)): ?>
          <span class="badge b-info"><?= count($announcements) ?></span>
        <?php endif; ?>
      </h3>
      <?php if (!empty($announcements)): ?>
        <a href="?page=reseller_announcements" class="btn btn-small">View all →</a>
      <?php endif; ?>
    </div>

    <?php if (empty($announcements)): ?>
      <div style="text-align:center; padding:30px 20px">
        <div style="font-size:36px; opacity:.3; margin-bottom:10px">📪</div>
        <p style="margin:0; font-size:13px; color:var(--text-3)">No announcements right now.</p>
      </div>
    <?php else: ?>
      <?php foreach (array_slice($announcements, 0, 3) as $a):
        $type  = $a['type'] ?? 'info';
        $icons = ['info'=>'ℹ️','success'=>'✅','warning'=>'⚠️','danger'=>'🚨'];
        $icon  = $icons[$type] ?? 'ℹ️';
        $is_new = (time() - strtotime($a['created_at'])) < (2 * 86400);
      ?>
        <div class="ann-item ann-item--<?= e($type) ?>">
          <?php if ($is_new): ?><span class="ann-new-tag">NEW</span><?php endif; ?>
          <div class="ann-title">
            <?= $icon ?> <?= e($a['title']) ?>
            <span class="badge b-<?= e($type === 'danger' ? 'danger' : ($type === 'warning' ? 'warning' : ($type === 'success' ? 'success' : 'info'))) ?>"><?= e($type) ?></span>
          </div>
          <div class="ann-meta">
            <span>📅 <?= date('M j, Y', strtotime($a['created_at'])) ?></span>
            <span><?= time_elapsed_string($a['created_at']) ?></span>
            <?php if (!empty($a['expires_at'])): ?>
              <span>⏰ Until <?= date('M j', strtotime($a['expires_at'])) ?></span>
            <?php endif; ?>
          </div>
          <?php if (!empty($a['content'])): ?>
            <div class="ann-body"><?= e(mb_substr($a['content'], 0, 160)) ?><?= mb_strlen($a['content']) > 160 ? '…' : '' ?></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- ═══════════════════════════ RECENT USERS + TRANSACTIONS ══════════ -->
<div class="dash-grid">

  <!-- Recent Users -->
  <div class="card" style="margin:0">
    <div class="section-header">
      <h3 class="section-title">🕒 Recent Users</h3>
      <a href="?page=reseller_users" class="btn btn-small">View All →</a>
    </div>
    <?php if (empty($recent_users)): ?>
      <p style="text-align:center;color:var(--text-3);padding:20px;margin:0;font-size:13px">No users yet.</p>
    <?php else: ?>
      <div class="table-wrap">
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
              if (!empty($u['expires_at']) && $u['expires_at'] < $today) {
                $sc = 'b-danger'; $st = 'Expired';
              } elseif (!empty($u['expires_at']) && $u['expires_at'] <= date('Y-m-d', strtotime('+7 days'))) {
                $sc = 'b-warning'; $st = 'Expiring Soon';
              } else {
                $sc = 'b-success'; $st = 'Active';
              }
            ?>
              <tr>
                <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($u['email']) ?></td>
                <td style="color:var(--text-2)"><?= e($u['product_profile'] ?: '—') ?></td>
                <td style="color:var(--text-2)"><?= e($u['expires_at'] ?: '—') ?></td>
                <td><span class="badge <?= $sc ?>"><?= $st ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- Recent Transactions -->
  <div class="card" style="margin:0">
    <div class="section-header">
      <h3 class="section-title">💰 Recent Transactions</h3>
      <a href="?page=reseller_billing" class="btn btn-small">View All →</a>
    </div>
    <?php if (empty($recent_txns)): ?>
      <p style="text-align:center;color:var(--text-3);padding:20px;margin:0;font-size:13px">No transactions yet.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Type</th>
              <th>Amount</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent_txns as $t): ?>
              <tr>
                <td style="color:var(--text-2);white-space:nowrap"><?= e(date('M j, H:i', strtotime($t['created_at']))) ?></td>
                <td>
                  <span class="badge <?= $t['type']==='payment'?'b-success':($t['type']==='charge'?'b-warning':'b-info') ?>">
                    <?= e($t['type']) ?>
                  </span>
                </td>
                <td style="font-weight:600;color:<?= $t['type']==='payment'?'var(--success)':'var(--danger)' ?>">
                  <?= $t['type']==='payment'?'+':'-' ?>$<?= money_fmt($t['amount']) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>


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
