<?php
require_once __DIR__ . '/auth.php';

if (getAdminUser()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please provide both email address and password.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if (($user['role'] ?? '') === 'admin') {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user'] = $user;
                header('Location: index.php');
                exit;
            } else {
                $error = 'Access Denied: This account does not possess administrator privileges.';
            }
        } else {
            $error = 'Invalid email or password. Please verify credentials and try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Portal Login | EarnNow</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    body {
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      background: var(--bg-body);
      padding: 20px;
    }
    .admin-login-card {
      width: 100%;
      max-width: 420px;
      background: var(--bg-card);
      border: 1px solid var(--border-glass);
      border-radius: var(--radius-xl);
      padding: 32px 28px;
      box-shadow: var(--shadow-glass);
    }
  </style>
</head>
<body>

<div class="admin-login-card">
  <div style="text-align: center; margin-bottom: 24px;">
    <div style="display: inline-flex; align-items: center; justify-content: center; width: 56px; height: 56px; border-radius: 16px; background: rgba(147, 215, 38, 0.12); border: 1px solid var(--accent-lime); margin-bottom: 12px;">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="var(--accent-lime)" stroke-width="2.2">
        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
      </svg>
    </div>
    <h1 style="font-size: 1.5rem; font-weight: 800; color: var(--text-main); margin-bottom: 4px;">Admin Portal</h1>
    <p style="color: var(--text-secondary); font-size: 0.85rem;">Secure administrative access for EarnNow</p>
  </div>

  <?php if ($error): ?>
    <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.35); color: #ef4444; padding: 12px; border-radius: var(--radius-md); margin-bottom: 18px; font-size: 0.85rem;">
      <?php echo htmlspecialchars($error); ?>
    </div>
  <?php endif; ?>

  <form method="POST" action="login.php">
    <div class="form-group">
      <label class="form-label">Admin Email</label>
      <input type="email" name="email" class="form-input" placeholder="mustansar.ba24@gamil.com" value="<?php echo htmlspecialchars($_POST['email'] ?? 'mustansar.ba24@gamil.com'); ?>" required autofocus>
    </div>

    <div class="form-group">
      <label class="form-label">Password</label>
      <input type="password" name="password" class="form-input" placeholder="Enter admin password" required>
    </div>

    <button type="submit" class="btn btn-pwa" style="width: 100%; padding: 12px; margin-top: 10px; font-size: 0.95rem;">
      Sign In to Admin
    </button>
  </form>

  <div style="text-align: center; margin-top: 20px;">
    <span style="color: var(--text-muted); font-size: 0.8rem; letter-spacing: 0.02em;">
      🔒 Desktop Administrative Portal
    </span>
  </div>
</div>

</body>
</html>
