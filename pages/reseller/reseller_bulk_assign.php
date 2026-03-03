<?php
// META: {"title": "Bulk Assign", "order": 40, "nav": true, "hidden": false}
// NOTE: session_start() mat lagana (app.php already start karta hoga)

$rid    = (int)($_SESSION['user_id'] ?? 0);
$role   = (string)($_SESSION['role'] ?? '');
$status = (string)($_SESSION['status'] ?? '');

if ($rid <= 0 || $role !== 'reseller') {
    echo "<div class='card' style='padding:12px'>Please login as reseller again.</div>";
    return;
}

// Consoles assigned to reseller
$stmt = $pdo->prepare("SELECT c.id, c.name, c.profiles_json
                       FROM reseller_consoles rc
                       JOIN admin_consoles c ON c.id=rc.console_id
                       WHERE rc.reseller_id=?
                       ORDER BY rc.priority ASC");
$stmt->execute([$rid]);
$consoles = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$consoles || count($consoles) === 0) {
    // Fallback to active_console_id
    $stmt = $pdo->prepare("SELECT active_console_id FROM resellers WHERE id=?");
    $stmt->execute([$rid]);
    $aid = (int)$stmt->fetchColumn();
    if ($aid > 0) {
        $c = get_console($pdo, $aid);
        if ($c) {
            $consoles = [[
                'id' => (int)$c['id'],
                'name' => (string)$c['name'],
                'profiles_json' => (string)($c['profiles_json'] ?? ''),
            ]];
        } else {
            $consoles = [];
        }
    } else {
        $consoles = [];
    }
}

$first_profiles = [];
if ($consoles && count($consoles) > 0 && !empty($consoles[0]['profiles_json'])) {
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
      <textarea id="bulkEmails" rows="10" placeholder="user1@example.com&#10;user2@example.com" <?= ($status === 'suspended') ? 'disabled' : '' ?>></textarea>
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
<div id="bulkProfileWrap" class="prod-grid" data-disabled="<?= ($status === 'suspended' || !$consoles || count($consoles)===0) ? '1' : '0' ?>">
  <?php foreach ($first_profiles as $p):
      $gn = (string)($p['groupName'] ?? '');
      $pn = (string)($p['productName'] ?? '');
      if ($gn==='') continue;
      $label = $pn !== '' ? ($pn . " — " . $gn) : $gn;
  ?>
    <label class="prod-item">
      <input type="checkbox" value="<?= e($gn) ?>" <?= ($status === 'suspended' || !$consoles || count($consoles)===0) ? 'disabled' : '' ?>>
      <span><?= e($label) ?></span>
    </label>
  <?php endforeach; ?>
</div>
<div class="small muted">Tap to select one or multiple product profiles.</div>

      <div class="small muted">Profiles list admin "Fetch Profiles" se aata hai (AJAX se refresh ho sakti hai).</div>

      <div style="margin-top:12px" class="row">
        <div class="col">
          <label>Team (optional)</label>
          <input class="input" id="bulkTeam" placeholder="e.g. Team A / Dept / Group" <?= ($status === 'suspended') ? 'disabled' : '' ?>>
        </div>
        <div class="col">
          <label>Expiry Date</label>
          <input class="input" id="bulkExpiry" type="date" value="<?= date('Y-m-d', strtotime('+1 month')) ?>" <?= ($status === 'suspended') ? 'disabled' : '' ?>>
        </div>
      </div>

      <div id="bulkPreview" class="small muted" style="margin-top:10px"></div>

      <div style="margin-top:14px" class="inline">
        <button class="btn btn-primary" id="bulkStartBtn" type="button" <?= ($status === 'suspended' || !$consoles || count($consoles)===0) ? 'disabled' : '' ?>>Start Bulk Assign</button>
        <button class="btn" id="bulkResetBtn" type="button">Reset</button>
      </div>

      <div id="bulkMsg" class="small" style="margin-top:12px"></div>

      <div id="bulkProgressWrap" style="margin-top:12px; display:none">
        <div class="small muted" id="bulkProgressText">Starting…</div>
        <div class="progress" style="height:10px; margin-top:8px">
          <div class="progress-bar" id="bulkProgressBar" style="width:0%"></div>
        </div>
        <div class="small muted" style="margin-top:6px" id="bulkProgressCounts"></div>
        <div class="small muted" style="margin-top:8px">You can navigate away - processing will continue in the background.</div>
      </div>

    </div>
  </div>
</div>

<script>
(function(){
  const csrf = <?= json_encode(csrf_token()) ?>;

  const elConsole = document.getElementById('bulkConsole');
  const elProfileWrap = document.getElementById('bulkProfileWrap');
  const elEmails  = document.getElementById('bulkEmails');
  const elTeam    = document.getElementById('bulkTeam');
  const elExpiry  = document.getElementById('bulkExpiry');

  const elPreview = document.getElementById('bulkPreview');
  const elMsg     = document.getElementById('bulkMsg');
  const btnStart  = document.getElementById('bulkStartBtn');
  const btnReset  = document.getElementById('bulkResetBtn');

  const wrap   = document.getElementById('bulkProgressWrap');
  const bar    = document.getElementById('bulkProgressBar');
  const txt    = document.getElementById('bulkProgressText');
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
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body: form.toString()
    });
    return await res.json();
  }

  async function loadProfiles(){
    if (!elConsole || !elProfileWrap) return;
    const cid = elConsole.value;
    elProfileWrap.innerHTML = '';
    const j = await post('reseller_ajax_get_profiles', {console_id: cid});
    if (!j || !j.ok){
      elProfileWrap.innerHTML = '';
      setMsg((j && j.error) ? j.error : 'Failed to load profiles', true);
      return;
    }

    const prof = j.profiles || [];
    const disabled = elProfileWrap.getAttribute('data-disabled') === '1';
    for (const p of prof){
      const gn = (p.groupName||'');
      if (!gn) continue;
      const pn = (p.productName||'');
      const label = pn ? (pn + ' — ' + gn) : gn;

      const item = document.createElement('label');
      item.className = 'prod-item';

      const cb = document.createElement('input');
      cb.type = 'checkbox';
      cb.value = gn;
      if (disabled) cb.disabled = true;

      const sp = document.createElement('span');
      sp.textContent = label;

      item.appendChild(cb);
      item.appendChild(sp);
      elProfileWrap.appendChild(item);
    }
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
    return {lines: lines.length, valid};
  }

  function updatePreview(){
    if (!elPreview) return;
    const a = analyzeEmails(elEmails?.value || '');
    const expiry = elExpiry?.value || '';
    elPreview.textContent = `Pasted: ${a.lines} • Unique valid emails: ${a.valid} • Expiry: ${expiry || '—'}`;
  }

  async function runLoop(){
    if (!jobId || !running) return;

    const j = await post('reseller_bulk_run', {job_id: jobId, batch: 20});
    if (!j || !j.ok){
      running = false;
      setMsg((j && j.error) ? j.error : 'Bulk run failed', true);
      btnStart.disabled = false;
      return;
    }

    wrap.style.display = 'block';
    const total = j.total || 0;
    const done = j.done || 0;
    const failed = j.failed || 0;
    const processed = (done + failed);
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

    const emails = (elEmails?.value || '').trim();
    const cid = elConsole?.value || '';
    const selected = Array.from(elProfileWrap?.querySelectorAll('input[type="checkbox"]:checked') || []).map(cb => String(cb.value)).filter(Boolean);

    if (!emails){ setMsg('Please paste emails first.', true); return; }
    if (!cid){ setMsg('Select organization/console.', true); return; }
    if (!selected || selected.length === 0){ setMsg('Select product profile(s).', true); return; }

    btnStart.disabled = true;
    setMsg('Creating job…', false);

    const expires_at = elExpiry ? elExpiry.value : '';
    const team = elTeam ? elTeam.value.trim() : '';

    const j = await post('reseller_bulk_init', {
      emails,
      console_id: cid,
      product_profile: JSON.stringify(selected),
      expires_at,
      team
    });

    if (!j || !j.ok){
      btnStart.disabled = false;
      setMsg((j && j.error) ? j.error : 'Failed to start bulk assign', true);
      return;
    }

    jobId = j.job_id;
    running = true;

    wrap.style.display = 'block';
    bar.style.width = '0%';
    txt.textContent = 'Running…';

    skippedExisting = parseInt(j.skipped_existing || 0, 10) || 0;
    counts.textContent = `Done: 0 • Failed: 0 • Skipped: ${skippedExisting} • Remaining: ${j.total}`;

    let note = `Job started. Total to add: <b>${j.total}</b>`;
    if (j.cost_per_user && j.total_cost){
      note += ` • Cost/user: <b>$${Number(j.cost_per_user).toFixed(2)}</b> • Total cost: <b>$${Number(j.total_cost).toFixed(2)}</b> (Expiry: ${j.expires_at || '—'})`;
    }
    if (skippedExisting) note += ` • Skipped existing: <b>${skippedExisting}</b>`;
    if (j.invalid && j.invalid.length) note += ` • Invalid lines: <b>${j.invalid.length}</b>`;
    setMsg(note, false);

    runLoop();
  });

  btnReset?.addEventListener('click', function(){
    if (elEmails) elEmails.value = '';
    wrap.style.display = 'none';
    bar.style.width = '0%';
    setMsg('', false);
    btnStart.disabled = false;
    running = false;
    jobId = null;
    updatePreview();
  });

  elConsole?.addEventListener('change', loadProfiles);
  if (elConsole && elConsole.value) loadProfiles();
})();
</script>