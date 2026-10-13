-- EarnNow MySQL Database Schema
-- Compatible with MySQL 5.7+ / 8.0+ and MariaDB (Hostinger / cPanel)

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `phone` VARCHAR(30) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `plan_id` INT NOT NULL DEFAULT 1,
  `plan_name` VARCHAR(50) NOT NULL,
  `plan_amount` DECIMAL(10, 2) NOT NULL,
  `monthly_profit` DECIMAL(10, 2) NOT NULL,
  `balance` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `package_status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `package_approved_at` DATETIME NULL DEFAULT NULL,
  `initial_plan_amount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `next_plan_id` INT NULL DEFAULT NULL,
  `upgrade_effective_date` VARCHAR(50) NULL DEFAULT NULL,
  `role` VARCHAR(20) NOT NULL DEFAULT 'user',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `investment_plans` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `amount` DECIMAL(10, 2) NOT NULL,
  `currency` VARCHAR(10) DEFAULT 'USD',
  `daily_percentage` DECIMAL(5, 2) DEFAULT 1.00,
  `profit_percentage` DECIMAL(5, 2) DEFAULT 30.00,
  `monthly_profit` DECIMAL(10, 2) NOT NULL,
  `daily_profit` DECIMAL(10, 2) NOT NULL,
  `min_withdrawal` DECIMAL(10, 2) NOT NULL DEFAULT 10.00,
  `badge` VARCHAR(100) NULL,
  `color` VARCHAR(50) NULL,
  `description` TEXT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `sort_order` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `payment_gateways` (
  `id` VARCHAR(50) PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `is_enabled` TINYINT(1) DEFAULT 1,
  `bank_name` VARCHAR(100) NULL,
  `account_title` VARCHAR(150) NULL,
  `account_number` VARCHAR(100) NULL,
  `iban` VARCHAR(100) NULL,
  `wallet_address` VARCHAR(255) NULL,
  `network` VARCHAR(100) NULL,
  `instructions` TEXT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `type` ENUM('deposit', 'withdraw') NOT NULL,
  `category` ENUM('deposit', 'profit', 'capital') DEFAULT 'deposit',
  `amount` DECIMAL(10, 2) NOT NULL,
  `method` VARCHAR(50) NOT NULL,
  `account_or_wallet` VARCHAR(255) NULL,
  `trx_id` VARCHAR(100) NULL,
  `screenshot` VARCHAR(255) NULL,
  `status` ENUM('Pending', 'Under Review', 'Approved', 'Rejected', 'Failed') DEFAULT 'Pending',
  `note` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `videos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `video_url` VARCHAR(500) NOT NULL,
  `video_type` VARCHAR(50) DEFAULT 'auto',
  `thumbnail` VARCHAR(255) NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `expires_at` DATETIME NULL DEFAULT NULL,
  `sort_order` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `daily_video_watches` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `video_id` INT DEFAULT NULL,
  `claim_date` DATE NOT NULL,
  `profit_amount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `user_daily_claim` (`user_id`, `claim_date`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `user_daily_video_completions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `video_id` INT NOT NULL,
  `video_title` VARCHAR(255) NULL,
  `watch_date` DATE NOT NULL,
  `completed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `user_daily_watch_completion` (`user_id`, `watch_date`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Seed Default Investment Plans
INSERT INTO `investment_plans` (`id`, `name`, `amount`, `currency`, `daily_percentage`, `profit_percentage`, `monthly_profit`, `daily_profit`, `min_withdrawal`, `badge`, `color`, `description`, `is_active`, `sort_order`) VALUES
(1, 'Starter Plan', 100.00, 'USD', 1.00, 30.00, 30.00, 1.00, 10.00, 'Starter Tier', '#10b981', 'Guaranteed 1% daily return credited directly to wallet.', 1, 1),
(2, 'Growth Plan', 200.00, 'USD', 1.00, 30.00, 60.00, 2.00, 20.00, 'Popular Tier', '#0ea5e9', 'Guaranteed 1% daily return with automated compounding.', 1, 2),
(4, 'Pro Plan', 300.00, 'USD', 1.00, 30.00, 90.00, 3.00, 30.00, 'Executive Tier', '#8b5cf6', 'Guaranteed 1% daily return with accelerated profit yields.', 1, 4),
(3, 'VIP Pro Plan', 500.00, 'USD', 1.00, 30.00, 150.00, 5.00, 50.00, 'VIP Pro Tier', '#f59e0b', 'Guaranteed 1% daily return with priority TRC-20 payouts.', 1, 3)
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Seed Default Payment Gateways
INSERT INTO `payment_gateways` (`id`, `name`, `is_enabled`, `bank_name`, `account_title`, `account_number`, `iban`, `wallet_address`, `network`, `instructions`) VALUES
('bank', 'Bank Transfer', 1, 'Meezan Bank Limited', 'EarnNow Technologies PVT LTD', '01020304050607', 'PK64MEZN0001020304050607', NULL, NULL, 'Transfer via Raast / Online Mobile Banking to the IBAN or Account Number above and submit transaction reference & screenshot proof below.'),
('easypaisa', 'Easypaisa', 1, NULL, 'EarnNow Official Deposits', '0345-1234567', NULL, NULL, NULL, 'Send payment to this Easypaisa mobile account and upload screenshot proof below.'),
('jazzcash', 'JazzCash', 1, NULL, 'EarnNow Finance', '0300-9876543', NULL, NULL, NULL, 'Send payment to this JazzCash mobile account and upload screenshot proof below.'),
('trc20', 'USDT (TRC20)', 1, NULL, NULL, NULL, NULL, 'TXm1P2k7gK9Q5vR4zB8aC3dE6fH7jL9mNw', 'TRC-20 (Tron Network)', 'Transfer exact USDT via Tron TRC-20 network only. Upload transaction screenshot below.')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Seed Default Videos
INSERT INTO `videos` (`title`, `description`, `video_url`, `video_type`, `is_active`, `sort_order`) VALUES
('EarnNow Platform Overview & 1% Daily Profit Guide', 'Learn how our automated 1% daily guaranteed profit model works, how plans compound, and how TRC-20 fast withdrawals are processed within 1 hour.', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4', 'auto', 1, 1),
('How to Deposit Funds (EasyPaisa, JazzCash, Bank & USDT)', 'Full walkthrough demonstrating how to send funds via Pakistani banks, Raast, mobile wallets, or Tron TRC-20 and upload verification receipts.', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'auto', 1, 2);

-- Sample User (Password: password123)
INSERT INTO `users` (`name`, `email`, `phone`, `password`, `role`, `plan_id`, `plan_name`, `plan_amount`, `monthly_profit`, `balance`)
VALUES (
  'Alex Morgan',
  'alex.morgan@example.com',
  '+1 555-234-5678',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  'user',
  2,
  'Growth Plan',
  200.00,
  30.00,
  2845.50
) ON DUPLICATE KEY UPDATE `id`=`id`;

-- Admin User: mustansar.ba24@gmail.com (Password: Test@12345)
INSERT INTO `users` (`name`, `email`, `phone`, `password`, `role`, `plan_id`, `plan_name`, `plan_amount`, `monthly_profit`, `balance`)
VALUES (
  'Mustansar Admin',
  'mustansar.ba24@gmail.com',
  '+92 300 0000000',
  '$2y$10$Gy1huZdYwEwCaJWf0OKIEeN3fk3icVQGHAwLOiGMCr9l3hNgD1ZoG',
  'admin',
  3,
  'VIP Pro Plan',
  500.00,
  150.00,
  10000.00
) ON DUPLICATE KEY UPDATE `id`=`id`;
