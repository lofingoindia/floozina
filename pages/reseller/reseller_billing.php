<?php
// META: {"title": "Billing", "order": 70, "nav": true, "hidden": false}
// NOTE: session_start() mat lagana (app.php already start karta hoga)

$rid  = (int)($_SESSION['user_id'] ?? 0);
$role = (string)($_SESSION['role'] ?? '');

if ($rid <= 0 || $role !== 'reseller') {
    echo "<div class='card' style='padding:12px'>Please login as reseller again.</div>";
    return;
}

$stmt = $pdo->prepare("SELECT * FROM resellers WHERE id=?");
$stmt->execute([$rid]);
$r = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE reseller_id=? AND expires_at >= CURDATE()");
$stmt->execute([$rid]);
$active = (int)$stmt->fetchColumn();
?>

<!-- Billing Page Styles -->
<style>
  .billing-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 16px;
  }
  .billing-stat {
    padding: 14px;
    background: var(--surface-2);
    border-radius: var(--r-sm);
    border: 1px solid var(--border-1);
  }
  .billing-stat-label {
    font-size: 11px;
    font-weight: 500;
    color: var(--text-3);
    text-transform: uppercase;
    letter-spacing: .03em;
  }
  .billing-stat-value {
    font-size: 20px;
    font-weight: 700;
    color: var(--text-1);
    margin-top: 4px;
  }
  .billing-info {
    font-size: 12px;
    color: var(--text-3);
    margin-top: 8px;
  }
  @media (max-width: 600px) {
    .billing-stats {
      grid-template-columns: repeat(2, 1fr);
    }
    .billing-stat { padding: 12px; }
    .billing-stat-value { font-size: 18px; }
  }
</style>

<div class="card">
  <h3>Billing</h3>
  <div class="billing-stats">
    <div class="billing-stat">
      <div class="billing-stat-label">Monthly Rate</div>
      <div class="billing-stat-value">$<?= money_fmt($r['monthly_rate'] ?? 0) ?></div>
    </div>
    <div class="billing-stat">
      <div class="billing-stat-label">Total Billed</div>
      <div class="billing-stat-value">$<?= money_fmt($r['total_billed'] ?? 0) ?></div>
    </div>
    <div class="billing-stat">
      <div class="billing-stat-label">Total Paid</div>
      <div class="billing-stat-value">$<?= money_fmt($r['total_paid'] ?? 0) ?></div>
    </div>
    <div class="billing-stat">
      <div class="billing-stat-label">Balance</div>
      <div class="billing-stat-value" style="color:var(--<?= ($r['balance'] ?? 0) < 0 ? 'danger' : 'success' ?>)">
        $<?= money_fmt($r['balance'] ?? 0) ?>
      </div>
    </div>
  </div>
  <div class="billing-info">Active Users (not expired): <?= (int)$active ?></div>
</div>