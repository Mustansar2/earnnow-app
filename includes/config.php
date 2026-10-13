<?php
// Prevent direct script access
if (!defined('EARNNOW_APP')) {
    define('EARNNOW_APP', true);
}

// Set default timezone for consistent midnight (12:00 AM) auto-expiry & withdrawal windows
date_default_timezone_set('Asia/Karachi');

// ========================================================
// DATABASE CONFIGURATION (Hostinger / cPanel / Localhost)
// ========================================================
// Live server par isko true karein jab Hostinger MySQL connect karna ho:
define('USE_MYSQL', false); // true for Hostinger MySQL, false for local SQLite
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'u123456789_earnnow'); // Hostinger DB Name
define('DB_USER', 'u123456789_earnnow'); // Hostinger DB Username
define('DB_PASS', 'your_db_password');   // Hostinger DB Password
define('DB_CHARSET', 'utf8mb4');

// Investment Plans Configuration (1% Daily Profit across all plans)
$INVESTMENT_PLANS = [
    1 => [
        'id' => 1,
        'name' => 'Starter Plan',
        'amount' => 100,
        'currency' => 'USD',
        'daily_percentage' => 1,
        'profit_percentage' => 30,
        'monthly_profit' => 30.00,
        'daily_profit' => 1.00,
        'min_withdrawal' => 10.00, // 100$ withdrawal After 10$
        'badge' => 'Starter Tier',
        'color' => '#10b981', // Emerald
        'description' => 'Guaranteed 1% daily return credited directly to wallet.'
    ],
    2 => [
        'id' => 2,
        'name' => 'Growth Plan',
        'amount' => 200,
        'currency' => 'USD',
        'daily_percentage' => 1,
        'profit_percentage' => 30,
        'monthly_profit' => 60.00,
        'daily_profit' => 2.00,
        'min_withdrawal' => 20.00, // 200$ Withdrawal After 20$
        'badge' => 'Popular Tier',
        'color' => '#0ea5e9', // Sky Blue
        'description' => 'Guaranteed 1% daily return with automated compounding.'
    ],
    4 => [
        'id' => 4,
        'name' => 'Pro Plan',
        'amount' => 300,
        'currency' => 'USD',
        'daily_percentage' => 1,
        'profit_percentage' => 30,
        'monthly_profit' => 90.00,
        'daily_profit' => 3.00,
        'min_withdrawal' => 30.00, // 300$ Withdrawal After 30$
        'badge' => 'Executive Tier',
        'color' => '#8b5cf6', // Violet / Purple
        'description' => 'Guaranteed 1% daily return with accelerated profit yields.'
    ],
    3 => [
        'id' => 3,
        'name' => 'VIP Pro Plan',
        'amount' => 500,
        'currency' => 'USD',
        'daily_percentage' => 1,
        'profit_percentage' => 30,
        'monthly_profit' => 150.00,
        'daily_profit' => 5.00,
        'min_withdrawal' => 50.00, // 500$ Withdrawal After 50$
        'badge' => 'VIP Pro Tier',
        'color' => '#f59e0b', // Amber / Gold
        'description' => 'Guaranteed 1% daily return with priority TRC-20 payouts.'
    ]
];

// Merchant Deposit Accounts
$DEPOSIT_METHODS = [
    'easypaisa' => [
        'id' => 'easypaisa',
        'name' => 'Easypaisa',
        'account_title' => 'EarnNow Official Deposits',
        'account_number' => '0345-1234567',
        'instructions' => 'Send payment to this Easypaisa mobile account and upload screenshot proof below.'
    ],
    'jazzcash' => [
        'id' => 'jazzcash',
        'name' => 'JazzCash',
        'account_title' => 'EarnNow Finance',
        'account_number' => '0300-9876543',
        'instructions' => 'Send payment to this JazzCash mobile account and upload screenshot proof below.'
    ],
    'bank' => [
        'id' => 'bank',
        'name' => 'Bank Transfer',
        'bank_name' => 'Meezan Bank Limited',
        'account_title' => 'EarnNow Technologies PVT LTD',
        'account_number' => '01020304050607',
        'iban' => 'PK64MEZN0001020304050607',
        'instructions' => 'Transfer via Raast / Online Mobile Banking to the IBAN or Account Number above and submit transaction reference & screenshot proof below.'
    ],
    'trc20' => [
        'id' => 'trc20',
        'name' => 'USDT (TRC20)',
        'network' => 'TRC-20 (Tron Network)',
        'wallet_address' => 'TXm1P2k7gK9Q5vR4zB8aC3dE6fH7jL9mNw',
        'instructions' => 'Transfer exact USDT via Tron TRC-20 network only. Upload transaction screenshot below.'
    ]
];

// Withdrawal Rules Configuration
$WITHDRAWAL_CONFIG = [
    'allowed_methods' => ['easypaisa', 'jazzcash', 'bank', 'trc20'],
    'start_hour' => 9,  // 09:00 AM
    'end_hour' => 21,   // 09:00 PM (21:00)
    'max_processing_time' => '1 hour',
    'capital_lock_days' => 30 // Month-end withdrawal rule for actual plan capital
];

$WITHDRAWAL_METHODS = [
    'easypaisa' => [
        'id' => 'easypaisa',
        'name' => 'Easypaisa',
        'badge' => 'Instant Mobile Account',
        'currency' => 'PKR / USD',
        'channel' => 'Easypaisa Wallet'
    ],
    'jazzcash' => [
        'id' => 'jazzcash',
        'name' => 'JazzCash',
        'badge' => 'Instant Mobile Account',
        'currency' => 'PKR / USD',
        'channel' => 'JazzCash Wallet'
    ],
    'bank' => [
        'id' => 'bank',
        'name' => 'Bank Transfer',
        'badge' => 'Direct Bank (Raast / IBFT)',
        'currency' => 'PKR / USD',
        'channel' => 'Pakistani Banks'
    ],
    'trc20' => [
        'id' => 'trc20',
        'name' => 'USDT (TRC20)',
        'badge' => 'Tether Tron Network',
        'currency' => 'USDT',
        'channel' => 'Crypto Wallet'
    ]
];

// Helper to get all investment plans (optionally active only)
function getInvestmentPlans($activeOnly = false) {
    if (function_exists('getDB')) {
        try {
            $db = getDB();
            $sql = "SELECT * FROM investment_plans";
            if ($activeOnly) {
                $sql .= " WHERE is_active = 1";
            }
            $sql .= " ORDER BY amount ASC, id ASC";
            $rows = $db->query($sql)->fetchAll();
            if (!empty($rows)) {
                $plans = [];
                foreach ($rows as $row) {
                    $plans[(int)$row['id']] = $row;
                }
                return $plans;
            }
        } catch (Exception $e) {
            // fallback
        }
    }
    global $INVESTMENT_PLANS;
    if ($activeOnly) {
        $active = [];
        foreach ($INVESTMENT_PLANS as $k => $p) {
            if (!isset($p['is_active']) || $p['is_active'] == 1) {
                $active[$k] = $p;
            }
        }
        return $active;
    }
    return $INVESTMENT_PLANS;
}

// Helper to get plan by ID
function getPlanById($planId) {
    if (function_exists('getDB')) {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM investment_plans WHERE id = ? LIMIT 1");
            $stmt->execute([(int)$planId]);
            $plan = $stmt->fetch();
            if ($plan) {
                return $plan;
            }
        } catch (Exception $e) {
            // fallback
        }
    }
    global $INVESTMENT_PLANS;
    return $INVESTMENT_PLANS[(int)$planId] ?? ($INVESTMENT_PLANS[1] ?? null);
}

// Check if current server/user time is within withdrawal hours (9 AM - 9 PM)
function isWithinWithdrawalHours() {
    global $WITHDRAWAL_CONFIG;
    // Set Pakistan Standard Time
    date_default_timezone_set('Asia/Karachi');

    // Admin bypass for testing anytime
    if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin') {
        return true;
    }

    $currentHour = (int)date('G');
    return ($currentHour >= $WITHDRAWAL_CONFIG['start_hour'] && $currentHour < $WITHDRAWAL_CONFIG['end_hour']);
}

// Check if user is eligible for capital/plan withdrawal (Month-end / 30 days)
function checkCapitalWithdrawalEligibility($userCreatedAt) {
    global $WITHDRAWAL_CONFIG;
    $createdTimestamp = strtotime($userCreatedAt);
    $daysPassed = floor((time() - $createdTimestamp) / (60 * 60 * 24));
    $daysRequired = $WITHDRAWAL_CONFIG['capital_lock_days'];
    
    return [
        'eligible' => ($daysPassed >= $daysRequired),
        'days_passed' => $daysPassed,
        'days_remaining' => max(0, $daysRequired - $daysPassed)
    ];
}

// Calculate user's earned profit, active days, and withdrawal threshold eligibility
// Rule: $100 plan -> $10 min profit withdrawal, $200 -> $20, $300 -> $30, $500 -> $50
function getUserProfitStats($user) {
    if (!$user) {
        return null;
    }

    $plan = getPlanById($user['plan_id'] ?? 1);
    $planAmount = (float)($user['plan_amount'] ?? 0);
    if ($planAmount <= 0) {
        $planAmount = (float)($plan['amount'] ?? 100);
    }

    // Match plan catalog item by plan amount if available
    if (!$plan || (float)($plan['amount'] ?? 0) != $planAmount) {
        $allPlans = getInvestmentPlans(false);
        foreach ($allPlans as $p) {
            if ((float)$p['amount'] == $planAmount) {
                $plan = $p;
                break;
            }
        }
    }

    // 1% daily profit per package tier: $100 -> $1, $200 -> $2, $300 -> $3, $500 -> $5
    $dailyProfit = round($planAmount * 0.01, 2);
    if ($dailyProfit <= 0 && !empty($plan['daily_profit'])) {
        $dailyProfit = (float)$plan['daily_profit'];
    }
    if ($dailyProfit <= 0) {
        $dailyProfit = 1.00;
    }

    $monthlyProfit = (float)($user['monthly_profit'] ?? 0);
    if ($monthlyProfit <= 0) {
        $monthlyProfit = !empty($plan['monthly_profit']) ? (float)$plan['monthly_profit'] : round($planAmount * 0.30, 2);
    }

    // Minimum profit withdrawal threshold (10% of package: 100 -> 10, 200 -> 20, 300 -> 30, 500 -> 50)
    if (isset($plan['min_withdrawal']) && (float)$plan['min_withdrawal'] > 0) {
        $minWithdrawal = (float)$plan['min_withdrawal'];
    } elseif ($planAmount > 0) {
        $minWithdrawal = round($planAmount * 0.10, 2);
    } else {
        $minWithdrawal = 10.00;
    }

    $packageStatus = $user['package_status'] ?? 'pending';
    $isActive = ($packageStatus === 'active');

    // Total claimed profit from daily video watching
    $userId = $user['id'] ?? 0;
    $totalClaimedProfit = ($userId && function_exists('getUserTotalClaimedProfit')) ? getUserTotalClaimedProfit($userId) : 0.00;
    $todayClaimed = ($userId && function_exists('hasClaimedDailyVideoProfit')) ? hasClaimedDailyVideoProfit($userId) : false;

    // Days active on package
    $daysActive = 0;
    if ($isActive && $planAmount > 0) {
        $approvedDate = !empty($user['package_approved_at']) ? $user['package_approved_at'] : ($user['created_at'] ?? date('Y-m-d H:i:s'));
        $approvedTime = strtotime($approvedDate);
        $secondsPassed = max(0, time() - $approvedTime);
        $daysActive = min(30, (int)floor($secondsPassed / 86400));
    }

    // User's wallet balance
    $userBalance = (float)($user['balance'] ?? 0.00);

    // Available profit to withdraw: uses wallet balance
    $availableProfit = $userBalance;
    $totalEarnedProfit = max($userBalance, $totalClaimedProfit);

    $canWithdraw = ($isActive && $availableProfit >= $minWithdrawal);
    $remainingToUnlock = max(0.00, round($minWithdrawal - $availableProfit, 2));
    $progressPercent = $minWithdrawal > 0 ? min(100, round(($availableProfit / $minWithdrawal) * 100)) : 100;

    // Monthly missed video tasks breakdown
    $missedStats = ($userId && function_exists('getUserMissedVideoDays')) 
        ? getUserMissedVideoDays($userId, $user) 
        : ['missed_days' => 0, 'missed_profit' => 0.00, 'eligible_days' => 0, 'claimed_days' => 0];

    return [
        'plan' => $plan,
        'plan_name' => $user['plan_name'] ?? ($plan['name'] ?? 'Starter Plan'),
        'plan_amount' => $planAmount,
        'daily_profit' => $dailyProfit,
        'monthly_profit' => $monthlyProfit,
        'min_withdrawal' => $minWithdrawal,
        'days_active' => $daysActive,
        'total_earned_profit' => $totalEarnedProfit,
        'total_claimed_profit' => $totalClaimedProfit,
        'today_claimed' => $todayClaimed,
        'available_profit' => $availableProfit,
        'can_withdraw' => $canWithdraw,
        'remaining_to_unlock' => $remainingToUnlock,
        'progress_percent' => $progressPercent,
        'missed_days' => $missedStats['missed_days'] ?? 0,
        'missed_profit' => $missedStats['missed_profit'] ?? 0.00,
        'missed_stats' => $missedStats,
        'is_active' => $isActive,
        'package_status' => $packageStatus
    ];
}
