<?php
require_once __DIR__ . '/auth.php';
$adminUser = requireAdmin();
$currentAdminPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | Admin Portal' : 'Admin Portal | EarnNow'; ?></title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    .admin-nav-bar {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      padding: 16px 24px;
      background: var(--bg-card);
      border-bottom: 1px solid var(--border-glass);
      position: sticky;
      top: 0;
      z-index: 100;
    }
    .admin-nav-links {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
    }
    .admin-nav-link {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 14px;
      border-radius: var(--radius-md);
      font-size: 0.85rem;
      font-weight: 600;
      color: var(--text-secondary);
      text-decoration: none;
      transition: all 0.2s ease;
    }
    .admin-nav-link:hover {
      color: var(--text-main);
      background: var(--bg-subtle);
    }
    .admin-nav-link.active {
      color: var(--accent-lime);
      background: rgba(147, 215, 38, 0.12);
      border: 1px solid var(--accent-lime);
    }
    .admin-container {
      max-width: 1300px;
      margin: 0 auto;
      padding: 24px 20px 60px 20px;
    }
  </style>
</head>
<body>

<header class="admin-nav-bar">
  <div style="display: flex; align-items: center; gap: 12px;">
    <div style="display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 10px; background: rgba(147, 215, 38, 0.15); border: 1px solid var(--accent-lime);">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--accent-lime)" stroke-width="2.2">
        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
      </svg>
    </div>
    <div>
      <div style="font-weight: 800; font-size: 1.05rem; color: var(--text-main); display: flex; align-items: center; gap: 6px;">
        EarnNow <span style="font-size: 0.7rem; background: var(--accent-lime); color: #000; padding: 2px 6px; border-radius: 4px; font-weight: 800;">ADMIN</span>
      </div>
      <div style="font-size: 0.75rem; color: var(--text-muted);">
        Signed in as <?php echo htmlspecialchars($adminUser['email']); ?>
      </div>
    </div>
  </div>

  <nav class="admin-nav-links">
    <a href="index.php" class="admin-nav-link <?php echo ($currentAdminPage === 'index.php') ? 'active' : ''; ?>">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
        <line x1="3" y1="9" x2="21" y2="9"></line>
        <line x1="9" y1="21" x2="9" y2="9"></line>
      </svg>
      Payment Submissions
    </a>

    <a href="users.php" class="admin-nav-link <?php echo ($currentAdminPage === 'users.php') ? 'active' : ''; ?>">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
        <circle cx="9" cy="7" r="4"></circle>
        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
      </svg>
      User Packages
      <?php 
        $pendingUsersCount = (int)getPendingPackageCount(); 
        if ($pendingUsersCount > 0): 
      ?>
        <span style="background: #f59e0b; color: #000; font-size: 0.7rem; font-weight: 800; padding: 1px 6px; border-radius: 10px; margin-left: 2px;">
          <?php echo $pendingUsersCount; ?>
        </span>
      <?php endif; ?>
    </a>

    <a href="gateways.php" class="admin-nav-link <?php echo ($currentAdminPage === 'gateways.php') ? 'active' : ''; ?>">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
        <line x1="1" y1="10" x2="23" y2="10"></line>
      </svg>
      Payment Gateways (Dynamic Details)
    </a>

    <a href="videos.php" class="admin-nav-link <?php echo ($currentAdminPage === 'videos.php') ? 'active' : ''; ?>">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polygon points="23 7 16 12 23 17 23 7"></polygon>
        <rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>
      </svg>
      Video Manager
    </a>

    <a href="plans.php" class="admin-nav-link <?php echo ($currentAdminPage === 'plans.php') ? 'active' : ''; ?>">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
        <polyline points="2 17 12 22 22 17"></polyline>
        <polyline points="2 12 12 17 22 12"></polyline>
      </svg>
      Manage Packages
    </a>


    <a href="../logout.php" class="admin-nav-link" style="color: #ef4444;">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
        <polyline points="16 17 21 12 16 7"></polyline>
        <line x1="21" y1="12" x2="9" y2="12"></line>
      </svg>
      Sign Out
    </a>
  </nav>
</header>

<main class="admin-container">
