-- ===================================================================
-- BLOOMORA PRODUCT REVIEW & RATING SYSTEM MIGRATION
-- Database: bloomora_db
-- ===================================================================

USE `bloomora_db`;

SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------------------
-- 1. TABLE: reviews
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reviews` (
    `id`                   INT AUTO_INCREMENT PRIMARY KEY,
    `product_id`           INT NOT NULL,
    `order_id`             INT NOT NULL,
    `user_id`              INT NOT NULL,
    `customer_name`        VARCHAR(100) NOT NULL,
    `customer_avatar`      VARCHAR(255) NULL,
    `rating`               TINYINT NOT NULL,
    `title`                VARCHAR(200) NULL,
    `comment`              TEXT NULL,
    `flower_quality_tag`   VARCHAR(150) NULL COMMENT 'Tag nhanh: Hoa rất tươi, Đóng gói cẩn thận, Giống hình, Giao đúng giờ',
    `status`               ENUM('pending', 'approved', 'hidden', 'rejected') NOT NULL DEFAULT 'pending',
    `is_verified_purchase` TINYINT(1) NOT NULL DEFAULT 1,
    `rejection_reason`     VARCHAR(255) NULL,
    `moderated_by`         INT NULL,
    `moderated_at`         DATETIME NULL,
    `created_at`           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_order_product_user` (`order_id`, `product_id`, `user_id`),
    INDEX `idx_product_status` (`product_id`, `status`),
    INDEX `idx_rating` (`rating`),
    INDEX `idx_created_at` (`created_at`),
    FOREIGN KEY (`product_id`)   REFERENCES `products`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`order_id`)     REFERENCES `orders`(`id`)   ON DELETE CASCADE,
    FOREIGN KEY (`user_id`)      REFERENCES `users`(`id`)    ON DELETE CASCADE,
    FOREIGN KEY (`moderated_by`) REFERENCES `users`(`id`)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------
-- 2. TABLE: review_images
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `review_images` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `review_id`    INT NOT NULL,
    `image_path`   VARCHAR(255) NOT NULL,
    `image_status` ENUM('active', 'hidden') NOT NULL DEFAULT 'active',
    `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_review_id` (`review_id`),
    FOREIGN KEY (`review_id`) REFERENCES `reviews`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------
-- 3. TABLE: review_replies (Official Shop Response)
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `review_replies` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `review_id`     INT NOT NULL,
    `user_id`       INT NOT NULL,
    `staff_name`    VARCHAR(100) NOT NULL,
    `reply_content` TEXT NOT NULL,
    `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_review_id` (`review_id`),
    FOREIGN KEY (`review_id`) REFERENCES `reviews`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`)   REFERENCES `users`(`id`)   ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------
-- 4. TABLE: review_audit_logs
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `review_audit_logs` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `review_id`  INT NOT NULL,
    `admin_id`   INT NOT NULL,
    `action`     VARCHAR(50) NOT NULL COMMENT 'approved, hidden, rejected, replied',
    `note`       VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_review_id` (`review_id`),
    FOREIGN KEY (`review_id`) REFERENCES `reviews`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`admin_id`)  REFERENCES `users`(`id`)   ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------
-- 5. SEED VERIFIED SAMPLE REVIEWS (Dựa trên sản phẩm thật đã giao)
-- -------------------------------------------------------------------

-- Cập nhật đơn hàng 1 của khách hàng 3 (Trần Tuấn Kiệt) sang 'completed' để có đơn hàng thật đã hoàn tất
UPDATE `orders` SET `order_status` = 'completed' WHERE `id` = 1;

-- Thêm 1 đánh giá mẫu đã duyệt cho sản phẩm 1 (Kệ Khai Trương Đại Cát Đại Lợi) từ đơn hàng 1 của user 3
INSERT INTO `reviews` (
    `id`, `product_id`, `order_id`, `user_id`, `customer_name`, `customer_avatar`,
    `rating`, `title`, `comment`, `flower_quality_tag`, `status`, `is_verified_purchase`,
    `moderated_by`, `moderated_at`
) VALUES (
    1, 1, 1, 3, 'Trần Tuấn Kiệt', NULL,
    5, 'Kệ hoa cực kỳ hoành tráng và tươi rói', 
    'Kệ hoa giao đúng giờ hẹn, hoa hướng dương nở to đều và màu vàng rất rực rỡ. Shop có gửi ảnh hoa thật qua Zalo trước khi giao nên mình rất an tâm. Khách mời khai trương ai cũng khen đẹp!',
    'Hoa tươi lâu, Giao đúng giờ, Giống hình 100%',
    'approved', 1, 1, NOW()
) ON DUPLICATE KEY UPDATE `status` = 'approved';

-- Phản hồi chính thức từ Shop cho review 1
INSERT INTO `review_replies` (`id`, `review_id`, `user_id`, `staff_name`, `reply_content`) VALUES
(1, 1, 1, 'Bloomora Florist', 'Dạ Bloomora chân thành cảm ơn anh Kiệt đã tin tưởng lựa chọn kệ hoa khai trương của tiệm! Chúc đối tác của anh khai trương hồng phát, vạn sự hanh thông ạ. Rất mong được phục vụ anh trong các dịp tới!')
ON DUPLICATE KEY UPDATE `reply_content` = VALUES(`reply_content`);

-- Đồng bộ điểm đánh giá ban đầu của Product 1
UPDATE `products` SET `rating` = 5.0, `reviews_count` = 1 WHERE `id` = 1;

SET FOREIGN_KEY_CHECKS = 1;
