# Progressive Web App (PWA) Integration Guide for PHP Websites

This guide explains how the PWA installation system works and how to integrate it into any PHP-based website (custom PHP, MVC framework, or WordPress).

---

## Table of Contents
1. [Overview & Architecture](#1-overview--architecture)
2. [Exact Files Created in this Project](#2-exact-files-created-in-this-project)
3. [Service Worker Strategy (PHP & Dynamic Data Protection)](#3-service-worker-strategy-php--dynamic-data-protection)
4. [Cross-Platform Installation Engine](#4-cross-platform-installation-engine)
5. [Step-by-Step Integration into an Existing PHP Site](#5-step-by-step-integration-into-an-existing-php-site)
6. [WordPress Theme Integration](#6-wordpress-theme-integration)
7. [Security & Session Safeguards](#7-security--session-safeguards)
8. [Testing & Verification Checklist](#8-testing--verification-checklist)

---

## 1. Overview & Architecture

A Progressive Web App (PWA) enables a traditional website to be installed on mobile devices (Android, iOS) and desktop computers (Chrome, Edge) without requiring separate mobile frameworks like Flutter or React Native.

### How it works:
- **Web App Manifest (`manifest.json`)**: Declares app metadata, icons, standalone display mode, and theme colors.
- **Service Worker (`sw.js`)**: Background worker running in the browser. Handles offline caching of static assets and provides an offline fallback page while preserving dynamic PHP server responses.
- **Install Controller (`pwa-install.js`)**: 
  - Intercepts Chromium/Android's `beforeinstallprompt` event.
  - Manages button states (`Install App`, `Installing...`, `App Installed`, `Installation Unavailable`).
  - Detects iOS Safari and presents an accessible step-by-step "Add to Home Screen" bottom sheet.
  - Detects standalone mode (running as installed app).

---

## 2. Exact Files Created in this Project

```text
d:\Wordpress\EarnNow\
│
├── manifest.json                  # PWA Manifest (metadata, theme, icons, standalone)
├── sw.js                          # Service Worker (Network-First dynamic, Cache-First static)
├── offline.html                   # Offline fallback page shown when connection drops
├── index.php                      # Dashboard / Home page with PWA layout
├── profile.php                    # User Profile with Change Password & Install App button
│
├── assets\
│   ├── css\
│   │   └── style.css              # Styling for profile, buttons, modals, & animations
│   ├── js\
│   │   └── pwa-install.js         # Complete PWA install engine & event listener
│   └── icons\
│       ├── icon-192x192.png       # Standard PWA icon (192x192)
│       ├── icon-512x512.png       # High-res PWA icon (512x512)
│       ├── icon-maskable-512x512.png # Maskable icon for Android adaptive launchers
│       ├── apple-touch-icon.png   # iOS home screen icon (180x180)
│       └── favicon.png            # Browser tab icon (48x48)
│
└── includes\
    ├── header.php                 # Layout header (manifest, meta tags, theme color)
    └── footer.php                 # Layout footer (pwa-install.js inclusion)
```

---

## 3. Service Worker Strategy (PHP & Dynamic Data Protection)

PHP websites have dynamic content, user sessions, and database updates. **Blindly caching PHP pages breaks authentication and leaks private user data.**

Our `sw.js` uses a strict **Dynamic-First Policy**:

| Request Type | Strategy | Reason |
| :--- | :--- | :--- |
| **HTML Navigation (`.php`, document)** | **Network-First** | Always gets real-time data from PHP/MySQL. If network fails, serves `offline.html`. Dynamic responses are **never cached on disk**. |
| **Sensitive Endpoints** (`login.php`, `logout.php`, `change-password.php`, `admin/`, `api/`) | **Network-Only (Bypass)** | Never intercepted or cached. |
| **POST / PUT / DELETE Requests** | **Network-Only** | Form submissions and authentication requests pass straight through to the server. |
| **Static Assets (CSS, JS, PNG, SVG, Fonts)** | **Stale-While-Revalidate / Cache-First** | Speeds up load times, reduces server bandwidth, and guarantees UI shell loads offline. |

---

## 4. Cross-Platform Installation Engine

### A. Android & Desktop Chrome / Edge
1. The browser fires the `beforeinstallprompt` event.
2. `pwa-install.js` intercepts and prevents the browser's default bar (`e.preventDefault()`).
3. The "Install App" button is highlighted and ready.
4. When clicked, `deferredPrompt.prompt()` triggers the browser's native install dialog.
5. The `appinstalled` event fires upon completion, updating the button to disabled **"App Installed"**.

### B. iPhone & iPad (iOS Safari)
1. iOS Safari does not support `beforeinstallprompt`.
2. `pwa-install.js` detects iOS Safari via User Agent and touch points.
3. When the user taps "Install App", a modal slides up with visual instructions:
   - **Step 1:** Tap the Safari **Share** icon.
   - **Step 2:** Select **"Add to Home Screen"**.
   - **Step 3:** Tap **"Add"** in the top-right corner.

### C. Standalone Mode Detection
When a user opens the app from their home screen:
- `window.matchMedia('(display-mode: standalone)').matches` or `navigator.standalone === true`.
- The button is automatically disabled with the state: **"✓ App Installed"** and badge: **"Active in standalone app"**.

---

## 5. Step-by-Step Integration into an Existing PHP Site

If you want to add this system to an existing PHP codebase:

### Step 1: Copy Core Files to Web Root
1. Copy `manifest.json`, `sw.js`, and `offline.html` to your site's root directory (`public_html/` or `htdocs/`).
2. Copy `assets/icons/` to your public assets directory.
3. Copy `assets/js/pwa-install.js` to your public javascripts folder.

### Step 2: Add Meta Tags to your Main Header (`header.php`)
Inside `<head>...</head>`, add:
```html
<!-- PWA Manifest & Theme Color -->
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#10b981">
<meta name="mobile-web-app-capable" content="yes">

<!-- Apple iOS Safari PWA Meta Tags -->
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="EarnNow">
<link rel="apple-touch-icon" href="/assets/icons/apple-touch-icon.png">
```

### Step 3: Add Script to Main Footer (`footer.php`)
Before `</body>`, add:
```html
<script src="/assets/js/pwa-install.js"></script>
```

### Step 4: Add the Install App Button to Profile/Account Page (`profile.php`)
Place this HTML markup in your profile section beside Change Password:
```html
<div class="action-row">
  <div class="action-info">
    <h4>Install App</h4>
    <p>Install on your mobile or desktop device for quick, app-like access.</p>
    <div id="pwaStatusBadge" class="pwa-status-badge badge-info">Checking installability...</div>
  </div>
  <div>
    <button type="button" id="pwaInstallBtn" class="btn btn-pwa" aria-label="Install App">
      <span class="pwa-btn-icon">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
        </svg>
      </span>
      <span class="pwa-btn-text">Install App</span>
    </button>
  </div>
</div>
```

---

## 6. WordPress Theme Integration

If integrating into a WordPress theme:

1. **Upload files**:
   - Upload `manifest.json`, `sw.js`, and `offline.html` to your WordPress root (where `wp-config.php` is located) so the Service Worker has root scope.
2. **Enqueue assets in `functions.php`**:
   ```php
   function earnnow_pwa_assets() {
       wp_enqueue_style('earnnow-pwa-style', get_template_directory_uri() . '/assets/css/style.css', array(), '1.0');
       wp_enqueue_script('earnnow-pwa-script', get_template_directory_uri() . '/assets/js/pwa-install.js', array(), '1.0', true);
   }
   add_action('wp_enqueue_scripts', 'earnnow_pwa_assets');

   function earnnow_pwa_head_tags() {
       ?>
       <link rel="manifest" href="/manifest.json">
       <meta name="theme-color" content="#10b981">
       <meta name="apple-mobile-web-app-capable" content="yes">
       <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
       <meta name="apple-mobile-web-app-title" content="EarnNow">
       <link rel="apple-touch-icon" href="/assets/icons/apple-touch-icon.png">
       <?php
   }
   add_action('wp_head', 'earnnow_pwa_head_tags');
   ```
3. Add the Install button markup inside your account template (e.g., WooCommerce account page or custom profile page).

---

## 7. Security & Session Safeguards

1. **Zero Credential Caching**:
   - `sw.js` never intercepts or caches `POST` requests, preventing login passwords or payment details from ever being logged.
2. **Session Privacy**:
   - Dynamic pages (such as user balance, transaction histories, and profile edits) use **Network-First** with **no cache write**. User A's data will never be stored locally or visible to User B.
3. **HTTPS Requirement**:
   - PWAs require HTTPS in production. The Service Worker will only activate in secure contexts (HTTPS or `localhost`).
4. **Device Independence**:
   - Installation is browser/device-based. Different users can log into their own accounts on the same installed app.

---

## 8. Testing & Verification Checklist

- [x] **Manifest Validation**: `manifest.json` is valid JSON, returns `application/json`, and includes 192, 512, and maskable icons.
- [x] **Service Worker Scope**: `sw.js` registers at scope `./` without errors.
- [x] **Android Prompt**: Captured `beforeinstallprompt` event; clicking "Install App" triggers native dialog.
- [x] **App Installed Transition**: Once installed, button updates to disabled state `"✓ App Installed"`.
- [x] **iOS Safari Modal**: Opening on iPhone / iPad shows the "Add to Home Screen" instructions sheet.
- [x] **Standalone Mode**: Opening in standalone mode hides install prompts and marks app as installed.
- [x] **Offline Test**: Disconnecting network and navigating renders `offline.html` with retry option.
