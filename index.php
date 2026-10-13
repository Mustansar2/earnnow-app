<?php
define('EARNNOW_APP', true);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

// Auth guard: If user is not logged in, redirect to login page
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$pageTitle = 'My Dashboard';
require_once __DIR__ . '/includes/header.php';

// Fetch current user details & plan info
$userPlan = getPlanById($currentUser['plan_id'] ?? 1);
$packageStatus = $currentUser['package_status'] ?? 'pending';
$isActive = ($packageStatus === 'active');
$isPending = ($packageStatus === 'pending');
$isExpired = ($packageStatus === 'expired');
$isRejected = ($packageStatus === 'rejected');

$profitStats = getUserProfitStats($currentUser);
$minProfitWithdrawal = $profitStats['min_withdrawal'];
$dailyProfit = $isActive ? (float)($profitStats['daily_profit'] ?? ($currentUser['plan_amount'] * 0.01)) : 0.00;
$transactions = getUserTransactions($currentUser['id'], 10);
?>

  <!-- Dashboard Top Welcome & Quick Actions -->
  <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 14px; margin-bottom: 18px;">
    <div>
      <h1 style="font-size: 1.7rem; font-weight: 800; letter-spacing: -0.02em; color: var(--text-main); margin-bottom: 2px;">
        Welcome back, <?php echo htmlspecialchars($currentUser['name']); ?>!
      </h1>
      <p style="color: var(--text-secondary); font-size: 0.9rem;">
        Personal Investor Dashboard • Phone: <strong style="color: var(--accent-lime);"><?php echo htmlspecialchars($currentUser['phone']); ?></strong>
      </p>
    </div>

    <!-- Quick Deposit & Withdraw Action Buttons -->
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
      <a href="deposit.php" class="btn btn-pwa" style="padding: 9px 18px; font-size: 0.88rem;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <line x1="12" y1="5" x2="12" y2="19"></line>
          <line x1="5" y1="12" x2="19" y2="12"></line>
        </svg>
        Deposit Funds
      </a>
      <a href="withdraw.php" class="btn btn-secondary" style="padding: 9px 18px; font-size: 0.88rem;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
          <polyline points="7 10 12 15 17 10"></polyline>
          <line x1="12" y1="15" x2="12" y2="3"></line>
        </svg>
        Withdraw (TRC20)
      </a>
    </div>
  </div>

  <?php if (isset($_GET['registered'])): ?>
    <div style="background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.4); color: #f59e0b; padding: 14px 18px; border-radius: var(--radius-md); margin-bottom: 18px; display: flex; align-items: center; gap: 12px;">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink: 0;">
        <circle cx="12" cy="12" r="10"></circle>
        <polyline points="12 6 12 12 16 14"></polyline>
      </svg>
      <div>
        <strong style="color: var(--text-main);">Registration Successful!</strong> Your account is created. Your selected package (<strong><?php echo htmlspecialchars($currentUser['plan_name']); ?></strong>) has been submitted and is waiting for <strong>Admin Approval</strong>.
      </div>
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['deposit']) && $_GET['deposit'] === 'success'): ?>
    <div style="background: var(--accent-lime-soft); border: 1px solid var(--accent-lime); color: var(--accent-lime); padding: 14px 18px; border-radius: var(--radius-md); margin-bottom: 18px; display: flex; align-items: center; gap: 12px;">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink: 0;">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <div>
        <strong style="color: var(--text-main);">Deposit Receipt Uploaded Successfully!</strong> Your transaction screenshot has been received and is waiting for <strong>Admin Approval</strong>.
      </div>
    </div>
  <?php endif; ?>

  <?php if (!$isActive): ?>
  <!-- BIG ACTIVATION BANNER for pending/inactive users -->
  <div style="background: linear-gradient(135deg, rgba(147,215,38,0.10) 0%, rgba(245,158,11,0.10) 100%); border: 2px solid <?php echo $isPending ? '#f59e0b' : ($isRejected ? '#ef4444' : '#94a3b8'); ?>; border-radius: 16px; padding: 28px 28px 24px; margin-bottom: 22px; text-align: center; position: relative; overflow: hidden;">
    
    <!-- Background Glow -->
    <div style="position: absolute; top: -40px; left: 50%; transform: translateX(-50%); width: 200px; height: 200px; background: radial-gradient(circle, <?php echo $isPending ? 'rgba(245,158,11,0.15)' : 'rgba(147,215,38,0.12)'; ?> 0%, transparent 70%); pointer-events: none;"></div>
    
    <!-- Icon -->
    <div style="width: 64px; height: 64px; border-radius: 50%; background: <?php echo $isPending ? 'rgba(245,158,11,0.15)' : ($isRejected ? 'rgba(239,68,68,0.15)' : 'rgba(148,163,184,0.15)'); ?>; border: 2px solid <?php echo $isPending ? '#f59e0b' : ($isRejected ? '#ef4444' : '#94a3b8'); ?>; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; position: relative; z-index: 1;">
      <?php if ($isPending): ?>
        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2">
          <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
        </svg>
      <?php elseif ($isExpired): ?>
        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2">
          <path d="M21.5 2l-19 19M9 4H5a2 2 0 0 0-2 2v14l7-7 7 7V6a2 2 0 0 0-2-2h-4"/>
        </svg>
      <?php else: ?>
        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2">
          <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
        </svg>
      <?php endif; ?>
    </div>

    <!-- Title -->
    <h2 style="font-size: 1.5rem; font-weight: 900; color: var(--text-main); margin-bottom: 8px; position: relative; z-index: 1;">
      <?php if ($isPending): ?>
        ⏳ Package Activation Pending
      <?php elseif ($isExpired): ?>
        📦 Package Expired
      <?php else: ?>
        ❌ Package Rejected
      <?php endif; ?>
    </h2>

    <!-- Message -->
    <p style="color: var(--text-secondary); font-size: 0.95rem; max-width: 520px; margin: 0 auto 20px; line-height: 1.6; position: relative; z-index: 1;">
      <?php if ($isPending): ?>
        Aapka <strong style="color: var(--text-main);"><?php echo htmlspecialchars($currentUser['plan_name']); ?> ($<?php echo number_format($currentUser['plan_amount'] ?: 0, 0); ?> USD)</strong> package pending hai. 
        Admin approval ke baad aapka profit start hoga aur withdrawals unlock honge.
      <?php elseif ($isExpired): ?>
        Aapka package expire ho gaya hai. Nayi investment karein aur daily profit phir se shuru karein.
      <?php else: ?>
        Aapka package reject ho gaya. Koi doosra package select karein ya admin se rabta karein.
      <?php endif; ?>
    </p>

    <!-- Steps (only for pending) -->
    <?php if ($isPending): ?>
    <div style="display: flex; justify-content: center; gap: 12px; flex-wrap: wrap; margin-bottom: 22px; position: relative; z-index: 1;">
      <!-- Step 1 -->
      <div style="background: rgba(255,255,255,0.05); border: 1px solid var(--border-glass); border-radius: 12px; padding: 14px 18px; min-width: 140px; text-align: center;">
        <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--accent-lime); color: #000; font-weight: 900; font-size: 0.9rem; display: flex; align-items: center; justify-content: center; margin: 0 auto 8px;">1</div>
        <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-main); margin-bottom: 2px;">Deposit Karein</div>
        <div style="font-size: 0.7rem; color: var(--text-muted);">Amount bhejein</div>
      </div>
      <!-- Arrow -->
      <div style="display: flex; align-items: center; color: var(--text-muted); font-size: 1.2rem;">→</div>
      <!-- Step 2 -->
      <div style="background: rgba(255,255,255,0.05); border: 1px solid var(--border-glass); border-radius: 12px; padding: 14px 18px; min-width: 140px; text-align: center;">
        <div style="width: 32px; height: 32px; border-radius: 50%; background: rgba(245,158,11,0.2); border: 2px solid #f59e0b; color: #f59e0b; font-weight: 900; font-size: 0.9rem; display: flex; align-items: center; justify-content: center; margin: 0 auto 8px;">2</div>
        <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-main); margin-bottom: 2px;">Admin Review</div>
        <div style="font-size: 0.7rem; color: var(--text-muted);">24 hrs mein</div>
      </div>
      <!-- Arrow -->
      <div style="display: flex; align-items: center; color: var(--text-muted); font-size: 1.2rem;">→</div>
      <!-- Step 3 -->
      <div style="background: rgba(255,255,255,0.05); border: 1px solid var(--border-glass); border-radius: 12px; padding: 14px 18px; min-width: 140px; text-align: center;">
        <div style="width: 32px; height: 32px; border-radius: 50%; background: rgba(147,215,38,0.1); border: 2px solid rgba(147,215,38,0.4); color: rgba(147,215,38,0.6); font-weight: 900; font-size: 0.9rem; display: flex; align-items: center; justify-content: center; margin: 0 auto 8px;">3</div>
        <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); margin-bottom: 2px;">Active!</div>
        <div style="font-size: 0.7rem; color: var(--text-muted);">Profit start</div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Action Buttons -->
    <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; position: relative; z-index: 1;">
      <?php if ($isPending || $isRejected): ?>
        <a href="deposit.php" class="btn btn-pwa" style="padding: 11px 24px; font-size: 0.92rem; font-weight: 800;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="display:inline;vertical-align:middle;margin-right:6px;">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          Deposit Karein & Activate Karein
        </a>
        <a href="plans.php" class="btn btn-secondary" style="padding: 11px 24px; font-size: 0.92rem;">
          Packages Dekhein
        </a>
      <?php else: ?>
        <a href="plans.php" class="btn btn-pwa" style="padding: 11px 24px; font-size: 0.92rem; font-weight: 800;">
          Nayi Package Select Karein
        </a>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Main 2-Column Responsive Dashboard Grid (Fits without vertical scrolling) -->
  <div class="dashboard-grid">
    <!-- 1. Package & Profit Hero Card -->
    <div class="card" style="display: flex; flex-direction: column; justify-content: space-between; overflow: hidden; margin-bottom: 0;">
      <div>
        <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 14px;">
          <div>
            <?php if ($isActive): ?>
              <div style="display: inline-flex; align-items: center; gap: 6px; background: var(--accent-lime-soft); border: 1px solid var(--accent-lime); color: var(--accent-lime); padding: 2px 8px; border-radius: 20px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; margin-bottom: 6px;">
                <span style="width: 6px; height: 6px; background: var(--accent-lime); border-radius: 50%; display: inline-block; box-shadow: 0 0 6px var(--accent-lime);"></span>
                Active Investment Package
              </div>
            <?php elseif ($isPending): ?>
              <div style="display: inline-flex; align-items: center; gap: 6px; background: rgba(245, 158, 11, 0.15); border: 1px solid #f59e0b; color: #f59e0b; padding: 2px 8px; border-radius: 20px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; margin-bottom: 6px;">
                <span style="width: 6px; height: 6px; background: #f59e0b; border-radius: 50%; display: inline-block; box-shadow: 0 0 6px #f59e0b;"></span>
                Pending Admin Approval
              </div>
            <?php elseif ($isExpired): ?>
              <div style="display: inline-flex; align-items: center; gap: 6px; background: rgba(148, 163, 184, 0.15); border: 1px solid #94a3b8; color: #94a3b8; padding: 2px 8px; border-radius: 20px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; margin-bottom: 6px;">
                <span style="width: 6px; height: 6px; background: #94a3b8; border-radius: 50%; display: inline-block;"></span>
                Package Expired (Capital Withdrawn)
              </div>
            <?php else: ?>
              <div style="display: inline-flex; align-items: center; gap: 6px; background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #ef4444; padding: 2px 8px; border-radius: 20px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; margin-bottom: 6px;">
                <span style="width: 6px; height: 6px; background: #ef4444; border-radius: 50%; display: inline-block;"></span>
                Package Rejected
              </div>
            <?php endif; ?>

            <h2 style="font-size: 1.5rem; font-weight: 800; color: var(--text-main); margin-bottom: 2px;">
              <?php echo htmlspecialchars($currentUser['plan_name']); ?>
            </h2>
            <p style="color: var(--text-secondary); font-size: 0.82rem;">
              <?php if ($isActive): ?>
                Guaranteed return • Automated daily calculation.
              <?php elseif ($isPending): ?>
                Application submitted • Awaiting Admin verification &amp; activation.
              <?php elseif ($isExpired): ?>
                Capital fully withdrawn • Account active • Select a new package to resume.
              <?php else: ?>
                Application not approved. Please select another package.
              <?php endif; ?>
            </p>
          </div>

          <div style="text-align: right;">
            <div style="font-size: 0.72rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">Invested Amount</div>
            <div style="font-size: 1.85rem; font-weight: 900; color: var(--text-main); letter-spacing: -0.02em; line-height: 1.1;">
              $<?php echo number_format($currentUser['plan_amount'], 2); ?> <span style="font-size: 0.85rem; color: <?php echo $isActive ? 'var(--accent-lime)' : '#f59e0b'; ?>; font-weight: 700;">USD</span>
            </div>
            <?php if ($isPending): ?>
              <span style="font-size: 0.72rem; color: #f59e0b; font-weight: 600;">(Pending Approval)</span>
            <?php elseif ($isExpired): ?>
              <span style="font-size: 0.72rem; color: #94a3b8; font-weight: 600;">(Fully Withdrawn)</span>
            <?php endif; ?>
          </div>
        </div>

        <!-- Monthly Profit & Earnings Metrics Highlight -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; padding-top: 14px; border-top: 1px solid var(--border-glass);">
          <div style="background: var(--bg-subtle); border: 1px solid var(--border-glass); border-radius: var(--radius-md); padding: 10px 12px;">
            <div style="font-size: 0.7rem; color: var(--text-secondary); font-weight: 700; text-transform: uppercase; margin-bottom: 2px;">Monthly Target</div>
            <div style="font-size: 1.35rem; font-weight: 900; color: <?php echo $isActive ? 'var(--accent-lime)' : 'var(--text-muted)'; ?>; line-height: 1.1;">
              <?php if ($isActive): ?>
                +$<?php echo number_format($currentUser['monthly_profit'], 2); ?>
              <?php elseif ($isPending): ?>
                $<?php echo number_format($currentUser['monthly_profit'], 2); ?> <span style="font-size: 0.7rem; font-weight: 500;">(Pending)</span>
              <?php else: ?>
                $0.00
              <?php endif; ?>
            </div>
            <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 2px;">
              <?php echo $isActive ? ($userPlan['profit_percentage'] . '% • 30 Days') : ($isPending ? 'Activates on approval' : 'Inactive'); ?>
            </div>
          </div>

          <div style="background: var(--bg-subtle); border: 1px solid var(--border-glass); border-radius: var(--radius-md); padding: 10px 12px;">
            <div style="font-size: 0.7rem; color: var(--text-secondary); font-weight: 700; text-transform: uppercase; margin-bottom: 2px;">Daily Return</div>
            <div style="font-size: 1.35rem; font-weight: 900; color: <?php echo $isActive ? '#38bdf8' : 'var(--text-muted)'; ?>; line-height: 1.1;">
              <?php if ($isActive): ?>
                ~$<?php echo number_format($dailyProfit, 2); ?>
              <?php else: ?>
                $0.00
              <?php endif; ?>
            </div>
            <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 2px;">
              <?php echo $isActive ? '1% daily rate' : ($isPending ? 'Waiting for admin' : 'Package expired'); ?>
            </div>
          </div>

          <div style="background: var(--bg-subtle); border: 1px solid <?php echo $profitStats['can_withdraw'] ? 'var(--accent-lime)' : 'var(--border-glass)'; ?>; border-radius: var(--radius-md); padding: 10px 12px;">
            <div style="font-size: 0.7rem; color: var(--text-secondary); font-weight: 700; text-transform: uppercase; margin-bottom: 2px;">Earned Profit</div>
            <div style="font-size: 1.35rem; font-weight: 900; color: <?php echo $profitStats['can_withdraw'] ? 'var(--accent-lime)' : '#f59e0b'; ?>; line-height: 1.1;">
              +$<?php echo number_format($profitStats['available_profit'], 2); ?>
            </div>
            <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 2px;">
              <?php echo $profitStats['can_withdraw'] ? '✓ Ready to withdraw' : ('Threshold: $' . number_format($minProfitWithdrawal, 0)); ?>
            </div>
          </div>

          <div style="background: var(--bg-subtle); border: 1px solid var(--border-glass); border-radius: var(--radius-md); padding: 10px 12px;">
            <div style="font-size: 0.7rem; color: var(--text-secondary); font-weight: 700; text-transform: uppercase; margin-bottom: 2px;">Wallet Balance</div>
            <div style="font-size: 1.35rem; font-weight: 900; color: #a5b4fc; line-height: 1.1;">
              $<?php echo number_format($currentUser['balance'], 2); ?>
            </div>
            <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 2px;">Max 1 Hr Payout</div>
          </div>
        </div>

        <!-- Profit Withdrawal Threshold Notice -->
        <?php if ($isActive): ?>
          <?php if (!$profitStats['can_withdraw']): ?>
            <div style="margin-top: 14px; background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.35); border-radius: var(--radius-md); padding: 12px 14px;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; flex-wrap: wrap; gap: 8px;">
                <span style="font-size: 0.82rem; font-weight: 700; color: #f59e0b; display: flex; align-items: center; gap: 6px;">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                  Withdrawal Milestone: Unlocks at $<?php echo number_format($minProfitWithdrawal, 0); ?>.00 Profit
                </span>
                <span style="font-size: 0.78rem; font-weight: 800; color: var(--text-main);">
                  $<?php echo number_format($profitStats['available_profit'], 2); ?> / $<?php echo number_format($minProfitWithdrawal, 2); ?>
                </span>
              </div>
              <div style="width: 100%; height: 6px; background: rgba(255,255,255,0.08); border-radius: 4px; overflow: hidden; margin-bottom: 6px;">
                <div style="width: <?php echo $profitStats['progress_percent']; ?>%; height: 100%; background: linear-gradient(90deg, #f59e0b, var(--accent-lime)); border-radius: 4px;"></div>
              </div>
              <div style="font-size: 0.74rem; color: var(--text-secondary);">
                Need <strong>$<?php echo number_format($profitStats['remaining_to_unlock'], 2); ?> USD</strong> more profit to withdraw ($100 plan unlocks after $10, $200 after $20, $300 after $30, $500 after $50).
              </div>
            </div>
          <?php else: ?>
            <div style="margin-top: 14px; background: var(--accent-lime-soft); border: 1px solid var(--accent-lime); border-radius: var(--radius-md); padding: 12px 14px; display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap;">
              <div style="font-size: 0.82rem; color: var(--text-main); display: flex; align-items: center; gap: 8px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--accent-lime)" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                <span><strong>Withdrawal Milestone Unlocked!</strong> You have <strong>+$<?php echo number_format($profitStats['available_profit'], 2); ?> USD</strong> available profit.</span>
              </div>
              <a href="withdraw.php" class="btn btn-pwa" style="font-size: 0.78rem; padding: 5px 12px;">Withdraw Now &rarr;</a>
            </div>
          <?php endif; ?>
        <?php endif; ?>

        <!-- Informational Status Notices -->
        <?php if ($isPending): ?>
          <div style="margin-top: 14px; background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.35); border-radius: var(--radius-md); padding: 12px 14px; display: flex; align-items: flex-start; gap: 10px; font-size: 0.84rem; color: #f59e0b;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink: 0; margin-top: 1px;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            <div>
              <strong style="color: var(--text-main);">Package Awaiting Admin Approval:</strong> Your account is registered. The administrator will review and approve your package shortly. Once approved, your daily profit calculation and withdrawals will be active.
              <div style="margin-top: 6px;">
                <a href="deposit.php" style="color: var(--accent-lime); font-weight: 700; text-decoration: underline;">Submit Deposit Verification &rarr;</a>
              </div>
            </div>
          </div>
        <?php elseif ($isExpired): ?>
          <div style="margin-top: 14px; background: rgba(148, 163, 184, 0.12); border: 1px solid rgba(148, 163, 184, 0.3); border-radius: var(--radius-md); padding: 12px 14px; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
            <div style="font-size: 0.84rem; color: var(--text-secondary);">
              <strong style="color: var(--text-main);">Package Expired:</strong> Your actual invested amount has been fully withdrawn. Your account remains active! Select a new package to resume daily profits.
            </div>
            <a href="plans.php" class="btn btn-pwa" style="font-size: 0.8rem; padding: 6px 14px;">Select New Package &rarr;</a>
          </div>
        <?php elseif ($isRejected): ?>
          <div style="margin-top: 14px; background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.35); border-radius: var(--radius-md); padding: 12px 14px; font-size: 0.84rem; color: #ef4444; display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap;">
            <div>
              <strong style="color: var(--text-main);">Package Application Rejected:</strong> Please select an alternate package or contact admin support.
            </div>
            <a href="plans.php" class="btn btn-secondary" style="font-size: 0.8rem; padding: 6px 14px; border-color: #ef4444; color: #ef4444;">Choose Package &rarr;</a>
          </div>
        <?php endif; ?>
      </div>

      <!-- Upcoming Upgrade Notice if scheduled -->
      <?php if (!empty($currentUser['next_plan_id'])): 
        $nextPlan = getPlanById($currentUser['next_plan_id']);
        $effDate = date('M 01, Y', strtotime($currentUser['upgrade_effective_date'] ?? 'first day of next month'));
      ?>
        <div style="background: rgba(56, 189, 248, 0.12); border: 1px solid rgba(56, 189, 248, 0.4); border-radius: var(--radius-md); padding: 8px 12px; margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 0.78rem;">
          <span style="color: #38bdf8; display: inline-flex; align-items: center; gap: 6px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <strong>Scheduled Upgrade:</strong> <?php echo htmlspecialchars($nextPlan['name']); ?> ($<?php echo number_format($nextPlan['amount'], 0); ?> USD) activates on <strong><?php echo $effDate; ?></strong>
          </span>
          <a href="plans.php" style="color: #38bdf8; text-decoration: underline; font-weight: 700; white-space: nowrap;">Manage</a>
        </div>
      <?php endif; ?>

      <!-- Quick Highlights Bar -->
      <div style="margin-top: 14px; padding-top: 12px; border-top: 1px solid var(--border-glass); display: flex; align-items: center; justify-content: space-between; gap: 10px; font-size: 0.78rem;">
        <span style="color: var(--text-muted); display: inline-flex; align-items: center; gap: 6px;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--accent-lime)" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          Bank-Grade Security • TRC-20 Fast Network
        </span>
        <a href="plans.php" style="color: var(--accent-lime); text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
          Upgrade / View Packages &rarr;
        </a>
      </div>
    </div>

    <!-- 2. Recent Transaction History Table -->
    <div class="card" style="display: flex; flex-direction: column; overflow: hidden; margin-bottom: 0;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
        <h3 class="card-title" style="margin-bottom: 0; font-size: 1.05rem;">
          <span style="display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 6px; background: var(--accent-lime-soft); color: var(--accent-lime); flex-shrink: 0;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"></circle>
              <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
          </span>
          Recent Activity
        </h3>
        <div style="font-size: 0.72rem; color: var(--text-muted); background: var(--bg-subtle); padding: 3px 8px; border-radius: 12px; border: 1px solid var(--border-glass);">
          9:00 AM - 9:00 PM • 1 Hr SLA
        </div>
      </div>

      <?php if (empty($transactions)): ?>
        <div style="text-align: center; padding: 28px 14px; color: var(--text-secondary); margin: auto 0;">
          <p style="font-size: 0.88rem; margin-bottom: 12px;">No deposits or withdrawals yet.</p>
          <div style="display: flex; gap: 8px; justify-content: center;">
            <a href="deposit.php" class="btn btn-pwa" style="font-size: 0.8rem; padding: 6px 14px;">Make Deposit</a>
            <a href="withdraw.php" class="btn btn-secondary" style="font-size: 0.8rem; padding: 6px 14px;">Withdraw</a>
          </div>
        </div>
      <?php else: ?>
        <div style="overflow-x: auto; flex: 1;">
          <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.82rem;">
            <thead>
              <tr style="border-bottom: 1px solid var(--border-glass); color: var(--text-muted); text-transform: uppercase; font-size: 0.7rem;">
                <th style="padding: 8px 10px;">Type</th>
                <th style="padding: 8px 10px;">Amount</th>
                <th style="padding: 8px 10px;">Method</th>
                <th style="padding: 8px 10px;">Status</th>
                <th style="padding: 8px 10px;">Date</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach (array_slice($transactions, 0, 5) as $tx): 
                $isDeposit = ($tx['type'] === 'deposit');
              ?>
                <tr style="border-bottom: 1px solid var(--border-glass);">
                  <td style="padding: 10px;">
                    <span style="display: inline-flex; align-items: center; gap: 4px; font-weight: 700; font-size: 0.8rem; color: <?php echo $isDeposit ? 'var(--accent-lime)' : '#38bdf8'; ?>;">
                      <?php if ($isDeposit): ?>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg> Deposit
                      <?php else: ?>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg> 
                        Withdraw
                      <?php endif; ?>
                    </span>
                  </td>
                  <td style="padding: 10px; font-weight: 800; color: var(--text-main);">
                    <?php echo $isDeposit ? '+' : '-'; ?>$<?php echo number_format($tx['amount'], 2); ?>
                  </td>
                  <td style="padding: 10px; color: var(--text-secondary); font-size: 0.78rem;">
                    <?php echo htmlspecialchars($tx['method']); ?>
                  </td>
                  <td style="padding: 10px;">
                    <?php 
                      $badgeClass = 'badge-warning';
                      if ($tx['status'] === 'Under Review') $badgeClass = 'badge-info';
                      if ($tx['status'] === 'Approved') $badgeClass = 'badge-success';
                      if ($tx['status'] === 'Rejected') $badgeClass = 'badge-danger';
                      if ($tx['status'] === 'Failed') $badgeClass = 'badge-secondary';
                    ?>
                    <span class="pwa-status-badge <?php echo $badgeClass; ?>" style="font-size: 0.7rem; padding: 2px 7px;">
                      <?php echo htmlspecialchars($tx['status']); ?>
                    </span>
                  </td>
                  <td style="padding: 10px; color: var(--text-muted); font-size: 0.75rem; white-space: nowrap;">
                    <?php echo date('M d, H:i', strtotime($tx['created_at'])); ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Daily Video Task & Training Banner -->
  <?php 
    $activeVideos = getVideos(true);
    $userWatchedTodayRow = function_exists('hasWatchedTodayVideo') ? hasWatchedTodayVideo($currentUser['id']) : null;
    $userWatchedToday = ($userWatchedTodayRow && $userWatchedTodayRow['watch_date'] === date('Y-m-d'));
    $todayClaimed = $profitStats['today_claimed'] ?? false;
    $hasVideosToShow = !empty($activeVideos) || $userWatchedToday || $todayClaimed;

    if ($hasVideosToShow): 
      $featuredVid = !empty($activeVideos) ? $activeVideos[0] : ['id' => $userWatchedTodayRow['video_id'] ?? 0];
      $missedDays = (int)($profitStats['missed_days'] ?? 0);
      $missedProfit = (float)($profitStats['missed_profit'] ?? 0.00);
  ?>
    <div style="background: <?php echo $todayClaimed ? 'var(--bg-card)' : 'linear-gradient(135deg, rgba(245, 158, 11, 0.12) 0%, rgba(147, 215, 38, 0.12) 100%)'; ?>; border: 1.5px solid <?php echo $todayClaimed ? 'var(--accent-lime)' : ($isActive ? '#f59e0b' : 'var(--border-glass)'); ?>; border-radius: var(--radius-md); padding: 14px 18px; margin-bottom: 16px; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 14px;">
      <div style="display: flex; align-items: center; gap: 12px;">
        <div style="width: 44px; height: 44px; border-radius: 12px; background: <?php echo $todayClaimed ? 'rgba(147, 215, 38, 0.15)' : ($isActive ? 'rgba(245, 158, 11, 0.2)' : 'var(--bg-subtle)'); ?>; border: 1px solid <?php echo $todayClaimed ? 'var(--accent-lime)' : ($isActive ? '#f59e0b' : 'var(--border-glass)'); ?>; display: flex; align-items: center; justify-content: center; color: <?php echo $todayClaimed ? 'var(--accent-lime)' : ($isActive ? '#f59e0b' : 'var(--text-muted)'); ?>; flex-shrink: 0;">
          <?php if ($todayClaimed): ?>
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
          <?php else: ?>
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
          <?php endif; ?>
        </div>
        <div>
          <div style="font-size: 0.88rem; font-weight: 800; color: var(--text-main); display: flex; flex-wrap: wrap; align-items: center; gap: 8px;">
            <span>Daily Video Task:</span>
            <?php if ($todayClaimed): ?>
              <span style="font-size: 0.72rem; background: var(--accent-lime-soft); color: var(--accent-lime); border: 1px solid var(--accent-lime); padding: 1px 8px; border-radius: 10px;">✅ Completed Today</span>
            <?php elseif ($userWatchedToday): ?>
              <span style="font-size: 0.72rem; background: var(--accent-lime-soft); color: var(--accent-lime); border: 1px solid var(--accent-lime); padding: 1px 8px; border-radius: 10px;">🎉 Watched &bull; Ready to Claim</span>
            <?php elseif ($isActive): ?>
              <span style="font-size: 0.72rem; background: rgba(245,158,11,0.15); color: #f59e0b; border: 1px solid #f59e0b; padding: 1px 8px; border-radius: 10px;">⏳ Pending Today (+$<?php echo number_format($dailyProfit, 2); ?> USD)</span>
            <?php else: ?>
              <span style="font-size: 0.72rem; background: var(--bg-subtle); color: var(--text-muted); padding: 1px 8px; border-radius: 10px;">Package Inactive</span>
            <?php endif; ?>

            <!-- Missed Days Badge -->
            <?php if ($isActive): ?>
              <?php if ($missedDays > 0): ?>
                <span style="font-size: 0.72rem; background: rgba(239,68,68,0.15); color: #ef4444; border: 1px solid rgba(239,68,68,0.35); padding: 1px 8px; border-radius: 10px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                  Missed This Month: <?php echo $missedDays; ?> <?php echo $missedDays == 1 ? 'Day' : 'Days'; ?> (-$<?php echo number_format($missedProfit, 2); ?>)
                </span>
              <?php else: ?>
                <span style="font-size: 0.72rem; background: rgba(16,185,129,0.15); color: var(--accent-lime); border: 1px solid var(--accent-lime); padding: 1px 8px; border-radius: 10px; font-weight: 700;">
                  🌟 0 Missed Days
                </span>
              <?php endif; ?>
            <?php endif; ?>
          </div>
          <div style="font-size: 0.78rem; color: var(--text-secondary); margin-top: 3px;">
            <?php if ($todayClaimed): ?>
              Aaj ki video watch ho chuki hai aur +$<?php echo number_format($dailyProfit, 2); ?> USD profit aapke account me add ho gaya hai.
              <?php if ($missedDays > 0): ?>
                <span style="color: #ef4444; font-weight: 600;">(Is mahine <?php echo $missedDays; ?> din video miss hone par reward lapse hua).</span>
              <?php endif; ?>
            <?php elseif ($isActive): ?>
              Daily video dekhein aur aaj ka +$<?php echo number_format($dailyProfit, 2); ?> USD profit claim karein. Raat 12 baje ke baad pichla profit lapse ho jata hai.
              <?php if ($missedDays > 0): ?>
                <span style="color: #ef4444; font-weight: 700;">(Pichle <?php echo $missedDays; ?> din miss hone ki wajah se lapse hue).</span>
              <?php endif; ?>
            <?php else: ?>
              Watch official platform tutorials and daily task videos inside the app.
            <?php endif; ?>
          </div>
        </div>
      </div>

      <a href="videos.php?v=<?php echo $featuredVid['id']; ?>" class="btn <?php echo ($isActive && !$todayClaimed) ? 'btn-pwa' : 'btn-secondary'; ?>" style="font-size: 0.82rem; padding: 8px 16px; display: inline-flex; align-items: center; gap: 6px; <?php echo ($todayClaimed || !$isActive) ? 'border-color: var(--accent-lime); color: var(--accent-lime);' : 'font-weight: 800;'; ?>">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
        <?php echo ($isActive && !$todayClaimed) ? 'Watch Video & Claim Profit (+$' . number_format($dailyProfit, 2) . ') &rarr;' : 'Watch Videos &rarr;'; ?>
      </a>
    </div>
  <?php endif; ?>

  <!-- Sleek Compact PWA Quick Bar -->
  <div style="background: var(--bg-card); border: 1px solid var(--border-glass); border-radius: var(--radius-md); padding: 10px 18px; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px;">
    <div style="display: flex; align-items: center; gap: 10px;">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--accent-lime)" stroke-width="2">
        <rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect>
        <line x1="12" y1="18" x2="12.01" y2="18"></line>
      </svg>
      <span style="font-size: 0.84rem; color: var(--text-secondary);">
        Install <strong style="color: var(--text-main);">EarnNow</strong> on your smartphone for one-tap access and live alerts.
      </span>
    </div>
    <a href="profile.php" class="btn btn-pwa" style="padding: 6px 14px; font-size: 0.78rem;">
      Install Mobile App
    </a>
  </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
