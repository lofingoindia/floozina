<?php
layout_header("Login - {$APP_NAME}", $flash_success, $flash_error);
?>
<style>
:root {
  --primary: #2563eb;
  --primary-dark: #1e40af;
  --secondary: #3b82f6;
  --background: #f0f7ff;
  --surface: #ffffff;
  --error: #dc2626;
  --success: #16a34a;
  --text-primary: #0f172a;
  --text-secondary: #475569;
  --border: #cbd5e1;
  --border-dark: #94a3b8;
}

body {
  background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
}

.login-container {
  width: 100%;
  max-width: 380px;
  padding: 15px;
  animation: fadeIn 0.5s ease-out;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-20px); }
  to { opacity: 1; transform: translateY(0); }
}

.card {
  background: var(--surface);
  border-radius: 12px;
  box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
  padding: 30px;
  border: 1px solid var(--border);
}

.logo {
  text-align: center;
  margin-bottom: 20px;
}

.logo i {
  font-size: 40px;
  color: var(--primary);
}

.logo h1 {
  color: var(--text-primary);
  font-size: 22px;
  font-weight: 600;
  margin: 8px 0 3px;
}

.logo p {
  color: var(--text-secondary);
  font-size: 13px;
  margin: 0;
}

.form-group { margin-bottom: 16px; }

.form-group label {
  display: block;
  margin-bottom: 5px;
  color: var(--text-primary);
  font-size: 13px;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.form-group input {
  width: 100%;
  padding: 10px 14px;
  border: 2px solid var(--border);
  border-radius: 6px;
  font-size: 14px;
  transition: all 0.3s;
  box-sizing: border-box;
  background: var(--background);
  color: var(--text-primary);
}

.form-group input:focus {
  outline: none;
  border-color: var(--primary);
  background: var(--surface);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
}

.input-icon { position: relative; }

.input-icon i {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--secondary);
  font-size: 14px;
}

.input-icon input { padding-left: 40px; }

.btn-login {
  width: 100%;
  padding: 12px;
  background: var(--primary);
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
  margin-top: 8px;
  text-transform: uppercase;
  letter-spacing: 1px;
}

.btn-login:hover {
  background: var(--primary-dark);
  transform: translateY(-1px);
  box-shadow: 0 5px 15px rgba(37, 99, 235, 0.3);
}

.btn-login:disabled {
  background: var(--border);
  cursor: not-allowed;
}

.flash-message {
  padding: 12px 14px;
  border-radius: 6px;
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  border-left: 4px solid transparent;
  font-size: 13px;
}

.flash-message.success {
  background: #eff6ff;
  border-left-color: var(--primary);
  color: #1e40af;
}

.flash-message.error {
  background: #fef2f2;
  border-left-color: var(--error);
  color: #991b1b;
}

.footer-text {
  text-align: center;
  margin-top: 16px;
  color: var(--text-secondary);
  font-size: 11px;
  border-top: 1px solid var(--border);
  padding-top: 16px;
}
</style>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="login-container">
  <div class="card">
    
    <div class="logo">
      <i class="fas fa-shield-alt"></i>
      <h1><?= e(t('Welcome Back')) ?></h1>
      <p><?= e(t('Sign in to your account')) ?></p>
    </div>

    <?php if ($flash_success): ?>
      <div class="flash-message success">
        <i class="fas fa-check-circle"></i>
        <?= e($flash_success) ?>
      </div>
    <?php endif; ?>
    
    <?php if ($flash_error): ?>
      <div class="flash-message error">
        <i class="fas fa-exclamation-circle"></i>
        <?= e($flash_error) ?>
      </div>
    <?php endif; ?>

    <div style="display:flex;gap:8px;justify-content:center;margin:0 0 14px 0;flex-wrap:wrap">
      <a class="btn-login" style="width:auto;padding:8px 12px;font-size:13px;letter-spacing:.4px;text-transform:none" href="?lang=en">EN</a>
      <a class="btn-login" style="width:auto;padding:8px 12px;font-size:13px;letter-spacing:.4px;text-transform:none" href="?lang=ar">عربي</a>
    </div>

    <form method="post" id="loginForm">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="login">
      
      <div class="form-group">
        <label for="username"><?= e(t('Username')) ?></label>
        <div class="input-icon">
          <i class="fas fa-user"></i>
          <input type="text" id="username" name="username" required autofocus>
        </div>
      </div>

      <div class="form-group">
        <label for="password"><?= e(t('Password')) ?></label>
        <div class="input-icon">
          <i class="fas fa-lock"></i>
          <input type="password" id="password" name="password" required>
        </div>
      </div>

      <button type="submit" class="btn-login" id="submitBtn">
        <i class="fas fa-sign-in-alt"></i> <?= e(t('Sign in')) ?>
      </button>
    </form>

    <div class="footer-text">
      <i class="far fa-copyright"></i> <?= date('Y') ?> <?= $APP_NAME ?>
    </div>
  </div>
</div>

<script>
document.getElementById('loginForm').addEventListener('submit', function(e) {
  const btn = document.getElementById('submitBtn');
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <?= e(t('Signing In...','جاري تسجيل الدخول...')) ?>';
  btn.disabled = true;
});
</script>

<?php
layout_footer(); 
exit;
?>