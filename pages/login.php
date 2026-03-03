<?php
layout_header("Login - {$APP_NAME}", $flash_success, $flash_error);
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    background-color: #0b1730;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #0f172a;
    position: relative;
    overflow: hidden;
  }

  /* --- Background decorative shapes --- */
  .bg-shapes {
    position: fixed;
    inset: 0;
    z-index: 0;
    pointer-events: none;
    overflow: hidden;
  }

  .bg-shape {
    position: absolute;
    border-radius: 50%;
  }

  /* Large circle — top right */
  .bg-s1 {
    width: 520px;
    height: 520px;
    top: -180px;
    right: -160px;
    background: #162448;
    border-radius: 50%;
  }

  /* Medium circle — bottom left */
  .bg-s2 {
    width: 340px;
    height: 340px;
    bottom: -110px;
    left: -90px;
    background: #162448;
    border-radius: 50%;
  }

  /* Small filled square — top left area */
  .bg-s3 {
    width: 100px;
    height: 100px;
    top: 15%;
    left: 7%;
    background: #1e3360;
    border-radius: 18px;
    transform: rotate(22deg);
  }

  /* Tiny circle — centre bottom */
  .bg-s4 {
    width: 60px;
    height: 60px;
    bottom: 18%;
    right: 14%;
    background: #1e3360;
    border-radius: 50%;
  }

  /* Outline ring — mid left */
  .bg-s5 {
    width: 200px;
    height: 200px;
    top: 42%;
    left: -70px;
    border: 18px solid #162448;
    background: transparent;
    border-radius: 50%;
  }

  /* Outline ring — top right inner */
  .bg-s6 {
    width: 140px;
    height: 140px;
    top: 8%;
    right: 22%;
    border: 12px solid #162448;
    background: transparent;
    border-radius: 50%;
  }

  /* Thin horizontal bar — decorative line bottom */
  .bg-bar {
    position: absolute;
    width: 180px;
    height: 4px;
    bottom: 12%;
    left: 18%;
    background: #1e3360;
    border-radius: 2px;
  }

  .page-wrapper {
    width: 100%;
    max-width: 460px;
    padding: 24px 16px;
    position: relative;
    z-index: 1;
  }

  /* --- Card --- */
  .card {
    background: #ffffff;
    border: 1px solid #dde3ed;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: none;
  }

  .card-accent {
    height: 4px;
    background: #2563eb;
  }

  .card-body {
    padding: 36px 40px 32px;
  }

  /* --- Branding --- */
  .brand {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin-bottom: 28px;
    text-align: center;
  }

  .brand-icon {
    width: 52px;
    height: 52px;
    background: #eff6ff;
    border: none;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 14px;
    box-shadow: none;
  }

  .brand-icon i {
    font-size: 22px;
    color: #2563eb;
  }

  .brand-title {
    font-size: 20px;
    font-weight: 700;
    color: #0f172a;
    letter-spacing: -0.3px;
    margin-bottom: 4px;
  }

  .brand-subtitle {
    font-size: 13px;
    color: #64748b;
    font-weight: 400;
  }

  /* --- Alerts --- */
  .alert {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 11px 14px;
    border-radius: 6px;
    font-size: 13px;
    line-height: 1.5;
    margin-bottom: 20px;
    border: 1px solid transparent;
  }

  .alert i { margin-top: 1px; flex-shrink: 0; font-size: 13px; }

  .alert-success {
    background: #f0fdf4;
    border-color: #bbf7d0;
    color: #166534;
  }

  .alert-error {
    background: #fef2f2;
    border-color: #fecaca;
    color: #991b1b;
  }

  /* --- Language switcher --- */
  .lang-switcher {
    display: flex;
    gap: 6px;
    justify-content: center;
    margin-bottom: 24px;
  }

  .lang-btn {
    display: inline-block;
    padding: 5px 14px;
    font-size: 12px;
    font-weight: 600;
    color: #475569;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    text-decoration: none;
    letter-spacing: 0.3px;
  }

  /* --- Form --- */
  .form-group {
    margin-bottom: 18px;
  }

  .form-label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #374151;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    margin-bottom: 6px;
  }

  .input-wrap {
    position: relative;
  }

  .input-wrap i {
    position: absolute;
    left: 13px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 13px;
    color: #94a3b8;
    pointer-events: none;
  }

  .form-input {
    width: 100%;
    padding: 10px 14px 10px 38px;
    font-size: 14px;
    font-family: inherit;
    color: #0f172a;
    background: #f8fafc;
    border: 1.5px solid #dde3ed;
    border-radius: 7px;
    outline: none;
  }

  .form-input:focus {
    border-color: #2563eb;
    background: #ffffff;
    box-shadow: none;
    outline: none;
  }

  /* --- Divider --- */
  .form-divider {
    height: 1px;
    background: #e8edf5;
    margin: 22px 0;
  }

  /* --- Submit button --- */
  .btn-submit {
    width: 100%;
    padding: 11px 16px;
    font-size: 14px;
    font-weight: 600;
    font-family: inherit;
    color: #ffffff;
    background: #2563eb;
    border: none;
    border-radius: 7px;
    cursor: pointer;
    letter-spacing: 0.2px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
  }

  .btn-submit:disabled {
    background: #94a3b8;
    cursor: not-allowed;
  }

  /* --- Footer --- */
  .card-footer {
    padding: 14px 40px;
    background: #f8fafc;
    border-top: 1px solid #e8edf5;
    text-align: center;
    font-size: 12px;
    color: #94a3b8;
  }
</style>

<div class="bg-shapes">
  <div class="bg-shape bg-s1"></div>
  <div class="bg-shape bg-s2"></div>
  <div class="bg-shape bg-s3"></div>
  <div class="bg-shape bg-s4"></div>
  <div class="bg-shape bg-s5"></div>
  <div class="bg-shape bg-s6"></div>
  <div class="bg-bar"></div>
</div>

<div class="page-wrapper">
  <div class="card">
    <div class="card-accent"></div>

    <div class="card-body">

      <div class="brand">
        <div class="brand-icon">
          <i class="fas fa-shield-halved"></i>
        </div>
        <div class="brand-title"><?= e(t('Welcome Back')) ?></div>
        <div class="brand-subtitle"><?= e(t('Sign in to your account to continue')) ?></div>
      </div>

      <?php if ($flash_success): ?>
        <div class="alert alert-success">
          <i class="fas fa-circle-check"></i>
          <span><?= e($flash_success) ?></span>
        </div>
      <?php endif; ?>

      <?php if ($flash_error): ?>
        <div class="alert alert-error">
          <i class="fas fa-circle-exclamation"></i>
          <span><?= e($flash_error) ?></span>
        </div>
      <?php endif; ?>

      <div class="lang-switcher">
        <a class="lang-btn" href="?lang=en">EN</a>
        <a class="lang-btn" href="?lang=ar">عربي</a>
      </div>

      <form method="post" id="loginForm">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="login">

        <div class="form-group">
          <label class="form-label" for="username"><?= e(t('Username')) ?></label>
          <div class="input-wrap">
            <i class="fas fa-user"></i>
            <input class="form-input" type="text" id="username" name="username" required autofocus>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="password"><?= e(t('Password')) ?></label>
          <div class="input-wrap">
            <i class="fas fa-lock"></i>
            <input class="form-input" type="password" id="password" name="password" required>
          </div>
        </div>

        <div class="form-divider"></div>

        <button type="submit" class="btn-submit" id="submitBtn">
          <i class="fas fa-arrow-right-to-bracket"></i>
          <?= e(t('Sign In')) ?>
        </button>
      </form>

    </div>

    <div class="card-footer">
      &copy; <?= date('Y') ?> <?= e($APP_NAME) ?> &mdash; <?= e(t('All rights reserved')) ?>
    </div>
  </div>
</div>

<script>
document.getElementById('loginForm').addEventListener('submit', function() {
  const btn = document.getElementById('submitBtn');
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <?= e(t('Signing In...','جاري تسجيل الدخول...')) ?>';
  btn.disabled = true;
});
</script>

<?php
layout_footer();
exit;
?>