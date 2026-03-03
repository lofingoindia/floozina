<?php
// META: {"title": "Logs", "order": 70, "nav": true, "hidden": false}
$logs = get_logs($pdo);
        ?>
        <div class="card">
          <h3>Audit Log</h3>
          <table>
            <thead><tr><th>Time</th><th>Action</th><th>Description</th><th>By</th><th>Reseller</th><th>Status</th><th>IP</th></tr></thead>
            <tbody>
              <?php foreach ($logs as $l): ?>
                <tr>
                  <td class="small muted"><?= e((string)$l['created_at']) ?></td>
                  <td><?= e($l['action_type']) ?></td>
                  <td><?= e((string)$l['description']) ?></td>
                  <td><?= e((string)$l['performed_by']) ?></td>
                  <td><?= e((string)$l['reseller_name']) ?></td>
                  <td><?= e((string)$l['status']) ?></td>
                  <td class="small muted"><?= e((string)$l['ip_address']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php
