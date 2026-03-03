<?php
// META: {"title": "Consoles", "order": 50, "nav": true, "hidden": false}
$consoles = get_consoles($pdo);
        ?>
        <div class="card">
          <h3>Add Organization/Console</h3>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="admin_add_console">
            <div class="row">
              <div class="col"><label>Name *</label><input name="name" required></div>
              <div class="col">
                <label>Status</label>
                <select name="status">
                  <option value="active">active</option>
                  <option value="inactive">inactive</option>
                  <option value="down">down</option>
                </select>
              </div>
              <div class="col"><label>Backup?</label><select name="is_backup"><option value="">No</option><option value="1">Yes</option></select></div>
            </div>

            <div class="row">
              <div class="col"><label>Client ID *</label><input name="client_id" placeholder="From Adobe Developer Console" required></div>
              <div class="col"><label>Client Secret *</label><input name="client_secret" required></div>
            </div>

            <div class="row">
              <div class="col"><label>Organization ID (orgId) *</label><input name="organization_id" required></div>
              <div class="col"><label>Technical Account ID</label><input name="technical_account_id"></div>
            </div>

            <div class="row">
              <div class="col">
                <label>Scopes *</label>
                <input name="scopes" value="openid, AdobeID, user_management_sdk" required>
                <div class="small muted">Usually: openid, AdobeID, user_management_sdk</div>
              </div>
              <div class="col"><label>IMS Host</label><input name="ims_host" value="ims-na1.adobelogin.com"></div>
              <div class="col"><label>UMAPI Host</label><input name="umapi_host" value="usermanagement.adobe.io"></div>
            </div>

            <button class="btn btn-primary" type="submit">Add Console</button>
            <p class="small muted" style="margin-top:10px">
              Add console -> then click <b>Test</b> -> then <b>Fetch Profiles</b>.
            </p>
          </form>
        </div>

        <div class="card">
          <h3>All Consoles</h3>
          <table>
            <thead>
              <tr>
                <th>Name</th><th>Status</th><th>Test</th><th>Profiles</th><th>OrgId</th><th>Created</th><th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($consoles as $c):
                $badge='b-info';
                if ($c['status']==='active') $badge='b-success';
                elseif ($c['status']==='inactive') $badge='b-warning';
                elseif ($c['status']==='down') $badge='b-danger';

                $tbadge = !empty($c['last_test_ok']) ? 'b-success' : 'b-danger';
                $profiles_count = 0;
                if (!empty($c['profiles_json'])) {
                    $arr = json_decode((string)$c['profiles_json'], true);
                    if (is_array($arr)) $profiles_count = count($arr);
                }
              ?>
                <tr>
                  <td><?= e($c['name']) ?> <?= !empty($c['is_backup']) ? "<span class='badge b-warning'>backup</span>" : "" ?></td>
                  <td><span class="badge <?= $badge ?>"><?= e($c['status']) ?></span></td>
                  <td>
                    <div class="inline">
                      <form method="post" style="margin:0">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="admin_test_console">
                        <input type="hidden" name="console_id" value="<?= (int)$c['id'] ?>">
                        <button class="btn btn-muted" type="submit">Test</button>
                      </form>
                      <span class="badge <?= $tbadge ?>"><?= !empty($c['last_test_ok']) ? "OK" : "Not OK" ?></span>
                      <span class="small muted"><?= e((string)$c['last_test_at']) ?></span>
                    </div>
                    <?php if (!empty($c['last_test_message'])): ?>
                      <div class="small muted" style="margin-top:6px;white-space:pre-wrap"><?= e((string)$c['last_test_message']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="inline">
                      <form method="post" style="margin:0">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="admin_fetch_profiles">
                        <input type="hidden" name="console_id" value="<?= (int)$c['id'] ?>">
                        <button class="btn btn-primary" type="submit">Fetch Profiles</button>
                      </form>
                      <span class="badge b-info"><?= (int)$profiles_count ?></span>
                      <span class="small muted"><?= e((string)$c['profiles_fetched_at']) ?></span>
                    </div>
                  </td>
                  <td class="small muted"><?= e((string)$c['organization_id']) ?></td>
                  <td class="muted small"><?= e((string)$c['created_at']) ?></td>
                  <td>
                    <form method="post" style="display:inline" onsubmit="return confirm('Delete this console? Users assigned to it will be reassigned if possible.');">
                      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="action" value="admin_delete_console">
                      <input type="hidden" name="console_id" value="<?= (int)$c['id'] ?>">
                      <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php
