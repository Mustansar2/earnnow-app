<?php
// Prevent direct script access
if (!defined('EARNNOW_APP')) {
    define('EARNNOW_APP', true);
}

require_once __DIR__ . '/config.php';

// Ensure upload directory exists
$uploadDir = __DIR__ . '/../uploads/deposits';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

function getDB() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    // Hostinger / MySQL Connection
    if (defined('USE_MYSQL') && USE_MYSQL) {
        try {
            $port = defined('DB_PORT') ? DB_PORT : '3306';
            $charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';
            $dsn = "mysql:host=" . DB_HOST . ";port=" . $port . ";dbname=" . DB_NAME . ";charset=" . $charset;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            // MySQL Schema Auto-Migration
            try {
                $cols = $pdo->query("SHOW COLUMNS FROM `users`")->fetchAll(PDO::FETCH_COLUMN);
                if (!in_array('package_status', $cols)) {
                    $pdo->exec("ALTER TABLE `users` ADD COLUMN `package_status` VARCHAR(20) NOT NULL DEFAULT 'pending'");
                    $pdo->exec("UPDATE `users` SET `package_status` = 'active' WHERE `role` = 'admin' OR `balance` > 0 OR `plan_amount` > 0");
                }
                if (!in_array('package_approved_at', $cols)) {
                    $pdo->exec("ALTER TABLE `users` ADD COLUMN `package_approved_at` DATETIME NULL DEFAULT NULL");
                    $pdo->exec("UPDATE `users` SET `package_approved_at` = CURRENT_TIMESTAMP WHERE `package_status` = 'active'");
                }
                if (!in_array('initial_plan_amount', $cols)) {
                    $pdo->exec("ALTER TABLE `users` ADD COLUMN `initial_plan_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00");
                    $pdo->exec("UPDATE `users` SET `initial_plan_amount` = `plan_amount` WHERE `initial_plan_amount` = 0");
                }
                $planCols = $pdo->query("SHOW COLUMNS FROM `investment_plans`")->fetchAll(PDO::FETCH_COLUMN);
                if (!in_array('min_withdrawal', $planCols)) {
                    $pdo->exec("ALTER TABLE `investment_plans` ADD COLUMN `min_withdrawal` DECIMAL(10,2) NOT NULL DEFAULT 10.00");
                    $pdo->exec("UPDATE `investment_plans` SET `min_withdrawal` = `amount` * 0.10");
                }
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS `daily_video_watches` (
                      `id` INT AUTO_INCREMENT PRIMARY KEY,
                      `user_id` INT NOT NULL,
                      `video_id` INT DEFAULT NULL,
                      `claim_date` DATE NOT NULL,
                      `profit_amount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                      UNIQUE KEY `user_daily_claim` (`user_id`, `claim_date`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                ");
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS `user_daily_video_completions` (
                      `id` INT AUTO_INCREMENT PRIMARY KEY,
                      `user_id` INT NOT NULL,
                      `video_id` INT NOT NULL,
                      `video_title` VARCHAR(255) NULL,
                      `watch_date` DATE NOT NULL,
                      `completed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                      UNIQUE KEY `user_daily_watch_completion` (`user_id`, `watch_date`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                ");
                $vidCols = $pdo->query("SHOW COLUMNS FROM `videos`")->fetchAll(PDO::FETCH_COLUMN);
                if (!in_array('expires_at', $vidCols)) {
                    $pdo->exec("ALTER TABLE `videos` ADD COLUMN `expires_at` DATETIME NULL DEFAULT NULL");
                    $pdo->exec("UPDATE `videos` SET `expires_at` = '" . date('Y-m-d 23:59:59') . "' WHERE `expires_at` IS NULL");
                }
            } catch (Exception $ignored) {}

            return $pdo;
        } catch (PDOException $e) {
            die("Database Connection Error: " . htmlspecialchars($e->getMessage()));
        }
    }

    // Local Development Fallback (SQLite)
    $dbFile = __DIR__ . '/../earnnow.db';

    try {
        $pdo = new PDO('sqlite:' . $dbFile);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // Users Table
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    email TEXT NOT NULL UNIQUE,
                    phone TEXT NOT NULL,
                    password TEXT NOT NULL,
                    plan_id INTEGER NOT NULL DEFAULT 1,
                    plan_name TEXT NOT NULL,
                    plan_amount REAL NOT NULL,
                    monthly_profit REAL NOT NULL,
                    balance REAL NOT NULL DEFAULT 0.00,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // Transactions Table (Deposits and Withdrawals)
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS transactions (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    type TEXT NOT NULL, -- 'deposit' or 'withdraw'
                    category TEXT DEFAULT 'deposit', -- 'deposit', 'profit', 'capital'
                    amount REAL NOT NULL,
                    method TEXT NOT NULL, -- 'Easypaisa', 'JazzCash', 'USDT TRC20'
                    account_or_wallet TEXT,
                    trx_id TEXT,
                    screenshot TEXT,
                    status TEXT DEFAULT 'Pending', -- 'Pending', 'Approved', 'Rejected'
                    note TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id)
                )
            ");

            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_transactions_trx_id ON transactions (trx_id)");

            // Payment Gateways Table (Dynamic Admin Payment Configuration)
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS payment_gateways (
                    id TEXT PRIMARY KEY,
                    name TEXT NOT NULL,
                    is_enabled INTEGER DEFAULT 1,
                    bank_name TEXT DEFAULT NULL,
                    account_title TEXT DEFAULT NULL,
                    account_number TEXT DEFAULT NULL,
                    iban TEXT DEFAULT NULL,
                    wallet_address TEXT DEFAULT NULL,
                    network TEXT DEFAULT NULL,
                    instructions TEXT DEFAULT NULL,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // Migration: Add next_plan_id, upgrade_effective_date, role, package_status, package_approved_at, initial_plan_amount if not exists
            $columns = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('next_plan_id', $columns)) {
                $pdo->exec("ALTER TABLE users ADD COLUMN next_plan_id INTEGER DEFAULT NULL");
            }
            if (!in_array('upgrade_effective_date', $columns)) {
                $pdo->exec("ALTER TABLE users ADD COLUMN upgrade_effective_date TEXT DEFAULT NULL");
            }
            if (!in_array('role', $columns)) {
                $pdo->exec("ALTER TABLE users ADD COLUMN role TEXT DEFAULT 'user'");
            }
            if (!in_array('package_status', $columns)) {
                $pdo->exec("ALTER TABLE users ADD COLUMN package_status TEXT DEFAULT 'pending'");
                // Set existing demo/admin users with existing plans to 'active'
                $pdo->exec("UPDATE users SET package_status = 'active' WHERE role = 'admin' OR balance > 0 OR plan_amount > 0");
            }
            if (!in_array('package_approved_at', $columns)) {
                $pdo->exec("ALTER TABLE users ADD COLUMN package_approved_at DATETIME DEFAULT NULL");
                $pdo->exec("UPDATE users SET package_approved_at = CURRENT_TIMESTAMP WHERE package_status = 'active'");
            }
            if (!in_array('initial_plan_amount', $columns)) {
                $pdo->exec("ALTER TABLE users ADD COLUMN initial_plan_amount REAL DEFAULT 0.00");
                $pdo->exec("UPDATE users SET initial_plan_amount = plan_amount WHERE initial_plan_amount = 0");
            }

            // Sync user monthly_profit to match 1% daily (30% monthly) for active packages
            $pdo->exec("UPDATE users SET monthly_profit = plan_amount * 0.30 WHERE package_status = 'active'");

            // Seed demo user if table is empty
            $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            if ($count == 0) {
                $demoPlan = getPlanById(2); // 200 USD plan
                $demoPass = password_hash('password123', PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (name, email, phone, password, role, plan_id, plan_name, plan_amount, monthly_profit, balance)
                    VALUES (?, ?, ?, ?, 'user', ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    'Alex Morgan',
                    'alex.morgan@example.com',
                    '+1 555-234-5678',
                    $demoPass,
                    $demoPlan['id'],
                    $demoPlan['name'],
                    $demoPlan['amount'],
                    $demoPlan['monthly_profit'],
                    2845.50
                ]);
            }

            // Ensure Admin account exists (Credentials: mustansar.ba24@gmail.com / Test@12345)
            $mustansarCheck = $pdo->query("SELECT id FROM users WHERE email = 'mustansar.ba24@gmail.com' LIMIT 1")->fetch();
            if (!$mustansarCheck) {
                $mPass = password_hash('Test@12345', PASSWORD_DEFAULT);
                $mPlan = getPlanById(3);
                $stmt = $pdo->prepare("
                    INSERT INTO users (name, email, phone, password, role, plan_id, plan_name, plan_amount, monthly_profit, balance)
                    VALUES (?, ?, ?, ?, 'admin', ?, ?, ?, ?, 10000.00)
                ");
                $stmt->execute([
                    'Mustansar Admin',
                    'mustansar.ba24@gmail.com',
                    '+92 300 0000000',
                    $mPass,
                    $mPlan['id'],
                    $mPlan['name'],
                    $mPlan['amount'],
                    $mPlan['monthly_profit']
                ]);
            } else {
                $pdo->exec("UPDATE users SET role = 'admin' WHERE email = 'mustansar.ba24@gmail.com'");
            }

            // Seed default payment gateways if table is empty
            $gwCount = $pdo->query("SELECT COUNT(*) FROM payment_gateways")->fetchColumn();
            if ($gwCount == 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO payment_gateways (id, name, is_enabled, bank_name, account_title, account_number, iban, wallet_address, network, instructions)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $defaults = [
                    ['bank', 'Bank Transfer', 1, 'Meezan Bank Limited', 'EarnNow Technologies PVT LTD', '01020304050607', 'PK64MEZN0001020304050607', null, null, 'Transfer via Raast / Online Mobile Banking to the IBAN or Account Number above and submit transaction reference & screenshot proof below.'],
                    ['easypaisa', 'Easypaisa', 1, null, 'EarnNow Official Deposits', '0345-1234567', null, null, null, 'Send payment to this Easypaisa mobile account and upload screenshot proof below.'],
                    ['jazzcash', 'JazzCash', 1, null, 'EarnNow Finance', '0300-9876543', null, null, null, 'Send payment to this JazzCash mobile account and upload screenshot proof below.'],
                    ['trc20', 'USDT (TRC20)', 1, null, null, null, null, 'TXm1P2k7gK9Q5vR4zB8aC3dE6fH7jL9mNw', 'TRC-20 (Tron Network)', 'Transfer exact USDT via Tron TRC-20 network only. Upload transaction screenshot below.']
                ];
                foreach ($defaults as $gw) {
                    $stmt->execute($gw);
                }
            }

            // Videos Table (Platform In-App Video Player & Management)
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS videos (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    title TEXT NOT NULL,
                    description TEXT NULL,
                    video_url TEXT NOT NULL,
                    video_type TEXT DEFAULT 'auto',
                    thumbnail TEXT NULL,
                    is_active INTEGER DEFAULT 1,
                    expires_at DATETIME DEFAULT NULL,
                    sort_order INTEGER DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // Migration: Add expires_at to videos if not exists
            $vidColumns = $pdo->query("PRAGMA table_info(videos)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('expires_at', $vidColumns)) {
                $pdo->exec("ALTER TABLE videos ADD COLUMN expires_at DATETIME DEFAULT NULL");
                // Set existing active videos to expire tonight at 23:59:59
                $pdo->exec("UPDATE videos SET expires_at = '" . date('Y-m-d 23:59:59') . "' WHERE expires_at IS NULL");
            }

            // Daily Video Watches / Task Claims Table (For Crediting Daily Profit)
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS daily_video_watches (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    video_id INTEGER DEFAULT NULL,
                    claim_date TEXT NOT NULL,
                    profit_amount REAL NOT NULL DEFAULT 0.00,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(user_id, claim_date)
                )
            ");

            // Daily Video Watch Progress Completion Table (Stores legitimate video watch before deletion)
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS user_daily_video_completions (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    video_id INTEGER NOT NULL,
                    video_title TEXT NULL,
                    watch_date TEXT NOT NULL,
                    completed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(user_id, watch_date)
                )
            ");

            // Seed default videos if empty
            $vidCount = $pdo->query("SELECT COUNT(*) FROM videos")->fetchColumn();
            if ($vidCount == 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO videos (title, description, video_url, video_type, is_active, sort_order)
                    VALUES (?, ?, ?, ?, 1, ?)
                ");
                $defaultVideos = [
                    [
                        'EarnNow Platform Overview & 1% Daily Profit Guide',
                        'Learn how our automated 1% daily guaranteed profit model works, how plans compound, and how TRC-20 fast withdrawals are processed within 1 hour.',
                        'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4',
                        'auto',
                        1
                    ],
                    [
                        'How to Deposit Funds (EasyPaisa, JazzCash, Bank & USDT)',
                        'Full walkthrough demonstrating how to send funds via Pakistani banks, Raast, mobile wallets, or Tron TRC-20 and upload verification receipts.',
                        'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                        'auto',
                        2
                    ]
                ];
                foreach ($defaultVideos as $v) {
                    $stmt->execute($v);
                }
            }

            // Investment Plans Table (Dynamic Admin Package Control & Activation)
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS investment_plans (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    amount REAL NOT NULL,
                    currency TEXT DEFAULT 'USD',
                    daily_percentage REAL DEFAULT 1.0,
                    profit_percentage REAL DEFAULT 30.0,
                    monthly_profit REAL NOT NULL,
                    daily_profit REAL NOT NULL,
                    min_withdrawal REAL DEFAULT 10.00,
                    badge TEXT,
                    color TEXT,
                    description TEXT,
                    is_active INTEGER DEFAULT 1,
                    sort_order INTEGER DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // Migration: Add min_withdrawal to investment_plans if not exists
            $planCols = $pdo->query("PRAGMA table_info(investment_plans)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('min_withdrawal', $planCols)) {
                $pdo->exec("ALTER TABLE investment_plans ADD COLUMN min_withdrawal REAL DEFAULT 10.00");
                $pdo->exec("UPDATE investment_plans SET min_withdrawal = amount * 0.10");
            } else {
                // Ensure existing plans have min_withdrawal set properly (10% of amount: 100->10, 200->20, 300->30, 500->50)
                $pdo->exec("UPDATE investment_plans SET min_withdrawal = amount * 0.10 WHERE min_withdrawal IS NULL OR min_withdrawal <= 0");
            }

            // Seed default investment plans if empty
            $planCount = $pdo->query("SELECT COUNT(*) FROM investment_plans")->fetchColumn();
            if ($planCount == 0) {
                $planStmt = $pdo->prepare("
                    INSERT INTO investment_plans (id, name, amount, currency, daily_percentage, profit_percentage, monthly_profit, daily_profit, min_withdrawal, badge, color, description, is_active, sort_order)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
                ");
                global $INVESTMENT_PLANS;
                foreach ($INVESTMENT_PLANS as $pId => $p) {
                    $planStmt->execute([
                        $p['id'],
                        $p['name'],
                        $p['amount'],
                        $p['currency'] ?? 'USD',
                        $p['daily_percentage'] ?? 1.0,
                        $p['profit_percentage'] ?? 30.0,
                        $p['monthly_profit'],
                        $p['daily_profit'],
                        $p['min_withdrawal'] ?? ($p['amount'] * 0.10),
                        $p['badge'] ?? '',
                        $p['color'] ?? '#10b981',
                        $p['description'] ?? '',
                        $p['id']
                    ]);
                }
            }
        } catch (PDOException $e) {
            die("Database Error: " . htmlspecialchars($e->getMessage()));
        }
    return $pdo;
}

// Fetch user transactions
function getUserTransactions($userId, $limit = 20) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT * FROM transactions 
        WHERE user_id = ? 
        ORDER BY id DESC 
        LIMIT ?
    ");
    $stmt->bindValue(1, (int)$userId, PDO::PARAM_INT);
    $stmt->bindValue(2, (int)$limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

// Check and apply scheduled month-start plan upgrades
function checkAndApplyPendingUpgrade(&$user) {
    if (!$user || empty($user['next_plan_id']) || empty($user['upgrade_effective_date'])) {
        return false;
    }

    $currentDate = date('Y-m-d');
    if ($currentDate >= $user['upgrade_effective_date']) {
        $newPlan = getPlanById($user['next_plan_id']);
        if ($newPlan) {
            $db = getDB();
            $stmt = $db->prepare("
                UPDATE users 
                SET plan_id = ?, plan_name = ?, plan_amount = ?, monthly_profit = ?, next_plan_id = NULL, upgrade_effective_date = NULL 
                WHERE id = ?
            ");
            $stmt->execute([
                $newPlan['id'],
                $newPlan['name'],
                $newPlan['amount'],
                $newPlan['monthly_profit'],
                $user['id']
            ]);

            // Refresh user array
            $user['plan_id'] = $newPlan['id'];
            $user['plan_name'] = $newPlan['name'];
            $user['plan_amount'] = $newPlan['amount'];
            $user['monthly_profit'] = $newPlan['monthly_profit'];
            $user['next_plan_id'] = null;
            $user['upgrade_effective_date'] = null;
            $_SESSION['user'] = $user;
            return true;
        }
    }
    return false;
}

// Get all payment gateways (optionally only active/enabled ones)
function getPaymentGateways($activeOnly = false) {
    $db = getDB();
    $sql = "SELECT * FROM payment_gateways";
    if ($activeOnly) {
        $sql .= " WHERE is_enabled = 1";
    }
    $sql .= " ORDER BY id ASC";
    $rows = $db->query($sql)->fetchAll();
    
    $gateways = [];
    foreach ($rows as $row) {
        $gateways[$row['id']] = $row;
    }
    return $gateways;
}

// Update single payment gateway (Admin dynamic update)
function updatePaymentGateway($id, array $data) {
    $db = getDB();
    $stmt = $db->prepare("
        UPDATE payment_gateways SET 
            is_enabled = ?,
            name = ?,
            bank_name = ?,
            account_title = ?,
            account_number = ?,
            iban = ?,
            wallet_address = ?,
            network = ?,
            instructions = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    return $stmt->execute([
        isset($data['is_enabled']) ? (int)$data['is_enabled'] : 1,
        $data['name'] ?? '',
        !empty($data['bank_name']) ? $data['bank_name'] : null,
        !empty($data['account_title']) ? $data['account_title'] : null,
        !empty($data['account_number']) ? $data['account_number'] : null,
        !empty($data['iban']) ? $data['iban'] : null,
        !empty($data['wallet_address']) ? $data['wallet_address'] : null,
        !empty($data['network']) ? $data['network'] : null,
        $data['instructions'] ?? '',
        $id
    ]);
}

// Update transaction status (Supports all 5 statuses: Pending, Under Review, Approved, Rejected, Failed)
function updateTransactionStatus($txId, $newStatus, $adminNote = '') {
    $validStatuses = ['Pending', 'Under Review', 'Approved', 'Rejected', 'Failed'];
    if (!in_array($newStatus, $validStatuses)) {
        return ['success' => false, 'message' => 'Invalid transaction status provided.'];
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM transactions WHERE id = ?");
    $stmt->execute([$txId]);
    $tx = $stmt->fetch();

    if (!$tx) {
        return ['success' => false, 'message' => 'Transaction not found.'];
    }

    $oldStatus = $tx['status'];

    // If approving a deposit for the first time, credit user's balance
    if ($newStatus === 'Approved' && $oldStatus !== 'Approved' && $tx['type'] === 'deposit') {
        $userStmt = $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $userStmt->execute([$tx['amount'], $tx['user_id']]);
    }

    // If approving a capital withdrawal for the first time, deduct user's plan capital
    // When capital is completely withdrawn (plan_amount <= 0), the package expires, but account remains active!
    if ($newStatus === 'Approved' && $oldStatus !== 'Approved' && $tx['type'] === 'withdraw' && ($tx['category'] ?? '') === 'capital') {
        $userStmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $userStmt->execute([$tx['user_id']]);
        $userObj = $userStmt->fetch();

        if ($userObj) {
            $currentPlanAmt = (float)$userObj['plan_amount'];
            $withdrawn = (float)$tx['amount'];
            $remPlanAmt = max(0, $currentPlanAmt - $withdrawn);
            $remProfit = $remPlanAmt * 0.30;

            if ($remPlanAmt <= 0.001) {
                // Actual amount completely withdrawn: package expires, account remains 100% active
                $expireStmt = $db->prepare("
                    UPDATE users 
                    SET plan_amount = 0.00, monthly_profit = 0.00, package_status = 'expired' 
                    WHERE id = ?
                ");
                $expireStmt->execute([$tx['user_id']]);
                $adminNote .= " [Actual amount fully withdrawn. Package expired; user account remains active.]";
            } else {
                $deductStmt = $db->prepare("
                    UPDATE users 
                    SET plan_amount = ?, monthly_profit = ? 
                    WHERE id = ?
                ");
                $deductStmt->execute([$remPlanAmt, $remProfit, $tx['user_id']]);
                $adminNote .= " [Capital partially withdrawn: remaining plan amount is $" . number_format($remPlanAmt, 2) . "]";
            }
        }
    }

    // If an approved capital withdrawal is reverted to Rejected/Failed/Pending, restore plan capital
    if ($oldStatus === 'Approved' && $newStatus !== 'Approved' && $tx['type'] === 'withdraw' && ($tx['category'] ?? '') === 'capital') {
        $userStmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $userStmt->execute([$tx['user_id']]);
        $userObj = $userStmt->fetch();
        if ($userObj) {
            $restoredAmount = (float)$userObj['plan_amount'] + (float)$tx['amount'];
            $restoredProfit = $restoredAmount * 0.30;
            $restoreStmt = $db->prepare("
                UPDATE users 
                SET plan_amount = ?, monthly_profit = ?, package_status = 'active' 
                WHERE id = ?
            ");
            $restoreStmt->execute([$restoredAmount, $restoredProfit, $tx['user_id']]);
        }
    }

    $note = !empty($adminNote) ? $adminNote : $tx['note'];
    $updateStmt = $db->prepare("UPDATE transactions SET status = ?, note = ? WHERE id = ?");
    $updateStmt->execute([$newStatus, $note, $txId]);

    return ['success' => true, 'message' => "Transaction #{$txId} status updated to '{$newStatus}'."];
}

// Get all transactions for Admin view
function getAllTransactions($limit = 100, $statusFilter = '') {
    $db = getDB();
    $sql = "
        SELECT t.*, u.name as user_name, u.email as user_email, u.phone as user_phone 
        FROM transactions t
        JOIN users u ON t.user_id = u.id
    ";
    $params = [];
    if (!empty($statusFilter)) {
        $sql .= " WHERE t.status = ?";
        $params[] = $statusFilter;
    }
    $sql .= " ORDER BY t.id DESC LIMIT ?";
    $params[] = (int)$limit;

    $stmt = $db->prepare($sql);
    foreach ($params as $idx => $val) {
        $stmt->bindValue($idx + 1, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    return $stmt->fetchAll();
}

// ----------------------------------------------------
// User Package & Account Management Functions
// ----------------------------------------------------

function getPendingPackageCount() {
    $db = getDB();
    try {
        return (int)$db->query("SELECT COUNT(*) FROM users WHERE package_status = 'pending' AND role != 'admin'")->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

function getUserPackageCounts() {
    $db = getDB();
    try {
        return [
            'all' => (int)$db->query("SELECT COUNT(*) FROM users WHERE role != 'admin'")->fetchColumn(),
            'pending' => (int)$db->query("SELECT COUNT(*) FROM users WHERE package_status = 'pending' AND role != 'admin'")->fetchColumn(),
            'active' => (int)$db->query("SELECT COUNT(*) FROM users WHERE package_status = 'active' AND role != 'admin'")->fetchColumn(),
            'expired' => (int)$db->query("SELECT COUNT(*) FROM users WHERE package_status = 'expired' AND role != 'admin'")->fetchColumn(),
            'rejected' => (int)$db->query("SELECT COUNT(*) FROM users WHERE package_status = 'rejected' AND role != 'admin'")->fetchColumn(),
        ];
    } catch (Exception $e) {
        return ['all' => 0, 'pending' => 0, 'active' => 0, 'expired' => 0, 'rejected' => 0];
    }
}

function getAllUsersWithPackages($limit = 100, $statusFilter = '', $search = '') {
    $db = getDB();
    $sql = "SELECT * FROM users WHERE role != 'admin'";
    $params = [];

    if (!empty($statusFilter)) {
        $sql .= " AND package_status = ?";
        $params[] = $statusFilter;
    }

    if (!empty($search)) {
        $sql .= " AND (name LIKE ? OR email LIKE ? OR phone LIKE ? OR plan_name LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    $sql .= " ORDER BY id DESC LIMIT ?";
    $params[] = (int)$limit;

    $stmt = $db->prepare($sql);
    foreach ($params as $idx => $val) {
        $stmt->bindValue($idx + 1, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    return $stmt->fetchAll();
}

function updateUserPackageStatus($userId, $newStatus, $adminNote = '', $newPlanId = null) {
    $validStatuses = ['pending', 'active', 'expired', 'rejected'];
    if (!in_array($newStatus, $validStatuses)) {
        return ['success' => false, 'message' => 'Invalid package status provided.'];
    }

    $db = getDB();
    $userStmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch();
    if (!$user) {
        return ['success' => false, 'message' => 'User not found.'];
    }

    // Changing plan and status
    if ($newPlanId && (int)$newPlanId > 0) {
        $plan = getPlanById($newPlanId);
        if ($plan) {
            $stmt = $db->prepare("
                UPDATE users SET 
                    plan_id = ?, 
                    plan_name = ?, 
                    plan_amount = ?, 
                    initial_plan_amount = ?, 
                    monthly_profit = ?, 
                    package_status = ?, 
                    package_approved_at = CASE WHEN ? = 'active' THEN CURRENT_TIMESTAMP ELSE package_approved_at END 
                WHERE id = ?
            ");
            $stmt->execute([
                $plan['id'],
                $plan['name'],
                $plan['amount'],
                $plan['amount'],
                $plan['monthly_profit'],
                $newStatus,
                $newStatus,
                $userId
            ]);
            return ['success' => true, 'message' => "User package updated to '{$plan['name']}' with status '{$newStatus}'."];
        }
    }

    if ($newStatus === 'active') {
        $planAmount = (float)$user['plan_amount'];
        if ($planAmount <= 0) {
            $plan = getPlanById($user['plan_id'] ?? 1);
            $planAmount = $plan ? (float)$plan['amount'] : ((float)($user['initial_plan_amount'] ?? 100.00));
            $monthlyProfit = $planAmount * 0.30;
            $stmt = $db->prepare("
                UPDATE users SET 
                    package_status = 'active', 
                    package_approved_at = CURRENT_TIMESTAMP, 
                    plan_amount = ?, 
                    monthly_profit = ? 
                WHERE id = ?
            ");
            $stmt->execute([$planAmount, $monthlyProfit, $userId]);
        } else {
            $stmt = $db->prepare("
                UPDATE users SET 
                    package_status = 'active', 
                    package_approved_at = CURRENT_TIMESTAMP,
                    monthly_profit = plan_amount * 0.30 
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
        }
        return ['success' => true, 'message' => "Package for {$user['name']} has been approved and activated!"];
    } elseif ($newStatus === 'expired') {
        // Expire package: set plan_amount and monthly_profit to 0, but user account remains active
        $stmt = $db->prepare("
            UPDATE users SET 
                package_status = 'expired', 
                plan_amount = 0.00, 
                monthly_profit = 0.00 
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        return ['success' => true, 'message' => "Package for {$user['name']} marked as Expired. User account remains active."];
    } else {
        $stmt = $db->prepare("UPDATE users SET package_status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $userId]);
        return ['success' => true, 'message' => "User {$user['name']} package status updated to '{$newStatus}'."];
    }
}

function getVideos($activeOnly = true) {
    $db = getDB();
    $sql = "SELECT * FROM videos";
    $params = [];
    if ($activeOnly) {
        $now = date('Y-m-d H:i:s');
        $sql .= " WHERE is_active = 1 AND (expires_at IS NULL OR expires_at > ?)";
        $params[] = $now;
    }
    $sql .= " ORDER BY sort_order ASC, id DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $currentTime = time();
    foreach ($rows as &$v) {
        $v['is_expired'] = (!empty($v['expires_at']) && strtotime($v['expires_at']) <= $currentTime);
    }
    unset($v);

    return $rows;
}

function getVideoById($id) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM videos WHERE id = ?");
    $stmt->execute([(int)$id]);
    $video = $stmt->fetch();
    if ($video) {
        $video['is_expired'] = (!empty($video['expires_at']) && strtotime($video['expires_at']) <= time());
    }
    return $video;
}

function addVideo(array $data) {
    $db = getDB();

    // Auto midnight (12:00 AM) expiry default if not specified
    $expiresAt = !empty($data['expires_at']) ? trim($data['expires_at']) : null;
    if (empty($expiresAt) && empty($data['no_expiry'])) {
        $expiresAt = date('Y-m-d 23:59:59');
    }
    if ($expiresAt) {
        $expiresAt = str_replace('T', ' ', $expiresAt);
        if (strlen($expiresAt) === 16) {
            $expiresAt .= ':00';
        }
    }

    $stmt = $db->prepare("
        INSERT INTO videos (title, description, video_url, video_type, thumbnail, is_active, expires_at, sort_order, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
    ");
    return $stmt->execute([
        trim($data['title'] ?? ''),
        trim($data['description'] ?? ''),
        trim($data['video_url'] ?? ''),
        $data['video_type'] ?? 'auto',
        $data['thumbnail'] ?? null,
        isset($data['is_active']) ? (int)$data['is_active'] : 1,
        $expiresAt,
        isset($data['sort_order']) ? (int)$data['sort_order'] : 0
    ]);
}

function updateVideo($id, array $data) {
    $db = getDB();

    $expiresAt = !empty($data['expires_at']) ? trim($data['expires_at']) : null;
    if (!empty($data['no_expiry'])) {
        $expiresAt = null;
    } elseif ($expiresAt) {
        $expiresAt = str_replace('T', ' ', $expiresAt);
        if (strlen($expiresAt) === 16) {
            $expiresAt .= ':00';
        }
    }

    $stmt = $db->prepare("
        UPDATE videos SET 
            title = ?,
            description = ?,
            video_url = ?,
            video_type = ?,
            thumbnail = ?,
            is_active = ?,
            expires_at = ?,
            sort_order = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    return $stmt->execute([
        trim($data['title'] ?? ''),
        trim($data['description'] ?? ''),
        trim($data['video_url'] ?? ''),
        $data['video_type'] ?? 'auto',
        $data['thumbnail'] ?? null,
        isset($data['is_active']) ? (int)$data['is_active'] : 1,
        $expiresAt,
        isset($data['sort_order']) ? (int)$data['sort_order'] : 0,
        (int)$id
    ]);
}

function extendVideoExpiry($id, $newExpiry = null) {
    $db = getDB();
    if ($newExpiry === null) {
        $newExpiry = date('Y-m-d 23:59:59');
    }
    $stmt = $db->prepare("UPDATE videos SET is_active = 1, expires_at = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    return $stmt->execute([$newExpiry, (int)$id]);
}

function deleteVideo($id) {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM videos WHERE id = ?");
    return $stmt->execute([(int)$id]);
}

// Parse Video Source for seamless In-Platform Playback
function parseVideoSource($url) {
    $url = trim($url ?? '');
    if (empty($url)) {
        return null;
    }
    
    // 1. Raw iframe embed code
    if (strpos($url, '<iframe') !== false) {
        if (preg_match('/src=["\']([^"\']+)["\']/', $url, $matches)) {
            return [
                'type' => 'embed',
                'embed_url' => $matches[1],
                'raw' => $url
            ];
        }
        return ['type' => 'raw_embed', 'html' => $url];
    }

    // 2. YouTube URL
    if (preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/|v\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_\-]{11})/', $url, $matches)) {
        $videoId = $matches[1];
        return [
            'type' => 'youtube',
            'video_id' => $videoId,
            'embed_url' => "https://www.youtube-nocookie.com/embed/{$videoId}?enablejsapi=1&rel=0&modestbranding=1&controls=1",
            'thumbnail' => "https://img.youtube.com/vi/{$videoId}/hqdefault.jpg"
        ];
    }

    // 3. Vimeo URL
    if (preg_match('/vimeo\.com\/(?:video\/)?([0-9]+)/', $url, $matches)) {
        $videoId = $matches[1];
        return [
            'type' => 'vimeo',
            'video_id' => $videoId,
            'embed_url' => "https://player.vimeo.com/video/{$videoId}?badge=0&autopause=0&player_id=0",
            'thumbnail' => null
        ];
    }

    // 4. Direct HTML5 video file (MP4, WebM, OGG, or direct streaming URL)
    return [
        'type' => 'html5',
        'src' => $url,
        'thumbnail' => null
    ];
}

// Toggle investment plan active/inactive status
function toggleInvestmentPlanStatus($planId, $isActive) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE investment_plans SET is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    return $stmt->execute([(int)$isActive, (int)$planId]);
}

// Update investment plan details
function updateInvestmentPlan($planId, array $data) {
    $db = getDB();
    $amount = (float)($data['amount'] ?? 100);
    $dailyPct = (float)($data['daily_percentage'] ?? 1.0);
    $dailyProfit = round($amount * ($dailyPct / 100), 2);
    $monthlyProfit = round($dailyProfit * 30, 2);
    $minWithdrawal = isset($data['min_withdrawal']) && (float)$data['min_withdrawal'] > 0
        ? (float)$data['min_withdrawal']
        : round($amount * 0.10, 2);

    $stmt = $db->prepare("
        UPDATE investment_plans SET
            name = ?,
            amount = ?,
            daily_percentage = ?,
            profit_percentage = ?,
            daily_profit = ?,
            monthly_profit = ?,
            min_withdrawal = ?,
            badge = ?,
            color = ?,
            description = ?,
            is_active = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");

    return $stmt->execute([
        trim($data['name'] ?? 'Package'),
        $amount,
        $dailyPct,
        $dailyPct * 30,
        $dailyProfit,
        $monthlyProfit,
        $minWithdrawal,
        trim($data['badge'] ?? ''),
        trim($data['color'] ?? '#10b981'),
        trim($data['description'] ?? ''),
        isset($data['is_active']) ? (int)$data['is_active'] : 1,
        (int)$planId
    ]);
}

// Create new investment plan
function createInvestmentPlan(array $data) {
    $db = getDB();
    $amount = (float)($data['amount'] ?? 100);
    $dailyPct = (float)($data['daily_percentage'] ?? 1.0);
    $dailyProfit = round($amount * ($dailyPct / 100), 2);
    $monthlyProfit = round($dailyProfit * 30, 2);
    $minWithdrawal = isset($data['min_withdrawal']) && (float)$data['min_withdrawal'] > 0
        ? (float)$data['min_withdrawal']
        : round($amount * 0.10, 2);

    $stmt = $db->prepare("
        INSERT INTO investment_plans (name, amount, currency, daily_percentage, profit_percentage, daily_profit, monthly_profit, min_withdrawal, badge, color, description, is_active)
        VALUES (?, ?, 'USD', ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    return $stmt->execute([
        trim($data['name'] ?? 'New Package'),
        $amount,
        $dailyPct,
        $dailyPct * 30,
        $dailyProfit,
        $monthlyProfit,
        $minWithdrawal,
        trim($data['badge'] ?? 'Standard Tier'),
        trim($data['color'] ?? '#10b981'),
        trim($data['description'] ?? ''),
        isset($data['is_active']) ? (int)$data['is_active'] : 1
    ]);
}

// Fast-forward user package approved date for testing/simulating daily earnings and withdrawal unlocks
function fastForwardUserPackageDays($userId, $days = 10) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([(int)$userId]);
    $u = $stmt->fetch();
    if (!$u || $u['package_status'] !== 'active') {
        return ['success' => false, 'message' => 'User account is not active on an approved package.'];
    }

    $baseTime = !empty($u['package_approved_at']) ? strtotime($u['package_approved_at']) : time();
    $newTime = date('Y-m-d H:i:s', $baseTime - ($days * 86400));
    $upStmt = $db->prepare("UPDATE users SET package_approved_at = ? WHERE id = ?");
    $upStmt->execute([$newTime, (int)$userId]);
    return ['success' => true, 'message' => "Successfully fast-forwarded +{$days} days for {$u['name']}! Daily profit and withdrawal threshold unlocked."];
}

// ----------------------------------------------------
// Daily Video Watch & Profit Crediting Functions
// ----------------------------------------------------

function hasClaimedDailyVideoProfit($userId, $date = null) {
    if (!$userId) return false;
    $db = getDB();
    $date = $date ?? date('Y-m-d');
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM daily_video_watches WHERE user_id = ? AND claim_date = ?");
        $stmt->execute([(int)$userId, $date]);
        return ((int)$stmt->fetchColumn() > 0);
    } catch (Exception $e) {
        return false;
    }
}

function getTodayDailyVideoClaim($userId) {
    if (!$userId) return null;
    $db = getDB();
    $today = date('Y-m-d');
    try {
        $stmt = $db->prepare("SELECT * FROM daily_video_watches WHERE user_id = ? AND claim_date = ? LIMIT 1");
        $stmt->execute([(int)$userId, $today]);
        return $stmt->fetch();
    } catch (Exception $e) {
        return null;
    }
}

function getUserTotalClaimedProfit($userId) {
    if (!$userId) return 0.00;
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT SUM(profit_amount) FROM daily_video_watches WHERE user_id = ?");
        $stmt->execute([(int)$userId]);
        $val = $stmt->fetchColumn();
        return $val ? (float)$val : 0.00;
    } catch (Exception $e) {
        return 0.00;
    }
}

// Record that user has watched today's video (completed 100%)
function recordDailyVideoWatched($userId, $videoId, $videoTitle = '') {
    if (!$userId || !$videoId) return false;
    $db = getDB();
    $today = date('Y-m-d');
    try {
        $stmt = $db->prepare("
            INSERT INTO user_daily_video_completions (user_id, video_id, video_title, watch_date, completed_at)
            VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        return $stmt->execute([(int)$userId, (int)$videoId, $videoTitle, $today]);
    } catch (Exception $e) {
        // If already recorded today, update timestamp and video info
        try {
            $upStmt = $db->prepare("
                UPDATE user_daily_video_completions 
                SET video_id = ?, video_title = ?, completed_at = CURRENT_TIMESTAMP
                WHERE user_id = ? AND watch_date = ?
            ");
            return $upStmt->execute([(int)$videoId, $videoTitle, (int)$userId, $today]);
        } catch (Exception $e2) {
            return false;
        }
    }
}

// Check if user has watched today's video
function hasWatchedTodayVideo($userId) {
    if (!$userId) return null;
    $db = getDB();
    $today = date('Y-m-d');
    try {
        $stmt = $db->prepare("SELECT * FROM user_daily_video_completions WHERE user_id = ? AND watch_date = ? LIMIT 1");
        $stmt->execute([(int)$userId, $today]);
        return $stmt->fetch();
    } catch (Exception $e) {
        return null;
    }
}

// Calculate user's missed video tasks for the current month
function getUserMissedVideoDays($userId, $user = null) {
    if (!$userId) {
        return [
            'missed_days' => 0,
            'missed_profit' => 0.00,
            'eligible_days' => 0,
            'claimed_days' => 0,
            'daily_profit' => 1.00
        ];
    }

    $db = getDB();
    if (!$user) {
        $uStmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $uStmt->execute([(int)$userId]);
        $user = $uStmt->fetch();
    }

    if (!$user || ($user['package_status'] ?? '') !== 'active') {
        return [
            'missed_days' => 0,
            'missed_profit' => 0.00,
            'eligible_days' => 0,
            'claimed_days' => 0,
            'daily_profit' => 1.00
        ];
    }

    $planAmount = (float)($user['plan_amount'] ?? 100);
    $dailyProfit = round($planAmount * 0.01, 2);
    if ($dailyProfit <= 0) $dailyProfit = 1.00;

    // Start of the current month
    $monthStart = date('Y-m-01');
    $userActiveDate = !empty($user['package_approved_at']) 
        ? date('Y-m-d', strtotime($user['package_approved_at']))
        : date('Y-m-d', strtotime($user['created_at'] ?? 'now'));

    // Count missed days starting from either 1st of month or activation date (whichever is later)
    $startDate = ($userActiveDate > $monthStart) ? $userActiveDate : $monthStart;
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    if ($startDate > $yesterday) {
        // Activated today or no past days to miss yet in this month
        return [
            'missed_days' => 0,
            'missed_profit' => 0.00,
            'eligible_days' => 0,
            'claimed_days' => 0,
            'daily_profit' => $dailyProfit
        ];
    }

    // Get all claimed dates in this month up to yesterday
    $cStmt = $db->prepare("SELECT claim_date FROM daily_video_watches WHERE user_id = ? AND claim_date >= ? AND claim_date <= ?");
    $cStmt->execute([(int)$userId, $startDate, $yesterday]);
    $claimedDates = $cStmt->fetchAll(PDO::FETCH_COLUMN);
    $claimedMap = array_flip($claimedDates);

    $curr = strtotime($startDate);
    $end = strtotime($yesterday);
    $missedDays = 0;
    $eligibleDays = 0;

    while ($curr <= $end) {
        $eligibleDays++;
        $dStr = date('Y-m-d', $curr);
        if (!isset($claimedMap[$dStr])) {
            $missedDays++;
        }
        $curr = strtotime('+1 day', $curr);
    }

    $missedProfit = round($missedDays * $dailyProfit, 2);

    return [
        'missed_days' => $missedDays,
        'missed_profit' => $missedProfit,
        'eligible_days' => $eligibleDays,
        'claimed_days' => count($claimedDates),
        'start_date' => $startDate,
        'yesterday' => $yesterday,
        'daily_profit' => $dailyProfit
    ];
}

function claimDailyVideoProfit($userId, $videoId = null) {
    if (!$userId) {
        return ['success' => false, 'message' => 'User not logged in.'];
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([(int)$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['success' => false, 'message' => 'User account not found.'];
    }

    if (($user['package_status'] ?? 'pending') !== 'active') {
        return ['success' => false, 'message' => 'Aapka package active nahi hai. Daily video watching profit claim karne ke liye active package zaroori hai.'];
    }

    $today = date('Y-m-d');
    if (hasClaimedDailyVideoProfit($userId, $today)) {
        return ['success' => false, 'message' => 'Aapne aaj ki video watch kar ke aaj ka profit pehle hi claim kar liya hai! Agla profit kal milega.'];
    }

    // Video verification:
    // 1. Is there an active video in the database?
    // 2. OR did this user legitimately complete watching today's video earlier before admin deleted it?
    $watchedToday = hasWatchedTodayVideo($userId);
    $isValidVideo = false;

    if (!empty($videoId) && (int)$videoId > 0) {
        $vStmt = $db->prepare("SELECT * FROM videos WHERE id = ? AND is_active = 1");
        $vStmt->execute([(int)$videoId]);
        $video = $vStmt->fetch();
        if ($video && !empty(trim($video['video_url'] ?? ''))) {
            $isValidVideo = true;
        }
    }

    // If video was deleted or disabled by admin, but this user watched it today:
    if (!$isValidVideo) {
        if ($watchedToday && $watchedToday['watch_date'] === $today) {
            $isValidVideo = true;
            if ((empty($videoId) || (int)$videoId <= 0) && !empty($watchedToday['video_id'])) {
                $videoId = (int)$watchedToday['video_id'];
            }
        } else {
            return [
                'success' => false,
                'message' => 'Task claim karne ke liye active video ka hona zaroori hai. Admin ne abhi koi active video add nahi ki ya aapne video nahi dekhi.'
            ];
        }
    }

    // Calculate daily profit based on user's plan (1% daily profit per package amount: 100 -> 1, 200 -> 2, 300 -> 3, 500 -> 5)
    $plan = getPlanById($user['plan_id'] ?? 1);
    $planAmount = (float)($user['plan_amount'] ?? 0);
    if ($planAmount <= 0) {
        $planAmount = (float)($plan['amount'] ?? 100);
    }
    if (!$plan || (float)($plan['amount'] ?? 0) != $planAmount) {
        $allPlans = getInvestmentPlans(false);
        foreach ($allPlans as $p) {
            if ((float)$p['amount'] == $planAmount) {
                $plan = $p;
                break;
            }
        }
    }

    $dailyProfit = round($planAmount * 0.01, 2);
    if ($dailyProfit <= 0 && !empty($plan['daily_profit'])) {
        $dailyProfit = (float)$plan['daily_profit'];
    }
    if ($dailyProfit <= 0) {
        $dailyProfit = 1.00;
    }

    try {
        // Record claim in daily_video_watches
        $claimStmt = $db->prepare("
            INSERT INTO daily_video_watches (user_id, video_id, claim_date, profit_amount, created_at)
            VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        $claimStmt->execute([(int)$userId, $videoId ? (int)$videoId : null, $today, $dailyProfit]);

        // Credit user's wallet balance
        $upStmt = $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $upStmt->execute([$dailyProfit, (int)$userId]);

        // Record a transaction entry for audit and recent activity log
        $txStmt = $db->prepare("
            INSERT INTO transactions (user_id, type, category, amount, method, account_or_wallet, status, note, created_at)
            VALUES (?, 'deposit', 'profit', ?, 'Daily Video Task', 'System Auto-Credit', 'Approved', ?, CURRENT_TIMESTAMP)
        ");
        $txNote = "Daily Video Watch profit credited for " . date('M d, Y');
        $txStmt->execute([(int)$userId, $dailyProfit, $txNote]);

        // Refresh session balance if active
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user']['id']) && $_SESSION['user']['id'] == $userId) {
            $_SESSION['user']['balance'] = (float)($user['balance'] ?? 0) + $dailyProfit;
        }

        return [
            'success' => true,
            'amount' => $dailyProfit,
            'message' => 'Mubarak ho! Daily video watch karne par +$' . number_format($dailyProfit, 2) . ' USD profit aapke account me add ho gaya hai!'
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error claiming daily profit: ' . $e->getMessage()];
    }
}


