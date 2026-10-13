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
$uStmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$uStmt->execute([$_SESSION['user_id']]);
$currentUser = $uStmt->fetch();

if (!$currentUser) {
    header('Location: login.php');
    exit;
}

$claimSuccess = '';
$claimError = '';

// AJAX handler: Record that user completed watching video
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_video_watched') {
    header('Content-Type: application/json');
    $vId = isset($_POST['video_id']) ? (int)$_POST['video_id'] : 0;
    $vTitle = trim($_POST['video_title'] ?? '');
    $submittedToken = $_POST['watch_token'] ?? '';
    $sessionToken = $_SESSION['video_watch_token'] ?? '';

    if ($vId > 0 && !empty($submittedToken) && $submittedToken === $sessionToken) {
        recordDailyVideoWatched($currentUser['id'], $vId, $vTitle);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid watch verification']);
    }
    exit;
}

// Handle daily video profit claim
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'claim_daily_profit') {
    $vId = isset($_POST['video_id']) ? (int)$_POST['video_id'] : 0;
    $watchedCompleted = isset($_POST['video_completed']) && $_POST['video_completed'] === '1';
    $submittedToken = $_POST['watch_token'] ?? '';
    $sessionToken = $_SESSION['video_watch_token'] ?? '';
    $sessionVid = (int)($_SESSION['video_watch_vid'] ?? 0);
    $watchStartTime = (int)($_SESSION['video_watch_start_time'] ?? 0);
    $elapsedSeconds = time() - $watchStartTime;

    $watchedRow = hasWatchedTodayVideo($currentUser['id']);
    if (!$watchedCompleted && $watchedRow) {
        $watchedCompleted = true;
    }
    if ($vId <= 0 && $watchedRow) {
        $vId = (int)$watchedRow['video_id'];
    }

    if ($vId <= 0) {
        $claimError = 'Claim karne ke liye koi active video mojood nahi hai. Admin ne abhi tak koi video task upload nahi kiya.';
    } elseif (!$watchedCompleted) {
        $claimError = 'Pehle poori video dekhein! Video ko aage/skip nahi kiya ja sakta. Poori video complete hone par hi profit unlock hoga.';
    } elseif (empty($submittedToken) || ($submittedToken !== $sessionToken && !$watchedRow)) {
        $claimError = 'Security verification fail ho gayi. Barah-e-karam page refresh karein aur poori video dekh kar claim karein.';
    } else {
        $res = claimDailyVideoProfit($currentUser['id'], $vId);
        if ($res['success']) {
            $claimSuccess = $res['message'];
            // Invalidate watch session token so it cannot be re-used
            unset($_SESSION['video_watch_token'], $_SESSION['video_watch_vid'], $_SESSION['video_watch_start_time']);
            // Refresh logged in user state
            $uStmt->execute([$currentUser['id']]);
            $currentUser = $uStmt->fetch();
            $_SESSION['user'] = $currentUser;
        } else {
            $claimError = $res['message'];
        }
    }
}

$profitStats = getUserProfitStats($currentUser);
$todayClaimed = hasClaimedDailyVideoProfit($currentUser['id']);
$userPlan = $profitStats['plan'] ?? getPlanById($currentUser['plan_id'] ?? 1);
$dailyProfitAmt = (float)($profitStats['daily_profit'] ?? ($currentUser['plan_amount'] * 0.01));
if ($dailyProfitAmt <= 0) {
    $dailyProfitAmt = 1.00;
}

$missedStats = $profitStats['missed_stats'] ?? ['missed_days' => 0, 'missed_profit' => 0.00];
$missedDays = (int)($missedStats['missed_days'] ?? 0);
$missedProfit = (float)($missedStats['missed_profit'] ?? 0.00);

$pageTitle = 'Training & Daily Videos';
require_once __DIR__ . '/includes/header.php';

// Fetch active videos from database
$allVideos = getVideos(true);
$videos = [];
foreach ($allVideos as $v) {
    if (!empty(trim($v['video_url'] ?? ''))) {
        $videos[] = $v;
    }
}

$hasActiveVideos = !empty($videos);
$selectedId = isset($_GET['v']) ? (int)$_GET['v'] : 0;
$activeVideo = null;

if ($hasActiveVideos) {
    if ($selectedId > 0) {
        foreach ($videos as $v) {
            if ($v['id'] === $selectedId) {
                $activeVideo = $v;
                break;
            }
        }
    }
    if (!$activeVideo) {
        $activeVideo = $videos[0]; // default to first valid active video
    }
}

$parsedSource = ($activeVideo && !empty(trim($activeVideo['video_url'] ?? ''))) ? parseVideoSource($activeVideo['video_url']) : null;
$hasValidActiveVideo = ($activeVideo && $parsedSource && !empty($parsedSource['type']));

// Check if user watched today's video earlier
$userWatchedTodayRow = hasWatchedTodayVideo($currentUser['id']);
$userHasWatchedToday = ($userWatchedTodayRow && $userWatchedTodayRow['watch_date'] === date('Y-m-d'));

// Initialize watch session token ONLY if there is a valid video and user has active package and hasn't claimed yet
if ($hasValidActiveVideo && !$todayClaimed && (($currentUser['package_status'] ?? '') === 'active')) {
    if (empty($_SESSION['video_watch_token']) || ($_SESSION['video_watch_vid'] ?? 0) !== $activeVideo['id']) {
        $_SESSION['video_watch_token'] = bin2hex(random_bytes(16));
        $_SESSION['video_watch_vid'] = $activeVideo['id'];
        $_SESSION['video_watch_start_time'] = time();
    }
}
?>

<!-- In-page Toast Alert Container for Video Warnings -->
<div id="videoToastContainer" style="position: fixed; top: 20px; right: 20px; z-index: 9999; max-width: 360px; display: none;">
  <div id="videoToastMessage" style="background: rgba(17, 24, 39, 0.95); color: #fff; border: 1.5px solid #ef4444; border-radius: var(--radius-md); padding: 12px 18px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); font-size: 0.85rem; font-weight: 700; display: flex; align-items: center; gap: 10px;">
  </div>
</div>

<!-- Page Header -->
<div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 14px; margin-bottom: 16px;">
  <div>
    <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin-bottom: 4px;">Daily Video Tasks &amp; Training</h1>
    <p style="color: var(--text-secondary); font-size: 0.9rem;">
      Watch official daily videos to earn and credit your package's daily profit directly into your wallet.
    </p>
  </div>
  <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
    <a href="admin/videos.php" class="btn btn-secondary" style="font-size: 0.82rem; padding: 7px 14px; display: inline-flex; align-items: center; gap: 6px;">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
      Admin: Manage Videos
    </a>
  <?php endif; ?>
</div>

<!-- Accountability & Attendance Metric Bar (Including Total Missed Days) -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 12px; margin-bottom: 18px;">
  
  <!-- Box 1: Today's Video Status -->
  <div class="card" style="margin-bottom: 0; padding: 14px 18px; border-left: 3px solid <?php echo $todayClaimed ? 'var(--accent-lime)' : '#f59e0b'; ?>;">
    <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); margin-bottom: 4px;">
      Today's Task Status
    </div>
    <div style="font-size: 1.15rem; font-weight: 800; color: <?php echo $todayClaimed ? 'var(--accent-lime)' : '#f59e0b'; ?>; display: flex; align-items: center; gap: 6px;">
      <?php if ($todayClaimed): ?>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        Claimed (+$<?php echo number_format($dailyProfitAmt, 2); ?>)
      <?php elseif (($currentUser['package_status'] ?? '') === 'active'): ?>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        Pending Today
      <?php else: ?>
        Package Inactive
      <?php endif; ?>
    </div>
    <div style="font-size: 0.74rem; color: var(--text-secondary); margin-top: 4px;">
      Task expires at 11:59 PM tonight.
    </div>
  </div>

  <!-- Box 2: Total Missed Days This Month (User Accountability Label) -->
  <div class="card" style="margin-bottom: 0; padding: 14px 18px; border-left: 3px solid <?php echo $missedDays > 0 ? '#ef4444' : 'var(--accent-lime)'; ?>;">
    <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); margin-bottom: 4px; display: flex; align-items: center; justify-content: space-between;">
      <span>Missed Tasks (<?php echo date('M Y'); ?>)</span>
      <span style="font-size: 0.7rem; color: #ef4444; font-weight: 800;">Lapsed Reward</span>
    </div>
    <div style="font-size: 1.25rem; font-weight: 800; color: <?php echo $missedDays > 0 ? '#ef4444' : 'var(--accent-lime)'; ?>; display: flex; align-items: center; gap: 8px;">
      <span><?php echo $missedDays; ?> <?php echo $missedDays == 1 ? 'Day' : 'Days'; ?></span>
      <?php if ($missedDays > 0): ?>
        <span style="font-size: 0.72rem; padding: 2px 8px; border-radius: 6px; background: rgba(239,68,68,0.15); color: #ef4444; border: 1px solid rgba(239,68,68,0.3); font-weight: 800;">
          -$<?php echo number_format($missedProfit, 2); ?> USD Lapsed
        </span>
      <?php else: ?>
        <span style="font-size: 0.72rem; padding: 2px 8px; border-radius: 6px; background: var(--accent-lime-soft); color: var(--accent-lime); border: 1px solid var(--accent-lime); font-weight: 800;">
          🌟 100% Attendance
        </span>
      <?php endif; ?>
    </div>
    <div style="font-size: 0.74rem; color: var(--text-secondary); margin-top: 4px;">
      <?php if ($missedDays > 0): ?>
        Aapne <?php echo $missedDays; ?> din video miss ki, is liye previous days ka reward expire ho gaya.
      <?php else: ?>
        Zabardast! Is mahine aapne koi video task miss nahi kiya.
      <?php endif; ?>
    </div>
  </div>

  <!-- Box 3: Daily Reward Rate -->
  <div class="card" style="margin-bottom: 0; padding: 14px 18px; border-left: 3px solid var(--accent-lime);">
    <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); margin-bottom: 4px;">
      Package Daily Profit
    </div>
    <div style="font-size: 1.25rem; font-weight: 800; color: var(--accent-lime);">
      +$<?php echo number_format($dailyProfitAmt, 2); ?> USD / Day
    </div>
    <div style="font-size: 0.74rem; color: var(--text-secondary); margin-top: 4px;">
      1% daily return guaranteed on active package.
    </div>
  </div>

</div>

<!-- Daily Task & Profit Claim Banner -->
<div class="card" style="margin-bottom: 20px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.08) 0%, rgba(14, 165, 233, 0.08) 100%); border: 2px solid <?php echo $todayClaimed ? 'var(--accent-lime)' : (!$hasValidActiveVideo && !$userHasWatchedToday ? 'rgba(239, 68, 68, 0.4)' : ((($currentUser['package_status'] ?? '') === 'active') ? '#f59e0b' : 'var(--border-glass)')); ?>; border-radius: var(--radius-lg); position: relative; overflow: hidden;">

  <?php if ($claimSuccess): ?>
    <div style="background: var(--accent-lime-soft); border: 1px solid var(--accent-lime); color: var(--accent-lime); padding: 14px 18px; border-radius: var(--radius-md); margin-bottom: 14px; display: flex; align-items: center; gap: 10px; font-weight: 700;">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
      <div><?php echo htmlspecialchars($claimSuccess); ?></div>
    </div>
  <?php endif; ?>

  <?php if ($claimError): ?>
    <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.35); color: #ef4444; padding: 14px 18px; border-radius: var(--radius-md); margin-bottom: 14px; display: flex; align-items: center; gap: 10px; font-weight: 700;">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      <div><?php echo htmlspecialchars($claimError); ?></div>
    </div>
  <?php endif; ?>

  <?php if ($todayClaimed): ?>
    <!-- SCENARIO 1: PROFIT ALREADY CLAIMED TODAY -->
    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; width: 100%;">
      <div style="display: flex; align-items: center; gap: 14px; flex: 1; min-width: 280px;">
        <div style="width: 52px; height: 52px; border-radius: 14px; background: rgba(147, 215, 38, 0.2); border: 2px solid var(--accent-lime); display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: var(--accent-lime);">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        </div>
        <div>
          <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 2px;">
            <span style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; padding: 2px 8px; border-radius: 12px; background: var(--accent-lime-soft); color: var(--accent-lime); border: 1px solid var(--accent-lime);">
              Task Completed • <?php echo date('M d, Y'); ?>
            </span>
          </div>
          <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin-bottom: 4px;">
            Aaj ki Daily Video Watch Profit Claim Ho Chuka Hai! ✅
          </h3>
          <p style="font-size: 0.84rem; color: var(--text-secondary); margin: 0;">
            Aapne aaj ki video watch kar ke <strong>+$<?php echo number_format($dailyProfitAmt, 2); ?> USD</strong> profit account me credit kar liya hai. Agla task kal unlock hoga.
          </p>
        </div>
      </div>
      <div>
        <button class="btn btn-secondary" disabled style="font-size: 0.88rem; padding: 10px 20px; opacity: 0.85; cursor: not-allowed; display: inline-flex; align-items: center; gap: 6px; border-color: var(--accent-lime); color: var(--accent-lime); pointer-events: none;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
          Today's Profit Claimed (+$<?php echo number_format($dailyProfitAmt, 2); ?>)
        </button>
      </div>
    </div>

  <?php elseif (!$hasValidActiveVideo && $userHasWatchedToday && (($currentUser['package_status'] ?? '') === 'active')): ?>
    <!-- SCENARIO 2: ADMIN DELETED VIDEO, BUT THIS USER ALREADY WATCHED IT TODAY -->
    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; width: 100%;">
      <div style="display: flex; align-items: center; gap: 14px; flex: 1; min-width: 280px;">
        <div style="width: 52px; height: 52px; border-radius: 14px; background: rgba(147, 215, 38, 0.2); border: 2px solid var(--accent-lime); display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: var(--accent-lime);">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        </div>
        <div>
          <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 2px;">
            <span style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; padding: 2px 8px; border-radius: 12px; background: var(--accent-lime-soft); color: var(--accent-lime); border: 1px solid var(--accent-lime);">
              Watched Record Verified
            </span>
          </div>
          <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin-bottom: 4px;">
            Aapne Aaj Ki Video Mukammal Dekh Li Thi!
          </h3>
          <p style="font-size: 0.84rem; color: var(--text-secondary); margin: 0;">
            Admin ne video delete/update kar di hai, lekin aapki attendance pehle hi record ho chuki hai. Aap apna aaj ka <strong>+$<?php echo number_format($dailyProfitAmt, 2); ?> USD</strong> profit claim kar sakte hain.
          </p>
        </div>
      </div>
      <div>
        <form method="POST" action="videos.php" id="claimDeletedForm">
          <input type="hidden" name="action" value="claim_daily_profit">
          <input type="hidden" name="video_id" value="<?php echo (int)($userWatchedTodayRow['video_id'] ?? 0); ?>">
          <input type="hidden" name="watch_token" value="<?php echo htmlspecialchars($_SESSION['video_watch_token'] ?? 'watched_today'); ?>">
          <input type="hidden" name="video_completed" value="1">
          <button type="submit" id="btnClaimDeleted" class="btn btn-pwa" style="font-size: 0.92rem; padding: 11px 22px; font-weight: 800; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 0 20px rgba(147, 215, 38, 0.5);">
            💰 Claim Today's Profit (+$<?php echo number_format($dailyProfitAmt, 2); ?> USD)
          </button>
        </form>
      </div>
    </div>

  <?php elseif (!$hasValidActiveVideo): ?>
    <!-- SCENARIO 3: ADMIN HAS NOT ADDED ANY ACTIVE VIDEO OR TODAY'S VIDEO HAS EXPIRED -->
    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; width: 100%;">
      <div style="display: flex; align-items: center; gap: 14px; flex: 1; min-width: 280px;">
        <div style="width: 52px; height: 52px; border-radius: 14px; background: rgba(239, 68, 68, 0.15); border: 2px solid rgba(239, 68, 68, 0.4); display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: #ef4444;">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
        </div>
        <div>
          <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 2px;">
            <span style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; padding: 2px 8px; border-radius: 12px; background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3);">
              Task Expired / Not Available
            </span>
          </div>
          <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin-bottom: 4px;">
            Aaj Ka Video Task Expire Ho Chuka Hai Ya Available Nahi Hai
          </h3>
          <p style="font-size: 0.84rem; color: var(--text-secondary); margin: 0;">
            Daily video task raat 12:00 baje auto expire ho jata hai. Naya video task admin ke publish karte hi yahan available ho jayega.
          </p>
        </div>
      </div>
      <div>
        <button type="button" disabled class="btn btn-secondary" style="font-size: 0.88rem; padding: 11px 22px; opacity: 0.5; cursor: not-allowed; display: inline-flex; align-items: center; gap: 8px; pointer-events: none;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
          🔒 Task Expired (Raat 12 AM)
        </button>
      </div>
    </div>

  <?php elseif (($currentUser['package_status'] ?? '') !== 'active'): ?>
    <!-- SCENARIO 4: USER PACKAGE IS INACTIVE -->
    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; width: 100%;">
      <div style="display: flex; align-items: center; gap: 14px; flex: 1; min-width: 280px;">
        <div style="width: 52px; height: 52px; border-radius: 14px; background: var(--bg-subtle); border: 2px solid var(--border-glass); display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: var(--text-muted);">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
        </div>
        <div>
          <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 2px;">
            <span style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; padding: 2px 8px; border-radius: 12px; background: var(--bg-subtle); color: var(--text-muted); border: 1px solid var(--border-glass);">
              Package Inactive
            </span>
          </div>
          <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin-bottom: 4px;">
            Daily Video Profit Claim Karne Ke Liye Package Active Karein
          </h3>
          <p style="font-size: 0.84rem; color: var(--text-secondary); margin: 0;">
            Sirf active package waale users video watch kar ke daily profit claim kar sakte hain. Pehle deposit kar ke package active karein.
          </p>
        </div>
      </div>
      <div>
        <a href="deposit.php" class="btn btn-pwa" style="font-size: 0.88rem; padding: 11px 20px;">
          Deposit &amp; Activate Plan &rarr;
        </a>
      </div>
    </div>

  <?php else: ?>
    <!-- SCENARIO 5: ACTIVE PACKAGE - VIDEO MUST BE COMPLETELY WATCHED TO UNLOCK -->
    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; width: 100%;">
      
      <div style="display: flex; align-items: center; gap: 14px; flex: 1; min-width: 280px;">
        <div id="taskStatusIconBox" style="width: 52px; height: 52px; border-radius: 14px; background: rgba(245, 158, 11, 0.2); border: 2px solid #f59e0b; display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: #f59e0b; transition: all 0.3s ease;">
          <svg id="taskStatusIcon" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
        </div>

        <div style="flex: 1;">
          <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 2px;">
            <span style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; padding: 2px 8px; border-radius: 12px; background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid #f59e0b;">
              Daily Video Task • <?php echo date('M d, Y'); ?>
            </span>
            <?php if (!empty($activeVideo['expires_at'])): ?>
              <span style="font-size: 0.72rem; font-weight: 700; background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); padding: 1px 8px; border-radius: 12px; display: inline-flex; align-items: center; gap: 4px;">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                Expires: Raat 12:00 AM (<?php echo date('h:i A', strtotime($activeVideo['expires_at'])); ?>)
              </span>
            <?php endif; ?>
          </div>

          <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin-bottom: 4px;">
            Daily Video Task (+$<?php echo number_format($dailyProfitAmt, 2); ?> USD Profit)
          </h3>

          <p style="font-size: 0.84rem; color: var(--text-secondary); margin: 0 0 6px 0;">
            Neeche di gayi video play karein. Video ko <strong>aage / skip nahi kiya ja sakta</strong>. Poori video complete hone par hi Claim button enable hoga.
          </p>

          <!-- Live Watch Progress Bar & Lock Status -->
          <div style="margin-top: 6px;">
            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.76rem; font-weight: 700; color: var(--text-secondary); margin-bottom: 4px;">
              <span id="taskWatchStatusText" style="color: #f59e0b; display: flex; align-items: center; gap: 4px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                <span>Video Lock Active (Video play karein taake progress start ho)</span>
              </span>
              <span id="taskPercentText" style="color: var(--text-main); font-weight: 800;">0%</span>
            </div>
            <div style="width: 100%; height: 8px; background: rgba(255,255,255,0.08); border-radius: 6px; overflow: hidden;">
              <div id="taskProgressBar" style="width: 0%; height: 100%; background: linear-gradient(90deg, #f59e0b, var(--accent-lime)); border-radius: 6px; transition: width 0.3s ease;"></div>
            </div>
          </div>

        </div>
      </div>

      <div>
        <form method="POST" action="videos.php<?php echo $selectedId ? '?v='.$selectedId : ''; ?>" id="claimProfitForm">
          <input type="hidden" name="action" value="claim_daily_profit">
          <input type="hidden" name="video_id" value="<?php echo (int)($activeVideo['id'] ?? 0); ?>">
          <input type="hidden" name="watch_token" value="<?php echo htmlspecialchars($_SESSION['video_watch_token'] ?? ''); ?>">
          <input type="hidden" name="video_completed" id="videoCompletedInput" value="0">
          <button type="submit" id="btnClaimProfit" disabled class="btn btn-secondary" style="font-size: 0.92rem; padding: 11px 22px; font-weight: 800; display: inline-flex; align-items: center; gap: 8px; opacity: 0.5; cursor: not-allowed; transition: all 0.3s ease; pointer-events: none;">
            <svg id="btnClaimLockIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
            <span id="btnClaimText">🔒 Watch Video to Unlock Profit (0%)</span>
          </button>
        </form>
      </div>

    </div>
  <?php endif; ?>

</div>

<?php if (!$hasValidActiveVideo): ?>
  <!-- Empty State When Admin Has Not Added Videos -->
  <div class="card" style="text-align: center; padding: 60px 20px; color: var(--text-secondary);">
    <div style="width: 64px; height: 64px; border-radius: 50%; background: var(--bg-subtle); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 14px; color: var(--text-muted);">
      <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect></svg>
    </div>
    <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin-bottom: 6px;">No Active Videos Available</h3>
    <p style="font-size: 0.88rem; max-width: 440px; margin: 0 auto 16px auto;">
      Admin ne abhi tak koi active video task publish nahi kiya. Video upload hone ke baad hi yahan show hogi aur aap task complete kar saken ge.
    </p>
    <a href="index.php" class="btn btn-pwa" style="font-size: 0.85rem; padding: 8px 18px;">Return to Dashboard</a>
  </div>
<?php else: ?>

  <!-- Video Main Layout (Player + Playlist) -->
  <div style="display: grid; grid-template-columns: 2.2fr 1fr; gap: 24px; align-items: start;">
    
    <!-- Left Column: The Interactive Player & Video Details -->
    <div>
      <div class="card" style="padding: 0; overflow: hidden; background: #000; border: 1px solid var(--border-glass); border-radius: var(--radius-lg); box-shadow: var(--shadow-glass); position: relative;">
        
        <!-- 16:9 Responsive Video Frame -->
        <div style="position: relative; width: 100%; padding-top: 56.25%; background: #0B0E14; overflow: hidden;">
          
          <?php if ($parsedSource['type'] === 'html5'): ?>
            <!-- HTML5 Direct Player with Seeking/Fast-forward Protection -->
            <video 
              id="mainPlatformVideo" 
              controls 
              controlsList="nodownload noplaybackrate"
              disablePictureInPicture
              playsinline 
              preload="metadata"
              style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: contain; background: #000;"
              poster="<?php echo htmlspecialchars($activeVideo['thumbnail'] ?? ''); ?>">
              <source src="<?php echo htmlspecialchars($parsedSource['src']); ?>" type="video/mp4">
              Your browser does not support the video tag.
            </video>

          <?php elseif ($parsedSource['type'] === 'youtube'): ?>
            <!-- Official YouTube Player Target for IFrame API -->
            <div id="youtubePlayerTarget" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;"></div>

          <?php elseif ($parsedSource['type'] === 'vimeo'): ?>
            <!-- Vimeo Player with API Hook -->
            <iframe 
              id="vimeoPlayerIframe"
              src="<?php echo htmlspecialchars($parsedSource['embed_url']); ?>" 
              title="<?php echo htmlspecialchars($activeVideo['title']); ?>"
              style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0;" 
              allow="autoplay; fullscreen; picture-in-picture" 
              allowfullscreen>
            </iframe>

          <?php elseif ($parsedSource['type'] === 'embed' || $parsedSource['type'] === 'raw_embed'): ?>
            <!-- In-Platform Secure Embedded Player -->
            <iframe 
              id="mainPlatformIframe"
              src="<?php echo htmlspecialchars($parsedSource['embed_url'] ?? ''); ?>" 
              title="<?php echo htmlspecialchars($activeVideo['title']); ?>"
              style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0;" 
              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
              allowfullscreen>
            </iframe>
          <?php endif; ?>

        </div>

      </div>

      <!-- Video Meta Details Box -->
      <div class="card" style="margin-top: 16px;">
        <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 12px;">
          <div>
            <div style="display: inline-flex; align-items: center; gap: 6px; background: rgba(147, 215, 38, 0.12); color: var(--accent-lime); border: 1px solid var(--accent-lime); padding: 2px 8px; border-radius: 12px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; margin-bottom: 8px;">
              <span style="width: 6px; height: 6px; border-radius: 50%; background: var(--accent-lime);"></span>
              Official Daily Video Task
            </div>
            <h2 style="font-size: 1.35rem; font-weight: 800; color: var(--text-main); margin-bottom: 4px;">
              <?php echo htmlspecialchars($activeVideo['title']); ?>
            </h2>
            <div style="font-size: 0.78rem; color: var(--text-muted);">
              Published on <?php echo date('M d, Y', strtotime($activeVideo['created_at'])); ?> &bull; In-Platform Playback
            </div>
          </div>

          <!-- Quick Action Buttons -->
          <div style="display: flex; gap: 8px;">
            <button type="button" class="btn btn-secondary" onclick="shareVideoLink()" style="font-size: 0.8rem; padding: 6px 12px; display: inline-flex; align-items: center; gap: 6px;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg>
              Share
            </button>
            <?php if (($currentUser['package_status'] ?? '') === 'active'): ?>
              <a href="index.php" class="btn btn-pwa" style="font-size: 0.8rem; padding: 6px 12px;">
                Dashboard &rarr;
              </a>
            <?php else: ?>
              <a href="deposit.php" class="btn btn-pwa" style="font-size: 0.8rem; padding: 6px 12px;">
                Activate Package &rarr;
              </a>
            <?php endif; ?>
          </div>
        </div>

        <?php if (!empty($activeVideo['description'])): ?>
          <div style="font-size: 0.88rem; color: var(--text-secondary); line-height: 1.6; padding-top: 12px; border-top: 1px solid var(--border-glass);">
            <?php echo nl2br(htmlspecialchars($activeVideo['description'])); ?>
          </div>
        <?php endif; ?>
      </div>

    </div>

    <!-- Right Column: Video Playlist / Collection -->
    <div>
      <div class="card" style="padding: 0; overflow: hidden; margin-bottom: 0;">
        <div style="padding: 14px 18px; border-bottom: 1px solid var(--border-glass); background: var(--bg-subtle); display: flex; justify-content: space-between; align-items: center;">
          <h3 style="font-size: 0.95rem; font-weight: 800; color: var(--text-main); margin-bottom: 0;">
            Platform Playlist
          </h3>
          <span style="font-size: 0.72rem; color: var(--text-muted);">
            <?php echo count($videos); ?> Videos
          </span>
        </div>

        <div style="display: flex; flex-direction: column; max-height: 520px; overflow-y: auto;">
          <?php foreach ($videos as $idx => $v): 
            $isSelected = ($v['id'] === $activeVideo['id']);
            $vInfo = parseVideoSource($v['video_url']);
          ?>
            <a href="videos.php?v=<?php echo $v['id']; ?>" style="display: flex; gap: 12px; padding: 14px 16px; border-bottom: 1px solid var(--border-glass); text-decoration: none; transition: background 0.2s ease; background: <?php echo $isSelected ? 'rgba(147, 215, 38, 0.08)' : 'transparent'; ?>; border-left: <?php echo $isSelected ? '3px solid var(--accent-lime)' : '3px solid transparent'; ?>;">
              
              <!-- Video Number / Play Icon -->
              <div style="width: 32px; height: 32px; border-radius: 8px; background: <?php echo $isSelected ? 'var(--accent-lime)' : 'var(--bg-subtle)'; ?>; color: <?php echo $isSelected ? '#000' : 'var(--text-secondary)'; ?>; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 0.78rem; font-weight: 800;">
                <?php if ($isSelected): ?>
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
                <?php else: ?>
                  <?php echo $idx + 1; ?>
                <?php endif; ?>
              </div>

              <!-- Title & Meta -->
              <div style="flex: 1; min-width: 0;">
                <div style="font-size: 0.85rem; font-weight: 700; color: <?php echo $isSelected ? 'var(--accent-lime)' : 'var(--text-main)'; ?>; margin-bottom: 2px; line-height: 1.3; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">
                  <?php echo htmlspecialchars($v['title']); ?>
                </div>
                <div style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase;">
                  <?php echo htmlspecialchars($vInfo['type'] ?? 'Video'); ?> &bull; Daily Task
                </div>
              </div>

            </a>
          <?php endforeach; ?>
        </div>

      </div>

      <!-- Quick Platform Info Note -->
      <div class="card" style="margin-top: 16px; font-size: 0.82rem; color: var(--text-secondary); line-height: 1.5;">
        <strong style="color: var(--text-main); display: flex; align-items: center; gap: 6px; margin-bottom: 6px;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--accent-lime)" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
          Strict Daily Reward Policy
        </strong>
        Har din ka reward sirf usi din claim kiya ja sakta hai. Raat 12 bajne ke baad pichle din ka task expire ho jata hai aur unclaimed profit lapse ho jata hai.
      </div>
    </div>

  </div>

<?php endif; ?>

<script>
  function shareVideoLink() {
    if (navigator.share) {
      navigator.share({
        title: <?php echo json_encode($activeVideo['title'] ?? 'EarnNow Video'); ?>,
        url: window.location.href
      }).catch(() => {});
    } else {
      navigator.clipboard.writeText(window.location.href).then(() => {
        alert('Video link copied to clipboard!');
      });
    }
  }

  // Toast alert notification for fast-forward prevention
  let toastTimer = null;
  function showVideoToast(msg) {
    const container = document.getElementById('videoToastContainer');
    const toastMsg = document.getElementById('videoToastMessage');
    if (!container || !toastMsg) return;

    toastMsg.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg> <span>' + msg + '</span>';
    container.style.display = 'block';

    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
      container.style.display = 'none';
    }, 3500);
  }

  // Prevent multiple submits on claim forms
  const mainClaimForm = document.getElementById('claimProfitForm');
  if (mainClaimForm) {
    mainClaimForm.addEventListener('submit', function() {
      const btn = document.getElementById('btnClaimProfit');
      const txt = document.getElementById('btnClaimText');
      if (btn) {
        btn.disabled = true;
        btn.style.opacity = '0.6';
        btn.style.pointerEvents = 'none';
        btn.style.cursor = 'not-allowed';
      }
      if (txt) {
        txt.innerHTML = '⏳ Processing Claim...';
      }
    });
  }

  const deletedClaimForm = document.getElementById('claimDeletedForm');
  if (deletedClaimForm) {
    deletedClaimForm.addEventListener('submit', function() {
      const btn = document.getElementById('btnClaimDeleted');
      if (btn) {
        btn.disabled = true;
        btn.style.opacity = '0.6';
        btn.style.pointerEvents = 'none';
        btn.style.cursor = 'not-allowed';
        btn.innerHTML = '⏳ Processing Claim...';
      }
    });
  }

  // ----------------------------------------------------
  // Video Watch Task Enforcement & Strict Anti-Skip Engine
  // ----------------------------------------------------
  (function() {
    const hasValidVideo = <?php echo json_encode($hasValidActiveVideo); ?>;
    const isTodayClaimed = <?php echo json_encode($todayClaimed); ?>;
    const isPkgActive = <?php echo json_encode(($currentUser['package_status'] ?? '') === 'active'); ?>;
    const dailyProfitStr = <?php echo json_encode(number_format($dailyProfitAmt, 2)); ?>;
    const videoSourceType = <?php echo json_encode($parsedSource['type'] ?? 'none'); ?>;
    const youtubeVideoId = <?php echo json_encode($parsedSource['video_id'] ?? ''); ?>;
    const activeVideoId = <?php echo json_encode((int)($activeVideo['id'] ?? 0)); ?>;
    const activeVideoTitle = <?php echo json_encode($activeVideo['title'] ?? ''); ?>;
    const watchToken = <?php echo json_encode($_SESSION['video_watch_token'] ?? ''); ?>;

    // Strict exit: If no video from admin, already claimed, or pkg inactive, do NOT run tracker!
    if (!hasValidVideo || isTodayClaimed || !isPkgActive) return;

    const claimBtn = document.getElementById('btnClaimProfit');
    const claimText = document.getElementById('btnClaimText');
    const completedInput = document.getElementById('videoCompletedInput');
    const progressBar = document.getElementById('taskProgressBar');
    const statusText = document.getElementById('taskWatchStatusText');
    const percentText = document.getElementById('taskPercentText');
    const statusIconBox = document.getElementById('taskStatusIconBox');

    let highestWatchedSeconds = 0;
    let isUnlocked = false;

    function updateProgress(pct) {
      if (isUnlocked) return;
      const rounded = Math.min(99, Math.max(0, Math.floor(pct)));
      if (progressBar) progressBar.style.width = rounded + '%';
      if (percentText) percentText.textContent = rounded + '%';
      if (claimText) claimText.textContent = '🔒 Watching Video... (' + rounded + '%)';
    }

    function setStatus(stateText, color = '#f59e0b') {
      if (isUnlocked) return;
      if (statusText) {
        statusText.style.color = color;
        statusText.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg> <span>' + stateText + '</span>';
      }
    }

    function unlockProfitClaim() {
      if (isUnlocked) return;
      isUnlocked = true;

      if (completedInput) completedInput.value = '1';
      if (progressBar) {
        progressBar.style.width = '100%';
        progressBar.style.background = 'var(--accent-lime)';
      }
      if (percentText) percentText.textContent = '100%';
      if (statusIconBox) {
        statusIconBox.style.background = 'rgba(147, 215, 38, 0.2)';
        statusIconBox.style.borderColor = 'var(--accent-lime)';
        statusIconBox.style.color = 'var(--accent-lime)';
      }
      if (statusText) {
        statusText.innerHTML = '<span style="color: var(--accent-lime); font-weight:800; display:inline-flex; align-items:center; gap:5px;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg> 🎉 Mubarak ho! Poori video dekhi gayi. Ab apna daily profit claim karein!</span>';
      }

      if (claimBtn) {
        claimBtn.disabled = false;
        claimBtn.className = 'btn btn-pwa';
        claimBtn.style.opacity = '1';
        claimBtn.style.cursor = 'pointer';
        claimBtn.style.pointerEvents = 'auto';
        claimBtn.style.boxShadow = '0 0 22px rgba(147, 215, 38, 0.65)';
        if (claimText) {
          claimText.innerHTML = '💰 Claim Today\'s Profit (+$' + dailyProfitStr + ' USD)';
        }
      }

      // Record watch completion in database immediately so user retains eligibility even if admin deletes video later!
      if (activeVideoId > 0 && watchToken) {
        const formData = new URLSearchParams();
        formData.append('action', 'mark_video_watched');
        formData.append('video_id', activeVideoId);
        formData.append('video_title', activeVideoTitle);
        formData.append('watch_token', watchToken);

        fetch('videos.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: formData.toString()
        }).catch(() => {});
      }
    }

    // ==========================================
    // 1. OFFICIAL YOUTUBE IFRAME API CONTROLLER
    // ==========================================
    if (videoSourceType === 'youtube' && youtubeVideoId) {
      let ytPlayer = null;
      let ytPollInterval = null;
      let ytDuration = 0;

      function onPlayerPlaying() {
        setStatus('▶️ Video chal rahi hai... Poori dekhne par profit unlock hoga', '#10b981');
        if (ytPollInterval) clearInterval(ytPollInterval);
        
        ytPollInterval = setInterval(function() {
          if (isUnlocked || !ytPlayer || typeof ytPlayer.getCurrentTime !== 'function') return;

          // Double verify player is actually in PLAYING state (1)
          const state = ytPlayer.getPlayerState();
          if (state !== 1) { // 1 = YT.PlayerState.PLAYING
            clearInterval(ytPollInterval);
            ytPollInterval = null;
            return;
          }

          const cur = ytPlayer.getCurrentTime();
          const dur = ytDuration > 0 ? ytDuration : (ytPlayer.getDuration() || 0);

          // STRICT ANTI-FORWARD SEEKING CHECK
          if (cur > highestWatchedSeconds + 2.5) {
            ytPlayer.seekTo(highestWatchedSeconds, true);
            showVideoToast('⚠️ Video ko aage/skip nahi kiya ja sakta! Barah-e-karam poori video dekhein.');
            return;
          }

          // Advance continuous progress
          if (cur > highestWatchedSeconds) {
            highestWatchedSeconds = cur;
          }

          if (dur > 0) {
            const pct = (highestWatchedSeconds / dur) * 100;
            updateProgress(pct);

            // Reached near end (>= 98% of full duration)
            if (pct >= 98) {
              clearInterval(ytPollInterval);
              unlockProfitClaim();
            }
          }
        }, 500);
      }

      function onPlayerPaused() {
        if (ytPollInterval) {
          clearInterval(ytPollInterval);
          ytPollInterval = null;
        }
        setStatus('⏸️ Video pause hai - Video Play karein taake progress aage barhe', '#f59e0b');
        if (claimText && !isUnlocked) {
          claimText.textContent = '⏸️ Video Paused (' + (percentText ? percentText.textContent : '0%') + ')';
        }
      }

      function initYouTubePlayer() {
        if (typeof YT === 'undefined' || !YT.Player) return;
        ytPlayer = new YT.Player('youtubePlayerTarget', {
          videoId: youtubeVideoId,
          playerVars: {
            'autoplay': 0,
            'controls': 1,
            'rel': 0,
            'modestbranding': 1,
            'playsinline': 1,
            'origin': window.location.origin
          },
          events: {
            'onReady': function(event) {
              ytDuration = ytPlayer.getDuration() || 0;
              setStatus('Video ready hai. "Play" dabayein taake task shuru ho sake', '#f59e0b');
            },
            'onStateChange': function(event) {
              if (event.data === 1) { // PLAYING
                if (!ytDuration || ytDuration <= 0) {
                  ytDuration = ytPlayer.getDuration() || 0;
                }
                onPlayerPlaying();
              } else if (event.data === 2) { // PAUSED
                onPlayerPaused();
              } else if (event.data === 3) { // BUFFERING
                setStatus('⏳ Buffering...', '#f59e0b');
              } else if (event.data === 0) { // ENDED
                if (ytPollInterval) clearInterval(ytPollInterval);
                unlockProfitClaim();
              }
            }
          }
        });
      }

      // Load YouTube IFrame API script dynamically
      if (typeof YT === 'undefined' || !YT.Player) {
        const tag = document.createElement('script');
        tag.src = "https://www.youtube.com/iframe_api";
        const firstScriptTag = document.getElementsByTagName('script')[0];
        firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
        window.onYouTubeIframeAPIReady = function() {
          initYouTubePlayer();
        };
      } else {
        initYouTubePlayer();
      }

    // ==========================================
    // 2. HTML5 DIRECT VIDEO PLAYER CONTROLLER
    // ==========================================
    } else if (videoSourceType === 'html5') {
      const html5Video = document.getElementById('mainPlatformVideo');
      if (html5Video) {
        // Enforce 1.0x playback speed (prevent fast-forwarding via speed control)
        html5Video.playbackRate = 1.0;
        html5Video.addEventListener('ratechange', function() {
          if (html5Video.playbackRate > 1.0) {
            html5Video.playbackRate = 1.0;
            showVideoToast('⚠️ Fast playback speed disabled. Video normal speed par dekhein.');
          }
        });

        // Anti seeking ahead
        html5Video.addEventListener('seeking', function() {
          if (html5Video.currentTime > highestWatchedSeconds + 1.5) {
            html5Video.currentTime = highestWatchedSeconds;
            showVideoToast('⚠️ Video ko aage/skip nahi kiya ja sakta! Barah-e-karam poori video dekhein.');
          }
        });

        html5Video.addEventListener('play', function() {
          setStatus('▶️ Video chal rahi hai... Poori dekhne par profit unlock hoga', '#10b981');
        });

        html5Video.addEventListener('pause', function() {
          if (!html5Video.ended && !isUnlocked) {
            setStatus('⏸️ Video pause hai - Video Play karein taake progress aage barhe', '#f59e0b');
            if (claimText) {
              claimText.textContent = '⏸️ Video Paused (' + (percentText ? percentText.textContent : '0%') + ')';
            }
          }
        });

        html5Video.addEventListener('timeupdate', function() {
          if (html5Video.paused || html5Video.seeking || isUnlocked) return;

          const cur = html5Video.currentTime;
          const dur = html5Video.duration || 0;

          // Prevent jumps
          if (cur > highestWatchedSeconds) {
            if (cur - highestWatchedSeconds < 2) {
              highestWatchedSeconds = cur;
            } else {
              html5Video.currentTime = highestWatchedSeconds;
            }
          }

          if (dur > 0) {
            const pct = (highestWatchedSeconds / dur) * 100;
            updateProgress(pct);

            if (pct >= 98 || html5Video.ended) {
              unlockProfitClaim();
            }
          }
        });

        html5Video.addEventListener('ended', function() {
          unlockProfitClaim();
        });
      }

    // ==========================================
    // 3. VIMEO PLAYER CONTROLLER
    // ==========================================
    } else if (videoSourceType === 'vimeo') {
      const vimeoIframe = document.getElementById('vimeoPlayerIframe');
      if (vimeoIframe) {
        const vimeoScript = document.createElement('script');
        vimeoScript.src = "https://player.vimeo.com/api/player.js";
        vimeoScript.onload = function() {
          if (window.Vimeo && window.Vimeo.Player) {
            const vPlayer = new Vimeo.Player(vimeoIframe);
            vPlayer.on('play', function() {
              setStatus('▶️ Video chal rahi hai... Poori dekhne par profit unlock hoga', '#10b981');
            });
            vPlayer.on('pause', function() {
              if (!isUnlocked) {
                setStatus('⏸️ Video pause hai - Video Play karein', '#f59e0b');
              }
            });
            vPlayer.on('seeked', function(data) {
              if (data.seconds > highestWatchedSeconds + 2) {
                vPlayer.setCurrentTime(highestWatchedSeconds);
                showVideoToast('⚠️ Video ko aage/skip nahi kiya ja sakta.');
              }
            });
            vPlayer.on('timeupdate', function(data) {
              if (data.seconds > highestWatchedSeconds) {
                if (data.seconds - highestWatchedSeconds < 2.5) {
                  highestWatchedSeconds = data.seconds;
                } else {
                  vPlayer.setCurrentTime(highestWatchedSeconds);
                }
              }
              const dur = data.duration || 60;
              const pct = (highestWatchedSeconds / dur) * 100;
              updateProgress(pct);
              if (pct >= 98) {
                unlockProfitClaim();
              }
            });
            vPlayer.on('ended', function() {
              unlockProfitClaim();
            });
          }
        };
        document.head.appendChild(vimeoScript);
      }
    }

  })();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
