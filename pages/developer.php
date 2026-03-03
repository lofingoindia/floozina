<?php
// Developer Portal
// - Only Activate / Deactivate
// - Protected with a URL key: /?page=developer&key=...

$flash_success = null;
$flash_error = null;

$key = (string)($_GET['key'] ?? '');
if ($key === '' || !hash_equals(portal_key(), $key)) {
    http_response_code(403);
    layout_header('Developer Portal', null, 'Access denied');
    ?>
    <div class="card" style="max-width:780px;margin:0 auto;">
      <h2 style="margin:0 0 10px;">Access denied</h2>
      <p class="muted" style="margin:0;">Invalid or missing developer key.</p>
    </div>
    <?php
    layout_footer();
    exit;
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        csrf_verify();
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'developer_set_state') {
            $mode = (string)($_POST['mode'] ?? '');
            if ($mode === 'activate') {
                portal_set_active(true);
                $flash_success = 'Activated successfully.';
            } elseif ($mode === 'deactivate') {
                portal_set_active(false);
                $flash_success = 'Deactivated successfully.';
            } else {
                $flash_error = 'Invalid action.';
            }
        }
    } catch (Throwable $e) {
        $flash_error = 'Request failed.';
    }
}

$st = portal_state_read();
$isActive = (bool)($st['active'] ?? true);

layout_header('Developer Portal', $flash_success, $flash_error);
?>

<div class="card" style="max-width:900px;margin:0 auto;">
  <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;justify-content:space-between;">
    <div>
      <h2 style="margin:0 0 6px;">Developer Portal</h2>
      <p class="muted" style="margin:0;">Master switch for Super Admin + Reseller portals.</p>
    </div>
    <div>
      <?php if ($isActive): ?>
        <span class="badge" style="background:rgba(74,222,128,.12);border:1px solid rgba(74,222,128,.25);color:#caffda;">ACTIVE</span>
      <?php else: ?>
        <span class="badge" style="background:rgba(255,77,77,.12);border:1px solid rgba(255,77,77,.25);color:#ffd2d2;">DEACTIVATED</span>
      <?php endif; ?>
    </div>
  </div>

  <hr style="border:none;border-top:1px solid rgba(255,255,255,.08);margin:16px 0;">

  <div class="row" style="gap:12px;">
    <div class="col" style="min-width:240px;">
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="developer_set_state">
        <input type="hidden" name="mode" value="activate">
        <button class="btn btn-primary" type="submit" <?= $isActive ? 'disabled' : '' ?> style="width:100%;">Activate</button>
      </form>
      <p class="muted" style="margin:10px 0 0;font-size:13px;">Enable Super Admin + Reseller portals.</p>
    </div>
    <div class="col" style="min-width:240px;">
      <form method="post" onsubmit="return confirm('Deactivate portals now?');">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="developer_set_state">
        <input type="hidden" name="mode" value="deactivate">
        <button class="btn" type="submit" <?= !$isActive ? 'disabled' : '' ?> style="width:100%;border-color:rgba(255,77,77,.35);">Deactivate</button>
      </form>
      <p class="muted" style="margin:10px 0 0;font-size:13px;">Block all portals (login + dashboards).</p>
    </div>
  </div>

  <hr style="border:none;border-top:1px solid rgba(255,255,255,.08);margin:16px 0;">

  <div class="card" style="background:rgba(255,255,255,.02);border:1px solid rgba(255,255,255,.06);box-shadow:none;">
    <h3 style="margin:0 0 8px;">When deactivated</h3>
    <p class="muted" style="margin:0;">
      Super Admin and Reseller portals will show: <b>“Please contact your developer to re-activate this.”</b>
    </p>
  </div>

  <p class="muted" style="margin:14px 0 0;font-size:12px;">
    Last updated: <?= e((string)($st['updated_at'] ?? '')) ?>
  </p>
</div>

<?php layout_footer(); ?>
