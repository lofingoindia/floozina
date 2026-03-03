<?php
// META: {"title": "Bulk Delete", "order": 50, "nav": true, "hidden": false}
// NOTE: session_start() mat lagana (app.php already start karta hoga)

$rid    = (int)($_SESSION['user_id'] ?? 0);
$role   = (string)($_SESSION['role'] ?? '');
$status = (string)($_SESSION['status'] ?? '');

if ($rid <= 0 || $role !== 'reseller') {
    echo "<div class='card' style='padding:12px'>Please login as reseller again.</div>";
    return;
}

// Consoles assigned to reseller (same as bulk assign)
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
        if ($c) {
            $consoles = [[
                'id' => (int)$c['id'],
                'name' => (string)$c['name'],
                'profiles_json' => (string)($c['profiles_json'] ?? '')
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
      <textarea id="bulkDelEmails" rows="10" placeholder="(optional) user1@example.com&#10;user2@example.com" <?= ($status==='suspended')?'disabled':'' ?>></textarea>

      <label class="d-flex items-center gap-2" style="margin-top:10px">
        <input type="checkbox" id="bulkDelAll" <?= ($status==='suspended')?'disabled':'' ?>>
        <span class="small muted"><b>Delete ALL</b> my users from selected profile</span>
      </label>
      <div class="small muted">If "Delete ALL" is checked, emails textarea is ignored.</div>
    </div>

    <div class="col">
      <label>Organization / Console *</label>
      <select id="bulkDelConsole" <?= ($status==='suspended' || !$consoles || count($consoles)===0) ? 'disabled' : '' ?>>
        <?php foreach ($consoles as $c): ?>
          <option value="<?= (int)$c['id'] ?>"><?= e((string)$c['name']) ?></option>
        <?php endforeach; ?>
      </select>

      <label style="margin-top:10px">Product Profile *</label>
      <select id="bulkDelProfile" <?= ($status==='suspended' || !$consoles || count($consoles)===0) ? 'disabled' : '' ?>>
        <option value="">Select profile</option>
        <?php foreach ($first_profiles as $p):
          $gn = (string)($p['groupName'] ?? '');
          $pn = (string)($p['productName'] ?? '');
          $label = $gn;
          if ($pn !== '') $label = $pn . " — " . $gn;
        ?>
          <option value="<?= e($gn) ?>"><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>

      <label class="d-flex items-center gap-2" style="margin-top:12px">
        <input type="checkbox" id="bulkDelRemoveFromOrg" <?= ($status==='suspended')?'disabled':'' ?>>
        <span class="small muted">Also remove from org (removeFromOrg)</span>
      </label>
      <div class="small muted">If unchecked, only removes from selected profile.</div>

      <div style="margin-top:14px" class="inline">
        <button class="btn btn-danger" id="bulkDelStartBtn" type="button" <?= ($status==='suspended' || !$consoles || count($consoles)===0) ? 'disabled' : '' ?>>
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
        <div class="small muted" style="margin-top:8px">You can navigate away - processing will continue in the background.</div>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  const csrf = <?= json_encode(csrf_token()) ?>;

  const elConsole = document.getElementById('bulkDelConsole');
  const elProfile = document.getElementById('bulkDelProfile');
  const elEmails  = document.getElementById('bulkDelEmails');
  const elAll     = document.getElementById('bulkDelAll');
  const elRfo     = document.getElementById('bulkDelRemoveFromOrg');
  const elMsg     = document.getElementById('bulkDelMsg');

  const btnStart  = document.getElementById('bulkDelStartBtn');
  const btnReset  = document.getElementById('bulkDelResetBtn');

  const wrap   = document.getElementById('bulkDelProgressWrap');
  const bar    = document.getElementById('bulkDelProgressBar');
  const txt    = document.getElementById('bulkDelProgressText');
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
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body: form.toString()
    });

    return await res.json();
  }

  async function loadProfiles(){
    if (!elConsole || !elProfile) return;
    const cid = elConsole.value;

    elProfile.innerHTML = '<option value="">Loading…</option>';
    const j = await post('reseller_ajax_get_profiles', {console_id: cid});

    if (!j || !j.ok){
      elProfile.innerHTML = '<option value="">Select profile</option>';
      setMsg((j && j.error) ? j.error : 'Failed to load profiles', true);
      return;
    }

    const prof = j.profiles || [];
    let html = '<option value="">Select profile</option>';
    for (const p of prof){
      const gn = (p.groupName||'');
      if (!gn) continue;
      const pn = (p.productName||'');
      const label = pn ? (pn + ' — ' + gn) : gn;
      const safe = label.replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
      html += '<option value="' + gn.replace(/"/g,'&quot;') + '">' + safe + '</option>';
    }
    elProfile.innerHTML = html;
  }

  elConsole?.addEventListener('change', loadProfiles);

  async function runLoop(){
    if (!jobId || !running) return;

    const j = await post('reseller_bulk_delete_run', {job_id: jobId, batch: 20});
    if (!j || !j.ok){
      running = false;
      setMsg((j && j.error) ? j.error : 'Bulk delete failed', true);
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

    const cid = elConsole?.value || '';
    const pg  = elProfile?.value || '';
    const emails = (elEmails?.value || '').trim();
    const delAll = elAll && elAll.checked ? 1 : 0;
    const rfo = elRfo && elRfo.checked ? 1 : 0;

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

    if (!j || !j.ok){
      btnStart.disabled = false;
      setMsg((j && j.error) ? j.error : 'Failed to start bulk delete', true);
      return;
    }

    jobId = j.job_id;
    running = true;

    wrap.style.display = 'block';
    bar.style.width = '0%';
    txt.textContent = 'Running…';
    counts.textContent = `Done: 0 • Failed: 0 • Skipped: 0 • Remaining: ${j.total || 0}`;

    runLoop();
  });

  btnReset?.addEventListener('click', function(){
    if (elEmails) elEmails.value = '';
    if (elAll) elAll.checked = false;
    if (elRfo) elRfo.checked = false;
    setMsg('');
    wrap.style.display = 'none';
    bar.style.width = '0%';
    btnStart.disabled = false;
    running = false;
    jobId = null;
  });

  if (elConsole && elConsole.value) loadProfiles();
})();
</script>