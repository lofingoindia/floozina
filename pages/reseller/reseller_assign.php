<?php
// META: {"title": "Assign", "order": 30, "nav": true, "hidden": false}
// Do NOT session_start() here if app.php already starts session

$rid    = (int)($_SESSION['user_id'] ?? 0);
$role   = (string)($_SESSION['role'] ?? '');
$status = (string)($_SESSION['status'] ?? '');

if ($rid <= 0 || $role !== 'reseller') {
    echo "<div class='card' style='padding:12px'>Please login as reseller again.</div>";
    return;
}

/* ==========================================================
   ASSIGN USER (SINGLE)
========================================================== */

// Reseller assigned consoles (supports multiple). Fallback to legacy active_console_id.
$stmt = $pdo->prepare("SELECT active_console_id FROM resellers WHERE id=?");
$stmt->execute([$rid]);
$legacy_active_console_id = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT ac.id, ac.name, ac.status, ac.profiles_json
                       FROM reseller_consoles rc
                       INNER JOIN admin_consoles ac ON ac.id = rc.console_id
                       WHERE rc.reseller_id = ?
                       ORDER BY rc.priority ASC, ac.id DESC");
$stmt->execute([$rid]);
$assigned_consoles = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($assigned_consoles) === 0 && $legacy_active_console_id > 0) {
    $c = get_console($pdo, $legacy_active_console_id);
    if ($c) {
        $assigned_consoles = [[
            'id' => (int)$c['id'],
            'name' => (string)$c['name'],
            'status' => (string)$c['status'],
            'profiles_json' => (string)($c['profiles_json'] ?? ''),
        ]];
    }
}

$default_console_id = 0;
if (!empty($_GET['console_id'])) {
    $default_console_id = (int)$_GET['console_id'];
} elseif (count($assigned_consoles) > 0) {
    $default_console_id = (int)$assigned_consoles[0]['id'];
}

$profiles = [];
$console_name = '';
if ($default_console_id > 0) {
    foreach ($assigned_consoles as $ac) {
        if ((int)$ac['id'] === $default_console_id) {
            $console_name = (string)$ac['name'];
            if (!empty($ac['profiles_json'])) {
                $arr = json_decode((string)$ac['profiles_json'], true);
                if (is_array($arr)) $profiles = $arr;
            }
            break;
        }
    }
}
?>

<div class="card">
  <h3>Assign User (Real Adobe)</h3>

  <?php if ($status === 'suspended'): ?>
    <div class="err">Your account is suspended. You cannot assign users.</div>
  <?php elseif (count($assigned_consoles) === 0): ?>
    <div class="err">Admin ne aapko koi console assign nahi kiya. Admin se bolo reseller ko console assign kare.</div>
  <?php endif; ?>

  <form method="post" id="assignUserForm">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="reseller_assign_user">

    <div class="row">
      <div class="col">
        <label>Organization / Console *</label>
        <select name="console_id" id="assignConsole" required <?= ($status === 'suspended' || count($assigned_consoles)===0) ? "disabled" : "" ?>>
          <option value="">Select console</option>
          <?php foreach ($assigned_consoles as $ac): ?>
            <option value="<?= (int)$ac['id'] ?>" <?= ((int)$ac['id'] === $default_console_id) ? "selected" : "" ?>>
              <?= e($ac['name']) ?> (<?= e($ac['status']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
        <div class="small muted">Console dropdown reseller ko assigned consoles se aata hai.</div>
      </div>

      <div class="col">
        <label>Email *</label>
        <input name="email" type="email" required <?= ($status === 'suspended') ? "disabled" : "" ?>>
      </div>
    </div>

    <div class="row">
      <div class="col">
        
<label>Product Profiles *</label>
<div id="assignProfileWrap" class="prod-grid" data-disabled="<?= ($status === 'suspended' || count($assigned_consoles)===0) ? "1" : "0" ?>">
  <?php foreach ($profiles as $p):
      $gn = (string)($p['groupName'] ?? '');
      $pn = (string)($p['productName'] ?? '');
      if ($gn==='') continue;
      $label = $pn !== '' ? ($pn . " — " . $gn) : $gn;
  ?>
    <label class="prod-item">
      <input type="checkbox" name="product_profile[]" value="<?= e($gn) ?>" <?= ($status === 'suspended' || count($assigned_consoles)===0) ? "disabled" : "" ?>>
      <span><?= e($label) ?></span>
    </label>
  <?php endforeach; ?>
</div>
<div class="small muted">Tap to select one or multiple product profiles (mobile friendly).</div>


      <div class="col">
        <label>Expiry Date *</label>
        <input name="expires_at" type="date" required value="<?= date('Y-m-d', strtotime('+1 month')) ?>" <?= ($status === 'suspended') ? "disabled" : "" ?>>
      </div>
    </div>

    <div class="row">
      <div class="col">
        <label>Organization Label (optional)</label>
        <input name="organization" placeholder="Internal label (optional)" <?= ($status === 'suspended') ? "disabled" : "" ?>>
      </div>
      <div class="col"></div>
    </div>

    <button class="btn btn-primary" type="submit" <?= ($status === 'suspended' || count($assigned_consoles)===0) ? "disabled" : "" ?>>
      Assign (Adobe)
    </button>
  </form>
</div>

<script>
(function(){
  const consoleSel = document.getElementById('assignConsole');
  const profileWrap = document.getElementById('assignProfileWrap');
  if (!consoleSel || !profileWrap) return;

  const csrf = <?= json_encode(csrf_token()) ?>;

  const form = document.getElementById('assignUserForm');
  if(form){
    form.addEventListener('submit', function(e){
      const any = profileWrap.querySelector('input[type="checkbox"]:checked');
      if(!any){
        e.preventDefault();
        alert('Please select at least one product profile.');
      }
    });
  }

  function setProfiles(list){
    profileWrap.innerHTML = '';
    if (!Array.isArray(list)) return;
    const disabled = profileWrap.getAttribute('data-disabled') === '1';
    for (const p of list) {
      const gn = (p && p.groupName) ? String(p.groupName) : '';
      const pn = (p && p.productName) ? String(p.productName) : '';
      if (!gn) continue;
      const label = pn ? (pn + ' — ' + gn) : gn;

      const item = document.createElement('label');
      item.className = 'prod-item';

      const cb = document.createElement('input');
      cb.type = 'checkbox';
      cb.name = 'product_profile[]';
      cb.value = gn;
      if (disabled) cb.disabled = true;

      const sp = document.createElement('span');
      sp.textContent = label;

      item.appendChild(cb);
      item.appendChild(sp);
      profileWrap.appendChild(item);
    }
  }

  async function loadProfiles(consoleId){
    try{
      const body = new URLSearchParams();
      body.set('csrf', csrf);
      body.set('console_id', consoleId);

      const res = await fetch('?ajax=reseller_ajax_get_profiles', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body
      });
      const data = await res.json();
      if (!data || !data.ok) return setProfiles([]);
      setProfiles(data.profiles || []);
    }catch(e){
      setProfiles([]);
    }
  }

  consoleSel.addEventListener('change', () => {
    const v = consoleSel.value;
    if (!v) return setProfiles([]);
    loadProfiles(v);
  });
})();
</script>

<?php
/* ==========================================================
   BULK ASSIGN
========================================================== */
if ($page === 'reseller_bulk_assign') {

    $stmt = $pdo->prepare("SELECT c.id, c.name, c.profiles_json
                           FROM reseller_consoles rc
                           JOIN admin_consoles c ON c.id=rc.console_id
                           WHERE rc.reseller_id=?
                           ORDER BY rc.priority ASC");
    $stmt->execute([$rid]);
    $consoles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$consoles || count($consoles) === 0) {
        $stmt = $pdo->prepare("SELECT active_console_id FROM resellers WHERE id=?");
        $stmt->execute([$rid]);
        $aid = (int)$stmt->fetchColumn();
        if ($aid > 0) {
            $c = get_console($pdo, $aid);
            $consoles = $c ? [[ 'id'=>$c['id'], 'name'=>$c['name'], 'profiles_json'=>$c['profiles_json'] ]] : [];
        } else {
            $consoles = [];
        }
    }

    $first_profiles = [];
    if ($consoles && count($consoles)>0 && !empty($consoles[0]['profiles_json'])) {
        $arr = json_decode((string)$consoles[0]['profiles_json'], true);
        if (is_array($arr)) $first_profiles = $arr;
    }
?>
<div class="card">
  <h3>Bulk Assign (Paste Emails)</h3>

  <?php if ($status === 'suspended'): ?>
    <div class="err">Your account is suspended. You cannot bulk assign users.</div>
  <?php elseif (!$consoles || count($consoles)===0): ?>
    <div class="err">Admin ne aapko koi console assign nahi kiya. Super admin se bolo consoles assign kare.</div>
  <?php endif; ?>

  <div class="small muted" style="margin-bottom:12px">
    Emails ko <b>one-per-line</b> paste karo. Phir organization/console aur product profile select karo.
  </div>

  <div class="row">
    <div class="col">
      <label>Emails (one per line) *</label>
      <textarea id="bulkEmails" rows="10" placeholder="user1@example.com&#10;user2@example.com"></textarea>
      <div class="small muted">Tip: duplicate emails automatically ignore ho jayenge.</div>
    </div>

    <div class="col">
      <label>Organization / Console *</label>
      <select id="bulkConsole" <?= ($status === 'suspended' || !$consoles || count($consoles)===0) ? 'disabled' : '' ?>>
        <?php foreach ($consoles as $c): ?>
          <option value="<?= (int)$c['id'] ?>"><?= e((string)$c['name']) ?></option>
        <?php endforeach; ?>
      </select>

      <label style="margin-top:10px">Product Profile *</label>
      <select id="bulkProfile" multiple size="8" <?= ($status === 'suspended' || !$consoles || count($consoles)===0) ? 'disabled' : '' ?>>
        <?php foreach ($first_profiles as $p):
            $gn = (string)($p['groupName'] ?? '');
            $pn = (string)($p['productName'] ?? '');
            $label = $pn ? ($pn . " — " . $gn) : $gn;
        ?>
          <option value="<?= e($gn) ?>"><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>

      <div class="small muted">Multiple select: Ctrl+Click (Windows) / Cmd+Click (Mac).</div>

      <div style="margin-top:12px" class="row">
        <div class="col">
          <label>Team (optional)</label>
          <input class="input" id="bulkTeam" placeholder="e.g. Team A / Dept / Group" <?= ($status === 'suspended') ? 'disabled' : '' ?>>
        </div>
        <div class="col">
          <label>Months</label>
          <select class="input" id="bulkMonths" <?= ($status === 'suspended') ? 'disabled' : '' ?>>
            <?php for($m=1;$m<=24;$m++): ?>
              <option value="<?= $m ?>"><?= $m ?></option>
            <?php endfor; ?>
          </select>
        </div>
      </div>

      <div id="bulkPreview" class="small muted" style="margin-top:10px"></div>

      <div style="margin-top:14px" class="inline">
        <button class="btn btn-primary" id="bulkStartBtn" type="button" <?= ($status === 'suspended' || !$consoles || count($consoles)===0) ? 'disabled' : '' ?>>
          Start Bulk Assign
        </button>
        <button class="btn" id="bulkResetBtn" type="button">Reset</button>
      </div>

      <div id="bulkMsg" class="small" style="margin-top:12px"></div>

      <div id="bulkProgressWrap" style="margin-top:12px; display:none">
        <div class="small muted" id="bulkProgressText">Starting…</div>
        <div class="progress" style="height:10px; margin-top:8px">
          <div class="progress-bar" id="bulkProgressBar" style="width:0%"></div>
        </div>
        <div class="small muted" style="margin-top:6px" id="bulkProgressCounts"></div>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  const csrf = <?= json_encode(csrf_token()) ?>;

  const form = document.getElementById('assignUserForm');
  if(form){
    form.addEventListener('submit', function(e){
      const any = profileWrap.querySelector('input[type="checkbox"]:checked');
      if(!any){
        e.preventDefault();
        alert('Please select at least one product profile.');
      }
    });
  }
  const elConsole = document.getElementById('bulkConsole');
  const elProfile = document.getElementById('bulkProfile');
  const elEmails = document.getElementById('bulkEmails');
  const elTeam = document.getElementById('bulkTeam');
  const elExpiry = document.getElementById('bulkExpiry');
  const elPreview = document.getElementById('bulkPreview');
  const elMsg = document.getElementById('bulkMsg');
  const btnStart = document.getElementById('bulkStartBtn');
  const btnReset = document.getElementById('bulkResetBtn');

  const wrap = document.getElementById('bulkProgressWrap');
  const bar = document.getElementById('bulkProgressBar');
  const txt = document.getElementById('bulkProgressText');
  const counts = document.getElementById('bulkProgressCounts');

  let jobId = null;
  let running = false;
  let skippedExisting = 0;

  function setMsg(html, isErr=false){
    elMsg.className = 'small ' + (isErr ? 'err' : 'muted');
    elMsg.innerHTML = html;
  }

  async function post(action, payload){
    const form = new URLSearchParams();
    form.append('csrf', csrf);
    payload['action'] = action;
    for (const k in payload) form.append(k, payload[k]);
    const res = await fetch('?ajax=' + action, {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: form.toString()
    });
    return await res.json();
  }

  async function loadProfiles(){
    if (!elConsole) return;
    const cid = elConsole.value;
    elProfile.innerHTML = '';
    const j = await post('reseller_ajax_get_profiles', {console_id: cid});
    if (!j.ok){
      elProfile.innerHTML = '';
      setMsg(j.error || 'Failed to load profiles', true);
      return;
    }
    const prof = j.profiles || [];
    let html = '';
    for (const p of prof){
      const gn = (p.groupName||'');
      if (!gn) continue;
      const pn = (p.productName||'');
      const label = pn ? (pn + ' — ' + gn) : gn;
      html += '<option value="' + encodeURIComponent(gn) + '">' + label.replace(/[&<>"']/g, function(c) {
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
      }) + '</option>';
    }
    elProfile.innerHTML = html;
  }

  function analyzeEmails(raw){
    const lines = String(raw || '').split(/\r?\n/).map(s => s.trim()).filter(Boolean);
    const uniq = new Set();
    let valid = 0;
    for (const s of lines){
      const em = s.toLowerCase();
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)) continue;
      if (!uniq.has(em)){ uniq.add(em); valid++; }
    }
    return {lines: lines.length, valid: valid};
  }

  function updatePreview(){
    const a = analyzeEmails(elEmails?.value || '');
    const expiry = elExpiry?.value || '';
    elPreview.textContent = `Pasted: ${a.lines} • Unique valid emails: ${a.valid} • Expiry: ${expiry || '—'}`;
  }

  async function runLoop(){
    if (!jobId || !running) return;
    const j = await post('reseller_bulk_run', {job_id: jobId, batch: 20});
    if (!j.ok){
      running = false;
      setMsg(j.error || 'Bulk run failed', true);
      btnStart.disabled = false;
      return;
    }
    wrap.style.display = 'block';
    const total = j.total || 0;
    const done = j.done || 0;
    const failed = j.failed || 0;
    const processed = done + failed;
    const p = total > 0 ? Math.round((processed / total) * 100) : 0;
    bar.style.width = p + '%';
    txt.textContent = (j.status === 'done') ? 'Completed' : `Processing... ${processed} / ${total} (${p}%)`;
    counts.textContent = `Done: ${done} • Failed: ${failed} • Skipped: ${skippedExisting} • Remaining: ${j.remaining || 0}`;

    if (j.status === 'done'){
      running = false;
      setMsg('Bulk assign completed. Refresh "My Users" to see the list.', false);
      btnStart.disabled = false;
      return;
    }
    setTimeout(runLoop, 600);
  }

  updatePreview();
  elEmails?.addEventListener('input', updatePreview);
  elExpiry?.addEventListener('change', updatePreview);

  btnStart?.addEventListener('click', async function(){
    if (running) return;
    const emails = elEmails.value.trim();
    const cid = elConsole.value;
    const selected = Array.from(elProfile.selectedOptions || []).map(o => decodeURIComponent(o.value || '')).filter(Boolean);

    if (!emails){ setMsg('Please paste emails first.', true); return; }
    if (!cid){ setMsg('Select organization/console.', true); return; }
    if (!selected || selected.length === 0){ setMsg('Select product profile(s).', true); return; }

    btnStart.disabled = true;
    setMsg('Creating job…', false);

    const expires_at = elExpiry ? elExpiry.value : '';
    const team = elTeam ? elTeam.value.trim() : '';
    const j = await post('reseller_bulk_init', {
      emails, console_id: cid, product_profile: JSON.stringify(selected), expires_at, team
    });

    if (!j.ok){
      btnStart.disabled = false;
      setMsg(j.error || 'Failed to start bulk assign', true);
      return;
    }

    jobId = j.job_id;
    skippedExisting = parseInt(j.skipped_existing || 0, 10) || 0;
    running = true;

    wrap.style.display = 'block';
    bar.style.width = '0%';
    txt.textContent = 'Running…';
    counts.textContent = `Done: 0 • Failed: 0 • Skipped: ${skippedExisting} • Remaining: ${j.total}`;

    runLoop();
  });

  btnReset?.addEventListener('click', function(){
    elEmails.value = '';
    wrap.style.display = 'none';
    bar.style.width = '0%';
    setMsg('', false);
    btnStart.disabled = false;
    running = false;
    jobId = null;
  });

  elConsole?.addEventListener('change', loadProfiles);
  if (elConsole && elConsole.value) loadProfiles();
})();
</script>

<?php } // end bulk assign ?>

<?php
/* ==========================================================
   BULK DELETE
========================================================== */
if ($page === 'reseller_bulk_delete') {

    $stmt = $pdo->prepare("SELECT c.id, c.name, c.profiles_json
                           FROM reseller_consoles rc
                           JOIN admin_consoles c ON c.id=rc.console_id
                           WHERE rc.reseller_id=?
                           ORDER BY rc.priority ASC");
    $stmt->execute([$rid]);
    $consoles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$consoles || count($consoles) === 0) {
        $stmt = $pdo->prepare("SELECT active_console_id FROM resellers WHERE id=?");
        $stmt->execute([$rid]);
        $aid = (int)$stmt->fetchColumn();
        if ($aid > 0) {
            $c = get_console($pdo, $aid);
            $consoles = $c ? [[ 'id'=>$c['id'], 'name'=>$c['name'], 'profiles_json'=>$c['profiles_json'] ]] : [];
        } else {
            $consoles = [];
        }
    }

    $first_profiles = [];
    if ($consoles && count($consoles)>0 && !empty($consoles[0]['profiles_json'])) {
        $arr = json_decode((string)$consoles[0]['profiles_json'], true);
        if (is_array($arr)) $first_profiles = $arr;
    }
?>
<div class="card">
  <h3>Bulk Delete (Remove Users)</h3>

  <?php if ($status === 'suspended'): ?>
    <div class="err">Your account is suspended. You cannot bulk delete users.</div>
  <?php elseif (!$consoles || count($consoles)===0): ?>
    <div class="err">Admin ne aapko koi console assign nahi kiya. Super admin se bolo consoles assign kare.</div>
  <?php endif; ?>

  <div class="small muted" style="margin-bottom:12px">
    Selected console + product profile se users remove honge. Source of truth: aapki local users list.
  </div>

  <div class="row">
    <div class="col">
      <label>Emails (one per line)</label>
      <textarea id="bulkDelEmails" rows="10" placeholder="(optional) user1@example.com&#10;user2@example.com"></textarea>

      <label class="d-flex items-center gap-2" style="margin-top:10px">
        <input type="checkbox" id="bulkDelAll">
        <span class="small muted"><b>Delete ALL</b> my users from selected profile</span>
      </label>
      <div class="small muted">If "Delete ALL" is checked, emails textarea is ignored.</div>
    </div>

    <div class="col">
      <label>Organization / Console *</label>
      <select id="bulkDelConsole" <?= ($status === 'suspended' || !$consoles || count($consoles)===0) ? 'disabled' : '' ?>>
        <?php foreach ($consoles as $c): ?>
          <option value="<?= (int)$c['id'] ?>"><?= e((string)$c['name']) ?></option>
        <?php endforeach; ?>
      </select>

      <label style="margin-top:10px">Product Profile *</label>
      <select id="bulkDelProfile" <?= ($status === 'suspended' || !$consoles || count($consoles)===0) ? 'disabled' : '' ?>>
        <option value="">Select profile</option>
        <?php foreach ($first_profiles as $p):
            $gn = (string)($p['groupName'] ?? '');
            $pn = (string)($p['productName'] ?? '');
            $label = $pn ? ($pn . " — " . $gn) : $gn;
        ?>
          <option value="<?= e($gn) ?>"><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>

      <label class="d-flex items-center gap-2" style="margin-top:12px">
        <input type="checkbox" id="bulkDelRemoveFromOrg">
        <span class="small muted">Also remove from org (removeFromOrg)</span>
      </label>

      <div style="margin-top:14px" class="inline">
        <button class="btn btn-danger" id="bulkDelStartBtn" type="button" <?= ($status === 'suspended' || !$consoles || count($consoles)===0) ? 'disabled' : '' ?>>
          Start Bulk Delete
        </button>
        <button class="btn" id="bulkDelResetBtn" type="button">Reset</button>
      </div>

      <div id="bulkDelMsg" class="small" style="margin-top:12px"></div>

      <div id="bulkDelProgressWrap" style="margin-top:12px; display:none">
        <div class="small muted" id="bulkDelProgressText">Starting…</div>
        <div class="progress" style="height:10px; margin-top:8px">
          <div class="progress-bar" id="bulkDelProgressBar" style="width:0%"></div>
        </div>
        <div class="small muted" style="margin-top:6px" id="bulkDelProgressCounts"></div>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  const csrf = <?= json_encode(csrf_token()) ?>;

  const form = document.getElementById('assignUserForm');
  if(form){
    form.addEventListener('submit', function(e){
      const any = profileWrap.querySelector('input[type="checkbox"]:checked');
      if(!any){
        e.preventDefault();
        alert('Please select at least one product profile.');
      }
    });
  }
  const elConsole = document.getElementById('bulkDelConsole');
  const elProfile = document.getElementById('bulkDelProfile');
  const elEmails = document.getElementById('bulkDelEmails');
  const elAll = document.getElementById('bulkDelAll');
  const elRfo = document.getElementById('bulkDelRemoveFromOrg');
  const elMsg = document.getElementById('bulkDelMsg');
  const btnStart = document.getElementById('bulkDelStartBtn');
  const btnReset = document.getElementById('bulkDelResetBtn');

  const wrap = document.getElementById('bulkDelProgressWrap');
  const bar = document.getElementById('bulkDelProgressBar');
  const txt = document.getElementById('bulkDelProgressText');
  const counts = document.getElementById('bulkDelProgressCounts');

  let jobId = null;
  let running = false;

  function setMsg(html, isErr=false){
    elMsg.className = 'small ' + (isErr ? 'err' : 'muted');
    elMsg.innerHTML = html;
  }

  async function post(action, payload){
    const form = new URLSearchParams();
    form.append('csrf', csrf);
    payload['action'] = action;
    for (const k in payload) form.append(k, payload[k]);
    const res = await fetch('?ajax=' + action, {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: form.toString()
    });
    return await res.json();
  }

  async function loadProfiles(){
    if (!elConsole) return;
    const cid = elConsole.value;
    elProfile.innerHTML = '<option value="">Loading…</option>';
    const j = await post('reseller_ajax_get_profiles', {console_id: cid});
    if (!j.ok){
      elProfile.innerHTML = '<option value="">Select profile</option>';
      setMsg(j.error || 'Failed to load profiles', true);
      return;
    }
    const prof = j.profiles || [];
    let html = '<option value="">Select profile</option>';
    for (const p of prof){
      const gn = (p.groupName||'');
      if (!gn) continue;
      const pn = (p.productName||'');
      const label = pn ? (pn + ' — ' + gn) : gn;
      html += '<option value="' + encodeURIComponent(gn) + '">' + label.replace(/[&<>"']/g, function(c) {
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
      }) + '</option>';
    }
    elProfile.innerHTML = html;
  }
  elConsole?.addEventListener('change', loadProfiles);

  async function runLoop(){
    if (!jobId || !running) return;
    const j = await post('reseller_bulk_delete_run', {job_id: jobId, batch: 20});
    if (!j.ok){
      running = false;
      setMsg(j.error || 'Bulk delete failed', true);
      btnStart.disabled = false;
      return;
    }

    wrap.style.display = 'block';
    const total = j.total || 0;
    const done = j.done || 0;
    const failed = j.failed || 0;
    const skipped = j.skipped || 0;
    const processed = done + failed + skipped;
    const p = total > 0 ? Math.round((processed / total) * 100) : 0;

    bar.style.width = p + '%';
    txt.textContent = (j.status === 'done') ? 'Completed' : `Processing... ${processed} / ${total} (${p}%)`;
    counts.textContent = `Done: ${done} • Failed: ${failed} • Skipped: ${skipped} • Remaining: ${j.remaining || 0}`;

    if (j.status === 'done'){
      running = false;
      setMsg('Bulk delete completed. Refresh "My Users" to see updated list.', false);
      btnStart.disabled = false;
      return;
    }
    setTimeout(runLoop, 600);
  }

  btnStart?.addEventListener('click', async function(){
    if (running) return;
    const cid = elConsole.value;
    const pg = decodeURIComponent(elProfile.value || '');
    const emails = elEmails.value.trim();
    const delAll = elAll.checked ? 1 : 0;
    const rfo = elRfo.checked ? 1 : 0;

    if (!cid){ setMsg('Select organization/console.', true); return; }
    if (!pg){ setMsg('Select product profile.', true); return; }
    if (!delAll && !emails){ setMsg('Paste emails or select "Delete ALL".', true); return; }

    btnStart.disabled = true;
    setMsg('Creating delete job…', false);

    const j = await post('reseller_bulk_delete_init', {
      console_id: cid,
      product_profile: pg,
      emails: emails,
      delete_all: delAll,
      remove_from_org: rfo
    });

    if (!j.ok){
      btnStart.disabled = false;
      setMsg(j.error || 'Failed to start bulk delete', true);
      return;
    }

    jobId = j.job_id;
    running = true;
    wrap.style.display = 'block';
    bar.style.width = '0%';
    txt.textContent = 'Running…';
    counts.textContent = `Done: 0 • Failed: 0 • Skipped: 0 • Remaining: ${j.total}`;
    runLoop();
  });

  btnReset?.addEventListener('click', function(){
    elEmails.value = '';
    elAll.checked = false;
    elRfo.checked = false;
    setMsg('');
    wrap.style.display = 'none';
    bar.style.width = '0%';
    running = false;
    jobId = null;
  });

  loadProfiles();
})();
</script>

<?php } // end bulk delete ?>