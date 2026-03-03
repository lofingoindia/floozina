<?php
// META: {"title": "Migration", "order": 80, "nav": true, "hidden": false}
/**
 * admin_migration.php - Auto Migration Tool (UI)
 * Works with existing backend in /app/app.php (AJAX: console_profiles, migration_start, migration_step, migration_cancel, migration_history, migration_csv)
 * Location expected: public_html/pages/admin/admin_migration.php
 */

// If someone opens this file directly, redirect to main router so session/layout works.
if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'admin_migration.php' && empty($_GET['page'])) {
    header('Location: /index.php?page=admin_migration');
    exit;
}

// Guard: only super admin can view (app.php also enforces this on AJAX)
if (!function_exists('is_super_admin') || !is_super_admin()) {
    header('HTTP/1.0 403 Forbidden');
    echo "<div class='card' style='padding:12px'>Access denied.</div>";
    return;
}

// $pdo should exist because app/app.php bootstraps it. But keep a safe fallback.
if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (function_exists('get_db_connection')) {
        $pdo = get_db_connection();
    } else {
        echo "<div class='card' style='padding:12px'>Database connection missing.</div>";
        return;
    }
}

// Consoles for dropdown
$consoles = $pdo->query("
    SELECT ac.*,
        (SELECT COUNT(*) FROM users u WHERE u.console_id = ac.id) AS users_count,
        (SELECT COUNT(DISTINCT rc.reseller_id) FROM reseller_consoles rc WHERE rc.console_id = ac.id) AS resellers_count
    FROM admin_consoles ac
    ORDER BY ac.name ASC
")->fetchAll() ?: [];
?>

<div class="content-wrapper">
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">⚡ Auto Migration (Turbo UI)</h3>
                <div class="muted small">Move system users from one console to another. Backend runs via app.php AJAX.</div>
            </div>
        </div>

        <div class="card-body">
            <form id="amForm" onsubmit="return false;">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

                <div class="grid" style="grid-template-columns:1fr 1fr;gap:12px">
                    <div>
                        <label>🔴 Source Console</label>
                        <select name="from_console_id" id="amFrom" required class="form-control">
                            <option value="">Select source console</option>
                            <?php foreach ($consoles as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" data-users="<?= (int)$c['users_count'] ?>">
                                    #<?= (int)$c['id'] ?> — <?= e((string)$c['name']) ?>
                                    (<?= e((string)($c['status'] ?? 'active')) ?>, users: <?= (int)$c['users_count'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="muted small" style="margin-top:6px">
                            <span id="sourceUserCount">0</span> users will be migrated
                        </div>
                    </div>

                    <div>
                        <label>🟢 Target Console</label>
                        <select name="to_console_id" id="amTo" required class="form-control">
                            <option value="">Select target console</option>
                            <?php foreach ($consoles as $c): ?>
                                <?php if (function_exists('console_is_usable') ? console_is_usable($c) : true): ?>
                                    <option value="<?= (int)$c['id'] ?>">
                                        #<?= (int)$c['id'] ?> — <?= e((string)$c['name']) ?> (active)
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                        <div class="muted small" style="margin-top:6px">Only usable consoles appear here.</div>
                    </div>
                </div>

                <div class="grid" style="grid-template-columns:1fr 1fr;gap:12px;margin-top:12px">
                    <div>
                        <label>🎯 Default Target Product Profile</label>
                        <select name="target_profile" id="amDefaultProfile" required class="form-control">
                            <option value="">Select a target product profile</option>
                        </select>
                        <div class="muted small" style="margin-top:6px">Used when no mapping matches.</div>
                    </div>

                    <div>
                        <label>⚙️ Options</label>
                        <div style="margin-top:8px">
                            <label class="d-flex items-center gap-2">
                                <input type="checkbox" id="amRemoveFromSource" name="remove_from_source" value="1" checked>
                                <span>Remove from source org after migration</span>
                            </label>
                        </div>
                        <div class="muted small" style="margin-top:6px">(Backend decides actual processing.)</div>
                    </div>
                </div>

                <div class="card" style="margin-top:14px">
                    <div class="card-header" style="padding:12px 12px">
                        <div>
                            <div style="font-weight:800">🔄 Product Profile Mapping (optional)</div>
                            <div class="muted small">Map specific source profiles to different target profiles.</div>
                        </div>
                        <div class="d-flex gap-2" style="flex-wrap:wrap">
                            <button class="btn btn-primary btn-sm" type="button" id="amAddMap">+ Add mapping</button>
                            <button class="btn btn-warning btn-sm" type="button" id="amClearMap">Clear</button>
                            <button class="btn btn-info btn-sm" type="button" id="amAutoMap">Auto-map</button>
                        </div>
                    </div>

                    <div class="card-body" style="padding:12px 12px">
                        <div class="table-responsive">
                            <table class="data-table" style="min-width:680px">
                                <thead>
                                    <tr>
                                        <th>Source Profile</th>
                                        <th>Target Profile</th>
                                        <th style="width:90px">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="amMapTbody">
                                    <tr><td colspan="3" class="muted small text-center">No mappings yet. Click “Add mapping”.</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <input type="hidden" name="profile_map_json" id="amProfileMapJson" value="">
                        <div class="muted small" style="margin-top:8px">Tip: First fetch profiles in Consoles, then map here.</div>
                    </div>
                </div>

                <div class="d-flex gap-2 items-center" style="margin-top:14px;flex-wrap:wrap">
                    <button class="btn btn-success btn-lg" type="button" id="amStart" style="min-width:200px">Start Migration</button>
                    <button class="btn btn-danger" type="button" id="amCancel" style="display:none">Cancel</button>
                    <span class="muted small" id="amHint"></span>
                </div>

                <div class="card" id="amProgressCard" style="margin-top:14px;display:none">
                    <div class="card-header">
                        <div class="d-flex justify-between items-center" style="gap:10px;flex-wrap:wrap">
                            <div>
                                <div style="font-weight:800; font-size:1.1rem">📊 Migration Progress</div>
                                <div class="muted small" id="amMeta">—</div>
                            </div>
                            <span class="badge b-info" id="amStatus" style="font-size:1rem">waiting</span>
                        </div>
                    </div>

                    <div class="card-body">
                        <div style="margin-top:10px">
                            <div style="height:20px;background:rgba(255,255,255,.08);border-radius:999px;overflow:hidden">
                                <div id="amBar" style="height:20px;width:0%;background:linear-gradient(90deg,#4CAF50,#8BC34A);transition:width 0.3s ease"></div>
                            </div>

                            <div class="grid" style="grid-template-columns:repeat(6,1fr);gap:10px;margin-top:15px">
                                <div class="stat-box"><div class="stat-label">✅ Done</div><div class="stat-value" id="amDone">0</div></div>
                                <div class="stat-box"><div class="stat-label">⏳ Remaining</div><div class="stat-value" id="amRemain">0</div></div>
                                <div class="stat-box"><div class="stat-label">❌ Failed</div><div class="stat-value" id="amFailed">0</div></div>
                                <div class="stat-box"><div class="stat-label">⚡ Speed</div><div class="stat-value" id="amRate">0</div><div class="stat-unit">/min</div></div>
                                <div class="stat-box"><div class="stat-label">⏱️ ETA</div><div class="stat-value" id="amEta">—</div></div>
                                <div class="stat-box"><div class="stat-label">📈 Progress</div><div class="stat-value" id="amPct">0%</div></div>
                            </div>
                        </div>

                        <div class="card" style="margin-top:15px; background:#1a1a1a;">
                            <div class="card-header" style="padding:8px">
                                <span style="font-weight:800">📝 Live Log</span>
                                <span class="muted small">(last 50)</span>
                            </div>
                            <div class="card-body" style="padding:8px; max-height:150px; overflow-y:auto; font-family:monospace; font-size:12px;" id="amLog">
                                <div class="muted small">Ready…</div>
                            </div>
                        </div>

                        <div id="amWarn" class="alert alert-warning" style="display:none;margin-top:10px;white-space:pre-wrap"></div>
                        <div id="amErr" class="alert alert-danger" style="display:none;margin-top:10px;white-space:pre-wrap"></div>
                    </div>
                </div>

                <div class="card" id="amHistoryCard" style="margin-top:20px">
                    <div class="card-header" style="padding:12px 12px">
                        <div>
                            <div style="font-weight:800">📜 Migration History</div>
                            <div class="muted small">Last 50 jobs + CSV export</div>
                        </div>
                        <div class="d-flex gap-2" style="flex-wrap:wrap">
                            <button class="btn btn-info btn-sm" type="button" id="amHistRefresh">Refresh</button>
                        </div>
                    </div>

                    <div class="card-body" style="padding:12px">
                        <div class="table-responsive">
                            <table class="data-table" style="min-width:900px" id="historyTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Date</th>
                                        <th>From</th>
                                        <th>To</th>
                                        <th>Progress</th>
                                        <th>Failed</th>
                                        <th>Status</th>
                                        <th>CSV</th>
                                    </tr>
                                </thead>
                                <tbody id="amHistTbody">
                                    <tr><td colspan="8" class="muted small text-center">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </form>
        </div>
    </div>
</div>

<style>
.stat-box { background: rgba(255,255,255,0.05); border-radius: 8px; padding: 10px; text-align: center; border: 1px solid rgba(255,255,255,0.1); }
.stat-label { font-size: 12px; color: #aaa; margin-bottom: 5px; }
.stat-value { font-size: 24px; font-weight: bold; color: #fff; line-height: 1.2; }
.stat-unit { font-size: 10px; color: #666; margin-top: 2px; }
.alert { padding: 12px; border-radius: 6px; margin-top: 10px; }
.alert-warning { background: rgba(255,193,7,0.2); border: 1px solid #ffc107; color: #ffc107; }
.alert-danger { background: rgba(220,53,69,0.2); border: 1px solid #dc3545; color: #dc3545; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { text-align: left; padding: 10px; background: rgba(255,255,255,0.05); font-weight: 600; font-size: 13px; }
.data-table td { padding: 10px; border-bottom: 1px solid rgba(255,255,255,0.1); }
.badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
.b-success { background: #28a745; color: #fff; }
.b-info { background: #17a2b8; color: #fff; }
.b-warning { background: #ffc107; color: #000; }
.b-danger { background: #dc3545; color: #fff; }
.btn-sm { padding: 4px 8px; font-size: 12px; }
.btn-lg { padding: 12px 24px; font-size: 16px; }
#amLog { scroll-behavior: smooth; }
#amLog div { padding: 2px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
</style>

<script>
(function () {
  'use strict';

  const form = document.getElementById('amForm');
  if (!form) return;

  const DOM = {
    csrf: document.querySelector('#amForm input[name="csrf"]').value,
    fromSel: document.getElementById('amFrom'),
    toSel: document.getElementById('amTo'),
    defaultProfileSel: document.getElementById('amDefaultProfile'),
    removeFromSource: document.getElementById('amRemoveFromSource'),

    addMapBtn: document.getElementById('amAddMap'),
    clearMapBtn: document.getElementById('amClearMap'),
    autoMapBtn: document.getElementById('amAutoMap'),
    tbody: document.getElementById('amMapTbody'),
    mapJsonEl: document.getElementById('amProfileMapJson'),

    startBtn: document.getElementById('amStart'),
    cancelBtn: document.getElementById('amCancel'),

    hint: document.getElementById('amHint'),

    progressCard: document.getElementById('amProgressCard'),
    statusEl: document.getElementById('amStatus'),
    metaEl: document.getElementById('amMeta'),
    barEl: document.getElementById('amBar'),
    doneEl: document.getElementById('amDone'),
    remainEl: document.getElementById('amRemain'),
    failedEl: document.getElementById('amFailed'),
    rateEl: document.getElementById('amRate'),
    etaEl: document.getElementById('amEta'),
    pctEl: document.getElementById('amPct'),
    warnEl: document.getElementById('amWarn'),
    errEl: document.getElementById('amErr'),
    logEl: document.getElementById('amLog'),

    histTbody: document.getElementById('amHistTbody'),
    histRefreshBtn: document.getElementById('amHistRefresh'),

    sourceUserCount: document.getElementById('sourceUserCount')
  };

  let jobId = null;
  let timer = null;
  let startedAt = null;
  let lastTick = null;
  let lastProcessed = 0;
  let sourceProfiles = [];
  let targetProfiles = [];
  let speedHistory = [];
  let logEntries = [];

  const CONFIG = {
    maxLogEntries: 50,
    speedHistorySize: 10,
    pollIntervals: { fast: 700, medium: 1200, slow: 2000 }
  };

  function setHint(text) { DOM.hint.textContent = text || ''; }

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = String(text ?? '');
    return div.innerHTML;
  }

  function addLog(message) {
    const ts = new Date().toLocaleTimeString();
    logEntries.unshift(`[${ts}] ${message}`);
    if (logEntries.length > CONFIG.maxLogEntries) logEntries.pop();
    DOM.logEl.innerHTML = logEntries.map(e => `<div class="muted small">${escapeHtml(e)}</div>`).join('');
  }

  function formatNumber(num) { return new Intl.NumberFormat().format(num || 0); }

  function updateSourceUserCount() {
    const opt = DOM.fromSel.options[DOM.fromSel.selectedIndex];
    const n = opt && opt.dataset && opt.dataset.users ? parseInt(opt.dataset.users, 10) : 0;
    DOM.sourceUserCount.textContent = formatNumber(n);
  }

  async function fetchProfiles(consoleId) {
    const res = await fetch(`?ajax=console_profiles&console_id=${encodeURIComponent(consoleId)}`, { credentials: 'same-origin' });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || 'Failed to load profiles');
    return Array.isArray(data.profiles) ? data.profiles : [];
  }

  function fillSelect(select, profiles, selectedValue = '') {
    const keep = selectedValue || select.value;
    select.innerHTML = '<option value="">Select profile</option>';

    profiles.forEach(p => {
      const name = p.groupName || p.name || '';
      if (!name) return;
      const opt = document.createElement('option');
      opt.value = name;
      opt.textContent = name;
      if (p.count) opt.textContent += ` (${p.count} users)`;
      select.appendChild(opt);
    });

    if (keep) select.value = keep;
  }

  async function refreshProfileLists() {
    const toId = DOM.toSel.value;
    const fromId = DOM.fromSel.value;

    DOM.warnEl.style.display = 'none';
    DOM.errEl.style.display = 'none';

    try {
      if (toId) {
        targetProfiles = await fetchProfiles(toId);
        fillSelect(DOM.defaultProfileSel, targetProfiles);
        document.querySelectorAll('.amTargetProfile').forEach(sel => fillSelect(sel, targetProfiles));
      } else {
        targetProfiles = [];
        fillSelect(DOM.defaultProfileSel, []);
      }

      if (fromId) {
        sourceProfiles = await fetchProfiles(fromId);
        document.querySelectorAll('.amSourceProfile').forEach(sel => fillSelect(sel, sourceProfiles));
      } else {
        sourceProfiles = [];
      }
    } catch (e) {
      setHint('Profiles load nahi ho rahe. Pehle consoles me profiles fetch/save kar lo.');
      addLog(`Profiles error: ${e.message}`);
    }
  }

  function serializeMap() {
    const map = {};
    document.querySelectorAll('#amMapTbody tr[data-row="1"]').forEach(tr => {
      const s = tr.querySelector('select.amSourceProfile')?.value;
      const t = tr.querySelector('select.amTargetProfile')?.value;
      if (s && t) map[s] = t;
    });
    DOM.mapJsonEl.value = JSON.stringify(map);
    return map;
  }

  function addRow(source = '', target = '') {
    if (!DOM.tbody.querySelector('tr[data-row="1"]')) DOM.tbody.innerHTML = '';

    const tr = document.createElement('tr');
    tr.setAttribute('data-row', '1');
    tr.innerHTML = `
      <td><select class="amSourceProfile form-control" style="min-width:260px"></select></td>
      <td><select class="amTargetProfile form-control" style="min-width:260px"></select></td>
      <td><button type="button" class="btn btn-danger btn-sm amRemoveRow">×</button></td>
    `;

    DOM.tbody.appendChild(tr);

    const sSel = tr.querySelector('.amSourceProfile');
    const tSel = tr.querySelector('.amTargetProfile');

    fillSelect(sSel, sourceProfiles, source);
    fillSelect(tSel, targetProfiles, target);

    tr.querySelector('.amRemoveRow').addEventListener('click', () => {
      tr.remove();
      if (!DOM.tbody.querySelector('tr[data-row="1"]')) {
        DOM.tbody.innerHTML = '<tr><td colspan="3" class="muted small text-center">No mappings yet.</td></tr>';
      }
      serializeMap();
    });

    sSel.addEventListener('change', serializeMap);
    tSel.addEventListener('change', serializeMap);
    serializeMap();
  }

  function autoMapProfiles() {
    let cnt = 0;
    sourceProfiles.forEach(s => {
      const sName = (s.groupName || s.name || '').trim();
      if (!sName) return;
      const match = targetProfiles.find(t => {
        const tName = (t.groupName || t.name || '').trim();
        if (!tName) return false;
        const a = sName.toLowerCase();
        const b = tName.toLowerCase();
        return a === b || a.includes(b) || b.includes(a);
      });
      if (match) {
        const tName = (match.groupName || match.name || '').trim();
        addRow(sName, tName);
        cnt++;
      }
    });
    addLog(cnt ? `Auto-mapped ${cnt} profiles` : 'Auto-map: koi match nahi mila');
  }

  function updateProgressUI(processed, total, failed, status, meta, error) {
    DOM.progressCard.style.display = 'block';
    DOM.statusEl.textContent = status || 'running';
    DOM.metaEl.textContent = meta || '';

    const pct = total > 0 ? Math.round((processed / total) * 100) : 0;
    DOM.barEl.style.width = pct + '%';
    DOM.doneEl.textContent = formatNumber(processed);
    DOM.remainEl.textContent = formatNumber(Math.max(0, total - processed));
    DOM.failedEl.textContent = formatNumber(failed || 0);
    DOM.pctEl.textContent = pct + '%';

    const now = Date.now();
    if (startedAt === null) startedAt = now;

    if (lastTick !== null) {
      const minDiff = (now - lastTick) / 1000 / 60;
      const procDiff = processed - lastProcessed;
      if (minDiff > 0) {
        const speed = Math.max(0, Math.round(procDiff / minDiff));
        DOM.rateEl.textContent = formatNumber(speed);

        speedHistory.push(speed);
        if (speedHistory.length > CONFIG.speedHistorySize) speedHistory.shift();

        const avg = speedHistory.reduce((a, b) => a + b, 0) / speedHistory.length;
        const remaining = Math.max(0, total - processed);
        if (avg > 0) {
          const etaMin = remaining / avg;
          if (etaMin < 1) DOM.etaEl.textContent = Math.round(etaMin * 60) + 's';
          else if (etaMin < 60) DOM.etaEl.textContent = Math.round(etaMin) + 'm';
          else DOM.etaEl.textContent = Math.floor(etaMin / 60) + 'h ' + Math.round(etaMin % 60) + 'm';
        }
      }
    }

    lastTick = now;
    lastProcessed = processed;

    if (error) {
      DOM.errEl.style.display = 'block';
      DOM.errEl.textContent = error;
    } else {
      DOM.errEl.style.display = 'none';
    }
  }

  function getAdaptivePollInterval(processed) {
    if (processed < 100) return CONFIG.pollIntervals.fast;
    if (processed < 500) return CONFIG.pollIntervals.medium;
    return CONFIG.pollIntervals.slow;
  }

  async function startMigration() {
    const fromId = DOM.fromSel.value;
    const toId = DOM.toSel.value;
    const defaultProfile = DOM.defaultProfileSel.value;

    if (!fromId || !toId) return alert('Source aur Target consoles select karo');
    if (fromId === toId) return alert('Source aur Target same nahi ho sakte');
    if (!defaultProfile) return alert('Default target profile select karo');

    const opt = DOM.fromSel.options[DOM.fromSel.selectedIndex];
    const userCount = opt && opt.dataset && opt.dataset.users ? parseInt(opt.dataset.users, 10) : 0;

    if (!confirm(`Migrate ${formatNumber(userCount)} users?\n\nYe operation undo nahi hoga.`)) return;

    DOM.startBtn.disabled = true;
    DOM.cancelBtn.style.display = 'inline-flex';
    setHint('Migration start ho rahi hai…');
    addLog('Starting migration…');

    const fd = new FormData();
    fd.append('csrf', DOM.csrf);
    fd.append('from_console_id', fromId);
    fd.append('to_console_id', toId);
    fd.append('target_profile', defaultProfile);
    if (DOM.removeFromSource.checked) fd.append('remove_from_source', '1');
    fd.append('profile_map_json', JSON.stringify(serializeMap()));

    try {
      const res = await fetch('?ajax=migration_start', { method: 'POST', body: fd, credentials: 'same-origin' });
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Failed to start');

      jobId = data.job_id;
      startedAt = null;
      lastTick = null;
      lastProcessed = 0;
      speedHistory = [];

      updateProgressUI(0, data.total_users || 0, 0, 'running', `Job #${jobId}`, null);
      addLog(`Job #${jobId} started (${data.total_users || 0} users)`);
      tick();
    } catch (e) {
      DOM.startBtn.disabled = false;
      DOM.cancelBtn.style.display = 'none';
      alert(e.message);
      addLog(`Start error: ${e.message}`);
    }
  }

  async function tick() {
    if (!jobId) return;

    const fd = new FormData();
    fd.append('csrf', DOM.csrf);
    fd.append('job_id', jobId);

    try {
      const res = await fetch('?ajax=migration_step', { method: 'POST', body: fd, credentials: 'same-origin' });
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Step failed');

      const processed = data.processed || 0;
      const total = data.total || 0;
      const failed = data.failed || 0;
      const status = data.status || 'running';

      updateProgressUI(processed, total, failed, status, `Job #${jobId} • ${formatNumber(processed)}/${formatNumber(total)}`, data.error || null);

      if (status === 'running') {
        timer = setTimeout(tick, getAdaptivePollInterval(processed));
      } else {
        DOM.startBtn.disabled = false;
        DOM.cancelBtn.style.display = 'none';
        setHint(status === 'done' ? '✅ Migration complete' : `⚠️ Migration ended: ${status}`);
        addLog(status === 'done' ? 'Migration completed' : `Migration ended: ${status}`);
        loadHistory();
      }

    } catch (e) {
      DOM.startBtn.disabled = false;
      DOM.cancelBtn.style.display = 'none';
      updateProgressUI(0, 0, 0, 'failed', '—', e.message);
      addLog(`Tick error: ${e.message}`);
    }
  }

  async function cancelMigration() {
    if (!jobId) return;
    if (!confirm('Ongoing migration cancel karni hai?')) return;

    const fd = new FormData();
    fd.append('csrf', DOM.csrf);
    fd.append('job_id', jobId);

    try {
      const res = await fetch('?ajax=migration_cancel', { method: 'POST', body: fd, credentials: 'same-origin' });
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Cancel failed');

      if (timer) clearTimeout(timer);
      setHint('Migration cancelled.');
      DOM.statusEl.textContent = 'canceled';
      DOM.cancelBtn.style.display = 'none';
      DOM.startBtn.disabled = false;
      addLog('Cancelled by user');
      loadHistory();
    } catch (e) {
      alert(e.message);
    }
  }

  async function loadHistory() {
    if (!DOM.histTbody) return;
    DOM.histTbody.innerHTML = '<tr><td colspan="8" class="muted small text-center">Loading…</td></tr>';

    try {
      const res = await fetch('?ajax=migration_history', { credentials: 'same-origin' });
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Failed');

      const rows = Array.isArray(data.rows) ? data.rows : [];
      if (!rows.length) {
        DOM.histTbody.innerHTML = '<tr><td colspan="8" class="muted small text-center">No history yet.</td></tr>';
        return;
      }

      DOM.histTbody.innerHTML = rows.map(r => {
        const total = parseInt(r.total_users || 0, 10);
        const processed = parseInt(r.processed || 0, 10);
        const failed = parseInt(r.failed || 0, 10);
        const pct = total > 0 ? Math.round((processed / total) * 100) : 0;

        const status = String(r.status || '');
        const statusClass = (status === 'done') ? 'b-success' : (status === 'running') ? 'b-info' : (status === 'canceled') ? 'b-warning' : 'b-danger';

        return `
          <tr>
            <td>#${escapeHtml(r.id)}</td>
            <td>${escapeHtml(r.created_at || '')}</td>
            <td>${escapeHtml(r.from_name || ('#' + r.from_console_id))}</td>
            <td>${escapeHtml(r.to_name || ('#' + r.to_console_id))}</td>
            <td>${formatNumber(processed)}/${formatNumber(total)} (${pct}%)</td>
            <td><span class="badge ${failed > 0 ? 'b-danger' : 'b-success'}">${formatNumber(failed)}</span></td>
            <td><span class="badge ${statusClass}">${escapeHtml(status)}</span></td>
            <td><a class="btn btn-info btn-sm" href="?ajax=migration_csv&job_id=${encodeURIComponent(r.id)}" title="Download CSV">CSV</a></td>
          </tr>
        `;
      }).join('');

    } catch (e) {
      DOM.histTbody.innerHTML = '<tr><td colspan="8" class="muted small text-center">Failed to load history.</td></tr>';
      console.error(e);
    }
  }

  DOM.fromSel.addEventListener('change', () => { updateSourceUserCount(); refreshProfileLists(); });
  DOM.toSel.addEventListener('change', refreshProfileLists);

  DOM.addMapBtn.addEventListener('click', () => addRow());
  DOM.clearMapBtn.addEventListener('click', () => {
    DOM.tbody.innerHTML = '<tr><td colspan="3" class="muted small text-center">No mappings yet.</td></tr>';
    DOM.mapJsonEl.value = '';
    addLog('Mappings cleared');
  });
  DOM.autoMapBtn.addEventListener('click', autoMapProfiles);

  DOM.startBtn.addEventListener('click', startMigration);
  DOM.cancelBtn.addEventListener('click', cancelMigration);

  DOM.histRefreshBtn.addEventListener('click', loadHistory);

  setHint('Source + Target select karo, phir Default profile choose karo.');
  updateSourceUserCount();
  loadHistory();
})();
</script>