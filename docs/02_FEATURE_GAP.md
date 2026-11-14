# 02_FEATURE_GAP.md – PHÂN TÍCH KHOẢNG TRỐNG CHỨC NĂNG (GAP ANALYSIS)

> **Dự án:** FLOW – BLOOMORA  
> **Mục tiêu:** Xác định chính xác khoảng cách giữa yêu cầu định hướng phát triển và mã nguồn thực tế hiện có, từ đó đưa ra giải pháp kỹ thuật bổ sung mà không phá vỡ kiến trúc đang chạy.  
> **Nguyên tắc phân loại:**
> - **P0 (Bắt buộc / Rất cao):** Các tính năng cốt lõi ảnh hưởng trực tiếp đến giao dịch, dữ liệu và tính toàn vẹn của hệ thống.
> - **P1 (Quan trọng / Cao):** Phân hệ khuyến mãi thông minh, quản trị kho và quản lý nghiệp vụ.
> - **P2 (Nâng cấp tiếp theo):** Tối ưu hóa trải nghiệm người dùng, phân tích báo cáo chuyên sâu và mở rộng.

---

## 1. MA TRẬN ĐỐI CHIẾU CHỨC NĂNG TỔNG THỂ

### 1.1. Phân hệ 1: Website Khách Hàng (Storefront)

| Mã | Chức năng theo định hướng | Hiện trạng trong Source thực tế | Đánh giá | Mức ưu tiên | Khoảng trống & Giải pháp kỹ thuật |
|:---|:---|:---|:---:|:---:|:---|
| **FE-01** | Trang chủ (Banner, sản phẩm nổi bật, cam kết) | Đã có trong `index.html` và `js/app.js`. Hero banner, 4 cam kết vàng, sản phẩm nổi bật. | **Hoàn thành 85%** | **P2** | *Thiếu:* Banner động cho Flash Sale và đếm ngược thời gian chương trình khuyến mãi. Thêm component Countdown Timer. |
| **FE-02** | Danh mục hoa (Khai trương, sinh nhật, tình yêu...) | Đã có bảng `categories`, API `api/categories.php`, render động dạng chip trượt ngang trên `index.html`. | **Hoàn thành 100%** | **--** | Đang hoạt động tốt, giữ nguyên. |
| **FE-03** | Danh sách sản phẩm & Chi tiết (Hình ảnh, giá, tồn kho) | Đã có API `api/products.php`, lưới sản phẩm responsive, modal QuickView xem ảnh, kích thước, thành phần hoa. | **Hoàn thành 90%** | **P2** | *Thiếu:* Thư viện nhiều ảnh phụ cho sản phẩm (gallery thumbnails). Hiện tại mỗi sản phẩm chỉ có 1 ảnh đại diện `thumbnail`. |
| **FE-04** | Tìm kiếm thông minh | Đã có ô Live Search với kỹ thuật **Debounce 300ms** trong `js/app.js`, gọi API tìm theo tên, hoa chính, ý nghĩa. | **Hoàn thành 90%** | **P2** | *Cần cải thiện:* Chuẩn hóa tiếng Việt không dấu (vd: khách gõ "khai truong" vẫn tìm được "Khai Trương"). |
| **FE-05** | Bộ lọc đa chiều | Đã có bộ lọc kết hợp: Danh mục (slug) + Khoảng giá (4 nấc) + Tông màu hoa (6 màu) + Sắp xếp (giá, bán chạy, rating). | **Hoàn thành 100%** | **--** | Đang hoạt động rất mượt mà bằng PDO Prepared Statements. |
| **FE-06** | Tài khoản (Đăng ký, Đăng nhập, Session) | Đã có modal Auth chuyển tab, API `login.php`, `register.php`, `me.php`, `logout.php`, băm mật khẩu bcrypt, quản lý bằng PHP Session. | **Hoàn thành 95%** | **P2** | *Thiếu:* Chức năng Đổi mật khẩu và Cập nhật hồ sơ cá nhân (sửa số điện thoại, địa chỉ nhận hoa mặc định). |
| **FE-07** | Giỏ hàng (Cart Drawer) | Đã có Slide-drawer trượt từ phải sang, thêm, sửa số lượng, xóa món, lưu trữ liên tục qua `localStorage`. | **Hoàn thành 100%** | **--** | Hoạt động hoàn hảo, giữ nguyên. |
| **FE-08** | Băng rôn in vi tính (Ribbon Live Preview) | Đã có trong modal QuickView, gõ lời chúc là dải băng rôn màu đỏ hiển thị chữ vàng thời gian thực. | **Hoàn thành 100%** | **--** | Tính năng độc đáo cho tiệm hoa, giữ nguyên. |
| **FE-09** | Thanh toán (Checkout Flow) | Đã có modal Checkout, nhập thông tin người gửi/nhận, chọn giao ẩn danh, chọn ngày giờ giao, chọn VietQR/COD. | **Hoàn thành 80%** | **P0** | *Thiếu nghiêm trọng:* Chưa có ô nhập Voucher, chưa trừ tiền khuyến mãi trong tổng thanh toán. Cần tích hợp luồng kiểm tra Voucher từ backend. |
| **FE-10** | Theo dõi đơn hàng & Ảnh thực tế (Tracking) | Đã có API `api/tracking.php` và modal tra cứu tiến trình 5 bước, hiển thị ảnh hoa thật đã cắm (`photo_proof`). | **Hoàn thành 100%** | **--** | Hoạt động rất tốt, giữ nguyên. |
| **FE-11** | Voucher phía khách hàng | Hoàn toàn chưa có. Khách hàng không thể nhập mã voucher giảm giá khi mua hàng. | **Chưa có (0%)** | **P0** | *Cần làm:* Thêm ô nhập mã voucher tại Giỏ hàng và Checkout, gọi API `api/vouchers/apply.php` để validate và cập nhật tổng tiền hiển thị. |
| **FE-12** | Đánh giá sản phẩm (Reviews) sau mua | Đang là dữ liệu tĩnh trong `data.js`. Chưa lưu vào DB, khách chưa gửi đánh giá được. | **Chưa có (10%)** | **P2** | *Cần làm:* Tạo bảng `reviews` trong DB, API gửi review cho đơn hàng có trạng thái `completed`. |

---

### 1.2. Phân hệ 2: Phân Hệ Quản Trị (Admin Portal)

| Mã | Chức năng theo định hướng | Hiện trạng trong Source thực tế | Đánh giá | Mức ưu tiên | Khoảng trống & Giải pháp kỹ thuật |
|:---|:---|:---|:---:|:---:|:---|
| **AD-01** | Đăng nhập Quản trị & Phân quyền | Đã có `admin/login.php`, phân quyền bằng `role_name` ('admin', 'staff'), bảo vệ bằng hàm `require_admin_auth()`. | **Hoàn thành 100%** | **--** | Hoạt động an toàn, giữ nguyên. |
| **AD-02** | Dashboard tổng quan | Đã có trong `admin/index.php`, hiển thị 4 thẻ: Doanh thu thực tế, Tổng đơn hàng, Đơn cần cắm, Đơn đang giao qua `api/admin/stats.php`. | **Hoàn thành 70%** | **P2** | *Cần nâng cấp:* Biểu đồ doanh thu 7 ngày gần nhất, thống kê đơn theo trạng thái và cảnh báo sản phẩm sắp hết hàng trong kho. |
| **AD-03** | Quản lý Đơn hàng (Orders) | Đã có bảng hiển thị đơn hàng, modal đổi trạng thái 5 bước, đính kèm link ảnh cắm thực tế `photo_proof`. | **Hoàn thành 85%** | **P0** | *Lỗi nghiệp vụ:* Khi bấm "Hủy đơn" (`cancelled`), hệ thống không hoàn lại tồn kho cho sản phẩm và không hoàn lại lượt voucher. Cần sửa logic trong transaction. |
| **AD-04** | Quản lý Sản phẩm (Products CRUD) | Chưa có giao diện quản lý hoa tươi. Không thể thêm hoa, sửa giá, ẩn hiện hoa, sửa hoa thành phần qua web. | **Chưa có (0%)** | **P0** | *Cần làm ngay:* Tạo giao diện Quản lý Sản phẩm trong Admin, tạo API `api/admin/products.php` hỗ trợ đầy đủ GET, POST (thêm mới), PUT (cập nhật), DELETE (xóa mềm). |
| **AD-05** | Quản lý Danh mục (Categories CRUD) | Chưa có giao diện quản lý danh mục. | **Chưa có (0%)** | **P1** | *Cần làm:* Thêm giao diện quản lý danh mục hoa, API `api/admin/categories.php`. |
| **AD-06** | Quản lý Tồn kho & Nhập xuất (Inventory) | Chỉ có trừ tồn kho khi checkout (`stock_quantity`). Chưa có giao diện kiểm kho, chưa có lịch sử nhập hàng (`inventory_logs`). | **Chưa có (15%)** | **P1** | *Cần làm:* Tạo bảng `inventory_logs`, giao diện điều chỉnh tồn kho, cảnh báo hàng dưới ngưỡng tối thiểu. |
| **AD-07** | Quản lý Khách hàng (Customers) | Chỉ có dữ liệu trong bảng `users`. Chưa có trang quản trị xem danh sách khách hàng, lịch sử mua hàng, tổng chi tiêu. | **Chưa có (0%)** | **P1** | *Cần làm:* Tạo giao diện danh sách khách hàng, API `api/admin/customers.php`, chức năng khóa/mở tài khoản. |
| **AD-08** | Báo cáo Doanh thu & Lợi nhuận gộp | Chưa có báo cáo lợi nhuận thực tế (Doanh thu thuần - Giá vốn). | **Chưa có (0%)** | **P1** | *Cần làm:* Tích hợp công thức tính lợi nhuận gộp dựa trên trường `cost_price` mới bổ sung vào bảng `products`. |

---

### 1.3. Phân hệ 3: Phân Hệ Khuyến Mãi Thông Minh (Promotion Engine)

> [!IMPORTANT]
> Đây là phân hệ trọng tâm được người dùng chỉ định ưu tiên phát triển cao nhất, nhưng hiện tại trong mã nguồn thực tế **hoàn toàn chưa có bất kỳ bảng dữ liệu hay file mã nguồn nào**. Cần được thiết kế bài bản, thống nhất và bảo mật tuyệt đối.

| Mã | Chức năng theo định hướng | Hiện trạng Source | Đánh giá | Mức ưu tiên | Khoảng trống & Yêu cầu kỹ thuật chi tiết |
|:---|:---|:---:|:---:|:---:|:---|
| **PR-01** | Bộ máy khuyến mãi tập trung (Centralized Engine) | Không có | **Chưa có (0%)** | **P0** | Xây dựng `app/Services/PromotionService.php` làm đầu mối tính toán tập trung cho mọi loại giảm giá. Không viết phân mảnh ở từng file. |
| **PR-02** | Voucher thủ công (Promo Code) | Không có | **Chưa có (0%)** | **P0** | Tạo bảng `vouchers`, `voucher_usages`. Hỗ trợ giảm theo % hoặc số tiền cố định, giá trị đơn tối thiểu (`min_order_value`), mức giảm tối đa (`max_discount_amount`), giới hạn lượt dùng (`usage_limit`). |
| **PR-03** | Khuyến mãi theo Thời gian (Lịch tự động) | Không có | **Chưa có (0%)** | **P1** | Tạo bảng `campaigns`, `promotion_schedules`. Hỗ trợ lịch theo: ngày trong tháng (ngày 01-05 đầu tháng, ngày 10-15 giữa tháng, 25-30 cuối tháng); các dịp lễ (Valentine 14/02, 08/03, 20/10, 20/11, Tết); Flash Sale theo khung giờ. Toàn bộ múi giờ cấu hình `Asia/Ho_Chi_Minh`. |
| **PR-04** | Kiểm soát Biên Lợi Nhuận Tối Thiểu (Min Margin Guard) | Không có | **Chưa có (0%)** | **P0** | Bổ sung cột `cost_price` vào bảng `products`. Viết `app/Services/MarginService.php`. Khi áp dụng khuyến mãi, công thức phải đảm bảo: $\text{Biên lợi nhuận} = \frac{\text{Doanh thu thuần} - \text{Giá vốn}}{\text{Doanh thu thuần}} \ge \text{min\_margin}$. Nếu vi phạm, hệ thống tự động chặn mức giảm sâu hoặc từ chối kích hoạt chương trình. |
| **PR-05** | Cơ chế Xả Kho Thông Minh (Smart Inventory Clearance) | Không có | **Chưa có (0%)** | **P1** | Tính toán mức giảm giá tối đa cho sản phẩm tồn kho lâu ngày dựa trên: số lượng tồn, giá vốn, giá bán hiện tại và ngưỡng biên lợi nhuận tối thiểu được Admin phê duyệt. Không tự ý giảm giá sâu gây lỗ vốn. |
| **PR-06** | Kiểm soát Ngân Sách & Giới Hạn Lượt Dùng (Budget Guard) | Không có | **Chưa có (0%)** | **P0** | Tạo bảng `promotion_budget_logs`. Ghi nhận số tiền ngân sách đã tiêu hao sau mỗi đơn hàng thành công. Khi tổng số tiền giảm đạt `budget_limit`, chương trình tự động chuyển trạng thái `budget_exhausted` và ngừng áp dụng. |
| **PR-07** | Phê duyệt Chương trình (Approval Workflow) | Không có | **Chưa có (0%)** | **P1** | Trạng thái chiến dịch: `draft` -> `pending_approval` -> `approved` / `rejected` -> `active` -> `expired`. Chỉ có tài khoản role `admin` mới có quyền phê duyệt; nhân viên `staff` chỉ được tạo bản nháp. |
| **PR-08** | Hoàn trả Ngân sách & Lượt dùng khi Hủy đơn (Refund Logic) | Không có | **Chưa có (0%)** | **P0** | Khi đơn hàng bị hủy (`cancelled`), hệ thống phải hoàn lại 1 lượt dùng trong `vouchers` / `campaigns`, hoàn tiền vào ngân sách khả dụng và đánh dấu bản ghi sử dụng là `refunded`. |

---

## 2. BẢNG TỔNG HỢP DANH MỤC FILE CẦN THÊM VÀ FILE CẦN SỬA

### 2.1. File cần sửa đổi (Existing Files to Modify)
1. `database/schema.sql`: Bổ sung migration ALTER TABLE `products` ADD `cost_price`, và tạo 8 bảng mới.
2. `api/checkout.php`: Bổ sung tham số `voucherCode`, gọi `PromotionService` để tính toán giá giảm thực tế trong Transaction, khóa voucher bằng `FOR UPDATE`, ghi nhận vào `voucher_usages` và `promotion_budget_logs`.
3. `api/admin/orders.php`: Bổ sung logic hoàn trả tồn kho sản phẩm và hoàn trả lượt voucher/ngân sách khi đơn bị đổi sang `cancelled`.
4. `api/admin/stats.php`: Bổ sung thống kê chi phí giá vốn, lợi nhuận gộp và ngân sách khuyến mãi đã sử dụng.
5. `admin/index.php`: Bổ sung thanh điều hướng chuyển đổi giữa các phân hệ: Đơn hàng, Sản phẩm, Tồn kho, Khuyến mãi & Voucher, Khách hàng.
6. `index.html`: Thêm input nhập Voucher ở Giỏ hàng và Checkout Modal, thêm hiển thị dòng giảm giá, thêm banner Flash Sale/khuyến mãi trên trang chủ.
7. `js/app.js`: Thêm hàm gọi API `applyVoucher()`, cập nhật `AppState.appliedVoucher`, hiển thị chiết khấu tức thì.

### 2.2. File cần tạo mới hoàn toàn (New Files to Create)

#### Backend Services (`app/Services/`):
- `app/Services/PromotionService.php`: Xử lý tập trung logic kiểm tra, áp dụng khuyến mãi và voucher.
- `app/Services/MarginService.php`: Tính toán biên lợi nhuận, ngăn chặn vi phạm ngưỡng an toàn.
- `app/Services/ScheduleService.php`: Kiểm tra lịch kích hoạt tự động theo ngày/tháng/năm/dịp lễ.
- `app/Services/BudgetService.php`: Theo dõi và kiểm soát trần ngân sách chiến dịch.
- `app/Services/InventoryService.php`: Xử lý trừ kho, hoàn kho và ghi log nhập xuất.

#### REST API Endpoints mới (`api/`):
- `api/vouchers/check.php`: Kiểm tra tính hợp lệ của mã voucher trước khi đặt hàng.
- `api/vouchers/apply.php`: Tính toán số tiền được giảm tạm tính cho giỏ hàng.
- `api/promotions/active.php`: Trả về danh sách banner khuyến mãi và chiến dịch đang chạy ngoài trang chủ.
- `api/admin/products.php`: CRUD quản lý danh sách hoa tươi cho quản trị viên.
- `api/admin/categories.php`: CRUD danh mục hoa.
- `api/admin/inventory.php`: Kiểm kê tồn kho, điều chỉnh số lượng, xem lịch sử biến động.
- `api/admin/promotions.php`: Quản lý danh sách chiến dịch, phê duyệt, tạm dừng, kích hoạt.
- `api/admin/vouchers.php`: Tạo mới mã voucher, quản lý danh sách mã giảm giá.
- `api/admin/customers.php`: Xem danh sách khách hàng, thống kê đơn mua, khóa tài khoản.

#### Cron Jobs (`cron/`):
- `cron/activate_promotions.php`: Kích hoạt các chiến dịch đã được duyệt khi đến thời điểm bắt đầu.
- `cron/expire_promotions.php`: Kết thúc các chiến dịch hết hạn hoặc vượt ngân sách.
- `cron/inventory_check.php`: Quét sản phẩm tồn kho lâu ngày để đề xuất chiến dịch xả kho.

---

## 3. RỦI RO KỸ THUẬT & BIỆN PHÁP PHÒNG NGỪA

1. **Rủi ro Race Condition khi tranh chấp lượt dùng cuối cùng của Voucher:**
   - *Tình huống:* Hai khách hàng cùng nhập một voucher chỉ còn duy nhất 1 lượt sử dụng tại cùng một thời điểm.
   - *Phòng ngừa:* Sử dụng `SELECT usage_limit, used_count FROM vouchers WHERE code = :code FOR UPDATE` bên trong PDO Transaction. Khách hàng thứ hai sẽ bị từ chối ngay lập tức khi lượt sử dụng đã đạt giới hạn.
2. **Rủi ro Khuyến mãi chồng chéo (Discount Stacking):**
   - *Tình huống:* Sản phẩm đã được giảm giá theo sự kiện Flash Sale, khách hàng tiếp tục áp dụng thêm mã Voucher 30%, dẫn đến giá bán thấp hơn giá vốn.
   - *Phòng ngừa:* Luật kết hợp khuyến mãi trong `PromotionService`: Mỗi đơn hàng chỉ được hưởng mức chiết khấu tự động cao nhất HOẶC mã voucher (trừ khi chiến dịch được Admin cấu hình cờ `allow_stacking = 1`). Mọi kịch bản đều phải vượt qua hàm `MarginService::checkSafety()`.
3. **Rủi ro Sai lệch múi giờ Lịch sự kiện:**
   - *Tình huống:* Máy chủ server dùng múi giờ UTC, nhưng chiến dịch lễ tính theo giờ Việt Nam.
   - *Phòng ngừa:* Định nghĩa hằng số múi giờ bắt buộc `date_default_timezone_set('Asia/Ho_Chi_Minh')` trong file cấu hình lõi `config/helpers.php`.

