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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user'] = [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'phone' => $user['phone'],
                'plan_id' => $user['plan_id'],
                'plan_name' => $user['plan_name'],
                'plan_amount' => $user['plan_amount'],
                'monthly_profit' => $user['monthly_profit'],
                'balance' => $user['balance']
            ];

            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    }
}

$pageTitle = 'Sign In to Your Account';
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 460px; margin: 40px auto;">
  <div style="text-align: center; margin-bottom: 28px;">
    <h1 style="font-size: 1.85rem; font-weight: 800; color: var(--text-main); margin-bottom: 6px;">Welcome Back</h1>
    <p style="color: var(--text-secondary); font-size: 0.95rem;">Log in to view your active package, monthly profit, and earnings.</p>
  </div>

  <?php if ($error): ?>
    <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.35); color: #ef4444; padding: 14px 18px; border-radius: var(--radius-lg); margin-bottom: 24px; font-size: 0.95rem;">
      <?php echo htmlspecialchars($error); ?>
    </div>
  <?php endif; ?>

  <div class="card">
    <form method="POST" action="login.php">
      <div class="form-group">
        <label class="form-label">Email Address</label>
        <input type="email" name="email" class="form-input" placeholder="name@example.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required autofocus>
      </div>

      <div class="form-group" style="margin-bottom: 26px;">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-input" placeholder="Enter your password" required>
      </div>

      <button type="submit" class="btn btn-pwa" style="width: 100%; padding: 14px 20px; font-size: 1rem;">
        Sign In
      </button>

      <div style="text-align: center; margin-top: 20px; font-size: 0.9rem; color: var(--text-secondary);">
        Don't have an account? <a href="register.php" style="color: var(--accent-lime); font-weight: 700; text-decoration: none;">Create Account</a>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
