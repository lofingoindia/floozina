<?php
layout_header("Login - {$APP_NAME}", $flash_success, $flash_error);
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
  /* ── RESET & BASE ─────────────────────────────────── */
  *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

  :root {
    --bg:     #0c0c10;
    --accent: #00e5ff;
  }

  body {
    background: var(--bg);
    min-height: 100vh;
    display: flex;
    font-family: 'Space Grotesk', sans-serif;
    color: var(--accent);
    -webkit-font-smoothing: antialiased;
  }

  /* ── SPLIT WRAPPER ────────────────────────────────── */
  .split-wrapper {
    display: flex;
    width: 100%;
    min-height: 100vh;
  }

  /* ── LEFT PANEL ───────────────────────────────────── */
  .left-panel {
    flex: 1;
    background: var(--bg);
    border-right: 1px solid var(--accent);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 64px 72px;
    position: relative;
    overflow: hidden;
  }

  /* corner geometric accents */
  .left-panel::before,
  .left-panel::after {
    content: '';
    position: absolute;
    border: 1px solid var(--accent);
    opacity: 0.07;
  }
  .left-panel::before {
    width: 520px; height: 520px;
    top: -160px; right: -160px;
  }
  .left-panel::after {
    width: 320px; height: 320px;
    bottom: -100px; left: -100px;
  }

  /* top eyebrow */
  .left-eyebrow {
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 5px;
    text-transform: uppercase;
    color: var(--accent);
    opacity: 0.4;
  }

  /* center block */
  .left-center { position: relative; z-index: 1; }

  .left-tagline {
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 5px;
    text-transform: uppercase;
    color: var(--accent);
    opacity: 0.45;
    margin-bottom: 28px;
  }

  .left-headline {
    font-size: clamp(36px, 4vw, 58px);
    font-weight: 700;
    line-height: 1.06;
    letter-spacing: -1.5px;
    color: var(--accent);
    margin-bottom: 28px;
  }

  .left-desc {
    font-size: 14px;
    font-weight: 400;
    line-height: 1.8;
    color: var(--accent);
    opacity: 0.4;
    max-width: 360px;
    margin-bottom: 56px;
  }

  /* dot grid */
  .dot-grid {
    display: grid;
    grid-template-columns: repeat(10, 1fr);
    gap: 10px;
    max-width: 280px;
  }
  .dot {
    width: 3px; height: 3px;
    background: var(--accent);
    opacity: 0.18;
  }
  .dot.lit { opacity: 0.9; }
  .dot.mid { opacity: 0.5; }

  /* structured line rows */
  .line-rows { margin-top: 48px; }
  .line-row {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 16px;
    opacity: 0.2;
  }
  .line-bar { height: 1px; background: var(--accent); }
  .line-cap {
    font-size: 9px;
    letter-spacing: 4px;
    text-transform: uppercase;
    color: var(--accent);
    white-space: nowrap;
  }

  /* bottom status */
  .left-footer {
    display: flex;
    align-items: center;
    gap: 10px;
    position: relative;
    z-index: 1;
  }
  .status-dot {
    width: 6px; height: 6px;
    background: var(--accent);
  }
  .status-text {
    font-size: 9px;
    font-weight: 600;
    letter-spacing: 4px;
    text-transform: uppercase;
    color: var(--accent);
    opacity: 0.4;
  }

  /* ── RIGHT PANEL ──────────────────────────────────── */
  .right-panel {
    width: 480px;
    min-width: 480px;
    background: var(--bg);
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 72px 60px;
  }

  /* form header */
  .form-eyebrow {
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 5px;
    text-transform: uppercase;
    color: var(--accent);
    opacity: 0.4;
    margin-bottom: 14px;
  }
  .form-title {
    font-size: 30px;
    font-weight: 700;
    letter-spacing: -0.5px;
    color: var(--accent);
    margin-bottom: 8px;
    line-height: 1.15;
  }
  .form-subtitle {
    font-size: 13px;
    color: var(--accent);
    opacity: 0.35;
    font-weight: 400;
  }
  .form-divider {
    width: 36px;
    height: 1px;
    background: var(--accent);
    opacity: 0.5;
    margin: 28px 0;
  }

  /* flash messages */
  .flash-msg {
    border: 1px solid var(--accent);
    padding: 13px 16px;
    margin-bottom: 28px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 12px;
    letter-spacing: 0.3px;
    color: var(--accent);
  }
  .flash-msg.error { opacity: 0.75; }
  .flash-msg.success { opacity: 1; }
  .flash-msg i { font-size: 13px; flex-shrink: 0; }

  /* language switcher */
  .lang-row {
    display: flex;
    gap: 8px;
    margin-bottom: 36px;
  }
  .lang-btn {
    padding: 7px 18px;
    border: 1px solid var(--accent);
    background: transparent;
    color: var(--accent);
    font-family: 'Space Grotesk', sans-serif;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 3px;
    text-transform: uppercase;
    text-decoration: none;
    opacity: 0.38;
  }
  .lang-btn.active {
    background: var(--accent);
    color: var(--bg);
    opacity: 1;
  }

  /* form fields */
  .field { margin-bottom: 24px; }

  .field-label {
    display: block;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 4px;
    text-transform: uppercase;
    color: var(--accent);
    opacity: 0.5;
    margin-bottom: 10px;
  }

  .field-wrap { position: relative; }

  .field-icon {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--accent);
    opacity: 0.3;
    font-size: 13px;
    pointer-events: none;
  }

  .field-input {
    width: 100%;
    padding: 15px 16px 15px 44px;
    background: transparent;
    border: 1px solid var(--accent);
    color: var(--accent);
    font-family: 'Space Grotesk', sans-serif;
    font-size: 14px;
    font-weight: 400;
    letter-spacing: 0.2px;
    outline: none;
    opacity: 0.6;
    -webkit-appearance: none;
    border-radius: 0;
  }
  .field-input:focus {
    opacity: 1;
    border: 2px solid var(--accent);
    padding: 14px 15px 14px 43px;
  }
  .field-input::placeholder {
    color: var(--accent);
    opacity: 0.15;
  }
  /* autofill override */
  .field-input:-webkit-autofill,
  .field-input:-webkit-autofill:focus {
    -webkit-box-shadow: 0 0 0 1000px var(--bg) inset !important;
    -webkit-text-fill-color: var(--accent) !important;
    caret-color: var(--accent);
  }

  /* forgot link row */
  .forgot-row {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 32px;
  }
  .forgot-link {
    font-size: 11px;
    color: var(--accent);
    opacity: 0.3;
    text-decoration: none;
    letter-spacing: 1px;
    font-weight: 500;
  }

  /* submit button */
  .btn-submit {
    width: 100%;
    padding: 17px;
    background: var(--accent);
    color: var(--bg);
    border: none;
    font-family: 'Space Grotesk', sans-serif;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 5px;
    text-transform: uppercase;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    border-radius: 0;
  }
  .btn-submit:disabled {
    opacity: 0.35;
    cursor: not-allowed;
  }

  /* form footer */
  .form-footer {
    margin-top: 44px;
    padding-top: 22px;
    border-top: 1px solid var(--accent);
    display: flex;
    justify-content: space-between;
    align-items: center;
    opacity: 0.25;
  }
  .footer-copy {
    font-size: 9px;
    letter-spacing: 3px;
    text-transform: uppercase;
    color: var(--accent);
  }
  .footer-version {
    font-size: 9px;
    letter-spacing: 3px;
    text-transform: uppercase;
    color: var(--accent);
  }

  /* ── RESPONSIVE ───────────────────────────────────── */
  @media (max-width: 960px) {
    .left-panel { display: none; }
    .right-panel { width: 100%; min-width: unset; padding: 64px 48px; }
  }
  @media (max-width: 520px) {
    .right-panel { padding: 48px 28px; }
  }
</style>

<div class="split-wrapper">

  <!-- ══ LEFT PANEL ══════════════════════════════════════ -->
  <div class="left-panel">
    <div class="left-eyebrow"><?= e($APP_NAME) ?> &mdash; <?= e(t('Secure Portal')) ?></div>

    <div class="left-center">
      <div class="left-tagline"><?= e(t('Next Generation Platform')) ?></div>
      <h2 class="left-headline">
        <?= e(t('Manage.<br>Scale.<br>Control.')) ?>
      </h2>
      <p class="left-desc">
        <?= e(t('Enterprise-grade infrastructure designed for speed, security, and precision at every level.')) ?>
      </p>

      <!-- dot grid decoration -->
      <div class="dot-grid">
        <?php
          $dotPattern = [1,0,0,1,0,0,1,0,0,1, 0,1,0,0,0,1,0,0,1,0, 0,0,1,0,0,0,1,0,0,0,
                         1,0,0,0,0,1,0,0,0,1, 0,0,0,1,0,0,0,0,1,0, 0,1,0,0,0,0,0,1,0,0,
                         0,0,0,0,1,0,0,0,0,0, 1,0,0,1,0,0,1,0,0,1, 0,0,1,0,0,1,0,0,0,0,
                         0,1,0,0,0,0,0,1,0,0];
          foreach($dotPattern as $i => $v):
            $cls = $v === 1 ? ($i % 7 === 0 ? 'dot lit' : 'dot mid') : 'dot';
        ?>
          <div class="<?= $cls ?>"></div>
        <?php endforeach; ?>
      </div>

      <!-- structured line rows -->
      <div class="line-rows">
        <div class="line-row"><div class="line-bar" style="width:80px"></div><span class="line-cap"><?= e(t('Authentication Layer')) ?></span><div class="line-bar" style="flex:1"></div></div>
        <div class="line-row"><div class="line-bar" style="width:32px"></div><span class="line-cap"><?= e(t('Encrypted Channel')) ?></span><div class="line-bar" style="flex:1"></div></div>
        <div class="line-row"><div class="line-bar" style="flex:1"></div><span class="line-cap"><?= e(t('Access Control')) ?></span><div class="line-bar" style="width:48px"></div></div>
      </div>
    </div>

    <div class="left-footer">
      <div class="status-dot"></div>
      <span class="status-text"><?= e(t('All Systems Operational')) ?></span>
    </div>
  </div>

  <!-- ══ RIGHT PANEL ═════════════════════════════════════ -->
  <div class="right-panel">

    <div class="form-eyebrow"><?= e(t('Sign In')) ?></div>
    <h1 class="form-title"><?= e(t('Welcome Back')) ?></h1>
    <p class="form-subtitle"><?= e(t('Enter your credentials to continue')) ?></p>
    <div class="form-divider"></div>

    <?php if ($flash_success): ?>
      <div class="flash-msg success">
        <i class="fas fa-check-circle"></i>
        <?= e($flash_success) ?>
      </div>
    <?php endif; ?>

    <?php if ($flash_error): ?>
      <div class="flash-msg error">
        <i class="fas fa-exclamation-triangle"></i>
        <?= e($flash_error) ?>
      </div>
    <?php endif; ?>

    <!-- language switcher -->
    <div class="lang-row">
      <a class="lang-btn<?= (!isset($_GET['lang']) || $_GET['lang']==='en') ? ' active' : '' ?>" href="?lang=en">EN</a>
      <a class="lang-btn<?= (isset($_GET['lang']) && $_GET['lang']==='ar') ? ' active' : '' ?>" href="?lang=ar">عربي</a>
    </div>

    <form method="post" id="loginForm">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="login">

      <div class="field">
        <label class="field-label" for="username"><?= e(t('Username')) ?></label>
        <div class="field-wrap">
          <i class="fas fa-user field-icon"></i>
          <input class="field-input" type="text" id="username" name="username" required autofocus autocomplete="username">
        </div>
      </div>

      <div class="field">
        <label class="field-label" for="password"><?= e(t('Password')) ?></label>
        <div class="field-wrap">
          <i class="fas fa-lock field-icon"></i>
          <input class="field-input" type="password" id="password" name="password" required autocomplete="current-password">
        </div>
      </div>

      <div class="forgot-row">
        <a class="forgot-link" href="#"><?= e(t('Forgot Password?')) ?></a>
      </div>

      <button type="submit" class="btn-submit" id="submitBtn">
        <i class="fas fa-arrow-right"></i> <?= e(t('Sign In')) ?>
      </button>
    </form>

    <div class="form-footer">
      <span class="footer-copy">&copy; <?= date('Y') ?> <?= e($APP_NAME) ?></span>
      <span class="footer-version">v2.0.0</span>
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