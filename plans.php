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

$db = getDB();
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$currentUser = $stmt->fetch();
if (!$currentUser) {
    header('Location: login.php');
    exit;
}

checkAndApplyPendingUpgrade($currentUser);

$error = '';
$success = '';
$nextMonthStartDate = date('Y-m-01', strtotime('first day of next month'));
$nextMonthFormatted = date('F 01, Y', strtotime('first day of next month'));

// Handle Direct Subscription for Expired, Rejected, or Pending Users
if (isset($_POST['action']) && $_POST['action'] === 'subscribe_plan') {
    $targetPlanId = intval($_POST['plan_id'] ?? 0);
    $targetPlan = getPlanById($targetPlanId);

    if (!$targetPlan || empty($targetPlan['is_active'])) {
        $error = 'This investment package is currently deactivated and not available.';
    } else {
        $stmt = $db->prepare("
            UPDATE users SET 
                plan_id = ?, 
                plan_name = ?, 
                plan_amount = ?, 
                initial_plan_amount = ?, 
                monthly_profit = ?, 
                package_status = 'pending', 
                package_approved_at = NULL 
            WHERE id = ?
        ");
        $stmt->execute([
            $targetPlan['id'],
            $targetPlan['name'],
            $targetPlan['amount'],
            $targetPlan['amount'],
            $targetPlan['monthly_profit'],
            $currentUser['id']
        ]);

        $currentUser['plan_id'] = $targetPlan['id'];
        $currentUser['plan_name'] = $targetPlan['name'];
        $currentUser['plan_amount'] = $targetPlan['amount'];
        $currentUser['initial_plan_amount'] = $targetPlan['amount'];
        $currentUser['monthly_profit'] = $targetPlan['monthly_profit'];
        $currentUser['package_status'] = 'pending';
        $_SESSION['user'] = $currentUser;

        $success = 'Application for <strong>' . htmlspecialchars($targetPlan['name']) . ' ($' . number_format($targetPlan['amount'], 0) . ' USD)</strong> submitted! It is now waiting for <strong>Admin Approval</strong>.';
    }
}

// Handle Upgrade Request for Active Users (POST or GET)
$targetPlanId = 0;
if (isset($_POST['action']) && $_POST['action'] === 'upgrade_plan') {
    $targetPlanId = intval($_POST['plan_id'] ?? 0);
} elseif (isset($_GET['upgrade'])) {
    $targetPlanId = intval($_GET['upgrade']);
}

if ($targetPlanId > 0 && (!isset($_POST['action']) || $_POST['action'] !== 'subscribe_plan')) {
    $targetPlan = getPlanById($targetPlanId);

    if (!$targetPlan || empty($targetPlan['is_active'])) {
        $error = 'This investment package is currently deactivated and not available for upgrades.';
    } elseif ($targetPlanId === (int)$currentUser['plan_id'] && ($currentUser['package_status'] ?? 'pending') === 'active') {
        $error = 'You are already active on this package.';
    } else {
        // Schedule upgrade for month start
        $upStmt = $db->prepare("
            UPDATE users 
            SET next_plan_id = ?, upgrade_effective_date = ? 
            WHERE id = ?
        ");
        $upStmt->execute([$targetPlanId, $nextMonthStartDate, $currentUser['id']]);

        $currentUser['next_plan_id'] = $targetPlanId;
        $currentUser['upgrade_effective_date'] = $nextMonthStartDate;
        $_SESSION['user'] = $currentUser;

        $success = 'Package upgrade to <strong>' . htmlspecialchars($targetPlan['name']) . ' ($' . number_format($targetPlan['amount'], 0) . ' USD)</strong> scheduled! It will officially activate on <strong>' . $nextMonthFormatted . '</strong> (Start of next month).';
    }
}

// Handle Cancel Upgrade Request
if ((isset($_POST['action']) && $_POST['action'] === 'cancel_upgrade') || isset($_GET['cancel_upgrade'])) {
    $cancelStmt = $db->prepare("
        UPDATE users 
        SET next_plan_id = NULL, upgrade_effective_date = NULL 
        WHERE id = ?
    ");
    $cancelStmt->execute([$currentUser['id']]);

    $currentUser['next_plan_id'] = null;
    $currentUser['upgrade_effective_date'] = null;
    $_SESSION['user'] = $currentUser;

    $success = 'Scheduled package upgrade has been cancelled.';
}

$pageTitle = 'Investment Packages';
require_once __DIR__ . '/includes/header.php';
?>

  <!-- Header Banner -->
  <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 14px; margin-bottom: 18px;">
    <div>
      <h1 style="font-size: 1.75rem; font-weight: 800; letter-spacing: -0.02em; color: var(--text-main); margin-bottom: 2px;">
        Investment Packages
      </h1>
      <p style="color: var(--text-secondary); font-size: 0.9rem;">
        Transparent automated returns credited daily. Upgrades become active at the start of the next month.
      </p>
    </div>

    <a href="deposit.php" class="btn btn-pwa" style="padding: 8px 16px; font-size: 0.85rem;">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
      Deposit Funds
    </a>
  </div>

  <?php if ($success): ?>
    <div style="background: var(--accent-lime-soft); border: 1px solid var(--accent-lime); color: var(--accent-lime); padding: 14px 18px; border-radius: var(--radius-md); margin-bottom: 18px; display: flex; align-items: center; gap: 12px;">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <div style="color: var(--text-main); font-size: 0.9rem;">
        <?php echo $success; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.35); color: #ef4444; padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 18px; font-size: 0.9rem;">
      <?php echo htmlspecialchars($error); ?>
    </div>
  <?php endif; ?>

  <!-- Month-Start Upgrade Policy Notice -->
  <div class="card" style="margin-bottom: 18px; padding: 14px 20px; background: var(--bg-card); border-left: 4px solid var(--accent-lime);">
    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px;">
      <div style="display: flex; align-items: center; gap: 12px;">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--accent-lime)" stroke-width="2">
          <circle cx="12" cy="12" r="10"></circle>
          <polyline points="12 6 12 12 16 14"></polyline>
        </svg>
        <div>
          <strong style="color: var(--text-main); font-size: 0.92rem;">Package Upgrade Cycle Rule</strong>
          <p style="font-size: 0.82rem; color: var(--text-secondary); margin: 0;">
            You can request an upgrade on any day. The upgraded package and increased daily return will activate on <strong><?php echo $nextMonthFormatted; ?></strong> (1st of next month).
          </p>
        </div>
      </div>

      <?php if (!empty($currentUser['next_plan_id'])): 
        $nextPlanInfo = getPlanById($currentUser['next_plan_id']);
      ?>
        <div style="display: flex; align-items: center; gap: 10px;">
          <span style="font-size: 0.8rem; background: var(--accent-lime-soft); color: var(--accent-lime); border: 1px solid var(--accent-lime); padding: 4px 10px; border-radius: 20px; font-weight: 700;">
            Upcoming: <?php echo htmlspecialchars($nextPlanInfo['name']); ?> ($<?php echo number_format($nextPlanInfo['amount'], 0); ?>)
          </span>
          <form method="POST" action="plans.php" style="margin: 0;">
            <input type="hidden" name="action" value="cancel_upgrade">
            <button type="submit" class="btn btn-secondary" style="padding: 4px 10px; font-size: 0.75rem; color: var(--accent-rose);">
              Cancel
            </button>
          </form>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Investment Packages Grid (Compact No-Scroll Layout) -->
  <?php $allPlans = getInvestmentPlans(false); ?>
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 14px; margin-bottom: 12px;">
    <?php foreach ($allPlans as $planId => $plan): 
      $isActive = !empty($plan['is_active']);
      $isCurrent = ($currentUser['plan_id'] == $planId);
      $isScheduled = ($currentUser['next_plan_id'] == $planId);
      $isUpgrade = ($plan['amount'] > $currentUser['plan_amount']);

      // If deactivated and not user's current or scheduled plan, hide from public view
      if (!$isActive && !$isCurrent && !$isScheduled) {
          continue;
      }
    ?>
      <div class="card" style="display: flex; flex-direction: column; justify-content: space-between; padding: 14px 18px; position: relative; border-color: <?php echo $isCurrent ? 'var(--accent-lime)' : ($isScheduled ? '#38bdf8' : 'var(--border-glass)'); ?>; <?php if ($isCurrent) echo 'box-shadow: 0 0 20px rgba(147, 215, 38, 0.14);'; ?>">
        
        <!-- Plan Header & Badges -->
        <div>
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
            <span style="font-size: 0.68rem; font-weight: 800; text-transform: uppercase; padding: 2px 8px; border-radius: 20px; letter-spacing: 0.05em; background: <?php echo $isCurrent ? 'var(--accent-lime-soft)' : ($isScheduled ? 'rgba(56, 189, 248, 0.15)' : 'var(--bg-subtle)'); ?>; color: <?php echo $isCurrent ? 'var(--accent-lime)' : ($isScheduled ? '#38bdf8' : 'var(--text-muted)'); ?>; border: 1px solid <?php echo $isCurrent ? 'var(--accent-lime)' : ($isScheduled ? '#38bdf8' : 'var(--border-glass)'); ?>;">
              <?php 
                if ($isCurrent) echo '● ACTIVE PLAN';
                elseif ($isScheduled) echo '★ SCHEDULED NEXT MONTH';
                else echo htmlspecialchars($plan['badge']);
              ?>
            </span>

            <span style="font-size: 0.7rem; color: var(--text-muted);">
              30-Day Cycle
            </span>
          </div>

          <h3 style="font-size: 1.3rem; font-weight: 800; color: var(--text-main); margin-bottom: 2px;">
            <?php echo htmlspecialchars($plan['name']); ?>
          </h3>
          <p style="color: var(--text-secondary); font-size: 0.78rem; margin-bottom: 10px;">
            <?php echo htmlspecialchars($plan['description']); ?>
          </p>

          <!-- Invested Amount -->
          <div style="background: var(--bg-subtle); border: 1px solid var(--border-glass); border-radius: var(--radius-md); padding: 8px 12px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
            <div style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Investment</div>
            <div style="font-size: 1.55rem; font-weight: 900; color: var(--text-main); letter-spacing: -0.02em; line-height: 1;">
              $<?php echo number_format($plan['amount'], 2); ?> <span style="font-size: 0.75rem; color: var(--accent-lime); font-weight: 700;">USD</span>
            </div>
          </div>

          <!-- Profit Breakdown: Daily & Monthly -->
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 12px;">
            <div style="background: var(--bg-card); border: 1px solid var(--border-glass); border-radius: var(--radius-md); padding: 8px 10px;">
              <div style="font-size: 0.68rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Daily Profit (1%)</div>
              <div style="font-size: 1.15rem; font-weight: 900; color: #38bdf8; margin-top: 1px; line-height: 1.1;">
                +$<?php echo number_format($plan['daily_profit'], 2); ?>
              </div>
              <div style="font-size: 0.66rem; color: var(--text-muted); margin-top: 1px;">1% daily return</div>
            </div>

            <div style="background: var(--bg-card); border: 1px solid var(--border-glass); border-radius: var(--radius-md); padding: 8px 10px;">
              <div style="font-size: 0.68rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Monthly (30 Days)</div>
              <div style="font-size: 1.15rem; font-weight: 900; color: var(--accent-lime); margin-top: 1px; line-height: 1.1;">
                +$<?php echo number_format($plan['monthly_profit'], 2); ?>
              </div>
              <div style="font-size: 0.66rem; color: var(--text-muted); margin-top: 1px;">30% monthly</div>
            </div>
          </div>

          <!-- Features List -->
          <?php $planMinWithdrawal = (float)($plan['min_withdrawal'] ?? ($plan['amount'] * 0.10)); ?>
          <ul style="list-style: none; padding: 0; margin: 0 0 12px 0; font-size: 0.78rem; color: var(--text-secondary); display: flex; flex-direction: column; gap: 5px;">
            <li style="display: flex; align-items: center; gap: 6px;">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--accent-lime)" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
              Withdraw Profit After <strong style="color: var(--accent-lime);">$<?php echo number_format($planMinWithdrawal, 0); ?> Profit</strong> (10% Threshold)
            </li>
            <li style="display: flex; align-items: center; gap: 6px;">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--accent-lime)" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
              Withdraw Daily Profit (9:00 AM - 9:00 PM)
            </li>
            <li style="display: flex; align-items: center; gap: 6px;">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--accent-lime)" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
              USDT (TRC-20) / Easypaisa 1-Hour Payout
            </li>
            <li style="display: flex; align-items: center; gap: 6px;">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--accent-lime)" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
              Capital Returned at 30-Day Cycle End
            </li>
          </ul>
        </div>

        <!-- Action / Upgrade Button -->
        <div style="padding-top: 10px; border-top: 1px solid var(--border-glass);">
          <?php 
            $userPkgStatus = $currentUser['package_status'] ?? 'pending';
            if ($isCurrent && $userPkgStatus === 'active'): 
          ?>
            <div style="text-align: center; padding: 8px; background: var(--accent-lime-soft); color: var(--accent-lime); border: 1px solid var(--accent-lime); border-radius: var(--radius-md); font-weight: 800; font-size: 0.82rem;">
              ✓ Current Active Package
            </div>
          <?php elseif ($isCurrent && $userPkgStatus === 'pending'): ?>
            <div style="text-align: center; padding: 8px; background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid #f59e0b; border-radius: var(--radius-md); font-weight: 800; font-size: 0.82rem;">
              ⏳ Awaiting Admin Approval
            </div>
          <?php elseif ($isScheduled): ?>
            <div style="text-align: center; padding: 8px; background: rgba(56, 189, 248, 0.15); color: #38bdf8; border: 1px solid #38bdf8; border-radius: var(--radius-md); font-weight: 800; font-size: 0.82rem;">
              ★ Upgrading on <?php echo date('M 01', strtotime('first day of next month')); ?>
            </div>
          <?php elseif ($userPkgStatus === 'expired' || $userPkgStatus === 'rejected' || $userPkgStatus === 'pending'): ?>
            <form method="POST" action="plans.php">
              <input type="hidden" name="action" value="subscribe_plan">
              <input type="hidden" name="plan_id" value="<?php echo $planId; ?>">
              <button type="submit" class="btn btn-pwa" style="width: 100%; padding: 9px 14px; font-size: 0.84rem;">
                <?php echo ($userPkgStatus === 'expired') ? 'Subscribe to this Package' : 'Select this Package'; ?> &rarr;
              </button>
            </form>
          <?php else: ?>
            <form method="POST" action="plans.php">
              <input type="hidden" name="action" value="upgrade_plan">
              <input type="hidden" name="plan_id" value="<?php echo $planId; ?>">
              <button type="submit" class="btn btn-pwa" style="width: 100%; padding: 9px 14px; font-size: 0.84rem;">
                <?php echo $isUpgrade ? 'Upgrade to this Package' : 'Switch to this Package'; ?> &rarr;
              </button>
            </form>
          <?php endif; ?>
        </div>

      </div>
    <?php endforeach; ?>
  </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
