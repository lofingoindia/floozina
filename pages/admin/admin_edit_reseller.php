<?php
// META: {"title": "Edit Reseller", "order": 999, "nav": false, "hidden": true}
$rid = (int)($_GET['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM resellers WHERE id=? AND role='reseller' LIMIT 1");
        $stmt->execute([$rid]);
        $r = $stmt->fetch();
        if (!$r) { echo "<div class='card'><h3>Reseller not found</h3></div>"; }
        else {
            $consoles = get_consoles($pdo);
            $assigned = get_reseller_console_ids($pdo, $rid);
            $assigned = array_pad($assigned, 5, 0);

            $users = $pdo->prepare("SELECT u.*, ac.name AS console_name FROM users u LEFT JOIN admin_consoles ac ON u.console_id=ac.id WHERE u.reseller_id=? ORDER BY u.updated_at DESC LIMIT 200");
            $users->execute([$rid]);
            $users = $users->fetchAll();
        ?>
        <div class="card">
          <div class="d-flex justify-between items-center">
            <div>
              <h3>Edit Reseller</h3>
              <div class="muted">Manage reseller account, consoles, billing, and users.</div>
            </div>
            <a class="btn" href="?page=admin_resellers">← Back</a>
          </div>
        </div>

        <div class="grid-2">
          <div class="card">
            <h3>Account</h3>
            <form method="post">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="admin_update_reseller">
              <input type="hidden" name="reseller_id" value="<?= (int)$r['id'] ?>">

              <div class="row">
                <div class="col"><label>Username</label><input name="username" value="<?= e((string)$r['username']) ?>" required></div>
                <div class="col"><label>Email</label><input name="email" type="email" value="<?= e((string)$r['email']) ?>" required></div>
              </div>
              <div class="row">
                <div class="col"><label>Company</label><input name="company_name" value="<?= e((string)$r['company_name']) ?>"></div>
                <div class="col"><label>Monthly Rate</label><input name="monthly_rate" type="number" step="0.01" value="<?= e((string)$r['monthly_rate']) ?>"></div>
              </div>

              <div class="row">
                <div class="col"><label>Balance</label><input name="balance" type="number" step="0.01" value="<?= e((string)$r['balance']) ?>"></div>
                <div class="col"><label>Total Billed</label><input name="total_billed" type="number" step="0.01" value="<?= e((string)$r['total_billed']) ?>"></div>
              </div>
              <div class="row">
                <div class="col"><label>Total Paid</label><input name="total_paid" type="number" step="0.01" value="<?= e((string)$r['total_paid']) ?>"></div>
                <div class="col"><label>New Password (optional)</label><input name="new_password" type="password" placeholder="Leave blank to keep unchanged"></div>
              </div>

              <div class="row">
                <div class="col">
                  <label>Status</label>
                  <select name="status">
                    <option value="active" <?= ($r['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="suspended" <?= ($r['status'] ?? 'active') === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                  </select>
                </div>
              </div>

              <button class="btn btn-primary" type="submit">Save Changes</button>
            </form>

            <hr style="margin:16px 0;border:0;border-top:1px solid var(--border);opacity:.6">

            <form method="post" onsubmit="return confirm('Delete this reseller? All users/transactions under this reseller will be deleted.');">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="admin_delete_reseller">
              <input type="hidden" name="reseller_id" value="<?= (int)$r['id'] ?>">
              <button class="btn btn-danger" type="submit">Delete Reseller</button>
            </form>
          </div>

          <div class="card">
            <h3>Assigned Admin Consoles (Priority)</h3>
            <div class="muted" style="margin-bottom:10px">If a console goes down/expired, users will be reassigned to the next available console in this order.</div>
            <form method="post">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="admin_assign_consoles">
              <input type="hidden" name="reseller_id" value="<?= (int)$r['id'] ?>">

              
<?php
  // Build assigned console IDs in priority order (max 5)
  $assigned_console_ids = array_values(array_filter(array_map('intval', $assigned)));
  $assigned_console_ids = array_values(array_unique($assigned_console_ids));
  $assigned_console_ids = array_slice($assigned_console_ids, 0, 5);
  $acSet = array_fill_keys(array_map('strval', $assigned_console_ids), true);
?>

<label>Select Consoles (max 5) — Priority order</label>
<div class="org-access-list" id="consolePriorityList" data-max="5">
  <?php foreach ($consoles as $c): 
    $cidv = (int)$c['id'];
    $checked = isset($acSet[(string)$cidv]);
  ?>
    <label class="org-card">
      <div class="org-left">
        <input type="checkbox" class="org-check" value="<?= $cidv ?>" <?= $checked ? 'checked' : '' ?>>
      </div>
      <div class="org-body">
        <div class="org-title"><?= e($c['name']) ?></div>
        <div class="org-sub small muted"><?= e($c['status']) ?> • Console ID: <?= $cidv ?></div>
        <div class="org-chips" data-for="<?= $cidv ?>"></div>
      </div>
    </label>
  <?php endforeach; ?>
</div>
<div class="small muted" style="margin-top:6px">
  Tap checkboxes to select consoles. The order you select becomes priority (#1 first). (Max 5)
</div>

<script>
(function(){
  const wrap = document.getElementById('consolePriorityList');
  if(!wrap) return;
  const max = parseInt(wrap.getAttribute('data-max')||'5',10) || 5;
  const checks = Array.from(wrap.querySelectorAll('input.org-check'));
  let order = checks.filter(c=>c.checked).map(c=>String(c.value));

  function syncHidden(){
    for(let i=1;i<=5;i++){
      const sel = document.getElementById('console_'+i);
      if(!sel) continue;
      sel.value = order[i-1] || '';
    }
    checks.forEach(c=>{
      const holder = wrap.querySelector('.org-chips[data-for="'+c.value+'"]');
      if(!holder) return;
      const idx = order.indexOf(String(c.value));
      holder.innerHTML = idx === -1 ? '' : '<span class="badge b-info">#'+(idx+1)+'</span>';
    });
  }

  wrap.addEventListener('change', function(e){
    const cb = e.target;
    if(!cb.classList.contains('org-check')) return;
    const v = String(cb.value);
    if(cb.checked){
      if(order.length >= max){
        cb.checked = false;
        return;
      }
      if(!order.includes(v)) order.push(v);
    } else {
      order = order.filter(x=>x!==v);
    }
    syncHidden();
  });

  syncHidden();
})();
</script>

<!-- Hidden priority fields for backend compatibility -->

<div style="display:none">
  <?php for ($i=1; $i<=5; $i++): ?>
    <select id="console_<?= $i ?>" name="console_<?= $i ?>">
      <option value="">None</option>
      <?php foreach ($consoles as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= ((int)($assigned_console_ids[$i-1] ?? 0)===(int)$c['id'])?'selected':'' ?>>
          <?= e($c['name']) ?> (<?= e($c['status']) ?>)
        </option>
      <?php endforeach; ?>
    </select>
  <?php endfor; ?>
</div>


              <button class="btn btn-primary" type="submit" style="margin-top:10px">Save Consoles</button>
            </form>
          </div>

          <div class="card" id="orgaccess">
            <h3>Allowed Product Profiles (Optional)</h3>
            <div class="muted" style="margin-bottom:10px">
              Default: reseller ko <b>ALL</b> product profiles miltay hain jo console mein available hon. Yahan se aap per-console <b>custom</b> profiles select kar sakte ho.
              <br><span class="small">Tip: agar aap <b>kuch select nahi karte</b> aur Save kar dete ho, to allow-list clear ho jati hai (ALL allowed).</span>
            </div>

            <?php
              // Build list of currently assigned console IDs (priority order)
              $assigned_console_ids = array_values(array_filter(array_map('intval', $assigned)));
              $assigned_console_ids = array_values(array_unique($assigned_console_ids));
            ?>

            <?php if (!$assigned_console_ids || count($assigned_console_ids) === 0): ?>
              <div class="small muted">Pehle consoles assign karo, phir yahan profiles customize ho sakti hain.</div>
            <?php else: ?>
              <?php foreach ($assigned_console_ids as $cid):
                $c = get_console($pdo, (int)$cid);
                if (!$c) continue;
                $profiles = [];
                if (!empty($c['profiles_json'])) {
                  $arr = json_decode((string)$c['profiles_json'], true);
                  if (is_array($arr)) $profiles = $arr;
                }
                // Existing allow-list
                $st = $pdo->prepare("SELECT group_name FROM reseller_console_profiles WHERE reseller_id=? AND console_id=? ORDER BY group_name ASC");
                $st->execute([(int)$r['id'], (int)$cid]);
                $selected = $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
                $selected = array_map('strval', $selected);
                $selSet = array_fill_keys($selected, true);
              ?>
                <div style="padding:10px;border:1px solid var(--border);border-radius:12px;margin:10px 0">
                  <div class="d-flex justify-between items-center" style="margin-bottom:8px">
                    <div>
                      <b><?= e((string)$c['name']) ?></b>
                      <div class="small muted">Console ID: <?= (int)$cid ?> • Profiles: <?= (int)count($profiles) ?> • Selected: <?= (int)count($selected) ?></div>
                    </div>
                  </div>

                  <form method="post">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="admin_set_console_profiles">
                    <input type="hidden" name="reseller_id" value="<?= (int)$r['id'] ?>">
                    <input type="hidden" name="console_id" value="<?= (int)$cid ?>">

                    
<label>Select allowed profiles</label>
<div class="prod-grid">
  <?php foreach ($profiles as $p):
    $gn = (string)($p['groupName'] ?? '');
    if ($gn === '') continue;
    $pn = (string)($p['productName'] ?? '');
    $label = $pn ? ($pn . " — " . $gn) : $gn;
    $isChecked = isset($selSet[$gn]);
  ?>
    <label class="prod-item">
      <input type="checkbox" name="profiles[]" value="<?= e($gn) ?>" <?= $isChecked ? 'checked' : '' ?>>
      <span><?= e($label) ?></span>
    </label>
  <?php endforeach; ?>
</div>
<div class="small muted">Checkbox selection (mobile friendly). Tip: if you leave all unchecked and Save, it means ALL allowed.</div>


                    <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap">
                      <button class="btn btn-primary" type="submit">Save Profiles</button>
                      <button class="btn" type="submit" onclick="this.form.querySelectorAll('select[name=\'profiles[]\'] option:checked').forEach(o=>o.selected=false);">Clear (ALL allowed)</button>
                    </div>
                  </form>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <div class="card" id="billing">
          <div class="d-flex justify-between items-center">
            <h3>Billing</h3>
            <div class="muted">Balance: <b>$<?= money_fmt($r['balance']) ?></b> • Paid: <b>$<?= money_fmt($r['total_paid']) ?></b> • Billed: <b>$<?= money_fmt($r['total_billed']) ?></b></div>
          </div>
          <form method="post" style="margin-top:10px">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="admin_add_payment">
            <input type="hidden" name="reseller_id" value="<?= (int)$r['id'] ?>">
            <div class="row">
              <div class="col"><label>Payment Amount</label><input name="amount" type="number" step="0.01" required></div>
              <div class="col"><label>Description</label><input name="description" value="Payment received"></div>
            </div>
            <button class="btn btn-primary" type="submit">Add Payment</button>
          </form>
        </div>

        <div class="card">
          <h3>Recent Users (This Reseller)</h3>
          <table>
            <thead>
              <tr><th>Email</th><th>Console</th><th>Profile</th><th>Expires</th><th>Extend</th><th>Status</th><th>Delete</th></tr>
            </thead>
            <tbody>
              <?php foreach ($users as $u): ?>
                <tr>
                  <td><?= e((string)$u['email']) ?></td>
                  <td><?= e((string)$u['console_name']) ?></td>
                  <td><?= e((string)$u['product_profile']) ?></td>
                  <td><?= e((string)$u['expires_at']) ?></td>
                  <td>
                    <form method="post" style="display:flex;gap:6px;align-items:center">
                      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="action" value="admin_extend_user">
                      <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                      <input name="expires_at" type="date" style="width:160px" value="<?= e(!empty($u['expires_at']) ? date('Y-m-d', strtotime((string)$u['expires_at'].' +1 month')) : date('Y-m-d', strtotime('+1 month'))) ?>">
                      <button class="btn btn-primary" type="submit">Extend</button>
                    </form>
                  </td>
                  <td><?= e((string)$u['status']) ?></td>
                  <td>
                    <form method="post" onsubmit="return confirm('Delete this user?');" style="display:inline">
                      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="action" value="admin_delete_user">
                      <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                      <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php
        }
