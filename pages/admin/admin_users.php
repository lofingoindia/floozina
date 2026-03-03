<?php
// META: {"title": "Users", "order": 30, "nav": true, "hidden": false}
$users = get_all_users($pdo);
        ?>
        <div class="card">
          <h3>All Users (Admin)</h3>
          <table>
            <thead>
              <tr><th>Email</th><th>Reseller</th><th>Console</th><th>Profile(Group)</th><th>Expires</th><th>Extend</th><th>Delete</th></tr>
            </thead>
            <tbody>
              <?php foreach ($users as $u): ?>
                <tr>
                  <td><?= e((string)$u['email']) ?></td>
                  <td><?= e((string)$u['reseller_name']) ?></td>
                  <td><?= e((string)$u['console_name']) ?></td>
                  <td class="small"><?= e((string)$u['product_profile']) ?></td>
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
                  <td>
                    <form method="post" onsubmit="return confirm('Delete user from Adobe + local DB?');">
                      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="action" value="admin_delete_user">
                      <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                      <label class="small muted"><input type="checkbox" name="remove_from_org" value="1"> removeFromOrg</label>
                      <button class="btn btn-danger" type="submit">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <p class="small muted">removeFromOrg tick karoge to user organization se bhi remove ho jayega.</p>
        </div>
        <?php
