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

<div class="card">
  <h3>Billing</h3>
  <div class="row">
    <div class="col">
      <div class="muted">Monthly Rate</div>
      <h3>$<?= money_fmt($r['monthly_rate'] ?? 0) ?></h3>
    </div>
    <div class="col">
      <div class="muted">Total Billed</div>
      <h3>$<?= money_fmt($r['total_billed'] ?? 0) ?></h3>
    </div>
    <div class="col">
      <div class="muted">Total Paid</div>
      <h3>$<?= money_fmt($r['total_paid'] ?? 0) ?></h3>
    </div>
    <div class="col">
      <div class="muted">Balance</div>
      <h3>$<?= money_fmt($r['balance'] ?? 0) ?></h3>
    </div>
  </div>

  <p class="muted small">Active Users (not expired): <?= (int)$active ?></p>
</div>