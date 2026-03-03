<?php
// META: {"title": "Transactions", "order": 60, "nav": true, "hidden": false}
$tx = get_transactions($pdo);
        ?>
        <div class="card">
          <h3>Transactions</h3>
          <table>
            <thead><tr><th>Time</th><th>Reseller</th><th>User</th><th>Type</th><th>Amount</th><th>Description</th></tr></thead>
            <tbody>
              <?php foreach ($tx as $t): ?>
                <tr>
                  <td class="small muted"><?= e((string)$t['created_at']) ?></td>
                  <td><?= e((string)$t['reseller_name']) ?></td>
                  <td><?= e((string)$t['user_email']) ?></td>
                  <td><?= e((string)$t['type']) ?></td>
                  <td>$<?= money_fmt($t['amount']) ?></td>
                  <td><?= e((string)$t['description']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php

