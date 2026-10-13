<?php
require_once __DIR__ . '/auth.php';
$adminUser = requireAdmin();

$success = '';
$error = '';

// 1. Handle Add Video
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_video') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $videoUrl = trim($_POST['video_url'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $noExpiry = isset($_POST['no_expiry']) ? 1 : 0;
    $expiresAt = trim($_POST['expires_at'] ?? '');
    $sortOrder = (int)($_POST['sort_order'] ?? 0);

    if (empty($title) || empty($videoUrl)) {
        $error = 'Please provide both video title and a valid video URL or embed link.';
    } else {
        $data = [
            'title' => $title,
            'description' => $description,
            'video_url' => $videoUrl,
            'video_type' => 'auto',
            'is_active' => $isActive,
            'expires_at' => $noExpiry ? null : $expiresAt,
            'no_expiry' => $noExpiry,
            'sort_order' => $sortOrder
        ];
        if (addVideo($data)) {
            $success = 'Video added successfully! It is set to automatically expire at ' . ($noExpiry ? 'Never (No expiry)' : ($expiresAt ?: 'Tonight at 11:59:59 PM (12:00 AM)')) . '.';
        } else {
            $error = 'Failed to add video. Please verify database connection.';
        }
    }
}

// 2. Handle Update / Replace Video
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_video') {
    $id = (int)($_POST['video_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $videoUrl = trim($_POST['video_url'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $noExpiry = isset($_POST['no_expiry']) ? 1 : 0;
    $expiresAt = trim($_POST['expires_at'] ?? '');
    $sortOrder = (int)($_POST['sort_order'] ?? 0);

    if ($id <= 0 || empty($title) || empty($videoUrl)) {
        $error = 'Please fill out all required fields to update the video.';
    } else {
        $data = [
            'title' => $title,
            'description' => $description,
            'video_url' => $videoUrl,
            'video_type' => 'auto',
            'is_active' => $isActive,
            'expires_at' => $noExpiry ? null : $expiresAt,
            'no_expiry' => $noExpiry,
            'sort_order' => $sortOrder
        ];
        if (updateVideo($id, $data)) {
            $success = "Video #{$id} updated successfully! Expiry: " . ($noExpiry ? 'Never' : ($expiresAt ?: 'Tonight 11:59:59 PM'));
        } else {
            $error = "Failed to update video #{$id}.";
        }
    }
}

// 3. Handle Delete Video
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_video') {
    $id = (int)($_POST['video_id'] ?? 0);
    if ($id > 0) {
        if (deleteVideo($id)) {
            $success = "Video #{$id} has been permanently deleted.";
        } else {
            $error = "Failed to delete video.";
        }
    }
}

// 4. Handle Quick Status Toggle (Admin manual deactivate/activate)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    $id = (int)($_POST['video_id'] ?? 0);
    $video = getVideoById($id);
    if ($video) {
        $newStatus = $video['is_active'] ? 0 : 1;
        $video['is_active'] = $newStatus;
        // If activating and video had expired in the past, auto refresh expiry to tonight 11:59 PM
        if ($newStatus === 1 && !empty($video['expires_at']) && strtotime($video['expires_at']) <= time()) {
            $video['expires_at'] = date('Y-m-d 23:59:59');
        }
        updateVideo($id, $video);
        $success = "Video #{$id} status changed to " . ($newStatus ? 'Active' : 'Inactive / Deactivated') . ".";
    }
}

// 5. Handle 1-Click Reactivate / Extend Expiry to Tonight 11:59 PM
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'extend_midnight') {
    $id = (int)($_POST['video_id'] ?? 0);
    if ($id > 0) {
        if (extendVideoExpiry($id, date('Y-m-d 23:59:59'))) {
            $success = "Video #{$id} has been reactivated and set to expire tonight at 11:59:59 PM (12:00 AM).";
        } else {
            $error = "Failed to reactivate video #{$id}.";
        }
    }
}

$videos = getVideos(false);

// If editing a specific video
$editVideo = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editVideo = getVideoById($editId);
}

$pageTitle = 'Video Management';
require_once __DIR__ . '/header.php';
?>

<div style="margin-bottom: 24px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-end; gap: 14px;">
  <div>
    <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin-bottom: 4px;">Platform Video Management</h1>
    <p style="color: var(--text-secondary); font-size: 0.88rem;">
      Add, replace, edit, or remove videos displayed to investors inside the platform. Direct MP4 links, YouTube, and Vimeo embeds are fully supported.
    </p>
  </div>
  <div>
    <a href="../videos.php" target="_blank" class="btn btn-secondary" style="font-size: 0.85rem; padding: 8px 16px; display: inline-flex; align-items: center; gap: 6px;">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
      View Platform Video Player &rarr;
    </a>
  </div>
</div>

<?php if ($success): ?>
  <div style="background: var(--accent-lime-soft); border: 1px solid var(--accent-lime); color: var(--accent-lime); padding: 14px 18px; border-radius: var(--radius-md); margin-bottom: 20px; font-size: 0.9rem;">
    <?php echo htmlspecialchars($success); ?>
  </div>
<?php endif; ?>

<?php if ($error): ?>
  <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.35); color: #ef4444; padding: 14px 18px; border-radius: var(--radius-md); margin-bottom: 20px; font-size: 0.9rem;">
    <?php echo htmlspecialchars($error); ?>
  </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 1.6fr; gap: 24px; align-items: start;">

  <!-- Form: Add or Edit Video -->
  <div class="card" style="margin-bottom: 0;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid var(--border-glass); padding-bottom: 12px;">
      <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin-bottom: 0; display: flex; align-items: center; gap: 8px;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--accent-lime)" stroke-width="2.5"><polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect></svg>
        <?php echo $editVideo ? 'Edit / Replace Video' : 'Add New Video'; ?>
      </h3>
      <?php if ($editVideo): ?>
        <a href="videos.php" class="btn btn-secondary" style="padding: 4px 10px; font-size: 0.75rem;">Cancel Edit</a>
      <?php endif; ?>
    </div>

    <form method="POST" action="videos.php">
      <input type="hidden" name="action" value="<?php echo $editVideo ? 'edit_video' : 'add_video'; ?>">
      <?php if ($editVideo): ?>
        <input type="hidden" name="video_id" value="<?php echo $editVideo['id']; ?>">
      <?php endif; ?>

      <div class="form-group">
        <label class="form-label">Video Title</label>
        <input type="text" name="title" class="form-input" placeholder="e.g. Platform Tutorial & Earning Guide" value="<?php echo htmlspecialchars($editVideo['title'] ?? ''); ?>" required>
      </div>

      <div class="form-group">
        <label class="form-label">Video Link or Embed Code</label>
        <input type="text" name="video_url" id="videoUrlInput" class="form-input" placeholder="e.g. https://www.youtube.com/watch?v=... or .mp4 URL" value="<?php echo htmlspecialchars($editVideo['video_url'] ?? ''); ?>" required oninput="previewVideo(this.value)">
        <small style="color: var(--text-muted); font-size: 0.75rem; margin-top: 4px; display: block;">
          Supports YouTube links, Vimeo links, Direct MP4/WebM video files, or iframe embed code.
        </small>
      </div>

      <div class="form-group">
        <label class="form-label">Description (Optional)</label>
        <textarea name="description" class="form-input" rows="2" placeholder="Brief summary of what the investor will learn from this video..." style="resize: vertical; font-size: 0.85rem;"><?php echo htmlspecialchars($editVideo['description'] ?? ''); ?></textarea>
      </div>

      <!-- Expiry Date & Time (Option 2 - Auto expire at midnight / custom time) -->
      <div class="form-group" style="background: rgba(147, 215, 38, 0.05); border: 1px dashed rgba(147, 215, 38, 0.35); border-radius: 8px; padding: 12px 14px; margin-bottom: 16px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
          <label class="form-label" style="margin-bottom: 0; font-weight: 700; color: var(--accent-lime); display: flex; align-items: center; gap: 6px;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            Auto Expiry (Raat 12 Baje / Custom)
          </label>
          <label style="font-size: 0.74rem; color: var(--text-muted); display: inline-flex; align-items: center; gap: 5px; cursor: pointer;">
            <input type="checkbox" name="no_expiry" id="noExpiryToggle" value="1" <?php echo ($editVideo && empty($editVideo['expires_at'])) ? 'checked' : ''; ?> onchange="toggleExpiryField(this.checked)" style="accent-color: var(--accent-lime);">
            No Expiry (Never expire)
          </label>
        </div>

        <div id="expiryFieldContainer" style="<?php echo ($editVideo && empty($editVideo['expires_at'])) ? 'display: none;' : ''; ?>">
          <?php
            $defaultExpiry = date('Y-m-d\T23:59');
            if ($editVideo && !empty($editVideo['expires_at'])) {
                $defaultExpiry = date('Y-m-d\TH:i', strtotime($editVideo['expires_at']));
            }
          ?>
          <input type="datetime-local" name="expires_at" id="expiresAtInput" class="form-input" value="<?php echo htmlspecialchars($defaultExpiry); ?>" style="font-family: inherit; font-size: 0.85rem;">
          
          <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px;">
            <button type="button" class="btn btn-secondary" style="padding: 3px 8px; font-size: 0.72rem;" onclick="setExpiryPreset('tonight')">
              🌙 Aaj Raat 11:59 PM (12:00 AM)
            </button>
            <button type="button" class="btn btn-secondary" style="padding: 3px 8px; font-size: 0.72rem;" onclick="setExpiryPreset('tomorrow')">
              📅 Kal Raat 11:59 PM
            </button>
          </div>
          <small style="color: var(--text-muted); font-size: 0.72rem; margin-top: 6px; display: block; line-height: 1.4;">
            Default settings ke mutabiq video <strong>aaj raat 11:59 PM (12:00 AM)</strong> par auto expire ho jayegi. Agar pehle band karni ho to status se <strong>Deactivate</strong> kar lein.
          </small>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
        <div class="form-group">
          <label class="form-label">Sort Order</label>
          <input type="number" name="sort_order" class="form-input" value="<?php echo (int)($editVideo['sort_order'] ?? 0); ?>">
          <small style="color: var(--text-muted); font-size: 0.72rem;">Lower numbers appear first</small>
        </div>

        <div class="form-group" style="display: flex; flex-direction: column; justify-content: center;">
          <label class="form-label">Status</label>
          <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.85rem; font-weight: 700; color: var(--text-main); margin-top: 6px;">
            <input type="checkbox" name="is_active" value="1" <?php echo (!$editVideo || !empty($editVideo['is_active'])) ? 'checked' : ''; ?> style="width: 18px; height: 18px; accent-color: var(--accent-lime); cursor: pointer;">
            Active (Visible to users)
          </label>
        </div>
      </div>

      <button type="submit" class="btn btn-pwa" style="width: 100%; padding: 11px; margin-top: 8px; font-size: 0.92rem;">
        <?php echo $editVideo ? 'Update Video Link & Details' : 'Add Video to Platform'; ?>
      </button>
    </form>
  </div>

  <!-- Videos List & Quick Controls Table -->
  <div class="card" style="margin-bottom: 0; padding: 0; overflow: hidden;">
    <div style="padding: 16px 20px; border-bottom: 1px solid var(--border-glass); display: flex; justify-content: space-between; align-items: center;">
      <h3 style="font-size: 1.05rem; font-weight: 800; color: var(--text-main); margin-bottom: 0;">
        Active Platform Videos (<?php echo count($videos); ?>)
      </h3>
      <span style="font-size: 0.75rem; color: var(--text-muted);">Real-time in-app player integration</span>
    </div>

    <?php if (empty($videos)): ?>
      <div style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
        No videos found. Use the form on the left to add a video.
      </div>
    <?php else: ?>
      <div style="display: flex; flex-direction: column;">
        <?php foreach ($videos as $v): 
          $parsed = parseVideoSource($v['video_url']);
          $isExpired = !empty($v['is_expired']);
        ?>
          <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 14px; padding: 16px 20px; border-bottom: 1px solid var(--border-glass); <?php echo ($isExpired || empty($v['is_active'])) ? 'opacity: 0.85; background: rgba(0,0,0,0.15);' : ''; ?>">
            
            <div style="display: flex; align-items: center; gap: 14px; flex: 1; min-width: 260px;">
              <!-- Video Type Badge / Thumbnail Icon -->
              <div style="width: 48px; height: 48px; border-radius: 10px; background: <?php echo (!$v['is_active'] || $isExpired) ? 'rgba(239, 68, 68, 0.12)' : 'rgba(147, 215, 38, 0.12)'; ?>; border: 1px solid var(--border-glass); display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: <?php echo (!$v['is_active'] || $isExpired) ? '#ef4444' : 'var(--accent-lime)'; ?>;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
              </div>

              <div>
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 2px;">
                  <strong style="font-size: 0.95rem; color: var(--text-main);"><?php echo htmlspecialchars($v['title']); ?></strong>
                  <?php if (!$v['is_active']): ?>
                    <span class="pwa-status-badge badge-secondary" style="font-size: 0.65rem; padding: 1px 6px; background: rgba(148, 163, 184, 0.15); color: #94a3b8; border: 1px solid rgba(148, 163, 184, 0.3);">Inactive (Deactivated)</span>
                  <?php elseif ($isExpired): ?>
                    <span class="pwa-status-badge badge-danger" style="font-size: 0.65rem; padding: 1px 6px; background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.35);">⚠️ Expired (12 AM)</span>
                  <?php else: ?>
                    <span class="pwa-status-badge badge-success" style="font-size: 0.65rem; padding: 1px 6px;">Active</span>
                  <?php endif; ?>
                </div>

                <div style="font-size: 0.78rem; color: var(--text-muted); line-height: 1.4; margin-bottom: 4px;">
                  <?php echo htmlspecialchars(substr($v['description'] ?? '', 0, 90)); ?><?php echo strlen($v['description'] ?? '') > 90 ? '...' : ''; ?>
                </div>

                <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 8px; font-size: 0.72rem;">
                  <span style="color: var(--accent-lime); text-transform: uppercase; font-weight: 700;">
                    Type: <?php echo htmlspecialchars($parsed['type'] ?? 'auto'); ?>
                  </span>
                  <span style="color: var(--text-muted);">&bull;</span>
                  <span style="color: <?php echo $isExpired ? '#ef4444' : 'var(--text-secondary)'; ?>; font-weight: 600;">
                    <?php if (!empty($v['expires_at'])): ?>
                      ⏱ Expiry: <?php echo date('d M, h:i A', strtotime($v['expires_at'])); ?>
                      <?php if ($isExpired): ?>
                        <span style="color: #ef4444;">(Expired)</span>
                      <?php else: ?>
                        <span style="color: var(--accent-lime);">(Active)</span>
                      <?php endif; ?>
                    <?php else: ?>
                      ⏱ Expiry: Never
                    <?php endif; ?>
                  </span>
                  <span style="color: var(--text-muted);">&bull;</span>
                  <code style="color: var(--text-secondary); max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: inline-block;">
                    <?php echo htmlspecialchars($v['video_url']); ?>
                  </code>
                </div>
              </div>
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; align-items: center; gap: 8px; flex-shrink: 0;">
              <?php if ($isExpired || empty($v['is_active'])): ?>
                <!-- Quick 1-Click Reactivate for Tonight -->
                <form method="POST" action="videos.php" style="display: inline;">
                  <input type="hidden" name="action" value="extend_midnight">
                  <input type="hidden" name="video_id" value="<?php echo $v['id']; ?>">
                  <button type="submit" class="btn btn-secondary" style="padding: 6px 10px; font-size: 0.75rem; border-color: var(--accent-lime); color: var(--accent-lime);" title="Reactivate with expiry Tonight 11:59 PM">
                    🌙 Reactivate Tonight
                  </button>
                </form>
              <?php endif; ?>

              <!-- Quick Status Toggle Form (Client can manually deactivate anytime) -->
              <form method="POST" action="videos.php" style="display: inline;">
                <input type="hidden" name="action" value="toggle_status">
                <input type="hidden" name="video_id" value="<?php echo $v['id']; ?>">
                <button type="submit" class="btn btn-secondary" style="padding: 6px 10px; font-size: 0.75rem; <?php echo $v['is_active'] ? 'color: #f59e0b; border-color: rgba(245,158,11,0.4);' : 'color: var(--accent-lime); border-color: var(--accent-lime);'; ?>" title="Toggle Visibility / Deactivate">
                  <?php echo $v['is_active'] ? 'Deactivate' : 'Activate'; ?>
                </button>
              </form>

              <!-- Edit Button -->
              <a href="videos.php?edit=<?php echo $v['id']; ?>" class="btn btn-secondary" style="padding: 6px 10px; font-size: 0.75rem;">
                Edit
              </a>

              <!-- Delete Form -->
              <form method="POST" action="videos.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to permanently delete this video?');">
                <input type="hidden" name="action" value="delete_video">
                <input type="hidden" name="video_id" value="<?php echo $v['id']; ?>">
                <button type="submit" class="btn btn-secondary" style="padding: 6px 10px; font-size: 0.75rem; color: #ef4444; border-color: rgba(239, 68, 68, 0.4);">
                  Delete
                </button>
              </form>
            </div>

          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</div>

<script>
function setExpiryPreset(type) {
  const input = document.getElementById('expiresAtInput');
  const noExpiry = document.getElementById('noExpiryToggle');
  if (noExpiry.checked) {
    noExpiry.checked = false;
    toggleExpiryField(false);
  }
  const now = new Date();
  if (type === 'tonight') {
    const yyyy = now.getFullYear();
    const mm = String(now.getMonth() + 1).padStart(2, '0');
    const dd = String(now.getDate()).padStart(2, '0');
    input.value = `${yyyy}-${mm}-${dd}T23:59`;
  } else if (type === 'tomorrow') {
    const tom = new Date(now.getTime() + 24 * 60 * 60 * 1000);
    const yyyy = tom.getFullYear();
    const mm = String(tom.getMonth() + 1).padStart(2, '0');
    const dd = String(tom.getDate()).padStart(2, '0');
    input.value = `${yyyy}-${mm}-${dd}T23:59`;
  }
}

function toggleExpiryField(isNoExpiry) {
  const container = document.getElementById('expiryFieldContainer');
  if (isNoExpiry) {
    container.style.display = 'none';
  } else {
    container.style.display = 'block';
  }
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
