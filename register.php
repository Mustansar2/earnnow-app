<?php
define('EARNNOW_APP', true);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$selectedPlanId = 2; // Default to 200 USD plan

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $planId = (int)($_POST['plan_id'] ?? 1);
    $selectedPlanId = $planId;

    if (empty($name) || empty($email) || empty($phone) || empty($password)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (!isset($INVESTMENT_PLANS[$planId])) {
        $error = 'Invalid investment plan selected.';
    } else {
        $db = getDB();
        
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'An account with this email address already exists. Please log in.';
        } else {
            $chosenPlan = getPlanById($planId);
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $insertStmt = $db->prepare("
                INSERT INTO users (name, email, phone, password, plan_id, plan_name, plan_amount, monthly_profit, balance)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $inserted = $insertStmt->execute([
                $name,
                $email,
                $phone,
                $hashedPassword,
                $chosenPlan['id'],
                $chosenPlan['name'],
                $chosenPlan['amount'],
                $chosenPlan['monthly_profit'],
                0.00
            ]);

            if ($inserted) {
                $newUserId = $db->lastInsertId();
                $_SESSION['user_id'] = $newUserId;
                $_SESSION['user'] = [
                    'id' => $newUserId,
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'plan_id' => $chosenPlan['id'],
                    'plan_name' => $chosenPlan['name'],
                    'plan_amount' => $chosenPlan['amount'],
                    'monthly_profit' => $chosenPlan['monthly_profit'],
                    'balance' => 0.00
                ];

                header('Location: index.php?registered=1');
                exit;
            } else {
                $error = 'Account creation failed. Please try again.';
            }
        }
    }
}

$pageTitle = 'Create Account & Choose Plan';
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 680px; margin: 0 auto;">
  <div style="text-align: center; margin-bottom: 28px;">
    <h1 style="font-size: 1.85rem; font-weight: 800; color: var(--text-main); margin-bottom: 6px;">Create Your Account</h1>
    <p style="color: var(--text-secondary); font-size: 0.95rem;">Join EarnNow, select your investment plan, and start earning monthly profits.</p>
  </div>

  <?php if ($error): ?>
    <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.35); color: #ef4444; padding: 14px 18px; border-radius: var(--radius-lg); margin-bottom: 24px; font-size: 0.95rem;">
      <?php echo htmlspecialchars($error); ?>
    </div>
  <?php endif; ?>

  <div class="card">
    <form method="POST" action="register.php" id="registerForm">
      
      <!-- Personal Details -->
      <h3 class="card-title" style="margin-bottom: 18px;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
          <circle cx="12" cy="7" r="4"></circle>
        </svg>
        1. Personal Information
      </h3>

      <div class="form-group">
        <label class="form-label">Full Name</label>
        <input type="text" name="name" class="form-input" placeholder="e.g. John Doe" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input type="email" name="email" class="form-input" placeholder="name@example.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Phone Number</label>
          <input type="tel" name="phone" class="form-input" placeholder="+1 234 567 8900" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;">
        <div class="form-group">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-input" placeholder="At least 6 characters" required>
        </div>
        <div class="form-group">
          <label class="form-label">Confirm Password</label>
          <input type="password" name="confirm_password" class="form-input" placeholder="Repeat password" required>
        </div>
      </div>

      <!-- Plan Selection -->
      <h3 class="card-title" style="margin-top: 10px; margin-bottom: 6px;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="12" y1="8" x2="12" y2="12"></line>
          <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
        2. Select Your Investment Plan
      </h3>
      <p style="color: var(--text-secondary); font-size: 0.88rem; margin-bottom: 16px;">
        Choose from our 3 plans. Your monthly profit will be calculated and credited automatically.
      </p>

      <div class="plans-selection-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 28px;">
        <?php foreach ($INVESTMENT_PLANS as $pId => $plan): 
          $isSelected = ($selectedPlanId == $pId);
        ?>
          <label class="plan-card-option <?php echo $isSelected ? 'selected' : ''; ?>" style="position: relative; border: 2px solid <?php echo $isSelected ? 'var(--accent-lime)' : 'var(--border-glass)'; ?>; background: <?php echo $isSelected ? 'var(--bg-card)' : 'var(--bg-subtle)'; ?>; padding: 18px 14px; border-radius: var(--radius-lg); cursor: pointer; transition: var(--transition-speed); display: block;">
            <input type="radio" name="plan_id" value="<?php echo $plan['id']; ?>" <?php echo $isSelected ? 'checked' : ''; ?> style="position: absolute; top: 12px; right: 12px; accent-color: var(--accent-lime);">
            
            <div style="display: inline-block; font-size: 0.72rem; text-transform: uppercase; font-weight: 800; color: var(--accent-lime); margin-bottom: 6px; letter-spacing: 0.05em;">
              <?php echo htmlspecialchars($plan['badge']); ?>
            </div>
            
            <h4 style="font-size: 1.05rem; font-weight: 800; color: var(--text-main); margin-bottom: 4px;">
              <?php echo htmlspecialchars($plan['name']); ?>
            </h4>

            <div style="font-size: 1.6rem; font-weight: 900; color: var(--text-main); margin: 8px 0;">
              $<?php echo number_format($plan['amount'], 0); ?> <span style="font-size: 0.8rem; font-weight: 600; color: var(--accent-lime);">USD</span>
            </div>

            <div style="background: var(--accent-lime-soft); border: 1px solid var(--accent-lime); border-radius: var(--radius-md); padding: 8px 10px; margin-top: 10px;">
              <div style="font-size: 0.75rem; color: var(--text-secondary);">Monthly Profit:</div>
              <div style="font-size: 0.95rem; font-weight: 800; color: var(--accent-lime);">
                +$<?php echo number_format($plan['monthly_profit'], 2); ?>/mo
              </div>
              <div style="font-size: 0.72rem; color: var(--text-muted);">(<?php echo $plan['profit_percentage']; ?>% per month)</div>
            </div>
          </label>
        <?php endforeach; ?>
      </div>

      <button type="submit" class="btn btn-pwa" style="width: 100%; padding: 14px 20px; font-size: 1rem;">
        Complete Registration & Start Earning
      </button>

      <div style="text-align: center; margin-top: 20px; font-size: 0.9rem; color: var(--text-secondary);">
        Already have an account? <a href="login.php" style="color: var(--accent-lime); font-weight: 700; text-decoration: none;">Sign in here</a>
      </div>
    </form>
  </div>
</div>

<script>
  document.querySelectorAll('input[name="plan_id"]').forEach(radio => {
    radio.addEventListener('change', function() {
      document.querySelectorAll('.plan-card-option').forEach(card => {
        card.classList.remove('selected');
        card.style.borderColor = 'var(--border-glass)';
        card.style.background = 'var(--bg-subtle)';
      });
      const parentCard = this.closest('.plan-card-option');
      if (parentCard) {
        parentCard.classList.add('selected');
        parentCard.style.borderColor = 'var(--accent-lime)';
        parentCard.style.background = 'var(--bg-card)';
      }
    });
  });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
