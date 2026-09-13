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
    if ($pdo === null) {
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

            // Migration: Add next_plan_id and upgrade_effective_date if not exists
            $columns = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('next_plan_id', $columns)) {
                $pdo->exec("ALTER TABLE users ADD COLUMN next_plan_id INTEGER DEFAULT NULL");
            }
            if (!in_array('upgrade_effective_date', $columns)) {
                $pdo->exec("ALTER TABLE users ADD COLUMN upgrade_effective_date TEXT DEFAULT NULL");
            }

            // Sync user monthly_profit to match 1% daily (30% monthly)
            $pdo->exec("UPDATE users SET monthly_profit = plan_amount * 0.30");

            // Seed demo user if table is empty
            $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            if ($count == 0) {
                $demoPlan = getPlanById(2); // 200 USD plan
                $demoPass = password_hash('password123', PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (name, email, phone, password, plan_id, plan_name, plan_amount, monthly_profit, balance)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
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
        } catch (PDOException $e) {
            die("Database Error: " . htmlspecialchars($e->getMessage()));
        }
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
