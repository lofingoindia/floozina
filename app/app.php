<?php
/**
 * Adobe Console Management SaaS (Single File) - REAL CONNECT (UMAPI + OAuth S2S)
 * What this adds:
 * - Admin Console: Test Connection button (token test)
 * - Admin Console: Fetch Product Profiles (UMAPI groups) auto save
 * - Reseller Assign: dropdown of profiles (auto fetched)
 * - Reseller Users: delete user (UMAPI remove group + optional removeFromOrg)
 * - Super Admin: view all users + delete
 * - Bulk Assign: Process multiple users at once with progress tracking
 * - Suspend/Activate Reseller: Temporarily suspend reseller access
 *
 * References (Adobe docs):
 * - OAuth Server-to-Server token (IMS) endpoint and client_credentials flow.
 * - UMAPI Groups/Profiles endpoint.
 * - UMAPI Action commands add/remove/removeFromOrg.
 */
declare(strict_types=1);

function starts_with(string $haystack, string $needle): bool {
    return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
}


/* ==========================
   DEBUG
   - Default OFF for production (prevents noisy warnings on mobile)
   - Enable by setting $APP_DEBUG=true in config/config.php or env APP_DEBUG=1
========================== */
$APP_DEBUG = $APP_DEBUG ?? (getenv('APP_DEBUG') ? true : false);
if ($APP_DEBUG) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
}

/* ==========================
   CONFIG
========================== */
require __DIR__ . '/../config/config.php'; // sets $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS, $APP_NAME, $SESSION_NAME

/* ==========================
   PORTAL MASTER SWITCH
   - Developer portal can activate/deactivate Super Admin + Reseller portals.
   - State stored in /config/portal_state.php (file-based).
========================== */
function portal_state_path(): string {
    return __DIR__ . '/../config/portal_state.php';
}
function portal_state_default(): array {
    // Access key for the Developer Portal
    $key = bin2hex(random_bytes(16));
    return ['active' => true, 'key' => $key, 'updated_at' => date('c')];
}
function portal_state_write(array $st): void {
    $p = portal_state_path();
    $st['updated_at'] = date('c');
    $php = "<?php\n// Auto-generated. Do not edit manually unless you know what you're doing.\nreturn " . var_export($st, true) . ";\n";
    $tmp = $p . '.tmp';
    @file_put_contents($tmp, $php, LOCK_EX);
    @rename($tmp, $p);
}
function portal_state_read(): array {
    $p = portal_state_path();
    if (!is_file($p)) {
        $st = portal_state_default();
        portal_state_write($st);
        return $st;
    }
    $st = @include $p;
    if (!is_array($st)) {
        $st = portal_state_default();
        portal_state_write($st);
    }
    // Normalize
    $st['active'] = isset($st['active']) ? (bool)$st['active'] : true;
    if (empty($st['key']) || !is_string($st['key'])) {
        $st['key'] = bin2hex(random_bytes(16));
    }
    $st['updated_at'] = $st['updated_at'] ?? date('c');
    return $st;
}
function portal_set_active(bool $active): void {
    $st = portal_state_read();
    $st['active'] = $active;
    portal_state_write($st);
}
function portal_is_active(): bool {
    $st = portal_state_read();
    return (bool)$st['active'];
}
function portal_key(): string {
    $st = portal_state_read();
    return (string)$st['key'];
}

/* ==========================
   APP SETTINGS
========================== */
if (!isset($APP_NAME) || $APP_NAME === '') $APP_NAME = "Adobe Console Management";
if (!isset($SESSION_NAME) || $SESSION_NAME === '') $SESSION_NAME = "adobe_saas_session";
session_name($SESSION_NAME);
session_start();

/* ==========================
   LANGUAGE (EN/AR)
   - Stores choice in session
   - Adds RTL support when Arabic
   - Optional GET param: ?lang=en|ar
========================== */
function app_lang(): string {
    $l = $_SESSION['lang'] ?? '';
    if ($l !== 'ar' && $l !== 'en') {
        // Fallback: keep language across session renewals (some hosts regenerate sessions after login)
        $c = $_COOKIE['lang'] ?? '';
        if ($c === 'ar' || $c === 'en') {
            $_SESSION['lang'] = $c;
            $l = $c;
        }
    }
    return ($l === 'ar') ? 'ar' : 'en';
}
function set_app_lang(string $lang): void {
    $lang = strtolower(trim($lang));
    $val = ($lang === 'ar') ? 'ar' : 'en';
    $_SESSION['lang'] = $val;
    // Persist for future requests even if PHP session changes
    setcookie('lang', $val, [
        'expires' => time() + 60*60*24*365,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
}
// Allow language switch via query param
if (isset($_GET['lang'])) {
    set_app_lang((string)$_GET['lang']);
}

// If no explicit lang in session yet, initialize from cookie
if (!isset($_SESSION['lang']) && isset($_COOKIE['lang'])) {
    $c = (string)$_COOKIE['lang'];
    if ($c === 'ar' || $c === 'en') {
        $_SESSION['lang'] = $c;
    }
}

function is_rtl(): bool { return app_lang() === 'ar'; }

/**
 * Translation helper
 * Usage:
 *   t('Logout','تسجيل الخروج')
 * If Arabic is active and $ar is provided, returns it.
 * Otherwise falls back to a dictionary lookup by English text.
 */
function t(string $en, ?string $ar = null): string {
    if (!is_rtl()) return $en;
    if ($ar !== null) return $ar;
    static $DICT = null;
    if ($DICT === null) {
        $DICT = [
            // Global / Shell
            'Management System' => 'نظام الإدارة',
            'Main Menu' => 'القائمة الرئيسية',
            'Logout' => 'تسجيل الخروج',
            'Dashboard' => 'لوحة التحكم',
            'Users' => 'المستخدمون',
            'Resellers' => 'الموزعون',
            'Announcements' => 'الإعلانات',
            'Billing' => 'الفوترة',
            'Transactions' => 'المعاملات',
            'Logs' => 'السجلات',
            'Status' => 'الحالة',
            'Super Admin' => 'المشرف العام',
            'Reseller' => 'موزّع',
            'Suspended' => 'معلّق',
            'Premium monochrome UI' => 'واجهة أنيقة أحادية اللون',

            // Auth
            'Secure admin access' => 'دخول آمن للمشرف',
            'Login' => 'تسجيل الدخول',
            'Username' => 'اسم المستخدم',
            'Password' => 'كلمة المرور',
            'Sign in' => 'دخول',
            'Welcome Back' => 'مرحباً بعودتك',
            'Sign in to your account' => 'سجّل الدخول إلى حسابك',

            // Common actions
            'Save' => 'حفظ',
            'Cancel' => 'إلغاء',
            'Delete' => 'حذف',
            'Edit' => 'تعديل',
            'Update' => 'تحديث',
            'Search' => 'بحث',
            'Filter' => 'تصفية',
            'Back' => 'رجوع',

            // Messages
            'Success' => 'تم بنجاح',
            'Error' => 'خطأ',
            'Warning' => 'تحذير',

            // Reseller suspension modal
            'Suspend Reseller' => 'تعليق الموزّع',
            'Reason for Suspension' => 'سبب التعليق',
            'Enter reason for suspending this reseller...' => 'اكتب سبب تعليق هذا الموزّع...',
        ];
    }
    return $DICT[$en] ?? $en;
}

/**
 * Page key -> Arabic label for sidebar.
 * Fallbacks to the generated label.
 */
function t_page_label(string $pageKey, string $fallback): string {
    if (!is_rtl()) return $fallback;
    static $PAGES = [
        // Admin
        'admin_dashboard' => 'لوحة التحكم',
        'admin_users' => 'المستخدمون',
        'admin_resellers' => 'الموزعون',
        'admin_consoles' => 'الكونسولات',
        'admin_transactions' => 'المعاملات',
        'admin_logs' => 'السجلات',
        'admin_announcements' => 'الإعلانات',
        'admin_migration' => 'الترحيل',

        // Reseller
        'reseller_dashboard' => 'لوحة التحكم',
        'reseller_users' => 'المستخدمون',
        'reseller_assign' => 'تعيين مستخدم',
        'reseller_bulk_assign' => 'تعيين جماعي',
        'reseller_bulk_history' => 'سجل التعيينات',
        'reseller_billing' => 'الفوترة',
        'reseller_announcements' => 'الإعلانات',
    ];
    return $PAGES[$pageKey] ?? $fallback;
}

// Output buffer translator (best-effort) so we don't have to edit every page.
// Only runs when Arabic is selected.
ob_start(function(string $html): string {
    if (!is_rtl()) return $html;
    // Phrase-level replacements. Keep this conservative to avoid breaking code/attributes.
    $map = [
        'Management System' => t('Management System'),
        'Main Menu' => t('Main Menu'),
        'Logout' => t('Logout'),
        'Super Admin' => t('Super Admin'),
        'Reseller' => t('Reseller'),
        'Suspended' => t('Suspended'),
        'Secure admin access' => t('Secure admin access'),
        'Username' => t('Username'),
        'Password' => t('Password'),
        'Sign in' => t('Sign in'),
        'Login' => t('Login'),
        'Welcome Back' => t('Welcome Back'),
        'Sign in to your account' => t('Sign in to your account'),
        'Save' => t('Save'),
        'Cancel' => t('Cancel'),
        'Delete' => t('Delete'),
        'Edit' => t('Edit'),
        'Update' => t('Update'),
        'Search' => t('Search'),
        'Filter' => t('Filter'),
        'Back' => t('Back'),
        'Suspend Reseller' => t('Suspend Reseller'),
        'Reason for Suspension' => t('Reason for Suspension'),
        'Enter reason for suspending this reseller...' => t('Enter reason for suspending this reseller...'),
        'Premium monochrome UI' => t('Premium monochrome UI'),
    ];
    return strtr($html, $map);
});

/* Security headers */
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

/* ==========================
   DB CONNECT
========================== */
try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (Throwable $e) {
    http_response_code(500);
    echo "<h2>Database connection failed</h2>";
    echo "<p>Check DB credentials in index.php and ensure the database exists.</p>";
    echo "<pre style='white-space:pre-wrap;color:#b00'>" . htmlspecialchars($e->getMessage()) . "</pre>";
    exit;
}


/* ==========================
   PDO RECONNECT HELPERS (for low wait_timeout hosting)
========================== */
function pdo_fresh(): PDO {
    global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS;
    return new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 10,
        ]
    );
}
function pdo_is_gone_away(Throwable $e): bool {
    $m = $e->getMessage();
    return (strpos($m, '2006') !== false) || (strpos($m, '2013') !== false);
}
function pdo_exec_retry(PDO &$pdo, string $sql, array $params = [], int $retries = 1): PDOStatement {
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st;
    } catch (Throwable $e) {
        if ($retries > 0 && pdo_is_gone_away($e)) {
            $pdo = pdo_fresh();
            $st = $pdo->prepare($sql);
            $st->execute($params);
            return $st;
        }
        throw $e;
    }
}

/* ==========================
   ADD SUSPEND COLUMNS TO DATABASE
========================== */
try {
    // Check if status column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM resellers LIKE 'status'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE resellers ADD COLUMN status ENUM('active','suspended') NOT NULL DEFAULT 'active'");
        $pdo->exec("ALTER TABLE resellers ADD COLUMN suspended_at DATETIME NULL");
        $pdo->exec("ALTER TABLE resellers ADD COLUMN suspended_reason TEXT NULL");
        $pdo->exec("ALTER TABLE resellers ADD COLUMN suspended_by INT NULL");
    }
} catch (Throwable $e) {
    // Table might not exist yet, ignore
}

/* ==========================
   HELPERS
========================== */
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

/**
 * Product profile helpers
 * - Accepts:
 *   - single string ("Profile A")
 *   - comma separated string ("A, B")
 *   - JSON array string ("[\"A\",\"B\"]")
 *   - PHP array
 */
function parse_profile_list($v): array {
    if (is_array($v)) {
        $arr = $v;
    } else {
        $s = trim((string)$v);
        if ($s === '') return [];
        if (strlen($s) > 1 && $s[0] === '[') {
            $j = json_decode($s, true);
            $arr = is_array($j) ? $j : [$s];
        } else if (strpos($s, ',') !== false) {
            $arr = array_map('trim', explode(',', $s));
        } else {
            $arr = [$s];
        }
    }
    $out = [];
    foreach ($arr as $x) {
        $x = trim((string)$x);
        if ($x === '') continue;
        $out[] = $x;
    }
    return array_values(array_unique($out));
}

function store_profile_list(array $profiles): string {
    $profiles = array_values(array_unique(array_filter(array_map('strval', $profiles), fn($x) => trim($x) !== '')));
    if (count($profiles) === 1) return (string)$profiles[0];
    return json_encode($profiles, JSON_UNESCAPED_UNICODE);
}

function reseller_allowed_profile_groups(PDO $pdo, int $reseller_id, int $console_id): ?array {
    // If reseller has a custom allow-list for this console, return it.
    // If not configured, return NULL meaning "all profiles allowed".
    try {
        $st = $pdo->prepare("SELECT group_name FROM reseller_console_profiles WHERE reseller_id=? AND console_id=? ORDER BY group_name ASC");
        $st->execute([$reseller_id, $console_id]);
        $rows = $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $rows = array_values(array_unique(array_map('strval', $rows)));
        return count($rows) > 0 ? $rows : null;
    } catch (Throwable $e) {
        return null;
    }
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_verify(): void {
    // Cron/CLI worker mode does not have browser CSRF tokens.
    if (defined('APP_CLI')) {
        return;
    }
    $token = $_POST['csrf'] ?? '';
    if (!$token || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(403);
        die("CSRF validation failed.");
    }
}
function is_logged_in(): bool { return !empty($_SESSION['user_id']); }
function is_super_admin(): bool { return !empty($_SESSION['is_super_admin']); }
function money_fmt($n): string { return number_format((float)$n, 2); }

function validate_date_ymd(?string $s): ?string {
    $s = trim((string)$s);
    if ($s === '') return null;
    // Expect YYYY-MM-DD
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return null;
    [$y,$m,$d] = array_map('intval', explode('-', $s));
    if (!checkdate($m,$d,$y)) return null;
    return sprintf('%04d-%02d-%02d', $y,$m,$d);
}

/**
 * Compute billing months (integer) from a custom expiry date.
 * We keep billing model "per month", so we approximate by 30-day months and round UP.
 */
function billing_months_from_expiry(string $expiryYmd, ?string $baseYmd = null): int {
    $baseYmd = $baseYmd ?: date('Y-m-d');
    $base = new DateTime($baseYmd);
    $end  = new DateTime($expiryYmd);
    $diffDays = (int)$base->diff($end)->format('%r%a');
    // If expiry is today/past, still bill at least 1 month (legacy behavior)
    if ($diffDays <= 0) return 1;
    return max(1, (int)ceil($diffDays / 30));
}


/**
 * Refresh session user fields from DB on every request.
 */
function refresh_session_user(PDO $pdo): void {
    if (empty($_SESSION['user_id'])) return;
    $uid = (int)$_SESSION['user_id'];

    $stmt = $pdo->prepare("SELECT id, role, username, email, company_name, monthly_rate, balance, total_billed, total_paid, status FROM resellers WHERE id=? LIMIT 1");
    $stmt->execute([$uid]);
    $u = $stmt->fetch();
    if (!$u) return;

    $_SESSION['username'] = (string)$u['username'];
    $_SESSION['role'] = (string)$u['role'];
    $_SESSION['is_super_admin'] = ($u['role'] === 'super_admin');
    $_SESSION['monthly_rate'] = (float)$u['monthly_rate'];
    $_SESSION['email'] = (string)$u['email'];
    $_SESSION['company_name'] = (string)$u['company_name'];
    $_SESSION['balance'] = (float)$u['balance'];
    $_SESSION['total_billed'] = (float)$u['total_billed'];
    $_SESSION['total_paid'] = (float)$u['total_paid'];
    $_SESSION['status'] = (string)($u['status'] ?? 'active');
}

function log_action(PDO $pdo, string $action, string $desc, ?string $performed_by = null, ?int $reseller_id = null, ?int $console_id = null, string $status = 'success', ?string $error = null): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $pdo->prepare("INSERT INTO audit_log (action_type, description, performed_by, reseller_id, console_id, status, error_message, ip_address)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$action, $desc, $performed_by, $reseller_id, $console_id, $status, $error, $ip]);
}

/* ==========================
   HTTP (cURL)
========================== */
function http_request(string $method, string $url, array $headers = [], ?string $body = null, int $timeout = 30): array {
    if (!function_exists('curl_init')) {
        throw new RuntimeException("cURL is not enabled on this hosting. Enable PHP cURL extension.");
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    if (!empty($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false) {
        throw new RuntimeException("HTTP request failed: " . $err);
    }

    return ['code' => $code, 'body' => $resp];
}

/* ==========================
   SCHEMA
========================== */
function ensure_schema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_consoles (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        client_id VARCHAR(120) NULL,
        client_secret VARCHAR(255) NULL,
        technical_account_id VARCHAR(255) NULL,
        organization_id VARCHAR(255) NULL,
        scopes TEXT NULL,
        ims_host VARCHAR(255) DEFAULT 'ims-na1.adobelogin.com',
        umapi_host VARCHAR(255) DEFAULT 'usermanagement.adobe.io',
        status ENUM('active','inactive','down') DEFAULT 'active',
        is_backup BOOLEAN DEFAULT 0,
        profiles_json MEDIUMTEXT NULL,
        profiles_fetched_at DATETIME NULL,
        last_test_at DATETIME NULL,
        last_test_ok BOOLEAN DEFAULT 0,
        last_test_message TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS resellers (
        id INT PRIMARY KEY AUTO_INCREMENT,
        role ENUM('super_admin','reseller') NOT NULL DEFAULT 'reseller',
        username VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        company_name VARCHAR(255) NULL,
        monthly_rate DECIMAL(10,2) DEFAULT 1.00,
        balance DECIMAL(10,2) DEFAULT 0.00,
        total_billed DECIMAL(10,2) DEFAULT 0.00,
        total_paid DECIMAL(10,2) DEFAULT 0.00,
        active_console_id INT NULL,
        backup_console_id INT NULL,
        status ENUM('active','suspended') DEFAULT 'active',
        suspended_at DATETIME NULL,
        suspended_reason TEXT NULL,
        suspended_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (active_console_id) REFERENCES admin_consoles(id) ON DELETE SET NULL,
        FOREIGN KEY (backup_console_id) REFERENCES admin_consoles(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        reseller_id INT NOT NULL,
        console_id INT NULL,
        email VARCHAR(255) NOT NULL,
        organization VARCHAR(255) NULL,
        product_profile VARCHAR(255) NULL,
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at DATE NULL,
        status ENUM('active','expiring','expired') DEFAULT 'active',
        refund_eligible_until DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (reseller_id) REFERENCES resellers(id) ON DELETE CASCADE,
        FOREIGN KEY (console_id) REFERENCES admin_consoles(id) ON DELETE SET NULL,
        UNIQUE KEY uniq_reseller_email (reseller_id, email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_log (
        id INT PRIMARY KEY AUTO_INCREMENT,
        action_type VARCHAR(100) NOT NULL,
        description TEXT,
        performed_by VARCHAR(255) NULL,
        reseller_id INT NULL,
        console_id INT NULL,
        status VARCHAR(50) DEFAULT 'success',
        error_message TEXT NULL,
        ip_address VARCHAR(45) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_created_at (created_at),
        FOREIGN KEY (reseller_id) REFERENCES resellers(id) ON DELETE SET NULL,
        FOREIGN KEY (console_id) REFERENCES admin_consoles(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS transactions (
        id INT PRIMARY KEY AUTO_INCREMENT,
        reseller_id INT NOT NULL,
        user_id INT NULL,
        type ENUM('charge','payment','refund') NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        description TEXT,
        status ENUM('pending','completed','failed') DEFAULT 'completed',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (reseller_id) REFERENCES resellers(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS announcements (
        id INT PRIMARY KEY AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        type ENUM('info','warning','success','danger') DEFAULT 'info',
        target ENUM('all','super_admin','reseller') DEFAULT 'all',
        is_active BOOLEAN DEFAULT 1,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at DATE NULL,
        FOREIGN KEY (created_by) REFERENCES resellers(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Backward-compatible migration: allow client-facing announcements (for public status page)
    try {
        $pdo->exec("ALTER TABLE announcements MODIFY target ENUM('all','super_admin','reseller','client') DEFAULT 'all'");
    } catch (Throwable $e) {
        // ignore (e.g., fresh install already has the enum or insufficient privileges)
    }


    // Bulk Assign Tables
    $pdo->exec("CREATE TABLE IF NOT EXISTS bulk_jobs (
        id INT PRIMARY KEY AUTO_INCREMENT,
        reseller_id INT NOT NULL,
        console_id INT NOT NULL,
        product_profile VARCHAR(255) NOT NULL,
        expires_at DATE NULL,
        months INT NOT NULL DEFAULT 1,
        team VARCHAR(255) NOT NULL DEFAULT '',
        total_items INT NOT NULL DEFAULT 0,
        done_items INT NOT NULL DEFAULT 0,
        failed_items INT NOT NULL DEFAULT 0,
        status ENUM('running','done','failed','canceled') NOT NULL DEFAULT 'running',
        error_message TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (reseller_id) REFERENCES resellers(id) ON DELETE CASCADE,
        FOREIGN KEY (console_id) REFERENCES admin_consoles(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS bulk_job_items (
        id INT PRIMARY KEY AUTO_INCREMENT,
        job_id INT NOT NULL,
        email VARCHAR(190) NOT NULL,
        status ENUM('pending','done','failed') NOT NULL DEFAULT 'pending',
        message TEXT NULL,
        user_id INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX(job_id),
        INDEX(email),
        FOREIGN KEY (job_id) REFERENCES bulk_jobs(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    
    // Bulk Jobs upgrades: job_type + skipped_items + remove_from_org (for bulk delete)
    try {
        $cols = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='bulk_jobs'")->fetchAll(PDO::FETCH_COLUMN);
        $cols = array_map('strtolower', $cols ?: []);
        if (!in_array('job_type', $cols, true)) {
            $pdo->exec("ALTER TABLE bulk_jobs ADD COLUMN job_type ENUM('assign','delete') NOT NULL DEFAULT 'assign' AFTER id");
        }
        if (!in_array('skipped_items', $cols, true)) {
            $pdo->exec("ALTER TABLE bulk_jobs ADD COLUMN skipped_items INT NOT NULL DEFAULT 0 AFTER failed_items");
        }
        if (!in_array('remove_from_org', $cols, true)) {
            $pdo->exec("ALTER TABLE bulk_jobs ADD COLUMN remove_from_org TINYINT(1) NOT NULL DEFAULT 0 AFTER team");
        }
        if (!in_array('expires_at', $cols, true)) {
            $pdo->exec("ALTER TABLE bulk_jobs ADD COLUMN expires_at DATE NULL AFTER product_profile");
        }
    } catch (Throwable $e) { /* ignore */ }

    try {
        $cols = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='bulk_job_items'")->fetchAll(PDO::FETCH_COLUMN);
        $cols = array_map('strtolower', $cols ?: []);
        if (in_array('status', $cols, true)) {
            // Add 'skipped' to enum if not present
            $def = $pdo->query("SHOW COLUMNS FROM bulk_job_items LIKE 'status'")->fetch();
            $type = strtolower((string)($def['Type'] ?? ''));
            if (strpos($type, "'skipped'") === false) {
                $pdo->exec("ALTER TABLE bulk_job_items MODIFY COLUMN status ENUM('pending','done','failed','skipped') NOT NULL DEFAULT 'pending'");
            }
        }
    } catch (Throwable $e) { /* ignore */ }

// Extra: map multiple consoles per reseller (priority order)
    $pdo->exec("CREATE TABLE IF NOT EXISTS reseller_consoles (
        id INT PRIMARY KEY AUTO_INCREMENT,
        reseller_id INT NOT NULL,
        console_id INT NOT NULL,
        priority INT NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_reseller_priority (reseller_id, priority),
        UNIQUE KEY uniq_reseller_console (reseller_id, console_id),
        FOREIGN KEY (reseller_id) REFERENCES resellers(id) ON DELETE CASCADE,
        FOREIGN KEY (console_id) REFERENCES admin_consoles(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Extra: optional per-reseller allow-list of product profiles per console
    // If rows exist for (reseller_id, console_id) => reseller can only use those profiles.
    // If no rows => reseller can use ALL profiles in admin_consoles.profiles_json.
    $pdo->exec("CREATE TABLE IF NOT EXISTS reseller_console_profiles (
        id INT PRIMARY KEY AUTO_INCREMENT,
        reseller_id INT NOT NULL,
        console_id INT NOT NULL,
        group_name VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_reseller_console_group (reseller_id, console_id, group_name),
        INDEX idx_reseller_console (reseller_id, console_id),
        FOREIGN KEY (reseller_id) REFERENCES resellers(id) ON DELETE CASCADE,
        FOREIGN KEY (console_id) REFERENCES admin_consoles(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Extra: console health / lifecycle fields for auto migration
    $cols = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
                         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='admin_consoles'")->fetchAll(PDO::FETCH_COLUMN);
    $cols = array_map('strtolower', $cols ?: []);
    if (!in_array('console_expires_at', $cols, true)) {
        $pdo->exec("ALTER TABLE admin_consoles ADD COLUMN console_expires_at DATE NULL");
    }
    if (!in_array('suspended_at', $cols, true)) {
        $pdo->exec("ALTER TABLE admin_consoles ADD COLUMN suspended_at DATETIME NULL");
    }
    if (!in_array('down_reason', $cols, true)) {
        $pdo->exec("ALTER TABLE admin_consoles ADD COLUMN down_reason TEXT NULL");
    }

    // Extra: migration jobs (for real-time progress)
    $pdo->exec("CREATE TABLE IF NOT EXISTS migration_jobs (
        id INT PRIMARY KEY AUTO_INCREMENT,
        from_console_id INT NOT NULL,
        to_console_id INT NOT NULL,
        total_users INT NOT NULL DEFAULT 0,
        processed_users INT NOT NULL DEFAULT 0,
        last_user_id INT NOT NULL DEFAULT 0,
        status ENUM('running','done','failed','canceled') NOT NULL DEFAULT 'running',
        mapping_updated TINYINT(1) NOT NULL DEFAULT 0,
        error_message TEXT NULL,
        created_by VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status (status),
        INDEX idx_from (from_console_id),
        FOREIGN KEY (from_console_id) REFERENCES admin_consoles(id) ON DELETE CASCADE,
        FOREIGN KEY (to_console_id) REFERENCES admin_consoles(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Add new columns for real Adobe-side migration (safe ALTER; ignore if already exists)
    try { $pdo->exec("ALTER TABLE migration_jobs ADD COLUMN target_profile VARCHAR(255) NULL"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE migration_jobs ADD COLUMN remove_from_source TINYINT(1) NOT NULL DEFAULT 0"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE migration_jobs ADD COLUMN profile_map_json TEXT NULL"); } catch (Throwable $e) {}
    
    // Auto Migration: job users snapshot (robust + per-user status)
    $pdo->exec("CREATE TABLE IF NOT EXISTS migration_job_users (
        job_id INT NOT NULL,
        user_id INT NOT NULL,
        reseller_id INT NULL,
        email VARCHAR(255) NOT NULL,
        source_profile VARCHAR(255) NULL,
        target_profile VARCHAR(255) NULL,
        status ENUM('pending','done','failed') NOT NULL DEFAULT 'pending',
        error_message TEXT NULL,
        processed_at DATETIME NULL,
        PRIMARY KEY (job_id, user_id),
        INDEX idx_job_status (job_id, status),
        INDEX idx_job_user (job_id, user_id),
        FOREIGN KEY (job_id) REFERENCES migration_jobs(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

}
ensure_schema($pdo);

/* ==========================
   BILLING HELPERS
========================== */
function add_transaction(PDO $pdo, int $reseller_id, ?int $user_id, string $type, float $amount, string $desc): void {
    $stmt = $pdo->prepare("INSERT INTO transactions (reseller_id, user_id, type, amount, description, status)
                           VALUES (?, ?, ?, ?, ?, 'completed')");
    $stmt->execute([$reseller_id, $user_id, $type, $amount, $desc]);
}
function update_balance(PDO $pdo, int $reseller_id, float $delta, string $type, string $desc, ?int $user_id = null): void {
    $already = $pdo->inTransaction();
    if (!$already) $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare("SELECT balance, total_billed, total_paid FROM resellers WHERE id = ? FOR UPDATE");
        $stmt->execute([$reseller_id]);
        $r = $stmt->fetch();
        if (!$r) throw new RuntimeException("Reseller not found.");

        $balance = (float)$r['balance'];
        $billed  = (float)$r['total_billed'];
        $paid    = (float)$r['total_paid'];

        if ($type === 'charge') { $balance -= $delta; $billed += $delta; }
        elseif ($type === 'payment') { $balance += $delta; $paid += $delta; }
        elseif ($type === 'refund') { $balance += $delta; $billed -= $delta; if ($billed < 0) $billed = 0; }
        else throw new RuntimeException("Invalid balance type.");

        $stmt = $pdo->prepare("UPDATE resellers SET balance=?, total_billed=?, total_paid=? WHERE id=?");
        $stmt->execute([$balance, $billed, $paid, $reseller_id]);

        add_transaction($pdo, $reseller_id, $user_id, $type, $delta, $desc);

        if (!$already) $pdo->commit();
    } catch (Throwable $e) {
        if (!$already && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/* ==========================
   ADOBE (OAuth S2S + UMAPI)
========================== */
function get_console(PDO $pdo, int $console_id): array {
    $stmt = $pdo->prepare("SELECT * FROM admin_consoles WHERE id=?");
    $stmt->execute([$console_id]);
    $c = $stmt->fetch();
    if (!$c) throw new RuntimeException("Console not found.");
    return $c;
}

/**
 * Token: POST https://{ims_host}/ims/token/v3
 * grant_type=client_credentials&client_id=...&client_secret=...&scope=...
 */
function adobe_get_access_token(array $console): string {
    $ims_host = trim((string)($console['ims_host'] ?? 'ims-na1.adobelogin.com'));
    $url = "https://" . $ims_host . "/ims/token/v3";

    $client_id = trim((string)$console['client_id']);
    $client_secret = (string)$console['client_secret'];
    $scopes = trim((string)($console['scopes'] ?? ''));

    if ($client_id === '' || $client_secret === '' || $scopes === '') {
        throw new RuntimeException("Missing Client ID / Client Secret / Scopes in this console.");
    }

    $body = http_build_query([
        'grant_type' => 'client_credentials',
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'scope' => $scopes,
    ]);

    $resp = http_request("POST", $url, [
        "Content-Type: application/x-www-form-urlencoded",
        "Accept: application/json",
    ], $body);

    $json = json_decode($resp['body'], true);
    if ($resp['code'] !== 200) {
        $msg = $resp['body'];
        throw new RuntimeException("Token failed (HTTP {$resp['code']}): " . $msg);
    }
    if (!is_array($json) || empty($json['access_token'])) {
        throw new RuntimeException("Token response missing access_token: " . $resp['body']);
    }
    return (string)$json['access_token'];
}

/**
 * UMAPI base: https://{umapi_host}/v2/usermanagement
 * - Get groups/profiles: GET /groups/{orgId}/{page}
 */
function umapi_get_groups(array $console): array {
    $orgId = trim((string)$console['organization_id']);
    if ($orgId === '') throw new RuntimeException("Missing Organization ID in console.");

    $token = adobe_get_access_token($console);
    $host = trim((string)($console['umapi_host'] ?? 'usermanagement.adobe.io'));
    $client_id = trim((string)$console['client_id']);

    $all = [];
    $page = 0;

    while (true) {
        $url = "https://{$host}/v2/usermanagement/groups/" . rawurlencode($orgId) . "/{$page}";
        $resp = http_request("GET", $url, [
            "Authorization: Bearer {$token}",
            "x-api-key: {$client_id}",
            "Accept: application/json",
        ], null, 45);

        if ($resp['code'] !== 200) {
            throw new RuntimeException("Fetch profiles failed (HTTP {$resp['code']}): " . $resp['body']);
        }

        $json = json_decode($resp['body'], true);
        if (!is_array($json)) throw new RuntimeException("Invalid JSON from UMAPI groups: " . $resp['body']);

        $groups = $json['groups'] ?? [];
        if (is_array($groups)) {
            foreach ($groups as $g) {
                if (!empty($g['groupName'])) {
                    $all[] = [
                        'groupName' => (string)$g['groupName'],
                        'groupType' => (string)($g['groupType'] ?? ''),
                        'productName' => (string)($g['productName'] ?? ''),
                        'description' => (string)($g['description'] ?? ''),
                    ];
                }
            }
        }

        $lastPage = (int)($json['lastPage'] ?? 0);
        if ($page >= $lastPage) break;
        $page++;
        if ($page > 200) break;
    }

    $map = [];
    foreach ($all as $g) $map[$g['groupName']] = $g;
    return array_values($map);
}

/**
 * UMAPI Action endpoint:
 * POST https://{host}/v2/usermanagement/action/{orgId}
 * Body: commands (addAdobeID/createFederatedID/createEnterpriseID + add/remove group + removeFromOrg)
 */
function umapi_action(array $console, array $commands): array {
    $orgId = trim((string)$console['organization_id']);
    $token = adobe_get_access_token($console);
    $host = trim((string)($console['umapi_host'] ?? 'usermanagement.adobe.io'));
    $client_id = trim((string)$console['client_id']);

    $url = "https://{$host}/v2/usermanagement/action/" . rawurlencode($orgId);

    // IMPORTANT: ensure commands array is a proper JSON list (0..n-1 keys).
    // If keys are non-sequential (e.g. after skips/unsets), json_encode turns it into an object
    // and UMAPI returns: error.command.malformed / Expected a valid JSON payload.
    if (!empty($commands)) {
        $keys = array_keys($commands);
        $isList = ($keys === range(0, count($commands) - 1));
        if (!$isList) {
            $commands = array_values($commands);
        }
    }

    $payload = json_encode($commands, JSON_UNESCAPED_SLASHES);
    if ($payload === false) throw new RuntimeException("JSON encode failed for UMAPI action.");

    $resp = http_request("POST", $url, [
        "Authorization: Bearer {$token}",
        "x-api-key: {$client_id}",
        "Content-Type: application/json",
        "Accept: application/json",
    ], $payload, 60);

    $json = json_decode($resp['body'], true);
    return ['code' => $resp['code'], 'raw' => $resp['body'], 'json' => $json];
}

/**
 * Send UMAPI commands safely in smaller chunks.
 *
 * Problem seen in production:
 * UMAPI returns HTTP 400 with:
 *   {"result":"error.command.malformed","message":"Expected a valid JSON payload"}
 * This typically happens when the request body becomes too large or gets truncated/stripped
 * by hosting/WAF/proxy, or when upstream cannot parse the JSON body.
 *
 * Fix:
 * - Try sending the batch.
 * - If HTTP 400 + "error.command.malformed", split the batch and retry (binary split).
 *
 * Returns:
 *  [
 *    'resultMap' => [requestID => ['ok'=>bool,'msg'=>string]],
 *    'warnings'  => [string...]
 *  ]
 */
function umapi_action_safe(array $console, array $commands, int $maxSplitDepth = 6): array {
    $warnings = [];
    $resultMap = [];

    $send = function(array $cmds, int $depth) use ($console, $maxSplitDepth, &$warnings, &$resultMap, &$send) {
        if (!$cmds) return;

        $resp = umapi_action($console, $cmds);
        $httpOk = ($resp['code'] >= 200 && $resp['code'] < 300);

        if ($httpOk) {
            // Parse per-request results (best-effort)
            $raw = $resp['json'];
            $list = $raw;
            if (is_array($raw) && isset($raw['result']) && is_array($raw['result'])) {
                $list = $raw['result'];
            }
            if (is_array($list) && array_is_list($list)) {
                foreach ($list as $it) {
                    if (!is_array($it)) continue;
                    $rid = (string)($it['requestID'] ?? $it['requestId'] ?? '');
                    if ($rid === '') continue;

                    $ok = true;
                    $msg = '';
                    if (!empty($it['errors']) && is_array($it['errors'])) {
                        $ok = false;
                        $msg = json_encode($it['errors'], JSON_UNESCAPED_SLASHES);
                    } elseif (!empty($it['errorCode']) || !empty($it['error'])) {
                        $ok = false;
                        $msg = (string)($it['error'] ?? $it['errorCode']);
                    } elseif (isset($it['status']) && is_string($it['status'])) {
                        $st = strtolower($it['status']);
                        if (!in_array($st, ['ok','success','succeeded','done'], true)) {
                            $ok = false;
                            $msg = (string)$it['status'];
                        }
                    }
                    $resultMap[$rid] = ['ok' => $ok, 'msg' => (string)$msg];
                }
            } else {
                // No per-command results => mark all as ok
                foreach ($cmds as $c) {
                    $rid = (string)($c['requestID'] ?? $c['requestId'] ?? '');
                    if ($rid !== '') $resultMap[$rid] = ['ok' => true, 'msg' => ''];
                }
            }
            return;
        }

        $rawBody = (string)($resp['raw'] ?? '');
        $isMalformed = ($resp['code'] === 400) && (stripos($rawBody, 'error.command.malformed') !== false);

        if ($isMalformed && $depth < $maxSplitDepth && count($cmds) > 1) {
            $mid = (int)ceil(count($cmds) / 2);
            $left = array_slice($cmds, 0, $mid);
            $right = array_slice($cmds, $mid);
            $warnings[] = 'UMAPI 400 malformed JSON: splitting batch (' . count($cmds) . ' -> ' . count($left) . '+' . count($right) . ')';
            $send($left, $depth + 1);
            $send($right, $depth + 1);
            return;
        }

        // Hard failure: mark this whole chunk failed
        $msg = 'UMAPI add failed (HTTP ' . (int)$resp['code'] . ')';
        if ($rawBody !== '') $msg .= ' | ' . substr($rawBody, 0, 900);
        foreach ($cmds as $c) {
            $rid = (string)($c['requestID'] ?? $c['requestId'] ?? '');
            if ($rid !== '') $resultMap[$rid] = ['ok' => false, 'msg' => $msg];
        }
    };

    $send($commands, 0);
    return ['resultMap' => $resultMap, 'warnings' => $warnings];
}

/* ==========================
   VALIDATE EMAILS (bulk)
========================== */
function validate_emails(string $text): array {
    $lines = preg_split("/\r\n|\n|\r/", trim($text));
    $valid = [];
    $invalid = [];
    foreach ($lines as $line) {
        $email = trim($line);
        if ($email === '') continue;
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) $valid[] = $email;
        else $invalid[] = $email;
    }
    $valid = array_values(array_unique($valid));
    return ['valid' => $valid, 'invalid' => $invalid];
}

/* ==========================
   FIRST RUN?
========================== */
$stmt = $pdo->query("SELECT COUNT(*) AS c FROM resellers WHERE role='super_admin'");
$has_super = ((int)$stmt->fetch()['c']) > 0;

/* ==========================
   ROUTING
========================== */
$page = $_GET['page'] ?? 'home';
$action = $_POST['action'] ?? null;
$flash_success = null;
$flash_error = null;

refresh_session_user($pdo);

/* ==========================
   AJAX HANDLERS (for bulk assign and other real-time features)
========================== */
if (!empty($_GET['ajax']) && is_logged_in()) {
    header('Content-Type: application/json; charset=utf-8');

    if (!portal_is_active()) {
        http_response_code(503);
        echo json_encode(['ok' => false, 'error' => 'Portal is currently deactivated. Please contact your developer to re-activate this.']);
        exit;
    }

    $ajax = (string)$_GET['ajax'];

    try {
        // Auto-dispatch AJAX to files (no need to edit app.php)
        // Create files here:
        //   /pages/admin/ajax/<ajax>.php
        //   /pages/reseller/ajax/<ajax>.php
        // Each file should echo JSON and exit.
        $ajax = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$ajax);
        $roleDir = is_super_admin() ? (__DIR__ . '/../pages/admin/ajax') : (__DIR__ . '/../pages/reseller/ajax');
        $sharedDir = __DIR__ . '/ajax';
        $candidates = [
            $roleDir . '/' . $ajax . '.php',
            $sharedDir . '/' . $ajax . '.php',
        ];
        foreach ($candidates as $cand) {
            if (is_file($cand)) {
                require $cand;
                exit;
            }
        }


        // Reseller AJAX: Get profiles for a console
        if ($ajax === 'reseller_ajax_get_profiles' && !is_super_admin()) {
            csrf_verify();
            $reseller_id = (int)$_SESSION['user_id'];
            $console_id = (int)($_POST['console_id'] ?? 0);
            
            if ($console_id <= 0) {
                echo json_encode(['ok' => false, 'error' => 'Invalid console']);
                exit;
            }

            // Verify console assigned to reseller
            $stmt = $pdo->prepare("SELECT 1 FROM reseller_consoles WHERE reseller_id=? AND console_id=? LIMIT 1");
            $stmt->execute([$reseller_id, $console_id]);
            $assigned = (bool)$stmt->fetchColumn();
            
            if (!$assigned) {
                $stmt = $pdo->prepare("SELECT active_console_id FROM resellers WHERE id=?");
                $stmt->execute([$reseller_id]);
                $assigned = ((int)$stmt->fetchColumn() === $console_id);
            }
            
            if (!$assigned) {
                echo json_encode(['ok' => false, 'error' => 'Console not assigned to you']);
                exit;
            }

            $c = get_console($pdo, $console_id);
            $profiles = [];
            if (!empty($c['profiles_json'])) {
                $arr = json_decode((string)$c['profiles_json'], true);
                if (is_array($arr)) $profiles = $arr;
            }

            // Apply per-reseller allow-list if configured
            $allowed = reseller_allowed_profile_groups($pdo, $reseller_id, $console_id);
            if (is_array($allowed)) {
                $allowedSet = array_fill_keys($allowed, true);
                $profiles = array_values(array_filter($profiles, function($p) use ($allowedSet){
                    $gn = is_array($p) ? (string)($p['groupName'] ?? '') : '';
                    return $gn !== '' && isset($allowedSet[$gn]);
                }));
            }
            echo json_encode(['ok' => true, 'profiles' => $profiles]);
            exit;
        }

        // Reseller AJAX: Initialize bulk job
        if ($ajax === 'reseller_bulk_init' && !is_super_admin()) {
            csrf_verify();
            $reseller_id = (int)$_SESSION['user_id'];
            $console_id = (int)($_POST['console_id'] ?? 0);
            $profiles = parse_profile_list($_POST['product_profile'] ?? ($_POST['product_profile[]'] ?? ''));
            $expires_in = validate_date_ymd($_POST['expires_at'] ?? '');
        $months = null; // legacy

            $team = trim((string)($_POST['team'] ?? ''));
            $emails_text = (string)($_POST['emails'] ?? '');

            if ($console_id <= 0) {
                echo json_encode(['ok' => false, 'error' => 'Select organization/console']);
                exit;
            }
            if (count($profiles) === 0) {
                echo json_encode(['ok' => false, 'error' => 'Select product profile']);
                exit;
            }
            if (!$expires_in) {
                echo json_encode(['ok' => false, 'error' => 'Select a valid expiry date']);
                exit;
            }
            $monthsBill = billing_months_from_expiry($expires_in);

            // Verify console assigned
            $stmt = $pdo->prepare("SELECT 1 FROM reseller_consoles WHERE reseller_id=? AND console_id=? LIMIT 1");
            $stmt->execute([$reseller_id, $console_id]);
            $assigned = (bool)$stmt->fetchColumn();
            
            if (!$assigned) {
                $stmt = $pdo->prepare("SELECT active_console_id FROM resellers WHERE id=?");
                $stmt->execute([$reseller_id]);
                $assigned = ((int)$stmt->fetchColumn() === $console_id);
            }
            
            if (!$assigned) {
                echo json_encode(['ok' => false, 'error' => 'Console not assigned to you']);
                exit;
            }

            // Validate requested profiles against reseller allow-list (if configured) and console profiles
            $console = get_console($pdo, $console_id);
            $allProfiles = [];
            if (!empty($console['profiles_json'])) {
                $arr = json_decode((string)$console['profiles_json'], true);
                if (is_array($arr)) {
                    foreach ($arr as $p) {
                        if (!is_array($p)) continue;
                        $gn = trim((string)($p['groupName'] ?? ''));
                        if ($gn !== '') $allProfiles[] = $gn;
                    }
                }
            }
            $allSet = array_fill_keys(array_values(array_unique($allProfiles)), true);
            $allowed = reseller_allowed_profile_groups($pdo, $reseller_id, $console_id);
            $allowedSet = $allowed ? array_fill_keys($allowed, true) : null;
            foreach ($profiles as $g) {
                if (!isset($allSet[$g])) {
                    echo json_encode(['ok' => false, 'error' => 'Invalid product profile selected']);
                    exit;
                }
                if (is_array($allowedSet) && !isset($allowedSet[$g])) {
                    echo json_encode(['ok' => false, 'error' => 'This product profile is not allowed for your account']);
                    exit;
                }
            }

            $profile_group = store_profile_list($profiles);

            // Get reseller rate
            $stmt = $pdo->prepare("SELECT monthly_rate FROM resellers WHERE id=?");
            $stmt->execute([$reseller_id]);
            $rate = (float)$stmt->fetchColumn();
            if ($rate <= 0) $rate = 1.0;

            // Validate emails
            $val = validate_emails($emails_text);
            $valid = array_values(array_unique($val['valid']));
            $invalid = $val['invalid'];

            if (count($valid) === 0) {
                echo json_encode(['ok' => false, 'error' => 'No valid emails found']);
                exit;
            }

            // Remove already-existing local users for this reseller
            $placeholders = implode(',', array_fill(0, count($valid), '?'));
            $params = array_merge([$reseller_id], $valid);
            $stmt = $pdo->prepare("SELECT email FROM users WHERE reseller_id=? AND email IN ($placeholders)");
            $stmt->execute($params);
            $existing = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
            $existing = array_map('strval', $existing);

            $to_add = array_values(array_diff($valid, $existing));
            if (count($to_add) === 0) {
                echo json_encode(['ok' => false, 'error' => 'All valid emails already exist in your users list']);
                exit;
            }

            $total_new = count($to_add);
            $cost_per_user = $rate * $monthsBill;
            $total_cost = $cost_per_user * $total_new;

            $pdo->beginTransaction();

            try {
                $stmt = $pdo->prepare("INSERT INTO bulk_jobs (reseller_id, console_id, product_profile, expires_at, months, team, remove_from_org, total_items, skipped_items, job_type, status) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'assign', 'running')");
                $stmt->execute([$reseller_id, $console_id, $profile_group, $expires_in, $monthsBill, $team, 0, $total_new, count($existing)]);
                $job_id = (int)$pdo->lastInsertId();

                $stmt = $pdo->prepare("INSERT INTO bulk_job_items (job_id, email, status) VALUES (?, ?, 'pending')");
                foreach ($to_add as $em) {
                    $stmt->execute([$job_id, $em]);
                }

                $pdo->commit();

                echo json_encode([
                    'ok' => true,
                    'job_id' => $job_id,
                    'total' => $total_new,
                    'skipped_existing' => count($existing),
                    'invalid' => $invalid,
                    'expires_at' => $expires_in,
                    'months_billed' => $monthsBill,
                    'rate' => $rate,
                    'cost_per_user' => $cost_per_user,
                    'total_cost' => $total_cost
                ]);
                exit;
            } catch (Throwable $e) {
                $pdo->rollBack();
                echo json_encode(['ok' => false, 'error' => 'Database error: ' . $e->getMessage()]);
                exit;
            }
        }

        // Reseller AJAX: Run bulk job (process next batch)
        if ($ajax === 'reseller_bulk_run' && !is_super_admin()) {
            csrf_verify();
            $reseller_id = (int)$_SESSION['user_id'];
            $job_id = (int)($_POST['job_id'] ?? 0);
            $batch = (int)($_POST['batch'] ?? 15);
            if ($batch < 1) $batch = 15;
            if ($batch > 50) $batch = 50;

            // Get job
            $stmt = $pdo->prepare("SELECT * FROM bulk_jobs WHERE id=? AND reseller_id=? LIMIT 1");
            $stmt->execute([$job_id, $reseller_id]);
            $job = $stmt->fetch();
            if (!$job) {
                echo json_encode(['ok' => false, 'error' => 'Job not found']);
                exit;
            }
            
            if ($job['status'] !== 'running') {
                echo json_encode([
                    'ok' => true,
                    'status' => $job['status'],
                    'done' => (int)$job['done_items'],
                    'failed' => (int)$job['failed_items'],
                    'skipped' => (int)($job['skipped_items'] ?? 0),
                    'total' => (int)$job['total_items']
                ]);
                exit;
            }

            $console_id = (int)$job['console_id'];
            $profile_group = (string)$job['product_profile'];
            $profiles = parse_profile_list($profile_group);
            if (count($profiles) === 0) {
                echo json_encode(['ok' => false, 'error' => 'Job has no product profile configured']);
                exit;
            }

            // Pick next pending items
            $stmt = $pdo->prepare("SELECT id, email FROM bulk_job_items WHERE job_id=? AND status='pending' ORDER BY id ASC LIMIT {$batch}");
            $stmt->execute([$job_id]);
            $items = $stmt->fetchAll();
            
            if (!$items || count($items) === 0) {
                // Done
                $stmt = $pdo->prepare("UPDATE bulk_jobs SET status='done' WHERE id=?");
                $stmt->execute([$job_id]);
                echo json_encode([
                    'ok' => true,
                    'status' => 'done',
                    'done' => (int)$job['done_items'],
                    'failed' => (int)$job['failed_items'],
                    'skipped' => (int)($job['skipped_items'] ?? 0),
                    'total' => (int)$job['total_items']
                ]);
                exit;
            }

            $console = get_console($pdo, $console_id);

            // Get reseller rate for charging
            $stmt = $pdo->prepare("SELECT monthly_rate FROM resellers WHERE id=?");
            $stmt->execute([$reseller_id]);
            $rate = (float)$stmt->fetchColumn();
            if ($rate <= 0) $rate = 1.0;

            $months = (int)($job['months'] ?? 1);
            if ($months < 1) $months = 1;
            if ($months > 24) $months = 24;
            $expiresJob = validate_date_ymd($job['expires_at'] ?? '') ?: date('Y-m-d', strtotime("+{$months} months"));
            $team = (string)($job['team'] ?? '');

            $commands = [];
            $mapEmailToItemId = [];
            foreach ($items as $it) {
                $email = (string)$it['email'];
                $mapEmailToItemId[$email] = (int)$it['id'];
                $commands[] = [
                    "user" => $email,
                    "requestID" => "bulk_" . $job_id . "_" . (int)$it['id'],
                    "do" => [
                        ["addAdobeID" => ["email" => $email, "option" => "ignoreIfAlreadyExists"]],
                        ["add" => ["group" => $profiles]]
                    ]
                ];
            }

            // Call UMAPI once for the batch
            $resp = umapi_action($console, $commands);
            // UMAPI may take > low wait_timeout; reconnect before DB writes
            $pdo = pdo_fresh();
            $ok_http = ($resp['code'] >= 200 && $resp['code'] < 300);

            // Build per-user result map
            $resultByEmail = [];
            if (is_array($resp['json'])) {
                foreach ($resp['json'] as $row) {
                    $u = (string)($row['user'] ?? '');
                    if ($u === '') continue;
                    $errors = $row['errors'] ?? null;
                    if (is_array($errors) && count($errors) > 0) {
                        $msg = (string)($errors[0]['message'] ?? 'UMAPI error');
                        $resultByEmail[$u] = ['ok' => false, 'msg' => $msg];
                    } else {
                        $resultByEmail[$u] = ['ok' => true, 'msg' => 'ok'];
                    }
                }
            }

            $done = 0;
            $failed = 0;
            $pdo->beginTransaction();

            try {
                foreach ($items as $it) {
                    $item_id = (int)$it['id'];
                    $email = (string)$it['email'];

                    $user_ok = $ok_http;
                    $user_msg = $ok_http ? 'ok' : ("HTTP " . $resp['code']);
                    
                    if (isset($resultByEmail[$email])) {
                        $user_ok = (bool)$resultByEmail[$email]['ok'];
                        $user_msg = (string)$resultByEmail[$email]['msg'];
                    } else if (!$ok_http) {
                        $user_ok = false;
                    }

                    if (!$user_ok) {
                        $stmt = $pdo->prepare("UPDATE bulk_job_items SET status='failed', message=? WHERE id=?");
                        $stmt->execute([$user_msg, $item_id]);
                        $failed++;
                        continue;
                    }

                    // Local DB insert + charge
                    $expires = $expiresJob;
                    $refund_until = date('Y-m-d H:i:s', time() + 24 * 3600);
                    $cost = $rate * $months;

                    // Store user locally
                    $stmt = $pdo->prepare("INSERT INTO users (reseller_id, console_id, email, organization, product_profile, expires_at, refund_eligible_until)
                                           VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$reseller_id, $console_id, $email, $team, $profile_group, $expires, $refund_until]);
                    $user_id = (int)$pdo->lastInsertId();

                    update_balance($pdo, $reseller_id, $cost, 'charge', "Bulk assign for {$email} (Expiry: {$expires})", $user_id);

                    $stmt = $pdo->prepare("UPDATE bulk_job_items SET status='done', user_id=? WHERE id=?");
                    $stmt->execute([$user_id, $item_id]);

                    $done++;
                }

                // Update job counters
                $stmt = $pdo->prepare("UPDATE bulk_jobs SET done_items = done_items + ?, failed_items = failed_items + ? WHERE id=?");
                $stmt->execute([$done, $failed, $job_id]);

                $pdo->commit();

                // Fresh stats
                $stmt = $pdo->prepare("SELECT total_items, done_items, failed_items, status FROM bulk_jobs WHERE id=?");
                $stmt->execute([$job_id]);
                $j2 = $stmt->fetch();
                $total = (int)($j2['total_items'] ?? 0);
                $done2 = (int)($j2['done_items'] ?? 0);
                $failed2 = (int)($j2['failed_items'] ?? 0);
                $status = (string)($j2['status'] ?? 'running');
                $remaining = max(0, $total - $done2 - $failed2);
                
                if ($remaining === 0 && $status === 'running') {
                    $stmt = $pdo->prepare("UPDATE bulk_jobs SET status='done' WHERE id=?");
                    $stmt->execute([$job_id]);
                    $status = 'done';
                }

                echo json_encode([
                    'ok' => true,
                    'status' => $status,
                    'total' => $total,
                    'done' => $done2,
                    'failed' => $failed2,
                    'skipped' => (int)($j2['skipped_items'] ?? 0),
                    'remaining' => $remaining,
                ]);
                exit;
            } catch (Throwable $e) {
                $pdo->rollBack();
                echo json_encode(['ok' => false, 'error' => 'Processing error: ' . $e->getMessage()]);
                exit;
            }
        }

        // Reseller AJAX: Get bulk job status
        if ($ajax === 'reseller_bulk_status' && !is_super_admin()) {
            csrf_verify();
            $reseller_id = (int)$_SESSION['user_id'];
            $job_id = (int)($_POST['job_id'] ?? 0);

            $stmt = $pdo->prepare("SELECT total_items, done_items, failed_items, status, error_message FROM bulk_jobs WHERE id=? AND reseller_id=?");
            $stmt->execute([$job_id, $reseller_id]);
            $j = $stmt->fetch();
            if (!$j) {
                echo json_encode(['ok' => false, 'error' => 'Job not found']);
                exit;
            }

            $total = (int)$j['total_items'];
            $done = (int)$j['done_items'];
            $failed = (int)$j['failed_items'];
            $remaining = max(0, $total - $done - $failed);

            echo json_encode([
                'ok' => true,
                'status' => (string)$j['status'],
                'total' => $total,
                'done' => $done,
                'failed' => $failed,
                'skipped' => (int)($j['skipped_items'] ?? 0),
                'remaining' => $remaining,
                'error' => (string)($j['error_message'] ?? '')
            ]);
            exit;
        }

        
        
        // Reseller AJAX: Initialize bulk delete job
        if ($ajax === 'reseller_bulk_delete_init' && !is_super_admin()) {
            csrf_verify();
            $reseller_id = (int)$_SESSION['user_id'];
            $console_id = (int)($_POST['console_id'] ?? 0);
            $profile_group = trim((string)($_POST['product_profile'] ?? ''));
            $remove_from_org = !empty($_POST['remove_from_org']) ? 1 : 0;
            $emails_text = (string)($_POST['emails'] ?? '');
            $delete_all = !empty($_POST['delete_all']) ? 1 : 0;

            if ($console_id <= 0) { echo json_encode(['ok'=>false,'error'=>'Select organization/console']); exit; }
            if ($profile_group === '') { echo json_encode(['ok'=>false,'error'=>'Select product profile']); exit; }

            // Verify console assigned
            $stmt = $pdo->prepare("SELECT 1 FROM reseller_consoles WHERE reseller_id=? AND console_id=? LIMIT 1");
            $stmt->execute([$reseller_id, $console_id]);
            $assigned = (bool)$stmt->fetchColumn();
            if (!$assigned) {
                $stmt = $pdo->prepare("SELECT active_console_id FROM resellers WHERE id=?");
                $stmt->execute([$reseller_id]);
                $assigned = ((int)$stmt->fetchColumn() === $console_id);
            }
            if (!$assigned) { echo json_encode(['ok'=>false,'error'=>'Console not assigned to you']); exit; }

            // Pick emails to delete from local DB (source of truth)
            $emails = [];
            if ($delete_all) {
                $stmt = $pdo->prepare("SELECT email FROM users WHERE reseller_id=? AND console_id=? AND product_profile=?");
                $stmt->execute([$reseller_id, $console_id, $profile_group]);
                $emails = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
            } else {
                $val = validate_emails($emails_text);
                $emails = array_values(array_unique($val['valid']));
                if (count($emails) === 0) { echo json_encode(['ok'=>false,'error'=>'No valid emails found']); exit; }
                // keep only emails that exist locally for this reseller/profile
                $place = implode(',', array_fill(0, count($emails), '?'));
                $params = array_merge([$reseller_id, $console_id, $profile_group], $emails);
                $stmt = $pdo->prepare("SELECT email FROM users WHERE reseller_id=? AND console_id=? AND product_profile=? AND email IN ($place)");
                $stmt->execute($params);
                $emails = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
            }

            $emails = array_map('strval', $emails);
            $emails = array_values(array_unique(array_filter($emails)));

            if (count($emails) === 0) { echo json_encode(['ok'=>false,'error'=>'No matching users found in your DB for selected profile']); exit; }

            $total = count($emails);

            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("INSERT INTO bulk_jobs (reseller_id, console_id, product_profile, remove_from_org, total_items, skipped_items, job_type, status)
                                       VALUES (?, ?, ?, ?, ?, ?, 'delete', 'running')");
                $stmt->execute([$reseller_id, $console_id, $profile_group, $remove_from_org, $total, 0]);
                $job_id = (int)$pdo->lastInsertId();

                $stmtIt = $pdo->prepare("INSERT INTO bulk_job_items (job_id, email, status) VALUES (?, ?, 'pending')");
                foreach ($emails as $em) { $stmtIt->execute([$job_id, $em]); }

                $pdo->commit();
                echo json_encode(['ok'=>true,'job_id'=>$job_id,'total'=>$total]);
                exit;
            } catch (Throwable $e) {
                $pdo->rollBack();
                echo json_encode(['ok'=>false,'error'=>'Database error: '.$e->getMessage()]);
                exit;
            }
        }

        // Reseller AJAX: Run bulk delete job
        if ($ajax === 'reseller_bulk_delete_run' && !is_super_admin()) {
            csrf_verify();
            $reseller_id = (int)$_SESSION['user_id'];
            $job_id = (int)($_POST['job_id'] ?? 0);
            $batch = (int)($_POST['batch'] ?? 15);
            if ($batch < 1) $batch = 15;
            if ($batch > 50) $batch = 50;

            $stmt = $pdo->prepare("SELECT * FROM bulk_jobs WHERE id=? AND reseller_id=? LIMIT 1");
            $stmt->execute([$job_id, $reseller_id]);
            $job = $stmt->fetch();
            if (!$job) { echo json_encode(['ok'=>false,'error'=>'Job not found']); exit; }
            if ((string)$job['job_type'] !== 'delete') { echo json_encode(['ok'=>false,'error'=>'Not a delete job']); exit; }

            if ($job['status'] !== 'running') {
                echo json_encode(['ok'=>true,'status'=>$job['status'],'done'=>(int)$job['done_items'],'failed'=>(int)$job['failed_items'],'skipped'=>(int)($job['skipped_items'] ?? 0),'total'=>(int)$job['total_items']]);
                exit;
            }

            $console_id = (int)$job['console_id'];
            $profile_group = (string)$job['product_profile'];
            $remove_from_org = (int)($job['remove_from_org'] ?? 0);

            $stmt = $pdo->prepare("SELECT id, email FROM bulk_job_items WHERE job_id=? AND status='pending' ORDER BY id ASC LIMIT {$batch}");
            $stmt->execute([$job_id]);
            $items = $stmt->fetchAll();
            if (!$items || count($items) === 0) {
                $stmt = $pdo->prepare("UPDATE bulk_jobs SET status='done' WHERE id=?");
                $stmt->execute([$job_id]);
                echo json_encode(['ok'=>true,'status'=>'done','done'=>(int)$job['done_items'],'failed'=>(int)$job['failed_items'],'skipped'=>(int)($job['skipped_items'] ?? 0),'total'=>(int)$job['total_items']]);
                exit;
            }

            $console = get_console($pdo, $console_id);

            $commands = [];
            foreach ($items as $it) {
                $email = (string)$it['email'];
                $do = [
                    ["remove" => ["group" => [$profile_group]]],
                ];
                if ($remove_from_org) $do[] = ["removeFromOrg" => new stdClass()];
                $commands[] = [
                    "user" => $email,
                    "requestID" => "bulkdel_" . $job_id . "_" . (int)$it['id'],
                    "do" => $do
                ];
            }

            $resp = umapi_action($console, $commands);
            // reconnect before DB writes (low wait_timeout)
            $pdo = pdo_fresh();

            $ok_http = ($resp['code'] >= 200 && $resp['code'] < 300);

            $resultByEmail = [];
            if (is_array($resp['json'])) {
                foreach ($resp['json'] as $row) {
                    $u = (string)($row['user'] ?? '');
                    if ($u === '') continue;
                    $errors = $row['errors'] ?? null;
                    if (is_array($errors) && count($errors) > 0) {
                        $msg = (string)($errors[0]['message'] ?? 'UMAPI error');
                        $resultByEmail[$u] = ['ok'=>false,'msg'=>$msg];
                    } else {
                        $resultByEmail[$u] = ['ok'=>true,'msg'=>'ok'];
                    }
                }
            }

            $done = 0; $failed = 0; $skipped = 0;

            $pdo->beginTransaction();
            try {
                foreach ($items as $it) {
                    $item_id = (int)$it['id'];
                    $email = (string)$it['email'];

                    $user_ok = $ok_http;
                    $user_msg = $ok_http ? 'ok' : ("HTTP " . $resp['code']);
                    if (isset($resultByEmail[$email])) {
                        $user_ok = (bool)$resultByEmail[$email]['ok'];
                        $user_msg = (string)$resultByEmail[$email]['msg'];
                    } else if (!$ok_http) {
                        $user_ok = false;
                    }

                    if (!$user_ok) {
                        // If user not found / already removed => treat as skipped
                        $low = strtolower($user_msg);
                        if (strpos($low, 'not found') !== false || strpos($low, 'does not exist') !== false) {
                            $stmt = $pdo->prepare("UPDATE bulk_job_items SET status='skipped', message=? WHERE id=?");
                            $stmt->execute([$user_msg, $item_id]);
                            $skipped++;
                            continue;
                        }
                        $stmt = $pdo->prepare("UPDATE bulk_job_items SET status='failed', message=? WHERE id=?");
                        $stmt->execute([$user_msg, $item_id]);
                        $failed++;
                        continue;
                    }

                    // Delete local user record
                    $stmt = $pdo->prepare("DELETE FROM users WHERE reseller_id=? AND console_id=? AND product_profile=? AND email=? LIMIT 1");
                    $stmt->execute([$reseller_id, $console_id, $profile_group, $email]);

                    $stmt = $pdo->prepare("UPDATE bulk_job_items SET status='done' WHERE id=?");
                    $stmt->execute([$item_id]);
                    $done++;
                }

                $stmt = $pdo->prepare("UPDATE bulk_jobs SET done_items = done_items + ?, failed_items = failed_items + ?, skipped_items = skipped_items + ? WHERE id=?");
                $stmt->execute([$done, $failed, $skipped, $job_id]);

                $pdo->commit();

                $stmt = $pdo->prepare("SELECT total_items, done_items, failed_items, skipped_items, status FROM bulk_jobs WHERE id=?");
                $stmt->execute([$job_id]);
                $j2 = $stmt->fetch();
                $total = (int)($j2['total_items'] ?? 0);
                $done2 = (int)($j2['done_items'] ?? 0);
                $failed2 = (int)($j2['failed_items'] ?? 0);
                $skipped2 = (int)($j2['skipped_items'] ?? 0);
                $status = (string)($j2['status'] ?? 'running');
                $remaining = max(0, $total - $done2 - $failed2 - $skipped2);

                if ($remaining === 0 && $status === 'running') {
                    $stmt = $pdo->prepare("UPDATE bulk_jobs SET status='done' WHERE id=?");
                    $stmt->execute([$job_id]);
                    $status = 'done';
                }

                echo json_encode(['ok'=>true,'status'=>$status,'total'=>$total,'done'=>$done2,'failed'=>$failed2,'skipped'=>$skipped2,'remaining'=>$remaining]);
                exit;

            } catch (Throwable $e) {
                $pdo->rollBack();
                echo json_encode(['ok'=>false,'error'=>'Processing error: '.$e->getMessage()]);
                exit;
            }
        }

        // Reseller AJAX: Download bulk job CSV (assign/delete)
        if ($ajax === 'reseller_bulk_csv' && !is_super_admin()) {
            csrf_verify();
            $reseller_id = (int)$_SESSION['user_id'];
            $job_id = (int)($_POST['job_id'] ?? 0);

            $stmt = $pdo->prepare("SELECT id FROM bulk_jobs WHERE id=? AND reseller_id=?");
            $stmt->execute([$job_id, $reseller_id]);
            if (!$stmt->fetchColumn()) { echo json_encode(['ok'=>false,'error'=>'Job not found']); exit; }

            // Return CSV as base64 (front-end will trigger download)
            $stmt = $pdo->prepare("SELECT email,status,message FROM bulk_job_items WHERE job_id=? ORDER BY id ASC");
            $stmt->execute([$job_id]);
            $rows = $stmt->fetchAll() ?: [];

            $fp = fopen('php://temp', 'r+');
            fputcsv($fp, ['email','status','message']);
            foreach ($rows as $r) { fputcsv($fp, [$r['email'],$r['status'],$r['message']]); }
            rewind($fp);
            $csv = stream_get_contents($fp);
            fclose($fp);

            echo json_encode(['ok'=>true,'filename'=>'bulk_job_'.$job_id.'.csv','data'=>base64_encode($csv)]);
            exit;
        }


        // Admin AJAX: Migration handlers (DB-driven; does NOT require access to old console)
        if ($ajax === 'migration_start' && is_super_admin()) {
            csrf_verify();

            $from = (int)($_POST['from_console_id'] ?? 0);
            $to   = (int)($_POST['to_console_id'] ?? 0);

            $targetProfile = trim((string)($_POST['target_profile'] ?? ''));
            $targetProfile = ($targetProfile !== '') ? $targetProfile : null;

            $removeFromSource = !empty($_POST['remove_from_source']) ? 1 : 0;

            $profileMapJson = isset($_POST['profile_map_json']) ? (string)$_POST['profile_map_json'] : null;
            $profileMapJson = ($profileMapJson !== null && trim($profileMapJson) !== '') ? $profileMapJson : null;

            if ($from <= 0 || $to <= 0 || $from === $to) {
                echo json_encode(['ok' => false, 'error' => 'Invalid source/target console']);
                exit;
            }
            if ($targetProfile === null) {
                echo json_encode(['ok' => false, 'error' => 'Select a default target product profile']);
                exit;
            }

            $fromConsole = get_console($pdo, $from);
            $toConsole   = get_console($pdo, $to);

            if (!$fromConsole || !$toConsole) {
                echo json_encode(['ok' => false, 'error' => 'Console not found']);
                exit;
            }
            if (!console_is_usable($toConsole)) {
                echo json_encode(['ok' => false, 'error' => 'Target console is not usable (inactive/expired/down).']);
                exit;
            }

            // Create job
            $createdBy = (string)($_SESSION['username'] ?? $_SESSION['email'] ?? 'admin');

            pdo_exec_retry($pdo, "INSERT INTO migration_jobs
                (from_console_id, to_console_id, total_users, processed_users, last_user_id, status, mapping_updated, error_message, created_by, target_profile, remove_from_source, profile_map_json)
                VALUES
                (:f,:t,0,0,0,'running',1,NULL,:cb,:tp,:rfs,:pmj)", [
                ':f' => $from,
                ':t' => $to,
                ':cb' => $createdBy,
                ':tp' => $targetProfile,
                ':rfs' => $removeFromSource,
                ':pmj' => $profileMapJson
            ]);
            $jobId = (int)$pdo->lastInsertId();

            // Decode mapping
            $map = [];
            if ($profileMapJson) {
                $tmp = json_decode($profileMapJson, true);
                if (is_array($tmp)) $map = $tmp;
            }

            // Snapshot users from DB (source of truth)
            $st = pdo_exec_retry($pdo, "SELECT id, reseller_id, email, product_profile FROM users WHERE console_id = :cid AND email <> ''", [':cid' => $from]);
            $users = $st->fetchAll();
            // Insert into migration_job_users in chunks
            $chunkSize = 200;

            // We'll do safer dynamic placeholder building (6 placeholders per row)
            $totalInserted = 0;
            $rows = [];
            foreach ($users as $u) {
                $srcP = (string)($u['product_profile'] ?? '');
                $tgtP = $targetProfile;
                if ($srcP !== '' && isset($map[$srcP]) && trim((string)$map[$srcP]) !== '') {
                    $tgtP = (string)$map[$srcP];
                }
                $rows[] = [
                    'user_id' => (int)$u['id'],
                    'reseller_id' => (int)$u['reseller_id'],
                    'email' => (string)$u['email'],
                    'source_profile' => $srcP,
                    'target_profile' => $tgtP
                ];
                if (count($rows) >= $chunkSize) {
                    $place = [];
                    $params = [];
                    foreach ($rows as $r) {
                        $place[] = "(?,?,?,?,?,?,'pending',NULL,NULL)";
                        $params[] = $jobId;
                        $params[] = $r['user_id'];
                        $params[] = $r['reseller_id'];
                        $params[] = $r['email'];
                        $params[] = $r['source_profile'];
                        $params[] = $r['target_profile'];
                    }
                    $sql = "INSERT IGNORE INTO migration_job_users (job_id, user_id, reseller_id, email, source_profile, target_profile, status, error_message, processed_at) VALUES " . implode(",", $place);
                    $stmtIns = $pdo->prepare($sql);
                    $stmtIns->execute($params);
                    $totalInserted += $stmtIns->rowCount();
                    $rows = [];
                }
            }
            if (count($rows) > 0) {
                $place = [];
                $params = [];
                foreach ($rows as $r) {
                    $place[] = "(?,?,?,?,?,?,'pending',NULL,NULL)";
                    $params[] = $jobId;
                    $params[] = $r['user_id'];
                    $params[] = $r['reseller_id'];
                    $params[] = $r['email'];
                    $params[] = $r['source_profile'];
                    $params[] = $r['target_profile'];
                }
                $sql = "INSERT IGNORE INTO migration_job_users (job_id, user_id, reseller_id, email, source_profile, target_profile, status, error_message, processed_at) VALUES " . implode(",", $place);
                $stmtIns = $pdo->prepare($sql);
                $stmtIns->execute($params);
                $totalInserted += $stmtIns->rowCount();
            }

            // Set totals based on snapshot (use COUNT(*) so it matches rows even if rowCount is unreliable)
            $stc = pdo_exec_retry($pdo, "SELECT COUNT(*) FROM migration_job_users WHERE job_id = :j", [':j' => $jobId]);
            $total = (int)$stc->fetchColumn();
            pdo_exec_retry($pdo, "UPDATE migration_jobs SET total_users = :t WHERE id = :j", [':t' => $total, ':j' => $jobId]);

            echo json_encode(['ok' => true, 'job_id' => $jobId, 'total_users' => $total]);
            exit;
        }

        if ($ajax === 'migration_step' && is_super_admin()) {
            csrf_verify();

            $jobId = (int)($_POST['job_id'] ?? 0);
            $limit = (int)($_POST['limit'] ?? 80);
    if ($limit <= 0) { $limit = 80; }
    if ($limit > 80) { $limit = 80; }
            if ($limit < 5) $limit = 5;
            // allow higher batch because we do 1 UMAPI call
            if ($limit > 150) $limit = 150;

            if ($jobId <= 0) {
                echo json_encode(['ok' => false, 'error' => 'Missing job_id']);
                exit;
            }

            // Fresh PDO (some hosts have low wait_timeout)
            $pdo = pdo_fresh();

            $job = pdo_exec_retry($pdo, "SELECT * FROM migration_jobs WHERE id = :j LIMIT 1", [':j' => $jobId])->fetch();
            if (!$job) {
                echo json_encode(['ok' => false, 'error' => 'Job not found']);
                exit;
            }

            $status = (string)$job['status'];
            if ($status !== 'running') {
                $processed = (int)pdo_exec_retry($pdo, "SELECT COUNT(*) FROM migration_job_users WHERE job_id=:j AND status IN ('done','failed')", [':j'=>$jobId])->fetchColumn();
                $total = (int)pdo_exec_retry($pdo, "SELECT total_users FROM migration_jobs WHERE id=:j", [':j'=>$jobId])->fetchColumn();
                $failed = (int)pdo_exec_retry($pdo, "SELECT COUNT(*) FROM migration_job_users WHERE job_id=:j AND status='failed'", [':j'=>$jobId])->fetchColumn();
                echo json_encode(['ok'=>true,'status'=>$status,'processed'=>$processed,'total'=>$total,'failed'=>$failed]);
                exit;
            }

            $fromId = (int)$job['from_console_id'];
            $toId   = (int)$job['to_console_id'];
            $defaultTargetProfile = (string)$job['target_profile'];
            $removeFromSource = (int)($job['remove_from_source'] ?? 0);

            $fromConsole = get_console($pdo, $fromId);
            $toConsole   = get_console($pdo, $toId);
            if (!$toConsole || !console_is_usable($toConsole)) {
                pdo_exec_retry($pdo, "UPDATE migration_jobs SET status='failed', error_message='Target console not usable' WHERE id=:j", [':j'=>$jobId]);
                echo json_encode(['ok'=>false,'error'=>'Target console not usable']);
                exit;
            }

            // Pull next batch
            $rows = pdo_exec_retry($pdo,
                "SELECT job_id, user_id, reseller_id, email, source_profile, target_profile
                 FROM migration_job_users
                 WHERE job_id = :j AND status = 'pending'
                 ORDER BY user_id ASC
                 LIMIT {$limit}",
                [':j' => $jobId]
            )->fetchAll();

            if (!$rows) {
                // nothing pending -> mark done
                pdo_exec_retry($pdo, "UPDATE migration_jobs SET status='done' WHERE id=:j", [':j'=>$jobId]);
                echo json_encode(['ok'=>true,'status'=>'done','processed'=>(int)$job['processed_users'],'total'=>(int)$job['total_users'],'failed'=>0]);
                exit;
            }

            $warnings = [];
            $commands = [];
            $reqToUser = []; // requestID => [user_id,email,profile]

            foreach ($rows as $r) {
                $userId = (int)$r['user_id'];
                $email = trim((string)$r['email']);
                $tgtProfile = trim((string)($r['target_profile'] ?? ''));
                if ($tgtProfile === '') $tgtProfile = $defaultTargetProfile;

                if ($email === '' || $tgtProfile === '') {
                    // mark failed (no UMAPI call)
                    pdo_exec_retry($pdo,
                        "UPDATE migration_job_users SET status='failed', error_message=:m, processed_at=NOW() WHERE job_id=:j AND user_id=:u",
                        [':m'=>'Missing email or target profile', ':j'=>$jobId, ':u'=>$userId]
                    );
                    continue;
                }

                $rid = "mig_{$jobId}_{$userId}";
                $commands[] = [
                    "user" => $email,
                    "requestID" => $rid,
                    "do" => [
                        ["addAdobeID" => ["email" => $email, "option" => "ignoreIfAlreadyExists"]],
                        ["add" => ["group" => [$tgtProfile]]]
                    ]
                ];
                $reqToUser[$rid] = ['user_id'=>$userId,'email'=>$email,'profile'=>$tgtProfile];
            }

            // If all rows invalid (rare)
            if (!$commands) {
                $processed = (int)pdo_exec_retry($pdo, "SELECT COUNT(*) FROM migration_job_users WHERE job_id=:j AND status IN ('done','failed')", [':j'=>$jobId])->fetchColumn();
                $failed = (int)pdo_exec_retry($pdo, "SELECT COUNT(*) FROM migration_job_users WHERE job_id=:j AND status='failed'", [':j'=>$jobId])->fetchColumn();
                $total = (int)pdo_exec_retry($pdo, "SELECT total_users FROM migration_jobs WHERE id=:j", [':j'=>$jobId])->fetchColumn();
                echo json_encode(['ok'=>true,'status'=>'running','processed'=>$processed,'total'=>$total,'failed'=>$failed,'warnings'=>$warnings]);
                exit;
            }

            // --- UMAPI call (safe: auto-splits on malformed JSON 400) ---
            $safe = umapi_action_safe($toConsole, $commands);
            if (!empty($safe['warnings'])) {
                foreach ($safe['warnings'] as $w) $warnings[] = $w;
            }

            // Reconnect before DB writes (UMAPI can take time)
            $pdo = pdo_fresh();

            $resultMap = $safe['resultMap'] ?? [];

            $successReqIds = [];
            $successEmails = [];

            // Mark each user based on request-level result. If missing, treat as failed.
            foreach ($reqToUser as $rid => $info) {
                $r = $resultMap[$rid] ?? ['ok' => false, 'msg' => 'No UMAPI result for request'];

                if (!$r['ok']) {
                    $msg = (string)($r['msg'] ?? 'UMAPI failed');
                    pdo_exec_retry($pdo,
                        "UPDATE migration_job_users SET status='failed', error_message=:m, processed_at=NOW() WHERE job_id=:j AND user_id=:u",
                        [':m'=>$msg, ':j'=>$jobId, ':u'=>(int)$info['user_id']]
                    );
                    continue;
                }

                // success
                $successReqIds[] = $rid;
                $successEmails[] = $info['email'];

                pdo_exec_retry($pdo,
                    "UPDATE migration_job_users SET status='done', error_message=NULL, processed_at=NOW() WHERE job_id=:j AND user_id=:u",
                    [':j'=>$jobId, ':u'=>(int)$info['user_id']]
                );

                // If remove_from_source is enabled, remove user from source org in a separate request later
                // (Your existing logic below already handles remove if present.)
            }

            // Update local DB users table for the successful ones
            if ($successReqIds) {
                foreach ($successReqIds as $rid) {
                    $info = $reqToUser[$rid] ?? null;
                    if (!$info) continue;
                    $uId = (int)$info['user_id'];
                    $profile = (string)$info['profile'];
                    pdo_exec_retry($pdo,
                        "UPDATE users SET console_id=:to, product_profile=:pp, updated_at=NOW() WHERE id=:uid",
                        [':to'=>$toId, ':pp'=>$profile, ':uid'=>$uId]
                    );
                }
            }

            // Optional cleanup: batch removeFromOrg (best-effort, ONE call)
            if ($removeFromSource && $fromConsole && $successEmails) {
                try {
                    $rmCmds = [];
                    foreach ($successEmails as $i => $em) {
                        $rmCmds[] = [
                            "user" => $em,
                            "requestID" => "mig_rm_{$jobId}_{$i}",
                            "do" => [
                                ["removeFromOrg" => new stdClass()]
                            ]
                        ];
                    }
                    $rmResp = umapi_action($fromConsole, $rmCmds);
                    if (!($rmResp['code'] >= 200 && $rmResp['code'] < 300)) {
                        $warnings[] = "Cleanup batch failed (HTTP {$rmResp['code']})";
                    }
                } catch (Throwable $e) {
                    $warnings[] = "Cleanup batch error: " . $e->getMessage();
                }
                $pdo = pdo_fresh();
            }

            // Update job counters from DB (robust)
            $processed = (int)pdo_exec_retry($pdo, "SELECT COUNT(*) FROM migration_job_users WHERE job_id=:j AND status IN ('done','failed')", [':j'=>$jobId])->fetchColumn();
            $failed = (int)pdo_exec_retry($pdo, "SELECT COUNT(*) FROM migration_job_users WHERE job_id=:j AND status='failed'", [':j'=>$jobId])->fetchColumn();
            $total = (int)pdo_exec_retry($pdo, "SELECT total_users FROM migration_jobs WHERE id=:j", [':j'=>$jobId])->fetchColumn();

            pdo_exec_retry($pdo, "UPDATE migration_jobs SET processed_users=:p WHERE id=:j", [':p'=>$processed, ':j'=>$jobId]);

            $pending = (int)pdo_exec_retry($pdo, "SELECT COUNT(*) FROM migration_job_users WHERE job_id=:j AND status='pending'", [':j'=>$jobId])->fetchColumn();
            $status = ($pending === 0) ? 'done' : 'running';
            if ($status === 'done') {
                pdo_exec_retry($pdo, "UPDATE migration_jobs SET status='done' WHERE id=:j", [':j'=>$jobId]);
            }

            echo json_encode([
                'ok' => true,
                'status' => $status,
                'processed' => $processed,
                'total' => $total,
                'failed' => $failed,
                'warnings' => $warnings
            ]);
            exit;
        }

if ($ajax === 'migration_status' && is_super_admin()) {
            $jobId = (int)($_GET['job_id'] ?? 0);
            if ($jobId <= 0) {
                echo json_encode(['ok'=>false,'error'=>'Missing job_id']);
                exit;
            }
            $job = pdo_exec_retry($pdo, "SELECT * FROM migration_jobs WHERE id=:j LIMIT 1", [':j'=>$jobId])->fetch();
            if (!$job) {
                echo json_encode(['ok'=>false,'error'=>'Job not found']);
                exit;
            }
            $processed = (int)pdo_exec_retry($pdo, "SELECT COUNT(*) FROM migration_job_users WHERE job_id=:j AND status IN ('done','failed')", [':j'=>$jobId])->fetchColumn();
            $failed = (int)pdo_exec_retry($pdo, "SELECT COUNT(*) FROM migration_job_users WHERE job_id=:j AND status='failed'", [':j'=>$jobId])->fetchColumn();
            echo json_encode([
                'ok'=>true,
                'status'=>(string)$job['status'],
                'processed'=>$processed,
                'total'=>(int)$job['total_users'],
                'failed'=>$failed,
                'error'=>(string)($job['error_message'] ?? '')
            ]);
            exit;
        }


        if ($ajax === 'console_profiles' && is_super_admin()) {
            $consoleId = (int)($_GET['console_id'] ?? 0);
            $console = get_console($pdo, $consoleId);
            $profiles = [];
            if (!empty($console['profiles_json'])) {
                $profiles = json_decode((string)$console['profiles_json'], true);
                if (!is_array($profiles)) $profiles = [];
            }
            echo json_encode(['ok' => true, 'profiles' => $profiles]);
            exit;
        }

        if ($ajax === 'migration_cancel' && is_super_admin()) {
            csrf_verify();
            $jobId = (int)($_POST['job_id'] ?? 0);
            if ($jobId <= 0) {
                echo json_encode(['ok'=>false,'error'=>'Missing job_id']);
                exit;
            }
            $pdo = pdo_fresh();
            pdo_exec_retry($pdo, "UPDATE migration_jobs SET status='canceled' WHERE id=:j", [':j'=>$jobId]);
            $processed = (int)pdo_exec_retry($pdo, "SELECT COUNT(*) FROM migration_job_users WHERE job_id=:j AND status IN ('done','failed')", [':j'=>$jobId])->fetchColumn();
            $total = (int)pdo_exec_retry($pdo, "SELECT total_users FROM migration_jobs WHERE id=:j", [':j'=>$jobId])->fetchColumn();
            echo json_encode(['ok'=>true,'status'=>'canceled','processed'=>$processed,'total'=>$total]);
            exit;
        }

        
        if ($ajax === 'migration_history' && is_super_admin()) {
            // Return last 50 migration jobs with computed progress
            $rows = pdo_exec_retry($pdo, "
                SELECT 
                    mj.id,
                    mj.from_console_id,
                    mj.to_console_id,
                    mj.status,
                    mj.total_users,
                    mj.created_at,
                    mj.updated_at,
                    ac1.name AS from_name,
                    ac2.name AS to_name,
                    (SELECT COUNT(*) FROM migration_job_users mju WHERE mju.job_id = mj.id AND mju.status IN ('done','failed')) AS processed,
                    (SELECT COUNT(*) FROM migration_job_users mju WHERE mju.job_id = mj.id AND mju.status = 'failed') AS failed
                FROM migration_jobs mj
                LEFT JOIN admin_consoles ac1 ON ac1.id = mj.from_console_id
                LEFT JOIN admin_consoles ac2 ON ac2.id = mj.to_console_id
                ORDER BY mj.id DESC
                LIMIT 50
            ")->fetchAll();

            echo json_encode(['ok'=>true,'rows'=>$rows]);
            exit;
        }

        if ($ajax === 'migration_csv' && is_super_admin()) {
            $jobId = (int)($_GET['job_id'] ?? 0);
            if ($jobId <= 0) {
                header('Content-Type: text/plain; charset=utf-8');
                echo "Missing job_id";
                exit;
            }

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="migration_job_'.$jobId.'.csv"');

            $out = fopen('php://output', 'w');
            fputcsv($out, ['email','status','error_message','source_profile','target_profile','processed_at']);

            $stmt = pdo_exec_retry($pdo, "SELECT email,status,error_message,source_profile,target_profile,processed_at FROM migration_job_users WHERE job_id=:j ORDER BY email ASC", [':j'=>$jobId]);
            while ($r = $stmt->fetch()) {
                fputcsv($out, [
                    (string)$r['email'],
                    (string)$r['status'],
                    (string)($r['error_message'] ?? ''),
                    (string)($r['source_profile'] ?? ''),
                    (string)($r['target_profile'] ?? ''),
                    (string)($r['processed_at'] ?? '')
                ]);
            }
            fclose($out);
            exit;
        }
echo json_encode(['ok' => false, 'error' => 'Unknown ajax action']);
        exit;

    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

/* ==========================
   ACTION HANDLERS
========================== */
try {
    // Setup Wizard
    if (!$has_super && $action === 'setup_create_super') {
        csrf_verify();
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $pass = (string)($_POST['password'] ?? '');

        if ($username === '' || $email === '' || $pass === '') throw new RuntimeException("All fields are required.");
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException("Invalid email.");
        if (strlen($pass) < 8) throw new RuntimeException("Password must be at least 8 characters.");

        $chk = $pdo->prepare("SELECT COUNT(*) FROM resellers WHERE username=? OR email=?");
        $chk->execute([$username, $email]);
        if ((int)$chk->fetchColumn() > 0) throw new RuntimeException("Username or email already exists.");

        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO resellers (role, username, password, email, company_name, monthly_rate, balance, status)
                               VALUES ('super_admin', ?, ?, ?, 'Super Admin', 0, 0, 'active')");
        $stmt->execute([$username, $hash, $email]);

        log_action($pdo, "SETUP", "Created Super Admin", $username, (int)$pdo->lastInsertId());
        header("Location: ?page=login&setup=done"); exit;
    }

    // Login
    if ($action === 'login') {
        csrf_verify();
        $username = trim($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        $stmt = $pdo->prepare("SELECT * FROM resellers WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $u = $stmt->fetch();

        if (!$u || !password_verify($password, $u['password'])) {
            throw new RuntimeException("Invalid username or password.");
        }

        // Check if reseller is suspended
        if (($u['status'] ?? 'active') === 'suspended' && $u['role'] === 'reseller') {
            throw new RuntimeException("Your account has been suspended. Please contact Super Admin for assistance.");
        }

        $_SESSION['user_id'] = (int)$u['id'];
        $_SESSION['username'] = (string)$u['username'];
        $_SESSION['role'] = (string)$u['role'];
        $_SESSION['is_super_admin'] = ($u['role'] === 'super_admin');
        $_SESSION['monthly_rate'] = (float)$u['monthly_rate'];
        $_SESSION['status'] = (string)($u['status'] ?? 'active');

        log_action($pdo, "LOGIN", "User logged in", (string)$u['username'], (int)$u['id']);
        header("Location: ?page=" . (is_super_admin() ? "admin_dashboard" : "reseller_dashboard")); exit;
    }

    // Logout
    if ($action === 'logout') {
        csrf_verify();
        if (is_logged_in()) log_action($pdo, "LOGOUT", "User logged out", $_SESSION['username'] ?? null, $_SESSION['user_id'] ?? null);
        $_SESSION = [];
        session_destroy();
        header("Location: ?page=login"); exit;
    }

    // Admin: Add Console
    if ($action === 'admin_add_console' && is_super_admin()) {
        csrf_verify();
        $name = trim($_POST['name'] ?? '');
        if ($name === '') throw new RuntimeException("Console name required.");

        $stmt = $pdo->prepare("INSERT INTO admin_consoles
            (name, client_id, client_secret, technical_account_id, organization_id, scopes, ims_host, umapi_host, status, is_backup)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $name,
            trim($_POST['client_id'] ?? ''),
            trim($_POST['client_secret'] ?? ''),
            trim($_POST['technical_account_id'] ?? ''),
            trim($_POST['organization_id'] ?? ''),
            trim($_POST['scopes'] ?? 'openid AdobeID user_management_sdk'),
            trim($_POST['ims_host'] ?? 'ims-na1.adobelogin.com'),
            trim($_POST['umapi_host'] ?? 'usermanagement.adobe.io'),
            $_POST['status'] ?? 'active',
            !empty($_POST['is_backup']) ? 1 : 0
        ]);

        $console_id = (int)$pdo->lastInsertId();
        log_action($pdo, "ADD_CONSOLE", "Added console: {$name}", $_SESSION['username'], null, $console_id);
        $flash_success = "Console added. Now click Test / Fetch Profiles.";
    }

    // Admin: Test Console (token test)
    if ($action === 'admin_test_console' && is_super_admin()) {
        csrf_verify();
        $console_id = (int)($_POST['console_id'] ?? 0);
        $c = get_console($pdo, $console_id);

        try {
            $token = adobe_get_access_token($c);
            $msg = "OK: Token received. Console is connected.";
            $pdo->prepare("UPDATE admin_consoles SET last_test_at=NOW(), last_test_ok=1, last_test_message=? WHERE id=?")
                ->execute([$msg, $console_id]);
            log_action($pdo, "TEST_CONSOLE", "Console test OK (#{$console_id})", $_SESSION['username'], null, $console_id);
            $flash_success = $msg;
        } catch (Throwable $e) {
            $msg = "FAILED: " . $e->getMessage();
            $pdo->prepare("UPDATE admin_consoles SET last_test_at=NOW(), last_test_ok=0, last_test_message=? WHERE id=?")
                ->execute([$msg, $console_id]);
            log_action($pdo, "TEST_CONSOLE", "Console test FAILED (#{$console_id})", $_SESSION['username'], null, $console_id, 'failed', $e->getMessage());
            throw new RuntimeException($msg);
        }
    }

    // Admin: Fetch Profiles (UMAPI groups)
    if ($action === 'admin_fetch_profiles' && is_super_admin()) {
        csrf_verify();
        $console_id = (int)($_POST['console_id'] ?? 0);
        $c = get_console($pdo, $console_id);

        $groups = umapi_get_groups($c);
        $json = json_encode($groups, JSON_UNESCAPED_SLASHES);
        $pdo->prepare("UPDATE admin_consoles SET profiles_json=?, profiles_fetched_at=NOW() WHERE id=?")
            ->execute([$json, $console_id]);

        log_action($pdo, "FETCH_PROFILES", "Fetched " . count($groups) . " profiles", $_SESSION['username'], null, $console_id);
        $flash_success = "Fetched " . count($groups) . " product profiles and saved.";
    }

    // Admin: Add Reseller
    if ($action === 'admin_add_reseller' && is_super_admin()) {
        csrf_verify();
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $pass = (string)($_POST['password'] ?? '');
        $company = trim($_POST['company_name'] ?? '');
        $rate = (float)($_POST['monthly_rate'] ?? 1.00);

        if ($username === '' || $email === '' || $pass === '') throw new RuntimeException("Username/email/password required.");
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException("Invalid email.");
        if (strlen($pass) < 6) throw new RuntimeException("Password must be at least 6 characters.");

        $chk = $pdo->prepare("SELECT COUNT(*) FROM resellers WHERE username=? OR email=?");
        $chk->execute([$username, $email]);
        if ((int)$chk->fetchColumn() > 0) throw new RuntimeException("Username or email already exists.");

        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO resellers (role, username, password, email, company_name, monthly_rate, balance, status)
                               VALUES ('reseller', ?, ?, ?, ?, ?, 0, 'active')");
        $stmt->execute([$username, $hash, $email, $company, $rate]);

        log_action($pdo, "ADD_RESELLER", "Added reseller: {$username}", $_SESSION['username'], (int)$pdo->lastInsertId());
        $flash_success = "Reseller added.";
    }

    // Admin: Assign consoles to reseller
    if ($action === 'admin_assign_consoles' && is_super_admin()) {
        csrf_verify();
        $reseller_id = (int)($_POST['reseller_id'] ?? 0);

        $c1 = ($_POST['console_1'] ?? ($_POST['active_console_id'] ?? '')) !== '' ? (int)($_POST['console_1'] ?? $_POST['active_console_id']) : 0;
        $c2 = ($_POST['console_2'] ?? ($_POST['backup_console_id'] ?? '')) !== '' ? (int)($_POST['console_2'] ?? $_POST['backup_console_id']) : 0;
        $c3 = ($_POST['console_3'] ?? '') !== '' ? (int)$_POST['console_3'] : 0;
        $c4 = ($_POST['console_4'] ?? '') !== '' ? (int)$_POST['console_4'] : 0;
        $c5 = ($_POST['console_5'] ?? '') !== '' ? (int)$_POST['console_5'] : 0;

        $ordered = array_values(array_filter([$c1, $c2, $c3, $c4, $c5]));
        set_reseller_consoles($pdo, $reseller_id, $ordered);

        log_action($pdo, "ASSIGN_CONSOLES", "Assigned consoles (priority) to reseller #{$reseller_id}", $_SESSION['username'], $reseller_id, ($ordered[0] ?? null));
        $flash_success = "Consoles saved (priority order).";
    }

    // Admin: Set allowed product profiles for a reseller per console (optional allow-list)
    if ($action === 'admin_set_console_profiles' && is_super_admin()) {
        csrf_verify();
        $reseller_id = (int)($_POST['reseller_id'] ?? 0);
        $console_id  = (int)($_POST['console_id'] ?? 0);
        $profiles = parse_profile_list($_POST['profiles'] ?? ($_POST['profiles[]'] ?? []));

        if ($reseller_id <= 0 || $console_id <= 0) throw new RuntimeException("Invalid reseller/console.");

        // Validate selected profiles belong to this console
        $console = get_console($pdo, $console_id);
        $all = [];
        if (!empty($console['profiles_json'])) {
            $arr = json_decode((string)$console['profiles_json'], true);
            if (is_array($arr)) {
                foreach ($arr as $p) {
                    if (!is_array($p)) continue;
                    $gn = trim((string)($p['groupName'] ?? ''));
                    if ($gn !== '') $all[] = $gn;
                }
            }
        }
        $allSet = array_fill_keys(array_values(array_unique($all)), true);
        foreach ($profiles as $g) {
            if (!isset($allSet[$g])) throw new RuntimeException("Invalid profile selected for this console.");
        }

        // Save: if empty => delete rows (means ALL allowed)
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare("DELETE FROM reseller_console_profiles WHERE reseller_id=? AND console_id=?");
            $st->execute([$reseller_id, $console_id]);

            if (count($profiles) > 0) {
                $ins = $pdo->prepare("INSERT INTO reseller_console_profiles (reseller_id, console_id, group_name) VALUES (?,?,?)");
                foreach ($profiles as $g) {
                    $ins->execute([$reseller_id, $console_id, $g]);
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $msg = (count($profiles) > 0) ? ("Set " . count($profiles) . " allowed profile(s)") : "Cleared allow-list (ALL profiles allowed)";
        log_action($pdo, "SET_RESELLER_PROFILES", $msg . " for reseller #{$reseller_id} console #{$console_id}", $_SESSION['username'], $reseller_id, $console_id);
        $flash_success = "Product profiles saved for this reseller.";
    }

    // Admin: Create Announcement
    if ($action === 'admin_create_announcement' && is_super_admin()) {
        csrf_verify();
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        if ($title === '' || $content === '') throw new RuntimeException("Title and content required.");

        $type = $_POST['type'] ?? 'info';
        $target = $_POST['target'] ?? 'all';
        $expires = ($_POST['expires_at'] ?? '') !== '' ? $_POST['expires_at'] : null;

        $stmt = $pdo->prepare("INSERT INTO announcements (title, content, type, target, expires_at, created_by)
                               VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $content, $type, $target, $expires, (int)$_SESSION['user_id']]);

        log_action($pdo, "CREATE_ANNOUNCEMENT", "Created announcement: {$title}", $_SESSION['username']);
        $flash_success = "Announcement posted.";
    }


    // Admin: Delete Announcement (soft delete)
    if ($action === 'admin_delete_announcement' && is_super_admin()) {
        csrf_verify();
        $id = (int)($_POST['announcement_id'] ?? 0);
        if ($id <= 0) throw new RuntimeException("Invalid announcement id.");

        $stmt = $pdo->prepare("UPDATE announcements SET is_active=0 WHERE id=? LIMIT 1");
        $stmt->execute([$id]);

        log_action($pdo, "DELETE_ANNOUNCEMENT", "Soft deleted announcement #{$id}", $_SESSION['username']);
        $flash_success = "Announcement deleted.";
    }

    /**
     * RESELLER: Assign user (REAL UMAPI)
     */
    if ($action === 'reseller_assign_user' && is_logged_in() && !is_super_admin()) {
        csrf_verify();
        $reseller_id = (int)$_SESSION['user_id'];

        // Check if reseller is suspended
        $stmt = $pdo->prepare("SELECT status FROM resellers WHERE id=?");
        $stmt->execute([$reseller_id]);
        $status = (string)($stmt->fetchColumn() ?: 'active');
        
        if ($status === 'suspended') {
            throw new RuntimeException("Your account is suspended. Cannot assign users.");
        }

        $email = trim($_POST['email'] ?? '');
        $expires_in = validate_date_ymd($_POST['expires_at'] ?? '');
        $months = null; // legacy

        $profiles = parse_profile_list($_POST['product_profile'] ?? ($_POST['product_profile[]'] ?? []));
        $org_label = trim($_POST['organization'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException("Invalid email.");
        if (!$expires_in) throw new RuntimeException("Select a valid expiry date.");
        $monthsBill = billing_months_from_expiry($expires_in);

        if (count($profiles) === 0) throw new RuntimeException("Select at least one Product Profile.");

        $console_id = (int)($_POST['console_id'] ?? 0);

        $stmt = $pdo->prepare("SELECT active_console_id, monthly_rate FROM resellers WHERE id=?");
        $stmt->execute([$reseller_id]);
        $r = $stmt->fetch();
        $rate = (float)($r['monthly_rate'] ?? 1.0);

        if ($console_id <= 0) {
            // fallback to reseller's active console (legacy)
            $console_id = (int)($r['active_console_id'] ?? 0);
        }
        if ($console_id <= 0) throw new RuntimeException("No console selected/assigned to this reseller. Ask admin to assign console.");

        // Verify console assigned to reseller (new multi-console table or legacy active_console_id)
        $stmt = $pdo->prepare("SELECT 1 FROM reseller_consoles WHERE reseller_id=? AND console_id=? LIMIT 1");
        $stmt->execute([$reseller_id, $console_id]);
        $assigned = (bool)$stmt->fetchColumn();
        if (!$assigned) {
            $assigned = ((int)($r['active_console_id'] ?? 0) === $console_id);
        }
        if (!$assigned) throw new RuntimeException("Selected console is not assigned to you.");

        // Validate requested profiles against reseller allow-list (if configured) and console profiles
        $console = get_console($pdo, $console_id);
        $allProfiles = [];
        if (!empty($console['profiles_json'])) {
            $arr = json_decode((string)$console['profiles_json'], true);
            if (is_array($arr)) {
                foreach ($arr as $p) {
                    if (!is_array($p)) continue;
                    $gn = trim((string)($p['groupName'] ?? ''));
                    if ($gn !== '') $allProfiles[] = $gn;
                }
            }
        }
        $allSet = array_fill_keys(array_values(array_unique($allProfiles)), true);
        $allowed = reseller_allowed_profile_groups($pdo, $reseller_id, $console_id);
        $allowedSet = $allowed ? array_fill_keys($allowed, true) : null;
        foreach ($profiles as $g) {
            if (!isset($allSet[$g])) throw new RuntimeException("Invalid product profile selected.");
            if (is_array($allowedSet) && !isset($allowedSet[$g])) throw new RuntimeException("This product profile is not allowed for your account.");
        }

        $profile_group = store_profile_list($profiles);
$stmt = $pdo->prepare("SELECT id FROM users WHERE reseller_id=? AND email=?");
        $stmt->execute([$reseller_id, $email]);
        if ($stmt->fetch()) throw new RuntimeException("This user already exists.");

        $rate = (float)($r['monthly_rate'] ?? 1.0);
        $expires = $expires_in;
        $cost = $rate * $monthsBill;
        $refund_until = date('Y-m-d H:i:s', time() + 24 * 3600);

        $commands = [[
            "user" => $email,
            "requestID" => "assign_" . time(),
            "do" => [
                [
                    "addAdobeID" => [
                        "email" => $email,
                        "option" => "ignoreIfAlreadyExists"
                    ]
                ],
                [
                    "add" => [
                        "group" => $profiles
                    ]
                ]
            ]
        ]];

        $pdo->beginTransaction();

        $result = umapi_action($console, $commands);
        if ($result['code'] < 200 || $result['code'] >= 300) {
            $pdo->rollBack();
            throw new RuntimeException("Adobe UMAPI assign failed (HTTP {$result['code']}): " . $result['raw']);
        }

        $stmt = $pdo->prepare("INSERT INTO users (reseller_id, console_id, email, organization, product_profile, expires_at, refund_eligible_until)
                               VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$reseller_id, $console_id, $email, $org_label, $profile_group, $expires, $refund_until]);
        $user_id = (int)$pdo->lastInsertId();

        update_balance($pdo, $reseller_id, $cost, 'charge', "Assignment for {$email} (Expiry: {$expires})", $user_id);

        $pdo->commit();

        log_action($pdo, "ASSIGN_USER", "Assigned {$email} ({$profile_group})", $_SESSION['username'], $reseller_id, $console_id);
        $flash_success = "User assigned in Adobe + saved locally. Cost: $" . money_fmt($cost);
    }

    /**
     * RESELLER: Delete user (REAL UMAPI remove)
     */
    if ($action === 'reseller_delete_user' && is_logged_in() && !is_super_admin()) {
        csrf_verify();
        $reseller_id = (int)$_SESSION['user_id'];
        
        // Check if reseller is suspended
        $stmt = $pdo->prepare("SELECT status FROM resellers WHERE id=?");
        $stmt->execute([$reseller_id]);
        $status = (string)($stmt->fetchColumn() ?: 'active');
        
        if ($status === 'suspended') {
            throw new RuntimeException("Your account is suspended. Cannot delete users.");
        }
        
        $user_id = (int)($_POST['user_id'] ?? 0);
        $remove_from_org = !empty($_POST['remove_from_org']) ? 1 : 0;

        $stmt = $pdo->prepare("SELECT * FROM users WHERE id=? AND reseller_id=?");
        $stmt->execute([$user_id, $reseller_id]);
        $u = $stmt->fetch();
        if (!$u) throw new RuntimeException("User not found.");

        $console_id = (int)($u['console_id'] ?? 0);
        if ($console_id <= 0) throw new RuntimeException("User has no console_id stored.");

        $console = get_console($pdo, $console_id);
        $email = (string)$u['email'];
        $group = (string)$u['product_profile'];
        $groups = parse_profile_list($group);

        $do = [];
        if (count($groups) > 0) {
            $do[] = ["remove" => ["group" => $groups]];
        }
        if ($remove_from_org) {
            $do[] = ["removeFromOrg" => new stdClass()];
        }

        $commands = [[
            "user" => $email,
            "requestID" => "delete_" . time(),
            "do" => $do
        ]];

        $result = umapi_action($console, $commands);
        if ($result['code'] < 200 || $result['code'] >= 300) {
            throw new RuntimeException("Adobe UMAPI delete failed (HTTP {$result['code']}): " . $result['raw']);
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id=? AND reseller_id=?");
            $stmt->execute([$user_id, $reseller_id]);

            // Refund (only within refund window, and only once)
            $refund_note = '';
            $eligible_until = (string)($u['refund_eligible_until'] ?? '');
            if ($eligible_until !== '' && strtotime($eligible_until) !== false && strtotime($eligible_until) >= time()) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE reseller_id=? AND user_id=? AND type='refund'");
                $stmt->execute([$reseller_id, $user_id]);
                $already_refunded = (int)$stmt->fetchColumn();

                if ($already_refunded === 0) {
                    $stmt = $pdo->prepare("SELECT amount FROM transactions WHERE reseller_id=? AND user_id=? AND type='charge' ORDER BY id DESC LIMIT 1");
                    $stmt->execute([$reseller_id, $user_id]);
                    $charge_amt = (float)($stmt->fetchColumn() ?: 0);

                    if ($charge_amt > 0) {
                        update_balance($pdo, $reseller_id, $charge_amt, 'refund', "Refund on delete for {$email}", $user_id);
                        $refund_note = " Refund initiated: $" . money_fmt($charge_amt) . ".";
                    }
                }
            }

            log_action($pdo, "DELETE_USER", "Deleted {$email} (group={$group})", $_SESSION['username'], $reseller_id, $console_id);
            $pdo->commit();

            $flash_success = "User removed from Adobe + deleted locally." . $refund_note;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * RESELLER: Bulk delete users (REAL UMAPI remove) + refund (if eligible)
     */
    if ($action === 'reseller_bulk_delete_users' && is_logged_in() && !is_super_admin()) {
        csrf_verify();
        $reseller_id = (int)$_SESSION['user_id'];

        // Check if reseller is suspended
        $stmt = $pdo->prepare("SELECT status FROM resellers WHERE id=?");
        $stmt->execute([$reseller_id]);
        $status = (string)($stmt->fetchColumn() ?: 'active');
        if ($status === 'suspended') {
            throw new RuntimeException("Your account is suspended. Cannot delete users.");
        }

        $remove_from_org = !empty($_POST['remove_from_org']) ? 1 : 0;
        $user_ids = $_POST['user_ids'] ?? [];
        if (!is_array($user_ids) || count($user_ids) === 0) {
            throw new RuntimeException("Please select at least one user to delete.");
        }

        $ok = 0;
        $fail = 0;
        $refunded_total = 0.0;
        $fail_msgs = [];

        foreach ($user_ids as $uid_raw) {
            $user_id = (int)$uid_raw;
            if ($user_id <= 0) continue;

            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id=? AND reseller_id=?");
                $stmt->execute([$user_id, $reseller_id]);
                $u = $stmt->fetch();
                if (!$u) throw new RuntimeException("User not found (ID={$user_id}).");

                $console_id = (int)($u['console_id'] ?? 0);
                if ($console_id <= 0) throw new RuntimeException("No console_id stored.");
                $console = get_console($pdo, $console_id);

                $email = (string)$u['email'];
                $group = (string)$u['product_profile'];

                $do = [];
                if ($group !== '') $do[] = ["remove" => ["group" => [$group]]];
                if ($remove_from_org) $do[] = ["removeFromOrg" => new stdClass()];

                $commands = [[
                    "user" => $email,
                    "requestID" => "bulk_delete_" . $user_id . "_" . time(),
                    "do" => $do
                ]];

                $result = umapi_action($console, $commands);
                if ($result['code'] < 200 || $result['code'] >= 300) {
                    throw new RuntimeException("Adobe UMAPI delete failed (HTTP {$result['code']}): " . $result['raw']);
                }

                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id=? AND reseller_id=?");
                    $stmt->execute([$user_id, $reseller_id]);

                    // Refund (only within refund window, and only once)
                    $eligible_until = (string)($u['refund_eligible_until'] ?? '');
                    if ($eligible_until !== '' && strtotime($eligible_until) !== false && strtotime($eligible_until) >= time()) {
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE reseller_id=? AND user_id=? AND type='refund'");
                        $stmt->execute([$reseller_id, $user_id]);
                        $already_refunded = (int)$stmt->fetchColumn();

                        if ($already_refunded === 0) {
                            $stmt = $pdo->prepare("SELECT amount FROM transactions WHERE reseller_id=? AND user_id=? AND type='charge' ORDER BY id DESC LIMIT 1");
                            $stmt->execute([$reseller_id, $user_id]);
                            $charge_amt = (float)($stmt->fetchColumn() ?: 0);

                            if ($charge_amt > 0) {
                                update_balance($pdo, $reseller_id, $charge_amt, 'refund', "Refund on delete for {$email}", $user_id);
                                $refunded_total += $charge_amt;
                            }
                        }
                    }

                    log_action($pdo, "BULK_DELETE_USER", "Deleted {$email} (group={$group})", $_SESSION['username'], $reseller_id, $console_id);
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    throw $e;
                }

                $ok++;
            } catch (Throwable $e) {
                $fail++;
                $fail_msgs[] = $e->getMessage();
            }
        }

        $msg = "Deleted {$ok} user(s).";
        if ($refunded_total > 0) $msg .= " Refunded: $" . money_fmt($refunded_total) . ".";
        if ($fail > 0) $msg .= " Failed: {$fail}.";
        if ($fail > 0 && count($fail_msgs) > 0) {
            $msg .= " First error: " . $fail_msgs[0];
        }

        $flash_success = $msg;
    }


    // Admin: Update reseller (edit)
    if ($action === 'admin_update_reseller' && is_super_admin()) {
        csrf_verify();
        $rid = (int)($_POST['reseller_id'] ?? 0);

        $username = trim((string)($_POST['username'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $company = trim((string)($_POST['company_name'] ?? ''));
        $rate = (float)($_POST['monthly_rate'] ?? 1.00);

        $balance = (float)($_POST['balance'] ?? 0.00);
        $total_billed = (float)($_POST['total_billed'] ?? 0.00);
        $total_paid = (float)($_POST['total_paid'] ?? 0.00);

        $newPass = (string)($_POST['new_password'] ?? '');
        $setPass = $newPass !== '' ? password_hash($newPass, PASSWORD_DEFAULT) : null;

        $chk = $pdo->prepare("SELECT COUNT(*) FROM resellers WHERE (username=? OR email=?) AND id<>?");
        $chk->execute([$username, $email, $rid]);
        if ((int)$chk->fetchColumn() > 0) throw new RuntimeException("Username or email already exists.");

        if ($setPass) {
            $stmt = $pdo->prepare("UPDATE resellers SET username=?, email=?, company_name=?, monthly_rate=?, balance=?, total_billed=?, total_paid=?, password=? WHERE id=? AND role='reseller'");
            $stmt->execute([$username, $email, $company, $rate, $balance, $total_billed, $total_paid, $setPass, $rid]);
        } else {
            $stmt = $pdo->prepare("UPDATE resellers SET username=?, email=?, company_name=?, monthly_rate=?, balance=?, total_billed=?, total_paid=? WHERE id=? AND role='reseller'");
            $stmt->execute([$username, $email, $company, $rate, $balance, $total_billed, $total_paid, $rid]);
        }

        log_action($pdo, "UPDATE_RESELLER", "Updated reseller #{$rid}", $_SESSION['username'], $rid);
        $flash_success = "Reseller updated.";
        header("Location: ?page=admin_edit_reseller&id={$rid}&saved=1"); exit;
    }

    // Admin: Add payment / adjust billing
    if ($action === 'admin_add_payment' && is_super_admin()) {
        csrf_verify();
        $rid = (int)($_POST['reseller_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);
        $desc = trim((string)($_POST['description'] ?? 'Payment recorded'));
        if ($amount <= 0) throw new RuntimeException("Amount must be greater than 0.");

        add_transaction($pdo, $rid, null, 'payment', $amount, $desc);
        log_action($pdo, "PAYMENT", "Payment added for reseller #{$rid}: {$amount}", $_SESSION['username'], $rid);
        $flash_success = "Payment added.";
        header("Location: ?page=admin_edit_reseller&id={$rid}#billing"); exit;
    }

    // Admin: Delete reseller
    if ($action === 'admin_delete_reseller' && is_super_admin()) {
        csrf_verify();
        $rid = (int)($_POST['reseller_id'] ?? 0);
        $pdo->prepare("DELETE FROM resellers WHERE id=? AND role='reseller'")->execute([$rid]);
        log_action($pdo, "DELETE_RESELLER", "Deleted reseller #{$rid}", $_SESSION['username'], $rid);
        $flash_success = "Reseller deleted.";
    }

    // Admin: Delete console
    if ($action === 'admin_delete_console' && is_super_admin()) {
        csrf_verify();
        $cid = (int)($_POST['console_id'] ?? 0);

        $affected = $pdo->prepare("SELECT id AS user_id, reseller_id, console_id FROM users WHERE console_id=?");
        $affected->execute([$cid]);
        foreach ($affected->fetchAll() as $urow) {
            $uid = (int)$urow['user_id'];
            $rid = (int)$urow['reseller_id'];
            $ordered = get_reseller_console_ids($pdo, $rid);
            $target = null;
            foreach ($ordered as $candidate) {
                if ((int)$candidate === $cid) continue;
                $c = $pdo->prepare("SELECT * FROM admin_consoles WHERE id=? LIMIT 1");
                $c->execute([(int)$candidate]);
                $cc = $c->fetch();
                if ($cc && console_is_usable($cc)) { $target = (int)$candidate; break; }
            }
            if ($target) {
                $pdo->prepare("UPDATE users SET console_id=?, updated_at=NOW() WHERE id=?")->execute([$target, $uid]);
            } else {
                $pdo->prepare("UPDATE users SET console_id=NULL, updated_at=NOW() WHERE id=?")->execute([$uid]);
            }
        }

        $pdo->prepare("DELETE FROM admin_consoles WHERE id=?")->execute([$cid]);
        log_action($pdo, "DELETE_CONSOLE", "Deleted console #{$cid}", $_SESSION['username'], null, $cid);
        $flash_success = "Console deleted.";
    }

    // Admin: Run auto migration now (manual trigger)
    if ($action === 'admin_run_migration' && is_super_admin()) {
        csrf_verify();
        $res = auto_migrate_users($pdo);
        $flash_success = "Auto migration complete. Migrated {$res['migrated']} users, skipped {$res['skipped']}.";
        log_action($pdo, "RUN_MIGRATION", "Manual auto migration run. Migrated {$res['migrated']}, skipped {$res['skipped']}.", $_SESSION['username']);
    }

    // Admin: Manual console -> console migration
    if ($action === 'admin_console_migrate' && is_super_admin()) {
        csrf_verify();
        $from = (int)($_POST['from_console_id'] ?? 0);
        $to = (int)($_POST['to_console_id'] ?? 0);
        $out = migrate_console_to_console($pdo, $from, $to);
        if (($out['message'] ?? '') === 'ok') {
            $flash_success = "Migration complete. Moved {$out['migrated_users']} users. Updated {$out['updated_resellers']} reseller console assignments.";
            log_action($pdo, "CONSOLE_MIGRATE", "Migrated console #{$from} -> #{$to}. Users: {$out['migrated_users']}. Resellers updated: {$out['updated_resellers']}", $_SESSION['username'] ?? null, null, $to);
        } else {
            $flash_error = (string)($out['message'] ?? 'Migration failed.');
        }
    }

    if ($action === 'admin_delete_user' && is_super_admin()) {
        csrf_verify();
        $user_id = (int)($_POST['user_id'] ?? 0);
        $remove_from_org = !empty($_POST['remove_from_org']) ? 1 : 0;

        $stmt = $pdo->prepare("SELECT u.*, r.username AS reseller_name FROM users u LEFT JOIN resellers r ON u.reseller_id=r.id WHERE u.id=?");
        $stmt->execute([$user_id]);
        $u = $stmt->fetch();
        if (!$u) throw new RuntimeException("User not found.");

        $console_id = (int)($u['console_id'] ?? 0);
        if ($console_id <= 0) throw new RuntimeException("User has no console assigned.");

        $console = get_console($pdo, $console_id);
        $email = (string)$u['email'];
        $group = (string)$u['product_profile'];

        $do = [];
        if ($group !== '') $do[] = ["remove" => ["group" => [$group]]];
        if ($remove_from_org) $do[] = ["removeFromOrg" => new stdClass()];

        $commands = [[
            "user" => $email,
            "requestID" => "admin_delete_" . time(),
            "do" => $do
        ]];

        $result = umapi_action($console, $commands);
        if ($result['code'] < 200 || $result['code'] >= 300) {
            throw new RuntimeException("Adobe UMAPI delete failed (HTTP {$result['code']}): " . $result['raw']);
        }

        $stmt = $pdo->prepare("DELETE FROM users WHERE id=?");
        $stmt->execute([$user_id]);

        log_action($pdo, "ADMIN_DELETE_USER", "Admin deleted {$email} (group={$group})", $_SESSION['username'], (int)$u['reseller_id'], $console_id);
        $flash_success = "User removed from Adobe + deleted locally.";
    }

    // ==============================
    // ADMIN - SUSPEND RESELLER
    // ==============================
    if ($action === 'admin_suspend_reseller' && is_super_admin()) {
        csrf_verify();
        
        $reseller_id = (int)($_POST['reseller_id'] ?? 0);
        $reason = trim((string)($_POST['reason'] ?? ''));
        
        if ($reseller_id <= 0) {
            throw new RuntimeException("Invalid reseller ID.");
        }
        if (empty($reason)) {
            throw new RuntimeException("Please provide a reason for suspension.");
        }
        
        // Check if reseller exists and is not already suspended
        $stmt = $pdo->prepare("SELECT username, status FROM resellers WHERE id=? AND role='reseller'");
        $stmt->execute([$reseller_id]);
        $reseller = $stmt->fetch();
        
        if (!$reseller) {
            throw new RuntimeException("Reseller not found.");
        }
        
        if (($reseller['status'] ?? 'active') === 'suspended') {
            throw new RuntimeException("Reseller is already suspended.");
        }
        
        // Update reseller status
        $stmt = $pdo->prepare("UPDATE resellers SET status='suspended', suspended_at=NOW(), suspended_reason=?, suspended_by=? WHERE id=?");
        $stmt->execute([$reason, (int)$_SESSION['user_id'], $reseller_id]);
        
        // Log the action
        log_action(
            $pdo,
            "SUSPEND_RESELLER",
            "Suspended reseller {$reseller['username']}. Reason: {$reason}",
            $_SESSION['username'] ?? null,
            $reseller_id
        );
        
        $flash_success = "Reseller suspended successfully.";
        
        // Redirect back to resellers page
        header("Location: ?page=admin_resellers");
        exit;
    }

    // ==============================
    // ADMIN - ACTIVATE RESELLER
    // ==============================
    if ($action === 'admin_activate_reseller' && is_super_admin()) {
        csrf_verify();
        
        $reseller_id = (int)($_POST['reseller_id'] ?? 0);
        
        if ($reseller_id <= 0) {
            throw new RuntimeException("Invalid reseller ID.");
        }
        
        // Check if reseller exists
        $stmt = $pdo->prepare("SELECT username, status FROM resellers WHERE id=? AND role='reseller'");
        $stmt->execute([$reseller_id]);
        $reseller = $stmt->fetch();
        
        if (!$reseller) {
            throw new RuntimeException("Reseller not found.");
        }
        
        // Update reseller status
        $stmt = $pdo->prepare("UPDATE resellers SET status='active', suspended_at=NULL, suspended_reason=NULL, suspended_by=NULL WHERE id=?");
        $stmt->execute([$reseller_id]);
        
        // Log the action
        log_action(
            $pdo,
            "ACTIVATE_RESELLER",
            "Activated reseller {$reseller['username']}",
            $_SESSION['username'] ?? null,
            $reseller_id
        );
        
        $flash_success = "Reseller activated successfully.";
        
        // Redirect back to resellers page
        header("Location: ?page=admin_resellers");
        exit;
    }

    // ==============================
    // ADMIN - EXTEND USER EXPIRY
    // ==============================
    if ($action === 'admin_extend_user' && is_super_admin()) {
        csrf_verify();

        $user_id = (int)($_POST['user_id'] ?? 0);
        $newExpiryIn = validate_date_ymd($_POST['expires_at'] ?? '');
        $months = null; // legacy


        if ($user_id <= 0) {
            throw new RuntimeException("Invalid user ID.");
        }
        if (!$newExpiryIn) {
            throw new RuntimeException("Select a valid expiry date.");
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        if (!$user) {
            throw new RuntimeException("User not found.");
        }

        // Get reseller rate for charging
        $reseller_id = (int)$user['reseller_id'];
        $stmt = $pdo->prepare("SELECT monthly_rate FROM resellers WHERE id=?");
        $stmt->execute([$reseller_id]);
        $rate = (float)$stmt->fetchColumn();
        if ($rate <= 0) $rate = 1.0;
        
        $today = date('Y-m-d');
        $base  = (!empty($user['expires_at']) && $user['expires_at'] > $today) ? $user['expires_at'] : $today;
        $newExpiry = $newExpiryIn;
        if ($newExpiry <= $base) {
            throw new RuntimeException("Expiry date must be after current expiry/date.");
        }
        $monthsBill = billing_months_from_expiry($newExpiry, $base);
        $cost = $rate * $monthsBill;

        $pdo->beginTransaction();

        try {
            // Update user expiry
            $pdo->prepare("UPDATE users SET expires_at=?, status='active', updated_at=NOW() WHERE id=?")
                ->execute([$newExpiry, $user_id]);

            // Charge the reseller for the extension
            update_balance($pdo, $reseller_id, $cost, 'charge', "Admin extended {$user['email']} (New Expiry: {$newExpiry})", $user_id);

            $pdo->commit();

            log_action(
                $pdo,
                "ADMIN_EXTEND_USER",
                "Admin extended {$user['email']} (New Expiry: {$newExpiry}) - Charged: $" . money_fmt($cost),
                $_SESSION['username'] ?? null,
                $reseller_id,
                (int)$user['console_id']
            );

            $flash_success = "User extended successfully to {$newExpiry}. Reseller charged: $" . money_fmt($cost);
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // ==============================
    // RESELLER - EXTEND USER EXPIRY
    // ==============================
    if ($action === 'reseller_extend_user' && is_logged_in() && !is_super_admin()) {
        csrf_verify();

        $user_id = (int)($_POST['user_id'] ?? 0);
        $newExpiryIn = validate_date_ymd($_POST['expires_at'] ?? '');
        $months = null; // legacy

        $reseller_id = (int)$_SESSION['user_id'];

        // Check if reseller is suspended
        $stmt = $pdo->prepare("SELECT status, monthly_rate, balance FROM resellers WHERE id=?");
        $stmt->execute([$reseller_id]);
        $resellerData = $stmt->fetch();
        $status = (string)($resellerData['status'] ?? 'active');
        
        if ($status === 'suspended') {
            throw new RuntimeException("Your account is suspended. Cannot extend users.");
        }

        if ($user_id <= 0) {
            throw new RuntimeException("Invalid user ID.");
        }
        if (!$newExpiryIn) {
            throw new RuntimeException("Select a valid expiry date.");
        }

        // Verify user belongs to this reseller
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id=? AND reseller_id=? LIMIT 1");
        $stmt->execute([$user_id, $reseller_id]);
        $user = $stmt->fetch();
        if (!$user) {
            throw new RuntimeException("User not found or does not belong to you.");
        }

        $rate = (float)($resellerData['monthly_rate'] ?? 1.0);
        $balance = (float)($resellerData['balance'] ?? 0);
        
        $today = date('Y-m-d');
        $base  = (!empty($user['expires_at']) && $user['expires_at'] > $today) ? $user['expires_at'] : $today;
        $newExpiry = $newExpiryIn;
        if ($newExpiry <= $base) {
            throw new RuntimeException("Expiry date must be after current expiry/date.");
        }
        $monthsBill = billing_months_from_expiry($newExpiry, $base);
        $cost = $rate * $monthsBill;

        $pdo->beginTransaction();

        try {
            // Update user expiry
            $stmt = $pdo->prepare("UPDATE users SET expires_at=?, status='active', updated_at=NOW() WHERE id=? AND reseller_id=?");
            $stmt->execute([$newExpiry, $user_id, $reseller_id]);

            // Charge the reseller for the extension
            update_balance($pdo, $reseller_id, $cost, 'charge', "Extended {$user['email']} (New Expiry: {$newExpiry})", $user_id);

            $pdo->commit();

            log_action(
                $pdo,
                "RESELLER_EXTEND_USER",
                "Reseller extended {$user['email']} (New Expiry: {$newExpiry}) (New Expiry: {$newExpiry}) - Charged: $" . money_fmt($cost),
                $_SESSION['username'] ?? null,
                $reseller_id,
                (int)$user['console_id']
            );

            // Get updated balance
            $stmt = $pdo->prepare("SELECT balance FROM resellers WHERE id=?");
            $stmt->execute([$reseller_id]);
            $new_balance = (float)$stmt->fetchColumn();

            $flash_success = "User extended successfully to {$newExpiry}. Cost: $" . money_fmt($cost) . ". New balance: $" . money_fmt($new_balance);
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

} catch (Throwable $ex) {
    if ($pdo->inTransaction()) { try { $pdo->rollBack(); } catch (Throwable $e) {} }
    $flash_error = $ex->getMessage();
}

/* ==========================
   DATA LOADERS
========================== */
function get_consoles(PDO $pdo): array {
    return $pdo->query("SELECT * FROM admin_consoles ORDER BY created_at DESC")->fetchAll();
}
function get_resellers(PDO $pdo): array {
    return $pdo->query("
        SELECT r.*,
        (SELECT COUNT(*) FROM users u WHERE u.reseller_id=r.id) AS user_count,
        ac.name AS active_console_name,
        bc.name AS backup_console_name
        FROM resellers r
        LEFT JOIN admin_consoles ac ON r.active_console_id=ac.id
        LEFT JOIN admin_consoles bc ON r.backup_console_id=bc.id
        WHERE r.role='reseller'
        ORDER BY r.created_at DESC
    ")->fetchAll();
}

function get_reseller_console_ids(PDO $pdo, int $reseller_id): array {
    $stmt = $pdo->prepare("SELECT console_id FROM reseller_consoles WHERE reseller_id=? ORDER BY priority ASC");
    $stmt->execute([$reseller_id]);
    $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    if (!$ids) {
        $r = $pdo->prepare("SELECT active_console_id, backup_console_id FROM resellers WHERE id=? LIMIT 1");
        $r->execute([$reseller_id]);
        $row = $r->fetch();
        $a = isset($row['active_console_id']) ? (int)$row['active_console_id'] : 0;
        $b = isset($row['backup_console_id']) ? (int)$row['backup_console_id'] : 0;
        if ($a) $ids[] = $a;
        if ($b && $b !== $a) $ids[] = $b;
    }
    return $ids;
}

function set_reseller_consoles(PDO $pdo, int $reseller_id, array $ordered_console_ids): void {
    $ordered_console_ids = array_values(array_filter(array_unique(array_map('intval', $ordered_console_ids))));
    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM reseller_consoles WHERE reseller_id=?")->execute([$reseller_id]);
        $ins = $pdo->prepare("INSERT INTO reseller_consoles (reseller_id, console_id, priority) VALUES (?,?,?)");
        $p = 1;
        foreach ($ordered_console_ids as $cid) {
            $ins->execute([$reseller_id, $cid, $p]);
            $p++;
        }
        $active = $ordered_console_ids[0] ?? null;
        $backup = $ordered_console_ids[1] ?? null;
        $pdo->prepare("UPDATE resellers SET active_console_id=?, backup_console_id=? WHERE id=?")->execute([$active, $backup, $reseller_id]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function console_is_usable(array $c): bool {
    $status = (string)($c['status'] ?? 'active');
    if ($status !== 'active') return false;
    if (!empty($c['console_expires_at'])) {
        try {
            $exp = new DateTime((string)$c['console_expires_at']);
            $today = new DateTime('today');
            if ($exp < $today) return false;
        } catch (Throwable $e) {}
    }
    return true;
}

/**
 * Auto migration: if a console is down/suspended/expired, reassign users to next available console for that reseller.
 */
function auto_migrate_users(PDO $pdo): array {
    $migrated = 0; $skipped = 0;

    $rows = $pdo->query("
        SELECT u.id AS user_id, u.reseller_id, u.console_id,
               ac.id AS console_id_current, ac.status, ac.console_expires_at
        FROM users u
        LEFT JOIN admin_consoles ac ON u.console_id = ac.id
        WHERE u.console_id IS NOT NULL
          AND (ac.id IS NULL OR ac.status <> 'active' OR (ac.console_expires_at IS NOT NULL AND ac.console_expires_at < CURDATE()))
        ORDER BY u.updated_at DESC
        LIMIT 2000
    ")->fetchAll();

    foreach ($rows as $r) {
        $userId = (int)$r['user_id'];
        $resellerId = (int)$r['reseller_id'];
        $currentConsole = (int)($r['console_id_current'] ?? 0);

        $ordered = get_reseller_console_ids($pdo, $resellerId);
        if (!$ordered) { $skipped++; continue; }

        $placeholders = implode(',', array_fill(0, count($ordered), '?'));
        $stmt = $pdo->prepare("SELECT * FROM admin_consoles WHERE id IN ($placeholders)");
        $stmt->execute($ordered);
        $consolesById = [];
        foreach ($stmt->fetchAll() as $c) $consolesById[(int)$c['id']] = $c;

        $target = null;
        foreach ($ordered as $cid) {
            if ($cid === $currentConsole) continue;
            $c = $consolesById[$cid] ?? null;
            if ($c && console_is_usable($c)) { $target = $cid; break; }
        }

        if (!$target) { $skipped++; continue; }

        $pdo->prepare("UPDATE users SET console_id=?, updated_at=NOW() WHERE id=?")->execute([$target, $userId]);
        log_action($pdo, "AUTO_MIGRATE", "Auto migrated user #{$userId} from console #{$currentConsole} to #{$target}", $_SESSION['username'] ?? null, $resellerId, $target);
        $migrated++;
    }

    return ['migrated' => $migrated, 'skipped' => $skipped];
}

/**
 * Manual migration: move ALL users from one console to another, and update reseller console assignments.
 */
function migrate_console_to_console(PDO $pdo, int $fromConsoleId, int $toConsoleId): array {
    if ($fromConsoleId <= 0 || $toConsoleId <= 0 || $fromConsoleId === $toConsoleId) {
        return ['migrated_users' => 0, 'updated_resellers' => 0, 'message' => 'Invalid console selection.'];
    }

    $from = $pdo->prepare("SELECT * FROM admin_consoles WHERE id=? LIMIT 1");
    $from->execute([$fromConsoleId]);
    $fromRow = $from->fetch();

    $to = $pdo->prepare("SELECT * FROM admin_consoles WHERE id=? LIMIT 1");
    $to->execute([$toConsoleId]);
    $toRow = $to->fetch();

    if (!$fromRow || !$toRow) {
        return ['migrated_users' => 0, 'updated_resellers' => 0, 'message' => 'Console not found.'];
    }
    if (!console_is_usable($toRow)) {
        return ['migrated_users' => 0, 'updated_resellers' => 0, 'message' => 'Target console is not usable (inactive/expired).'];
    }

    $pdo->beginTransaction();
    try {
        $countStmt = $pdo->prepare("SELECT COUNT(*) c FROM users WHERE console_id=?");
        $countStmt->execute([$fromConsoleId]);
        $userCount = (int)($countStmt->fetch()['c'] ?? 0);

        $pdo->prepare("UPDATE users SET console_id=?, updated_at=NOW() WHERE console_id=?")
            ->execute([$toConsoleId, $fromConsoleId]);

        $resellers = $pdo->prepare("SELECT reseller_id, priority FROM reseller_consoles WHERE console_id=?");
        $resellers->execute([$fromConsoleId]);
        $rows = $resellers->fetchAll();

        $updatedResellers = 0;
        foreach ($rows as $r) {
            $rid = (int)$r['reseller_id'];
            $priority = (int)$r['priority'];

            $hasTarget = $pdo->prepare("SELECT 1 FROM reseller_consoles WHERE reseller_id=? AND console_id=? LIMIT 1");
            $hasTarget->execute([$rid, $toConsoleId]);
            if ($hasTarget->fetch()) {
                $pdo->prepare("DELETE FROM reseller_consoles WHERE reseller_id=? AND console_id=?")
                    ->execute([$rid, $fromConsoleId]);
            } else {
                $pdo->prepare("UPDATE reseller_consoles SET console_id=? WHERE reseller_id=? AND console_id=? AND priority=?")
                    ->execute([$toConsoleId, $rid, $fromConsoleId, $priority]);
            }
            $updatedResellers++;
        }

        $pdo->prepare("UPDATE resellers SET active_console_id = CASE WHEN active_console_id=? THEN ? ELSE active_console_id END,
                                      backup_console_id = CASE WHEN backup_console_id=? THEN ? ELSE backup_console_id END
                         WHERE active_console_id=? OR backup_console_id=?")
            ->execute([$fromConsoleId, $toConsoleId, $fromConsoleId, $toConsoleId, $fromConsoleId, $fromConsoleId]);

        $pdo->commit();
        return ['migrated_users' => $userCount, 'updated_resellers' => $updatedResellers, 'message' => 'ok'];
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['migrated_users' => 0, 'updated_resellers' => 0, 'message' => 'Migration failed: ' . $e->getMessage()];
    }
}

function get_my_users(PDO $pdo, int $reseller_id, string $q = ''): array {
    $q = trim($q);
    if ($q !== '') {
        $like = '%' . $q . '%';
        $stmt = $pdo->prepare("SELECT * FROM users WHERE reseller_id=? AND (email LIKE ? OR product_profile LIKE ?) ORDER BY created_at DESC LIMIT 500");
        $stmt->execute([$reseller_id, $like, $like]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE reseller_id=? ORDER BY created_at DESC LIMIT 500");
        $stmt->execute([$reseller_id]);
    }
    $users = $stmt->fetchAll();

    $today = date('Y-m-d');
    $soon = date('Y-m-d', strtotime('+7 days'));
    foreach ($users as &$u) {
        if (!empty($u['expires_at']) && $u['expires_at'] < $today) $u['status'] = 'expired';
        elseif (!empty($u['expires_at']) && $u['expires_at'] <= $soon) $u['status'] = 'expiring';
        else $u['status'] = 'active';
    }
    return $users;
}
function get_all_users(PDO $pdo): array {
    return $pdo->query("
        SELECT u.*, r.username AS reseller_name, c.name AS console_name
        FROM users u
        LEFT JOIN resellers r ON u.reseller_id=r.id
        LEFT JOIN admin_consoles c ON u.console_id=c.id
        ORDER BY u.created_at DESC
        LIMIT 800
    ")->fetchAll();
}
function get_announcements(PDO $pdo, string $role): array {
    if ($role === 'super_admin') {
        return $pdo->query("SELECT a.*, r.username AS creator FROM announcements a
                            LEFT JOIN resellers r ON a.created_by=r.id
                            WHERE a.is_active=1 ORDER BY a.created_at DESC")->fetchAll();
    }
    $stmt = $pdo->prepare("
        SELECT a.* FROM announcements a
        WHERE a.is_active=1
        AND (a.target='all' OR a.target='reseller')
        AND (a.expires_at IS NULL OR a.expires_at >= CURDATE())
        ORDER BY a.created_at DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}
function get_logs(PDO $pdo): array {
    return $pdo->query("
        SELECT a.*, r.username AS reseller_name
        FROM audit_log a
        LEFT JOIN resellers r ON a.reseller_id=r.id
        ORDER BY a.created_at DESC
        LIMIT 300
    ")->fetchAll();
}
function get_transactions(PDO $pdo): array {
    return $pdo->query("
        SELECT t.*, r.username AS reseller_name, u.email AS user_email
        FROM transactions t
        LEFT JOIN resellers r ON t.reseller_id=r.id
        LEFT JOIN users u ON t.user_id=u.id
        ORDER BY t.created_at DESC
        LIMIT 300
    ")->fetchAll();
}

/* ==========================
   UI LAYOUT
========================== */
function layout_header(string $title, ?string $flash_success, ?string $flash_error): void {
    $csrf = csrf_token();

    $nav = $GLOBALS['__NAV'] ?? null;
    $active = $GLOBALS['__ACTIVE_PAGE'] ?? '';
    $user = $_SESSION['username'] ?? '';
    $isLogged = !empty($_SESSION['user_id']);
    $isShell = $isLogged && is_array($nav) && !empty($nav);

    $pageLabel = ($isShell && isset($nav[$active])) ? (string)$nav[$active] : $title;

    ?>
<!doctype html>
<html lang="<?= e(app_lang()) ?>" dir="<?= is_rtl() ? 'rtl' : 'ltr' ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="#0d0d14" id="themeColorMeta">
  <title><?= e($pageLabel) ?> | <?= e($title) ?></title>
  <style>
    /* ================================================================
       FLOOZINA DESIGN SYSTEM v2
       Dark theme (default) + Light theme via [data-theme="light"]
    ================================================================ */
    :root {
      /* Brand */
      --brand: #6366f1;
      --brand-dark: #4f46e5;
      --brand-hover: #818cf8;
      --brand-glow: rgba(99,102,241,.18);

      /* Dark Background Layers */
      --bg: #0d0d14;
      --surface-0: #111119;
      --surface-1: #16161f;
      --surface-2: #1d1d2a;
      --surface-3: #242434;

      /* Dark Text */
      --text-1: #ececf4;
      --text-2: #9090b0;
      --text-3: #50506a;

      /* Dark Borders */
      --border-1: rgba(255,255,255,.09);
      --border-2: rgba(255,255,255,.05);
      --border-3: rgba(255,255,255,.03);

      /* Dark Shadows */
      --shadow-sm: 0 2px 8px rgba(0,0,0,.28);
      --shadow-md: 0 8px 28px rgba(0,0,0,.42);
      --shadow-lg: 0 20px 56px rgba(0,0,0,.58);

      /* Semantic Colors (same in both themes) */
      --success: #10b981;
      --warning: #f59e0b;
      --danger: #ef4444;
      --info: #3b82f6;
      --bg-success: rgba(16,185,129,.12);
      --bg-warning: rgba(245,158,11,.12);
      --bg-danger: rgba(239,68,68,.12);
      --bg-info: rgba(59,130,246,.12);

      /* Radii */
      --r-xs: 6px;
      --r-sm: 8px;
      --r: 12px;
      --r-lg: 16px;
      --r-xl: 20px;
      --r-2xl: 24px;

      /* Layout */
      --sidebar-w: 260px;
      --sidebar-collapsed: 70px;
      --topbar-h: 64px;

      /* Transition */
      --t: .18s ease;

      /* ── Legacy aliases (so existing pages keep working) ── */
      --panel: var(--surface-1);
      --panel2: var(--surface-0);
      --border: var(--border-1);
      --border2: var(--border-2);
      --text: var(--text-1);
      --muted: var(--text-2);
      --muted2: var(--text-3);
      --shadow: var(--shadow-md);
      --shadow2: var(--shadow-sm);
      --radius: var(--r-lg);
      --radius2: var(--r);
      --ring: 0 0 0 3px var(--brand-glow);
      --focus: rgba(255,255,255,.1);
    }

    [data-theme="light"] {
      --bg: #f2f4fb;
      --surface-0: #e8ecf8;
      --surface-1: #ffffff;
      --surface-2: #f6f8fe;
      --surface-3: #eef0fb;
      --text-1: #1a1a2e;
      --text-2: #5a5a7e;
      --text-3: #9898b8;
      --border-1: rgba(0,0,0,.08);
      --border-2: rgba(0,0,0,.05);
      --border-3: rgba(0,0,0,.03);
      --shadow-sm: 0 2px 8px rgba(0,0,0,.06);
      --shadow-md: 0 8px 24px rgba(0,0,0,.1);
      --shadow-lg: 0 20px 48px rgba(0,0,0,.14);
      --focus: rgba(0,0,0,.06);
    }

    /* ── Base reset ── */
    *, *::before, *::after { box-sizing: border-box; }
    html, body { height: 100%; }
    body {
      margin: 0;
      font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
      font-size: 14px;
      line-height: 1.6;
      color: var(--text-1);
      background: var(--bg);
      overflow-x: hidden;
      -webkit-font-smoothing: antialiased;
      transition: background var(--t), color var(--t);
    }
    a { color: inherit; text-decoration: none; }

    /* ── RTL support ── */
    body.rtl { direction: rtl; }
    body.rtl .app { flex-direction: row-reverse; }
    body.rtl .sidebar { border-right: none; border-left: 1px solid var(--border-1); }
    body.rtl .sidebar-collapse-btn { right: auto; left: -14px; }
    body.rtl .nav-item-icon { margin-right: 0; margin-left: 0; }
    body.rtl .topbar { flex-direction: row-reverse; }
    body.rtl .cbms-btn .cbms-text { text-align: right; }
    body.rtl .cbms-panel { left: auto; right: 0; }

    /* ─────────────────────────────────────────
       APP SHELL
    ───────────────────────────────────────── */
    .app {
      display: flex;
      min-height: 100vh;
    }

    /* ─────────────────────────────────────────
       SIDEBAR
    ───────────────────────────────────────── */
    .sidebar {
      position: fixed;
      top: 0; left: 0;
      width: var(--sidebar-w);
      height: 100vh;
      display: flex;
      flex-direction: column;
      background: var(--surface-1);
      border-right: 1px solid var(--border-1);
      z-index: 60;
      transition: width var(--t), transform var(--t), box-shadow var(--t);
      overflow: hidden;
    }
    .sidebar.collapsed { width: var(--sidebar-collapsed); }
    .sidebar.collapsed .nav-label,
    .sidebar.collapsed .brand-text,
    .sidebar.collapsed .sidebar-footer-text,
    .sidebar.collapsed .nav-section-label { opacity: 0; pointer-events: none; width: 0; overflow: hidden; }
    .sidebar.collapsed .nav-item { justify-content: center; padding: 11px; }
    .sidebar.collapsed .nav-item-icon { margin: 0; }
    .sidebar.collapsed .sidebar-user { flex-direction: column; align-items: center; gap: 4px; }
    .sidebar.collapsed .brand { justify-content: center; padding: 20px 0; }

    /* Sidebar top brand */
    .brand {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 20px 18px 16px;
      border-bottom: 1px solid var(--border-2);
      flex-shrink: 0;
    }
    .brand-logo {
      width: 36px; height: 36px;
      border-radius: var(--r);
      background: linear-gradient(135deg, var(--brand) 0%, var(--brand-dark) 100%);
      display: grid; place-items: center;
      color: #fff;
      font-size: 16px;
      font-weight: 800;
      flex-shrink: 0;
      box-shadow: 0 4px 12px var(--brand-glow);
    }
    /* Also keep old .brand-mark working */
    .brand-mark {
      width: 36px; height: 36px;
      border-radius: var(--r);
      background: linear-gradient(135deg, var(--brand) 0%, var(--brand-dark) 100%);
      display: grid; place-items: center;
      color: #fff;
      font-size: 16px;
      font-weight: 800;
      flex-shrink: 0;
      box-shadow: 0 4px 12px var(--brand-glow);
    }
    .brand-text { min-width: 0; overflow: hidden; transition: opacity var(--t), width var(--t); }
    .brand-text h1 { margin: 0; font-size: 14px; font-weight: 700; white-space: nowrap; color: var(--text-1); }
    .brand-text p { margin: 1px 0 0; font-size: 11px; color: var(--text-3); white-space: nowrap; }
    /* Old brand h1/p selectors kept for compat */
    .brand h1 { margin: 0; font-size: 14px; font-weight: 700; white-space: nowrap; color: var(--text-1); }
    .brand p { margin: 1px 0 0; font-size: 11px; color: var(--text-3); white-space: nowrap; }

    /* Sidebar scroll area */
    .sidebar-scroll {
      flex: 1;
      overflow-y: auto;
      overflow-x: hidden;
      padding: 12px 10px;
      scrollbar-width: thin;
      scrollbar-color: var(--border-1) transparent;
    }
    .sidebar-scroll::-webkit-scrollbar { width: 4px; }
    .sidebar-scroll::-webkit-scrollbar-track { background: transparent; }
    .sidebar-scroll::-webkit-scrollbar-thumb { background: var(--border-1); border-radius: 99px; }

    /* Nav sections */
    .nav-section { margin-bottom: 4px; }
    .nav-section-label {
      font-size: 10px;
      font-weight: 600;
      letter-spacing: .12em;
      text-transform: uppercase;
      color: var(--text-3);
      padding: 10px 10px 4px;
      white-space: nowrap;
      transition: opacity var(--t), width var(--t);
    }
    /* Legacy nav-title alias */
    .nav-title {
      font-size: 10px;
      font-weight: 600;
      letter-spacing: .12em;
      text-transform: uppercase;
      color: var(--text-3);
      padding: 10px 10px 4px;
    }
    .nav { display: flex; flex-direction: column; gap: 2px; }
    .nav-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 9px 11px;
      border-radius: var(--r);
      color: var(--text-1);
      font-size: 13px;
      font-weight: 500;
      white-space: nowrap;
      border: 1px solid transparent;
      transition: background var(--t), color var(--t), border-color var(--t);
      cursor: pointer;
    }
    .nav-item:hover {
      background: var(--surface-2);
      color: var(--text-1);
    }
    .nav-item.active {
      background: var(--brand-glow);
      border-color: rgba(99,102,241,.25);
      color: var(--brand-hover);
      font-weight: 600;
    }
    .nav-item-icon {
      width: 20px;
      height: 20px;
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      color: inherit;
    }
    .nav-item-icon svg {
      width: 18px;
      height: 18px;
      stroke: currentColor;
      flex-shrink: 0;
    }
    .nav-label { flex: 1; transition: opacity var(--t); }
    /* Legacy nav-dot – hidden in new design, but kept so old pages don't break */
    .nav-dot { display: none; }

    /* Collapse toggle button */
    .sidebar-collapse-btn {
      position: absolute;
      top: 22px;
      right: -13px;
      width: 26px; height: 26px;
      border-radius: 50%;
      background: var(--surface-1);
      border: 1px solid var(--border-1);
      display: flex; align-items: center; justify-content: center;
      cursor: pointer;
      font-size: 11px;
      color: var(--text-2);
      z-index: 70;
      transition: background var(--t), color var(--t), transform var(--t);
      box-shadow: var(--shadow-sm);
    }
    .sidebar-collapse-btn:hover { background: var(--surface-2); color: var(--text-1); }
    .sidebar.collapsed .sidebar-collapse-btn { transform: rotate(180deg); }

    /* Sidebar footer / user area */
    .sidebar-footer {
      border-top: 1px solid var(--border-2);
      padding: 12px 10px;
      flex-shrink: 0;
    }
    .sidebar-user {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 8px;
      border-radius: var(--r);
      cursor: default;
      transition: background var(--t);
    }
    .sidebar-user:hover { background: var(--surface-2); }
    /* Alias for old .userbox */
    .userbox {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 8px;
      border-radius: var(--r);
    }
    .avatar {
      width: 34px; height: 34px;
      border-radius: var(--r-sm);
      background: linear-gradient(135deg, var(--brand) 0%, var(--brand-dark) 100%);
      color: #fff;
      display: grid; place-items: center;
      font-size: 13px;
      font-weight: 700;
      flex-shrink: 0;
    }
    .sidebar-footer-text { overflow: hidden; min-width: 0; transition: opacity var(--t), width var(--t); }
    .userbox .name,
    .sidebar-footer-text .name { font-size: 13px; font-weight: 600; color: var(--text-1); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .userbox .role,
    .sidebar-footer-text .role { font-size: 11px; color: var(--text-3); white-space: nowrap; }
    .logout-btn {
      display: flex; align-items: center; justify-content: center; gap: 8px;
      width: 100%;
      margin-top: 6px;
      padding: 9px 12px;
      border-radius: var(--r);
      border: 1px solid var(--border-1);
      background: transparent;
      color: var(--text-2);
      font-size: 13px;
      font-weight: 500;
      cursor: pointer;
      transition: background var(--t), color var(--t), border-color var(--t);
    }
    .logout-btn:hover { background: var(--bg-danger); border-color: rgba(239,68,68,.3); color: #fca5a5; }

    /* Sidebar overlay (mobile) */
    .overlay {
      display: none;
      position: fixed; inset: 0;
      background: rgba(0,0,0,.55);
      backdrop-filter: blur(4px);
      z-index: 55;
    }
    body.nav-open .overlay { display: block; }

    /* ─────────────────────────────────────────
       MAIN AREA
    ───────────────────────────────────────── */
    .main {
      flex: 1;
      min-width: 0;
      margin-left: var(--sidebar-w);
      transition: margin-left var(--t);
    }
    body.sidebar-collapsed .main { margin-left: var(--sidebar-collapsed); }

    /* Top bar */
    .topbar {
      position: sticky; top: 0; z-index: 30;
      height: var(--topbar-h);
      display: flex; align-items: center; justify-content: space-between; gap: 12px;
      padding: 0 24px;
      background: var(--surface-1);
      border-bottom: 1px solid var(--border-1);
      backdrop-filter: blur(12px);
    }
    .topbar-left { display: flex; align-items: center; gap: 12px; }
    .topbar-right { display: flex; align-items: center; gap: 8px; }
    .page-title { margin: 0; font-size: 17px; font-weight: 700; color: var(--text-1); }
    .page-subtitle { display: none; } /* hidden in new topbar – kept for compat */

    /* Hamburger */
    .menu-btn {
      display: none;
      align-items: center; justify-content: center;
      width: 36px; height: 36px;
      border-radius: var(--r-sm);
      border: 1px solid var(--border-1);
      background: var(--surface-2);
      color: var(--text-1);
      cursor: pointer;
      font-size: 16px;
      flex-shrink: 0;
      transition: background var(--t);
    }
    .menu-btn:hover { background: var(--surface-3); }

    /* Theme toggle */
    .theme-btn {
      display: flex; align-items: center; justify-content: center;
      width: 36px; height: 36px;
      border-radius: var(--r-sm);
      border: 1px solid var(--border-1);
      background: var(--surface-2);
      color: var(--text-2);
      cursor: pointer;
      font-size: 15px;
      transition: background var(--t), color var(--t);
    }
    .theme-btn:hover { background: var(--surface-3); color: var(--text-1); }

    /* Topbar search */
    .topbar-search {
      position: relative;
      display: flex; align-items: center;
    }
    .topbar-search input {
      width: 200px;
      padding: 7px 12px 7px 34px;
      border-radius: var(--r);
      border: 1px solid var(--border-1);
      background: var(--surface-2);
      color: var(--text-1);
      font-size: 13px;
      outline: none;
      transition: border-color var(--t), width var(--t), box-shadow var(--t);
    }
    .topbar-search input:focus { border-color: var(--brand); box-shadow: 0 0 0 3px var(--brand-glow); width: 260px; }
    .topbar-search-icon {
      position: absolute; left: 10px;
      color: var(--text-3); font-size: 13px; pointer-events: none;
    }

    /* Notification bell */
    .topbar-notif {
      position: relative;
      display: flex; align-items: center; justify-content: center;
      width: 36px; height: 36px;
      border-radius: var(--r-sm);
      border: 1px solid var(--border-1);
      background: var(--surface-2);
      color: var(--text-2);
      cursor: pointer;
      font-size: 16px;
      transition: background var(--t);
    }
    .topbar-notif:hover { background: var(--surface-3); color: var(--text-1); }
    .notif-dot {
      position: absolute; top: 6px; right: 6px;
      width: 7px; height: 7px;
      border-radius: 50%;
      background: var(--danger);
      border: 1.5px solid var(--surface-1);
    }

    /* Language switcher in topbar */
    .topbar-lang { display: flex; gap: 4px; }
    .topbar-lang a {
      padding: 5px 9px;
      border-radius: var(--r-sm);
      border: 1px solid var(--border-1);
      background: var(--surface-2);
      font-size: 12px;
      font-weight: 600;
      color: var(--text-2);
      transition: background var(--t), color var(--t);
    }
    .topbar-lang a:hover { background: var(--surface-3); color: var(--text-1); }
    .topbar-lang a.active { background: var(--brand-glow); border-color: rgba(99,102,241,.3); color: var(--brand-hover); }

    /* Content area */
    .content {
      padding: 24px 28px 60px;
      max-width: 1280px;
      margin: 0 auto;
    }

    /* ─────────────────────────────────────────
       CARDS
    ───────────────────────────────────────── */
    .card {
      background: var(--surface-1);
      border: 1px solid var(--border-1);
      border-radius: var(--r-lg);
      padding: 20px;
      margin: 0 0 16px;
      box-shadow: var(--shadow-sm);
    }
    .card h3 { margin: 0 0 14px; font-size: 14px; font-weight: 600; color: var(--text-1); }
    .row { display: flex; gap: 16px; flex-wrap: wrap; }
    .col { flex: 1; min-width: 220px; }

    /* KPI stat card */
    .kpi-card {
      background: var(--surface-1);
      border: 1px solid var(--border-1);
      border-radius: var(--r-lg);
      padding: 20px 22px;
      display: flex; flex-direction: column; gap: 10px;
      box-shadow: var(--shadow-sm);
      transition: box-shadow var(--t), border-color var(--t), transform var(--t);
      position: relative; overflow: hidden;
    }
    .kpi-card::before {
      content: '';
      position: absolute; top: 0; left: 0; right: 0; height: 3px;
      background: linear-gradient(90deg, var(--brand), var(--brand-hover));
      opacity: 0;
      transition: opacity var(--t);
    }
    .kpi-card:hover { box-shadow: var(--shadow-md); border-color: var(--border-1); transform: translateY(-1px); }
    .kpi-card:hover::before { opacity: 1; }
    .kpi-label { font-size: 12px; font-weight: 500; color: var(--text-2); text-transform: uppercase; letter-spacing: .06em; }
    .kpi-value { font-size: 30px; font-weight: 700; color: var(--text-1); line-height: 1; }
    .kpi-icon {
      position: absolute; right: 18px; top: 18px;
      font-size: 28px; opacity: .14;
    }
    .kpi-delta { font-size: 12px; color: var(--text-3); }
    .kpi-delta.up { color: var(--success); }
    .kpi-delta.down { color: var(--danger); }

    /* ─────────────────────────────────────────
       FORMS
    ───────────────────────────────────────── */
    label { display: block; font-size: 12px; font-weight: 500; color: var(--text-2); margin: 0 0 5px; }
    input, select, textarea {
      width: 100%;
      padding: 9px 12px;
      border-radius: var(--r);
      border: 1px solid var(--border-1);
      background: var(--surface-2);
      color: var(--text-1);
      font-size: 13px;
      outline: none;
      transition: border-color var(--t), box-shadow var(--t), background var(--t);
    }
    textarea { min-height: 110px; resize: vertical; }
    input:focus, select:focus, textarea:focus {
      border-color: var(--brand);
      box-shadow: 0 0 0 3px var(--brand-glow);
    }
    @media (max-width: 768px) { input, select, textarea { font-size: 16px; } }

    /* ─────────────────────────────────────────
       BUTTONS
    ───────────────────────────────────────── */
    .btn {
      display: inline-flex; align-items: center; justify-content: center; gap: 6px;
      padding: 9px 16px;
      border-radius: var(--r);
      border: 1px solid var(--border-1);
      background: var(--surface-2);
      color: var(--text-1);
      font-size: 13px;
      font-weight: 500;
      cursor: pointer;
      white-space: nowrap;
      transition: background var(--t), border-color var(--t), transform var(--t), box-shadow var(--t);
      text-decoration: none;
    }
    .btn:hover { background: var(--surface-3); border-color: var(--border-1); transform: translateY(-1px); }
    .btn:active { transform: translateY(0); }
    .btn-small, .btn-sm { padding: 6px 11px; font-size: 12px; border-radius: var(--r-sm); }
    .btn-primary {
      background: var(--brand);
      border-color: var(--brand-dark);
      color: #fff;
      box-shadow: 0 2px 8px var(--brand-glow);
    }
    .btn-primary:hover { background: var(--brand-dark); box-shadow: 0 4px 14px var(--brand-glow); }
    .btn-accent {
      background: var(--info);
      border-color: rgba(59,130,246,.6);
      color: #fff;
    }
    .btn-accent:hover { background: #2563eb; }
    .btn-danger {
      background: var(--bg-danger);
      border-color: rgba(239,68,68,.3);
      color: #fca5a5;
    }
    .btn-danger:hover { background: rgba(239,68,68,.2); border-color: rgba(239,68,68,.5); }
    .btn-warning {
      background: var(--bg-warning);
      border-color: rgba(245,158,11,.3);
      color: #fcd34d;
    }
    .btn-warning:hover { background: rgba(245,158,11,.2); }
    .btn-success {
      background: var(--bg-success);
      border-color: rgba(16,185,129,.3);
      color: #6ee7b7;
    }
    .btn-success:hover { background: rgba(16,185,129,.2); }
    .btn-link {
      background: transparent; border-color: transparent;
      color: var(--text-2); padding: 6px 8px;
    }
    .btn-link:hover { background: var(--surface-2); color: var(--text-1); border-color: transparent; }
    .btn-muted { background: var(--surface-2); color: var(--text-2); }

    /* ─────────────────────────────────────────
       BADGES
    ───────────────────────────────────────── */
    .badge {
      display: inline-flex; align-items: center;
      padding: 3px 9px;
      border-radius: 999px;
      font-size: 11px;
      font-weight: 600;
      border: 1px solid var(--border-2);
      white-space: nowrap;
    }
    .b-success { background: var(--bg-success); border-color: rgba(16,185,129,.3); color: #6ee7b7; }
    .b-warning { background: var(--bg-warning); border-color: rgba(245,158,11,.3); color: #fcd34d; }
    .b-danger  { background: var(--bg-danger);  border-color: rgba(239,68,68,.3);  color: #fca5a5; }
    .b-info    { background: var(--bg-info);    border-color: rgba(59,130,246,.3);  color: #93c5fd; }

    /* ─────────────────────────────────────────
       TABLES
    ───────────────────────────────────────── */
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 10px 14px; text-align: left; font-size: 13px; vertical-align: middle; }
    thead th {
      font-size: 11px; font-weight: 600;
      text-transform: uppercase; letter-spacing: .06em;
      color: var(--text-3);
      background: var(--surface-2);
      border-bottom: 1px solid var(--border-1);
    }
    tbody tr { border-bottom: 1px solid var(--border-2); }
    tbody tr:last-child { border-bottom: none; }
    tbody tr:hover td { background: var(--surface-2); }

    /* ─────────────────────────────────────────
       ALERTS
    ───────────────────────────────────────── */
    .ok, .err {
      border-radius: var(--r);
      padding: 11px 14px;
      margin: 12px 0;
      font-size: 13px;
      border: 1px solid;
    }
    .ok  { background: var(--bg-success); border-color: rgba(16,185,129,.35); color: #6ee7b7; }
    .err { background: var(--bg-danger);  border-color: rgba(239,68,68,.35);  color: #fca5a5; }

    /* ─────────────────────────────────────────
       COLLAPSIBLE
    ───────────────────────────────────────── */
    details.collapsible {
      border: 1px solid var(--border-1);
      border-radius: var(--r-lg);
      padding: 12px 16px;
      background: var(--surface-2);
    }
    details.collapsible > summary {
      cursor: pointer; list-style: none;
      display: flex; align-items: center; justify-content: space-between; gap: 10px;
      font-weight: 600; font-size: 13px;
    }
    details.collapsible > summary::-webkit-details-marker { display: none; }

    /* ─────────────────────────────────────────
       MODAL
    ───────────────────────────────────────── */
    .modal {
      background: var(--surface-1);
      border: 1px solid var(--border-1);
      border-radius: var(--r-2xl);
      padding: 28px;
      max-width: 500px; width: 90%;
      box-shadow: var(--shadow-lg);
    }
    .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .modal-header h3 { margin: 0; font-size: 16px; font-weight: 700; }
    .modal-close { background: none; border: none; color: var(--text-2); font-size: 18px; cursor: pointer; border-radius: var(--r-sm); padding: 4px 7px; }
    .modal-close:hover { background: var(--surface-2); color: var(--text-1); }

    /* ─────────────────────────────────────────
       AUTH PAGES (login etc.)
    ───────────────────────────────────────── */
    .auth-wrap { min-height: 100vh; display: grid; place-items: center; padding: 26px; }
    .auth-card { width: min(520px, 100%); }

    /* ─────────────────────────────────────────
       UTILITY
    ───────────────────────────────────────── */
    .small { font-size: 12px; }
    .muted { color: var(--text-2); }
    .inline { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
    img { max-width: 100%; height: auto; }

    /* ─────────────────────────────────────────
       RESPONSIVE
    ───────────────────────────────────────── */
    @media (max-width: 1024px) {
      .sidebar { width: var(--sidebar-collapsed); }
      .sidebar .nav-label,
      .sidebar .brand-text,
      .sidebar .sidebar-footer-text,
      .sidebar .nav-section-label { opacity: 0; width: 0; overflow: hidden; pointer-events: none; }
      .sidebar .nav-item { justify-content: center; padding: 11px; }
      .sidebar .nav-item-icon { margin: 0; }
      .sidebar .brand { justify-content: center; padding: 20px 0; }
      .sidebar .sidebar-user { flex-direction: column; align-items: center; }
      .main { margin-left: var(--sidebar-collapsed); }
      body.sidebar-expanded .sidebar { width: var(--sidebar-w); }
      body.sidebar-expanded .sidebar .nav-label,
      body.sidebar-expanded .sidebar .brand-text,
      body.sidebar-expanded .sidebar .sidebar-footer-text,
      body.sidebar-expanded .sidebar .nav-section-label { opacity: 1; width: auto; pointer-events: auto; }
      body.sidebar-expanded .sidebar .nav-item { justify-content: flex-start; padding: 9px 11px; }
      body.sidebar-expanded .sidebar .brand { justify-content: flex-start; padding: 20px 18px 16px; }
      body.sidebar-expanded .sidebar .sidebar-user { flex-direction: row; align-items: center; }
      body.sidebar-expanded .main { margin-left: var(--sidebar-w); }
    }
    @media (max-width: 768px) {
      .sidebar {
        transform: translateX(-110%);
        width: min(88vw, 280px) !important;
        box-shadow: var(--shadow-lg);
      }
      .sidebar .nav-label,
      .sidebar .brand-text,
      .sidebar .sidebar-footer-text,
      .sidebar .nav-section-label { opacity: 1 !important; width: auto !important; pointer-events: auto !important; }
      .sidebar .nav-item { justify-content: flex-start !important; padding: 9px 11px !important; }
      .sidebar .nav-item-icon { margin-right: 0; }
      .sidebar .brand { justify-content: flex-start !important; padding: 20px 18px 16px !important; }
      .sidebar .sidebar-user { flex-direction: row !important; align-items: center !important; }
      body.rtl .sidebar { transform: translateX(110%); left: auto; right: 0; }
      body.nav-open .sidebar { transform: translateX(0); }
      .main { margin-left: 0 !important; }
      .menu-btn { display: inline-flex !important; }
      .sidebar-collapse-btn { display: none; }
      .topbar-search { display: none; }
      .content { padding: 16px; }
      .row { flex-direction: column; }
      .col { width: 100%; }
    }
    @media (max-width: 520px) {
      .topbar { padding: 0 14px; }
      .content { padding: 12px; }
      .kpi-grid { grid-template-columns: 1fr 1fr !important; }
      .stats { grid-template-columns: 1fr 1fr !important; }
      .card { padding: 14px; }
    }
    @media (max-width: 400px) {
      .kpi-grid, .stats { grid-template-columns: 1fr !important; }
    }

    /* ─────────────────────────────────────────
       CHECKBOX MULTISELECT (CBMS)
    ───────────────────────────────────────── */
    .cbms { position: relative; width: 100%; }
    .cbms select.cbms-hidden { position: absolute; left: -9999px; width: 1px; height: 1px; opacity: 0; }
    .cbms-btn { width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 9px 12px; border: 1px solid var(--border-1); border-radius: var(--r); background: var(--surface-2); cursor: pointer; min-height: 40px; color: var(--text-1); }
    .cbms-btn:focus { outline: 2px solid var(--brand); outline-offset: 2px; }
    .cbms-btn .cbms-text { flex: 1; text-align: left; font-size: 13px; }
    .cbms-btn .cbms-count { font-size: 12px; color: var(--text-3); }
    .cbms-panel { position: absolute; z-index: 50; top: calc(100% + 6px); left: 0; right: 0; background: var(--surface-1); border: 1px solid var(--border-1); border-radius: var(--r); box-shadow: var(--shadow-md); padding: 10px; display: none; }
    .cbms.open .cbms-panel { display: block; }
    .cbms-search { width: 100%; margin-bottom: 8px; }
    .cbms-list { max-height: 260px; overflow: auto; border-radius: var(--r-sm); }
    .cbms-item { display: flex; align-items: flex-start; gap: 10px; padding: 9px; border-radius: var(--r-sm); cursor: pointer; user-select: none; color: var(--text-1); font-size: 13px; }
    .cbms-item:hover { background: var(--surface-2); }
    .cbms-item input { margin-top: 2px; transform: scale(1.1); accent-color: var(--brand); }
    .cbms-item .cbms-label { line-height: 1.3; }
    .cbms-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
    .cbms-chip { display: inline-flex; align-items: center; gap: 6px; border: 1px solid var(--border-1); border-radius: 999px; padding: 3px 10px; font-size: 12px; background: var(--surface-2); color: var(--text-1); }
    .cbms-chip b { font-weight: 700; }
    @media (max-width: 520px) {
      .cbms-panel { position: fixed; left: 12px; right: 12px; top: 72px; max-height: 70vh; overflow: auto; }
      .cbms-list { max-height: 50vh; }
    }

    /* ─────────────────────────────────────────
       ORG / PRODUCT CHECKBOX CARDS
    ───────────────────────────────────────── */
    .org-access-list { display: flex; flex-direction: column; gap: 12px; max-height: 60vh; overflow: auto; padding-right: 6px; }
    .org-card { display: flex; gap: 14px; align-items: flex-start; padding: 14px; border: 1px solid var(--border-1); border-radius: var(--r-lg); background: var(--surface-2); cursor: pointer; transition: background var(--t); }
    .org-card:hover { background: var(--surface-3); }
    .org-card:active { transform: translateY(1px); }
    .org-left { padding-top: 2px; }
    .org-card input[type="checkbox"] { width: 20px; height: 20px; accent-color: var(--brand); }
    .org-body { flex: 1; min-width: 0; }
    .org-title { font-size: 16px; font-weight: 700; color: var(--text-1); }
    .org-sub { margin-top: 4px; font-size: 13px; color: var(--text-2); }
    .org-chips { margin-top: 10px; display: flex; gap: 6px; flex-wrap: wrap; }
    .prod-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px; }
    .prod-item { display: flex; gap: 10px; align-items: flex-start; padding: 10px; border: 1px solid var(--border-1); border-radius: var(--r); background: var(--surface-2); cursor: pointer; transition: background var(--t); }
    .prod-item:hover { background: var(--surface-3); }
    .prod-item input { margin-top: 2px; width: 18px; height: 18px; accent-color: var(--brand); }
    .prod-item span { line-height: 1.3; font-size: 13px; color: var(--text-1); }
    @media (max-width: 700px) { .prod-grid { grid-template-columns: 1fr; } .org-title { font-size: 15px; } }

</style>
</head>
<body class="<?= is_rtl() ? 'rtl' : '' ?>" data-theme="dark">
<?php
// Nav icon map for sidebar — Lucide outline SVGs
$_ic = fn(string $p): string => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">'.$p.'</svg>';
$navIcons = [
  // Admin pages
  'admin_dashboard'     => $_ic('<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>'),
  'admin_users'         => $_ic('<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'),
  'admin_resellers'     => $_ic('<path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/><path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/><path d="M10 18h4"/>'),
  'admin_consoles'      => $_ic('<rect width="20" height="14" x="2" y="3" rx="2"/><path d="M8 21h8"/><path d="M12 17v4"/>'),
  'admin_transactions'  => $_ic('<rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/>'),
  'admin_logs'          => $_ic('<rect width="8" height="4" x="8" y="2" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/>'),
  'admin_announcements' => $_ic('<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>'),
  'admin_migration'     => $_ic('<path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/>'),
  // Reseller pages
  'reseller_dashboard'     => $_ic('<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>'),
  'reseller_users'         => $_ic('<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'),
  'reseller_assign'        => $_ic('<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/>'),
  'reseller_bulk_assign'   => $_ic('<path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"/><path d="M12 22V12"/><path d="m3.3 7 7.703 4.734a2 2 0 0 0 1.994 0L20.7 7"/><path d="m7.5 4.27 9 5.15"/>'),
  'reseller_bulk_delete'   => $_ic('<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/>'),
  'reseller_bulk_history'  => $_ic('<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/>'),
  'reseller_billing'       => $_ic('<rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/>'),
  'reseller_announcements' => $_ic('<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>'),
];
?>
<?php if ($isShell): ?>
<div class="app" id="appShell">

  <!-- ══ SIDEBAR ══ -->
  <aside class="sidebar" id="sidebar" aria-label="Sidebar navigation">

    <!-- Collapse toggle (desktop/tablet) -->
    <button class="sidebar-collapse-btn" id="sidebarCollapseBtn" title="Toggle sidebar" aria-label="Toggle sidebar"><?= $_ic('<path d="m15 18-6-6 6-6"/>') ?></button>

    <!-- Brand -->
    <div class="brand">
      <div class="brand-logo" aria-hidden="true"><?= strtoupper(substr(e($title), 0, 1)) ?: 'F' ?></div>
      <div class="brand-text">
        <h1><?= e($title) ?></h1>
        <p><?= e(t('Management System')) ?></p>
      </div>
    </div>

    <!-- Scrollable nav area -->
    <div class="sidebar-scroll">
      <div class="nav-section">
        <div class="nav-section-label"><?= e(t('Main Menu')) ?></div>
        <nav class="nav" role="navigation" aria-label="Main navigation">
          <?php foreach ($nav as $k => $label):
            $icon = $navIcons[$k] ?? $_ic('<circle cx="12" cy="12" r="5"/>');
          ?>
            <a class="nav-item <?= ($k === $active ? 'active' : '') ?>" href="?page=<?= e($k) ?>" title="<?= e(t_page_label((string)$k, (string)$label)) ?>">
              <span class="nav-item-icon" aria-hidden="true"><?= $icon ?></span>
              <span class="nav-label"><?= e(t_page_label((string)$k, (string)$label)) ?></span>
            </a>
          <?php endforeach; ?>
        </nav>
      </div>
    </div>

    <!-- User + Logout -->
    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="avatar" aria-hidden="true"><?= e(strtoupper(substr((string)$user, 0, 1) ?: 'U')) ?></div>
        <div class="sidebar-footer-text">
          <div class="name"><?= e($user) ?></div>
          <div class="role"><?= e(is_super_admin() ? t('Super Admin') : t('Reseller')) ?></div>
          <?php if (!is_super_admin() && ($_SESSION['status'] ?? 'active') === 'suspended'): ?>
            <span class="badge b-danger" style="margin-top:3px;font-size:10px"><?= e(t('Suspended')) ?></span>
          <?php endif; ?>
        </div>
      </div>
      <form method="post" style="margin:0">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="logout">
        <button class="logout-btn" type="submit">
          <span aria-hidden="true">&#10148;</span>
          <span class="nav-label sidebar-footer-text"><?= e(t('Logout')) ?></span>
        </button>
      </form>
    </div>
  </aside>

  <!-- Mobile overlay -->
  <div class="overlay" id="overlay" aria-hidden="true"></div>

  <!-- ══ MAIN ══ -->
  <main class="main" id="mainContent">

    <!-- Top bar -->
    <header class="topbar" role="banner">
      <div class="topbar-left">
        <button class="menu-btn" type="button" id="menuBtn" aria-label="<?= e(t('Open menu')) ?>">&#9776;</button>
        <h1 class="page-title"><?= e($pageLabel) ?></h1>
      </div>
      <div class="topbar-right">
        <div class="topbar-search">
          <span class="topbar-search-icon" aria-hidden="true">&#128269;</span>
          <input type="search" placeholder="<?= e(t('Search')) ?>…" id="topbarSearch" autocomplete="off" aria-label="Search">
        </div>
        <div class="topbar-lang">
          <a href="?<?= e(http_build_query(array_merge($_GET, ['lang'=>'en']))) ?>" class="<?= app_lang()==='en'?'active':'' ?>">EN</a>
          <a href="?<?= e(http_build_query(array_merge($_GET, ['lang'=>'ar']))) ?>" class="<?= app_lang()==='ar'?'active':'' ?>">&#1593;&#1585;&#1576;&#1610;</a>
        </div>
        <button class="theme-btn" id="themeToggle" title="Toggle theme" aria-label="Toggle light/dark theme" type="button">&#127769;</button>
      </div>
    </header>

    <div class="content">
      <?php if ($flash_success): ?><div class="ok"><?= e($flash_success) ?></div><?php endif; ?>
      <?php if ($flash_error): ?><div class="err"><?= e($flash_error) ?></div><?php endif; ?>

<?php else: ?>
  <div class="auth-wrap">
    <div class="auth-card">
      <div style="margin-bottom:20px">
        <div style="display:flex;align-items:center;gap:14px">
          <div class="brand-logo" style="width:42px;height:42px;font-size:18px;flex-shrink:0;border-radius:var(--r-lg)"><?= strtoupper(substr(e($title), 0, 1)) ?: 'F' ?></div>
          <div>
            <div style="font-weight:700;font-size:15px;color:var(--text-1)"><?= e($title) ?></div>
            <div style="font-size:12px;color:var(--text-3)">Secure admin access</div>
          </div>
        </div>
      </div>
      <?php if ($flash_success): ?><div class="ok"><?= e($flash_success) ?></div><?php endif; ?>
      <?php if ($flash_error): ?><div class="err"><?= e($flash_error) ?></div><?php endif; ?>
<?php endif; ?>

<!-- Suspend Reseller Modal -->
<div id="suspendModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:1000; align-items:center; justify-content:center;">
  <div class="modal">
    <div class="modal-header">
      <h3>Suspend Reseller</h3>
      <button class="modal-close" onclick="hideSuspendModal()">✕</button>
    </div>
    <form method="post" id="suspendForm">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="admin_suspend_reseller">
      <input type="hidden" name="reseller_id" id="suspendResellerId" value="">
      
      <div class="form-group">
        <label>Reseller</label>
        <div id="suspendResellerName" style="padding:12px; background:rgba(255,255,255,0.05); border-radius:12px; margin-bottom:10px;"></div>
      </div>
      
      <div class="form-group">
        <label>Reason for Suspension</label>
        <textarea name="reason" rows="4" required placeholder="Enter reason for suspending this reseller..."></textarea>
      </div>
      
      <div class="alert alert-warning" style="margin:20px 0; padding:12px; background:rgba(246,193,119,0.1); border-radius:12px; color:#f6c177;">
        <strong>Note:</strong> Suspended reseller cannot:
        <ul style="margin:10px 0 0 20px;">
          <li>Login to their account</li>
          <li>Assign new users</li>
          <li>Extend existing users</li>
          <li>Access any features</li>
        </ul>
      </div>
      
      <div class="inline" style="justify-content:flex-end;">
        <button type="button" class="btn" onclick="hideSuspendModal()">Cancel</button>
        <button type="submit" class="btn btn-danger">Suspend Reseller</button>
      </div>
    </form>
  </div>
</div>

<script>
function showSuspendModal(resellerId, resellerName) {
  document.getElementById('suspendResellerId').value = resellerId;
  document.getElementById('suspendResellerName').textContent = resellerName;
  document.getElementById('suspendModal').style.display = 'flex';
}

function hideSuspendModal() {
  document.getElementById('suspendModal').style.display = 'none';
  document.getElementById('suspendForm').reset();
}

// Close modal if clicked outside (do not clobber other global click handlers)
window.addEventListener('click', function(event){
  const modal = document.getElementById('suspendModal');
  if (event.target === modal) hideSuspendModal();
});
</script>
<?php
}

function layout_footer(): void {
    $nav = $GLOBALS['__NAV'] ?? null;
    $isShell = !empty($_SESSION['user_id']) && is_array($nav) && !empty($nav);

    if ($isShell) {
echo <<<HTML
</div></main></div>
<script>
/* ══ THEME TOGGLE ══ */
(function(){
  var STORAGE_KEY = 'floozina_theme';
  var html = document.documentElement;
  var metaTag = document.getElementById('themeColorMeta');
  var btn = document.getElementById('themeToggle');
  function getTheme(){ return localStorage.getItem(STORAGE_KEY) || 'dark'; }
  function applyTheme(t){
    html.setAttribute('data-theme', t);
    if(metaTag) metaTag.content = (t==='light') ? '#f2f4fb' : '#0d0d14';
    if(btn) btn.textContent = (t==='light') ? '\u2600\uFE0F' : '\uD83C\uDF19';
  }
  applyTheme(getTheme());
  if(btn){
    btn.addEventListener('click', function(){
      var next = (html.getAttribute('data-theme')==='dark') ? 'light' : 'dark';
      localStorage.setItem(STORAGE_KEY, next);
      applyTheme(next);
    });
  }
})();

/* ══ SIDEBAR COLLAPSE / MOBILE NAV ══ */
(function(){
  var sidebar   = document.getElementById('sidebar');
  var menuBtn   = document.getElementById('menuBtn');
  var overlay   = document.getElementById('overlay');
  var colBtn    = document.getElementById('sidebarCollapseBtn');
  var body      = document.body;
  var SKEY      = 'floozina_sidebar';
  var MQ_MOBILE = window.matchMedia('(max-width: 768px)');
  var MQ_TABLET = window.matchMedia('(max-width: 1024px)');

  function isMobile(){ return MQ_MOBILE.matches; }

  /* Mobile slide-in/out */
  function openNav(){ body.classList.add('nav-open'); }
  function closeNav(){ body.classList.remove('nav-open'); }

  /* Desktop/tablet: collapsed vs expanded */
  function applySavedState(){
    if(isMobile()) return;
    var saved = localStorage.getItem(SKEY);
    if(saved === 'expanded') body.classList.add('sidebar-expanded');
    else body.classList.remove('sidebar-expanded');
  }
  applySavedState();

  if(colBtn){
    colBtn.addEventListener('click', function(){
      if(isMobile()) return;
      var expanded = body.classList.toggle('sidebar-expanded');
      localStorage.setItem(SKEY, expanded ? 'expanded' : 'collapsed');
    });
  }
  if(menuBtn){
    menuBtn.addEventListener('click', function(){
      if(body.classList.contains('nav-open')) closeNav(); else openNav();
    });
  }
  if(overlay){ overlay.addEventListener('click', closeNav); }
  document.addEventListener('keydown', function(e){
    if(e.key==='Escape') closeNav();
  });
  document.addEventListener('click', function(e){
    var a = e.target.closest && e.target.closest('a.nav-item');
    if(a && isMobile()) closeNav();
  });
  /* Re-apply state on resize */
  window.addEventListener('resize', applySavedState);
})();

/* ═══════════════════════════════════════
   CBMS — Checkbox MultiSelect
═══════════════════════════════════════ */
function cbmsInit(root){
  if(!root) return;
  var select = root.querySelector('select');
  if(!select) return;
  select.classList.add('cbms-hidden');
  var placeholder = root.getAttribute('data-placeholder') || 'Select options';
  var max = parseInt(root.getAttribute('data-max')||'0',10)||0;

  // Button
  var btn = document.createElement('button');
  btn.type = 'button';
  btn.className = 'cbms-btn';
  btn.innerHTML = '<span class="cbms-text"></span><span class="cbms-count"></span><span aria-hidden="true">▾</span>';
  root.insertBefore(btn, select);

  // Panel
  var panel = document.createElement('div');
  panel.className = 'cbms-panel';
  var search = document.createElement('input');
  search.type = 'search';
  search.placeholder = 'Search...';
  search.className = 'cbms-search input';
  panel.appendChild(search);

  var list = document.createElement('div');
  list.className = 'cbms-list';
  panel.appendChild(list);

  var chips = document.createElement('div');
  chips.className = 'cbms-chips';
  panel.appendChild(chips);

  root.appendChild(panel);

  // Track selection order for priority use-cases
  var order = [];
  function rebuildFromSelect(){
    order = [];
    Array.prototype.forEach.call(select.options, function(opt){
      if(opt.selected) order.push(String(opt.value));
    });
  }
  rebuildFromSelect();

  function optionLabel(opt){
    return (opt.textContent || opt.label || opt.value || '').trim();
  }

  function render(){
    // list
    list.innerHTML = '';
    var q = (search.value||'').toLowerCase();
    Array.prototype.forEach.call(select.options, function(opt){
      if(!opt.value) return;
      var label = optionLabel(opt);
      if(q && label.toLowerCase().indexOf(q) === -1) return;

      var row = document.createElement('div');
      row.className = 'cbms-item';
      row.setAttribute('data-value', opt.value);

      var cb = document.createElement('input');
      cb.type = 'checkbox';
      cb.checked = !!opt.selected;

      var lbl = document.createElement('div');
      lbl.className = 'cbms-label';
      lbl.textContent = label;

      row.appendChild(cb);
      row.appendChild(lbl);

      function toggle(){
        var v = String(opt.value);
        var willSelect = !opt.selected;

        if(willSelect){
          if(max && order.length >= max){
            // If max reached, ignore extra taps
            return;
          }
          opt.selected = true;
          if(order.indexOf(v) === -1) order.push(v);
        } else {
          opt.selected = false;
          var idx = order.indexOf(v);
          if(idx !== -1) order.splice(idx,1);
        }
        cb.checked = !!opt.selected;
        update();
        // Trigger change for any listeners
        var ev = new Event('change', {bubbles:true});
        select.dispatchEvent(ev);
      }

      row.addEventListener('click', function(e){
        if(e.target && e.target.tagName === 'A') return;
        if(e.target && e.target.type === 'checkbox') return;
        toggle();
      });
      cb.addEventListener('click', function(e){
        e.stopPropagation();
        toggle();
      });

      list.appendChild(row);
    });

    // chips
    chips.innerHTML = '';
    order.forEach(function(v, idx){
      var opt = select.querySelector('option[value="'+CSS.escape(v)+'"]');
      if(!opt || !opt.selected) return;
      var chip = document.createElement('span');
      chip.className = 'cbms-chip';
      // show index for priority components
      if(root.classList.contains('cbms-priority')){
        chip.innerHTML = '<b>#'+(idx+1)+'</b> '+ optionLabel(opt);
      } else {
        chip.textContent = optionLabel(opt);
      }
      chips.appendChild(chip);
    });
  }

  function update(){
    // keep select selection consistent with order (priority use-case)
    if(root.classList.contains('cbms-priority')){
      // unselect anything not in order
      var set = {};
      order.forEach(function(v){ set[v]=true; });
      Array.prototype.forEach.call(select.options, function(opt){
        if(!opt.value) return;
        opt.selected = !!set[String(opt.value)];
      });
    }
    // update button text
    var selected = [];
    Array.prototype.forEach.call(select.options, function(opt){
      if(opt.value && opt.selected) selected.push(optionLabel(opt));
    });
    var textEl = btn.querySelector('.cbms-text');
    var countEl = btn.querySelector('.cbms-count');
    if(selected.length === 0){
      textEl.textContent = placeholder;
      countEl.textContent = '';
    } else if(selected.length <= 2){
      textEl.textContent = selected.join(', ');
      countEl.textContent = selected.length + ' selected';
    } else {
      textEl.textContent = selected.length + ' selected';
      countEl.textContent = selected.length + ' selected';
    }

    // if page uses hidden priority selects, sync them
    var sync = root.getAttribute('data-sync');
    if(sync === 'priority5'){
      for(var i=1;i<=5;i++){
        var el = document.getElementById('console_'+i);
        if(el){
          el.value = (order[i-1]!==undefined) ? order[i-1] : '';
        }
      }
    }

    render();
  }

  function open(){
    document.querySelectorAll('.cbms.open').forEach(function(x){
      if(x!==root) x.classList.remove('open');
    });
    root.classList.add('open');
    render();
    setTimeout(function(){ search.focus(); }, 50);
  }
  function close(){ root.classList.remove('open'); }
  btn.addEventListener('click', function(){
    if(root.classList.contains('open')) close(); else open();
  });

  document.addEventListener('click', function(e){
    if(!root.contains(e.target)) close();
  });
  document.addEventListener('keydown', function(e){
    if(e.key === 'Escape') close();
  });
  search.addEventListener('input', render);

  // Initial
  update();
}

document.addEventListener('DOMContentLoaded', function(){
  document.querySelectorAll('.cbms').forEach(function(el){ cbmsInit(el); });
});
</script>

</body></html>
HTML;
    } else {
        echo "</div></div></body></html>";
    }
}

/* ==========================
   PAGE RENDER
========================== */
if (!$has_super) {
    layout_header("Setup Wizard - Create Super Admin", $flash_success, $flash_error);
    ?>
    <div class="card">
      <p class="muted">First run detected. Create your Super Admin account.</p>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="setup_create_super">
        <div class="row">
          <div class="col"><label>Username</label><input name="username" required></div>
          <div class="col"><label>Email</label><input name="email" type="email" required></div>
        </div>
        <div class="row">
          <div class="col"><label>Password (min 8 chars)</label><input name="password" type="password" required></div>
        </div>
        <button class="btn btn-primary" type="submit">Create Super Admin</button>
      </form>
    </div>
    <?php
    layout_footer(); exit;
}

if (!is_logged_in() && !in_array($page, ['login','status','developer'], true)) $page = 'login';

// If the platform is deactivated, block ALL portals (including login),
// except Status + Developer Portal.
if (!portal_is_active() && !in_array($page, ['developer','status'], true)) {
    require __DIR__ . '/../pages/portal_disabled.php';
    exit;
}



// --- Modular pages: login handled separately ---
if ($page === 'login') {
    require __DIR__ . '/../pages/login.php';
    exit;
}


// --- Public Status Page (no login required) ---
if ($page === 'status') {
    require __DIR__ . '/../pages/status.php';
    exit;
}

// --- Developer Portal (no login required, protected by key) ---
if ($page === 'developer') {
    require __DIR__ . '/../pages/developer.php';
    exit;
}




// -----------------------------------------------------------------------------
// CLI / Cron Support
// If this file is required from a cron/worker script, we don't want to render
// UI pages or enforce browser login. Cron scripts should define APP_CLI = true
// before requiring app.php.
// -----------------------------------------------------------------------------
if (defined('APP_CLI') && APP_CLI) {
    return;
}

/* Navigation (auto-discovered)
   -----------------------------------------------------------
   To add a new page: just drop a new PHP file into:
     - /pages/admin/      (super admin)
     - /pages/reseller/   (reseller)
   No need to edit app.php.

   Optional: add at the very top of your page file:
     <?php $PAGE_META = ['title'=>'My Page','order'=>50,'nav'=>true,'hidden'=>false]; ?>
*/
function humanize_page_label(string $page, string $rolePrefix): string {
    $s = $page;
    if (starts_with($s, $rolePrefix . '_')) $s = substr($s, strlen($rolePrefix) + 1);
    $s = str_replace('_', ' ', $s);
    $s = preg_replace('/\s+/', ' ', $s);
    return ucwords(trim((string)$s));
}
function discover_nav(string $dir, string $rolePrefix): array {
    $items = [];
    foreach (glob($dir . '/*.php') as $file) {
        $base = basename($file, '.php');
        // keep only files matching role prefix (admin_* / reseller_*)
        if (!starts_with($base, $rolePrefix . '_')) continue;

        // Optional per-page metadata
        $meta = ['title' => null, 'order' => 999, 'nav' => true, 'hidden' => false];
        $head = @file_get_contents($file, false, null, 0, 2048);
        if (is_string($head) && preg_match('/\$PAGE_META\s*=\s*\[(.*?)\];/s', $head)) {
            // We won't eval arbitrary PHP here; instead allow a lightweight JSON meta comment:
            // // META: {"title":"...","order":10,"nav":true,"hidden":false}
        }
        if (is_string($head) && preg_match('/^\s*\/\/\s*META:\s*(\{.*?\})\s*$/m', $head, $mm)) {
            $j = json_decode($mm[1], true);
            if (is_array($j)) {
                $meta['title']  = $j['title']  ?? null;
                $meta['order']  = isset($j['order']) ? (int)$j['order'] : 999;
                $meta['nav']    = isset($j['nav']) ? (bool)$j['nav'] : true;
                $meta['hidden'] = isset($j['hidden']) ? (bool)$j['hidden'] : false;
            }
        }

        // Default hide "edit/detail" pages from the sidebar, but keep them routable
        $autoHidden = (bool)preg_match('/(^admin_edit_|^reseller_edit_|_edit_|_detail_|_view_)/', $base);

        if (!$meta['nav'] || $meta['hidden'] || $autoHidden) continue;

        $label = $meta['title'] ?: humanize_page_label($base, $rolePrefix);
        $items[] = ['key' => $base, 'label' => $label, 'order' => (int)$meta['order']];
    }

    usort($items, fn($a,$b) => ($a['order'] <=> $b['order']) ?: strcmp($a['label'], $b['label']));
    $nav = [];
    foreach ($items as $it) $nav[$it['key']] = $it['label'];
    return $nav;
}

$nav = [];
if (is_super_admin()) {
    $nav = discover_nav(__DIR__ . '/../pages/admin', 'admin');
    if (empty($nav)) $nav = ['admin_dashboard' => 'Dashboard']; // safety fallback
} else {
    $nav = discover_nav(__DIR__ . '/../pages/reseller', 'reseller');
    if (empty($nav)) $nav = ['reseller_dashboard' => 'Dashboard']; // safety fallback
}
if ($page === 'home') $page = is_super_admin() ? 'admin_dashboard' : 'reseller_dashboard';

// Sanitize page key early (used for nav highlighting)
$page = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$page) ?: 'home';

$GLOBALS['__NAV'] = $nav;
$GLOBALS['__ACTIVE_PAGE'] = $page;

if (is_logged_in() && (is_super_admin())) {
    try { auto_migrate_users($pdo); } catch (Throwable $e) { /* ignore */ }
}

layout_header($APP_NAME, $flash_success, $flash_error);

// Role-based page allowlist

// -----------------------------------------------------------------------------
// CLI/Cron Migration Worker
// -----------------------------------------------------------------------------
// Usage:
//   php app/app.php migration_worker --limit=80 --seconds=50
// -----------------------------------------------------------------------------
if (PHP_SAPI === 'cli' && !defined('APP_CLI')) {
    define('APP_CLI', true);
}

if (defined('APP_CLI')) {
    /**
     * Run one migration step for a job (same logic as ajax=migration_step, but no CSRF/session).
     * Returns: ['status','processed','total','failed','warnings']
     */
    function migration_step_core(int $jobId, int $limit = 80): array {
        $limit = (int)$limit;
        if ($limit <= 0) $limit = 80;
        if ($limit > 80) $limit = 80;

        $pdo = pdo_fresh();

        $job = pdo_exec_retry($pdo, "SELECT * FROM migration_jobs WHERE id = :j LIMIT 1", [':j' => $jobId])->fetch();
        if (!$job) {
            return ['status' => 'failed', 'processed' => 0, 'total' => 0, 'failed' => 0, 'warnings' => ['Job not found']];
        }

        $status = (string)$job['status'];
        $total  = (int)$job['total_users'];

        if ($status !== 'running') {
            $processed = (int)pdo_exec_retry($pdo, "SELECT COUNT(*) FROM migration_job_users WHERE job_id=:j AND status IN ('done','failed')", [':j'=>$jobId])->fetchColumn();
            $failed    = (int)pdo_exec_retry($pdo, "SELECT COUNT(*) FROM migration_job_users WHERE job_id=:j AND status='failed'", [':j'=>$jobId])->fetchColumn();
            return ['status'=>$status,'processed'=>$processed,'total'=>$total,'failed'=>$failed,'warnings'=>[]];
        }

        $fromId = (int)$job['from_console_id'];
        $toId   = (int)$job['to_console_id'];
        $defaultTargetProfile = (string)$job['target_profile'];
        $removeFromSource = (int)($job['remove_from_source'] ?? 0);

        $fromConsole = get_console($pdo, $fromId);
        $toConsole   = get_console($pdo, $toId);

        if (!$toConsole || !console_is_usable($toConsole)) {
            pdo_exec_retry($pdo, "UPDATE migration_jobs SET status='failed', error_message='Target console not usable' WHERE id=:j", [':j'=>$jobId]);
            $processed = (int)pdo_exec_retry($pdo, "SELECT COUNT(*) FROM migration_job_users WHERE job_id=:j AND status IN ('done','failed')", [':j'=>$jobId])->fetchColumn();
            $failed    = (int)pdo_exec_retry($pdo, "SELECT COUNT(*) FROM migration_job_users WHERE job_id=:j AND status='failed'", [':j'=>$jobId])->fetchColumn();
            return ['status'=>'failed','processed'=>$processed,'total'=>$total,'failed'=>$failed,'warnings'=>['Target console not usable']];
        }

        $rows = pdo_exec_retry(
            $pdo,
            "SELECT job_id, user_id, reseller_id, email, source_profile, target_profile
             FROM migration_job_users
             WHERE job_id = :j AND status = 'pending'
             ORDER BY user_id ASC
             LIMIT {$limit}",
            [':j' => $jobId]
        )->fetchAll();

        if (!$rows) {
            pdo_exec_retry($pdo, "UPDATE migration_jobs SET status='done' WHERE id=:j", [':j'=>$jobId]);
            $processed = (int)pdo_exec_retry($pdo, "SELECT COUNT(*) FROM migration_job_users WHERE job_id=:j AND status IN ('done','failed')", [':j'=>$jobId])->fetchColumn();
            $failed    = (int)pdo_exec_retry($pdo, "SELECT COUNT(*) FROM migration_job_users WHERE job_id=:j AND status='failed'", [':j'=>$jobId])->fetchColumn();
            return ['status'=>'done','processed'=>$processed,'total'=>$total,'failed'=>$failed,'warnings'=>[]];
        }

        $warnings = [];
        $commands = [];
        $reqToUser = [];

        foreach ($rows as $r) {
            $userId = (int)$r['user_id'];
            $email  = trim((string)$r['email']);
            $tgtProfile = trim((string)($r['target_profile'] ?? ''));
            if ($tgtProfile === '') $tgtProfile = $defaultTargetProfile;

            if ($email === '' || $tgtProfile === '') {
                pdo_exec_retry($pdo,
                    "UPDATE migration_job_users SET status='failed', error_message=:m, processed_at=NOW() WHERE job_id=:j AND user_id=:u",
                    [':m'=>'Missing email or target profile', ':j'=>$jobId, ':u'=>$userId]
                );
                continue;
            }

            $rid = "mig_{$jobId}_{$userId}";
            $commands[] = [
                'user'      => $email,
                'requestID' => $rid,
                'do'        => [
                    ['addAdobeID' => ['email' => $email, 'option' => 'ignoreIfAlreadyExists']],
                    ['add'       => ['group' => [$tgtProfile]]],
                ],
            ];
            $reqToUser[$rid] = ['user_id'=>$userId,'email'=>$email,'profile'=>$tgtProfile];
        }

        if (!$commands) {
            $processed = (int)pdo_exec_retry($pdo, "SELECT COUNT(*) FROM migration_job_users WHERE job_id=:j AND status IN ('done','failed')", [':j'=>$jobId])->fetchColumn();
            $failed    = (int)pdo_exec_retry($pdo, "SELECT COUNT(*) FROM migration_job_users WHERE job_id=:j AND status='failed'", [':j'=>$jobId])->fetchColumn();
            return ['status'=>'running','processed'=>$processed,'total'=>$total,'failed'=>$failed,'warnings'=>$warnings];
        }

        // Main UMAPI add + group add
        $safe = umapi_action_safe($toConsole, $commands);
        if (!empty($safe['warnings'])) {
            foreach ($safe['warnings'] as $w) $warnings[] = $w;
        }

        $pdo = pdo_fresh();
        $resultMap = $safe['resultMap'] ?? [];

        $successEmails = [];

        foreach ($reqToUser as $rid => $info) {
            $r = $resultMap[$rid] ?? ['ok' => false, 'msg' => 'No UMAPI result for request'];

            if (empty($r['ok'])) {
                $msg = (string)($r['msg'] ?? 'UMAPI failed');
                pdo_exec_retry($pdo,
                    "UPDATE migration_job_users SET status='failed', error_message=:m, processed_at=NOW() WHERE job_id=:j AND user_id=:u",
                    [':m'=>$msg, ':j'=>$jobId, ':u'=>(int)$info['user_id']]
                );
                continue;
            }

            $successEmails[] = $info['email'];

            pdo_exec_retry($pdo,
                "UPDATE migration_job_users SET status='done', error_message=NULL, processed_at=NOW() WHERE job_id=:j AND user_id=:u",
                [':j'=>$jobId, ':u'=>(int)$info['user_id']]
            );

            // Update local DB
            pdo_exec_retry($pdo,
                "UPDATE users SET console_id=:to, product_profile=:pp, updated_at=NOW() WHERE id=:uid",
                [':to'=>$toId, ':pp'=>(string)$info['profile'], ':uid'=>(int)$info['user_id']]
            );
        }

        // Optional: remove from source org
        if ($removeFromSource && $fromConsole && $successEmails) {
            try {
                $rmCmds = [];
                foreach ($successEmails as $i => $em) {
                    $rmCmds[] = [
                        'user'      => $em,
                        'requestID' => "mig_rm_{$jobId}_{$i}",
                        'do'        => [['removeFromOrg' => new stdClass()]],
                    ];
                }
                $rmResp = umapi_action($fromConsole, $rmCmds);
                $code = (int)($rmResp['code'] ?? 0);
                if ($code < 200 || $code >= 300) {
                    $warnings[] = "Cleanup batch failed (HTTP {$code})";
                }
            } catch (Throwable $e) {
                $warnings[] = 'Cleanup batch error: ' . $e->getMessage();
            }
        }

        // Update counters + finalize
        $processed = (int)pdo_exec_retry($pdo, "SELECT COUNT(*) FROM migration_job_users WHERE job_id=:j AND status IN ('done','failed')", [':j'=>$jobId])->fetchColumn();
        $failed    = (int)pdo_exec_retry($pdo, "SELECT COUNT(*) FROM migration_job_users WHERE job_id=:j AND status='failed'", [':j'=>$jobId])->fetchColumn();

        pdo_exec_retry($pdo, "UPDATE migration_jobs SET processed_users=:p WHERE id=:j", [':p'=>$processed, ':j'=>$jobId]);

        $pending = (int)pdo_exec_retry($pdo, "SELECT COUNT(*) FROM migration_job_users WHERE job_id=:j AND status='pending'", [':j'=>$jobId])->fetchColumn();
        if ($pending <= 0) {
            pdo_exec_retry($pdo, "UPDATE migration_jobs SET status='done' WHERE id=:j", [':j'=>$jobId]);
            $status = 'done';
        }

        return ['status'=>$status,'processed'=>$processed,'total'=>$total,'failed'=>$failed,'warnings'=>$warnings];
    }

    function migration_worker_run(int $limit = 80, int $seconds = 50): int {
        $limit = max(1, min(80, (int)$limit));
        $seconds = max(5, (int)$seconds);

        $start = time();
        $steps = 0;

        while ((time() - $start) < $seconds) {
            $pdo = pdo_fresh();
            $jobId = (int)pdo_exec_retry($pdo, "SELECT id FROM migration_jobs WHERE status='running' ORDER BY id ASC LIMIT 1", [])->fetchColumn();
            if ($jobId <= 0) break;

            migration_step_core($jobId, $limit);
            $steps++;

            // avoid a tight loop
            usleep(250000);
        }

        return $steps;
    }

    // CLI entry
    $cmd = $argv[1] ?? '';
    if ($cmd === 'migration_worker') {
        // satisfy any permission gates in shared helpers
        $_SESSION['is_super_admin'] = true;
        $_SESSION['user_id'] = $_SESSION['user_id'] ?? 1;

        $limit = 80;
        $seconds = 50;
        foreach ($argv as $a) {
            if (preg_match('/^--limit=(\d+)$/', $a, $m)) $limit = (int)$m[1];
            if (preg_match('/^--seconds=(\d+)$/', $a, $m)) $seconds = (int)$m[1];
        }

        $steps = migration_worker_run($limit, $seconds);
        echo "OK steps={$steps}\n";
        exit(0);
    }
}
// -----------------------------------------------------
// Auto page loader (no whitelists)
// Add a new page by creating a new PHP file in /pages/admin or /pages/reseller.
// The page key is the filename without .php (e.g. pages/admin/admin_reports.php => ?page=admin_reports).
function safe_page_key(string $p): string {
    // only allow letters, numbers and underscores
    $p = preg_replace('/[^a-zA-Z0-9_]/', '', $p);
    return $p ?: 'home';
}

$page = safe_page_key($page);

if (is_super_admin()) {
    $default = 'admin_dashboard';
    if (!starts_with($page, 'admin_')) $page = $default;
    $dir = __DIR__ . '/../pages/admin';
    $file = $dir . '/' . $page . '.php';
    if (!is_file($file)) {
        $page = $default;
        $file = $dir . '/' . $page . '.php';
    }
    require $file;
} else {
    $default = 'reseller_dashboard';
    if (!starts_with($page, 'reseller_')) $page = $default;
    $dir = __DIR__ . '/../pages/reseller';
    $file = $dir . '/' . $page . '.php';
    if (!is_file($file)) {
        $page = $default;
        $file = $dir . '/' . $page . '.php';
    }
    require $file;
}

layout_footer();
