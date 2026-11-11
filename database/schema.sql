-- ===================================================
-- BLOOMORA DATABASE SCHEMA & INITIAL SEED DATA
-- Charset: utf8mb4_unicode_ci
-- ===================================================

CREATE DATABASE IF NOT EXISTS `bloomora_db` 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `bloomora_db`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `order_status_history`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `product_images`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `roles`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. BẢNG PHÂN QUYỀN
CREATE TABLE `roles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL UNIQUE,
    `description` VARCHAR(255) NULL
) ENGINE=InnoDB;

INSERT INTO `roles` (`id`, `name`, `description`) VALUES 
(1, 'admin', 'Quản trị viên toàn quyền hệ thống'),
(2, 'staff', 'Nhân viên cắm hoa & giao nhận'),
(3, 'customer', 'Khách hàng');

-- 2. BẢNG TÀI KHOẢN NGƯỜI DÙNG
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `role_id` INT NOT NULL DEFAULT 3,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) NULL,
    `avatar` VARCHAR(255) NULL,
    `status` ENUM('active', 'inactive', 'banned') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Mật khẩu mặc định: admin123
INSERT INTO `users` (`id`, `role_id`, `email`, `password_hash`, `full_name`, `phone`, `status`) VALUES
(1, 1, 'admin@bloomora.vn', '$2y$10$wT0lQf/P.K0P3wA0R6y/dOomvQ6P7P.Cj3B2D2Vd/t6P1O/h0uTfa', 'Quản Trị Viên Bloomora', '0908123456', 'active'),
(2, 2, 'florist@bloomora.vn', '$2y$10$wT0lQf/P.K0P3wA0R6y/dOomvQ6P7P.Cj3B2D2Vd/t6P1O/h0uTfa', 'Nghệ Nhân Cắm Hoa Mai Anh', '0912345678', 'active'),
(3, 3, 'khachhang@gmail.com', '$2y$10$wT0lQf/P.K0P3wA0R6y/dOomvQ6P7P.Cj3B2D2Vd/t6P1O/h0uTfa', 'Trần Tuấn Kiệt', '0987654321', 'active');

-- 3. BẢNG DANH MỤC HOA
CREATE TABLE `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(120) NOT NULL UNIQUE,
    `icon` VARCHAR(50) DEFAULT 'ph-flower-lotus',
    `badge` VARCHAR(30) NULL,
    `display_order` INT DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

INSERT INTO `categories` (`id`, `name`, `slug`, `icon`, `badge`, `display_order`) VALUES
(1, 'Hoa Khai Trương', 'khai-truong', 'ph-storefront', 'Hot', 1),
(2, 'Hoa Sinh Nhật', 'sinh-nhat', 'ph-cake', NULL, 2),
(3, 'Tình Yêu & Valentine', 'tinh-yeu', 'ph-heart', 'Lãng mạn', 3),
(4, 'Lan Hồ Điệp VIP', 'lan-ho-diep', 'ph-crown', 'Sang trọng', 4),
(5, 'Tốt Nghiệp & Tri Ân', 'tot-nghiep', 'ph-graduation-cap', NULL, 5),
(6, 'Hoa Chia Buồn', 'chia-buon', 'ph-dove', NULL, 6);

-- 4. BẢNG SẢN PHẨM HOA
CREATE TABLE `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT NOT NULL,
    `sku` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(200) NOT NULL,
    `slug` VARCHAR(220) NOT NULL UNIQUE,
    `price` DECIMAL(12, 2) NOT NULL,
    `sale_price` DECIMAL(12, 2) NULL,
    `stock_quantity` INT NOT NULL DEFAULT 50,
    `primary_flower` VARCHAR(150) NULL,
    `color` VARCHAR(50) NULL,
    `color_name` VARCHAR(100) NULL,
    `size_dimensions` VARCHAR(100) NULL,
    `meaning` TEXT NULL,
    `tags` VARCHAR(255) NULL,
    `thumbnail` VARCHAR(500) NOT NULL,
    `is_featured` TINYINT(1) DEFAULT 1,
    `is_active` TINYINT(1) DEFAULT 1,
    `rating` DECIMAL(3, 2) DEFAULT 5.00,
    `reviews_count` INT DEFAULT 10,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Nạp 18 sản phẩm hoa thuần Việt
INSERT INTO `products` (`id`, `category_id`, `sku`, `name`, `slug`, `price`, `sale_price`, `stock_quantity`, `primary_flower`, `color`, `color_name`, `size_dimensions`, `meaning`, `tags`, `thumbnail`, `rating`, `reviews_count`) VALUES
(1, 1, 'KT01', 'Kệ Khai Trương Đại Cát Đại Lợi', 'ke-khai-truong-dai-cat-dai-loi', 1350000, 1600000, 25, 'Hướng dương Đà Lạt, Hồng cam Spirit, Đồng tiền đỏ, Lan vũ nữ', 'vang', 'Vàng & Đỏ Tài Lộc', 'Cao 175cm x Rộng 90cm', 'Biểu trưng cho khởi đầu rực rỡ, chiêu tài tấn bảo, làm ăn phát đạt thuận buồm xuôi gió.', 'Bán chạy nhất,Giao 60 phút', 'https://images.unsplash.com/photo-1561181286-d3fee7d55364?auto=format&fit=crop&w=800&q=80', 5.0, 48),
(2, 1, 'KT02', 'Lẵng Hoa Khai Trương Khởi Sắc Hoàng Kim', 'lang-khai-truong-khoi-sac-hoang-kim', 1850000, 2200000, 18, 'Hồng môn đỏ, Cúc mẫu đơn vàng nhập, Hồng Ohara cam, Cẩm chướng', 'vang', 'Tone Cam Vàng Hiện Đại', 'Cao 185cm x Rộng 95cm', 'Mang sinh khí dồi dào, thăng tiến vươn tầm cao mới, phúc lộc trường tồn.', 'Mẫu VIP,Freeship', 'https://images.unsplash.com/photo-1526047932273-341f2a7631f9?auto=format&fit=crop&w=800&q=80', 5.0, 36),
(3, 1, 'KT03', 'Giỏ Hoa Để Bàn Phát Tài Thịnh Vượng', 'gio-hoa-de-ban-phat-tai-thinh-vuong', 680000, 850000, 30, 'Hồng đỏ Sa Pa, Cúc tana, Cát tường song hỷ, Lá ngọc lan', 'do', 'Đỏ Rực Rỡ', 'Cao 55cm x Rộng 45cm', 'Thích hợp đặt quầy thu ngân, bàn lễ tân trong ngày khai trương mang lại may mắn cả năm.', 'Để bàn lễ tân,Giao nhanh', 'https://images.unsplash.com/photo-1582794543139-8ac9cb0f7b11?auto=format&fit=crop&w=800&q=80', 4.9, 62),
(4, 1, 'KT04', 'Kệ Hoa Khai Trương Pastel Phong Cách Hàn Quốc', 'ke-hoa-khai-truong-pastel-han-quoc', 1550000, 1800000, 15, 'Cúc mẫu đơn hồng, Hồng kem dâu, Phi yến trắng, Lá bạc dollar nhập', 'hong', 'Hồng Pastel & Trắng', 'Cao 170cm x Rộng 85cm', 'Phù hợp khai trương spa, thẩm mỹ viện, studio, showroom thời trang cao cấp.', 'Xu hướng 2026,Thanh lịch', 'https://images.unsplash.com/photo-1508615039623-a25605d2b022?auto=format&fit=crop&w=800&q=80', 5.0, 29),

(5, 2, 'SN01', 'Bó Hoa Nắng Ban Mai Rực Rỡ', 'bo-hoa-nang-ban-mai-ruc-ro', 490000, 580000, 40, 'Hướng dương hữu cơ Đà Lạt, Hoa baby trắng, Hoa sao tím', 'vang', 'Vàng Nắng Ấm Áp', 'Cao 60cm x Rộng 45cm', 'Gửi gắm niềm tin, năng lượng tích cực và nụ cười rạng ngời như ánh mặt trời cho tuổi mới.', 'Bán chạy,Giao 60 phút', 'https://images.unsplash.com/photo-1597848212624-a19eb35e2651?auto=format&fit=crop&w=800&q=80', 4.9, 114),
(6, 2, 'SN02', 'Giỏ Hoa Gỗ Vintage Hương Dịu Ngọt', 'gio-hoa-go-vintage-huong-diu-ngot', 750000, 890000, 22, 'Hồng Ohara nhập thơm ngát, Cẩm tú cầu hồng nhạt, Lan tường, Lá bạc', 'hong', 'Hồng Nhẹ Nhàng', 'Cao 50cm x Rộng 45cm', 'Lời tri ân sâu sắc và chúc cho người phụ nữ luôn an yên, dịu dàng và hạnh phúc đong đầy.', 'Tặng Mẹ / Sếp nữ,Giao 60 phút', 'https://images.unsplash.com/photo-1563245372-f21724e3856d?auto=format&fit=crop&w=800&q=80', 5.0, 75),
(7, 2, 'SN03', 'Bó Hoa Cúc Họa Mi Thuần Khiết Hà Nội', 'bo-hoa-cuc-hoa-mi-thuan-khiet', 360000, 420000, 35, 'Cúc họa mi Nhật Tân trắng muốt, Giấy báo nghệ thuật cổ điển, Nơ gai', 'trang', 'Trắng Trong Trẻo', 'Cao 55cm x Rộng 40cm', 'Vẻ đẹp mộc mạc, tinh khôi và tình bạn chân thành không toan tính.', 'Giá tiết kiệm,Thanh thuần', 'https://images.unsplash.com/photo-1606041008023-472dfb5e530f?auto=format&fit=crop&w=800&q=80', 4.8, 88),
(8, 2, 'SN04', 'Lẵng Hoa Trái Cây Kèm Hoa Tươi Cao Cấp', 'lang-hoa-trai-cay-cao-cap', 1250000, 1500000, 12, 'Táo Envy New Zealand, Nho mẫu đơn Hàn Quốc, Hồng đỏ Sa Pa', 'do', 'Sắc Đỏ Phúc Lộc', 'Cao 45cm x Rộng 50cm', 'Món quà trọn vẹn cả sắc lẫn vị, tượng trưng cho sức khỏe dồi dào, vạn sự cát tường.', 'Combo 2 trong 1,Tặng người lớn tuổi', 'https://images.unsplash.com/photo-1615865417491-9941019fbc00?auto=format&fit=crop&w=800&q=80', 5.0, 42),

(9, 3, 'TY01', 'Bó 99 Bông Hồng Đỏ Ecuador Mãi Mãi Một Tình Yêu', 'bo-99-bong-hong-do-ecuador', 2650000, 3200000, 15, '99 bông hồng đỏ nhung Ecuador cành dài 80cm, Đính ngọc trai, Đèn led', 'do', 'Đỏ Nhung Say Đắm', 'Cao 75cm x Rộng 70cm', '99 bông hồng tượng trưng cho tình yêu vĩnh cửu trường tồn, không bao giờ phai nhạt.', 'Cầu hôn / Kỷ niệm,Bán chạy #1', 'https://images.unsplash.com/photo-1518895949257-7621c3c786d7?auto=format&fit=crop&w=800&q=80', 5.0, 153),
(10, 3, 'TY02', 'Bó Hoa Hồng Juliet Nàng Thơ Kiêu Kỳ', 'bo-hoa-hong-juliet-nang-tho', 880000, 1050000, 20, 'Hồng triệu đô Juliet David Austin, Cát tường trắng, Baby Hà Lan', 'vang', 'Cam Đào Pastel', 'Cao 60cm x Rộng 50cm', 'Loài hoa mệnh danh Nàng thơ, thể hiện sự nâng niu, trân trọng và say đắm nàng từ ánh nhìn đầu tiên.', 'Hồng Triệu Đô,Đốn tim nàng', 'https://images.unsplash.com/photo-1562690868-60bbe7293e94?auto=format&fit=crop&w=800&q=80', 5.0, 97),
(11, 3, 'TY03', 'Hộp Hoa Trái Tim Ngọt Ngào Kèm Socola', 'hop-hoa-trai-tim-kem-socola', 790000, 950000, 25, 'Hồng đỏ Pháp, Baby trắng, 12 viên socola Ferrero Rocher mạ vàng', 'do', 'Đỏ Hồng Ngọt Ngào', 'Hộp tim 32cm x 30cm', 'Hương vị ngọt ngào của tình yêu nồng thắm hòa quyện giữa hoa tươi và socola mê đắm.', 'Combo Lãng Mạn,Valentine', 'https://images.unsplash.com/photo-1544717305-2782549b5136?auto=format&fit=crop&w=800&q=80', 4.9, 64),
(12, 3, 'TY04', 'Bó Hoa Baby Cầu Vồng Giấc Mơ Thơ Mộng', 'bo-hoa-baby-cau-vong', 620000, 750000, 30, 'Hoa baby Hà Lan nhuộm màu hữu cơ pastel: hồng, tím, xanh, vàng', 'xanh', '7 Sắc Cầu Vồng', 'Cao 65cm x Rộng 55cm', 'Tượng trưng cho thế giới muôn màu khi có em bên cạnh, hoa có thể để khô ngắm suốt 2-3 năm.', 'Hoa tươi giữ lâu,Trend GenZ', 'https://images.unsplash.com/photo-1525310072745-f49212b5ac6d?auto=format&fit=crop&w=800&q=80', 4.9, 81),

(13, 4, 'LH01', 'Chậu Lan Hồ Điệp 5 Cành Phú Quý Bình An', 'chau-lan-ho-diep-5-canh-phu-quy', 1650000, 1950000, 10, '5 cành đại hồ điệp trắng Đà Lạt tuyển chọn 10-12 bông/cành, sen đá tài lộc', 'trang', 'Trắng Quý Phái', 'Cao 95cm x Rộng 60cm', 'Mang lại vượng khí, tài lộc dồi dào, sự bình an và hòa khí cho tư gia hay văn phòng giám đốc.', 'Chậu sứ Bát Tràng,Tươi trên 6 tuần', 'https://images.unsplash.com/photo-1566938064504-a3791920ef62?auto=format&fit=crop&w=800&q=80', 5.0, 38),
(14, 4, 'LH02', 'Chậu Lan Hồ Điệp Vàng Hoàng Kim 9 Cành', 'chau-lan-ho-diep-vang-hoang-kim-9-canh', 2950000, 3500000, 8, '9 cành lan hồ điệp vàng hoàng hậu cánh dày, cây cọ mini, thảm rêu xanh tự nhiên', 'vang', 'Vàng Hoàng Gia', 'Cao 110cm x Rộng 75cm', 'Số 9 cửu đỉnh tượng trưng cho sự trường cửu, thịnh vượng bền vững và thành công vượt bậc.', 'VIP Doanh Nghiệp,Đại Cát', 'https://images.unsplash.com/photo-1599733589046-10c005739ef9?auto=format&fit=crop&w=800&q=80', 5.0, 27),
(15, 4, 'LH03', 'Chậu Đại Lan Hồ Điệp Tím Huế 12 Cành', 'chau-dai-lan-ho-diep-tim-hue', 3850000, 4500000, 5, '12 cành đại hồ điệp tím vân hoa, tiểu cảnh tùng bồng lai, phụ kiện đá phong thủy', 'tim', 'Tím Thủy Chung', 'Cao 120cm x Rộng 85cm', 'Thể hiện tấm lòng son sắt, lòng biết ơn sâu sắc đối với cha mẹ hoặc đối tác chiến lược.', 'Quà tặng VIP,Đẳng cấp', 'https://images.unsplash.com/photo-1524355875839-66e85f4039ec?auto=format&fit=crop&w=800&q=80', 5.0, 19),

(16, 5, 'TN01', 'Bó Hoa Hướng Dương Chắp Cánh Ước Mơ', 'bo-hoa-huong-duong-chap-canh-uoc-mo', 420000, 500000, 45, '3 bông hướng dương loại A, thạch thảo trắng, gấu bông tốt nghiệp mini', 'vang', 'Vàng Rực Rỡ', 'Cao 55cm x Rộng 40cm', 'Chúc bạn vững bước tương lai, công danh rộng mở và gặt hái ngàn thành tựu rực rỡ.', 'Tặng gấu cử nhân,Giao cổng trường', 'https://images.unsplash.com/photo-1543257580-7269da773bf5?auto=format&fit=crop&w=800&q=80', 4.9, 120),
(17, 5, 'TN02', 'Giỏ Hoa Tri Ân Thầy Cô Người Lái Đò Thầm Lặng', 'gio-hoa-tri-an-thay-co', 680000, 800000, 20, 'Hoa ly vàng thơm, Cẩm chướng hồng đại diện tình mẫu tử/sư phụ, Lan tường', 'hong', 'Hồng Cam Ấm Áp', 'Cao 55cm x Rộng 45cm', 'Tri ân công lao dạy dỗ dưỡng dục tựa biển trời của quý thầy cô giáo kính yêu.', 'Tri ân 20/11,Trang trọng', 'https://images.unsplash.com/photo-1487070183336-b863922373d4?auto=format&fit=crop&w=800&q=80', 5.0, 54),

(18, 6, 'CB01', 'Vòng Hoa Viếng Thành Kính Phân Ưu Tone Trắng', 'vong-hoa-vieng-thanh-kinh-phan-uu-trang', 950000, 1150000, 15, 'Lan thái trắng, Hoa ly trắng, Cúc mai trắng, Lá cau kiểng kết vòng tròn sinh tử', 'trang', 'Trắng Trang Nghiêm', 'Cao 175cm x Rộng 85cm', 'Bày tỏ lòng tiếc thương vô hạn, cầu chúc linh hồn người đã khuất an nghỉ nơi vĩnh hằng.', 'Giao viếng hỏa tốc,Trang nghiêm', 'https://images.unsplash.com/photo-1509198397868-475647b2a1e5?auto=format&fit=crop&w=800&q=80', 5.0, 68);

-- 5. BẢNG ĐƠN HÀNG
CREATE TABLE `orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_code` VARCHAR(20) NOT NULL UNIQUE,
    `user_id` INT NULL,
    `sender_name` VARCHAR(100) NOT NULL,
    `sender_phone` VARCHAR(20) NOT NULL,
    `is_anonymous` TINYINT(1) DEFAULT 0,
    `receiver_name` VARCHAR(100) NOT NULL,
    `receiver_phone` VARCHAR(20) NOT NULL,
    `receiver_address` VARCHAR(255) NOT NULL,
    `city` VARCHAR(50) DEFAULT 'TP. Hồ Chí Minh',
    `delivery_date` DATE NOT NULL,
    `delivery_time_slot` VARCHAR(50) NOT NULL,
    `card_message` TEXT NULL,
    `ribbon_message` VARCHAR(255) NULL,
    `subtotal` DECIMAL(12, 2) NOT NULL,
    `shipping_fee` DECIMAL(12, 2) DEFAULT 0,
    `discount_amount` DECIMAL(12, 2) DEFAULT 0,
    `total_amount` DECIMAL(12, 2) NOT NULL,
    `payment_method` ENUM('cod', 'vietqr', 'momo', 'bank_transfer') DEFAULT 'vietqr',
    `payment_status` ENUM('unpaid', 'paid', 'refunded') DEFAULT 'paid',
    `order_status` ENUM('received', 'preparing', 'photo_ready', 'delivering', 'completed', 'cancelled') DEFAULT 'delivering',
    `photo_proof` VARCHAR(500) NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 6. BẢNG CHI TIẾT SẢN PHẨM TRONG ĐƠN (SNAPSHOT)
CREATE TABLE `order_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `product_name` VARCHAR(200) NOT NULL,
    `product_sku` VARCHAR(50) NOT NULL,
    `unit_price` DECIMAL(12, 2) NOT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `total_price` DECIMAL(12, 2) NOT NULL,
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 7. BẢNG LỊCH SỬ TIẾN ĐỘ ĐƠN HÀNG
CREATE TABLE `order_status_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `status` VARCHAR(50) NOT NULL,
    `note` VARCHAR(255) NULL,
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Nạp 2 đơn hàng mẫu ban đầu
INSERT INTO `orders` (`id`, `order_code`, `user_id`, `sender_name`, `sender_phone`, `is_anonymous`, `receiver_name`, `receiver_phone`, `receiver_address`, `city`, `delivery_date`, `delivery_time_slot`, `card_message`, `ribbon_message`, `subtotal`, `shipping_fee`, `total_amount`, `payment_method`, `payment_status`, `order_status`, `photo_proof`) VALUES
(1, 'BL89241', 3, 'Nguyễn Văn Hùng', '0912345678', 0, 'Lê Thị Thu Thảo', '0988765432', 'Tòa nhà Landmark 81, P. 22, Q. Bình Thạnh', 'TP. Hồ Chí Minh', CURDATE(), 'Hỏa tốc 60-90 phút', 'Chúc em tuổi mới luôn xinh đẹp rạng ngời!', 'Chúc Mừng Sinh Nhật Thu Thảo', 1350000, 0, 1350000, 'vietqr', 'paid', 'delivering', 'https://images.unsplash.com/photo-1561181286-d3fee7d55364?auto=format&fit=crop&w=800&q=80'),
(2, 'BL90152', NULL, 'Công ty BĐS An Khang', '0903112233', 0, 'Ngân Hàng VIB Chi Nhánh Quận 1', '02838221144', 'Số 16 Phan Chu Trinh, P. Bến Thành, Quận 1', 'TP. Hồ Chí Minh', CURDATE(), 'Buổi sáng (08:00 - 11:30)', 'Kính chúc Chi nhánh khai trương Hồng Phát - Tấn Tài Tấn Lộc!', 'Công ty An Khang Kính Chúc Khai Trương Hồng Phát', 1850000, 0, 1850000, 'vietqr', 'paid', 'photo_ready', 'https://images.unsplash.com/photo-1526047932273-341f2a7631f9?auto=format&fit=crop&w=800&q=80');

INSERT INTO `order_items` (`order_id`, `product_id`, `product_name`, `product_sku`, `unit_price`, `quantity`, `total_price`) VALUES
(1, 1, 'Kệ Khai Trương Đại Cát Đại Lợi', 'KT01', 1350000, 1, 1350000),
(2, 2, 'Lẵng Hoa Khai Trương Khởi Sắc Hoàng Kim', 'KT02', 1850000, 1, 1850000);

INSERT INTO `order_status_history` (`order_id`, `status`, `note`, `created_by`) VALUES
(1, 'received', 'Hệ thống đã tiếp nhận đơn hoa thành công', 1),
(1, 'preparing', 'Florist Mai Anh đang thực hiện cắm hoa', 2),
(1, 'photo_ready', 'Đã chụp ảnh hoa thật và gửi duyệt qua Zalo khách', 2),
(1, 'delivering', 'Shipper Minh Tuấn đang vận chuyển giao hoa', 2),
(2, 'received', 'Tiếp nhận đơn hàng doanh nghiệp', 1),
(2, 'preparing', 'Florist đang cắm lẵng hoa 2 tầng', 2),
(2, 'photo_ready', 'Đã chụp ảnh lẵng hoa kèm băng rôn vi tính', 2);

