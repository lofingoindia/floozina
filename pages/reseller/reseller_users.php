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
        if ($already) return ['Not Refundable (already refunded)', 'b-glow-red'];
    } catch (Throwable $e) {
        // ignore if table/columns differ
    }

    // Check refund window
    $until = (string)($u['refund_eligible_until'] ?? '');
    if ($until === '') return ['Not Refundable<br>(expired window)', 'b-glow-red'];

    $ts = strtotime($until);
    if (!$ts) return ['Not Refundable<br>(expired window)', 'b-glow-red'];

    if (time() <= $ts) {
        $left = $ts - time();
        $hrs = (int)floor($left / 3600);
        if ($hrs < 0) $hrs = 0;
        return ["Refundable<br>(ends in ~{$hrs}h)", 'b-glow-green'];
    }

    return ['Not Refundable<br>(expired window)', 'b-glow-red'];
}
?>

<style>
/* Matte Premium Black & Blue Dashboard Styles */
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
    background: linear-gradient(135deg, #0f1219 0%, #0a0d14 100%);
    border: 1px solid rgba(59, 130, 246, 0.3);
    border-radius: var(--radius-lg);
    padding: 24px;
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.4), 0 0 20px var(--accent-blue-glow);
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
    background: linear-gradient(90deg, transparent, rgba(59, 130, 246, 0.4), transparent);
    opacity: 1;
}

@keyframes fadeUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

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

.premium-dashboard h3 { margin: 0; font-weight: 700; color: #FFFFFF; letter-spacing: -0.5px; font-size: 20px;}

.premium-dashboard .action-btn {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    background: rgba(37, 99, 235, 0.05); border: 1px solid rgba(37, 99, 235, 0.15); padding: 10px 16px;
    border-radius: var(--radius-md); color: var(--text-main); text-decoration: none; font-weight: 600; font-size: 14px; 
    transition: var(--transition); position: relative; overflow: hidden; cursor:pointer;
    white-space: nowrap;
}
.premium-dashboard .action-btn:hover { 
    filter: brightness(1.2);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3); transform: translateY(-1px);
}
.premium-dashboard .btn-danger {
    background: rgba(239, 68, 68, 0.2); border-color: rgba(239, 68, 68, 0.4); color: #FCA5A5; font-weight: 700;
}
.premium-dashboard .btn-primary { 
    background: rgba(37, 99, 235, 0.25); border-color: rgba(37, 99, 235, 0.4); color: #93C5FD; font-weight: 700;
}

.premium-dashboard table { width: 100%; border-collapse: separate; border-spacing: 0; }
.premium-dashboard th, .premium-dashboard td { padding: 12px 10px; text-align: left; font-size: 13px; }
.premium-dashboard th { 
    background: rgba(37, 99, 235, 0.05); font-size: 12px; text-transform: uppercase; 
    color: #60A5FA; font-weight: 700; letter-spacing: 1px;
    border-bottom: 2px solid rgba(37, 99, 235, 0.1);
    white-space: nowrap;
}
.premium-dashboard tbody tr { transition: var(--transition); border-bottom: 1px solid rgba(255,255,255,0.02); display: table-row; }
.premium-dashboard tbody tr td { border-bottom: 1px solid rgba(255,255,255,0.03); color: #e2e8f0; }
.premium-dashboard tbody tr:last-child td { border-bottom: none; }
.premium-dashboard tbody tr:hover td { background: rgba(37, 99, 235, 0.03); color: #fff; }

/* Custom Inputs in Premium Dashboard */
.premium-dashboard input[type="text"], 
.premium-dashboard input[type="date"] {
    background: rgba(0, 0, 0, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: #e2e8f0;
    padding: 10px 14px;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s ease;
}
.premium-dashboard input[type="text"]:focus,
.premium-dashboard input[type="date"]:focus {
    outline: none;
    border-color: rgba(37, 99, 235, 0.5);
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.2);
    background: rgba(0, 0, 0, 0.3);
}
.premium-dashboard input[type="checkbox"] {
    accent-color: #2563eb;
    width: 16px; height: 16px;
    cursor: pointer;
}
</style>

<div class="premium-dashboard">
<div class="premium-card">
  <div style="display:flex; align-items:center; gap: 10px; margin-bottom: 20px;">
    <div style="width: 36px; height: 36px; font-size: 18px; background: rgba(165, 180, 252, 0.1); color: #A5B4FC; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
      <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    </div>
    <h3>My Users (latest 500)</h3>
  </div>

  
  <form method="get" style="margin:20px 0; display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
    <input type="hidden" name="page" value="reseller_users">
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search email or profile..." style="flex:1; min-width:260px;">
    <button class="status-badge b-glow-blue" style="cursor: pointer; padding: 10px 20px; font-size: 14px;" type="submit">
      <svg xmlns="http://www.w3.org/2000/svg" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      Search
    </button>
    <?php if ($q !== ''): ?>
      <a class="status-badge b-glow-red" style="cursor: pointer; padding: 10px 20px; font-size: 14px; text-decoration: none;" href="?page=reseller_users">
        <svg xmlns="http://www.w3.org/2000/svg" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        Clear
      </a>
    <?php endif; ?>
  </form>

<?php if ($status === 'suspended'): ?>
    <div style="background: rgba(248, 113, 113, 0.1); border: 1px solid rgba(248, 113, 113, 0.3); color: #F87171; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; display: flex; align-items: center; gap: 8px;">
      <svg xmlns="http://www.w3.org/2000/svg" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      Your account is suspended. You can view users but actions are disabled.
    </div>
  <?php endif; ?>

  <!-- Bulk Delete Form (NO table inside, to avoid nested forms) -->
  <form id="bulkDeleteForm" method="post" onsubmit="return confirm_bulk_delete();" style="margin:0;">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="reseller_bulk_delete_users">
    <input type="hidden" name="remove_from_org" value="1">
  </form>

  <div style="display:flex;gap:12px;align-items:center;justify-content:flex-end;margin:12px 0 20px;">
    <button class="status-badge b-glow-red" style="cursor: pointer; padding: 8px 16px;" type="submit" form="bulkDeleteForm" <?= ($status === 'suspended') ? 'disabled' : '' ?>>
      <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
      Delete Selected
    </button>
    <span id="selCount" class="text-muted" style="font-weight: 500;">0 selected</span>
  </div>

  <div style="overflow-x: hidden; border: 1px solid rgba(59, 130, 246, 0.1); border-radius: 12px;">
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
        <tr>
          <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 40px;">
            <div style="font-size:32px; opacity:0.5; margin-bottom:10px;">
              <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <p style="margin:0; font-size: 15px;">No users found.</p>
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($users as $u):
          $st = (string)($u['status'] ?? '');
          $badge='b-glow-green';
          if ($st==='expiring') $badge='b-glow-yellow';
          if ($st==='expired')  $badge='b-glow-red';

          // Refund badge
          [$refundLabel, $refundClass] = user_refund_status($pdo, $rid, $u);
          $uid = (int)($u['id'] ?? 0);
        ?>
          <tr>
            <td style="padding: 12px 10px;">
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

            <td style="font-weight: 500; color: #fff; padding: 12px 10px; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= e((string)($u['email'] ?? '')) ?>"><?= e((string)($u['email'] ?? '')) ?></td>
            <td class="text-muted" style="padding: 12px 10px; max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= e((string)($u['product_profile'] ?? '')) ?>"><?= e((string)($u['product_profile'] ?? '')) ?></td>
            <td class="text-muted" style="padding: 12px 10px;"><?= e((string)($u['expires_at'] ?? '')) ?></td>

            <td style="padding: 12px 10px; line-height: 1.4;">
              <span class="status-badge <?= e($refundClass) ?>" style="font-size: 11px; padding: 4px 10px; display: inline-block; white-space: normal; text-align: center; min-width: 100px;"><?= $refundLabel ?></span>
            </td>

            <td style="padding: 12px 10px;">
              <!-- Extend: separate form (no longer nested) -->
              <form method="post" style="display:flex;gap:6px;align-items:center; margin:0; flex-wrap: nowrap;">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="reseller_extend_user">
                <input type="hidden" name="user_id" value="<?= $uid ?>">
                <input name="expires_at" type="date" style="width:130px; padding: 6px 8px; font-size: 13px;" value="<?= e(!empty($u['expires_at']) ? date('Y-m-d', strtotime((string)$u['expires_at'].' +1 month')) : date('Y-m-d', strtotime('+1 month'))) ?>" <?= ($status === 'suspended') ? 'disabled' : '' ?>>
                <button class="status-badge b-glow-blue" style="cursor: pointer; padding: 6px 14px;" type="submit" <?= ($status === 'suspended') ? 'disabled' : '' ?>>Extend</button>
              </form>
            </td>

            <td style="padding: 12px 10px;"><span class="status-badge <?= $badge ?>"><?= ucfirst(e($st)) ?></span></td>

            <td style="padding: 12px 10px;">
              <!-- Delete single user: separate form (no longer nested) -->
              <form method="post" onsubmit="return confirm('Delete from Adobe/Console + delete locally + refund charge (if eligible)?');" style="margin:0;">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="reseller_delete_user">
                <input type="hidden" name="user_id" value="<?= $uid ?>">
                <input type="hidden" name="remove_from_org" value="1">
                <button class="status-badge b-glow-red" style="cursor: pointer; padding: 6px 14px;" type="submit" <?= ($status === 'suspended') ? 'disabled' : '' ?>>Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
  </div>
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