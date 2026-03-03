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

<div class="card">
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px">
        <h3 style="margin:0">📢 Announcements & Updates</h3>
        <?php if (!empty($announcements)): ?>
            <span class="badge b-info"><?= count($announcements) ?> new</span>
        <?php endif; ?>
    </div>
    
    <!-- Stats Overview -->
    <?php if (!empty($announcements)): ?>
    <div class="row" style="margin-bottom:20px; gap:8px">
        <?php if ($danger_count > 0): ?>
        <div class="col card" style="background:rgba(255,77,77,0.05); border-color:rgba(255,77,77,0.2); padding:8px; text-align:center">
            <span style="font-size:20px">🚨</span>
            <span class="badge b-danger" style="display:block; margin-top:4px"><?= $danger_count ?> urgent</span>
        </div>
        <?php endif; ?>
        
        <?php if ($warning_count > 0): ?>
        <div class="col card" style="background:rgba(255,193,7,0.05); border-color:rgba(255,193,7,0.2); padding:8px; text-align:center">
            <span style="font-size:20px">⚠️</span>
            <span class="badge b-warning" style="display:block; margin-top:4px"><?= $warning_count ?> warnings</span>
        </div>
        <?php endif; ?>
        
        <?php if ($success_count > 0): ?>
        <div class="col card" style="background:rgba(74,222,128,0.05); border-color:rgba(74,222,128,0.2); padding:8px; text-align:center">
            <span style="font-size:20px">✅</span>
            <span class="badge b-success" style="display:block; margin-top:4px"><?= $success_count ?> updates</span>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <?php if (empty($announcements)): ?>
        <div class="card" style="text-align:center; padding:60px 20px; background:rgba(255,255,255,0.02)">
            <div style="font-size:64px; margin-bottom:20px; opacity:0.7">📪</div>
            <h4 style="margin:10px 0; font-size:20px">No Announcements</h4>
            <p class="muted" style="max-width:400px; margin:10px auto">
                There are no announcements at this time. Check back later for updates from the admin team!
            </p>
        </div>
    <?php else: ?>
        <div class="announcement-feed">
            <?php foreach ($announcements as $index => $a): 
                // Set colors and icons based on type
                $border_color = '#93c5fd';
                $bg_color = 'rgba(147,197,253,0.05)';
                $icon = 'ℹ️';
                $badge_class = 'b-info';
                
                if ($a['type'] === 'success') {
                    $border_color = '#4ade80';
                    $bg_color = 'rgba(74,222,128,0.05)';
                    $icon = '✅';
                    $badge_class = 'b-success';
                } elseif ($a['type'] === 'warning') {
                    $border_color = '#f6c177';
                    $bg_color = 'rgba(246,193,119,0.05)';
                    $icon = '⚠️';
                    $badge_class = 'b-warning';
                } elseif ($a['type'] === 'danger') {
                    $border_color = '#ff4d4d';
                    $bg_color = 'rgba(255,77,77,0.05)';
                    $icon = '🚨';
                    $badge_class = 'b-danger';
                }
                
                // Calculate if new (less than 3 days old)
                $is_new = (time() - strtotime($a['created_at'])) < (3 * 24 * 60 * 60);
            ?>
                <div class="card announcement-item" style="margin:16px 0; border-left:4px solid <?= $border_color ?>; background:<?= $bg_color ?>; animation: slideIn 0.3s ease <?= $index * 0.1 ?>s both">
                    <!-- New badge for recent announcements -->
                    <?php if ($is_new): ?>
                        <div style="position:absolute; top:-8px; right:20px; background:<?= $border_color ?>; color:#000; padding:4px 12px; border-radius:20px; font-size:11px; font-weight:bold">
                            NEW
                        </div>
                    <?php endif; ?>
                    
                    <!-- Header -->
                    <div style="display:flex; align-items:flex-start; gap:12px; margin-bottom:12px">
                        <span style="font-size:28px; line-height:1"><?= $icon ?></span>
                        <div style="flex:1">
                            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:4px">
                                <h4 style="margin:0; font-size:18px"><?= e($a['title']) ?></h4>
                                <span class="badge <?= $badge_class ?>"><?= e($a['type']) ?></span>
                                <?php if (!empty($a['expires_at'])): ?>
                                    <span class="badge" style="background:rgba(255,255,255,0.1); font-size:10px">
                                        ⏰ Until <?= e(date('M j', strtotime($a['expires_at']))) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Meta info -->
                            <div style="display:flex; gap:15px; flex-wrap:wrap; font-size:12px; color:var(--muted); margin-top:4px">
                                <span>📅 <?= e(date('F j, Y', strtotime($a['created_at']))) ?></span>
                                <span>⏱️ <?= e(time_elapsed_string($a['created_at'])) ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Content -->
                    <div style="margin-top:12px; padding:15px; background:rgba(0,0,0,0.2); border-radius:12px; white-space:pre-wrap; line-height:1.6">
                        <?= e($a['content']) ?>
                    </div>
                    
                    <!-- Read receipt (optional) -->
                    <div style="margin-top:12px; display:flex; justify-content:flex-end; gap:8px">
                        <button class="btn btn-small" onclick="markAsRead(<?= $a['id'] ?>)" style="background:rgba(255,255,255,0.05)">
                            <span style="margin-right:4px">✓</span> Mark as read
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Footer -->
        <div class="muted small" style="display:flex; justify-content:space-between; align-items:center; margin-top:20px; padding-top:15px; border-top:1px solid var(--border2)">
            <span>Showing <?= count($announcements) ?> announcement(s)</span>
            <span>Last updated: <?= date('M j, Y g:i a') ?></span>
        </div>
    <?php endif; ?>
</div>

<!-- Helper function for time elapsed -->
<?php
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;
    
    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }
    
    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
?>

<!-- JavaScript for mark as read -->
<script>
function markAsRead(announcementId) {
    // You can implement an AJAX call here to mark as read
    // For now, we'll just show a message
    alert('Marked as read! (Feature coming soon)');
    
    // Optional: You could add an AJAX call to record when users read announcements
    /*
    fetch('?ajax=mark_announcement_read', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({announcement_id: announcementId, csrf: '<?= csrf_token() ?>'})
    })
    .then(response => response.json())
    .then(data => {
        if(data.ok) location.reload();
    });
    */
}
</script>

<!-- Animations -->
<style>
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.announcement-item {
    position: relative;
    transition: all 0.3s ease;
}

.announcement-item:hover {
    transform: translateX(5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.3);
}

.btn-small {
    padding: 4px 10px;
    font-size: 11px;
    border-radius: 8px;
}

.badge {
    text-transform: uppercase;
    font-size: 10px;
    padding: 3px 8px;
}
</style>
