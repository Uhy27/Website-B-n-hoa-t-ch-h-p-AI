-- ===================================================================
-- BLOOMORA v2 MIGRATION — Promotions, Vouchers, Inventory & Reviews
-- Run AFTER schema.sql on database `bloomora_db`
-- Charset: utf8mb4_unicode_ci   Engine: InnoDB
-- ===================================================================

USE `bloomora_db`;

SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------------------
-- 1. ALTER TABLE `products` — add cost_price
-- -------------------------------------------------------------------
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'bloomora_db'
      AND TABLE_NAME   = 'products'
      AND COLUMN_NAME  = 'cost_price'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `products` ADD COLUMN `cost_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `sale_price`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Back-fill cost_price from price for existing rows
UPDATE `products` SET `cost_price` = `price` * 0.55 WHERE `cost_price` = 0.00;

-- -------------------------------------------------------------------
-- 2. ALTER TABLE `orders` — add voucher_code
-- -------------------------------------------------------------------
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'bloomora_db'
      AND TABLE_NAME   = 'orders'
      AND COLUMN_NAME  = 'voucher_code'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `orders` ADD COLUMN `voucher_code` VARCHAR(50) NULL AFTER `ribbon_message`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- -------------------------------------------------------------------
-- 3. CREATE TABLE `campaigns`
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `campaigns` (
    `id`                  INT AUTO_INCREMENT PRIMARY KEY,
    `name`                VARCHAR(200) NOT NULL,
    `slug`                VARCHAR(220) NOT NULL UNIQUE,
    `description`         TEXT NULL,
    `campaign_type`       ENUM('daily','monthly','yearly','holiday','flash_sale','clearance','loyalty') NOT NULL,
    `discount_type`       ENUM('percentage','fixed_amount') NOT NULL,
    `discount_value`      DECIMAL(12,2) NOT NULL,
    `min_order_value`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `max_discount_amount` DECIMAL(12,2) NULL,
    `budget_limit`        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `budget_used`         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `min_margin`          DECIMAL(5,2)  NOT NULL DEFAULT 20.00,
    `start_at`            DATETIME NOT NULL,
    `end_at`              DATETIME NOT NULL,
    `allow_stacking`      TINYINT(1) NOT NULL DEFAULT 0,
    `status`              ENUM('draft','scheduled','active','paused','expired','budget_exhausted') NOT NULL DEFAULT 'draft',
    `approval_status`     ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `created_by`          INT NULL,
    `created_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------
-- 4. CREATE TABLE `vouchers`
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vouchers` (
    `id`                  INT AUTO_INCREMENT PRIMARY KEY,
    `campaign_id`         INT NULL,
    `code`                VARCHAR(50) NOT NULL UNIQUE,
    `description`         TEXT NULL,
    `discount_type`       ENUM('percentage','fixed_amount') NOT NULL,
    `discount_value`      DECIMAL(12,2) NOT NULL,
    `min_order_value`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `max_discount_amount` DECIMAL(12,2) NULL,
    `usage_limit`         INT NOT NULL DEFAULT 100,
    `used_count`          INT NOT NULL DEFAULT 0,
    `per_user_limit`      INT NOT NULL DEFAULT 1,
    `start_at`            DATETIME NOT NULL,
    `end_at`              DATETIME NOT NULL,
    `is_active`           TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`campaign_id`) REFERENCES `campaigns`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------
-- 5. CREATE TABLE `promotion_schedules`
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `promotion_schedules` (
    `id`              INT AUTO_INCREMENT PRIMARY KEY,
    `campaign_id`     INT NOT NULL,
    `schedule_type`   ENUM('specific_date','date_range','recurring_weekly','recurring_monthly','holiday') NOT NULL,
    `day_of_week`     TINYINT NULL,
    `day_of_month`    VARCHAR(50) NULL,
    `month`           TINYINT NULL,
    `holiday_name`    VARCHAR(100) NULL,
    `start_time`      TIME NOT NULL DEFAULT '00:00:00',
    `end_time`        TIME NOT NULL DEFAULT '23:59:59',
    `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (`campaign_id`) REFERENCES `campaigns`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------
-- 6. CREATE TABLE `promotion_products`
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `promotion_products` (
    `id`                    INT AUTO_INCREMENT PRIMARY KEY,
    `campaign_id`           INT NOT NULL,
    `product_id`            INT NOT NULL,
    `custom_discount_value` DECIMAL(12,2) NULL,
    `max_quantity`          INT NOT NULL DEFAULT 0,
    `sold_quantity`         INT NOT NULL DEFAULT 0,
    FOREIGN KEY (`campaign_id`) REFERENCES `campaigns`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------
-- 7. CREATE TABLE `voucher_usages`
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `voucher_usages` (
    `id`              INT AUTO_INCREMENT PRIMARY KEY,
    `voucher_id`      INT NOT NULL,
    `order_id`        INT NOT NULL,
    `user_id`         INT NULL,
    `discount_amount` DECIMAL(12,2) NOT NULL,
    `status`          ENUM('used','refunded') NOT NULL DEFAULT 'used',
    `used_at`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`voucher_id`) REFERENCES `vouchers`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`order_id`)   REFERENCES `orders`(`id`)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------
-- 8. CREATE TABLE `promotion_approvals`
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `promotion_approvals` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `campaign_id`  INT NOT NULL,
    `admin_id`     INT NOT NULL,
    `action`       ENUM('submitted','approved','rejected','paused') NOT NULL,
    `note`         TEXT NULL,
    `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`campaign_id`) REFERENCES `campaigns`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`admin_id`)    REFERENCES `users`(`id`)     ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------
-- 9. CREATE TABLE `promotion_budget_logs`
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `promotion_budget_logs` (
    `id`               INT AUTO_INCREMENT PRIMARY KEY,
    `campaign_id`      INT NOT NULL,
    `order_id`         INT NOT NULL,
    `amount_deducted`  DECIMAL(12,2) NOT NULL,
    `budget_remaining` DECIMAL(12,2) NOT NULL,
    `type`             ENUM('spend','refund') NOT NULL,
    `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`campaign_id`) REFERENCES `campaigns`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`order_id`)    REFERENCES `orders`(`id`)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------
-- 10. CREATE TABLE `inventory_logs`
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventory_logs` (
    `id`                 INT AUTO_INCREMENT PRIMARY KEY,
    `product_id`         INT NOT NULL,
    `change_quantity`    INT NOT NULL,
    `remaining_quantity` INT NOT NULL,
    `reason`             ENUM('import','sale','cancel_refund','damage','clearance','audit_adjustment') NOT NULL,
    `order_id`           INT NULL,
    `user_id`            INT NULL,
    `note`               VARCHAR(255) NULL,
    `created_at`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`order_id`)   REFERENCES `orders`(`id`)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------
-- 11. CREATE TABLE `reviews`
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reviews` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `product_id`    INT NOT NULL,
    `order_id`      INT NOT NULL,
    `user_id`       INT NULL,
    `customer_name` VARCHAR(100) NOT NULL,
    `rating`        TINYINT NOT NULL DEFAULT 5,
    `comment`       TEXT NULL,
    `is_approved`   TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`order_id`)   REFERENCES `orders`(`id`)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------
-- SAMPLE DATA — Campaign & Vouchers
-- -------------------------------------------------------------------

-- Campaign: Mừng Khai Trương Bloomora
INSERT INTO `campaigns` (
    `name`, `slug`, `description`, `campaign_type`,
    `discount_type`, `discount_value`, `min_order_value`, `max_discount_amount`,
    `budget_limit`, `budget_used`, `min_margin`,
    `start_at`, `end_at`,
    `allow_stacking`, `status`, `approval_status`, `created_by`
) VALUES (
    'Mừng Khai Trương Bloomora',
    'mung-khai-truong-bloomora',
    'Chương trình ưu đãi đặc biệt chào mừng Bloomora chính thức ra mắt. Áp dụng cho tất cả đơn hàng trong 30 ngày đầu tiên.',
    'holiday',
    'percentage', 10.00, 500000.00, 100000.00,
    5000000.00, 0.00, 20.00,
    NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY),
    0, 'active', 'approved', 1
);

SET @campaign_id = LAST_INSERT_ID();

-- Voucher 1: BLOOMORA10 — 10% giảm, tối đa 100k, đơn tối thiểu 500k
INSERT INTO `vouchers` (
    `campaign_id`, `code`, `description`,
    `discount_type`, `discount_value`, `min_order_value`, `max_discount_amount`,
    `usage_limit`, `used_count`, `per_user_limit`,
    `start_at`, `end_at`, `is_active`
) VALUES (
    @campaign_id,
    'BLOOMORA10',
    'Giảm 10% tối đa 100.000₫ cho đơn từ 500.000₫ — Mừng Khai Trương Bloomora',
    'percentage', 10.00, 500000.00, 100000.00,
    100, 0, 1,
    NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 1
);

-- Voucher 2: GIAM50K — giảm cố định 50k, đơn tối thiểu 600k
INSERT INTO `vouchers` (
    `campaign_id`, `code`, `description`,
    `discount_type`, `discount_value`, `min_order_value`, `max_discount_amount`,
    `usage_limit`, `used_count`, `per_user_limit`,
    `start_at`, `end_at`, `is_active`
) VALUES (
    @campaign_id,
    'GIAM50K',
    'Giảm trực tiếp 50.000₫ cho đơn từ 600.000₫ — Mừng Khai Trương Bloomora',
    'fixed_amount', 50000.00, 600000.00, NULL,
    100, 0, 1,
    NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 1
);

-- Approval record for the campaign
INSERT INTO `promotion_approvals` (`campaign_id`, `admin_id`, `action`, `note`) VALUES
(@campaign_id, 1, 'submitted', 'Đề xuất chương trình khai trương'),
(@campaign_id, 1, 'approved',  'Duyệt chương trình khai trương Bloomora');

SET FOREIGN_KEY_CHECKS = 1;

-- ===================================================================
-- MIGRATION v2 COMPLETE
-- ===================================================================
