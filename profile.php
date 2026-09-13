<?php
define('EARNNOW_APP', true);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

// Auth guard
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$pageTitle = 'Account Profile & Settings';
require_once __DIR__ . '/includes/header.php';

$passwordMessage = '';
$passwordError = '';

// Handle password change form submission with database update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $passwordError = 'All password fields are required.';
    } elseif ($newPassword !== $confirmPassword) {
        $passwordError = 'New password and confirmation do not match.';
    } elseif (strlen($newPassword) < 6) {
        $passwordError = 'New password must be at least 6 characters long.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$currentUser['id']]);
        $row = $stmt->fetch();

        if ($row && password_verify($currentPassword, $row['password'])) {
            $newHashed = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateStmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $updateStmt->execute([$newHashed, $currentUser['id']]);
            $passwordMessage = 'Your password has been updated successfully.';
        } else {
            $passwordError = 'Incorrect current password.';
        }
    }
}

$userPlan = getPlanById($currentUser['plan_id']);
?>

  <div style="margin-bottom: 28px;">
    <h1 style="font-size: 1.85rem; font-weight: 800; color: var(--text-main); margin-bottom: 6px;">Profile & Account Settings</h1>
    <p style="color: var(--text-secondary); font-size: 0.95rem;">Manage your personal information, security credentials, and app preferences.</p>
  </div>

  <?php if ($passwordMessage): ?>
    <div style="background: var(--accent-lime-soft); border: 1px solid var(--accent-lime); color: var(--accent-lime); padding: 14px 18px; border-radius: var(--radius-lg); margin-bottom: 24px;">
      <?php echo htmlspecialchars($passwordMessage); ?>
    </div>
  <?php endif; ?>

  <?php if ($passwordError): ?>
    <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.35); color: #ef4444; padding: 14px 18px; border-radius: var(--radius-lg); margin-bottom: 24px;">
      <?php echo htmlspecialchars($passwordError); ?>
    </div>
  <?php endif; ?>

  <div class="profile-grid">
    <!-- Left Column: User Summary Card -->
    <aside>
      <div class="card user-summary">
        <div class="avatar">
          <?php echo strtoupper(substr($currentUser['name'], 0, 1)); ?>
        </div>
        <h2 style="font-size: 1.35rem; font-weight: 800; color: var(--text-main); margin-bottom: 4px;">
          <?php echo htmlspecialchars($currentUser['name']); ?>
        </h2>
        <p style="color: var(--text-secondary); font-size: 0.88rem; margin-bottom: 18px;">
          <?php echo htmlspecialchars($currentUser['email']); ?>
        </p>

        <div style="background: var(--accent-lime-soft); border: 1px solid var(--accent-lime); padding: 10px 18px; border-radius: 50px; display: inline-flex; align-items: center; gap: 8px; font-weight: 800; color: var(--accent-lime); font-size: 1rem;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          Balance: $<?php echo number_format($currentUser['balance'], 2); ?>
        </div>

        <div style="margin-top: 24px; padding-top: 18px; border-top: 1px solid var(--border-glass); text-align: left; font-size: 0.85rem; color: var(--text-secondary);">
          <div style="margin-bottom: 8px;"><strong>Account ID:</strong> #<?php echo htmlspecialchars($currentUser['id']); ?></div>
          <div style="margin-bottom: 8px;"><strong>Phone:</strong> <?php echo htmlspecialchars($currentUser['phone']); ?></div>
          <div style="margin-bottom: 8px;">
            <strong>Package:</strong> 
            <span style="color: var(--accent-lime); font-weight: 800;">
              <?php echo htmlspecialchars($currentUser['plan_name']); ?> ($<?php echo number_format($currentUser['plan_amount'], 0); ?>)
            </span>
          </div>
          <div>
            <strong>Monthly Profit:</strong> 
            <span style="color: var(--accent-lime); font-weight: 800;">+$<?php echo number_format($currentUser['monthly_profit'], 2); ?>/mo</span>
          </div>
        </div>
      </div>
    </aside>

    <!-- Right Column: Account & Security Actions -->
    <div style="display: flex; flex-direction: column; gap: 24px;">
      <!-- Profile Information Form -->
      <div class="card">
        <h3 class="card-title">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
            <circle cx="12" cy="7" r="4"></circle>
          </svg>
          Personal Details
        </h3>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
          <div class="form-group">
            <label class="form-label">Full Name</label>
            <input type="text" class="form-input" value="<?php echo htmlspecialchars($currentUser['name']); ?>" readonly>
          </div>
          <div class="form-group">
            <label class="form-label">Email Address</label>
            <input type="email" class="form-input" value="<?php echo htmlspecialchars($currentUser['email']); ?>" readonly>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
          <div class="form-group">
            <label class="form-label">Phone Number</label>
            <input type="tel" class="form-input" value="<?php echo htmlspecialchars($currentUser['phone']); ?>" readonly>
          </div>
          <div class="form-group">
            <label class="form-label">Active Plan & Monthly Profit</label>
            <input type="text" class="form-input" value="<?php echo htmlspecialchars($currentUser['plan_name']); ?> ($<?php echo number_format($currentUser['plan_amount'], 0); ?>) • +$<?php echo number_format($currentUser['monthly_profit'], 2); ?>/mo" readonly style="color: var(--accent-lime); font-weight: 700;">
          </div>
        </div>
      </div>

      <!-- Security & Installation Section -->
      <div class="card">
        <h3 class="card-title">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
          </svg>
          Security & App Options
        </h3>

        <div class="account-actions-box">
          <!-- Action 1: Change Password -->
          <div class="action-row">
            <div class="action-info">
              <h4 style="color: var(--text-main); font-weight: 700; margin-bottom: 2px;">Change Password</h4>
              <p style="color: var(--text-secondary); font-size: 0.85rem;">Update your account password to maintain security.</p>
            </div>
            <div>
              <button type="button" class="btn btn-secondary" onclick="togglePasswordForm()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M12 20h9"></path>
                  <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
                </svg>
                Change Password
              </button>
            </div>
          </div>

          <!-- Password Reset Sub-form (Collapsible) -->
          <div id="passwordFormCollapse" style="display: none; padding: 20px 0; border-bottom: 1px solid var(--border-glass);">
            <form method="POST" action="profile.php" style="background: var(--bg-subtle); padding: 18px; border-radius: var(--radius-md); border: 1px solid var(--border-glass);">
              <input type="hidden" name="action" value="change_password">
              
              <div class="form-group">
                <label class="form-label">Current Password</label>
                <input type="password" name="current_password" class="form-input" placeholder="Enter current password" required>
              </div>
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                <div class="form-group">
                  <label class="form-label">New Password</label>
                  <input type="password" name="new_password" class="form-input" placeholder="Minimum 6 characters" required>
                </div>
                <div class="form-group">
                  <label class="form-label">Confirm New Password</label>
                  <input type="password" name="confirm_password" class="form-input" placeholder="Repeat new password" required>
                </div>
              </div>
              <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 10px;">
                <button type="button" class="btn btn-secondary" onclick="togglePasswordForm()">Cancel</button>
                <button type="submit" class="btn btn-pwa">Update Password</button>
              </div>
            </form>
          </div>

          <!-- Action 2: Install App (Progressive Web App) -->
          <div class="action-row">
            <div class="action-info">
              <h4 style="color: var(--text-main); font-weight: 700; margin-bottom: 2px;">Install App</h4>
              <p style="color: var(--text-secondary); font-size: 0.85rem;">Install EarnNow on your mobile device for fast, app-like access without browser bars.</p>
              <div id="pwaStatusBadge" class="pwa-status-badge badge-info" style="margin-top: 6px;">Checking installability...</div>
            </div>
            <div>
              <button type="button" id="pwaInstallBtn" class="btn btn-pwa" aria-label="Install EarnNow App">
                <span class="pwa-btn-icon">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                  </svg>
                </span>
                <span class="pwa-btn-text">Install App</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

<script>
  function togglePasswordForm() {
    const form = document.getElementById('passwordFormCollapse');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
  }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
