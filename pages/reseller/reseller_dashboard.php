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
