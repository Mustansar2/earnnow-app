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

$error = '';
$success = '';

$isWithinHours = isWithinWithdrawalHours();
$capitalEligibility = checkCapitalWithdrawalEligibility($currentUser['created_at'] ?? date('Y-m-d H:i:s'));
$userPlan = getPlanById($currentUser['plan_id'] ?? 1);
$profitStats = getUserProfitStats($currentUser);
$minProfitWithdrawal = $profitStats['min_withdrawal'];

// Available withdrawal methods
$availableMethods = $WITHDRAWAL_METHODS ?? [
    'easypaisa' => [
        'id' => 'easypaisa',
        'name' => 'Easypaisa',
        'badge' => 'Instant Mobile Account',
        'channel' => 'Easypaisa Wallet'
    ],
    'jazzcash' => [
        'id' => 'jazzcash',
        'name' => 'JazzCash',
        'badge' => 'Instant Mobile Account',
        'channel' => 'JazzCash Wallet'
    ],
    'bank' => [
        'id' => 'bank',
        'name' => 'Bank Transfer',
        'badge' => 'Direct Bank (Raast / IBFT)',
        'channel' => 'Pakistani Banks'
    ],
    'trc20' => [
        'id' => 'trc20',
        'name' => 'USDT (TRC20)',
        'badge' => 'Tether Tron Network',
        'channel' => 'Crypto Wallet'
    ]
];

$selectedCategory = $_POST['withdrawal_category'] ?? 'profit';
$selectedMethod = $_POST['withdrawal_method'] ?? 'easypaisa';
if (!array_key_exists($selectedMethod, $availableMethods)) {
    $selectedMethod = 'easypaisa';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $withdrawalCategory = $_POST['withdrawal_category'] ?? 'profit';
    $methodKey = trim($_POST['withdrawal_method'] ?? 'easypaisa');
    $amount = floatval($_POST['amount'] ?? 0);

    // Inputs
    $accountTitle = trim($_POST['account_title'] ?? '');
    $accountNumber = trim($_POST['account_number'] ?? '');
    $bankName = trim($_POST['bank_name'] ?? '');
    $bankAccountTitle = trim($_POST['bank_account_title'] ?? '');
    $bankAccountNumber = trim($_POST['bank_account_number'] ?? '');
    $trcWallet = trim($_POST['trc_wallet'] ?? '');

    $methodDisplayName = $availableMethods[$methodKey]['name'] ?? 'Easypaisa';
    $accountOrWallet = '';

    $packageStatus = $currentUser['package_status'] ?? 'pending';

    if ($packageStatus !== 'active') {
        if ($packageStatus === 'pending') {
            $error = 'Your package is currently pending Admin Approval. Withdrawals will unlock once approved.';
        } elseif ($packageStatus === 'expired') {
            $error = 'Your package has expired because your actual capital was fully withdrawn. Please subscribe to a new package to resume earnings and withdrawals.';
        } else {
            $error = 'Your package is currently inactive or rejected. Please subscribe to an active package to withdraw.';
        }
    } elseif (!$isWithinHours) {
        $error = 'Withdrawals can only be submitted between 9:00 AM and 9:00 PM. Please return during active hours.';
    } elseif (!array_key_exists($methodKey, $availableMethods)) {
        $error = 'Please select a valid withdrawal method.';
    } elseif ($withdrawalCategory === 'profit' && !$profitStats['can_withdraw']) {
        $error = "Withdrawal locked. You currently have $" . number_format($profitStats['available_profit'], 2) . " available. On your " . htmlspecialchars($userPlan['name']) . " ($" . number_format($currentUser['plan_amount'], 0) . " USD), withdrawals unlock once you reach at least $" . number_format($minProfitWithdrawal, 2) . " profit ($" . number_format($profitStats['remaining_to_unlock'], 2) . " remaining).";
    } elseif ($withdrawalCategory === 'profit' && $amount < $minProfitWithdrawal) {
        $error = "Minimum profit withdrawal for " . htmlspecialchars($userPlan['name']) . " ($" . number_format($currentUser['plan_amount'], 0) . " USD) is $" . number_format($minProfitWithdrawal, 2) . " USD (100$ after 10$, 200$ after 20$, 300$ after 30$, 500$ after 50$).";
    } elseif ($withdrawalCategory === 'profit' && $amount > $profitStats['available_profit']) {
        $error = "Requested amount ($" . number_format($amount, 2) . ") exceeds your available balance ($" . number_format($profitStats['available_profit'], 2) . ").";
    } elseif ($withdrawalCategory === 'capital' && $amount < 10) {
        $error = 'Minimum capital withdrawal amount is $10.00 USD.';
    } else {
        // Validate method specific details
        if ($methodKey === 'easypaisa' || $methodKey === 'jazzcash') {
            if (empty($accountTitle)) {
                $error = "Please enter your {$methodDisplayName} account title / receiver name.";
            } elseif (empty($accountNumber)) {
                $error = "Please enter your {$methodDisplayName} mobile account number.";
            } else {
                $digitsOnly = preg_replace('/[^0-9]/', '', $accountNumber);
                if (strlen($digitsOnly) < 10 || strlen($digitsOnly) > 13) {
                    $error = "Please enter a valid 11-digit mobile number for {$methodDisplayName} (e.g. 03451234567).";
                } else {
                    $accountOrWallet = $accountNumber . ' (' . $accountTitle . ')';
                }
            }
        } elseif ($methodKey === 'bank') {
            $effectiveBankTitle = !empty($bankAccountTitle) ? $bankAccountTitle : $accountTitle;
            $effectiveBankNumber = !empty($bankAccountNumber) ? $bankAccountNumber : $accountNumber;
            if (empty($bankName)) {
                $error = 'Please enter your bank name (e.g. Meezan Bank, HBL, Allied Bank).';
            } elseif (empty($effectiveBankTitle)) {
                $error = 'Please enter your bank account title.';
            } elseif (empty($effectiveBankNumber)) {
                $error = 'Please enter your account number or IBAN.';
            } else {
                $accountOrWallet = $bankName . ' - ' . $effectiveBankNumber . ' (' . $effectiveBankTitle . ')';
            }
        } elseif ($methodKey === 'trc20') {
            if (empty($trcWallet)) {
                $error = 'Please enter your valid USDT (TRC-20) wallet address.';
            } elseif (strlen($trcWallet) < 25) {
                $error = 'The TRC-20 wallet address entered is too short or invalid.';
            } else {
                $accountOrWallet = $trcWallet;
            }
        }

        // Check category rules and balances
        if (empty($error)) {
            if ($withdrawalCategory === 'capital') {
                $pendingCapital = (float)$db->query("
                    SELECT SUM(amount) FROM transactions 
                    WHERE user_id = " . (int)$currentUser['id'] . " 
                    AND type = 'withdraw' 
                    AND category = 'capital' 
                    AND status IN ('Pending', 'Under Review')
                ")->fetchColumn();

                $availableCapital = max(0, (float)$currentUser['plan_amount'] - $pendingCapital);

                if (!$capitalEligibility['eligible']) {
                    $error = 'Actual invested plan capital can only be withdrawn at the end of the monthly cycle (30 days). ' . 
                             $capitalEligibility['days_remaining'] . ' days remaining for your plan.';
                } elseif ($amount > $availableCapital) {
                    $error = 'You cannot withdraw more than your available package capital ($' . number_format($availableCapital, 2) . ').' . 
                             ($pendingCapital > 0 ? ' (Pending request: $' . number_format($pendingCapital, 2) . ')' : '');
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO transactions (user_id, type, category, amount, method, account_or_wallet, status, note)
                        VALUES (?, 'withdraw', 'capital', ?, ?, ?, 'Pending', ?)
                    ");
                    $note = "Monthly capital withdrawal via {$methodDisplayName} (Max 1 hour SLA)";
                    $stmt->execute([$currentUser['id'], $amount, $methodDisplayName, $accountOrWallet, $note]);

                    $success = "Capital withdrawal request of $" . number_format($amount, 2) . " via {$methodDisplayName} submitted! When approved by admin, funds will be transferred within 1 hour.";
                }
            } else {
                // Profit Withdrawal
                $stmt = $db->prepare("
                    INSERT INTO transactions (user_id, type, category, amount, method, account_or_wallet, status, note)
                    VALUES (?, 'withdraw', 'profit', ?, ?, ?, 'Pending', ?)
                ");
                $note = "Earned daily profit withdrawal via {$methodDisplayName} (Max 1 hour SLA)";
                $stmt->execute([$currentUser['id'], $amount, $methodDisplayName, $accountOrWallet, $note]);

                if ($currentUser['balance'] >= $amount) {
                    $newBalance = max(0, $currentUser['balance'] - $amount);
                    $updateStmt = $db->prepare("UPDATE users SET balance = ? WHERE id = ?");
                    $updateStmt->execute([$newBalance, $currentUser['id']]);
                    $currentUser['balance'] = $newBalance;
                    $_SESSION['user']['balance'] = $newBalance;
                }

                // Refresh profit stats after transaction
                $profitStats = getUserProfitStats($currentUser);

                $success = "Profit withdrawal of $" . number_format($amount, 2) . " via {$methodDisplayName} submitted successfully! Your transfer to " . htmlspecialchars($accountOrWallet) . " will be completed within 1 hour.";
            }
        }
    }
}

$pageTitle = 'Withdraw Funds (Easypaisa, JazzCash, USDT)';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-2col-grid">
  <!-- Left Side: Header, Guarantee Badge & Operating Hours -->
  <div>
    <div style="margin-bottom: 18px;">
      <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin-bottom: 4px;">Withdraw Funds</h1>
      <p style="color: var(--text-secondary); font-size: 0.9rem;">Fast payout directly to your Easypaisa, JazzCash, Bank Account, or USDT (TRC-20) wallet.</p>
    </div>

    <!-- 1-Hour SLA Processing Guarantee Badge -->
    <div style="background: var(--accent-lime-soft); border: 1px solid var(--accent-lime); border-radius: var(--radius-lg); padding: 16px 18px; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between; gap: 12px;">
      <div style="display: flex; align-items: center; gap: 12px;">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--accent-lime)" stroke-width="2">
          <circle cx="12" cy="12" r="10"></circle>
          <polyline points="12 6 12 12 16 14"></polyline>
        </svg>
        <div>
          <strong style="color: var(--text-main); font-size: 0.95rem;">Max 1 Hour Transfer Guarantee</strong>
          <p style="font-size: 0.8rem; color: var(--text-secondary); margin: 0;">Once submitted, your withdrawal is transferred within 1 hour.</p>
        </div>
      </div>
      <span style="background: var(--bg-card); border: 1px solid var(--border-glass); color: var(--accent-lime); font-size: 0.72rem; font-weight: 800; padding: 4px 10px; border-radius: 20px; white-space: nowrap;">
        Instant Payout
      </span>
    </div>

    <!-- Operational Hours Indicator (9:00 AM - 9:00 PM) -->
    <div style="background: <?php echo $isWithinHours ? 'var(--accent-lime-soft)' : 'rgba(239, 68, 68, 0.12)'; ?>; border: 1px solid <?php echo $isWithinHours ? 'var(--accent-lime)' : 'rgba(239, 68, 68, 0.35)'; ?>; border-radius: var(--radius-md); padding: 12px 16px; margin-bottom: 16px; display: flex; align-items: center; gap: 10px;">
      <span style="width: 10px; height: 10px; border-radius: 50%; background: <?php echo $isWithinHours ? 'var(--accent-lime)' : '#ef4444'; ?>; display: inline-block; box-shadow: 0 0 8px <?php echo $isWithinHours ? 'var(--accent-lime)' : '#ef4444'; ?>;"></span>
      <div style="font-size: 0.84rem; color: <?php echo $isWithinHours ? 'var(--accent-lime)' : '#ef4444'; ?>;">
        <strong style="color: var(--text-main);">Hours: 9:00 AM - 9:00 PM</strong> • 
        <?php echo $isWithinHours ? 'Processing window is OPEN.' : 'Currently CLOSED. Opens at 9:00 AM.'; ?>
      </div>
    </div>

    <!-- Policy Card -->
    <div class="card" style="font-size: 0.85rem; color: var(--text-secondary); line-height: 1.6;">
      <strong style="color: var(--text-main); display: block; margin-bottom: 6px;">Withdrawal Policy &amp; Thresholds:</strong>
      • <strong style="color: var(--accent-lime);">$100 Starter Plan:</strong> Withdrawal after <strong>$10 profit</strong><br>
      • <strong style="color: var(--accent-lime);">$200 Growth Plan:</strong> Withdrawal after <strong>$20 profit</strong><br>
      • <strong style="color: var(--accent-lime);">$300 Pro Plan:</strong> Withdrawal after <strong>$30 profit</strong><br>
      • <strong style="color: var(--accent-lime);">$500 VIP Pro Plan:</strong> Withdrawal after <strong>$50 profit</strong><br>
      • <strong style="color: #38bdf8;">Plan Capital:</strong> Unlocked at 30-day month end.<br>
      • Supported channels: <strong style="color: #10b981;">Easypaisa</strong>, <strong style="color: #f59e0b;">JazzCash</strong>, <strong style="color: #0ea5e9;">Bank Transfer</strong> &amp; <strong style="color: var(--accent-lime);">USDT TRC-20</strong>.<br>
      • Processing time: <strong>Under 1 hour</strong> during operational hours (9:00 AM - 9:00 PM).
    </div>
  </div>

  <!-- Right Side: Withdrawal Form & Alerts -->
  <div>
    <?php if ($success): ?>
      <div style="background: var(--accent-lime-soft); border: 1px solid var(--accent-lime); color: var(--accent-lime); padding: 16px 20px; border-radius: var(--radius-lg); margin-bottom: 18px;">
        <div style="display: flex; align-items: center; gap: 10px; font-weight: 700; margin-bottom: 4px; color: var(--text-main);">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
          Withdrawal Submitted (1 Hour Max SLA)
        </div>
        <p style="font-size: 0.92rem; color: var(--text-secondary);"><?php echo htmlspecialchars($success); ?></p>
        <div style="margin-top: 12px;">
          <a href="index.php" class="btn btn-secondary" style="font-size: 0.85rem; padding: 7px 14px;">Return to Dashboard</a>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.35); color: #ef4444; padding: 12px 16px; border-radius: var(--radius-lg); margin-bottom: 18px; font-size: 0.92rem;">
        <?php echo htmlspecialchars($error); ?>
      </div>
    <?php endif; ?>

    <?php 
      $pkgStatus = $currentUser['package_status'] ?? 'pending';
      if ($pkgStatus === 'pending'): 
    ?>
      <div style="background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.35); color: #f59e0b; padding: 14px 18px; border-radius: var(--radius-lg); margin-bottom: 18px; font-size: 0.9rem; display: flex; align-items: flex-start; gap: 10px;">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink: 0; margin-top: 1px;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
        <div>
          <strong style="color: var(--text-main);">Package Pending Admin Approval:</strong> Your user account is registered, but your package is awaiting Admin Approval. Withdrawals will be automatically enabled once the administrator approves your package.
        </div>
      </div>
    <?php elseif ($pkgStatus === 'expired'): ?>
      <div style="background: rgba(148, 163, 184, 0.12); border: 1px solid rgba(148, 163, 184, 0.3); padding: 14px 18px; border-radius: var(--radius-lg); margin-bottom: 18px; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
        <div style="font-size: 0.88rem; color: var(--text-secondary);">
          <strong style="color: var(--text-main);">Package Expired (Capital Fully Withdrawn):</strong> You have already withdrawn your full actual capital amount. <strong>Your user account is completely active!</strong> Please subscribe to a new package to reactivate earnings and withdrawals.
        </div>
        <a href="plans.php" class="btn btn-pwa" style="font-size: 0.82rem; padding: 6px 14px;">Select New Package &rarr;</a>
      </div>
    <?php endif; ?>

    <div class="card">
      <form method="POST" action="withdraw.php" id="withdrawForm">

        <!-- Step 1: Select What to Withdraw (Profit vs Capital) -->
        <div class="form-group">
          <label class="form-label">Select Withdrawal Source</label>
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
            <!-- Option A: Profit (After Threshold) -->
            <label class="plan-card-option <?php echo ($selectedCategory === 'profit') ? 'selected' : ''; ?>" id="optProfit" style="position: relative; border: 2px solid <?php echo ($selectedCategory === 'profit') ? 'var(--accent-lime)' : 'var(--border-glass)'; ?>; background: <?php echo ($selectedCategory === 'profit') ? 'var(--bg-card)' : 'var(--bg-subtle)'; ?>; padding: 16px; border-radius: var(--radius-lg); cursor: pointer; display: block;">
              <input type="radio" name="withdrawal_category" value="profit" <?php echo ($selectedCategory === 'profit') ? 'checked' : ''; ?> style="position: absolute; top: 12px; right: 12px; accent-color: var(--accent-lime);" onchange="toggleCategory('profit')">
              <div style="font-size: 0.72rem; text-transform: uppercase; font-weight: 800; color: <?php echo $profitStats['can_withdraw'] ? 'var(--accent-lime)' : '#f59e0b'; ?>; margin-bottom: 4px;">
                <?php echo $profitStats['can_withdraw'] ? '✓ Threshold Reached' : ('Unlocks at $' . number_format($minProfitWithdrawal, 0) . ' Profit'); ?>
              </div>
              <h4 style="font-size: 1.05rem; font-weight: 800; color: var(--text-main); margin-bottom: 4px;">Withdraw Profit</h4>
              <div style="font-size: 0.82rem; color: var(--text-muted);">Available Earned Profit:</div>
              <div style="font-size: 1.35rem; font-weight: 900; color: var(--accent-lime); margin-top: 2px;">
                +$<?php echo number_format($profitStats['available_profit'], 2); ?>
              </div>
              <div style="font-size: 0.73rem; color: var(--text-muted); margin-top: 4px;">
                Earned: $<?php echo number_format($profitStats['total_earned_profit'], 2); ?> • Min: $<?php echo number_format($minProfitWithdrawal, 0); ?>
              </div>
            </label>

            <!-- Option B: Capital / Plan (Month End only) -->
            <label class="plan-card-option <?php echo ($selectedCategory === 'capital') ? 'selected' : ''; ?>" id="optCapital" style="position: relative; border: 2px solid <?php echo ($selectedCategory === 'capital') ? '#0ea5e9' : 'var(--border-glass)'; ?>; background: <?php echo ($selectedCategory === 'capital') ? 'var(--bg-card)' : 'var(--bg-subtle)'; ?>; padding: 16px; border-radius: var(--radius-lg); cursor: pointer; display: block;">
              <input type="radio" name="withdrawal_category" value="capital" <?php echo ($selectedCategory === 'capital') ? 'checked' : ''; ?> style="position: absolute; top: 12px; right: 12px; accent-color: #0ea5e9;" onchange="toggleCategory('capital')">
              <div style="font-size: 0.72rem; text-transform: uppercase; font-weight: 800; color: <?php echo $capitalEligibility['eligible'] ? '#38bdf8' : '#f59e0b'; ?>; margin-bottom: 4px;">
                <?php echo $capitalEligibility['eligible'] ? 'Month-End Unlocked' : '30-Day Lock'; ?>
              </div>
              <h4 style="font-size: 1.05rem; font-weight: 800; color: var(--text-main); margin-bottom: 4px;">Plan Capital</h4>
              <div style="font-size: 0.82rem; color: var(--text-muted);">Invested Amount:</div>
              <div style="font-size: 1.35rem; font-weight: 900; color: var(--text-main); margin-top: 2px;">
                $<?php echo number_format($currentUser['plan_amount'], 2); ?>
              </div>
              <div style="font-size: 0.73rem; color: <?php echo $capitalEligibility['eligible'] ? 'var(--accent-lime)' : 'var(--text-muted)'; ?>; margin-top: 4px;">
                <?php echo $capitalEligibility['eligible'] ? 'Eligible to withdraw' : ($capitalEligibility['days_remaining'] . ' days left in month'); ?>
              </div>
            </label>
          </div>
        </div>

        <!-- Profit Threshold Status / Progress Alert (Shows rule: 100$ after 10$, 200$ after 20$, 300$ after 30$, 500$ after 50$) -->
        <div id="profitMilestoneBox" style="<?php echo ($selectedCategory === 'profit') ? 'display: block;' : 'display: none;'; ?> margin-bottom: 18px;">
          <?php if ($pkgStatus === 'active'): ?>
            <?php if (!$profitStats['can_withdraw']): ?>
              <div style="background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.4); border-radius: var(--radius-md); padding: 14px 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; flex-wrap: wrap; gap: 8px;">
                  <span style="font-size: 0.86rem; font-weight: 800; color: #f59e0b; display: flex; align-items: center; gap: 6px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    Withdrawal Locked (Milestone: $<?php echo number_format($minProfitWithdrawal, 0); ?>.00 Profit Required)
                  </span>
                  <span style="font-size: 0.82rem; font-weight: 800; color: var(--text-main);">
                    $<?php echo number_format($profitStats['available_profit'], 2); ?> / $<?php echo number_format($minProfitWithdrawal, 2); ?>
                  </span>
                </div>
                <p style="font-size: 0.82rem; color: var(--text-secondary); margin: 0 0 10px 0; line-height: 1.5;">
                  Client Rule: On your <strong><?php echo htmlspecialchars($userPlan['name']); ?> ($<?php echo number_format($currentUser['plan_amount'], 0); ?> USD)</strong>, withdrawal unlocks once your earned profit reaches <strong>$<?php echo number_format($minProfitWithdrawal, 2); ?> USD</strong>. You have earned <strong>$<?php echo number_format($profitStats['available_profit'], 2); ?></strong> so far. <strong>$<?php echo number_format($profitStats['remaining_to_unlock'], 2); ?> USD</strong> more profit needed to unlock withdrawal.
                </p>
                <!-- Visual Progress Bar -->
                <div style="width: 100%; height: 9px; background: rgba(255,255,255,0.08); border-radius: 6px; overflow: hidden;">
                  <div style="width: <?php echo $profitStats['progress_percent']; ?>%; height: 100%; background: linear-gradient(90deg, #f59e0b, var(--accent-lime)); border-radius: 6px; transition: width 0.4s ease;"></div>
                </div>
                <div style="font-size: 0.74rem; color: var(--text-muted); margin-top: 6px; display: flex; justify-content: space-between;">
                  <span>Progress: <?php echo $profitStats['progress_percent']; ?>% reached</span>
                  <span>Daily Profit Rate: +$<?php echo number_format($profitStats['daily_profit'], 2); ?>/day (1% daily)</span>
                </div>
              </div>
            <?php else: ?>
              <div style="background: var(--accent-lime-soft); border: 1px solid var(--accent-lime); border-radius: var(--radius-md); padding: 14px 16px; display: flex; align-items: center; gap: 12px;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--accent-lime)" stroke-width="2.5" style="flex-shrink: 0;"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                <div style="font-size: 0.86rem; color: var(--text-main);">
                  <strong style="color: var(--accent-lime);">Withdrawal Unlocked!</strong> You have reached your package milestone (<strong>+$<?php echo number_format($profitStats['available_profit'], 2); ?> USD</strong> available profit). Minimum withdrawal is <strong>$<?php echo number_format($minProfitWithdrawal, 2); ?> USD</strong>.
                </div>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        </div>

        <!-- Step 2: Select Withdrawal Method -->
        <div class="form-group">
          <label class="form-label">Select Withdrawal Method</label>
          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px;">
            
            <!-- Easypaisa Card -->
            <label class="method-card <?php echo ($selectedMethod === 'easypaisa') ? 'selected' : ''; ?>" id="card_easypaisa" onclick="selectMethod('easypaisa')" style="position: relative; border: 2px solid <?php echo ($selectedMethod === 'easypaisa') ? '#10b981' : 'var(--border-glass)'; ?>; background: <?php echo ($selectedMethod === 'easypaisa') ? 'rgba(16, 185, 129, 0.12)' : 'var(--bg-subtle)'; ?>; padding: 12px 14px; border-radius: var(--radius-md); cursor: pointer; display: flex; flex-direction: column; gap: 4px; transition: var(--transition-speed);">
              <input type="radio" name="withdrawal_method" value="easypaisa" <?php echo ($selectedMethod === 'easypaisa') ? 'checked' : ''; ?> style="position: absolute; top: 10px; right: 10px; accent-color: #10b981;">
              <div style="display: flex; align-items: center; gap: 6px;">
                <span style="width: 10px; height: 10px; border-radius: 50%; background: #10b981; display: inline-block;"></span>
                <strong style="color: var(--text-main); font-size: 0.92rem;">Easypaisa</strong>
              </div>
              <span style="font-size: 0.72rem; color: #10b981; font-weight: 700;">Mobile Wallet</span>
            </label>

            <!-- JazzCash Card -->
            <label class="method-card <?php echo ($selectedMethod === 'jazzcash') ? 'selected' : ''; ?>" id="card_jazzcash" onclick="selectMethod('jazzcash')" style="position: relative; border: 2px solid <?php echo ($selectedMethod === 'jazzcash') ? '#f59e0b' : 'var(--border-glass)'; ?>; background: <?php echo ($selectedMethod === 'jazzcash') ? 'rgba(245, 158, 11, 0.12)' : 'var(--bg-subtle)'; ?>; padding: 12px 14px; border-radius: var(--radius-md); cursor: pointer; display: flex; flex-direction: column; gap: 4px; transition: var(--transition-speed);">
              <input type="radio" name="withdrawal_method" value="jazzcash" <?php echo ($selectedMethod === 'jazzcash') ? 'checked' : ''; ?> style="position: absolute; top: 10px; right: 10px; accent-color: #f59e0b;">
              <div style="display: flex; align-items: center; gap: 6px;">
                <span style="width: 10px; height: 10px; border-radius: 50%; background: #f59e0b; display: inline-block;"></span>
                <strong style="color: var(--text-main); font-size: 0.92rem;">JazzCash</strong>
              </div>
              <span style="font-size: 0.72rem; color: #f59e0b; font-weight: 700;">Mobile Wallet</span>
            </label>

            <!-- Bank Transfer Card -->
            <label class="method-card <?php echo ($selectedMethod === 'bank') ? 'selected' : ''; ?>" id="card_bank" onclick="selectMethod('bank')" style="position: relative; border: 2px solid <?php echo ($selectedMethod === 'bank') ? '#0ea5e9' : 'var(--border-glass)'; ?>; background: <?php echo ($selectedMethod === 'bank') ? 'rgba(14, 165, 233, 0.12)' : 'var(--bg-subtle)'; ?>; padding: 12px 14px; border-radius: var(--radius-md); cursor: pointer; display: flex; flex-direction: column; gap: 4px; transition: var(--transition-speed);">
              <input type="radio" name="withdrawal_method" value="bank" <?php echo ($selectedMethod === 'bank') ? 'checked' : ''; ?> style="position: absolute; top: 10px; right: 10px; accent-color: #0ea5e9;">
              <div style="display: flex; align-items: center; gap: 6px;">
                <span style="width: 10px; height: 10px; border-radius: 50%; background: #0ea5e9; display: inline-block;"></span>
                <strong style="color: var(--text-main); font-size: 0.92rem;">Bank Account</strong>
              </div>
              <span style="font-size: 0.72rem; color: #0ea5e9; font-weight: 700;">Raast / IBFT</span>
            </label>

            <!-- USDT TRC20 Card -->
            <label class="method-card <?php echo ($selectedMethod === 'trc20') ? 'selected' : ''; ?>" id="card_trc20" onclick="selectMethod('trc20')" style="position: relative; border: 2px solid <?php echo ($selectedMethod === 'trc20') ? 'var(--accent-lime)' : 'var(--border-glass)'; ?>; background: <?php echo ($selectedMethod === 'trc20') ? 'var(--accent-lime-soft)' : 'var(--bg-subtle)'; ?>; padding: 12px 14px; border-radius: var(--radius-md); cursor: pointer; display: flex; flex-direction: column; gap: 4px; transition: var(--transition-speed);">
              <input type="radio" name="withdrawal_method" value="trc20" <?php echo ($selectedMethod === 'trc20') ? 'checked' : ''; ?> style="position: absolute; top: 10px; right: 10px; accent-color: var(--accent-lime);">
              <div style="display: flex; align-items: center; gap: 6px;">
                <span style="width: 10px; height: 10px; border-radius: 50%; background: var(--accent-lime); display: inline-block;"></span>
                <strong style="color: var(--text-main); font-size: 0.92rem;">USDT TRC20</strong>
              </div>
              <span style="font-size: 0.72rem; color: var(--accent-lime); font-weight: 700;">Crypto Wallet</span>
            </label>

          </div>
        </div>

        <!-- Step 3: Dynamic Receiving Account Details -->
        <div id="accountDetailsSection">
          
          <!-- Fields for Easypaisa & JazzCash -->
          <div id="mobileWalletFields" style="<?php echo ($selectedMethod === 'easypaisa' || $selectedMethod === 'jazzcash') ? 'display: block;' : 'display: none;'; ?>">
            <div class="form-group">
              <label class="form-label" id="lblAccountTitle">
                <span id="methodNameText"><?php echo ($selectedMethod === 'jazzcash') ? 'JazzCash' : 'Easypaisa'; ?></span> Account Title (Receiver Name)
              </label>
              <input type="text" name="account_title" id="inputAccountTitle" class="form-input" placeholder="e.g. Muhammad Ali" value="<?php echo htmlspecialchars($_POST['account_title'] ?? $currentUser['name'] ?? ''); ?>">
              <small style="color: var(--text-muted); font-size: 0.78rem;">Enter the exact account holder title registered on your mobile account.</small>
            </div>

            <div class="form-group">
              <label class="form-label" id="lblAccountNumber">
                <span id="methodNameText2"><?php echo ($selectedMethod === 'jazzcash') ? 'JazzCash' : 'Easypaisa'; ?></span> Mobile Account Number
              </label>
              <input type="text" name="account_number" id="inputAccountNumber" class="form-input" placeholder="<?php echo ($selectedMethod === 'jazzcash') ? 'e.g. 03001234567' : 'e.g. 03451234567'; ?>" value="<?php echo htmlspecialchars($_POST['account_number'] ?? $currentUser['phone'] ?? ''); ?>">
              <small style="color: var(--text-muted); font-size: 0.78rem;">11-digit Pakistani mobile number where funds will be credited.</small>
            </div>
          </div>

          <!-- Fields for Bank Transfer -->
          <div id="bankTransferFields" style="<?php echo ($selectedMethod === 'bank') ? 'display: block;' : 'display: none;'; ?>">
            <div class="form-group">
              <label class="form-label">Bank Name</label>
              <input type="text" name="bank_name" id="inputBankName" class="form-input" placeholder="e.g. Meezan Bank, HBL, Allied Bank, Alfalah..." value="<?php echo htmlspecialchars($_POST['bank_name'] ?? ''); ?>">
            </div>

            <div class="form-group">
              <label class="form-label">Bank Account Title</label>
              <input type="text" name="bank_account_title" id="inputBankAccountTitle" class="form-input" placeholder="e.g. Muhammad Ali" value="<?php echo htmlspecialchars($_POST['bank_account_title'] ?? $currentUser['name'] ?? ''); ?>">
            </div>

            <div class="form-group">
              <label class="form-label">Bank Account Number / IBAN</label>
              <input type="text" name="bank_account_number" id="inputBankAccountNumber" class="form-input" placeholder="e.g. PK64MEZN0001020304050607" value="<?php echo htmlspecialchars($_POST['bank_account_number'] ?? ''); ?>">
              <small style="color: var(--text-muted); font-size: 0.78rem;">Enter your 14-digit Account Number or 24-character IBAN.</small>
            </div>
          </div>

          <!-- Fields for USDT TRC20 -->
          <div id="trcWalletFields" style="<?php echo ($selectedMethod === 'trc20') ? 'display: block;' : 'display: none;'; ?>">
            <div class="form-group">
              <label class="form-label">Your USDT (TRC-20) Receiving Wallet Address</label>
              <input type="text" name="trc_wallet" id="inputTrcWallet" class="form-input" placeholder="e.g. TXm1P2k7gK..." value="<?php echo htmlspecialchars($_POST['trc_wallet'] ?? ''); ?>">
              <small style="color: var(--text-muted); font-size: 0.78rem;">Tron TRC-20 address only. Please verify your address carefully.</small>
            </div>
          </div>

        </div>

        <!-- Step 4: Amount Input -->
        <div class="form-group" style="margin-bottom: 24px;">
          <label class="form-label" id="lblWithdrawalAmount">Withdrawal Amount (USD)</label>
          <div style="position: relative;">
            <input 
              type="number" 
              step="0.01" 
              min="<?php echo ($selectedCategory === 'profit' ? $minProfitWithdrawal : 10); ?>" 
              <?php if ($selectedCategory === 'profit' && $profitStats['available_profit'] >= $minProfitWithdrawal): ?>
                max="<?php echo $profitStats['available_profit']; ?>" 
              <?php elseif ($selectedCategory === 'capital'): ?>
                max="<?php echo $currentUser['plan_amount']; ?>" 
              <?php endif; ?>
              name="amount" 
              id="inputWithdrawalAmount" 
              class="form-input" 
              placeholder="<?php echo ($selectedCategory === 'profit' ? 'e.g. ' . number_format($minProfitWithdrawal, 2) : 'e.g. 50.00'); ?>" 
              value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>" 
              required 
              style="padding-left: 36px;"
            >
            <span style="position: absolute; left: 14px; top: 13px; color: var(--accent-lime); font-weight: 800;">$</span>
          </div>
          <small id="amountHelpNotice" style="color: var(--text-muted); font-size: 0.78rem;">
            <?php if ($selectedCategory === 'profit'): ?>
              Minimum profit withdrawal for your <?php echo htmlspecialchars($userPlan['name']); ?>: <strong>$<?php echo number_format($minProfitWithdrawal, 2); ?> USD</strong> (Unlocked after $<?php echo number_format($minProfitWithdrawal, 2); ?> profit).
            <?php else: ?>
              Minimum capital withdrawal: <strong>$10.00 USD</strong> • Unlocked at 30-day month end.
            <?php endif; ?>
          </small>
        </div>

        <?php 
          $canWithdraw = ($isWithinHours && ($currentUser['package_status'] ?? 'pending') === 'active');
          if ($selectedCategory === 'profit' && !$profitStats['can_withdraw']) {
              $canWithdraw = false;
          } elseif ($selectedCategory === 'capital' && !$capitalEligibility['eligible']) {
              $canWithdraw = false;
          }

          $btnText = 'Request Withdrawal (1-Hour Payout)';
          if (($currentUser['package_status'] ?? 'pending') === 'pending') {
              $btnText = 'Waiting for Package Approval';
          } elseif (($currentUser['package_status'] ?? 'pending') === 'expired') {
              $btnText = 'Package Expired (Select New Plan)';
          } elseif (!$isWithinHours) {
              $btnText = 'Hours Closed (9:00 AM - 9:00 PM)';
          } elseif ($selectedCategory === 'profit' && !$profitStats['can_withdraw']) {
              $btnText = 'Withdrawal Locked (Need $' . number_format($profitStats['remaining_to_unlock'], 2) . ' More Profit)';
          } elseif ($selectedCategory === 'capital' && !$capitalEligibility['eligible']) {
              $btnText = 'Capital Locked (' . $capitalEligibility['days_remaining'] . ' Days Left)';
          } else {
              $btnText = 'Request Withdrawal ($' . number_format($minProfitWithdrawal, 0) . ' Min - 1-Hour SLA)';
          }
        ?>
        <button type="submit" id="btnSubmitWithdrawal" class="btn btn-pwa" style="width: 100%; padding: 14px 20px; font-size: 1rem;" <?php echo !$canWithdraw ? 'disabled' : ''; ?>>
          <?php echo $btnText; ?>
        </button>

        <div style="text-align: center; margin-top: 16px;">
          <a href="index.php" style="color: var(--text-secondary); text-decoration: none; font-size: 0.9rem;">&larr; Back to Dashboard</a>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  const PLAN_MIN_PROFIT = <?php echo json_encode($minProfitWithdrawal); ?>;
  const PLAN_AVAILABLE_PROFIT = <?php echo json_encode($profitStats['available_profit']); ?>;
  const PLAN_CAN_WITHDRAW_PROFIT = <?php echo json_encode($profitStats['can_withdraw']); ?>;
  const PLAN_REMAINING_PROFIT = <?php echo json_encode($profitStats['remaining_to_unlock']); ?>;
  const PLAN_NAME = <?php echo json_encode($userPlan['name']); ?>;
  const CAPITAL_ELIGIBLE = <?php echo json_encode($capitalEligibility['eligible']); ?>;
  const CAPITAL_REMAINING_DAYS = <?php echo json_encode($capitalEligibility['days_remaining']); ?>;
  const IS_WITHIN_HOURS = <?php echo json_encode($isWithinHours); ?>;
  const PKG_STATUS = <?php echo json_encode($currentUser['package_status'] ?? 'pending'); ?>;

  function toggleCategory(cat) {
    const optP = document.getElementById('optProfit');
    const optC = document.getElementById('optCapital');
    const milestoneBox = document.getElementById('profitMilestoneBox');
    const inputAmt = document.getElementById('inputWithdrawalAmount');
    const helpNotice = document.getElementById('amountHelpNotice');
    const submitBtn = document.getElementById('btnSubmitWithdrawal');

    if (cat === 'profit') {
      optP.classList.add('selected');
      optP.style.borderColor = 'var(--accent-lime)';
      optP.style.background = 'var(--bg-card)';

      optC.classList.remove('selected');
      optC.style.borderColor = 'var(--border-glass)';
      optC.style.background = 'var(--bg-subtle)';

      if (milestoneBox) milestoneBox.style.display = 'block';

      inputAmt.min = PLAN_MIN_PROFIT;
      if (PLAN_AVAILABLE_PROFIT >= PLAN_MIN_PROFIT) {
        inputAmt.max = PLAN_AVAILABLE_PROFIT;
      } else {
        inputAmt.removeAttribute('max');
      }
      inputAmt.placeholder = 'e.g. ' + PLAN_MIN_PROFIT.toFixed(2);
      helpNotice.innerHTML = 'Minimum profit withdrawal for your ' + PLAN_NAME + ': <strong>$' + PLAN_MIN_PROFIT.toFixed(2) + ' USD</strong> (Unlocked after $' + PLAN_MIN_PROFIT.toFixed(2) + ' profit).';

      if (PKG_STATUS !== 'active') {
        submitBtn.disabled = true;
      } else if (!IS_WITHIN_HOURS) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Hours Closed (9:00 AM - 9:00 PM)';
      } else if (!PLAN_CAN_WITHDRAW_PROFIT) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Withdrawal Locked (Need $' + PLAN_REMAINING_PROFIT.toFixed(2) + ' More Profit)';
      } else {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Request Withdrawal ($' + PLAN_MIN_PROFIT.toFixed(0) + ' Min - 1-Hour SLA)';
      }
    } else {
      optC.classList.add('selected');
      optC.style.borderColor = '#0ea5e9';
      optC.style.background = 'var(--bg-card)';

      optP.classList.remove('selected');
      optP.style.borderColor = 'var(--border-glass)';
      optP.style.background = 'var(--bg-subtle)';

      if (milestoneBox) milestoneBox.style.display = 'none';

      inputAmt.min = 10;
      inputAmt.removeAttribute('max');
      inputAmt.placeholder = 'e.g. 50.00';
      helpNotice.innerHTML = 'Minimum capital withdrawal: <strong>$10.00 USD</strong> • Unlocked at 30-day month end.';

      if (PKG_STATUS !== 'active') {
        submitBtn.disabled = true;
      } else if (!IS_WITHIN_HOURS) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Hours Closed (9:00 AM - 9:00 PM)';
      } else if (!CAPITAL_ELIGIBLE) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Capital Locked (' + CAPITAL_REMAINING_DAYS + ' Days Left)';
      } else {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Request Capital Withdrawal (1-Hour SLA)';
      }
    }
  }

  function selectMethod(method) {
    // Update radio
    const radio = document.querySelector(`input[name="withdrawal_method"][value="${method}"]`);
    if (radio) radio.checked = true;

    // Reset card styles
    const methods = {
      'easypaisa': { color: '#10b981', bg: 'rgba(16, 185, 129, 0.12)' },
      'jazzcash':  { color: '#f59e0b', bg: 'rgba(245, 158, 11, 0.12)' },
      'bank':      { color: '#0ea5e9', bg: 'rgba(14, 165, 233, 0.12)' },
      'trc20':     { color: 'var(--accent-lime)', bg: 'var(--accent-lime-soft)' }
    };

    Object.keys(methods).forEach(m => {
      const card = document.getElementById(`card_${m}`);
      if (!card) return;
      if (m === method) {
        card.classList.add('selected');
        card.style.borderColor = methods[m].color;
        card.style.background = methods[m].bg;
      } else {
        card.classList.remove('selected');
        card.style.borderColor = 'var(--border-glass)';
        card.style.background = 'var(--bg-subtle)';
      }
    });

    // Toggle field visibility
    const mobileSection = document.getElementById('mobileWalletFields');
    const bankSection   = document.getElementById('bankTransferFields');
    const trcSection    = document.getElementById('trcWalletFields');

    const inputAccountTitle  = document.getElementById('inputAccountTitle');
    const inputAccountNumber  = document.getElementById('inputAccountNumber');
    const inputBankName      = document.getElementById('inputBankName');
    const inputBankAccountTitle = document.getElementById('inputBankAccountTitle');
    const inputBankAccountNumber = document.getElementById('inputBankAccountNumber');
    const inputTrcWallet     = document.getElementById('inputTrcWallet');

    const methodNameText  = document.getElementById('methodNameText');
    const methodNameText2 = document.getElementById('methodNameText2');

    if (method === 'easypaisa' || method === 'jazzcash') {
      mobileSection.style.display = 'block';
      bankSection.style.display = 'none';
      trcSection.style.display = 'none';

      const labelName = method === 'easypaisa' ? 'Easypaisa' : 'JazzCash';
      methodNameText.textContent = labelName;
      methodNameText2.textContent = labelName;
      inputAccountNumber.placeholder = method === 'easypaisa' ? 'e.g. 03451234567' : 'e.g. 03001234567';

      inputAccountTitle.required = true;
      inputAccountNumber.required = true;
      inputBankName.required = false;
      inputBankAccountTitle.required = false;
      inputBankAccountNumber.required = false;
      inputTrcWallet.required = false;
    } else if (method === 'bank') {
      mobileSection.style.display = 'none';
      bankSection.style.display = 'block';
      trcSection.style.display = 'none';

      inputAccountTitle.required = false;
      inputAccountNumber.required = false;
      inputBankName.required = true;
      inputBankAccountTitle.required = true;
      inputBankAccountNumber.required = true;
      inputTrcWallet.required = false;
    } else if (method === 'trc20') {
      mobileSection.style.display = 'none';
      bankSection.style.display = 'none';
      trcSection.style.display = 'block';

      inputAccountTitle.required = false;
      inputAccountNumber.required = false;
      inputBankName.required = false;
      inputBankAccountTitle.required = false;
      inputBankAccountNumber.required = false;
      inputTrcWallet.required = true;
    }
  }

  // Handle bank input mapping before submit
  document.getElementById('withdrawForm').addEventListener('submit', function(e) {
    const selectedMethod = document.querySelector('input[name="withdrawal_method"]:checked').value;
    if (selectedMethod === 'bank') {
      const bTitle = document.getElementById('inputBankAccountTitle').value;
      const bNum = document.getElementById('inputBankAccountNumber').value;
      document.getElementById('inputAccountTitle').value = bTitle;
      document.getElementById('inputAccountNumber').value = bNum;
    }
  });

  // Initialize on load
  document.addEventListener('DOMContentLoaded', function() {
    const checkedMethod = document.querySelector('input[name="withdrawal_method"]:checked');
    if (checkedMethod) {
      selectMethod(checkedMethod.value);
    }
  });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
