<?php
// Shown when Super Admin / Reseller portals are deactivated by Developer Portal.

$flash_success = null;
$flash_error = null;

// Best-effort titles (use existing layout)
layout_header('Portal Deactivated', $flash_success, $flash_error);
?>

<div class="card" style="max-width:780px;margin:0 auto;">
  <h2 style="margin:0 0 10px;">Portal is currently deactivated</h2>
  <p class="muted" style="margin:0 0 12px;">
    Please contact your developer to re-activate this.
  </p>
  <hr style="border:none;border-top:1px solid rgba(255,255,255,.08);margin:14px 0;">
  <p class="muted" style="margin:0; font-size:14px;">
    یہ پورٹل بند ہے۔ براہِ کرم دوبارہ فعال کرنے کے لیے اپنے ڈیولپر سے رابطہ کریں۔
  </p>
</div>

<?php layout_footer(); ?>
