<?php
// META: {"title": "Bulk History", "order": 60, "nav": true, "hidden": false}
// NOTE: session_start() mat lagana (app.php already start karta hoga)

$rid    = (int)($_SESSION['user_id'] ?? 0);
$role   = (string)($_SESSION['role'] ?? '');
$status = (string)($_SESSION['status'] ?? '');

if ($rid <= 0 || $role !== 'reseller') {
    echo "<div class='card' style='padding:12px'>Please login as reseller again.</div>";
    return;
}

$stmt = $pdo->prepare("SELECT id, job_type, console_id, product_profile, months, team,
                              total_items, done_items, failed_items, skipped_items,
                              status, created_at
                       FROM bulk_jobs
                       WHERE reseller_id=?
                       ORDER BY id DESC
                       LIMIT 50");
$stmt->execute([$rid]);
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<div class="card">
  <h3>Bulk History</h3>
  <div class="small muted" style="margin-bottom:12px">
    Last 50 bulk jobs (assign + delete). Download CSV for details.
  </div>

  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Type</th>
        <th>Console</th>
        <th>Profile</th>
        <th>Progress</th>
        <th>Failed</th>
        <th>Skipped</th>
        <th>Status</th>
        <th>Created</th>
        <th>CSV</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$jobs || count($jobs) === 0): ?>
        <tr><td colspan="10" class="small muted">No bulk jobs found.</td></tr>
      <?php else: ?>
        <?php foreach ($jobs as $j):
          $total   = (int)($j['total_items'] ?? 0);
          $done    = (int)($j['done_items'] ?? 0);
          $failed  = (int)($j['failed_items'] ?? 0);
          $skipped = (int)($j['skipped_items'] ?? 0);
          $proc    = $done + $failed + $skipped;
          $pct     = $total > 0 ? (int)round(($proc/$total)*100) : 0;

          $badge = 'b-info';
          if (($j['status'] ?? '') === 'done') $badge = 'b-success';
          elseif (($j['status'] ?? '') === 'failed') $badge = 'b-danger';
        ?>
          <tr>
            <td>#<?= (int)($j['id'] ?? 0) ?></td>
            <td><?= e((string)($j['job_type'] ?? '')) ?></td>
            <td><?= (int)($j['console_id'] ?? 0) ?></td>
            <td><?= e((string)($j['product_profile'] ?? '')) ?></td>
            <td><?= $proc ?>/<?= $total ?> (<?= $pct ?>%)</td>
            <td><?= $failed ?></td>
            <td><?= $skipped ?></td>
            <td><span class="badge <?= $badge ?>"><?= e((string)($j['status'] ?? '')) ?></span></td>
            <td class="small muted"><?= e((string)($j['created_at'] ?? '')) ?></td>
            <td>
              <button class="btn btn-muted btn-sm" type="button" onclick="bulkDownloadCSV(<?= (int)($j['id'] ?? 0) ?>)">
                CSV
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<script>
async function bulkDownloadCSV(jobId){
  const csrf = <?= json_encode(csrf_token()) ?>;
  const form = new URLSearchParams();
  form.append('csrf', csrf);
  form.append('action', 'reseller_bulk_csv');
  form.append('job_id', jobId);

  const res = await fetch('?ajax=reseller_bulk_csv', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: form.toString()
  });

  const j = await res.json();
  if (!j || !j.ok){ alert((j && j.error) ? j.error : 'CSV failed'); return; }

  const bytes = atob(j.data);
  const arr = new Uint8Array(bytes.length);
  for (let i=0;i<bytes.length;i++) arr[i] = bytes.charCodeAt(i);

  const blob = new Blob([arr], {type:'text/csv'});
  const url = URL.createObjectURL(blob);

  const a = document.createElement('a');
  a.href = url;
  a.download = j.filename || ('bulk_job_'+jobId+'.csv');
  document.body.appendChild(a);
  a.click();
  a.remove();

  URL.revokeObjectURL(url);
}
</script>