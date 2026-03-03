<?php
// META: {"title": "Announcements", "order": 40, "nav": true, "hidden": false}
$ann = $pdo->query("SELECT a.*, r.username AS creator FROM announcements a LEFT JOIN resellers r ON a.created_by=r.id ORDER BY a.created_at DESC")->fetchAll();?>
<div class="card">
  <h3>📢 Create New Announcement</h3>
  <p class="muted">Create announcements that will appear to users on their dashboards</p>
  
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="admin_create_announcement">
    
    <div class="row">
      <div class="col">
        <label>Title *</label>
        <input name="title" required placeholder="e.g., System Maintenance, New Features, etc.">
      </div>
      <div class="col">
        <label>Type</label>
        <select name="type">
          <option value="info">ℹ️ Info</option>
          <option value="success">✅ Success</option>
          <option value="warning">⚠️ Warning</option>
          <option value="danger">🚨 Urgent</option>
        </select>
      </div>
    </div>
    
    <div class="row">
      <div class="col">
        <label>Target Audience</label>
        <select name="target">
          <option value="all">👥 All Users</option>
          <option value="super_admin">👑 Super Admins Only</option>
          <option value="reseller">💼 Resellers Only</option>
          <option value="client">🌐 Public Status Page (Clients)</option>
        </select>
      </div>
      <div class="col">
        <label>Expires (optional)</label>
        <input name="expires_at" type="date" min="<?= date('Y-m-d') ?>">
        <small class="muted">Leave empty for no expiration</small>
      </div>
    </div>
    
    <div class="row">
      <div class="col">
        <label>Content *</label>
        <textarea name="content" required placeholder="Write your announcement here..." rows="5"></textarea>
      </div>
    </div>
    
    <div class="inline" style="gap:10px; margin-top:10px">
      <button class="btn btn-primary" type="submit">
        <span style="margin-right:6px">📨</span> Post Announcement
      </button>
      <button class="btn" type="reset">
        <span style="margin-right:6px">↺</span> Reset
      </button>
    </div>
  </form>
</div>

<div class="card">
  <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px">
    <h3 style="margin:0">📋 Manage Announcements</h3>
    <div class="badge b-info">Total: <?= count($ann) ?></div>
  </div>
  
  <!-- Statistics Dashboard -->
  <div class="row" style="margin-bottom:20px; gap:10px">
    <div class="col card" style="background:rgba(74,222,128,0.05); border-color:rgba(74,222,128,0.2); padding:12px">
      <div style="font-size:24px; margin-bottom:5px">✅</div>
      <div style="font-size:20px; font-weight:bold"><?= count(array_filter($ann, fn($a) => $a['is_active'] == 1 && $a['type'] === 'success')) ?></div>
      <div class="muted">Success</div>
    </div>
    <div class="col card" style="background:rgba(255,193,7,0.05); border-color:rgba(255,193,7,0.2); padding:12px">
      <div style="font-size:24px; margin-bottom:5px">⚠️</div>
      <div style="font-size:20px; font-weight:bold"><?= count(array_filter($ann, fn($a) => $a['is_active'] == 1 && $a['type'] === 'warning')) ?></div>
      <div class="muted">Warnings</div>
    </div>
    <div class="col card" style="background:rgba(255,77,77,0.05); border-color:rgba(255,77,77,0.2); padding:12px">
      <div style="font-size:24px; margin-bottom:5px">🚨</div>
      <div style="font-size:20px; font-weight:bold"><?= count(array_filter($ann, fn($a) => $a['is_active'] == 1 && $a['type'] === 'danger')) ?></div>
      <div class="muted">Urgent</div>
    </div>
    <div class="col card" style="background:rgba(147,197,253,0.05); border-color:rgba(147,197,253,0.2); padding:12px">
      <div style="font-size:24px; margin-bottom:5px">ℹ️</div>
      <div style="font-size:20px; font-weight:bold"><?= count(array_filter($ann, fn($a) => $a['is_active'] == 1 && $a['type'] === 'info')) ?></div>
      <div class="muted">Info</div>
    </div>
  </div>
  
  <!-- Active Announcements Section -->
  <div style="margin-bottom:30px">
    <h4 style="display:flex; align-items:center; gap:8px; margin:15px 0; color:var(--success)">
      <span>🟢</span> Active Announcements
      <span class="badge b-success"><?= count(array_filter($ann, fn($a) => $a['is_active'] == 1)) ?></span>
    </h4>
    
    <?php 
    $active_found = false;
    foreach ($ann as $a):
        if ($a['is_active'] == 0) continue;
        $active_found = true;
        
        // Set badge and icon based on type
        $badge = 'b-info';
        $icon = 'ℹ️';
        $border_color = '#93c5fd';
        if ($a['type'] === 'success') { 
            $badge = 'b-success'; 
            $icon = '✅'; 
            $border_color = '#4ade80';
        } elseif ($a['type'] === 'warning') { 
            $badge = 'b-warning'; 
            $icon = '⚠️'; 
            $border_color = '#f6c177';
        } elseif ($a['type'] === 'danger') { 
            $badge = 'b-danger'; 
            $icon = '🚨'; 
            $border_color = '#ff4d4d';
        }
        
        // Target display
        $target_display = '👥 All Users';
        $target_color = '#93c5fd';
        if ($a['target'] === 'super_admin') {
            $target_display = '👑 Super Admins Only';
            $target_color = '#c084fc';
        } elseif ($a['target'] === 'reseller') {
            $target_display = '💼 Resellers Only';
            $target_color = '#f6c177';
        }
    ?>
        <div class="card announcement-card" style="margin:16px 0; border-left:4px solid <?= $border_color ?>; position:relative">
            <!-- Status indicator (top right) -->
            <div style="position:absolute; top:12px; right:12px">
                <span class="badge" style="background:<?= $border_color ?>20; color:<?= $border_color ?>">Active</span>
            </div>
            
            <!-- Header -->
            <div class="row" style="align-items:center; margin-bottom:12px; padding-right:60px">
                <div class="col">
                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap">
                        <span style="font-size:24px"><?= $icon ?></span>
                        <b style="font-size:18px"><?= e($a['title']) ?></b>
                        <span class="badge <?= $badge ?>"><?= e($a['type']) ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Metadata -->
            <div class="row" style="margin-bottom:12px; gap:15px; flex-wrap:wrap">
                <div class="col" style="display:flex; gap:15px; flex-wrap:wrap">
                    <span class="muted" style="display:flex; align-items:center; gap:4px">
                        <span>👤</span> By: <?= e($a['creator'] ?? 'System') ?>
                    </span>
                    <span class="muted" style="display:flex; align-items:center; gap:4px">
                        <span>📅</span> Posted: <?= e(date('M j, Y g:i a', strtotime($a['created_at']))) ?>
                    </span>
                    <span class="muted" style="display:flex; align-items:center; gap:4px">
                        <span style="color:<?= $target_color ?>">🎯</span> 
                        <span style="color:<?= $target_color ?>"><?= $target_display ?></span>
                    </span>
                    <?php if (!empty($a['expires_at'])): ?>
                        <span class="muted" style="display:flex; align-items:center; gap:4px">
                            <span>⏰</span> Expires: <?= e(date('M j, Y', strtotime($a['expires_at']))) ?>
                            <?php if (strtotime($a['expires_at']) < time()): ?>
                                <span class="badge b-warning" style="font-size:10px">Expired</span>
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Content -->
            <div class="muted" style="margin:12px 0; white-space:pre-wrap; line-height:1.6; padding:15px; background:rgba(255,255,255,0.03); border-radius:12px">
                <?= e($a['content']) ?>
            </div>
            
            <!-- Actions -->
            <div class="inline" style="justify-content:flex-end; gap:10px; border-top:1px solid var(--border2); padding-top:15px; margin-top:10px">
                <!-- Preview Button -->
                <button class="btn btn-small" onclick="previewAnnouncement(<?= htmlspecialchars(json_encode($a)) ?>)" style="background:rgba(147,197,253,0.1)">
                    <span style="margin-right:4px">👁️</span> Preview
                </button>
                
                <!-- Edit Button (if you want to add edit functionality later) -->
                <button class="btn btn-small" onclick="alert('Edit feature coming soon!')" style="background:rgba(255,255,255,0.05)">
                    <span style="margin-right:4px">✏️</span> Edit
                </button>
                
                <!-- Delete Button -->
                <form method="post" onsubmit="return confirmDelete('<?= e(addslashes($a['title'])) ?>');" style="margin:0">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="admin_delete_announcement">
                    <input type="hidden" name="announcement_id" value="<?= (int)$a['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-small">
                        <span style="margin-right:4px">🗑️</span> Delete
                    </button>
                </form>
            </div>
        </div>
    <?php endforeach; 
    
    if (!$active_found): ?>
        <div class="card" style="text-align:center; padding:40px 20px; background:rgba(255,255,255,0.02)">
            <div style="font-size:48px; margin-bottom:10px">📪</div>
            <h4 style="margin:10px 0">No Active Announcements</h4>
            <p class="muted">Create your first announcement using the form above!</p>
        </div>
    <?php endif; ?>
  </div>
  
  <!-- Inactive/Deleted Announcements Section -->
  <?php 
  $inactive_count = count(array_filter($ann, fn($a) => $a['is_active'] == 0));
  if ($inactive_count > 0): 
  ?>
  <details class="collapsible">
    <summary>
      <span style="display:flex; align-items:center; gap:8px; cursor:pointer">
        <span>📁</span> Deleted Announcements
        <span class="badge" style="background:rgba(255,77,77,0.1); color:#ff9999"><?= $inactive_count ?></span>
        <span class="muted small" style="margin-left:auto">Click to expand</span>
      </span>
    </summary>
    
    <div style="margin-top:15px">
        <?php 
        foreach ($ann as $a):
            if ($a['is_active'] != 0) continue;
            
            $badge = 'b-info';
            if ($a['type'] === 'success') $badge = 'b-success';
            elseif ($a['type'] === 'warning') $badge = 'b-warning';
            elseif ($a['type'] === 'danger') $badge = 'b-danger';
        ?>
            <div class="card" style="margin:8px 0; opacity:0.6; background:rgba(255,255,255,0.02); border-left:4px solid #71717a">
                <div class="row" style="align-items:center">
                    <div class="col">
                        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap">
                            <b style="text-decoration:line-through"><?= e($a['title']) ?></b>
                            <span class="badge <?= $badge ?>"><?= e($a['type']) ?></span>
                            <span class="badge" style="background:rgba(255,77,77,0.1); color:#ff9999">Deleted</span>
                        </div>
                        <div class="small muted" style="margin-top:4px">
                            Posted: <?= e(date('M j, Y', strtotime($a['created_at']))) ?> 
                            | Target: <?= $a['target'] ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
  </details>
  <?php endif; ?>
</div>

<!-- Preview Modal -->
<div id="previewModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.9); z-index:1000; align-items:center; justify-content:center;">
    <div class="modal" style="max-width:600px">
        <div class="modal-header">
            <h3>Announcement Preview</h3>
            <button class="modal-close" onclick="hidePreview()">✕</button>
        </div>
        <div id="previewContent" class="card" style="margin:0">
            <!-- Preview will be inserted here via JavaScript -->
        </div>
        <div class="inline" style="justify-content:flex-end; margin-top:20px">
            <button class="btn" onclick="hidePreview()">Close</button>
        </div>
    </div>
</div>

<!-- JavaScript for preview and confirm delete -->
<script>
function confirmDelete(title) {
    return confirm(`Are you sure you want to delete the announcement: "${title}"?\n\nThis action can be reversed by reactivating if needed.`);
}

function previewAnnouncement(announcement) {
    const previewDiv = document.getElementById('previewContent');
    
    // Determine colors and icons
    let borderColor = '#93c5fd';
    let icon = 'ℹ️';
    let badgeClass = 'b-info';
    
    if (announcement.type === 'success') {
        borderColor = '#4ade80';
        icon = '✅';
        badgeClass = 'b-success';
    } else if (announcement.type === 'warning') {
        borderColor = '#f6c177';
        icon = '⚠️';
        badgeClass = 'b-warning';
    } else if (announcement.type === 'danger') {
        borderColor = '#ff4d4d';
        icon = '🚨';
        badgeClass = 'b-danger';
    }
    
    // Target display
    let targetDisplay = 'All Users';
    if (announcement.target === 'super_admin') targetDisplay = 'Super Admins Only';
    else if (announcement.target === 'reseller') targetDisplay = 'Resellers Only';
    
    previewDiv.innerHTML = `
        <div style="border-left:4px solid ${borderColor}; padding:15px; background:rgba(255,255,255,0.03)">
            <div style="display:flex; align-items:center; gap:8px; margin-bottom:10px">
                <span style="font-size:24px">${icon}</span>
                <b style="font-size:18px">${announcement.title}</b>
                <span class="badge ${badgeClass}">${announcement.type}</span>
            </div>
            
            <div style="margin-bottom:15px; color:var(--muted); font-size:12px">
                <span style="margin-right:15px">👤 To: ${targetDisplay}</span>
                <span>📅 Posted: ${new Date(announcement.created_at).toLocaleString()}</span>
                ${announcement.expires_at ? `<span style="margin-left:15px">⏰ Expires: ${new Date(announcement.expires_at).toLocaleDateString()}</span>` : ''}
            </div>
            
            <div style="white-space:pre-wrap; line-height:1.6; padding:15px; background:rgba(255,255,255,0.03); border-radius:12px">
                ${announcement.content}
            </div>
        </div>
    `;
    
    document.getElementById('previewModal').style.display = 'flex';
}

function hidePreview() {
    document.getElementById('previewModal').style.display = 'none';
}

// Close modal if clicked outside
window.onclick = function(event) {
    const modal = document.getElementById('previewModal');
    if (event.target == modal) {
        hidePreview();
    }
}
</script>

<!-- Additional CSS -->
<style>
.announcement-card {
    transition: all 0.3s ease;
}
.announcement-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.5);
}
.btn-small {
    padding: 6px 12px;
    font-size: 12px;
    border-radius: 10px;
}
.btn-danger {
    background: rgba(255,77,77,0.1);
    border-color: rgba(255,77,77,0.3);
    color: #ffb3b3;
}
.btn-danger:hover {
    background: rgba(255,77,77,0.2);
    border-color: rgba(255,77,77,0.5);
}
.collapsible summary {
    cursor: pointer;
    padding: 10px;
    background: rgba(255,255,255,0.03);
    border-radius: 12px;
    list-style: none;
}
.collapsible summary::-webkit-details-marker {
    display: none;
}
.collapsible[open] summary {
    margin-bottom: 15px;
    background: rgba(255,255,255,0.05);
}
</style>
