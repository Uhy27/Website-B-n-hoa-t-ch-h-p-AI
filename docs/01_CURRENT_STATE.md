# 01_CURRENT_STATE.md – BÁO CÁO KIỂM TOÁN HIỆN TRẠNG DỰ ÁN FLOW – BLOOMORA

> **Dự án:** BLOOMORA – Website Thương mại Điện tử Hoa tươi & Điện hoa Trực tuyến  
> **Thư mục kiểm toán:** `C:\xampp\htdocs\flow\`  
> **Môi trường thực thi:** PHP 8.0.30 (CLI & Apache), MariaDB/MySQL 10.4+, XAMPP trên Windows  
> **Ngày thực hiện kiểm toán:** 14/11/2026  
> **Trạng thái mã nguồn:** Mã nguồn thực tế đã được giải nén và kiểm tra trực tiếp từng dòng code trong thư mục làm việc.

---

## 1. TỔNG QUAN HỆ THỐNG HIỆN TẠI

Hệ thống BLOOMORA hiện tại là một ứng dụng web thương mại điện tử chuyên ngành hoa tươi được xây dựng theo mô hình **Single Page Application (SPA) phía Client** kết hợp với **hệ thống Backend RESTful API độc lập bằng PHP thuần + PDO** và lưu trữ dữ liệu trên **MariaDB/MySQL**.

### 1.1. Cấu trúc thư mục thực tế tại `C:\xampp\htdocs\flow\`

```
flow/
├── admin/
│   ├── index.php                 # Dashboard quản trị: thống kê 4 chỉ số & quản lý đơn hàng 5 bước (22.4 KB)
│   └── login.php                 # Trang đăng nhập dành riêng cho Admin/Staff (8.3 KB)
├── api/
│   ├── admin/
│   │   ├── orders.php            # GET: danh sách đơn hàng; POST: cập nhật trạng thái đơn & photo proof (5.5 KB)
│   │   └── stats.php             # GET: 4 chỉ số thống kê tổng quan (1.6 KB)
│   ├── auth/
│   │   ├── login.php             # POST: xác thực email/password qua bcrypt, cấp session (3.0 KB)
│   │   ├── logout.php            # POST/GET: hủy phiên làm việc session (0.5 KB)
│   │   ├── me.php                # GET: kiểm tra trạng thái session người dùng hiện tại (1.9 KB)
│   │   └── register.php          # POST: đăng ký tài khoản khách hàng mới (3.4 KB)
│   ├── user/
│   │   └── orders.php            # GET: danh sách lịch sử đơn hàng của khách hàng đang đăng nhập (2.1 KB)
│   ├── categories.php            # GET: danh sách danh mục hoa kèm đếm số sản phẩm active (1.4 KB)
│   ├── checkout.php              # POST: giao dịch đặt hàng, khóa dữ liệu, trừ tồn kho, sinh VietQR (10.6 KB)
│   ├── products.php              # GET: danh sách sản phẩm với bộ lọc đa chiều (category, price, color, q, sort) (3.7 KB)
│   └── tracking.php              # GET: tra cứu tiến trình đơn hàng công khai theo mã đơn hoặc SĐT (3.2 KB)
├── config/
│   ├── database.php              # Cấu hình kết nối PDO chuẩn utf8mb4, tắt emulate prepares (1.6 KB)
│   └── helpers.php               # Tiện ích: get_json_input, init_secure_session, require_admin_auth, json_response (2.2 KB)
├── core/                         # Thư mục trống (chưa có code)
├── css/
│   └── style.css                 # Định dạng bổ trợ cho Tailwind CSS và hiệu ứng UI (5.9 KB)
├── database/
│   ├── bloomora_erd.dbml         # Thiết kế sơ đồ quan hệ thực thể DBML cho dbdiagram.io (3.1 KB)
│   └── schema.sql                # File SQL DDL khởi tạo 7 bảng chính và dữ liệu mẫu (18.4 KB)
├── docs/                         # Thư mục chứa tài liệu đặc tả đồ án, SRS và báo cáo phản biện
├── images/                       # Thư mục hình ảnh tĩnh
├── js/
│   ├── app.js                    # Core JavaScript Client xử lý toàn bộ tương tác SPA (67.2 KB, 1479 dòng)
│   └── data.js                   # Mock data tĩnh dự phòng (18 sản phẩm, danh mục, review) (20.9 KB)
├── index.html                    # Giao diện người dùng Storefront hoàn chỉnh (69.1 KB)
└── index.php                     # Chuyển hướng về index.html (170 Bytes)
```

---

## 2. HIỆN TRẠNG CƠ SỞ DỮ LIỆU (DATABASE AUDIT)

Cơ sở dữ liệu `bloomora_db` đã được cấu hình trong `database/schema.sql` và tồn tại trong thư mục dữ liệu MySQL của XAMPP (`C:\xampp\mysql\data\bloomora_db`).

### 2.1. Danh sách các bảng đang tồn tại (7 bảng)

| STT | Tên bảng | Số trường | Mục đích lưu trữ | Đánh giá hiện trạng |
|:---:|:---|:---:|:---|:---|
| 1 | `roles` | 3 | Phân quyền hệ thống (admin, staff, customer) | Đầy đủ, hoạt động tốt |
| 2 | `users` | 10 | Tài khoản người dùng, băm mật khẩu `password_hash` | Đầy đủ, bảo mật chuẩn bcrypt |
| 3 | `categories` | 7 | Danh mục hoa (khai trương, sinh nhật, tình yêu...) | Đầy đủ slug, icon, badge |
| 4 | `products` | 19 | Thông tin sản phẩm hoa, giá, sale_price, stock_quantity | Thiếu trường `cost_price` (giá vốn) |
| 5 | `orders` | 23 | Đơn hàng, địa chỉ, người gửi/nhận, trạng thái, VietQR | Có `discount_amount` nhưng chưa có FK voucher |
| 6 | `order_items` | 8 | Snapshot chi tiết sản phẩm trong đơn tại thời điểm mua | Rất tốt, chuẩn snapshot nghiệp vụ |
| 7 | `order_status_history` | 6 | Lịch sử cập nhật trạng thái đơn (5 bước) | Hoạt động tốt |

### 2.2. Điểm mạnh của Database hiện tại
1. **Toàn vẹn dữ liệu (Referential Integrity):** Sử dụng các khóa ngoại (`FOREIGN KEY`) ràng buộc chặt chẽ với các hành động `ON DELETE RESTRICT`, `ON DELETE CASCADE` và `ON DELETE SET NULL` hợp lý.
2. **Nguyên tắc Snapshot giá (Price Snapshotting):** Bảng `order_items` lưu lại `unit_price`, `quantity`, `total_price` và `product_name` tại thời điểm đặt hàng. Khi giá ở bảng `products` biến động, lịch sử giao dịch không bị sai lệch.
3. **Bảo mật thông tin người gửi (Privacy):** Bảng `orders` có cờ `is_anonymous` hỗ trợ khách hàng tặng hoa ẩn danh.
4. **Kiểm soát quy trình thực tế:** Có bảng `order_status_history` lưu vết từng bước xử lý của nghệ nhân cắm hoa (`created_by`), kèm cột `photo_proof` lưu URL ảnh chụp hoa thật để khách duyệt.

### 2.3. Thiếu sót nghiêm trọng trong Database
1. **Chưa có trường `cost_price` (Giá vốn) trong bảng `products`:** Hệ thống đang có `price` và `sale_price` nhưng không có giá vốn. Điều này khiến cho việc kiểm tra **biên lợi nhuận tối thiểu (Minimum Margin)** trong các chương trình khuyến mãi/xả kho là **không thể thực hiện được**.
2. **Hoàn toàn chưa có các bảng phục vụ Promotion Engine:**
   - Không có bảng `campaigns` (chiến dịch khuyến mãi).
   - Không có bảng `vouchers` (mã giảm giá).
   - Không có bảng `promotion_schedules` (lịch kích hoạt theo ngày, tháng, năm, dịp lễ).
   - Không có bảng `promotion_products` (sản phẩm áp dụng khuyến mãi).
   - Không có bảng `voucher_usages` (ghi nhận khách hàng đã dùng voucher để chặn tái sử dụng).
   - Không có bảng `promotion_budget_logs` (theo dõi hạn mức ngân sách chiến dịch).
   - Không có bảng `inventory_logs` (theo dõi biến động nhập xuất kho hoa).
3. **Chưa có bảng `reviews`:** Bảng `products` có 2 trường `rating` và `reviews_count`, nhưng không có bảng lưu trữ nội dung đánh giá của khách hàng sau khi nhận hoa. Dữ liệu đánh giá hiện tại ở Frontend hoàn toàn là mock data tĩnh trong `data.js`.

---

## 3. HIỆN TRẠNG PHÂN HỆ BACKEND & REST API

### 3.1. Các API đang hoạt động

#### Nhóm Xác thực & Người dùng (`api/auth/` & `api/user/`)
- `POST /api/auth/login.php`:
  - Cơ chế: Đọc JSON qua `get_json_input()`, truy vấn user theo email bằng Prepared Statement, xác thực qua `password_verify()`.
  - Phân quyền: Có cờ `require_admin` để chặn người dùng role `customer` truy cập vào giao diện quản trị.
  - Quản lý phiên: Tái tạo Session ID chống Session Fixation (`session_regenerate_id(true)`), gán `$_SESSION['user_id']`, `role_name`.
- `POST /api/auth/register.php`:
  - Validate email, độ dài mật khẩu (>= 6 ký tự), kiểm tra trùng lặp email.
  - Băm mật khẩu bằng `password_hash(..., PASSWORD_DEFAULT)`, gán mặc định role `customer` (role_id = 3).
  - Tự động đăng nhập và trả về dữ liệu profile.
- `GET /api/auth/me.php`: Kiểm tra session hiện tại, kiểm tra lại trạng thái `status = 'active'` trong database.
- `GET /api/user/orders.php`: Lấy danh sách đơn hàng đã mua của user đăng nhập, kèm snapshot items chi tiết.

#### Nhóm Mua hàng & Tra cứu (`api/`)
- `GET /api/categories.php`: Lấy danh mục đang active kèm số lượng sản phẩm (`COUNT(p.id)`), bổ sung danh mục ảo `all` ("Tất Cả Mẫu Hoa").
- `GET /api/products.php`:
  - Hỗ trợ lọc theo danh mục (`category`), khoảng giá (`price`), tông màu (`color`), từ khóa tìm kiếm (`q`), sắp xếp (`sort`).
  - Toàn bộ tham số được bind qua PDO Prepared Statements.
  - Trả về JSON chuẩn hóa (ép kiểu float, int, mảng tags).
- `POST /api/checkout.php`:
  - Đọc payload JSON danh sách sản phẩm `items` và thông tin giao hoa.
  - **Bắt đầu PDO Transaction (`beginTransaction`)**.
  - Truy vấn giá sản phẩm trực tiếp từ DB với câu lệnh khóa dòng `FOR UPDATE` (`SELECT id, name, sku, price, stock_quantity FROM products WHERE ... FOR UPDATE`).
  - Kiểm tra tồn kho: Nếu không đủ, rollback ngay và trả lỗi 400.
  - Trừ tồn kho trực tiếp trong DB (`UPDATE products SET stock_quantity = GREATEST(0, stock_quantity - :qty)`).
  - Tạo mã đơn hàng duy nhất `BLxxxxx`, lưu vào `orders`, `order_items` và `order_status_history`.
  - Commit transaction và sinh link mã VietQR động chuẩn NAPAS theo mẫu MBBank.
- `GET /api/tracking.php`: Tra cứu đơn hàng theo mã hoặc số điện thoại. Ẩn thông tin người tặng nếu `is_anonymous = 1`. Trả về chi tiết đơn, sản phẩm và lịch sử các bước cắm hoa.

#### Nhóm Quản trị (`api/admin/`)
- `GET /api/admin/stats.php`:
  - Yêu cầu xác thực `require_admin_auth()`.
  - Thống kê: `totalRevenue` (loại trừ cancelled), `totalOrders`, `pendingOrders`, `deliveringOrders`, `completedOrders`, `totalProducts`.
- `GET /api/admin/orders.php`:
  - Lọc theo trạng thái đơn hàng, kèm chi tiết `order_items`.
- `POST /api/admin/orders.php`:
  - Cập nhật trạng thái đơn (received -> preparing -> photo_ready -> delivering -> completed -> cancelled).
  - Đính kèm URL ảnh hoa cắm thực tế `photo_proof`.
  - Ghi lịch sử vào bảng `order_status_history`.

### 3.2. Lỗ hổng và Hạn chế Kỹ thuật trong Backend hiện tại
1. **Thiếu cơ chế hoàn tồn kho khi Hủy đơn (Cancel Order Bug):**
   - Tại `api/checkout.php`, khi khách đặt đơn, hệ thống đã trừ `stock_quantity`.
   - Tuy nhiên tại `api/admin/orders.php`, khi Admin chuyển trạng thái đơn sang `cancelled`, **hệ thống không hề cộng lại số lượng tồn kho** cho các sản phẩm trong đơn. Điều này dẫn đến thất thoát số lượng ảo trong kho.
2. **Hardcode bảng giá phụ kiện (Addons):**
   - Trong `api/checkout.php`, mảng `$addonPrices` (gấu bông, socola, nến thơm, bánh kem) đang được gán cứng dạng PHP array. Chưa có bảng phụ kiện hoặc sản phẩm bổ trợ trong database.
3. **Chưa hỗ trợ Voucher & Discount trong Checkout:**
   - `api/checkout.php` chưa nhận trường `voucherCode`, chưa kiểm tra điều kiện mã giảm giá, trường `discount_amount` của bảng `orders` luôn mặc định bằng 0.
4. **Chưa có API CRUD cho Sản phẩm, Danh mục, Khách hàng, Kho hàng và Khuyến mãi:**
   - Phân hệ Admin hiện tại chỉ có duy nhất 2 file API là `orders.php` và `stats.php`. Toàn bộ các phân hệ quản trị còn lại đều chưa có API backend.

---

## 4. HIỆN TRẠNG PHÂN HỆ FRONTEND (CLIENT AUDIT)

### 4.1. Giao diện Khách hàng (`index.html` & `js/app.js`)
- **Kiến trúc UI:** Xây dựng bằng Tailwind CSS (CDN), bộ icon Phosphor Icons, font chữ Google Fonts tiếng Việt (Be Vietnam Pro, Playfair Display).
- **Trạng thái ứng dụng (`AppState`):**
  - Quản trị state tập trung trong `js/app.js`: danh sách sản phẩm, bộ lọc, giỏ hàng, thông tin phiên đăng nhập.
  - Giỏ hàng lưu trữ đồng bộ với `localStorage` (`bloomora_cart`).
- **Các thành phần giao diện đã hoạt động mượt mà:**
  1. *Header:* Thanh cam kết, logo, ô tìm kiếm thời gian thực có Debounce 300ms, nút tra cứu nhanh, nút giỏ hàng có badge hiển thị số lượng, dropdown thông tin tài khoản đăng nhập.
  2. *Hero Section:* Hộp "Tìm Hoa Nhanh 3 Bước" (dịp hoa, ngân sách, khu vực giao), 4 cam kết vàng.
  3. *Danh mục & Bộ lọc:* Thanh cuộn ngang danh mục, bộ lọc giá (dưới 500k, 500k-1tr, 1tr-2tr, trên 2tr), bộ lọc tông màu (đỏ, vàng, hồng, trắng, tím, xanh), sắp xếp theo độ bán chạy, giá, đánh giá.
  4. *Lưới sản phẩm & QuickView Modal:* Hiển thị thẻ sản phẩm với nhãn sale, hoa tươi thành phần, đánh giá sao. Modal QuickView hỗ trợ chọn số lượng, chọn quà tặng kèm (gấu bông, socola, nến), **Live Preview dải băng rôn in vi tính thời gian thực**.
  5. *Slide-drawer Giỏ hàng:* Trượt mượt từ cạnh phải, cho phép tăng/giảm số lượng, xóa sản phẩm, tính tổng tiền tức thời.
  6. *Checkout Modal:* Đặt hoa 1 bước (One-page Checkout), chọn người gửi/người nhận, chọn giao ẩn danh, chọn ngày giao và khung giờ giao (slot), phương thức thanh toán (VietQR hoặc COD).
  7. *Order Success Modal:* Hiển thị mã đơn `BLxxxxx`, ảnh mã VietQR động do Backend trả về, thông tin chuyển khoản chính xác.
  8. *Order Tracking Modal:* Nhập mã đơn hoặc số điện thoại, hiển thị tiến trình 5 bước trực quan và ảnh chụp hoa thực tế (`photo_proof`).
  9. *My Orders Modal:* Hiển thị danh sách các đơn hàng đã đặt của tài khoản thành viên.
  10. *Auth Modal:* Modal đăng nhập / đăng ký chuyển tab mượt mà, kiểm tra lỗi form trực quan.

### 4.2. Hạn chế của Frontend hiện tại
1. **Chưa có ô nhập mã Voucher ở Giỏ hàng và Checkout:** Giao diện chưa có input nhập mã giảm giá, chưa có giao diện hiển thị số tiền được giảm trừ và thông báo lý do mã không hợp lệ.
2. **Chưa có Banner / Countdown cho Flash Sale & Khuyến mãi:** Trang chủ chưa có khối hiển thị các chương trình khuyến mãi tự động đang diễn ra (chỉ có banner tĩnh).
3. **Phần Đánh giá (Reviews) chưa tương tác được:** Danh sách đánh giá ở cuối trang chủ đang render tĩnh từ `data.js`. Người dùng chưa có nút gửi đánh giá cho đơn hàng đã hoàn tất.
4. **Phụ thuộc vào Tailwind CDN:** Đang tải Tailwind CSS qua script CDN trên client (`https://cdn.tailwindcss.com`), phù hợp cho môi trường phát triển nhưng cần tối ưu bundle khi triển khai production để tối ưu First Contentful Paint.

---

## 5. HIỆN TRẠNG PHÂN HỆ QUẢN TRỊ (`admin/`)

### 5.1. Những gì đã có
- `admin/login.php`: Giao diện đăng nhập bảo mật cho quản trị viên, tự động chuyển hướng về `index.php` nếu đã đăng nhập.
- `admin/index.php`:
  - 4 thẻ thống kê: Tổng doanh thu, Tổng đơn hàng, Đơn cần cắm & chụp ảnh, Đơn đang giao.
  - Bảng quản trị đơn hàng: lọc theo 6 trạng thái (`received`, `preparing`, `photo_ready`, `delivering`, `completed`, `cancelled`).
  - Modal cập nhật trạng thái đơn hàng: cho phép đổi trạng thái sang bước tiếp theo, dán link ảnh chụp hoa thật (`photoProof`) và ghi chú tiến độ.
  - Cơ chế escape dữ liệu đầu ra (`escapeHtml`) chống tấn công Stored XSS trong trang admin.

### 5.2. Những gì còn thiếu (Khoảng trống quản trị lớn)
1. **Không có Quản lý Sản phẩm (Product Management):** Admin không thể thêm mẫu hoa mới, không thể sửa giá, không thể điều chỉnh tồn kho, không thể cập nhật ảnh hoa hay phân loại danh mục qua giao diện.
2. **Không có Quản lý Danh mục (Category Management):** Không thể thêm hoặc sửa danh mục hoa.
3. **Không có Quản lý Khách hàng (Customer Management):** Không có danh sách tài khoản khách hàng, không xem được tổng tiền chi tiêu của từng khách, không có chức năng khóa/mở tài khoản.
4. **Không có Quản lý Khuyến mãi & Voucher (Campaign / Promotion Management):** Chưa có giao diện tạo voucher, cấu hình lịch khuyến mãi theo ngày/tháng/dịp lễ, cài đặt ngưỡng biên lợi nhuận hay phê duyệt chiến dịch.
5. **Không có Báo cáo Chi tiết & Quản lý Kho:** Chưa có báo cáo lợi nhuận thực tế (Doanh thu - Giá vốn), chưa có nhật ký biến động kho (`inventory_logs`).

---

## 6. ĐÁNH GIÁ BẢO MẬT & HIỆU NĂNG

| Tiêu chí | Trạng thái thực tế | Đánh giá kỹ thuật |
|:---|:---:|:---|
| **SQL Injection** | **ĐẠT (Tốt)** | 100% các API hiện tại đều sử dụng `PDO Prepared Statements` với tham số bind (`:name`, `:id`). Tùy chọn `PDO::ATTR_EMULATE_PREPARES => false` được bật, đảm bảo tầng driver MySQL thực hiện parameter binding thực chất. |
| **XSS (Cross-Site Scripting)** | **ĐẠT (Khá)** | Frontend Admin có hàm `escapeHtml()` cho toàn bộ dữ liệu động từ API. Storefront index.html đã escape các trường đầu vào. Cần kiểm soát thêm thuộc tính HTML trong phần render mô tả hoa. |
| **Gian lận sửa giá (F12 Tampering)** | **ĐẠT (Rất tốt)** | `api/checkout.php` tuyệt đối không nhận đơn giá từ client. Hệ thống truy vấn trực tiếp bảng `products` từ database để nhân với số lượng và tính tổng tiền trên server. |
| **Race Condition khi mua hàng** | **ĐẠT (Khá)** | `api/checkout.php` sử dụng transaction kèm lệnh `SELECT ... FOR UPDATE` để khóa bản ghi sản phẩm trước khi kiểm tra số lượng và trừ kho. |
| **Bảo mật Mật khẩu** | **ĐẠT (Tốt)** | Mật khẩu được băm bằng thuật toán Bcrypt tiêu chuẩn công nghiệp qua hàm `password_hash()` và đối soát bằng `password_verify()`. |
| **CSRF Protection** | **CHƯA ĐẠT** | Chưa triển khai CSRF token cho các request POST/PUT/DELETE làm thay đổi trạng thái (hiện tại dựa hoàn toàn vào Session cookie `SameSite=Lax`). |
| **Kiểm soát Tồn kho khi Hủy đơn** | **CHƯA ĐẠT** | Khi đơn hàng bị hủy trong admin, số lượng sản phẩm không được hoàn trả về kho. |
| **Kiểm soát Lợi nhuận Khuyến mãi** | **CHƯA CÓ** | Chưa có cơ chế ngăn chặn bán lỗ dưới giá vốn. |

---

## 7. KẾT LUẬN KIỂM TOÁN HIỆN TRẠNG

1. **Nền tảng hiện có rất vững chắc:** Hệ thống không phải là một bản vẽ phác thảo mà đã có sẵn khung kiến trúc SPA + REST API hoàn chỉnh, luồng mua hàng cốt lõi (xem sản phẩm -> giỏ hàng -> checkout -> VietQR -> tra cứu 5 bước) đã vận hành trơn tru và logic bảo mật nền tảng được viết rất chỉn chu.
2. **Tuân thủ triệt để nguyên tắc "Không đập đi xây lại":** Toàn bộ giao diện storefront `index.html`, script `js/app.js`, cấu trúc database hiện tại và các API mua hàng đang chạy cần được giữ nguyên vẹn 100%.
3. **Trọng tâm nâng cấp tiếp theo:**
   - Bổ sung trường `cost_price` vào bảng `products`.
   - Xây dựng hệ thống bảng và dịch vụ cho **Promotion Engine** (Voucher, Giảm giá theo thời gian, Xả kho có kiểm soát lợi nhuận).
   - Nâng cấp `api/checkout.php` để tích hợp tính toán voucher trực tiếp trong Transaction.
   - Bổ sung các trang quản trị còn thiếu trong `admin/` (Sản phẩm, Kho hàng, Khuyến mãi, Khách hàng) theo phong cách tab hoặc menu đồng bộ với giao diện hiện có.

