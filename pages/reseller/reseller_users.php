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

<style>
/* Premium Dark Matte Theme for Users Page */
.premium-card {
    background-color: #171923;
    border-radius: 12px;
    padding: 24px;
    color: #e2e8f0;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
    border: 1px solid #2d3748;
}
.premium-card h3 {
    margin-top: 0;
    font-size: 1.25rem;
    font-weight: 600;
    color: #f7fafc;
    margin-bottom: 20px;
}
.premium-input {
    background-color: #2d3748;
    border: 1px solid #4a5568;
    color: #e2e8f0;
    padding: 8px 16px;
    border-radius: 8px;
    outline: none;
    transition: all 0.2s;
}
.premium-input:focus {
    border-color: #3182ce;
    box-shadow: 0 0 0 3px rgba(49, 130, 206, 0.3);
}
.premium-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border-radius: 8px;
    font-weight: 500;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.875rem;
}
.premium-btn-blue {
    background-color: #3182ce;
    color: #fff;
}
.premium-btn-blue:hover {
    background-color: #2b6cb0;
}
.premium-btn-danger {
    background-color: #e53e3e;
    color: #fff;
}
.premium-btn-danger:hover {
    background-color: #c53030;
}
.premium-btn-secondary {
    background-color: #4a5568;
    color: #fff;
}
.premium-btn-secondary:hover {
    background-color: #2d3748;
}
.premium-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    margin-top: 16px;
}
.premium-table th {
    background-color: #000000;
    color: #a0aec0;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
    padding: 12px 16px;
    text-align: left;
    border-bottom: 1px solid #2d3748;
}
.premium-table td {
    padding: 16px;
    background-color: #1a202c;
    border-bottom: 1px solid #2d3748;
    color: #cbd5e0;
    vertical-align: middle;
}
.premium-table tr:hover td {
    background-color: #2d3748;
}
.premium-table tr:last-child td {
    border-bottom: none;
}
.premium-table tr td:first-child {
    border-top-left-radius: 8px;
    border-bottom-left-radius: 8px;
}
.premium-table tr td:last-child {
    border-top-right-radius: 8px;
    border-bottom-right-radius: 8px;
}
.badge-premium {
    padding: 4px 10px;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: capitalize;
}
.b-success-premium { background-color: rgba(72, 187, 120, 0.2); color: #48bb78; border: 1px solid #48bb78; }
.b-danger-premium { background-color: rgba(245, 101, 101, 0.2); color: #fc8181; border: 1px solid #fc8181; }
.b-warning-premium { background-color: rgba(237, 137, 54, 0.2); color: #f6ad55; border: 1px solid #f6ad55; }
</style>

<div class="card premium-card">
  <h3>My Users (latest 500)</h3>

  
  <form method="get" style="margin:10px 0; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
    <input type="hidden" name="page" value="reseller_users">
    <input type="text" name="q" class="premium-input" value="<?= e($q) ?>" placeholder="Search email or profile..." style="min-width:260px;">
    <button class="premium-btn premium-btn-blue" type="submit">
      <svg stroke="currentColor" fill="none" stroke-width="2" viewBox="0 0 24 24" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
      Search
    </button>
    <?php if ($q !== ''): ?>
      <a class="premium-btn premium-btn-secondary" style="text-decoration:none;" href="?page=reseller_users">Clear</a>
    <?php endif; ?>
  </form>

<?php if ($status === 'suspended'): ?>
    <div class="err" style="background:#742a2a; border-radius:8px; padding:12px; margin-bottom:12px;">Your account is suspended. You can view users but actions are disabled.</div>
  <?php endif; ?>

  <!-- Bulk Delete Form (NO table inside, to avoid nested forms) -->
  <form id="bulkDeleteForm" method="post" onsubmit="return confirm_bulk_delete();" style="margin:0;">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="reseller_bulk_delete_users">
    <input type="hidden" name="remove_from_org" value="1">
  </form>

  <div style="display:flex;gap:10px;align-items:center;justify-content:flex-end;margin:8px 0 12px;">
    <button class="premium-btn premium-btn-danger" style="padding:10px 16px;" type="submit" form="bulkDeleteForm" <?= ($status === 'suspended') ? 'disabled' : '' ?>>
      <svg stroke="currentColor" fill="none" stroke-width="2" viewBox="0 0 24 24" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
      Delete Selected
    </button>
    <span id="selCount" class="small muted" style="color:#a0aec0;">0 selected</span>
  </div>

  <div style="overflow-x:auto;">
  <table class="premium-table">
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
        <tr><td colspan="8" class="small muted" style="text-align:center; padding: 24px;">No users found.</td></tr>
      <?php else: ?>
        <?php foreach ($users as $u):
          $st = (string)($u['status'] ?? '');
          $badge='b-success-premium';
          if ($st==='expiring') $badge='b-warning-premium';
          if ($st==='expired')  $badge='b-danger-premium';

          // Refund badge
          [$refundLabel, $refundClass] = user_refund_status($pdo, $rid, $u);
          if (strpos($refundClass, 'b-success') !== false) {
             $refundClass = 'b-success-premium';
          } else {
             $refundClass = 'b-danger-premium';
          }
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

            <td style="font-weight:500; color:#fff;"><i class="fa fa-envelope" style="color:#4a5568;margin-right:6px;"></i><?= e((string)($u['email'] ?? '')) ?></td>
            <td style="font-size:0.875rem; color:#a0aec0;"><?= e((string)($u['product_profile'] ?? '')) ?></td>
            <td><?= e((string)($u['expires_at'] ?? '')) ?></td>

            <td>
              <span class="badge-premium <?= e($refundClass) ?>"><?= e($refundLabel) ?></span>
            </td>

            <td>
              <!-- Extend: separate form (no longer nested) -->
              <form method="post" style="display:flex;gap:6px;align-items:center; margin:0;">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="reseller_extend_user">
                <input type="hidden" name="user_id" value="<?= $uid ?>">
                <input class="premium-input" name="expires_at" type="date" style="padding:4px 8px; width:130px; font-size:0.875rem;" value="<?= e(!empty($u['expires_at']) ? date('Y-m-d', strtotime((string)$u['expires_at'].' +1 month')) : date('Y-m-d', strtotime('+1 month'))) ?>" <?= ($status === 'suspended') ? 'disabled' : '' ?>>
                <button class="premium-btn premium-btn-blue" type="submit" style="padding:6px 12px;" <?= ($status === 'suspended') ? 'disabled' : '' ?>>Extend</button>
              </form>
            </td>

            <td><span class="badge-premium <?= $badge ?>"><?= e($st) ?></span></td>

            <td>
              <!-- Delete single user: separate form (no longer nested) -->
              <form method="post" onsubmit="return confirm('Delete from Adobe/Console + delete locally + refund charge (if eligible)?');" style="margin:0;">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="reseller_delete_user">
                <input type="hidden" name="user_id" value="<?= $uid ?>">
                <input type="hidden" name="remove_from_org" value="1">
                <button class="premium-btn premium-btn-danger" type="submit" style="padding:6px 12px;" <?= ($status === 'suspended') ? 'disabled' : '' ?>>Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
  </div>
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