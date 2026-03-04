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

// ── Lucide SVG helper (inline, no external dependency) ──────────────────────
$_ic = fn(string $paths, int $size = 18): string =>
    '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'"'
    .' viewBox="0 0 24 24" fill="none" stroke="currentColor"'
    .' stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"'
    .' aria-hidden="true">'.$paths.'</svg>';

// Icon definitions
$ic_users     = $_ic('<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>');
$ic_user_plus = $_ic('<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/>');
$ic_check     = $_ic('<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/>');
$ic_clock     = $_ic('<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>');
$ic_xcircle   = $_ic('<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/>');
$ic_wallet    = $_ic('<rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/>');
$ic_barchart  = $_ic('<line x1="18" x2="18" y1="20" y2="10"/><line x1="12" x2="12" y1="20" y2="4"/><line x1="6" x2="6" y1="20" y2="14"/>');
$ic_zap       = $_ic('<path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/>');
$ic_package   = $_ic('<path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"/><path d="M12 22V12"/><path d="m3.3 7 7.703 4.734a2 2 0 0 0 1.994 0L20.7 7"/><path d="m7.5 4.27 9 5.15"/>');
$ic_history   = $_ic('<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/>');
$ic_bell      = $_ic('<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>');
$ic_inbox     = $_ic('<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>');
$ic_info      = $_ic('<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>');
$ic_warning   = $_ic('<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>');
$ic_danger    = $_ic('<circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/>');
$ic_calendar  = $_ic('<rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/>');
$ic_creditcard= $_ic('<rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/>');
$ic_dollar    = $_ic('<line x1="12" x2="12" y1="2" y2="22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>');

// Announcement type → icon map
$ann_icons = [
    'info'    => $ic_info,
    'success' => $ic_check,
    'warning' => $ic_warning,
    'danger'  => $ic_danger,
];
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
    border: 1px solid var(--border-1);
    border-radius: var(--r-lg);
    color: var(--text-1);
    font-size: 12px;
    font-weight: 600;
    text-align: center;
    cursor: pointer;
    transition: background var(--t), border-color var(--t), transform var(--t), box-shadow var(--t), color var(--t);
    text-decoration: none;
  }
  .qa-btn:hover {
    background: var(--brand-glow);
    border-color: rgba(99,102,241,.4);
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
    color: var(--brand-hover);
  }
  .qa-icon {
    width: 36px; height: 36px;
    border-radius: var(--r);
    background: var(--brand-glow);
    display: flex; align-items: center; justify-content: center;
    color: var(--brand-hover);
    transition: background var(--t), color var(--t);
  }
  .qa-btn:hover .qa-icon { background: rgba(99,102,241,.25); color: var(--brand-hover); }
  .qa-icon svg { width: 18px; height: 18px; stroke: currentColor; }

  /* KPI icon override for SVG */
  .kpi-icon {
    width: 38px; height: 38px;
    border-radius: var(--r);
    background: var(--brand-glow);
    display: flex; align-items: center; justify-content: center;
    color: var(--brand-hover);
  }
  .kpi-icon svg { width: 20px; height: 20px; stroke: currentColor; }

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
  .section-title svg { color: var(--text-2); }

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
  .ann-icon { display: flex; align-items: center; color: inherit; }
  .ann-icon svg { width: 15px; height: 15px; stroke: currentColor; flex-shrink: 0; }
  .ann-title { font-size: 13px; font-weight: 600; color: var(--text-1); display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
  .ann-meta  { font-size: 11px; color: var(--text-2); margin-top: 6px; display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
  .ann-meta svg { width: 12px; height: 12px; stroke: currentColor; flex-shrink: 0; color: var(--text-2); }
  .ann-meta-item { display: flex; align-items: center; gap: 4px; }
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
    <div class="welcome-name">Welcome back, <?= e($_SESSION['username']) ?></div>
    <div class="welcome-date"><?= date('l, F j, Y') ?></div>
  </div>
  <div class="welcome-badges">
    <span class="badge <?= $balance >= 0 ? 'b-success' : 'b-danger' ?>">
      Balance: $<?= money_fmt($balance) ?>
    </span>
    <span class="badge b-info">Rate: $<?= money_fmt($monthly_rate) ?>/mo</span>
    <?php if (!empty($announcements)): ?>
      <a href="?page=reseller_announcements" class="badge b-warning" style="cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:5px">
        <?= $ic_bell ?> <?= count($announcements) ?> announcement<?= count($announcements) !== 1 ? 's' : '' ?>
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
    <span class="kpi-icon"><?= $_ic('<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>', 20) ?></span>
  </div>
  <div class="kpi-card">
    <span class="kpi-label">Active Users</span>
    <span class="kpi-value" style="color:var(--success)"><?= $active_users ?></span>
    <span class="kpi-delta">of <?= $total_users ?> total</span>
    <span class="kpi-icon"><?= $_ic('<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/>', 20) ?></span>
  </div>
  <div class="kpi-card">
    <span class="kpi-label">Expiring in 7 Days</span>
    <span class="kpi-value" style="color:var(--warning)"><?= $expiring_soon ?></span>
    <span class="kpi-delta">needs renewal</span>
    <span class="kpi-icon"><?= $_ic('<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>', 20) ?></span>
  </div>
  <div class="kpi-card">
    <span class="kpi-label">Expired</span>
    <span class="kpi-value" style="color:var(--danger)"><?= $expired_users ?></span>
    <span class="kpi-delta">users expired</span>
    <span class="kpi-icon"><?= $_ic('<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/>', 20) ?></span>
  </div>
  <div class="kpi-card">
    <span class="kpi-label">Current Balance</span>
    <span class="kpi-value" style="color:<?= $balance >= 0 ? 'var(--success)' : 'var(--danger)' ?>">
      $<?= money_fmt($balance) ?>
    </span>
    <span class="kpi-delta">monthly rate $<?= money_fmt($monthly_rate) ?></span>
    <span class="kpi-icon"><?= $_ic('<line x1="12" x2="12" y1="2" y2="22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>', 20) ?></span>
  </div>
  <div class="kpi-card">
    <span class="kpi-label">Est. Monthly Cost</span>
    <span class="kpi-value">$<?= money_fmt($total_users * $monthly_rate) ?></span>
    <span class="kpi-delta"><?= $total_users ?> × $<?= money_fmt($monthly_rate) ?></span>
    <span class="kpi-icon"><?= $_ic('<line x1="18" x2="18" y1="20" y2="10"/><line x1="12" x2="12" y1="20" y2="4"/><line x1="6" x2="6" y1="20" y2="14"/>', 20) ?></span>
  </div>
</div>

<!-- ═══════════════════════════ QUICK ACTIONS + ANNOUNCEMENTS ════════ -->
<div class="dash-grid">

  <!-- Quick Actions -->
  <div class="card" style="margin:0">
    <div class="section-header">
      <h3 class="section-title">
        <?= $_ic('<path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/>') ?>
        Quick Actions
      </h3>
    </div>
    <div class="qa-grid">
      <a href="?page=reseller_assign" class="qa-btn">
        <span class="qa-icon"><?= $ic_user_plus ?></span>
        <span>Assign User</span>
      </a>
      <a href="?page=reseller_bulk_assign" class="qa-btn">
        <span class="qa-icon"><?= $ic_package ?></span>
        <span>Bulk Assign</span>
      </a>
      <a href="?page=reseller_users" class="qa-btn">
        <span class="qa-icon"><?= $ic_users ?></span>
        <span>View Users</span>
      </a>
      <a href="?page=reseller_billing" class="qa-btn">
        <span class="qa-icon"><?= $ic_creditcard ?></span>
        <span>Billing</span>
      </a>
      <a href="?page=reseller_bulk_history" class="qa-btn">
        <span class="qa-icon"><?= $ic_history ?></span>
        <span>Bulk History</span>
      </a>
      <a href="?page=reseller_announcements" class="qa-btn">
        <span class="qa-icon"><?= $ic_bell ?></span>
        <span>Announcements</span>
      </a>
    </div>
  </div>

  <!-- Announcements -->
  <div class="card" style="margin:0">
    <div class="section-header">
      <h3 class="section-title">
        <?= $ic_bell ?>
        Announcements
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
        <div style="opacity:.25; margin-bottom:10px; display:flex; justify-content:center; color:var(--text-3)">
          <?= $ic_inbox ?>
        </div>
        <p style="margin:0; font-size:13px; color:var(--text-3)">No announcements right now.</p>
      </div>
    <?php else: ?>
      <?php foreach (array_slice($announcements, 0, 3) as $a):
        $type     = $a['type'] ?? 'info';
        $ann_icon = $ann_icons[$type] ?? $ic_info;
        $is_new   = (time() - strtotime($a['created_at'])) < (2 * 86400);
      ?>
        <div class="ann-item ann-item--<?= e($type) ?>">
          <?php if ($is_new): ?><span class="ann-new-tag">NEW</span><?php endif; ?>
          <div class="ann-title">
            <span class="ann-icon"><?= $ann_icon ?></span>
            <?= e($a['title']) ?>
            <span class="badge b-<?= e($type === 'danger' ? 'danger' : ($type === 'warning' ? 'warning' : ($type === 'success' ? 'success' : 'info'))) ?>"><?= e($type) ?></span>
          </div>
          <div class="ann-meta">
            <span class="ann-meta-item">
              <?= $ic_calendar ?>
              <?= date('M j, Y', strtotime($a['created_at'])) ?>
            </span>
            <span class="ann-meta-item">
              <?= $ic_clock ?>
              <?= time_elapsed_string($a['created_at']) ?>
            </span>
            <?php if (!empty($a['expires_at'])): ?>
              <span class="ann-meta-item">
                <?= $_ic('<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>') ?>
                Until <?= date('M j', strtotime($a['expires_at'])) ?>
              </span>
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
      <h3 class="section-title">
        <?= $ic_users ?>
        Recent Users
      </h3>
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
      <h3 class="section-title">
        <?= $ic_wallet ?>
        Recent Transactions
      </h3>
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
