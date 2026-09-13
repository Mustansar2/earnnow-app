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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $withdrawalCategory = $_POST['withdrawal_category'] ?? 'profit';
    $amount = floatval($_POST['amount'] ?? 0);
    $trcWallet = trim($_POST['trc_wallet'] ?? '');

    if (!$isWithinHours) {
        $error = 'Withdrawals can only be submitted between 9:00 AM and 9:00 PM. Please return during active hours.';
    } elseif (empty($trcWallet)) {
        $error = 'Please enter your valid USDT (TRC-20) wallet address.';
    } elseif (strlen($trcWallet) < 25) {
        $error = 'The TRC-20 wallet address entered is too short or invalid.';
    } elseif ($amount <= 0) {
        $error = 'Please enter a valid withdrawal amount.';
    } else {
        if ($withdrawalCategory === 'capital') {
            if (!$capitalEligibility['eligible']) {
                $error = 'Actual invested plan capital can only be withdrawn at the end of the monthly cycle (30 days). ' . 
                         $capitalEligibility['days_remaining'] . ' days remaining for your plan.';
            } elseif ($amount > $currentUser['plan_amount']) {
                $error = 'You cannot withdraw more than your subscribed package amount ($' . number_format($currentUser['plan_amount'], 2) . ').';
            } else {
                $db = getDB();
                $stmt = $db->prepare("
                    INSERT INTO transactions (user_id, type, category, amount, method, account_or_wallet, status, note)
                    VALUES (?, 'withdraw', 'capital', ?, 'USDT TRC20', ?, 'Pending', 'Monthly capital plan withdrawal (Max 1 hour SLA)')
                ");
                $stmt->execute([$currentUser['id'], $amount, $trcWallet]);

                $success = 'Capital withdrawal request of $' . number_format($amount, 2) . ' submitted! Funds will be transferred to your TRC-20 wallet within 1 hour.';
            }
        } else {
            $maxProfitAllowed = max($currentUser['balance'], $currentUser['monthly_profit']);
            
            if ($amount > $maxProfitAllowed && $maxProfitAllowed > 0) {
                $error = 'Requested amount exceeds your available profit balance ($' . number_format($maxProfitAllowed, 2) . ').';
            } else {
                $db = getDB();
                $stmt = $db->prepare("
                    INSERT INTO transactions (user_id, type, category, amount, method, account_or_wallet, status, note)
                    VALUES (?, 'withdraw', 'profit', ?, 'USDT TRC20', ?, 'Pending', 'Instant daily profit withdrawal (Max 1 hour SLA)')
                ");
                $stmt->execute([$currentUser['id'], $amount, $trcWallet]);

                if ($currentUser['balance'] >= $amount) {
                    $newBalance = max(0, $currentUser['balance'] - $amount);
                    $updateStmt = $db->prepare("UPDATE users SET balance = ? WHERE id = ?");
                    $updateStmt->execute([$newBalance, $currentUser['id']]);
                    $currentUser['balance'] = $newBalance;
                    $_SESSION['user']['balance'] = $newBalance;
                }

                $success = 'Profit withdrawal of $' . number_format($amount, 2) . ' submitted successfully! Your transfer to ' . htmlspecialchars(substr($trcWallet, 0, 8)) . '... will be completed within 1 hour.';
            }
        }
    }
}

$pageTitle = 'Withdraw Funds (USDT TRC20)';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-2col-grid">
  <!-- Left Side: Header, Guarantee Badge & Operating Hours -->
  <div>
    <div style="margin-bottom: 18px;">
      <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin-bottom: 4px;">Withdraw Funds</h1>
      <p style="color: var(--text-secondary); font-size: 0.9rem;">Fast payout directly in USDT (TRC-20) to your personal crypto wallet.</p>
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
      <span style="background: var(--bg-card); border: 1px solid var(--border-glass); color: var(--accent-lime); font-size: 0.75rem; font-weight: 800; padding: 4px 10px; border-radius: 20px; white-space: nowrap;">
        TRC-20
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

    <!-- Wallet Summary Card -->
    <div class="card" style="font-size: 0.85rem; color: var(--text-secondary); line-height: 1.6;">
      <strong style="color: var(--text-main); display: block; margin-bottom: 6px;">Withdrawal Policy:</strong>
      • <strong style="color: var(--accent-lime);">Monthly Profit:</strong> Can be withdrawn any day, anytime.<br>
      • <strong style="color: #38bdf8;">Plan Capital:</strong> Unlocked at 30-day month end.<br>
      • Payouts processed exclusively via USDT on TRC-20 Network.
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

    <div class="card">
      <form method="POST" action="withdraw.php">

        <!-- Step 1: Select What to Withdraw (Profit vs Capital) -->
        <div class="form-group">
          <label class="form-label">Select Withdrawal Source</label>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
          <!-- Option A: Profit (Any day) -->
          <label class="plan-card-option selected" id="optProfit" style="position: relative; border: 2px solid var(--accent-lime); background: var(--bg-card); padding: 16px; border-radius: var(--radius-lg); cursor: pointer; display: block;">
            <input type="radio" name="withdrawal_category" value="profit" checked style="position: absolute; top: 12px; right: 12px; accent-color: var(--accent-lime);" onchange="toggleCategory('profit')">
            <div style="font-size: 0.72rem; text-transform: uppercase; font-weight: 800; color: var(--accent-lime); margin-bottom: 4px;">Any Day Available</div>
            <h4 style="font-size: 1.05rem; font-weight: 800; color: var(--text-main); margin-bottom: 4px;">Withdraw Profit</h4>
            <div style="font-size: 0.85rem; color: var(--text-muted);">Available Monthly Profit:</div>
            <div style="font-size: 1.3rem; font-weight: 900; color: var(--accent-lime); margin-top: 2px;">
              +$<?php echo number_format($currentUser['monthly_profit'], 2); ?>
            </div>
          </label>

          <!-- Option B: Capital / Plan (Month End only) -->
          <label class="plan-card-option" id="optCapital" style="position: relative; border: 2px solid var(--border-glass); background: var(--bg-subtle); padding: 16px; border-radius: var(--radius-lg); cursor: pointer; display: block;">
            <input type="radio" name="withdrawal_category" value="capital" style="position: absolute; top: 12px; right: 12px; accent-color: #0ea5e9;" onchange="toggleCategory('capital')">
            <div style="font-size: 0.72rem; text-transform: uppercase; font-weight: 800; color: <?php echo $capitalEligibility['eligible'] ? '#38bdf8' : '#f59e0b'; ?>; margin-bottom: 4px;">
              <?php echo $capitalEligibility['eligible'] ? 'Month-End Unlocked' : '30-Day Lock'; ?>
            </div>
            <h4 style="font-size: 1.05rem; font-weight: 800; color: var(--text-main); margin-bottom: 4px;">Plan Capital</h4>
            <div style="font-size: 0.85rem; color: var(--text-muted);">Invested Amount:</div>
            <div style="font-size: 1.3rem; font-weight: 900; color: var(--text-main); margin-top: 2px;">
              $<?php echo number_format($currentUser['plan_amount'], 2); ?>
            </div>
            <div style="font-size: 0.75rem; color: <?php echo $capitalEligibility['eligible'] ? 'var(--accent-lime)' : 'var(--text-muted)'; ?>; margin-top: 4px;">
              <?php echo $capitalEligibility['eligible'] ? 'Eligible to withdraw' : ($capitalEligibility['days_remaining'] . ' days left in month'); ?>
            </div>
          </label>
        </div>
      </div>

      <!-- Payment Method (Fixed to USDT TRC20) -->
      <div class="form-group">
        <label class="form-label">Withdrawal Method (Automatic)</label>
        <div style="display: flex; align-items: center; justify-content: space-between; background: var(--bg-subtle); border: 1px solid var(--border-glass); border-radius: var(--radius-md); padding: 12px 16px;">
          <div style="display: flex; align-items: center; gap: 10px;">
            <span style="background: var(--accent-lime-soft); color: var(--accent-lime); font-weight: 900; padding: 4px 8px; border-radius: 6px; font-size: 0.8rem;">USDT</span>
            <span style="font-weight: 700; color: var(--text-main);">Tether USD (TRC-20 Network)</span>
          </div>
          <span style="font-size: 0.8rem; color: var(--text-muted);">Direct to Wallet</span>
        </div>
      </div>

      <!-- TRC20 Wallet Address -->
      <div class="form-group">
        <label class="form-label">Your USDT (TRC-20) Receiving Wallet Address</label>
        <input type="text" name="trc_wallet" class="form-input" placeholder="e.g. T..." value="<?php echo htmlspecialchars($_POST['trc_wallet'] ?? ''); ?>" required>
        <small style="color: var(--text-muted); font-size: 0.8rem;">Tron TRC20 address only. Please verify your address carefully.</small>
      </div>

      <!-- Amount Input -->
      <div class="form-group" style="margin-bottom: 24px;">
        <label class="form-label">Withdrawal Amount (USD)</label>
        <div style="position: relative;">
          <input type="number" step="0.01" min="5" name="amount" class="form-input" placeholder="e.g. 50.00" value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>" required style="padding-left: 36px;">
          <span style="position: absolute; left: 14px; top: 13px; color: var(--accent-lime); font-weight: 800;">$</span>
        </div>
      </div>

      <button type="submit" class="btn btn-pwa" style="width: 100%; padding: 14px 20px; font-size: 1rem;" <?php echo !$isWithinHours ? 'disabled' : ''; ?>>
        Request Withdrawal (1-Hour Payout)
      </button>

      <div style="text-align: center; margin-top: 16px;">
        <a href="index.php" style="color: var(--text-secondary); text-decoration: none; font-size: 0.9rem;">&larr; Back to Dashboard</a>
      </div>
    </form>
  </div>
  </div>
</div>

<script>
  function toggleCategory(cat) {
    const optP = document.getElementById('optProfit');
    const optC = document.getElementById('optCapital');

    if (cat === 'profit') {
      optP.classList.add('selected');
      optP.style.borderColor = 'var(--accent-lime)';
      optP.style.background = 'var(--bg-card)';

      optC.classList.remove('selected');
      optC.style.borderColor = 'var(--border-glass)';
      optC.style.background = 'var(--bg-subtle)';
    } else {
      optC.classList.add('selected');
      optC.style.borderColor = '#0ea5e9';
      optC.style.background = 'var(--bg-card)';

      optP.classList.remove('selected');
      optP.style.borderColor = 'var(--border-glass)';
      optP.style.background = 'var(--bg-subtle)';
    }
  }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
