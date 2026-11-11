# ĐẶC TẢ YÊU CẦU PHẦN MỀM (SRS) - BLOOMORA
**Dự án:** Bloomora – Nền Tảng Thương Mại Điện Tử Hoa Tươi Trực Tuyến  
**Phiên bản:** 1.0.0  
**Tác giả:** Bloomora Architecture Team  
**Môi trường:** PHP 8.0+, MariaDB / MySQL 10.4+, Apache / Nginx, HTML5, CSS3/Tailwind, JavaScript ES6+  

---

## 1. TỔNG QUAN & PHẠM VI DỰ ÁN (PROJECT OVERVIEW & SCOPE)

### 1.1. Mục tiêu
Xây dựng nền tảng thương mại điện tử chuyên biệt cho ngành hoa tươi và điện hoa tại Việt Nam (kế thừa các điểm mạnh từ WishFlorist, FaSunflower, FlowerDJ), đáp ứng trọn vẹn hành trình từ tìm kiếm, tùy chỉnh bó hoa, thanh toán VietQR đến quản lý quy trình cắm hoa và giao nhận thực tế.

### 1.2. Đối tượng sử dụng (Actors)
1. **Khách vãng lai (Guest):** Tìm kiếm hoa, xem danh mục, lọc theo ngân sách/màu sắc, thêm vào giỏ, đặt hoa mà không bắt buộc tạo tài khoản.
2. **Khách hàng (Customer):** Đăng ký/đăng nhập, quản lý sổ địa chỉ nhận hoa, xem lịch sử đơn hàng, theo dõi hành trình giao hoa trực quan, lưu hoa yêu thích (Wishlist).
3. **Nhân viên / Florist (Staff):** Xem danh sách đơn hoa cần cắm theo ca, cập nhật trạng thái cắm hoa, chụp ảnh hoa thực tế tải lên hệ thống để gửi khách duyệt.
4. **Quản trị viên (Admin):** Quản lý toàn bộ danh mục, sản phẩm hoa, giá bán theo mùa, tồn kho, đơn hàng, doanh thu và cấu hình hệ thống.

---

## 2. QUY TẮC NGHIỆP VỤ CỐT LÕI (FLORIST BUSINESS RULES)

1. **BR01 - Khóa Giá Thời Điểm Mua (Price Snapshotting):**
   - Giá hoa phụ thuộc vào mùa vụ và ngày lễ. Bảng `order_items` bắt buộc phải lưu snapshot tên hoa, SKU và đơn giá tại thời điểm đặt hàng. Khi Admin thay đổi giá bán ngoài cửa hàng thì lịch sử đơn cũ không bị thay đổi.
2. **BR02 - Giới Hạn Khung Giờ Giao (Delivery Slot Capacity):**
   - Hoa tươi cần thời gian cắm nghệ thuật và giao bảo quản lạnh. Mỗi khung giờ giao (ví dụ 08:00 - 11:30) giới hạn tối đa 15 đơn hàng. Khi đạt giới hạn, hệ thống tự động khóa khung giờ đó.
3. **BR03 - Tách Biệt Người Gửi & Người Nhận (Sender vs. Receiver):**
   - Hệ thống lưu trữ riêng thông tin người đặt hoa và người nhận hoa. Khi khách chọn `is_anonymous = 1`, hệ thống ẩn danh tính người gửi trên hóa đơn giao hàng và thiệp chúc mừng.
4. **BR04 - Chụp Ảnh Thực Tế Trước Khi Giao (Photo Proof Approval):**
   - Trước khi shipper xuất phát, Florist bắt buộc tải ảnh chụp sản phẩm thực tế lên đơn hàng để khách hàng duyệt qua Zalo hoặc link tra cứu đơn.
5. **BR05 - Quy Trình Đơn Hàng 5 Bước (Order State Machine):**
   - `received` (Đã tiếp nhận) ➔ `preparing` (Florist đang cắm hoa) ➔ `photo_ready` (Đã chụp ảnh gửi duyệt) ➔ `delivering` (Shipper đang giao) ➔ `completed` (Đã giao thành công) / `cancelled` (Đã hủy).

---

## 3. ĐẶC TẢ CHỨC NĂNG CHI TIẾT (FUNCTIONAL SPECIFICATIONS)

### 3.1. Phân hệ Frontend Khách Hàng (FE01 - FE15)
- **FE01 - Header:** Thanh thông báo khuyến mãi, Logo Bloomora, ô tìm kiếm Live Search có debounce 300ms, bộ chọn khu vực giao hoa (TP.HCM, Hà Nội, Toàn quốc), nút tra cứu đơn nhanh, giỏ hàng slide-drawer có badge số lượng.
- **FE02 - Homepage:** Hero banner nghệ thuật, Hộp "Tìm Hoa Nhanh 3 Bước" (Dịp + Ngân sách + Khu vực), 4 cam kết vàng, khối sản phẩm nổi bật, dịch vụ cắm hoa chuyên biệt, đánh giá khách hàng.
- **FE04 + FE06 - Product Listing & Filter:** Lọc đa chiều kết hợp (Category + Price Range + Tone Màu), sắp xếp theo độ bán chạy, giá tăng/giảm dần, điểm đánh giá.
- **FE07 - Product Detail & Quick View:** Thư viện ảnh hoa, thông số kích thước, loài hoa thành phần, ý nghĩa loài hoa, Live Preview dải ruy băng/thiệp in vi tính, tùy chọn quà tặng kèm (Add-ons).
- **FE08 - Shopping Cart:** Thêm, xóa, cập nhật số lượng, lưu trữ phiên (Session/LocalStorage).
- **FE09 - Checkout:** Nhập thông tin người gửi, người nhận, chọn ngày/giờ giao hoa, phương thức thanh toán (VietQR, MoMo, COD), tự động tính tổng tiền trên Server để chống gian lận F12.
- **FE10 - Order Success & VietQR:** Hiển thị mã đơn hàng dạng `BL-xxxxx`, mã VietQR động chuẩn NAPAS ngân hàng quân đội MBBank, nội dung chuyển khoản tự động khớp mã đơn.

### 3.2. Phân hệ Quản Trị Admin (AD01 - AD22)
- **AD02 - Dashboard:** Thống kê doanh thu theo ngày/tháng, số lượng đơn hàng mới, đơn đang cắm, đơn đang giao, top sản phẩm bán chạy nhất.
- **AD03 - Product Management:** Xem danh sách hoa, thêm hoa mới (tên, danh mục, giá bán, giá khuyến mãi, số lượng tồn kho, màu sắc, hoa thành phần), sửa, ẩn/hiện sản phẩm.
- **AD08 - Order Management:** Quản lý danh sách đơn hàng, xem chi tiết người nhận/người gửi, lời nhắn in băng rôn, đổi trạng thái đơn hàng (5 bước), cập nhật link ảnh chụp hoa thực tế (`photo_proof`).

---

## 4. BẢO MẬT & XỬ LÝ LỖI (SECURITY & ERROR HANDLING)
1. **Chống SQL Injection:** 100% câu truy vấn qua `PDO Prepared Statements` và tham số bind.
2. **Chống XSS (Cross-Site Scripting):** Mọi dữ liệu xuất ra HTML đều được xử lý qua `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
3. **Chống giả mạo giá:** Tổng tiền đơn hàng được tính toán lại hoàn toàn tại Backend dựa trên bảng `products`, không tin cậy giá gửi lên từ Client.
4. **Database Transaction:** Toàn bộ quá trình tạo đơn hàng gồm `orders` và `order_items` được thực thi bên trong `beginTransaction()`, nếu có lỗi sẽ `rollBack()` ngay lập tức.

