<?php
// META: {"title": "Reseller Management", "order": 20, "nav": true, "hidden": false}
$resellers = get_resellers($pdo);
$consoles  = get_consoles($pdo);
?>

<style>
  .action-icons{display:flex;gap:10px;align-items:center;justify-content:flex-start}
  .icon-btn{
    width:54px;height:54px;border-radius:14px;
    border:1px solid rgba(255,255,255,.08);
    background:rgba(255,255,255,.04);
    display:flex;align-items:center;justify-content:center;
    cursor:pointer;position:relative;text-decoration:none;
    transition:transform .08s ease, background .15s ease, border-color .15s ease;
  }
  .icon-btn:hover{transform:translateY(-1px);background:rgba(255,255,255,.06);border-color:rgba(255,255,255,.16)}
  .icon-btn.danger{background:rgba(255,80,80,.92);border-color:rgba(255,80,80,.2)}
  .icon-btn.danger:hover{background:rgba(255,80,80,1)}
  .icon-btn.warn{background:rgba(246,193,119,.16);border-color:rgba(246,193,119,.28)}
  .icon-btn.warn:hover{background:rgba(246,193,119,.22)}
  .icon-btn.ok{background:rgba(74,222,128,.14);border-color:rgba(74,222,128,.26)}
  .icon-btn.ok:hover{background:rgba(74,222,128,.20)}
  .icon-btn svg{width:22px;height:22px;fill:#fff;opacity:.96}

  .icon-btn[data-tip]:hover:after{
    content:attr(data-tip);
    position:absolute;top:100%;left:0;margin-top:8px;
    background:rgba(0,0,0,.72);color:#fff;font-size:13px;
    padding:6px 10px;border-radius:10px;white-space:nowrap;z-index:50;
  }

  /* Team modal */
  .modal-backdrop{
    position:fixed;inset:0;background:rgba(0,0,0,.55);
    display:none;align-items:center;justify-content:center;
    z-index:9999;padding:16px;
  }
  .modal-backdrop.show{display:flex}
  .modal-card{
    width:min(720px,100%);
    background:#141c2a;
    border:1px solid rgba(255,255,255,.08);
    border-radius:18px;
    overflow:hidden;
  }
  .modal-head{
    display:flex;align-items:center;justify-content:space-between;
    padding:16px 18px;border-bottom:1px solid rgba(255,255,255,.08);
  }
  .modal-title{font-size:18px;font-weight:900;color:#fff}
  .modal-close{
    width:40px;height:40px;border-radius:12px;
    border:1px solid rgba(255,255,255,.08);
    background:rgba(255,255,255,.04);
    color:#fff;cursor:pointer;
  }
  .modal-body{padding:16px 18px}
  .modal-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
  .modal-grid label{display:block;font-size:13px;opacity:.85;margin-bottom:6px}
  .modal-grid select{width:100%;padding:10px 10px;border-radius:12px}
  .modal-foot{
    padding:14px 18px;border-top:1px solid rgba(255,255,255,.08);
    display:flex;justify-content:flex-end;gap:10px
  }
  @media (max-width:640px){
    .icon-btn{width:48px;height:48px}
    .modal-grid{grid-template-columns:1fr}
  }
</style>

<div class="card">
  <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap">
    <div>
      <h3 style="margin-bottom:4px">All Resellers</h3>
      <div class="muted small">Manage resellers, access, teams and billing at a glance.</div>
    </div>
    <div class="inline" style="justify-content:flex-end">
      <a class="btn" href="?page=login" title="Open the reseller login page in a new tab" target="_blank" rel="noopener">Go to Reseller Portal</a>
      <button class="btn btn-accent" type="button" onclick="openAddResellerModal()">+ Add Reseller</button>
    </div>
  </div>

  <div style="margin-top:14px;display:flex;gap:12px;flex-wrap:wrap;align-items:center">
    <div style="flex:1;min-width:260px">
      <input id="resellerSearch" placeholder="Search resellers..." autocomplete="off" />
    </div>
  </div>

  <div style="margin-top:14px">
    <table id="resellersTable">
      <thead>
        <tr>
          <th>Group</th>
          <th>Monthly Rate</th>
          <th>Organization</th>
          <th>Balance</th>
          <th>Active Users</th>
          <th>Status</th>
          <th style="width:240px">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($resellers as $r): ?>
          <?php
            $assigned = array_pad(get_reseller_console_ids($pdo, (int)$r['id']), 5, 0);
            $assignedCsv = implode(',', array_map('intval', $assigned));
            $status = $r['status'] ?? 'active';
            $badge_class = $status === 'active' ? 'b-success' : 'b-danger';
            $status_text = $status === 'active' ? 'Active' : 'Suspended';
            $org = trim((string)($r['company_name'] ?? ''));
            if ($org === '') $org = (string)$r['username'];
          ?>
          <tr data-search="<?= e(strtolower(($r['username'] ?? '') . ' ' . ($r['email'] ?? '') . ' ' . ($r['company_name'] ?? ''))) ?>">
            <td><?= e($r['username']) ?></td>
            <td>$<?= money_fmt($r['monthly_rate']) ?></td>
            <td><?= e($org) ?></td>
            <td>
              <?php if ((float)$r['balance'] < 0): ?>
                <span class='badge b-danger'>$<?= money_fmt($r['balance']) ?></span>
              <?php else: ?>
                <span class='badge b-success'>$<?= money_fmt($r['balance']) ?></span>
              <?php endif; ?>
            </td>
            <td><?= (int)$r['user_count'] ?></td>
            <td><span class="badge <?= $badge_class ?>"><?= $status_text ?></span></td>
            <td>
              <div class="action-icons">
                <!-- Org Access -->
                <a class="icon-btn" data-tip="Manage Org Access"
                   href="?page=admin_edit_reseller&id=<?= (int)$r['id'] ?>#orgaccess">
                  <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M3 21V3h10v18H3zm2-2h6v-2H5v2zm0-4h6v-2H5v2zm0-4h6V9H5v2zm0-4h6V5H5v2zM15 21V7h6v14h-6zm2-2h2v-2h-2v2zm0-4h2v-2h-2v2zm0-4h2V9h-2v2z"/>
                  </svg>
                </a>

                <!-- Teams -->
                <button type="button" class="icon-btn" data-tip="Manage Teams"
                  onclick="openConsoleModal(<?= (int)$r['id'] ?>, '<?= e($r['username']) ?>', '<?= e($assignedCsv) ?>')">
                  <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zM8 11c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5C15 14.17 10.33 13 8 13zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
                  </svg>
                </button>

                <!-- Edit -->
                <a class="icon-btn" data-tip="Edit"
                   href="?page=admin_edit_reseller&id=<?= (int)$r['id'] ?>">
                  <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M3 17.25V21h3.75L17.8 9.95l-3.75-3.75L3 17.25zm2.92 2.33H5v-.92l8.06-8.06.92.92L5.92 19.58zM20.7 7.04a1 1 0 0 0 0-1.41L18.37 3.3a1 1 0 0 0-1.41 0l-1.56 1.56 3.75 3.75 1.55-1.57z"/>
                  </svg>
                </a>

                <!-- Delete -->
                <form method="post" style="display:inline" onsubmit="return confirm('Delete this reseller?');">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="admin_delete_reseller">
                  <input type="hidden" name="reseller_id" value="<?= (int)$r['id'] ?>">
                  <button class="icon-btn danger" data-tip="Delete" type="submit">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                      <path d="M6 7h12l-1 14H7L6 7zm3-3h6l1 2H8l1-2zM9 10h2v9H9v-9zm4 0h2v9h-2v-9z"/>
                    </svg>
                  </button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Reseller Modal -->
<div class="modal-backdrop" id="addResellerModal" aria-hidden="true">
  <div class="modal-card" role="dialog" aria-modal="true">
    <div class="modal-head">
      <div class="modal-title">Add Reseller</div>
      <button class="modal-close" type="button" onclick="closeAddResellerModal()">✕</button>
    </div>
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="admin_add_reseller">
      <div class="modal-body">
        <div class="modal-grid">
          <div>
            <label>Username *</label>
            <input name="username" required />
          </div>
          <div>
            <label>Email *</label>
            <input name="email" type="email" required />
          </div>
          <div>
            <label>Password *</label>
            <input name="password" type="password" required />
          </div>
          <div>
            <label>Monthly Rate</label>
            <input name="monthly_rate" type="number" step="0.01" value="1.00" />
          </div>
          <div style="grid-column:1/-1">
            <label>Company Name</label>
            <input name="company_name" />
          </div>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn" onclick="closeAddResellerModal()">Cancel</button>
        <button type="submit" class="btn btn-accent">Create Reseller</button>
      </div>
    </form>
  </div>
</div>

<!-- Teams / Consoles Popup -->
<div class="modal-backdrop" id="consoleModal" aria-hidden="true">
  <div class="modal-card" role="dialog" aria-modal="true">
    <div class="modal-head">
      <div class="modal-title" id="consoleModalTitle">Manage Teams</div>
      <button class="modal-close" type="button" onclick="closeConsoleModal()">✕</button>
    </div>

    <form method="post" id="consoleModalForm">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="admin_assign_consoles">
      <input type="hidden" name="reseller_id" id="modal_reseller_id" value="">

      <div class="modal-body">
        <div class="modal-grid">
          <?php for($i=1;$i<=5;$i++): ?>
            <div>
              <label>Priority <?= $i ?></label>
              <select name="console_<?= $i ?>" id="modal_console_<?= $i ?>">
                <option value="">— Select Team —</option>
                <?php foreach ($consoles as $c): ?>
                  <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endfor; ?>
        </div>
      </div>

      <div class="modal-foot">
        <button type="button" class="btn" onclick="closeConsoleModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
  // Add reseller modal
  function openAddResellerModal(){
    var m = document.getElementById('addResellerModal');
    if(m) m.classList.add('show');
  }
  function closeAddResellerModal(){
    var m = document.getElementById('addResellerModal');
    if(m) m.classList.remove('show');
  }

  function openConsoleModal(resellerId, resellerName, assignedCsv){
    var title = document.getElementById('consoleModalTitle');
    if(title) title.textContent = 'Manage Teams: ' + resellerName;

    var rid = document.getElementById('modal_reseller_id');
    if(rid) rid.value = resellerId;

    var parts = (assignedCsv || '').split(',').map(function(x){ return (x||'').trim(); });
    for(var i=1;i<=5;i++){
      var sel = document.getElementById('modal_console_'+i);
      if(!sel) continue;
      sel.value = parts[i-1] ? parts[i-1] : '';
    }

    var m = document.getElementById('consoleModal');
    if(m) m.classList.add('show');
  }

  function closeConsoleModal(){
    var m = document.getElementById('consoleModal');
    if(m) m.classList.remove('show');
  }

  // click outside closes
  document.addEventListener('click', function(e){
    var cm = document.getElementById('consoleModal');
    if(cm && e.target === cm) closeConsoleModal();
    var am = document.getElementById('addResellerModal');
    if(am && e.target === am) closeAddResellerModal();
  });

  // ESC closes
  document.addEventListener('keydown', function(e){
    if(e.key !== 'Escape') return;
    closeConsoleModal();
    closeAddResellerModal();
  });

  // Search filter
  (function(){
    var input = document.getElementById('resellerSearch');
    var table = document.getElementById('resellersTable');
    if(!input || !table) return;

    function filter(){
      var q = (input.value || '').toLowerCase().trim();
      var rows = table.querySelectorAll('tbody tr');
      rows.forEach(function(tr){
        var hay = (tr.getAttribute('data-search') || '').toLowerCase();
        tr.style.display = (!q || hay.indexOf(q) !== -1) ? '' : 'none';
      });
    }
    input.addEventListener('input', filter);
  })();
</script>
