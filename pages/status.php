<?php
// Public Status Page (Client lookup)
// Accessible via: /?page=status

$title = defined('APP_NAME') ? APP_NAME : ($GLOBALS['APP_NAME'] ?? 'Adobe Console Management');
$flash_success = $flash_success ?? null;
$flash_error   = $flash_error ?? null;

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die('Database connection error. Please contact support.');
}

$email  = trim($_POST['email'] ?? '');
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Public page: log CSRF failures but do not hard-block
    if (empty($_POST['csrf']) || !hash_equals(csrf_token(), (string)$_POST['csrf'])) {
        error_log('CSRF validation failed for public status page - possible automated request');
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $flash_error = "Please enter a valid email address.";
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT
                    u.id,
                    u.email,
                    u.status,
                    u.organization,
                    u.product_profile,
                    u.assigned_at,
                    u.expires_at,
                    u.reseller_id,
                    u.console_id,

                    r.company_name AS reseller_company,
                    r.username     AS reseller_username,

                    ac.name            AS console_name,
                    ac.organization_id AS adobe_org_id
                FROM users u
                LEFT JOIN resellers r ON r.id = u.reseller_id
                LEFT JOIN admin_consoles ac ON ac.id = u.console_id
                WHERE u.email = ?
                ORDER BY u.assigned_at DESC
                LIMIT 1
            ");
            $stmt->execute([$email]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                $flash_error = "No active record found for this email.";
            }
        } catch (PDOException $e) {
            error_log('Database error in status page: ' . $e->getMessage());
            $flash_error = "An error occurred while searching. Please try again later.";
        }
    }
}

// Client-facing announcements
$announcements = [];
try {
    $annStmt = $pdo->prepare("
        SELECT a.*
        FROM announcements a
        WHERE a.is_active = 1
          AND (a.target = 'all' OR a.target = 'client')
          AND (a.expires_at IS NULL OR a.expires_at >= CURDATE())
        ORDER BY a.created_at DESC
        LIMIT 8
    ");
    $annStmt->execute();
    $announcements = $annStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error fetching announcements: ' . $e->getMessage());
}

layout_header($title, $flash_success, $flash_error);
?>

<style>
:root{
  --bg0:#070b18;
  --bg1:#0b1220;
  --bg2:#0b2a6f;
  --stroke: rgba(148,163,184,.18);
  --text:#e5e7eb;
  --muted:#94a3b8;
  --shadow: 0 18px 50px rgba(2,6,23,.55);
  --radius:20px;

  --blue:#3b82f6;
  --green:#22c55e;
  --yellow:#f59e0b;
  --red:#ef4444;
}

body{
  background:
    radial-gradient(900px 500px at 50% 0%, rgba(59,130,246,.30), transparent 60%),
    radial-gradient(700px 450px at 85% 20%, rgba(34,197,94,.14), transparent 55%),
    radial-gradient(700px 450px at 12% 25%, rgba(37,99,235,.18), transparent 55%),
    linear-gradient(135deg, var(--bg0) 0%, var(--bg1) 45%, var(--bg2) 100%) !important;
}

.container-status{max-width:980px;margin:0 auto;padding:16px}
.break-any{word-break:break-word;overflow-wrap:anywhere}

.card{
  background: rgba(15, 23, 42, .78) !important;
  border: 1px solid var(--stroke) !important;
  border-radius: var(--radius) !important;
  box-shadow: var(--shadow);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  padding:18px;
}

h2,h3{color:var(--text);margin:0}
.muted,.muted-sm{color:var(--muted) !important}

.status-top{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}

.status-form-row{display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;margin-top:16px}
.status-form-row .col-grow{flex:1 1 420px;min-width:240px}
.status-form-row .col-btn{flex:0 0 180px;min-width:160px}

label{color:var(--text); font-weight:800}
input{
  width:100%;
  border:1px solid rgba(148,163,184,.20) !important;
  background: rgba(2,6,23,.35) !important;
  color: var(--text) !important;
  border-radius: 14px !important;
  padding: 12px 12px !important;
  box-sizing: border-box;
}
input::placeholder{color:rgba(148,163,184,.65)}
input:focus{
  outline:none !important;
  border-color: rgba(59,130,246,.55) !important;
  box-shadow: 0 0 0 4px rgba(59,130,246,.18) !important;
  background: rgba(2,6,23,.22) !important;
}

.btn, .btn-primary{
  background: linear-gradient(180deg, rgba(37,99,235,1), rgba(30,64,175,1)) !important;
  border: 0 !important;
  color: #fff !important;
  border-radius: 14px !important;
  font-weight: 900 !important;
  padding: 12px 14px !important;
  box-shadow: 0 16px 30px rgba(37,99,235,.18);
  cursor:pointer;
  text-decoration:none;
  display:inline-flex;
  align-items:center;
  justify-content:center;
}
.btn:hover, .btn-primary:hover{filter: brightness(1.06); transform: translateY(-1px)}

.st2-wrap{margin-top:14px}
.st2-card{
  background: rgba(15,23,42,.78);
  border: 1px solid rgba(148,163,184,.18);
  border-radius: 26px;
  box-shadow: var(--shadow);
  padding: 22px 18px;
  text-align:center;
}
.st2-top{display:flex;flex-direction:column;align-items:center;gap:12px}

.st2-icon{
  width:84px;height:84px;border-radius: 22px;
  display:flex;align-items:center;justify-content:center;
  box-shadow: 0 18px 34px rgba(2,6,23,.40);
  border:1px solid rgba(255,255,255,.14);
}
.st2-icon svg{width:44px;height:44px;fill:#fff}

.st2-pill{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  padding: 10px 18px;
  border-radius:999px;
  font-weight:900;
  letter-spacing:.5px;
  border: 1px solid rgba(255,255,255,.12);
}

.st2-email{
  font-size: 34px;
  line-height: 1.15;
  font-weight: 900;
  color: var(--text);
  margin-top: 6px;
}

/* THIS BLOCK NOW SHOWS CONSOLE NAME (NOT ORGANIZATION) */
.st2-org{
  margin-top:18px;
  text-align:left;
  background: rgba(59,130,246,.10);
  border: 1px solid rgba(59,130,246,.20);
  border-radius: 18px;
  padding: 16px 16px;
}
.st2-org-label{
  font-size: 14px;
  letter-spacing: 1.4px;
  font-weight: 900;
  color: rgba(148,163,184,.9);
  margin-bottom: 10px;
}
.st2-org-name{
  font-size: 30px;
  font-weight: 900;
  color: #dbeafe;
}

/* grid like screenshot */
.st2-metrics{
  margin-top: 14px;
  display:grid;
  grid-template-columns: repeat(2, minmax(0,1fr));
  gap: 14px 18px;
  text-align:left;
}
.st2-metric{
  padding-top: 10px;
  border-top: 1px solid rgba(148,163,184,.14);
}
.st2-metric .k{
  font-size: 14px;
  color: rgba(148,163,184,.85);
  font-weight: 800;
}
.st2-metric .v{
  font-size: 18px;
  font-weight: 900;
  color: var(--text);
  margin-top: 6px;
}
.st2-metric .v.is-green{color: rgba(34,197,94,1)}

.st2-progress{
  margin-top: 12px;
  border-top: 1px solid rgba(148,163,184,.14);
  padding-top: 12px;
  text-align:left;
}
.st2-progress .k{
  font-size: 14px;
  color: rgba(148,163,184,.85);
  font-weight: 800;
  margin-bottom: 8px;
}
.st2-bar{
  height: 10px;
  background: rgba(148,163,184,.18);
  border-radius: 999px;
  overflow:hidden;
}
.st2-bar > span{
  display:block;
  height:100%;
  width: 0%;
  background: linear-gradient(90deg, rgba(34,197,94,1), rgba(59,130,246,1));
  border-radius: 999px;
}
.st2-progress .pct{
  margin-top: 8px;
  color: rgba(148,163,184,.9);
  font-weight: 800;
  font-size: 13px;
}

/* status variants */
.is-active{background: linear-gradient(180deg, rgba(34,197,94,1), rgba(16,185,129,1))}
.is-expiring{background: linear-gradient(180deg, rgba(245,158,11,1), rgba(234,88,12,1))}
.is-expired{background: linear-gradient(180deg, rgba(239,68,68,1), rgba(220,38,38,1))}

.st2-pill.is-active{background: rgba(34,197,94,.18); color: rgba(220,252,231,1)}
.st2-pill.is-expiring{background: rgba(245,158,11,.18); color: rgba(254,243,199,1)}
.st2-pill.is-expired{background: rgba(239,68,68,.18); color: rgba(254,226,226,1)}

/* announcements */
.ann-item{
  padding:12px;
  background: rgba(2,6,23,.22);
  border: 1px solid rgba(148,163,184,.14);
  border-radius: 16px;
}
.badge{
  border-radius:999px !important;
  padding:7px 12px !important;
  font-weight: 900 !important;
  letter-spacing: .6px;
  border: 1px solid rgba(255,255,255,.10);
}
.b-success{ background: rgba(34,197,94,.16) !important; color: rgba(220,252,231,1) !important; }
.b-info{    background: rgba(59,130,246,.16) !important; color: #dbeafe !important; }
.b-warning{ background: rgba(245,158,11,.18) !important; color: rgba(254,243,199,1) !important; }
.b-danger{  background: rgba(239,68,68,.16) !important; color: rgba(254,226,226,1) !important; }

@media (max-width: 720px){
  .container-status{padding:12px}
  .status-form-row .col-btn{flex:1 1 100%}
  .status-form-row .col-btn .btn{width:100%}
  .st2-email{font-size: 26px}
  .st2-org-name{font-size: 26px}
  .st2-metrics{grid-template-columns: 1fr}
}
</style>

<div class="main container-status">
  <div class="card">
    <div class="status-top">
      <div>
        <h2 style="margin:0 0 6px 0">🔎 Client Status</h2>
        <p class="muted" style="margin:0">Type your email to check plan duration, remaining time, expiry date, and profile details.</p>
      </div>
    </div>

    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <div class="status-form-row">
        <div class="col col-grow">
          <label for="email-input">Email</label>
          <input id="email-input" name="email" value="<?= e($email) ?>" placeholder="name@example.com" required>
          <small class="muted">We only show subscription info saved by your reseller/admin.</small>
        </div>
        <div class="col col-btn">
          <button class="btn btn-primary" type="submit" style="width:100%">Search</button>
        </div>
      </div>
    </form>
  </div>

  <?php if ($result): ?>
    <?php
      $assignedAt = null;
      $expiresAt  = null;

      try {
          $assignedAt = !empty($result['assigned_at']) ? new DateTime($result['assigned_at']) : null;
          $expiresAt  = !empty($result['expires_at'])  ? new DateTime($result['expires_at'])  : null;
      } catch (Exception $e) {
          error_log('Date parsing error: ' . $e->getMessage());
      }

      $planMonths = null;
      $daysLeft   = null;

      if ($assignedAt && $expiresAt) {
          if ($expiresAt < $assignedAt) {
              $planMonths = null;
              $daysLeft = null;
          } else {
              $interval = $assignedAt->diff($expiresAt);
              $months = ($interval->y * 12) + $interval->m;
              $monthsFloat = $months + ($interval->d / 30);
              $planMonths = max(1.0, round($monthsFloat, 1));

              $today = new DateTime('today');
              $daysLeft = (int)$today->diff($expiresAt)->format('%r%a');
          }
      }

      $st = $result['status'] ?? 'active';
      if ($daysLeft !== null) {
          if ($daysLeft < 0) $st = 'expired';
          elseif ($daysLeft <= 7) $st = 'expiring';
          else $st = ($st ?: 'active');
      }

      $progressPct = 0;
      if ($assignedAt && $expiresAt && $expiresAt > $assignedAt) {
          $today = new DateTime('today');
          $totalDays = max(1, (int)$assignedAt->diff($expiresAt)->format('%a'));
          $usedDays  = (int)$assignedAt->diff(min($today, $expiresAt))->format('%a');
          $progressPct = (int)max(0, min(100, round(($usedDays / $totalDays) * 100)));
      }

      $stateClass = 'is-active';
      if ($st === 'expiring') $stateClass = 'is-expiring';
      if ($st === 'expired')  $stateClass = 'is-expired';

      $emailShow   = $result['email'] ?? '—';
      $consoleName = $result['console_name'] ?? '—';

      $activated = $assignedAt ? $assignedAt->format('d/m/Y') : '—';
      $expires   = $expiresAt  ? $expiresAt->format('d/m/Y')  : '—';

      $managedBy = $result['reseller_company'] ?: ($result['reseller_username'] ?: '—');
    ?>

    <div class="st2-wrap">
      <div class="st2-card">
        <div class="st2-top">
          <div class="st2-icon <?= e($stateClass) ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <path d="M9.0 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4z"></path>
            </svg>
          </div>

          <div class="st2-pill <?= e($stateClass) ?>"><?= e(ucfirst($st)) ?></div>
          <div class="st2-email break-any"><?= e($emailShow) ?></div>
        </div>

        <!-- NOW: show console name in this block, label kept as ORGANIZATION like screenshot -->
        <div class="st2-org">
          <div class="st2-org-label">ORGANIZATION</div>
          <div class="st2-org-name break-any"><?= e($consoleName) ?></div>
        </div>

        <div class="st2-metrics">
          <div class="st2-metric">
            <div class="k">Activated</div>
            <div class="v"><?= e($activated) ?></div>
          </div>

          <div class="st2-metric">
            <div class="k">Expires</div>
            <div class="v is-green"><?= e($expires) ?></div>
          </div>

          <div class="st2-metric">
            <div class="k">Days Remaining</div>
            <div class="v is-green">
              <?php if ($daysLeft === null): ?>
                —
              <?php elseif ($daysLeft < 0): ?>
                <?= e(abs($daysLeft)) ?> days overdue
              <?php else: ?>
                <?= e($daysLeft) ?> days
              <?php endif; ?>
            </div>
          </div>

          <div class="st2-metric">
            <div class="k">Plan Duration</div>
            <div class="v"><?= $planMonths ? e(number_format($planMonths, 1)) . " month(s)" : "—" ?></div>
          </div>
        </div>

        <div class="st2-progress">
          <div class="k">Subscription Progress</div>
          <div class="st2-bar">
            <span style="width: <?= e((string)$progressPct) ?>%"></span>
          </div>
          <div class="pct"><?= e((string)$progressPct) ?>%</div>
        </div>

        <!-- bottom row like screenshot: System/Console + Profile (NO ORG ID) -->
        

          <div class="st2-metric">
            <div class="k">Profile</div>
            <div class="v break-any"><?= e($result['product_profile'] ?? '—') ?></div>
            <div class="muted-sm break-any" style="margin-top:6px">Managed By: <?= e($managedBy) ?></div>
          </div>
        </div>

      </div>
    </div>
  <?php endif; ?>

  <?php if (!empty($announcements)): ?>
    <div class="card" style="margin-top:14px">
      <h3 style="margin-top:0">📢 Announcements</h3>
      <div style="display:flex;flex-direction:column;gap:10px;margin-top:12px">
        <?php foreach ($announcements as $a): ?>
          <?php
            $type  = $a['type'] ?? 'info';
            $b = 'b-info';
            if ($type === 'success') $b = 'b-success';
            if ($type === 'warning') $b = 'b-warning';
            if ($type === 'danger')  $b = 'b-danger';
          ?>
          <div class="ann-item">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap">
              <div style="display:flex;align-items:center;gap:10px">
                <span class="badge <?= e($b) ?>"><?= e(strtoupper($type)) ?></span>
                <div style="font-weight:900" class="break-any"><?= e($a['title'] ?? '') ?></div>
              </div>
              <div class="muted" style="font-size:12px">
                <?= !empty($a['expires_at']) ? "Expires: " . e($a['expires_at']) : "" ?>
              </div>
            </div>
            <div style="margin-top:8px;color:rgba(148,163,184,.92)" class="break-any">
              <?= nl2br(e($a['content'] ?? '')) ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <div class="card" style="margin-top:14px">
    <h3 style="margin-top:0">ℹ️ Notes</h3>
    <ul style="margin:12px 0 0 0;padding-left:18px;color:rgba(148,163,184,.92)">
      <li>This page reads the same subscription data that your reseller/admin saved in the system.</li>
      <li>If you can’t find your record, ask your reseller to confirm the email and expiry date in their portal.</li>
    </ul>
  </div>
</div>

<?php layout_footer(); ?>