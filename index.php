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
$userPlan = getPlanById($currentUser['plan_id']);
$dailyProfit = $userPlan['daily_profit'] ?? ($currentUser['monthly_profit'] / 30);
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
    <div style="background: var(--accent-lime-soft); border: 1px solid var(--accent-lime); color: var(--accent-lime); padding: 12px 18px; border-radius: var(--radius-md); margin-bottom: 18px; display: flex; align-items: center; gap: 12px;">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <div>
        <strong style="color: var(--text-main);">Registration Successful!</strong> Your account is active and your package has been assigned.
      </div>
    </div>
  <?php endif; ?>

  <!-- Main 2-Column Responsive Dashboard Grid (Fits without vertical scrolling) -->
  <div class="dashboard-grid">
    <!-- 1. Active Package & Monthly Profit Hero Card -->
    <div class="card" style="display: flex; flex-direction: column; justify-content: space-between; overflow: hidden; margin-bottom: 0;">
      <div>
        <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 14px;">
          <div>
            <div style="display: inline-flex; align-items: center; gap: 6px; background: var(--accent-lime-soft); border: 1px solid var(--accent-lime); color: var(--accent-lime); padding: 2px 8px; border-radius: 20px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; margin-bottom: 6px;">
              <span style="width: 6px; height: 6px; background: var(--accent-lime); border-radius: 50%; display: inline-block; box-shadow: 0 0 6px var(--accent-lime);"></span>
              Active Investment Package
            </div>
            <h2 style="font-size: 1.5rem; font-weight: 800; color: var(--text-main); margin-bottom: 2px;">
              <?php echo htmlspecialchars($currentUser['plan_name']); ?>
            </h2>
            <p style="color: var(--text-secondary); font-size: 0.82rem;">
              Guaranteed return • Automated daily calculation.
            </p>
          </div>

          <div style="text-align: right;">
            <div style="font-size: 0.72rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">Invested Amount</div>
            <div style="font-size: 1.85rem; font-weight: 900; color: var(--text-main); letter-spacing: -0.02em; line-height: 1.1;">
              $<?php echo number_format($currentUser['plan_amount'], 2); ?> <span style="font-size: 0.85rem; color: var(--accent-lime); font-weight: 700;">USD</span>
            </div>
          </div>
        </div>

        <!-- Monthly Profit & Earnings Metrics Highlight -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; padding-top: 14px; border-top: 1px solid var(--border-glass);">
          <div style="background: var(--bg-subtle); border: 1px solid var(--border-glass); border-radius: var(--radius-md); padding: 10px 12px;">
            <div style="font-size: 0.7rem; color: var(--text-secondary); font-weight: 700; text-transform: uppercase; margin-bottom: 2px;">Monthly Profit</div>
            <div style="font-size: 1.35rem; font-weight: 900; color: var(--accent-lime); line-height: 1.1;">
              +$<?php echo number_format($currentUser['monthly_profit'], 2); ?>
            </div>
            <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 2px;">
              <?php echo $userPlan['profit_percentage']; ?>% • Any Day
            </div>
          </div>

          <div style="background: var(--bg-subtle); border: 1px solid var(--border-glass); border-radius: var(--radius-md); padding: 10px 12px;">
            <div style="font-size: 0.7rem; color: var(--text-secondary); font-weight: 700; text-transform: uppercase; margin-bottom: 2px;">Daily Return</div>
            <div style="font-size: 1.35rem; font-weight: 900; color: #38bdf8; line-height: 1.1;">
              ~$<?php echo number_format($dailyProfit, 2); ?>
            </div>
            <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 2px;">Credited daily</div>
          </div>

          <div style="background: var(--bg-subtle); border: 1px solid var(--border-glass); border-radius: var(--radius-md); padding: 10px 12px;">
            <div style="font-size: 0.7rem; color: var(--text-secondary); font-weight: 700; text-transform: uppercase; margin-bottom: 2px;">Wallet Balance</div>
            <div style="font-size: 1.35rem; font-weight: 900; color: #a5b4fc; line-height: 1.1;">
              $<?php echo number_format($currentUser['balance'], 2); ?>
            </div>
            <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 2px;">Max 1 Hr Payout</div>
          </div>
        </div>
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
                      if ($tx['status'] === 'Approved') $badgeClass = 'badge-success';
                      if ($tx['status'] === 'Rejected') $badgeClass = 'badge-danger';
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
