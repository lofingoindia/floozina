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

    // If already refunded -> Not Refundable
    try {
        $st = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE reseller_id=? AND user_id=? AND type='refund'");
        $st->execute([$rid, $userId]);
        $already = (int)$st->fetchColumn() > 0;
        if ($already) return ['Not Refundable (already refunded)', 'b-danger'];
    } catch (Throwable $e) {
        // ignore if table/columns differ
    }

    // Check refund window
    $until = (string)($u['refund_eligible_until'] ?? '');
    if ($until === '') return ['Not Refundable', 'b-danger'];

    $ts = strtotime($until);
    if (!$ts) return ['Not Refundable', 'b-danger'];

    if (time() <= $ts) {
        $left = $ts - time();
        $hrs = (int)floor($left / 3600);
        if ($hrs < 0) $hrs = 0;
        return ["Refundable (ends in ~{$hrs}h)", 'b-success'];
    }

    return ['Not Refundable (expired window)', 'b-danger'];
}
?>

<div class="card">
  <h3>My Users (latest 500)</h3>

  
  <form method="get" style="margin:10px 0; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
    <input type="hidden" name="page" value="reseller_users">
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search email or profile..." style="min-width:260px;">
    <button class="btn" type="submit">Search</button>
    <?php if ($q !== ''): ?>
      <a class="btn btn-secondary" href="?page=reseller_users">Clear</a>
    <?php endif; ?>
  </form>

<?php if ($status === 'suspended'): ?>
    <div class="err">Your account is suspended. You can view users but actions are disabled.</div>
  <?php endif; ?>

  <!-- Bulk Delete Form (NO table inside, to avoid nested forms) -->
  <form id="bulkDeleteForm" method="post" onsubmit="return confirm_bulk_delete();" style="margin:0;">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="reseller_bulk_delete_users">
    <input type="hidden" name="remove_from_org" value="1">
  </form>

  <div style="display:flex;gap:10px;align-items:center;justify-content:flex-end;margin:8px 0 12px;">
    <button class="btn btn-danger" type="submit" form="bulkDeleteForm" <?= ($status === 'suspended') ? 'disabled' : '' ?>>
      Delete Selected
    </button>
    <span id="selCount" class="small muted">0 selected</span>
  </div>

  <table>
    <thead>
      <tr>
        <th style="width:38px;">
          <input type="checkbox" id="select_all" <?= ($status === 'suspended') ? 'disabled' : '' ?>>
        </th>
        <th>Email</th>
        <th>Profile(Group)</th>
        <th>Expires</th>
        <th>Refund</th>
        <th>Extend</th>
        <th>Status</th>
        <th>Delete</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$users || count($users) === 0): ?>
        <tr><td colspan="8" class="small muted">No users found.</td></tr>
      <?php else: ?>
        <?php foreach ($users as $u):
          $st = (string)($u['status'] ?? '');
          $badge='b-success';
          if ($st==='expiring') $badge='b-warning';
          if ($st==='expired')  $badge='b-danger';

          // Refund badge
          [$refundLabel, $refundClass] = user_refund_status($pdo, $rid, $u);
          $uid = (int)($u['id'] ?? 0);
        ?>
          <tr>
            <td>
              <!-- IMPORTANT: checkbox belongs to bulkDeleteForm via form attr -->
              <input
                type="checkbox"
                class="user_cb"
                name="user_ids[]"
                value="<?= $uid ?>"
                form="bulkDeleteForm"
                <?= ($status === 'suspended') ? 'disabled' : '' ?>
              >
            </td>

            <td><?= e((string)($u['email'] ?? '')) ?></td>
            <td class="small"><?= e((string)($u['product_profile'] ?? '')) ?></td>
            <td><?= e((string)($u['expires_at'] ?? '')) ?></td>

            <td>
              <span class="badge <?= e($refundClass) ?>"><?= e($refundLabel) ?></span>
            </td>

            <td>
              <!-- Extend: separate form (no longer nested) -->
              <form method="post" style="display:flex;gap:6px;align-items:center">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="reseller_extend_user">
                <input type="hidden" name="user_id" value="<?= $uid ?>">
                <input name="expires_at" type="date" style="width:160px" value="<?= e(!empty($u['expires_at']) ? date('Y-m-d', strtotime((string)$u['expires_at'].' +1 month')) : date('Y-m-d', strtotime('+1 month'))) ?>" <?= ($status === 'suspended') ? 'disabled' : '' ?>>
                <button class="btn btn-primary" type="submit" <?= ($status === 'suspended') ? 'disabled' : '' ?>>Extend</button>
              </form>
            </td>

            <td><span class="badge <?= $badge ?>"><?= e($st) ?></span></td>

            <td>
              <!-- Delete single user: separate form (no longer nested) -->
              <form method="post" onsubmit="return confirm('Delete from Adobe/Console + delete locally + refund charge (if eligible)?');">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="reseller_delete_user">
                <input type="hidden" name="user_id" value="<?= $uid ?>">
                <input type="hidden" name="remove_from_org" value="1">
                <button class="btn btn-danger" type="submit" <?= ($status === 'suspended') ? 'disabled' : '' ?>>Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<script>
(function () {
  const selectAll = document.getElementById('select_all');
  const boxes = () => Array.from(document.querySelectorAll('.user_cb'));
  const selCount = document.getElementById('selCount');

  function updateCount() {
    const c = boxes().filter(b => b.checked).length;
    if (selCount) selCount.textContent = c + ' selected';

    // keep select_all in sync (only if not suspended)
    if (selectAll && !selectAll.disabled) {
      const all = boxes().filter(b => !b.disabled);
      const checked = all.filter(b => b.checked);
      selectAll.checked = all.length > 0 && checked.length === all.length;
      selectAll.indeterminate = checked.length > 0 && checked.length < all.length;
    }
  }

  if (selectAll) {
    selectAll.addEventListener('change', function () {
      boxes().forEach(b => {
        if (!b.disabled) b.checked = selectAll.checked;
      });
      updateCount();
    });
  }

  boxes().forEach(b => b.addEventListener('change', updateCount));

  window.confirm_bulk_delete = function () {
    const c = boxes().filter(b => b.checked).length;
    if (c <= 0) {
      alert('Please select at least one user to delete.');
      return false;
    }
    return confirm('Delete ' + c + ' selected user(s) from Adobe/Console + delete locally + refund charge (if eligible)?');
  };

  // initial
  updateCount();
})();
</script>