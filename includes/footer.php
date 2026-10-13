    </div> <!-- End .page-container -->

    <!-- Footer Copyright -->
    <footer style="padding: 12px 20px; text-align: center; font-size: 0.78rem; color: var(--text-muted); border-top: 1px solid var(--border-glass); margin-top: auto;">
      &copy; <?php echo date('Y'); ?> EarnNow Platform. All rights reserved.
    </footer>
  </div> <!-- End .app-main -->
</div> <!-- End .app-layout -->

<!-- Mobile Bottom Navigation Bar (App Experience) -->
<nav class="mobile-bottom-nav">
  <a href="index.php" class="mobile-nav-item <?php echo ($currentPage === 'index.php') ? 'active' : ''; ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <rect x="3" y="3" width="7" height="7"></rect>
      <rect x="14" y="3" width="7" height="7"></rect>
      <rect x="14" y="14" width="7" height="7"></rect>
      <rect x="3" y="14" width="7" height="7"></rect>
    </svg>
    <span>Home</span>
  </a>

  <a href="plans.php" class="mobile-nav-item <?php echo ($currentPage === 'plans.php') ? 'active' : ''; ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
      <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
      <line x1="12" y1="22.08" x2="12" y2="12"></line>
    </svg>
    <span>Plans</span>
  </a>

  <a href="deposit.php" class="mobile-nav-item <?php echo ($currentPage === 'deposit.php') ? 'active' : ''; ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <line x1="12" y1="5" x2="12" y2="19"></line>
      <line x1="5" y1="12" x2="19" y2="12"></line>
    </svg>
    <span>Deposit</span>
  </a>

  <a href="withdraw.php" class="mobile-nav-item <?php echo ($currentPage === 'withdraw.php') ? 'active' : ''; ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
      <polyline points="7 10 12 15 17 10"></polyline>
      <line x1="12" y1="15" x2="12" y2="3"></line>
    </svg>
    <span>Withdraw</span>
  </a>

  <?php if ($isLoggedIn): ?>
    <a href="profile.php" class="mobile-nav-item <?php echo ($currentPage === 'profile.php') ? 'active' : ''; ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
        <circle cx="12" cy="7" r="4"></circle>
      </svg>
      <span>Profile</span>
    </a>
  <?php else: ?>
    <a href="login.php" class="mobile-nav-item <?php echo ($currentPage === 'login.php') ? 'active' : ''; ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
        <polyline points="10 17 15 12 10 7"></polyline>
        <line x1="15" y1="12" x2="3" y2="12"></line>
      </svg>
      <span>Sign In</span>
    </a>
  <?php endif; ?>
</nav>

<!-- Theme Controller & Mobile Drawer Script -->
<script>
  (function() {
    const themeBtn = document.getElementById('themeToggleBtn');
    const themeIcon = document.getElementById('themeIcon');
    const themeLabel = document.getElementById('themeLabel');
    const htmlElem = document.documentElement;

    const sunSVG = `
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="5"></circle>
        <line x1="12" y1="1" x2="12" y2="3"></line>
        <line x1="12" y1="21" x2="12" y2="23"></line>
        <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
        <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
        <line x1="1" y1="12" x2="3" y2="12"></line>
        <line x1="21" y1="12" x2="23" y2="12"></line>
        <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
        <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
      </svg>
    `;

    const moonSVG = `
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
      </svg>
    `;

    function updateThemeUI(theme) {
      if (theme === 'dark') {
        themeIcon.innerHTML = sunSVG;
        themeLabel.textContent = 'Light Mode';
      } else {
        themeIcon.innerHTML = moonSVG;
        themeLabel.textContent = 'Dark Mode';
      }
    }

    const currentTheme = htmlElem.getAttribute('data-theme') || 'dark';
    updateThemeUI(currentTheme);

    if (themeBtn) {
      themeBtn.addEventListener('click', function() {
        const activeTheme = htmlElem.getAttribute('data-theme') || 'dark';
        const newTheme = (activeTheme === 'dark') ? 'light' : 'dark';
        
        htmlElem.setAttribute('data-theme', newTheme);
        localStorage.setItem('earnnow_theme', newTheme);
        updateThemeUI(newTheme);
      });
    }

    // Mobile Sidebar Drawer Toggle
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('appSidebar');

    if (sidebarToggle && sidebar) {
      sidebarToggle.addEventListener('click', function(e) {
        e.stopPropagation();
        sidebar.classList.toggle('sidebar-open');
      });

      document.addEventListener('click', function(e) {
        if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
          sidebar.classList.remove('sidebar-open');
        }
      });
    }
  })();
</script>

<!-- Progressive Web App Script -->
<script src="assets/js/pwa-install.js"></script>
</body>
</html>
