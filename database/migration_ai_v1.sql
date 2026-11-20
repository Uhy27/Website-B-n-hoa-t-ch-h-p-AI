-- ===================================================================
-- BLOOMORA AI CHATBOT & RECOMMENDATION ENGINE MIGRATION (Phase 1)
-- Database: bloomora_db
-- ===================================================================

USE `bloomora_db`;

SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------------------
-- 1. ADD AI METADATA TAGS TO TABLE `products`
-- -------------------------------------------------------------------

-- occasion_tags: sinh-nhat, khai-truong, tinh-yeu, tot-nghiep, chia-buon, 20-10, 8-3, 20-11, tan-gia...
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'bloomora_db' AND TABLE_NAME = 'products' AND COLUMN_NAME = 'occasion_tags');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `products` ADD COLUMN `occasion_tags` VARCHAR(255) NULL AFTER `tags`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- recipient_tags: me, bo, nguoi-yeu, vo, crush, ban-be, dong-nghiep, doi-tac, thay-co, sep...
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'bloomora_db' AND TABLE_NAME = 'products' AND COLUMN_NAME = 'recipient_tags');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `products` ADD COLUMN `recipient_tags` VARCHAR(255) NULL AFTER `occasion_tags`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- flower_type_tags: hong, huong-duong, lan-ho-diep, cuc, baby, cam-chuong, cat-tuong, ly...
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'bloomora_db' AND TABLE_NAME = 'products' AND COLUMN_NAME = 'flower_type_tags');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `products` ADD COLUMN `flower_type_tags` VARCHAR(255) NULL AFTER `recipient_tags`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- color_tags: do, vang, hong, trang, tim, cam, xanh...
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'bloomora_db' AND TABLE_NAME = 'products' AND COLUMN_NAME = 'color_tags');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `products` ADD COLUMN `color_tags` VARCHAR(150) NULL AFTER `flower_type_tags`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- style_tags: han-quoc, toi-gian, sang-trong, nhe-nhang, vintage, hien-dai, ruc-ro...
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'bloomora_db' AND TABLE_NAME = 'products' AND COLUMN_NAME = 'style_tags');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `products` ADD COLUMN `style_tags` VARCHAR(255) NULL AFTER `color_tags`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- product_form: bo, gio, ke, hop, chau, lang
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'bloomora_db' AND TABLE_NAME = 'products' AND COLUMN_NAME = 'product_form');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `products` ADD COLUMN `product_form` ENUM(\'bo\', \'gio\', \'ke\', \'hop\', \'chau\', \'lang\') NOT NULL DEFAULT \'bo\' AFTER `style_tags`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- stock_status: in_stock, low_stock, out_of_stock, pre_order
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'bloomora_db' AND TABLE_NAME = 'products' AND COLUMN_NAME = 'stock_status');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `products` ADD COLUMN `stock_status` ENUM(\'in_stock\', \'low_stock\', \'out_of_stock\', \'pre_order\') NOT NULL DEFAULT \'in_stock\' AFTER `stock_quantity`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- -------------------------------------------------------------------
-- 2. CREATE TABLE `ai_settings` (Dynamic Scoring Weights & LLM Config)
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(50) NOT NULL UNIQUE,
    `setting_value` TEXT NOT NULL,
    `description` VARCHAR(255) NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `ai_settings` (`setting_key`, `setting_value`, `description`) VALUES
('weight_occasion', '0.25', 'Trọng số khớp dịp tặng (Occasion) - 25%'),
('weight_price', '0.25', 'Trọng số khớp ngân sách (Price/Budget) - 25%'),
('weight_recipient', '0.20', 'Trọng số khớp đối tượng nhận (Recipient) - 20%'),
('weight_color_flower', '0.15', 'Trọng số khớp màu sắc & loại hoa - 15%'),
('weight_style_form', '0.10', 'Trọng số khớp phong cách & hình thức (bó, giỏ, kệ...) - 10%'),
('weight_stock', '0.05', 'Trọng số trạng thái tồn kho thực tế - 5%'),
('ai_model', 'gemini-2.5-flash', 'Model AI sử dụng để phân tích intent & sinh câu trả lời'),
('gemini_api_key', '', 'Google Gemini API Key (tùy chọn lưu trong DB hoặc biến môi trường)'),
('max_recommendations', '4', 'Số lượng sản phẩm đề xuất tối đa trong 1 lần chat'),
('shop_hotline', '1900 8888', 'Hotline tư vấn khi khách cần hỗ trợ gấp hoặc ngoài phạm vi AI')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);


-- -------------------------------------------------------------------
-- 3. CREATE TABLE `ai_knowledge` (Grounding Knowledge Base)
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_knowledge` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category` ENUM('meaning', 'care', 'faq', 'policy') NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `keywords` VARCHAR(255) NOT NULL,
    `content` TEXT NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `ai_knowledge` (`category`, `title`, `keywords`, `content`) VALUES
('meaning', 'Ý nghĩa Hoa Hồng Đỏ', 'hong, do, tinh yeu, valentine, cau hon', 'Hoa hồng đỏ là biểu tượng tối cao của tình yêu say đắm, mãnh liệt và vĩnh cửu. Rất thích hợp tỏ tình, kỷ niệm ngày yêu, ngày cưới và dịp Valentine.'),
('meaning', 'Ý nghĩa Hoa Hướng Dương', 'huong duong, nang luong, sinh nhat, tot nghiep, niem tin', 'Hoa hướng dương luôn hướng về ánh mặt trời, đại diện cho niềm tin, ý chí vươn lên, năng lượng tích cực và sự thành công. Thích hợp tặng tốt nghiệp, sinh nhật bạn bè, đồng nghiệp.'),
('meaning', 'Ý nghĩa Lan Hồ Điệp', 'lan ho diep, sang trong, tai loc, khai truong, mung tho, sep, doi tac', 'Lan hồ điệp tượng trưng cho sự phú quý, thịnh vượng bền lâu, tài lộc dồi dào và đẳng cấp sang trọng. Thường dùng chúc mừng khai trương, thăng chức, tặng đối tác VIP hoặc mừng thọ.'),
('meaning', 'Ý nghĩa Hoa Cúc Họa Mi', 'cuc hoa mi, thuan khiet, trong treo, thanh xuan, moc mac', 'Cúc họa mi mang nét đẹp thuần khiết, giản dị, tinh khôi và gắn liền với thanh xuân rực rỡ. Rất được bạn trẻ, phái nữ yêu thích vào mùa thu đông.'),
('meaning', 'Ý nghĩa Cẩm Tú Cầu & Hoa Baby', 'baby, cam tu cau, biet on, thau hieu, ngot ngao', 'Hoa baby trắng thể hiện tình yêu ngây thơ, thuần khiết. Cẩm tú cầu tượng trưng cho sự biết ơn chân thành, lòng thấu hiểu sâu sắc.'),
('care', 'Hướng dẫn chăm sóc hoa cắm bình cắt cành', 'cham soc, giu tuoi, cat canh, nuoc', '1. Cắt vát gốc hoa một góc 45 độ bằng dao bén trước khi cắm.\n2. Bỏ sạch lá ngập trong nước để tránh phân hủy gây thối gốc.\n3. Dùng nước sạch pha thêm gói dưỡng hoa Bloomora tặng kèm.\n4. Thay nước 1-2 ngày/lần và cắt bớt 1cm gốc mỗi lần thay.\n5. Đặt hoa nơi thoáng mát, tránh ánh nắng trực tiếp, quạt gió và máy lạnh thổi thẳng vào hoa.'),
('care', 'Cách chăm sóc chậu Lan Hồ Điệp', 'lan ho diep, tuoi nuoc, anh sang', '1. Tưới nước 4-6 ngày/lần tùy độ ẩm không khí, tưới vào gốc, tuyệt đối không tưới lên cánh hoa.\n2. Đặt chậu nơi có ánh sáng tán xạ nhẹ (gần cửa sổ có rèm), nhiệt độ lý tưởng 20-28°C.\n3. Sau khi hoa tàn, cắt cuống hoa cách mắt ngủ thứ 2 để cây tiếp tục đâm chồi mới.'),
('policy', 'Chính sách giao hoa hỏa tốc', 'giao hang, thoi gian, hoa toc, phi ship, 60 phut', 'Bloomora hỗ trợ giao hoa hỏa tốc trong 60 - 90 phút tại tất cả quận nội thành TP.HCM. Miễn phí vận chuyển cho đơn hàng từ 1.000.000đ trong bán kính 7km.'),
('policy', 'Quy trình chụp ảnh xác nhận trước khi giao', 'chup anh, xem hoa, chup hinh, photo proof', '100% đơn hàng tại Bloomora đều được nghệ nhân chụp ảnh sản phẩm thật và gửi qua Zalo/SMS cho quý khách duyệt trước khi nhân viên giao hoa lên đường.'),
('policy', 'Chính sách đổi trả & bảo hành độ tươi', 'doi tra, bao hanh, khieu nai, dap nat, hoa heo', 'Bloomora cam kết hoa tươi tối thiểu 3 ngày. Nếu sản phẩm giao đến bị dập nát, héo úa hoặc không giống mẫu cam kết, quý khách vui lòng phản hồi trong vòng 2 giờ kể từ khi nhận hoa để được đổi mới 100% hoặc hoàn tiền.');


-- -------------------------------------------------------------------
-- 4. CREATE TABLES `chat_sessions` & `chat_messages`
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `chat_sessions` (
    `id` VARCHAR(64) PRIMARY KEY,
    `user_id` INT NULL,
    `guest_identifier` VARCHAR(100) NULL,
    `last_intent` VARCHAR(50) NULL,
    `context_data` JSON NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `session_id` VARCHAR(64) NOT NULL,
    `sender` ENUM('user', 'bot', 'system') NOT NULL,
    `message` TEXT NOT NULL,
    `intent` VARCHAR(50) NULL,
    `tool_called` VARCHAR(50) NULL,
    `metadata` JSON NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`session_id`) REFERENCES `chat_sessions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -------------------------------------------------------------------
-- 5. BACKFILL AI METADATA FOR 18 EXISTING PRODUCTS
-- -------------------------------------------------------------------

-- 1. Kệ Khai Trương Đại Cát Đại Lợi (KT01 - 1.350k)
UPDATE `products` SET 
  `occasion_tags` = 'khai-truong,thanh-lap,su-kien',
  `recipient_tags` = 'doi-tac,dong-nghiep,sep,doanh-nghiep',
  `flower_type_tags` = 'huong-duong,hong,lan',
  `color_tags` = 'vang,do',
  `style_tags` = 'sang-trong,ruc-ro,hien-dai',
  `product_form` = 'ke',
  `stock_status` = 'in_stock'
WHERE `sku` = 'KT01';

-- 2. Lẵng Hoa Khai Trương Khởi Sắc Hoàng Kim (KT02 - 1.850k)
UPDATE `products` SET 
  `occasion_tags` = 'khai-truong,ky-niem,su-kien',
  `recipient_tags` = 'doi-tac,sep,doanh-nghiep',
  `flower_type_tags` = 'cuc-mau-don,hong,cam-chuong',
  `color_tags` = 'cam,vang,do',
  `style_tags` = 'sang-trong,hien-dai',
  `product_form` = 'lang',
  `stock_status` = 'in_stock'
WHERE `sku` = 'KT02';

-- 3. Giỏ Hoa Để Bàn Phát Tài Thịnh Vượng (KT03 - 680k)
UPDATE `products` SET 
  `occasion_tags` = 'khai-truong,sinh-nhat,tan-gia',
  `recipient_tags` = 'ban-be,doi-tac,dong-nghiep',
  `flower_type_tags` = 'hong,cuc,cat-tuong',
  `color_tags` = 'do',
  `style_tags` = 'nhe-nhang,hien-dai',
  `product_form` = 'gio',
  `stock_status` = 'in_stock'
WHERE `sku` = 'KT03';

-- 4. Kệ Hoa Khai Trương Pastel Phong Cách Hàn Quốc (KT04 - 1.550k)
UPDATE `products` SET 
  `occasion_tags` = 'khai-truong,su-kien',
  `recipient_tags` = 'ban-be,doi-tac,sep',
  `flower_type_tags` = 'cuc-mau-don,hong,phi-yen',
  `color_tags` = 'hong,trang',
  `style_tags` = 'han-quoc,pastel,thanh-lich,nhe-nhang',
  `product_form` = 'ke',
  `stock_status` = 'in_stock'
WHERE `sku` = 'KT04';

-- 5. Bó Hoa Nắng Ban Mai Rực Rỡ (SN01 - 490k)
UPDATE `products` SET 
  `occasion_tags` = 'sinh-nhat,tot-nghiep,cam-on',
  `recipient_tags` = 'ban-be,nguoi-yeu,dong-nghiep,em-gai',
  `flower_type_tags` = 'huong-duong,baby',
  `color_tags` = 'vang,trang',
  `style_tags` = 'ruc-ro,tre-trung,nang-dong',
  `product_form` = 'bo',
  `stock_status` = 'in_stock'
WHERE `sku` = 'SN01';

-- 6. Giỏ Hoa Gỗ Vintage Hương Dịu Ngọt (SN02 - 750k)
UPDATE `products` SET 
  `occasion_tags` = 'sinh-nhat,20-10,8-3,tri-an',
  `recipient_tags` = 'me,sep,thay-co,phu-nu',
  `flower_type_tags` = 'hong,cam-tu-cau,lan-tuong',
  `color_tags` = 'hong',
  `style_tags` = 'vintage,nhe-nhang,am-ap',
  `product_form` = 'gio',
  `stock_status` = 'in_stock'
WHERE `sku` = 'SN02';

-- 7. Bó Hoa Cúc Họa Mi Thuần Khiết Hà Nội (SN03 - 360k)
UPDATE `products` SET 
  `occasion_tags` = 'sinh-nhat,ky-niem,chup-anh,tang-me',
  `recipient_tags` = 'me,ban-be,nguoi-yeu,em-gai',
  `flower_type_tags` = 'cuc',
  `color_tags` = 'trang',
  `style_tags` = 'nhe-nhang,toi-gian,vintage,korean',
  `product_form` = 'bo',
  `stock_status` = 'in_stock'
WHERE `sku` = 'SN03';

-- 8. Lẵng Hoa Trái Cây Kèm Hoa Tươi Cao Cấp (SN04 - 1.250k)
UPDATE `products` SET 
  `occasion_tags` = 'sinh-nhat,mung-tho,tham-benh,tri-an',
  `recipient_tags` = 'me,bo,ong-ba,nguoi-lon-tuoi,sep',
  `flower_type_tags` = 'hong',
  `color_tags` = 'do',
  `style_tags` = 'sang-trong,thiet-thuc',
  `product_form` = 'lang',
  `stock_status` = 'in_stock'
WHERE `sku` = 'SN04';

-- 9. Bó 99 Bông Hồng Đỏ Ecuador (TY01 - 2.650k)
UPDATE `products` SET 
  `occasion_tags` = 'tinh-yeu,valentine,cau-hon,ky-niem',
  `recipient_tags` = 'nguoi-yeu,vo',
  `flower_type_tags` = 'hong',
  `color_tags` = 'do',
  `style_tags` = 'sang-trong,lang-man,ruc-ro',
  `product_form` = 'bo',
  `stock_status` = 'in_stock'
WHERE `sku` = 'TY01';

-- 10. Bó Hoa Hồng Juliet Nàng Thơ Kiêu Kỳ (TY02 - 880k)
UPDATE `products` SET 
  `occasion_tags` = 'tinh-yeu,sinh-nhat,valentine,ky-niem',
  `recipient_tags` = 'nguoi-yeu,vo,crush',
  `flower_type_tags` = 'hong,cat-tuong,baby',
  `color_tags` = 'cam,vang',
  `style_tags` = 'han-quoc,nang-tho,nhe-nhang,sang-trong',
  `product_form` = 'bo',
  `stock_status` = 'in_stock'
WHERE `sku` = 'TY02';

-- 11. Hộp Hoa Trái Tim Ngọt Ngào Kèm Socola (TY03 - 790k)
UPDATE `products` SET 
  `occasion_tags` = 'tinh-yeu,valentine,ky-niem,sinh-nhat',
  `recipient_tags` = 'nguoi-yeu,vo',
  `flower_type_tags` = 'hong,baby',
  `color_tags` = 'do,hong',
  `style_tags` = 'lang-man,ngot-ngao',
  `product_form` = 'hop',
  `stock_status` = 'in_stock'
WHERE `sku` = 'TY03';

-- 12. Bó Hoa Baby Cầu Vồng Giấc Mơ Thơ Mộng (TY04 - 620k)
UPDATE `products` SET 
  `occasion_tags` = 'sinh-nhat,tot-nghiep,tinh-yeu,ky-niem',
  `recipient_tags` = 'nguoi-yeu,ban-be,em-gai',
  `flower_type_tags` = 'baby',
  `color_tags` = 'xanh,hong,tim,vang',
  `style_tags` = 'tre-trung,pastel,mong-mo',
  `product_form` = 'bo',
  `stock_status` = 'in_stock'
WHERE `sku` = 'TY04';

-- 13. Chậu Lan Hồ Điệp 5 Cành Phú Quý Bình An (LH01 - 1.650k)
UPDATE `products` SET 
  `occasion_tags` = 'khai-truong,tan-gia,tet,chuc-tho',
  `recipient_tags` = 'sep,doi-tac,bo,me,nguoi-lon-tuoi',
  `flower_type_tags` = 'lan-ho-diep',
  `color_tags` = 'trang',
  `style_tags` = 'sang-trong,quy-phai,tinh-te',
  `product_form` = 'chau',
  `stock_status` = 'in_stock'
WHERE `sku` = 'LH01';

-- 14. Chậu Lan Hồ Điệp Vàng Hoàng Kim 9 Cành (LH02 - 2.950k)
UPDATE `products` SET 
  `occasion_tags` = 'khai-truong,tan-gia,tet,thang-chuc',
  `recipient_tags` = 'doi-tac,sep,doanh-nghiep',
  `flower_type_tags` = 'lan-ho-diep',
  `color_tags` = 'vang',
  `style_tags` = 'sang-trong,hoang-gia,dang-cap',
  `product_form` = 'chau',
  `stock_status` = 'in_stock'
WHERE `sku` = 'LH02';

-- 15. Chậu Đại Lan Hồ Điệp Tím Huế 12 Cành (LH03 - 3.850k)
UPDATE `products` SET 
  `occasion_tags` = 'khai-truong,tri-an,chuc-tho,tet',
  `recipient_tags` = 'me,bo,doi-tac,sep',
  `flower_type_tags` = 'lan-ho-diep',
  `color_tags` = 'tim',
  `style_tags` = 'sang-trong,quy-phai,thuy-chung',
  `product_form` = 'chau',
  `stock_status` = 'in_stock'
WHERE `sku` = 'LH03';

-- 16. Bó Hoa Hướng Dương Chắp Cánh Ước Mơ (TN01 - 420k)
UPDATE `products` SET 
  `occasion_tags` = 'tot-nghiep,chuc-mung,sinh-nhat',
  `recipient_tags` = 'ban-be,em-gai,dong-nghiep,hoc-sinh',
  `flower_type_tags` = 'huong-duong',
  `color_tags` = 'vang',
  `style_tags` = 'tre-trung,nang-dong,ruc-ro',
  `product_form` = 'bo',
  `stock_status` = 'in_stock'
WHERE `sku` = 'TN01';

-- 17. Giỏ Hoa Tri Ân Thầy Cô Người Lái Đò Thầm Lặng (TN02 - 680k)
UPDATE `products` SET 
  `occasion_tags` = '20-11,tri-an,tot-nghiep,sinh-nhat',
  `recipient_tags` = 'thay-co,me,nguoi-lon-tuoi',
  `flower_type_tags` = 'ly,cam-chuong,lan-tuong',
  `color_tags` = 'hong,cam,vang',
  `style_tags` = 'trang-trong,am-ap,thanh-lich',
  `product_form` = 'gio',
  `stock_status` = 'in_stock'
WHERE `sku` = 'TN02';

-- 18. Vòng Hoa Viếng Thành Kính Phân Ưu Tone Trắng (CB01 - 950k)
UPDATE `products` SET 
  `occasion_tags` = 'chia-buon,tang-le',
  `recipient_tags` = 'gia-quyen,doi-tac',
  `flower_type_tags` = 'lan,ly,cuc',
  `color_tags` = 'trang',
  `style_tags` = 'trang-nghiem,thanh-kinh',
  `product_form` = 'ke',
  `stock_status` = 'in_stock'
WHERE `sku` = 'CB01';

SET FOREIGN_KEY_CHECKS = 1;
