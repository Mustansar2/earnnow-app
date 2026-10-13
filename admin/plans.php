<?php
require_once __DIR__ . '/auth.php';
$adminUser = requireAdmin();

$success = '';
$error = '';

// Handle Toggle Status (Quick 1-Click Action)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    $planId = (int)($_POST['plan_id'] ?? 0);
    $newStatus = (int)($_POST['new_status'] ?? 0);

    if ($planId <= 0) {
        $error = 'Invalid package specified.';
    } else {
        if (toggleInvestmentPlanStatus($planId, $newStatus)) {
            $statusLabel = $newStatus ? 'Activated' : 'Deactivated';
            $success = "Package #{$planId} successfully <strong>{$statusLabel}</strong>! Live users will see this update immediately.";
        } else {
            $error = 'Failed to update package status in database.';
        }
    }
}

// Handle Package Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_plan') {
    $planId = (int)($_POST['plan_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $dailyPct = (float)($_POST['daily_percentage'] ?? 1.0);
    $badge = trim($_POST['badge'] ?? '');
    $color = trim($_POST['color'] ?? '#10b981');
    $description = trim($_POST['description'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($planId <= 0 || empty($name) || $amount <= 0) {
        $error = 'Please provide a valid name and positive investment amount.';
    } else {
        $minWithdrawal = isset($_POST['min_withdrawal']) && (float)$_POST['min_withdrawal'] > 0
            ? (float)$_POST['min_withdrawal']
            : round($amount * 0.10, 2);

        $updateData = [
            'name' => $name,
            'amount' => $amount,
            'daily_percentage' => $dailyPct,
            'min_withdrawal' => $minWithdrawal,
            'badge' => $badge,
            'color' => $color,
            'description' => $description,
            'is_active' => $isActive
        ];

        if (updateInvestmentPlan($planId, $updateData)) {
            $success = "Package <strong>" . htmlspecialchars($name) . "</strong> updated successfully!";
        } else {
            $error = 'Failed to update package details.';
        }
    }
}

// Handle Add New Package
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_plan') {
    $name = trim($_POST['name'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $dailyPct = (float)($_POST['daily_percentage'] ?? 1.0);
    $badge = trim($_POST['badge'] ?? 'New Tier');
    $color = trim($_POST['color'] ?? '#10b981');
    $description = trim($_POST['description'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if (empty($name) || $amount <= 0) {
        $error = 'Please provide a valid package name and investment amount.';
    } else {
        $minWithdrawal = isset($_POST['min_withdrawal']) && (float)$_POST['min_withdrawal'] > 0
            ? (float)$_POST['min_withdrawal']
            : round($amount * 0.10, 2);

        $newData = [
            'name' => $name,
            'amount' => $amount,
            'daily_percentage' => $dailyPct,
            'min_withdrawal' => $minWithdrawal,
            'badge' => $badge,
            'color' => $color,
            'description' => $description,
            'is_active' => $isActive
        ];

        if (createInvestmentPlan($newData)) {
            $success = "New package <strong>" . htmlspecialchars($name) . "</strong> created and published!";
        } else {
            $error = 'Failed to create new package.';
        }
    }
}

// Load all plans
$plans = getInvestmentPlans(false);

$pageTitle = 'Manage Investment Packages';
require_once __DIR__ . '/header.php';
?>

<div style="margin-bottom: 24px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-end; gap: 14px;">
  <div>
    <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin-bottom: 4px;">
      Investment Packages Control
    </h1>
    <p style="color: var(--text-secondary); font-size: 0.88rem;">
      Active packages are visible to users on Registration and Upgrade screens. Deactivated packages are automatically hidden and cannot be selected.
    </p>
  </div>

  <button onclick="document.getElementById('new-plan-modal').style.display = 'flex';" class="btn btn-pwa" style="display: inline-flex; align-items: center; gap: 8px; font-size: 0.88rem; padding: 10px 18px;">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
    Create New Package
  </button>
</div>

<?php if ($success): ?>
  <div style="background: var(--accent-lime-soft); border: 1px solid var(--accent-lime); color: var(--accent-lime); padding: 14px 18px; border-radius: var(--radius-md); margin-bottom: 20px; font-size: 0.9rem;">
    <?php echo $success; ?>
  </div>
<?php endif; ?>

<?php if ($error): ?>
  <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.35); color: #ef4444; padding: 14px 18px; border-radius: var(--radius-md); margin-bottom: 20px; font-size: 0.9rem;">
    <?php echo htmlspecialchars($error); ?>
  </div>
<?php endif; ?>

<!-- Packages Grid -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 20px;">
  <?php foreach ($plans as $planId => $p): 
    $isActive = !empty($p['is_active']);
  ?>
    <div class="card" style="margin-bottom: 0; display: flex; flex-direction: column; justify-content: space-between; border-left: 4px solid <?php echo htmlspecialchars($p['color'] ?? 'var(--accent-lime)'); ?>; opacity: <?php echo $isActive ? '1' : '0.78'; ?>;">
      
      <div>
        <!-- Card Top Bar with Status Badge and 1-Click Toggle -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid var(--border-glass); padding-bottom: 12px;">
          <div>
            <span style="font-size: 0.7rem; font-weight: 800; text-transform: uppercase; padding: 3px 10px; border-radius: 20px; letter-spacing: 0.05em; background: <?php echo $isActive ? 'rgba(16, 185, 129, 0.15)' : 'rgba(239, 68, 68, 0.15)'; ?>; color: <?php echo $isActive ? '#10b981' : '#ef4444'; ?>; border: 1px solid <?php echo $isActive ? 'rgba(16, 185, 129, 0.4)' : 'rgba(239, 68, 68, 0.4)'; ?>;">
              <?php echo $isActive ? '● ACTIVE / LIVE' : '○ DEACTIVATED / HIDDEN'; ?>
            </span>
            <span style="font-size: 0.72rem; color: var(--text-muted); margin-left: 8px;">ID: #<?php echo $planId; ?></span>
          </div>

          <!-- Quick Toggle Form -->
          <form method="POST" action="plans.php" style="margin: 0;">
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="plan_id" value="<?php echo $planId; ?>">
            <input type="hidden" name="new_status" value="<?php echo $isActive ? '0' : '1'; ?>">
            <?php if ($isActive): ?>
              <button type="submit" class="btn" style="background: rgba(239, 68, 68, 0.12); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); font-size: 0.75rem; padding: 5px 12px; font-weight: 700;" onclick="return confirm('Deactivate this package? Users will no longer see or be able to choose it.');">
                Deactivate
              </button>
            <?php else: ?>
              <button type="submit" class="btn" style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.4); font-size: 0.75rem; padding: 5px 12px; font-weight: 700;">
                Activate
              </button>
            <?php endif; ?>
          </form>
        </div>

        <!-- Profit Summary Box -->
        <div style="background: var(--bg-subtle); border: 1px solid var(--border-glass); border-radius: var(--radius-md); padding: 12px 14px; margin-bottom: 16px; display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; text-align: center;">
          <div>
            <div style="font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Price</div>
            <div style="font-size: 1.15rem; font-weight: 900; color: var(--text-main); margin-top: 2px;">
              $<?php echo number_format($p['amount'], 0); ?>
            </div>
          </div>
          <div>
            <div style="font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Daily (<?php echo (float)$p['daily_percentage']; ?>%)</div>
            <div style="font-size: 1.15rem; font-weight: 900; color: #38bdf8; margin-top: 2px;">
              +$<?php echo number_format($p['daily_profit'], 2); ?>
            </div>
          </div>
          <div>
            <div style="font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Monthly</div>
            <div style="font-size: 1.15rem; font-weight: 900; color: var(--accent-lime); margin-top: 2px;">
              +$<?php echo number_format($p['monthly_profit'], 2); ?>
            </div>
          </div>
          <div>
            <div style="font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Min Withdraw</div>
            <div style="font-size: 1.15rem; font-weight: 900; color: #f59e0b; margin-top: 2px;">
              $<?php echo number_format($p['min_withdrawal'] ?? ($p['amount'] * 0.10), 0); ?>
            </div>
          </div>
        </div>

        <!-- Edit Form -->
        <form method="POST" action="plans.php">
          <input type="hidden" name="action" value="update_plan">
          <input type="hidden" name="plan_id" value="<?php echo $planId; ?>">

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
            <div class="form-group" style="margin-bottom: 0;">
              <label class="form-label">Package Name</label>
              <input type="text" name="name" class="form-input" value="<?php echo htmlspecialchars($p['name']); ?>" required>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
              <label class="form-label">Amount ($ USD)</label>
              <input type="number" step="1" name="amount" class="form-input" value="<?php echo (float)$p['amount']; ?>" required>
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
            <div class="form-group" style="margin-bottom: 0;">
              <label class="form-label">Daily Return %</label>
              <input type="number" step="0.1" name="daily_percentage" class="form-input" value="<?php echo (float)($p['daily_percentage'] ?? 1.0); ?>" required>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
              <label class="form-label">Min Profit Withdrawal ($)</label>
              <input type="number" step="1" name="min_withdrawal" class="form-input" value="<?php echo (float)($p['min_withdrawal'] ?? ($p['amount'] * 0.10)); ?>" required>
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
            <div class="form-group" style="margin-bottom: 0;">
              <label class="form-label">Badge Label</label>
              <input type="text" name="badge" class="form-input" value="<?php echo htmlspecialchars($p['badge'] ?? ''); ?>" placeholder="e.g. Popular Tier">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
              <label class="form-label">Accent Color</label>
              <input type="color" name="color" class="form-input" value="<?php echo htmlspecialchars($p['color'] ?? '#10b981'); ?>" style="height: 40px; padding: 2px 4px; cursor: pointer;">
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 12px; margin-bottom: 12px;">
            <div class="form-group" style="margin-bottom: 0;">
              <label class="form-label">Accent Color</label>
              <input type="color" name="color" class="form-input" value="<?php echo htmlspecialchars($p['color'] ?? '#10b981'); ?>" style="height: 40px; padding: 2px 4px; cursor: pointer;">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
              <label class="form-label">Visibility Status</label>
              <select name="is_active" class="form-input">
                <option value="1" <?php echo $isActive ? 'selected' : ''; ?>>Active (Visible to users)</option>
                <option value="0" <?php echo !$isActive ? 'selected' : ''; ?>>Deactivated (Hidden from users)</option>
              </select>
            </div>
          </div>

          <div class="form-group" style="margin-bottom: 14px;">
            <label class="form-label">Description / Feature Note</label>
            <input type="text" name="description" class="form-input" value="<?php echo htmlspecialchars($p['description'] ?? ''); ?>" placeholder="Brief description of perks">
          </div>

          <button type="submit" class="btn btn-pwa" style="width: 100%; padding: 10px; font-size: 0.88rem;">
            Save Package Changes
          </button>
        </form>

      </div>

    </div>
  <?php endforeach; ?>
</div>

<!-- Modal: Create New Package -->
<div id="new-plan-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.75); backdrop-filter: blur(6px); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
  <div class="card" style="width: 100%; max-width: 520px; background: var(--bg-card); border: 1px solid var(--border-glass); border-radius: var(--radius-lg); padding: 24px; position: relative;">
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid var(--border-glass); padding-bottom: 12px;">
      <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0;">
        Create New Investment Package
      </h3>
      <button onclick="document.getElementById('new-plan-modal').style.display = 'none';" style="background: none; border: none; color: var(--text-muted); font-size: 1.5rem; cursor: pointer; line-height: 1;">&times;</button>
    </div>

    <form method="POST" action="plans.php">
      <input type="hidden" name="action" value="create_plan">

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label">Package Name *</label>
          <input type="text" name="name" class="form-input" placeholder="e.g. Master Plan" required>
        </div>
        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label">Amount ($ USD) *</label>
          <input type="number" step="1" name="amount" class="form-input" placeholder="e.g. 1000" required>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label">Daily Return %</label>
          <input type="number" step="0.1" name="daily_percentage" class="form-input" value="1.0" required>
        </div>
        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label">Min Profit Withdrawal ($)</label>
          <input type="number" step="1" name="min_withdrawal" class="form-input" placeholder="e.g. 100 (Default: 10% of amount)">
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label">Badge Label</label>
          <input type="text" name="badge" class="form-input" placeholder="e.g. Elite Tier">
        </div>
        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label">Accent Color</label>
          <input type="color" name="color" class="form-input" value="#ec4899" style="height: 40px; padding: 2px 4px; cursor: pointer;">
        </div>
      </div>
        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label">Initial Status</label>
          <select name="is_active" class="form-input">
            <option value="1">Active (Immediately Visible)</option>
            <option value="0">Deactivated (Draft / Hidden)</option>
          </select>
        </div>
      </div>

      <div class="form-group" style="margin-bottom: 16px;">
        <label class="form-label">Description</label>
        <input type="text" name="description" class="form-input" placeholder="e.g. Guaranteed 1% daily return with dedicated VIP priority support.">
      </div>

      <div style="display: flex; gap: 10px; justify-content: flex-end;">
        <button type="button" onclick="document.getElementById('new-plan-modal').style.display = 'none';" class="btn" style="background: var(--bg-subtle); color: var(--text-secondary);">
          Cancel
        </button>
        <button type="submit" class="btn btn-pwa">
          Publish Package
        </button>
      </div>
    </form>

  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
