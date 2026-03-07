<?php
// META: {"title": "Announcements", "order": 80, "nav": true, "hidden": false}
// Get announcements for reseller
$announcements = get_announcements($pdo, 'reseller');

// Count by type for stats
$success_count = count(array_filter($announcements, fn($a) => $a['type'] === 'success'));
$warning_count = count(array_filter($announcements, fn($a) => $a['type'] === 'warning'));
$danger_count = count(array_filter($announcements, fn($a) => $a['type'] === 'danger'));
$info_count = count(array_filter($announcements, fn($a) => $a['type'] === 'info'));
?>

<!-- Announcements Styles -->
<style>
  .ann-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
    gap: 10px;
    flex-wrap: wrap;
  }
  .ann-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-1);
    margin: 0;
  }
  .ann-stats {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 16px;
  }
  .ann-stat {
    flex: 1;
    min-width: 80px;
    padding: 10px;
    background: var(--surface-2);
    border: 1px solid var(--border-1);
    border-radius: var(--r-sm);
    text-align: center;
  }
  .ann-stat-icon {
    font-size: 16px;
    margin-bottom: 4px;
  }
  .ann-empty {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-3);
  }
  .ann-empty-icon {
    font-size: 32px;
    opacity: 0.5;
    margin-bottom: 12px;
  }
  .ann-empty-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-2);
    margin: 0 0 6px;
  }
  .ann-empty-text {
    font-size: 12px;
    max-width: 300px;
    margin: 0 auto;
  }
  .ann-item {
    position: relative;
    padding: 14px;
    border-radius: var(--r-sm);
    border-left: 3px solid var(--info);
    background: var(--surface-2);
    margin-bottom: 10px;
    transition: background var(--t);
  }
  .ann-item:last-child { margin-bottom: 0; }
  .ann-item:hover { background: var(--surface-3); }
  .ann-item--info { border-left-color: var(--info); }
  .ann-item--success { border-left-color: var(--success); }
  .ann-item--warning { border-left-color: var(--warning); }
  .ann-item--danger { border-left-color: var(--danger); }
  .ann-item-new {
    position: absolute;
    top: -6px; right: 12px;
    font-size: 8px;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 4px;
    background: var(--brand);
    color: #fff;
    letter-spacing: .05em;
  }
  .ann-item-header {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin-bottom: 8px;
  }
  .ann-item-icon {
    font-size: 18px;
    line-height: 1;
  }
  .ann-item-title {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-1);
    margin: 0 0 4px;
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
  }
  .ann-item-meta {
    font-size: 10px;
    color: var(--text-3);
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
  }
  .ann-item-body {
    font-size: 12px;
    color: var(--text-2);
    line-height: 1.5;
    white-space: pre-wrap;
    padding: 10px;
    background: var(--surface-1);
    border-radius: var(--r-xs);
    margin-top: 8px;
  }
  .ann-footer {
    font-size: 11px;
    color: var(--text-3);
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 12px;
    padding-top: 10px;
    border-top: 1px solid var(--border-2);
  }
  @media (max-width: 520px) {
    .ann-header { flex-direction: column; align-items: stretch; }
    .ann-item { padding: 12px; }
  }
</style>

<div class="card">
  <div class="ann-header">
    <h3 class="ann-title">Announcements</h3>
    <?php if (!empty($announcements)): ?>
      <span class="badge b-info"><?= count($announcements) ?></span>
    <?php endif; ?>
  </div>
  
  <!-- Stats Overview -->
  <?php if (!empty($announcements)): ?>
  <div class="ann-stats">
    <?php if ($danger_count > 0): ?>
      <div class="ann-stat">
        <div class="ann-stat-icon">🚨</div>
        <span class="badge b-danger"><?= $danger_count ?></span>
      </div>
    <?php endif; ?>
    <?php if ($warning_count > 0): ?>
      <div class="ann-stat">
        <div class="ann-stat-icon">⚠️</div>
        <span class="badge b-warning"><?= $warning_count ?></span>
      </div>
    <?php endif; ?>
    <?php if ($info_count > 0): ?>
      <div class="ann-stat">
        <div class="ann-stat-icon">ℹ️</div>
        <span class="badge b-info"><?= $info_count ?></span>
      </div>
    <?php endif; ?>
    <?php if ($success_count > 0): ?>
      <div class="ann-stat">
        <div class="ann-stat-icon">✅</div>
        <span class="badge b-success"><?= $success_count ?></span>
      </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
  
  <?php if (empty($announcements)): ?>
    <div class="ann-empty">
      <div class="ann-empty-icon">📭</div>
      <div class="ann-empty-title">No Announcements</div>
      <div class="ann-empty-text">There are no announcements at this time.</div>
    </div>
  <?php else: ?>
    <?php foreach ($announcements as $a): 
      // Set colors and icons based on type
      $icon = 'ℹ️';
      if ($a['type'] === 'success') $icon = '✅';
      elseif ($a['type'] === 'warning') $icon = '⚠️';
      elseif ($a['type'] === 'danger') $icon = '🚨';
      
      // Calculate if new (less than 3 days old)
      $is_new = (time() - strtotime($a['created_at'])) < (3 * 24 * 60 * 60);
    ?>
      <div class="ann-item ann-item--<?= e($a['type']) ?>">
        <?php if ($is_new): ?>
          <div class="ann-item-new">NEW</div>
        <?php endif; ?>
        
        <div class="ann-item-header">
          <span class="ann-item-icon"><?= $icon ?></span>
          <div style="flex:1">
            <div class="ann-item-title">
              <?= e($a['title']) ?>
              <span class="badge b-<?= e($a['type']) ?>"><?= e($a['type']) ?></span>
            </div>
            <div class="ann-item-meta">
              <span><?= e(date('M j, Y', strtotime($a['created_at']))) ?></span>
              <?php if (!empty($a['expires_at'])): ?>
                <span>Expires: <?= e(date('M j', strtotime($a['expires_at']))) ?></span>
              <?php endif; ?>
            </div>
          </div>
        </div>
        
        <div class="ann-item-body"><?= e($a['content']) ?></div>
      </div>
    <?php endforeach; ?>
    
    <div class="ann-footer">
      <span><?= count($announcements) ?> announcement(s)</span>
      <span>Updated: <?= date('M j, Y') ?></span>
    </div>
  <?php endif; ?>
</div>
