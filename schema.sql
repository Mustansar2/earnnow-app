-- EarnNow MySQL Database Schema
-- Compatible with MySQL 5.7+ / 8.0+ and MariaDB

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
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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
  `status` ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
  `note` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sample User (Password: password123)
INSERT INTO `users` (`name`, `email`, `phone`, `password`, `plan_id`, `plan_name`, `plan_amount`, `monthly_profit`, `balance`)
VALUES (
  'Alex Morgan',
  'alex.morgan@example.com',
  '+1 555-234-5678',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  2,
  'Growth Plan',
  200.00,
  30.00,
  2845.50
) ON DUPLICATE KEY UPDATE `id`=`id`;
