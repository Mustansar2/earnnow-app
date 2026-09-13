/**
 * EarnNow PWA Installation Manager (pwa-install.js)
 * 
 * Features:
 * - Cross-platform support (Android, iOS Safari, Desktop Chrome/Edge)
 * - Native `beforeinstallprompt` event handling
 * - iOS Safari "Add to Home Screen" interactive bottom sheet guide
 * - Standalone mode detection (checks display-mode and navigator.standalone)
 * - Dynamic button states: "Install App", "Installing...", "App Installed", "Installation Unavailable"
 * - Service worker registration and lifecycle handling
 */

(function () {
  'use strict';

  // State
  let deferredPrompt = null;
  let isStandalone = false;
  let isIOS = false;
  let isSafari = false;

  // DOM elements cache
  let installBtn = null;
  let statusBadge = null;

  // Check if running in standalone mode (already installed & opened as app)
  function checkStandalone() {
    return (
      window.matchMedia('(display-mode: standalone)').matches ||
      window.matchMedia('(display-mode: fullscreen)').matches ||
      window.matchMedia('(display-mode: minimal-ui)').matches ||
      window.navigator.standalone === true ||
      document.referrer.includes('android-app://')
    );
  }

  // Detect iOS and Safari
  function detectDevice() {
    const userAgent = window.navigator.userAgent.toLowerCase();
    isIOS = /iphone|ipad|ipod/.test(userAgent) || 
            (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1); // iPadOS
    isSafari = isIOS && /safari/.test(userAgent) && !/crios|fxios|opios/.test(userAgent);
  }

  // Initialize Service Worker
  function registerServiceWorker() {
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        // Register sw.js relative to current origin/root
        const swPath = './sw.js';
        navigator.serviceWorker
          .register(swPath, { scope: './' })
          .then((registration) => {
            console.log('[EarnNow PWA] Service Worker registered successfully with scope:', registration.scope);

            // Handle updates
            registration.onupdatefound = () => {
              const installingWorker = registration.installing;
              if (installingWorker) {
                installingWorker.onstatechange = () => {
                  if (installingWorker.state === 'installed' && navigator.serviceWorker.controller) {
                    console.log('[EarnNow PWA] New content is available; please refresh.');
                  }
                };
              }
            };
          })
          .catch((error) => {
            console.warn('[EarnNow PWA] Service Worker registration failed:', error);
          });
      });
    }
  }

  // Set Button UI State
  function setButtonState(state) {
    if (!installBtn) return;

    const iconSpan = installBtn.querySelector('.pwa-btn-icon');
    const textSpan = installBtn.querySelector('.pwa-btn-text');

    switch (state) {
      case 'installed':
        installBtn.disabled = true;
        installBtn.classList.remove('btn-loading', 'btn-primary');
        installBtn.classList.add('btn-installed');
        if (textSpan) textSpan.textContent = 'App Installed';
        if (iconSpan) {
          iconSpan.innerHTML = `
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
          `;
        }
        if (statusBadge) {
          statusBadge.textContent = 'Active in standalone app';
          statusBadge.className = 'pwa-status-badge badge-success';
        }
        break;

      case 'installing':
        installBtn.disabled = true;
        installBtn.classList.add('btn-loading');
        if (textSpan) textSpan.textContent = 'Installing...';
        if (iconSpan) {
          iconSpan.innerHTML = `
            <svg class="pwa-spinner" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle>
              <path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
          `;
        }
        break;

      case 'ready':
        installBtn.disabled = false;
        installBtn.classList.remove('btn-loading', 'btn-installed');
        installBtn.classList.add('btn-primary');
        if (textSpan) textSpan.textContent = 'Install App';
        if (iconSpan) {
          iconSpan.innerHTML = `
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
          `;
        }
        if (statusBadge) {
          statusBadge.textContent = 'Ready to install on this device';
          statusBadge.className = 'pwa-status-badge badge-info';
        }
        break;

      case 'unavailable':
        installBtn.disabled = false; // keep clickable so user gets instructions modal!
        installBtn.classList.remove('btn-loading', 'btn-installed');
        if (textSpan) textSpan.textContent = 'Install Instructions';
        if (iconSpan) {
          iconSpan.innerHTML = `
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          `;
        }
        if (statusBadge) {
          statusBadge.textContent = 'Browser manual installation';
          statusBadge.className = 'pwa-status-badge badge-warning';
        }
        break;
    }
  }

  // Handle Install Button Click
  async function onInstallBtnClick() {
    if (isStandalone) {
      alert('EarnNow is already installed and running in app mode.');
      return;
    }

    // 1. If native prompt is available (Android / Chromium)
    if (deferredPrompt) {
      setButtonState('installing');
      try {
        deferredPrompt.prompt();
        const { outcome } = await deferredPrompt.userChoice;
        console.log(`[EarnNow PWA] User prompt outcome: ${outcome}`);

        if (outcome === 'accepted') {
          setButtonState('installed');
        } else {
          setButtonState('ready');
        }
      } catch (err) {
        console.error('[EarnNow PWA] Error triggering install prompt:', err);
        setButtonState('ready');
      } finally {
        deferredPrompt = null;
      }
      return;
    }

    // 2. If iOS Safari
    if (isIOS) {
      showIOSModal();
      return;
    }

    // 3. Fallback for Desktop / browsers where beforeinstallprompt hasn't fired
    showDesktopFallbackModal();
  }

  // Create & Show iOS Safari Instructions Modal
  function showIOSModal() {
    let modal = document.getElementById('pwaIOSModal');
    if (!modal) {
      modal = document.createElement('div');
      modal.id = 'pwaIOSModal';
      modal.className = 'pwa-modal-overlay';
      modal.innerHTML = `
        <div class="pwa-modal-card">
          <div class="pwa-modal-header">
            <div class="pwa-modal-app-badge">
              <img src="assets/icons/icon-192x192.png" alt="EarnNow Icon" class="pwa-modal-icon">
              <div>
                <h3>Install EarnNow</h3>
                <p>Install on iPhone / iPad Home Screen</p>
              </div>
            </div>
            <button class="pwa-modal-close" aria-label="Close modal">&times;</button>
          </div>
          <div class="pwa-modal-body">
            <ol class="pwa-step-list">
              <li>
                <div class="step-num">1</div>
                <div class="step-text">
                  In Safari, tap the <strong>Share</strong> button in the navigation bar.
                  <div class="step-icon-hint">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#0284c7" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                    </svg>
                    <span>(Share icon at bottom or top of Safari)</span>
                  </div>
                </div>
              </li>
              <li>
                <div class="step-num">2</div>
                <div class="step-text">
                  Scroll down the share sheet and select <strong>Add to Home Screen</strong>.
                  <div class="step-icon-hint">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2">
                      <rect x="3" y="3" width="18" height="18" rx="4" />
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v8m-4-4h8" />
                    </svg>
                    <span>[+] Add to Home Screen</span>
                  </div>
                </div>
              </li>
              <li>
                <div class="step-num">3</div>
                <div class="step-text">
                  Tap <strong>Add</strong> in the top-right corner to complete installation.
                </div>
              </li>
            </ol>
          </div>
          <div class="pwa-modal-footer">
            <button class="pwa-btn-gotit">Got it!</button>
          </div>
        </div>
      `;
      document.body.appendChild(modal);

      // Close events
      const closeBtn = modal.querySelector('.pwa-modal-close');
      const gotItBtn = modal.querySelector('.pwa-btn-gotit');
      const closeModal = () => modal.classList.remove('pwa-modal-visible');

      closeBtn.addEventListener('click', closeModal);
      gotItBtn.addEventListener('click', closeModal);
      modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
      });
    }

    setTimeout(() => {
      modal.classList.add('pwa-modal-visible');
    }, 10);
  }

  // Create & Show Fallback Modal for Desktop or browsers without beforeinstallprompt
  function showDesktopFallbackModal() {
    let modal = document.getElementById('pwaFallbackModal');
    if (!modal) {
      modal = document.createElement('div');
      modal.id = 'pwaFallbackModal';
      modal.className = 'pwa-modal-overlay';
      modal.innerHTML = `
        <div class="pwa-modal-card">
          <div class="pwa-modal-header">
            <div class="pwa-modal-app-badge">
              <img src="assets/icons/icon-192x192.png" alt="EarnNow Icon" class="pwa-modal-icon">
              <div>
                <h3>Install EarnNow App</h3>
                <p>Browser Installation Guide</p>
              </div>
            </div>
            <button class="pwa-modal-close" aria-label="Close modal">&times;</button>
          </div>
          <div class="pwa-modal-body">
            <p class="modal-info-intro">If the automatic install prompt did not appear, you can install EarnNow directly using your browser's menu:</p>
            <div class="pwa-instruction-box">
              <h4>Chrome / Edge / Brave:</h4>
              <p>1. Look for the <strong>Install</strong> icon (<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>) in the right side of the address/URL bar.</p>
              <p>2. Or click the <strong>three dots menu (&vellip;)</strong> &rarr; select <strong>"Install EarnNow"</strong> or <strong>"Apps" &rarr; "Install this site as an app"</strong>.</p>
            </div>
            <div class="pwa-instruction-box" style="margin-top: 12px;">
              <h4>Mobile Firefox / Opera:</h4>
              <p>Tap the browser menu &rarr; select <strong>"Install"</strong> or <strong>"Add to Home Screen"</strong>.</p>
            </div>
          </div>
          <div class="pwa-modal-footer">
            <button class="pwa-btn-gotit">Close</button>
          </div>
        </div>
      `;
      document.body.appendChild(modal);

      const closeBtn = modal.querySelector('.pwa-modal-close');
      const gotItBtn = modal.querySelector('.pwa-btn-gotit');
      const closeModal = () => modal.classList.remove('pwa-modal-visible');

      closeBtn.addEventListener('click', closeModal);
      gotItBtn.addEventListener('click', closeModal);
      modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
      });
    }

    setTimeout(() => {
      modal.classList.add('pwa-modal-visible');
    }, 10);
  }

  // Initialize UI & Event Handlers
  function init() {
    detectDevice();
    isStandalone = checkStandalone();
    registerServiceWorker();

    installBtn = document.getElementById('pwaInstallBtn');
    statusBadge = document.getElementById('pwaStatusBadge');

    if (!installBtn) {
      // Button not on current page (e.g. non-profile page)
      return;
    }

    // Attach click listener
    installBtn.addEventListener('click', onInstallBtnClick);

    // Initial state check
    if (isStandalone) {
      setButtonState('installed');
    } else if (isIOS) {
      setButtonState('ready');
    } else {
      // Default initial state while waiting for beforeinstallprompt
      setButtonState('ready');
    }

    // Listen for Chromium / Android beforeinstallprompt
    window.addEventListener('beforeinstallprompt', (e) => {
      // Prevent browser's default mini-infobar
      e.preventDefault();
      deferredPrompt = e;
      console.log('[EarnNow PWA] beforeinstallprompt event captured');

      if (!isStandalone) {
        setButtonState('ready');
      }
    });

    // Listen for successful installation event
    window.addEventListener('appinstalled', (evt) => {
      console.log('[EarnNow PWA] App was successfully installed!');
      deferredPrompt = null;
      isStandalone = true;
      setButtonState('installed');
    });

    // Detect standalone mode change on mobile/desktop window
    window.matchMedia('(display-mode: standalone)').addEventListener('change', (e) => {
      if (e.matches) {
        isStandalone = true;
        setButtonState('installed');
      }
    });
  }

  // Expose helper globally if needed
  window.EarnNowPWA = {
    init,
    showIOSModal,
    showDesktopFallbackModal,
    isStandalone: checkStandalone
  };

  // Run when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
