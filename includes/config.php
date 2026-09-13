<?php
// Prevent direct script access
if (!defined('EARNNOW_APP')) {
    define('EARNNOW_APP', true);
}

// 3 Investment Plans Configuration (1% Daily Profit across all plans)
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
        'badge' => 'Popular Tier',
        'color' => '#0ea5e9', // Sky Blue
        'description' => 'Guaranteed 1% daily return with automated compounding.'
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
    'allowed_method' => 'USDT (TRC20)',
    'start_hour' => 9,  // 09:00 AM
    'end_hour' => 21,   // 09:00 PM (21:00)
    'max_processing_time' => '1 hour',
    'capital_lock_days' => 30 // Month-end withdrawal rule for actual plan capital
];

// Helper to get plan by ID
function getPlanById($planId) {
    global $INVESTMENT_PLANS;
    return $INVESTMENT_PLANS[(int)$planId] ?? $INVESTMENT_PLANS[1];
}

// Check if current server/user time is within withdrawal hours (9 AM - 9 PM)
function isWithinWithdrawalHours() {
    global $WITHDRAWAL_CONFIG;
    // Use server hour in 24-hr format
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
