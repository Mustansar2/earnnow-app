<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('EARNNOW_APP')) {
    define('EARNNOW_APP', true);
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$isLoggedIn = false;
$currentUser = null;

if (isset($_SESSION['user_id'])) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $currentUser = $stmt->fetch();
    if ($currentUser) {
        // Restrict admin accounts from user app pages; redirect directly to desktop Admin portal
        if (($currentUser['role'] ?? '') === 'admin') {
            header('Location: admin/index.php');
            exit;
        }
        checkAndApplyPendingUpgrade($currentUser);
        $isLoggedIn = true;
        $_SESSION['user'] = $currentUser;
    } else {
        unset($_SESSION['user_id'], $_SESSION['user']);
    }
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  
  <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | EarnNow' : 'EarnNow - Financial & Earning Platform'; ?></title>
  
  <!-- Inline theme preloader to prevent flash of unstyled theme -->
  <script>
    (function() {
      const savedTheme = localStorage.getItem('earnnow_theme') || 'dark';
      document.documentElement.setAttribute('data-theme', savedTheme);
    })();
  </script>

  <!-- PWA Metadata & Web App Manifest -->
  <link rel="manifest" href="manifest.json">
  <meta name="theme-color" content="#93D726">
  <meta name="mobile-web-app-capable" content="yes">
  
  <!-- iOS Safari PWA Meta Tags -->
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="EarnNow">
  <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
  
  <!-- Favicons -->
  <link rel="icon" type="image/png" sizes="48x48" href="assets/icons/favicon.png">
  <link rel="icon" type="image/png" sizes="192x192" href="assets/icons/icon-192x192.png">

  <!-- Core Glassmorphism Stylesheet -->
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="app-layout">
  <!-- 1. Left Sidebar Navigation -->
  <aside class="app-sidebar" id="appSidebar">
    <!-- Brand Logo Area (Aligned to Header Height) -->
    <a href="<?php echo $isLoggedIn ? 'index.php' : 'login.php'; ?>" class="sidebar-brand">
      <div class="brand-icon-wrapper">
        <svg class="brand-svg-logo" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect width="36" height="36" rx="9" fill="url(#brandGrad)"/>
          <path d="M10 11H26M10 18H22M10 25H26" stroke="#0B0E14" stroke-width="2.8" stroke-linecap="round"/>
          <circle cx="25" cy="18" r="2.5" fill="#0B0E14"/>
          <defs>
            <linearGradient id="brandGrad" x1="0" y1="0" x2="36" y2="36" gradientUnits="userSpaceOnUse">
              <stop stop-color="#93D726"/>
              <stop offset="1" stop-color="#6DB311"/>
            </linearGradient>
          </defs>
        </svg>
      </div>
      <div class="brand-title-group">
        <div class="brand-title">
          <span>Earn</span><span class="brand-title-highlight">Now</span>
          <span class="brand-pill-tag">PRO</span>
        </div>
        <span class="brand-subtitle">Invest &amp; Earn</span>
      </div>
    </a>

    <!-- Navigation Menu Items -->
    <nav class="sidebar-nav">
      <div class="nav-section-title">Main Menu</div>
      
      <?php if ($isLoggedIn): ?>
        <a href="index.php" class="sidebar-link <?php echo ($currentPage === 'index.php') ? 'active' : ''; ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="3" width="7" height="7"></rect>
            <rect x="14" y="3" width="7" height="7"></rect>
            <rect x="14" y="14" width="7" height="7"></rect>
            <rect x="3" y="14" width="7" height="7"></rect>
          </svg>
          Dashboard
        </a>

        <a href="plans.php" class="sidebar-link <?php echo ($currentPage === 'plans.php') ? 'active' : ''; ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
            <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
            <line x1="12" y1="22.08" x2="12" y2="12"></line>
          </svg>
          Investment Packages
        </a>

        <a href="videos.php" class="sidebar-link <?php echo ($currentPage === 'videos.php') ? 'active' : ''; ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polygon points="23 7 16 12 23 17 23 7"></polygon>
            <rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>
          </svg>
          Watch Tutorials
        </a>

        <a href="deposit.php" class="sidebar-link <?php echo ($currentPage === 'deposit.php') ? 'active' : ''; ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
          </svg>
          Deposit Funds
        </a>

        <a href="withdraw.php" class="sidebar-link <?php echo ($currentPage === 'withdraw.php') ? 'active' : ''; ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
            <polyline points="7 10 12 15 17 10"></polyline>
            <line x1="12" y1="15" x2="12" y2="3"></line>
          </svg>
          Withdraw (TRC20)
        </a>

        <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
          <div class="nav-section-title" style="margin-top: 14px; color: var(--accent-lime);">Admin Management</div>
          <a href="admin/index.php" class="sidebar-link" style="border-left: 2px solid var(--accent-lime); background: rgba(147, 215, 38, 0.08);">
            <svg viewBox="0 0 24 24" fill="none" stroke="var(--accent-lime)" stroke-width="2">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
            </svg>
            <span style="font-weight: 700; color: var(--accent-lime);">Admin Portal</span>
          </a>
        <?php endif; ?>
      <?php else: ?>
        <a href="login.php" class="sidebar-link <?php echo ($currentPage === 'login.php') ? 'active' : ''; ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
            <polyline points="10 17 15 12 10 7"></polyline>
            <line x1="15" y1="12" x2="3" y2="12"></line>
          </svg>
          Sign In
        </a>

        <a href="register.php" class="sidebar-link <?php echo ($currentPage === 'register.php') ? 'active' : ''; ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
            <circle cx="8.5" cy="7.5" r="4"></circle>
            <line x1="20" y1="8" x2="20" y2="14"></line>
            <line x1="23" y1="11" x2="17" y2="11"></line>
          </svg>
          Create Account
        </a>
      <?php endif; ?>
    </nav>

    <!-- Pinned Bottom Section: User Profile & Logout -->
    <div class="sidebar-bottom">
      <?php if ($isLoggedIn): ?>
        <a href="profile.php" class="user-snippet-card">
          <div class="user-mini-avatar">
            <?php echo strtoupper(substr($currentUser['name'], 0, 1)); ?>
          </div>
          <div class="user-mini-info">
            <div class="user-mini-name"><?php echo htmlspecialchars($currentUser['name']); ?></div>
            <div class="user-mini-plan"><?php echo htmlspecialchars($currentUser['plan_name']); ?></div>
          </div>
        </a>

        <a href="profile.php" class="sidebar-action-btn sidebar-btn-profile <?php echo ($currentPage === 'profile.php') ? 'active' : ''; ?>">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
            <circle cx="12" cy="7" r="4"></circle>
          </svg>
          Profile & Settings
        </a>

        <a href="logout.php" class="sidebar-action-btn sidebar-btn-logout">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
            <polyline points="16 17 21 12 16 7"></polyline>
            <line x1="21" y1="12" x2="9" y2="12"></line>
          </svg>
          Logout
        </a>
      <?php else: ?>
        <div style="font-size: 0.82rem; color: var(--text-muted); text-align: center; padding: 6px;">
          Welcome to EarnNow
        </div>
      <?php endif; ?>
    </div>
  </aside>

  <!-- 2. Right Main Layout Area -->
  <div class="app-main">
    <!-- Top Header Bar with Theme Switcher -->
    <header class="app-header">
      <div class="header-left">
        <button type="button" class="sidebar-toggle-btn" id="sidebarToggle" aria-label="Toggle Navigation">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
          </svg>
        </button>
        <span class="header-title-crumb">
          <?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'EarnNow Platform'; ?>
        </span>
      </div>

      <div class="header-right">
        <!-- Dark / Light Mode Switcher Button -->
        <button type="button" class="theme-toggle-btn" id="themeToggleBtn" aria-label="Toggle Dark/Light Mode">
          <span id="themeIcon">
            <!-- Dynamic SVG icon inserted via script -->
          </span>
          <span id="themeLabel">Theme</span>
        </button>
      </div>
    </header>

    <!-- Page Content Slot -->
    <div class="page-container">
