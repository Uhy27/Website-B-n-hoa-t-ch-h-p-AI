# BÁO CÁO PHÂN TÍCH VÀ ĐẶC TẢ TRIỂN KHAI KỸ THUẬT BLOOMORA 2.0

**Loại tài liệu:** Software Requirements Specification (SRS) & Technical Design Document (TDD)  
**Dự án:** Bloomora 2.0 – Hệ thống thương mại điện tử hoa tươi và sản phẩm trồng trọt  
**Phiên bản tài liệu:** 1.0  
**Mục tiêu:** Làm cơ sở xây dựng hệ thống phần mềm hoàn chỉnh, có thể kiểm thử, vận hành và mở rộng.

---

# PHẦN 1. TỔNG QUAN VÀ ĐỊNH HƯỚNG DỰ ÁN

## 1.1. Mục tiêu hệ thống

Bloomora 2.0 được định hướng là nền tảng thương mại điện tử chuyên biệt, kết hợp kinh doanh hoa tươi, hạt giống, combo DIY và các tính năng quản trị vận hành thông minh.

Hệ thống cần giải quyết đồng thời ba bài toán:

- **Thương mại điện tử:** Quản lý sản phẩm, khách hàng, giỏ hàng, đơn hàng, thanh toán và giao hàng.
- **Tự động hóa vận hành:** Quản lý tồn kho, lịch cắm hoa, khung giờ giao hàng, voucher và chăm sóc khách hàng.
- **Tính năng nâng cao:** Reverse Scheduling, Dutch Auction, Bloom Calendar, RFM và Lifecycle Marketing.

Mục tiêu cuối cùng không chỉ là xây dựng một website bán hàng mà là một hệ thống có khả năng kiểm soát quy trình kinh doanh, hạn chế sai sót và đưa ra quyết định dựa trên dữ liệu thực tế.

## 1.2. Phạm vi chức năng

Hệ thống được chia thành 3 nhóm chính:

**Nhóm A – Chức năng nền tảng**
1. Đăng ký, đăng nhập và phân quyền.
2. Quản lý danh mục và sản phẩm.
3. Quản lý kho và tồn kho.
4. Giỏ hàng và đặt hàng.
5. Thanh toán và quản lý giao hàng.
6. Quản lý khách hàng.
7. Quản trị nội dung và báo cáo.

**Nhóm B – Chức năng nghiệp vụ nâng cao**
1. Reverse Scheduling.
2. Voucher động và xả kho.
3. Bloom Calendar.
4. Quản lý chất lượng hoa và Photo Proof.
5. Quản lý combo DIY và lô hạt giống.
6. RFM và Lifecycle Marketing.

**Nhóm C – Chức năng mở rộng**
1. Dutch Auction.
2. Quản lý đối tác B2B.
3. Thống kê và phân tích hiệu quả kinh doanh.
4. Tự động hóa thông báo.
5. Dashboard giám sát vận hành.

## 1.3. Nguyên tắc phát triển

- Không để thuật toán tự đưa ra quyết định khi thiếu dữ liệu đầu vào.
- Không nhận đơn vượt quá năng lực thực tế của cửa hàng.
- Không cho phép voucher khiến đơn hàng vi phạm biên lợi nhuận tối thiểu.
- Không bán vượt tồn kho.
- Không cho phép hai người cùng thắng một lô đấu giá.
- Mọi thao tác quan trọng phải được ghi nhận lịch sử.
- Mọi chức năng tự động phải có cơ chế kiểm soát thủ công.
- Ưu tiên độ chính xác và khả năng vận hành trước khi mở rộng tính năng.

---

# PHẦN 2. PHÂN TÍCH YÊU CẦU HỆ THỐNG

## 2.1. Các nhóm người dùng

| Vai trò | Quyền hạn chính |
|---|---|
| Guest | Xem sản phẩm, tìm kiếm, xem thông tin |
| Customer | Mua hàng, thanh toán, theo dõi đơn |
| Admin | Quản lý toàn bộ hệ thống |
| Warehouse Staff | Quản lý kho, nhập xuất, kiểm kê |
| Florist | Tiếp nhận và xử lý đơn cắm hoa |
| Shipper | Nhận đơn và cập nhật giao hàng |
| Marketing | Quản lý voucher, chiến dịch, RFM |
| B2B Partner | Tham gia đấu giá và mua hàng theo lô |

Phân quyền phải được kiểm tra tại backend, không chỉ ẩn hoặc hiện giao diện frontend.

## 2.2. Danh sách module

| Mã | Module | Mức ưu tiên |
|---|---|---|
| M01 | Authentication & Authorization | P0 |
| M02 | Product Management | P0 |
| M03 | Inventory Management | P0 |
| M04 | Cart & Order Management | P0 |
| M05 | Payment & Shipping | P0 |
| M06 | Quality Control & Photo Proof | P1 |
| M07 | Reverse Scheduling | P1 |
| M08 | Dynamic Voucher | P1 |
| M09 | Seed & Bloom Calendar | P1 |
| M10 | RFM & Lifecycle | P2 |
| M11 | Dutch Auction | P2 |
| M12 | Analytics Dashboard | P1 |
| M13 | Notification System | P1 |
| M14 | Audit Log & Monitoring | P0 |

Trong đó:

- P0: Bắt buộc phải có để hệ thống vận hành.
- P1: Chức năng nâng cao cần triển khai sau nền tảng.
- P2: Chức năng mở rộng, chỉ triển khai khi dữ liệu và nghiệp vụ đã ổn định.

---

# PHẦN 3. KIẾN TRÚC HỆ THỐNG

## 3.1. Kiến trúc tổng thể

Đề xuất sử dụng Modular Monolith kết hợp MVC và REST API.

Đây là lựa chọn phù hợp hơn việc chia ngay thành nhiều microservice vì Bloomora cần một hệ thống dễ triển khai, dễ kiểm soát giao dịch và thuận tiện bảo trì.

Kiến trúc gồm các tầng:

1. Presentation Layer: Giao diện khách hàng và quản trị.
2. Controller Layer: Tiếp nhận và điều phối yêu cầu.
3. Service Layer: Xử lý nghiệp vụ.
4. Repository/Data Access Layer: Truy vấn dữ liệu.
5. Database Layer: Lưu trữ dữ liệu.
6. Infrastructure Layer: Thanh toán, thông báo, lưu trữ hình ảnh và các dịch vụ bên ngoài.

## 3.2. Sơ đồ kiến trúc

```text
                 BLOOMORA 2.0
                      |
          +-----------+-----------+
          |                       |
      CUSTOMER UI             ADMIN UI
          |                       |
          +-----------+-----------+
                      |
                 REST API
                      |
               MIDDLEWARE
          Authentication / RBAC
                      |
               CONTROLLERS
                      |
                SERVICES
                      |
     +----------------+----------------+
     |        |       |       |         |
   Product  Order   Stock  Voucher  Auction
     |        |       |       |         |
     +----------------+----------------+
                      |
                REPOSITORIES
                      |
                  DATABASE
                      |
        +-------------+-------------+
        |             |             |
      Storage       Cache       Scheduler
        |             |             |
      Images        Redis      Background Jobs
```

## 3.3. Nguyên tắc thiết kế

- Controller không chứa logic nghiệp vụ phức tạp.
- Service chịu trách nhiệm xử lý nghiệp vụ.
- Repository quản lý truy vấn dữ liệu.
- Database là nguồn dữ liệu chính xác.
- Các giao dịch liên quan tiền và tồn kho phải có transaction.
- Các tác vụ định kỳ phải có cơ chế chống chạy trùng.
- Các module giao tiếp với nhau thông qua service hoặc API nội bộ được định nghĩa rõ ràng.

## 3.4. Công nghệ đề xuất

Công nghệ dưới đây là phương án triển khai tham khảo, chưa phải yêu cầu đã được xác nhận từ mã nguồn hiện tại.

| Thành phần | Công nghệ đề xuất |
|---|---|
| Frontend | HTML, CSS, JavaScript hoặc Vue.js |
| Backend | PHP 8.2+ hoặc Laravel |
| Architecture | MVC / REST API |
| Database | MySQL 8 |
| Cache | Redis |
| Queue | Laravel Queue hoặc Worker |
| Image Storage | Local Storage / S3-compatible |
| Authentication | Session hoặc JWT |
| API Documentation | OpenAPI / Swagger |
| Testing | PHPUnit / Pest |
| Version Control | Git |

**Lưu ý:** Cần chốt framework, phiên bản PHP và cấu trúc source code trước khi bắt đầu viết code thực tế.

---

# PHẦN 4. THIẾT KẾ CHỨC NĂNG CHI TIẾT

## 4.1. Module Authentication & Authorization

### Mục tiêu

Quản lý tài khoản và quyền truy cập của từng nhóm người dùng.

### Chức năng

- Đăng ký tài khoản.
- Đăng nhập và đăng xuất.
- Quên mật khẩu.
- Đổi mật khẩu.
- Quản lý hồ sơ cá nhân.
- Quản lý vai trò.
- Phân quyền theo chức năng.

### Quy tắc nghiệp vụ

1. Email hoặc số điện thoại phải duy nhất theo chính sách định danh.
2. Mật khẩu phải được hash an toàn.
3. Tài khoản bị khóa không được đăng nhập.
4. Mọi API quản trị phải kiểm tra quyền.
5. Không lưu mật khẩu dạng văn bản thuần.

### API đề xuất

```text
POST   /api/auth/register
POST   /api/auth/login
POST   /api/auth/logout
POST   /api/auth/forgot-password
POST   /api/auth/reset-password
GET    /api/auth/profile
PUT    /api/auth/profile
```

## 4.2. Module Product Management

### Mục tiêu

Quản lý hai nhóm sản phẩm chính: hoa tươi và hạt giống.

### Dữ liệu sản phẩm

- Mã sản phẩm.
- Tên sản phẩm.
- Danh mục.
- Loại sản phẩm.
- Giá bán.
- Giá vốn.
- Mô tả.
- Hình ảnh.
- Trạng thái kinh doanh.
- Tồn kho.
- Thông tin bảo quản.
- Thông tin mùa vụ nếu là hạt giống.

### Quy tắc nghiệp vụ

- Không hiển thị sản phẩm ngừng kinh doanh.
- Không cho phép đặt hàng sản phẩm không đủ tồn.
- Không được tùy ý chỉnh sửa giá vốn.
- Mọi thay đổi giá phải được lưu lịch sử.
- Hạt giống phải có thông tin lô hàng, hạn sử dụng và ngày đóng gói.

### API đề xuất

```text
GET    /api/products
GET    /api/products/{id}
POST   /api/admin/products
PUT    /api/admin/products/{id}
DELETE /api/admin/products/{id}
GET    /api/categories
```

## 4.3. Module Inventory Management

### Mục tiêu

Kiểm soát chính xác tồn kho hoa tươi, hạt giống và các thành phần combo.

### Chức năng

- Nhập kho.
- Xuất kho.
- Điều chỉnh tồn kho.
- Kiểm kê.
- Theo dõi lô hàng.
- Cảnh báo tồn thấp.
- Theo dõi hạn sử dụng.
- Lịch sử biến động kho.

### Cấu trúc tồn kho

```text
PRODUCT
   |
   +--- INVENTORY
   |
   +--- INVENTORY_TRANSACTION
   |
   +--- INVENTORY_BATCH
   |
   +--- STOCK_RESERVATION
```

### Quy tắc quan trọng

Số lượng có thể bán phải được tính như sau:

```text
Available Stock =
On-hand Stock - Reserved Stock
```

Không được sử dụng tồn kho thực tế thay cho tồn kho khả dụng.

Khi khách đặt hàng:

1. Kiểm tra tồn kho khả dụng.
2. Tạo reservation.
3. Giữ hàng trong thời gian thanh toán.
4. Khi thanh toán thành công, chuyển reservation thành xuất kho.
5. Khi thanh toán thất bại hoặc hết thời gian giữ hàng, giải phóng reservation.

Tất cả các bước phải được thực hiện an toàn bằng database transaction và khóa dữ liệu phù hợp.

## 4.4. Module Order Management

### Mục tiêu

Quản lý toàn bộ vòng đời đơn hàng.

### Trạng thái đơn hàng

```text
PENDING
   |
   v
CONFIRMED
   |
   v
PROCESSING
   |
   v
QUALITY_CHECK
   |
   v
READY_TO_SHIP
   |
   v
SHIPPING
   |
   v
DELIVERED
```

Các trạng thái ngoại lệ:

```text
CANCELLED
PAYMENT_FAILED
DELIVERY_FAILED
REFUND_PENDING
REFUNDED
```

Không được cho phép chuyển trạng thái tùy ý. Mỗi chuyển trạng thái phải thỏa mãn điều kiện nghiệp vụ.

Ví dụ:

- Chưa thanh toán không được chuyển sang xử lý nếu đơn yêu cầu thanh toán trước.
- Chưa hoàn tất kiểm tra chất lượng không được bàn giao shipper.
- Đơn đã giao thành công không được hủy theo quy trình hủy đơn thông thường.

### API

```text
POST   /api/orders
GET    /api/orders/{id}
GET    /api/orders/my-orders
POST   /api/orders/{id}/cancel
PUT    /api/admin/orders/{id}/status
GET    /api/admin/orders
```

## 4.5. Module Quality Control & Photo Proof

### Mục tiêu

Đảm bảo hoa được giao đúng chất lượng và hạn chế tranh chấp.

### Quy trình

1. Florist tiếp nhận đơn.
2. Kiểm tra nguyên liệu.
3. Tiến hành cắm hoa.
4. Hoàn thành sản phẩm.
5. Chụp ảnh ba góc.
6. Lưu ảnh vào hệ thống.
7. Nhân viên kiểm tra chất lượng.
8. Xác nhận đạt hoặc yêu cầu làm lại.
9. Đóng gói và bàn giao vận chuyển.

### Checklist kiểm tra

| Tiêu chí | Kết quả |
|---|---|
| Hoa tươi | Pass/Fail |
| Không dập nát | Pass/Fail |
| Đúng thiết kế | Pass/Fail |
| Đúng màu sắc | Pass/Fail |
| Thiệp đúng nội dung | Pass/Fail |
| Bao bì đạt yêu cầu | Pass/Fail |
| Ảnh đầy đủ | Pass/Fail |

### Quy tắc

- Mỗi đơn chỉ được xác nhận đạt chất lượng khi hoàn thành checklist bắt buộc.
- Ảnh phải gắn với mã đơn.
- Không cho phép nhân viên tự ý thay hoa ngoài danh sách thay thế đã được khách chấp thuận.
- Lưu lại người kiểm tra và thời gian kiểm tra.

## 4.6. Module Reverse Scheduling

### Mục tiêu

Tính toán thời điểm bắt đầu chuẩn bị đơn để đảm bảo giao hàng trong khung giờ khách lựa chọn.

### Dữ liệu đầu vào

- Thời gian giao dự kiến.
- Loại thiết kế.
- Độ phức tạp của đơn.
- Thời gian chuẩn bị nguyên liệu.
- Thời gian cắm.
- Thời gian kiểm tra.
- Thời gian chụp ảnh.
- Thời gian đóng gói.
- Thời gian vận chuyển.
- Buffer theo điều kiện thực tế.

### Công thức

```text
Start Time =
Delivery Deadline
- Delivery Duration
- Packaging Duration
- QC Duration
- Photo Proof Duration
- Arrangement Duration
- Preparation Duration
- Buffer Duration
```

Ví dụ:

```text
Khung giờ giao: 10:00 - 11:00

Thời gian vận chuyển: 45 phút
Đóng gói:             10 phút
Kiểm tra:             10 phút
Photo Proof:           5 phút
Cắm hoa:              60 phút
Chuẩn bị:             15 phút
Buffer:               20 phút

Tổng thời gian:      165 phút
```

Hệ thống sẽ tính thời điểm bắt đầu dựa trên mốc giao hàng mục tiêu và các thành phần thời gian trên.

### Logic xử lý

```text
1. Nhận yêu cầu đặt hàng.
2. Xác định loại thiết kế.
3. Truy xuất thời gian xử lý tương ứng.
4. Kiểm tra năng lực florist.
5. Kiểm tra năng lực giao hàng.
6. Tính thời gian bắt đầu.
7. Kiểm tra slot còn khả dụng.
8. Nếu đủ năng lực: cho phép đặt hàng.
9. Nếu quá tải: từ chối hoặc đề xuất slot khác.
```

### Yêu cầu quan trọng

Không dùng một thời gian cắm cố định cho mọi sản phẩm.

Hệ thống phải lưu thời gian dự kiến và thời gian thực tế để điều chỉnh dự báo sau này.

Chức năng tự động tối ưu bằng dữ liệu lịch sử chỉ được triển khai khi đã thu thập đủ dữ liệu đáng tin cậy.

## 4.7. Module Dynamic Voucher

### Mục tiêu

Tự động tạo và áp dụng chương trình khuyến mãi nhưng vẫn bảo vệ biên lợi nhuận.

### Các loại voucher

1. Voucher theo ngày.
2. Voucher theo tháng.
3. Voucher theo năm.
4. Voucher dịp lễ.
5. Voucher sinh nhật.
6. Voucher khách hàng thân thiết.
7. Flash Sale.
8. Voucher xả kho.
9. Voucher dành riêng cho nhóm khách hàng.

### Quy tắc tính giảm giá

```text
Discount =
MIN(
    Calculated Discount,
    Maximum Discount
)
```

Giá trị thanh toán:

```text
Final Price =
Subtotal - Discount + Shipping Fee
```

Điều kiện áp dụng:

- Voucher đang hoạt động.
- Nằm trong thời gian hiệu lực.
- Sản phẩm thuộc danh mục áp dụng.
- Đơn đạt giá trị tối thiểu.
- Người dùng chưa vượt giới hạn sử dụng.
- Ngân sách chương trình còn khả dụng.
- Đơn hàng không vi phạm mức lợi nhuận tối thiểu.

### Quy tắc xả kho

Không áp dụng giảm giá tự động 40–50% cho toàn bộ sản phẩm sau lễ.

Hệ thống phải kiểm tra:

1. Tồn kho thực tế.
2. Chất lượng còn đủ điều kiện bán.
3. Giá vốn.
4. Chi phí vận hành.
5. Giá sàn.
6. Ngân sách khuyến mãi.
7. Thời gian còn khả dụng của sản phẩm.

Công thức kiểm soát:

```text
Estimated Profit =
Final Price
- Cost Price
- Variable Cost
```

Chỉ kích hoạt voucher khi lợi nhuận dự kiến đáp ứng mức tối thiểu được cấu hình.

Nếu không đạt điều kiện, hệ thống phải từ chối tạo voucher hoặc yêu cầu quản trị viên phê duyệt.

### Các kiểu lịch khuyến mãi

| Loại | Ví dụ |
|---|---|
| Theo ngày | 11/11, 20/10 |
| Theo tháng | Ngày đầu tháng |
| Theo năm | Chiến dịch thường niên |
| Theo mùa | Mùa xuân, mùa hè |
| Theo sự kiện | Valentine, 8/3, 20/10 |
| Theo tồn kho | Xả hàng theo SKU |

Ngày tháng phải được lưu theo múi giờ Asia/Ho_Chi_Minh và xử lý đúng khoảng thời gian hiệu lực.

## 4.8. Module Seed & Bloom Calendar

### Mục tiêu

Quản lý hạt giống và cung cấp hướng dẫn gieo trồng chính xác.

### Dữ liệu cần có

- Tên giống.
- Mã SKU.
- Mã lô.
- Ngày đóng gói.
- Hạn sử dụng.
- Tỷ lệ nảy mầm được kiểm nghiệm.
- Điều kiện bảo quản.
- Vùng khí hậu phù hợp.
- Tháng gieo trồng.
- Thời gian nảy mầm.
- Thời gian ra hoa.
- Hướng dẫn chăm sóc.

### Bloom Calendar

Lịch gieo trồng phải được cấu hình theo:

- Loại giống.
- Khu vực.
- Tháng.
- Điều kiện thời tiết.
- Điều kiện ánh sáng.
- Nhiệt độ phù hợp.

Không sử dụng một lịch gieo trồng chung cho tất cả vùng miền.

### QR hướng dẫn

Mỗi SKU hoặc lô hàng có thể liên kết với một trang hướng dẫn riêng.

Ví dụ:

```text
/products/seed-guides/{sku}
```

Trang hướng dẫn cần hỗ trợ:

- Hướng dẫn gieo.
- Hướng dẫn tưới nước.
- Hướng dẫn ánh sáng.
- Hướng dẫn chăm sóc.
- Các lỗi thường gặp.
- Video hướng dẫn.

## 4.9. Module RFM & Lifecycle Marketing

### Mục tiêu

Phân loại khách hàng dựa trên hành vi mua hàng.

### Ba chỉ số RFM

- Recency: Thời gian từ lần mua gần nhất.
- Frequency: Số lần mua hàng.
- Monetary: Tổng giá trị mua hàng.

### Cách tính

```text
R Score = Điểm theo lần mua gần nhất
F Score = Điểm theo tần suất mua
M Score = Điểm theo tổng chi tiêu
```

Sau đó:

```text
RFM Score = R Score + F Score + M Score
```

Các nhóm khách hàng:

| Nhóm | Chiến lược |
|---|---|
| Champions | Ưu đãi đặc biệt |
| Loyal Customers | Chăm sóc và giữ chân |
| Potential Loyalists | Khuyến khích mua lại |
| New Customers | Hướng dẫn sử dụng |
| At Risk | Kích hoạt quay lại |
| Lost Customers | Chiến dịch tái tiếp cận |

### Quy tắc thông báo

- Giới hạn tần suất.
- Có chức năng hủy đăng ký.
- Không gửi trùng chiến dịch.
- Không gửi ưu đãi khi không có mục tiêu.
- Phân biệt khách mua hoa và khách mua hạt giống.

## 4.10. Module Dutch Auction

### Mục tiêu

Tổ chức đấu giá giảm dần cho các lô sản phẩm dành cho đối tác B2B.

### Cơ chế

Giá khởi điểm giảm dần theo thời gian cho tới khi có người chấp nhận mua hoặc chạm giá sàn.

```text
Current Price =
MAX(
    Floor Price,
    Start Price - (Elapsed Time × Decrease Rate)
)
```

### Dữ liệu phiên đấu giá

- Mã phiên.
- Mã lô.
- Giá khởi điểm.
- Giá sàn.
- Tốc độ giảm giá.
- Thời điểm bắt đầu.
- Thời điểm kết thúc.
- Trạng thái.
- Người mua thắng.
- Giá chốt.
- Trạng thái thanh toán.

### Quy trình

```text
DRAFT
  |
  v
SCHEDULED
  |
  v
ACTIVE
  |
  +-------> SOLD
  |
  +-------> EXPIRED
  |
  +-------> CANCELLED
```

### Xử lý tranh chấp giao dịch

Đây là phần bắt buộc phải xử lý an toàn.

Khi có người bấm mua:

1. Kiểm tra phiên đang hoạt động.
2. Tính giá tại thời điểm giao dịch.
3. Khóa phiên và lô hàng trong transaction.
4. Kiểm tra lại tồn kho.
5. Ghi nhận người mua đầu tiên hợp lệ.
6. Tạo giao dịch thanh toán.
7. Nếu thanh toán thành công, xác nhận bán lô.
8. Nếu hết thời gian thanh toán, xử lý mở lại phiên theo chính sách.

Không xử lý chốt đấu giá bằng cách chỉ kiểm tra trạng thái ở frontend.

Phải sử dụng transaction và cơ chế khóa dữ liệu để tránh race condition.

---

# PHẦN 5. THIẾT KẾ CƠ SỞ DỮ LIỆU

## 5.1. Danh sách bảng đề xuất

### Nhóm người dùng

| Bảng | Chức năng |
|---|---|
| users | Tài khoản |
| roles | Vai trò |
| permissions | Quyền |
| role_permissions | Quan hệ vai trò và quyền |
| customer_profiles | Thông tin khách hàng |
| addresses | Địa chỉ giao hàng |

### Nhóm sản phẩm

| Bảng | Chức năng |
|---|---|
| categories | Danh mục |
| products | Sản phẩm |
| product_images | Hình ảnh |
| product_variants | Biến thể |
| seed_details | Thông tin hạt giống |
| seed_batches | Lô hạt giống |
| seed_guides | Hướng dẫn trồng |

### Nhóm kho

| Bảng | Chức năng |
|---|---|
| inventories | Tồn kho |
| inventory_batches | Lô hàng |
| inventory_transactions | Lịch sử kho |
| stock_reservations | Giữ hàng |
| combo_items | Thành phần combo |

### Nhóm đơn hàng

| Bảng | Chức năng |
|---|---|
| carts | Giỏ hàng |
| cart_items | Chi tiết giỏ hàng |
| orders | Đơn hàng |
| order_items | Chi tiết đơn |
| order_status_histories | Lịch sử trạng thái |
| payments | Thanh toán |
| shipments | Giao hàng |
| refunds | Hoàn tiền |

### Nhóm chất lượng và lịch giao

| Bảng | Chức năng |
|---|---|
| florist_profiles | Nhân viên cắm hoa |
| florist_schedules | Lịch làm việc |
| arrangement_tasks | Công việc cắm hoa |
| quality_checks | Kiểm tra chất lượng |
| photo_proofs | Ảnh xác nhận |
| delivery_slots | Khung giờ giao |
| scheduling_rules | Quy tắc tính lịch |

### Nhóm voucher

| Bảng | Chức năng |
|---|---|
| vouchers | Voucher |
| voucher_conditions | Điều kiện |
| voucher_products | Sản phẩm áp dụng |
| voucher_usages | Lịch sử sử dụng |
| promotion_campaigns | Chiến dịch |
| promotion_budgets | Ngân sách |

### Nhóm đấu giá

| Bảng | Chức năng |
|---|---|
| b2b_partners | Đối tác |
| auction_lots | Lô đấu giá |
| auctions | Phiên đấu giá |
| auction_winners | Người thắng |
| auction_transactions | Giao dịch |
| auction_events | Lịch sử sự kiện |

### Nhóm Marketing và hệ thống

| Bảng | Chức năng |
|---|---|
| customer_rfm | Điểm RFM |
| customer_segments | Nhóm khách |
| marketing_campaigns | Chiến dịch marketing |
| notifications | Thông báo |
| notification_logs | Lịch sử gửi |
| audit_logs | Nhật ký hệ thống |
| system_settings | Cấu hình |
| scheduled_jobs | Công việc định kỳ |

## 5.2. Nguyên tắc thiết kế database

- Sử dụng khóa chính và khóa ngoại rõ ràng.
- Đặt unique constraint cho mã sản phẩm, email và các định danh cần duy nhất.
- Tạo index cho các trường tìm kiếm thường xuyên.
- Không xóa dữ liệu giao dịch quan trọng bằng thao tác xóa cứng.
- Giá tiền sử dụng kiểu DECIMAL, không sử dụng FLOAT.
- Thời gian lưu thống nhất và hiển thị theo múi giờ Việt Nam.
- Các bảng lịch sử phải có thời gian và người thực hiện.
- Có cơ chế migration để quản lý thay đổi cấu trúc database.

**Lưu ý:** Đây là danh sách bảng logic cấp thiết kế. Trước khi lập trình cần hoàn thiện ERD, kiểu dữ liệu, cardinality, khóa ngoại và migration cho từng bảng.

---

# PHẦN 6. THIẾT KẾ API VÀ QUY TẮC NGHIỆP VỤ

## 6.1. Quy chuẩn API

Sử dụng REST API với cấu trúc:

```text
/api/v1/{module}/{resource}
```

Response thành công:

```json
{
  "success": true,
  "message": "Request successful",
  "data": {}
}
```

Response thất bại:

```json
{
  "success": false,
  "message": "Business rule violation",
  "error_code": "INSUFFICIENT_STOCK",
  "errors": {}
}
```

## 6.2. Danh sách API theo module

| Module | API chính |
|---|---|
| Auth | /auth/login, /auth/register |
| Product | /products, /categories |
| Inventory | /inventory, /inventory/transactions |
| Order | /orders, /orders/{id} |
| Scheduling | /scheduling/slots, /scheduling/calculate |
| Quality | /quality/checks, /quality/photo-proofs |
| Voucher | /vouchers/validate, /vouchers/apply |
| Seeds | /seeds, /seeds/calendar |
| RFM | /marketing/rfm, /marketing/segments |
| Auction | /auctions, /auctions/{id}/buy |
| Dashboard | /admin/dashboard |

Mọi API quản trị phải được bảo vệ bằng xác thực và phân quyền.

## 6.3. Quy chuẩn xử lý lỗi

Các mã lỗi nghiệp vụ cần thống nhất:

| Mã | Ý nghĩa |
|---|---|
| AUTH_001 | Chưa đăng nhập |
| AUTH_002 | Không đủ quyền |
| STOCK_001 | Không đủ tồn kho |
| STOCK_002 | Sản phẩm đã hết hàng |
| ORDER_001 | Trạng thái đơn không hợp lệ |
| ORDER_002 | Không thể hủy đơn |
| SLOT_001 | Khung giờ đã đầy |
| VOUCHER_001 | Voucher hết hạn |
| VOUCHER_002 | Không đủ điều kiện |
| VOUCHER_003 | Vượt ngân sách |
| AUCTION_001 | Phiên đấu giá đã kết thúc |
| AUCTION_002 | Lô hàng đã có người mua |

---

# PHẦN 7. BẢO MẬT VÀ TÍNH TOÀN VẸN DỮ LIỆU

## 7.1. Bảo mật

Các yêu cầu bắt buộc:

1. Hash mật khẩu bằng thuật toán phù hợp như Argon2id hoặc bcrypt.
2. Sử dụng prepared statements hoặc ORM an toàn.
3. Chống SQL Injection.
4. Chống XSS.
5. Chống CSRF đối với các luồng sử dụng session.
6. Kiểm tra quyền truy cập ở backend.
7. Giới hạn số lần đăng nhập.
8. Xác thực dữ liệu upload.
9. Không để lộ thông tin nhạy cảm trong log.
10. Sử dụng HTTPS khi triển khai thực tế.

## 7.2. Bảo vệ giao dịch

Các nghiệp vụ phải sử dụng transaction:

- Đặt hàng và giữ tồn kho.
- Thanh toán và cập nhật đơn hàng.
- Hoàn tiền.
- Sử dụng voucher.
- Chốt đấu giá.
- Xuất kho.
- Chuyển trạng thái quan trọng.

Cần bổ sung idempotency key cho các thao tác thanh toán và giao dịch có nguy cơ gửi lại nhiều lần.

Webhook thanh toán phải được xác thực chữ ký và chống xử lý trùng.

## 7.3. Audit Log

Các hành động phải được ghi log:

- Thay đổi giá.
- Điều chỉnh tồn kho.
- Tạo và chỉnh sửa voucher.
- Thay đổi khung giờ.
- Hủy đơn.
- Hoàn tiền.
- Mở hoặc đóng phiên đấu giá.
- Thay đổi quyền người dùng.

---

# PHẦN 8. KIỂM THỬ VÀ TIÊU CHÍ NGHIỆM THU

## 8.1. Các loại kiểm thử

**Unit Testing**

Kiểm tra từng hàm nghiệp vụ:

- Tính giá voucher.
- Tính lợi nhuận.
- Tính tồn kho khả dụng.
- Tính thời gian Reverse Scheduling.
- Tính giá đấu giá.
- Tính điểm RFM.

**Integration Testing**

Kiểm tra sự phối hợp:

- Đơn hàng và tồn kho.
- Đơn hàng và thanh toán.
- Voucher và đơn hàng.
- Scheduling và florist.
- Auction và thanh toán.

**End-to-End Testing**

Kiểm thử toàn bộ hành trình:

```text
Khách hàng
    |
    v
Chọn sản phẩm
    |
    v
Thêm giỏ hàng
    |
    v
Áp dụng voucher
    |
    v
Chọn giờ giao
    |
    v
Thanh toán
    |
    v
Florist xử lý
    |
    v
Kiểm tra chất lượng
    |
    v
Giao hàng
    |
    v
Hoàn thành đơn
```

## 8.2. Các tình huống bắt buộc phải kiểm thử

| STT | Tình huống | Kết quả mong đợi |
|---|---|---|
| 1 | Hai khách mua sản phẩm cuối cùng | Chỉ một đơn giữ được hàng |
| 2 | Voucher hết hạn | Không áp dụng |
| 3 | Voucher vượt ngân sách | Từ chối |
| 4 | Đơn vượt năng lực florist | Không nhận slot |
| 5 | Florist không hoàn thành đúng giờ | Có cảnh báo |
| 6 | Thiếu ảnh Photo Proof | Không hoàn tất kiểm tra |
| 7 | Hai người cùng chốt đấu giá | Chỉ một người thắng |
| 8 | Thanh toán gửi webhook hai lần | Không ghi nhận trùng |
| 9 | Hủy đơn sau khi giữ hàng | Giải phóng tồn kho đúng |
| 10 | Hạt giống hết hạn | Không được bán |
| 11 | Nhân viên không có quyền sửa giá | Bị từ chối |
| 12 | Gửi thông báo trùng | Hệ thống ngăn chặn |

## 8.3. Tiêu chí nghiệm thu

- Tất cả chức năng P0 hoạt động đúng.
- Không xảy ra bán vượt tồn kho trong kiểm thử đồng thời.
- Không xảy ra áp dụng voucher trái điều kiện.
- Không cho phép hai người thắng cùng một lô đấu giá.
- Mọi trạng thái đơn hàng chuyển theo đúng quy trình.
- Dữ liệu giao dịch có khả năng truy vết.
- Các chức năng có kiểm thử tự động.
- Không còn lỗi nghiêm trọng hoặc lỗi bảo mật mức cao trước khi phát hành.

---

# PHẦN 9. KẾ HOẠCH TRIỂN KHAI

## 9.1. Giai đoạn 1 – Phân tích và chuẩn bị

Công việc:

- Chốt yêu cầu.
- Xác định công nghệ.
- Thiết kế ERD.
- Thiết kế kiến trúc.
- Thiết kế API.
- Tạo repository Git.
- Thiết lập môi trường phát triển.

Kết quả: Tài liệu thiết kế và project skeleton.

## 9.2. Giai đoạn 2 – Xây dựng nền tảng

Triển khai:

- Authentication.
- Phân quyền.
- Product Management.
- Inventory Management.
- Cart.
- Order Management.
- Payment.
- Shipping.

Kết quả: Website có thể thực hiện một quy trình mua hàng cơ bản.

## 9.3. Giai đoạn 3 – Vận hành hoa tươi

Triển khai:

- Florist Management.
- Quality Control.
- Photo Proof.
- Delivery Slots.
- Reverse Scheduling.

Kết quả: Có thể quản lý quy trình tiếp nhận, cắm, kiểm tra và giao hoa.

## 9.4. Giai đoạn 4 – Khuyến mãi và hạt giống

Triển khai:

- Dynamic Voucher.
- Flash Sale.
- Voucher theo ngày, tháng, năm.
- Xả kho.
- Seed Management.
- Bloom Calendar.
- QR Guide.

Kết quả: Hoàn thiện hệ thống bán hàng và quản lý sản phẩm trồng trọt.

## 9.5. Giai đoạn 5 – Marketing và đấu giá

Triển khai:

- RFM.
- Customer Segmentation.
- Lifecycle Marketing.
- Notification.
- B2B Partner.
- Dutch Auction.

Kết quả: Hoàn thiện các tính năng kinh doanh nâng cao.

## 9.6. Giai đoạn 6 – Kiểm thử và phát hành

- Kiểm thử chức năng.
- Kiểm thử bảo mật.
- Kiểm thử tải.
- Kiểm thử giao dịch đồng thời.
- Kiểm tra database.
- Tối ưu hiệu năng.
- Triển khai production.
- Thiết lập backup.
- Giám sát hệ thống.

---

# PHẦN 10. CẤU TRÚC SOURCE CODE ĐỀ XUẤT

Nếu sử dụng PHP MVC, có thể tổ chức dự án như sau:

```text
bloomora/
│
├── app/
│   ├── Controllers/
│   │   ├── AuthController.php
│   │   ├── ProductController.php
│   │   ├── OrderController.php
│   │   ├── InventoryController.php
│   │   ├── VoucherController.php
│   │   ├── SchedulingController.php
│   │   ├── AuctionController.php
│   │   └── AdminController.php
│   │
│   ├── Models/
│   │   ├── User.php
│   │   ├── Product.php
│   │   ├── Order.php
│   │   ├── Inventory.php
│   │   ├── Voucher.php
│   │   ├── Auction.php
│   │   └── SeedBatch.php
│   │
│   ├── Services/
│   │   ├── AuthService.php
│   │   ├── OrderService.php
│   │   ├── InventoryService.php
│   │   ├── VoucherService.php
│   │   ├── SchedulingService.php
│   │   ├── QualityService.php
│   │   ├── RFMService.php
│   │   └── AuctionService.php
│   │
│   ├── Repositories/
│   ├── Middleware/
│   ├── Validators/
│   ├── Policies/
│   └── Helpers/
│
├── config/
├── database/
│   ├── migrations/
│   ├── seeders/
│   └── schema/
│
├── public/
│   ├── assets/
│   ├── uploads/
│   └── index.php
│
├── resources/
│   ├── views/
│   │   ├── customer/
│   │   ├── admin/
│   │   ├── florist/
│   │   └── b2b/
│   │
│   ├── css/
│   ├── js/
│   └── images/
│
├── routes/
│   ├── web.php
│   └── api.php
│
├── storage/
│   ├── logs/
│   ├── cache/
│   └── private/
│
├── tests/
│   ├── Unit/
│   ├── Integration/
│   └── Feature/
│
├── .env.example
├── composer.json
└── README.md
```

Đây là cấu trúc tham khảo. Nếu dự án đã có source code thì phải phân tích và điều chỉnh theo cấu trúc hiện hữu, tránh tạo lại các module đã có.

---

# PHẦN 11. QUY TRÌNH XÂY DỰNG CODE HOÀN CHỈNH

Đây là phần quan trọng nhất khi chuyển từ báo cáo sang lập trình thực tế.

Không nên yêu cầu AI hoặc lập trình viên viết toàn bộ dự án trong một lần. Cách đó dễ tạo ra code trùng lặp, thiếu liên kết database và nhiều chức năng chỉ hoạt động trên giao diện.

Thay vào đó, triển khai theo quy trình sau:

**Bước 1 – Audit source code**

- Kiểm tra toàn bộ thư mục.
- Đọc các file cấu hình.
- Kiểm tra database.
- Kiểm tra các chức năng đã có.
- Xác định lỗi và phần còn thiếu.
- Không tự ý xóa hoặc ghi đè code đang hoạt động.

**Bước 2 – Chuẩn hóa kiến trúc**

- Chốt công nghệ.
- Hoàn thiện cấu trúc MVC.
- Thiết lập routing.
- Thiết lập database connection.
- Thiết lập middleware.
- Chuẩn hóa response API.

**Bước 3 – Xây dựng database**

- Tạo ERD.
- Tạo migration.
- Tạo khóa ngoại.
- Tạo index.
- Tạo dữ liệu mẫu.
- Kiểm thử quan hệ dữ liệu.

**Bước 4 – Lập trình backend**

Mỗi module phải có:

- Model.
- Repository nếu cần.
- Service.
- Controller.
- Validation.
- Authorization.
- API.
- Xử lý ngoại lệ.
- Unit test.

**Bước 5 – Lập trình frontend**

- Xây dựng giao diện.
- Kết nối API.
- Loading state.
- Empty state.
- Error state.
- Form validation.
- Responsive.
- Phân quyền hiển thị.

**Bước 6 – Tích hợp nghiệp vụ**

Đảm bảo các module thực sự hoạt động với nhau, không phải những chức năng độc lập chỉ có giao diện.

**Bước 7 – Kiểm thử**

- Kiểm thử nghiệp vụ.
- Kiểm thử dữ liệu.
- Kiểm thử đồng thời.
- Kiểm thử bảo mật.
- Kiểm thử hiệu năng.

**Bước 8 – Hoàn thiện và bàn giao**

- README.
- Hướng dẫn cài đặt.
- Hướng dẫn cấu hình.
- Database migration.
- Tài khoản thử nghiệm.
- Tài liệu API.
- Bộ test.
- Hướng dẫn triển khai.

---

# PHẦN 12. KẾT LUẬN VÀ ĐỊNH HƯỚNG PHÁT TRIỂN

Bloomora 2.0 có thể được triển khai thành một hệ thống thương mại điện tử hoàn chỉnh nếu các tính năng được xây dựng trên nền tảng dữ liệu chính xác và quy trình nghiệp vụ thống nhất.

Thứ tự ưu tiên cần được giữ nguyên:

1. Xây dựng nền tảng bán hàng và quản lý dữ liệu.
2. Hoàn thiện quản lý tồn kho và đơn hàng.
3. Hoàn thiện quy trình chất lượng và giao hoa.
4. Triển khai Reverse Scheduling.
5. Triển khai Dynamic Voucher và quản lý hạt giống.
6. Triển khai RFM và Lifecycle Marketing.
7. Thử nghiệm Dutch Auction với nhóm đối tác B2B giới hạn.

Không nên triển khai các thuật toán nâng cao trước khi có dữ liệu vận hành đủ tin cậy.

**Tiêu chuẩn hoàn thành cuối cùng:**

Bloomora 2.0 chỉ được xem là hoàn thiện khi các chức năng không chỉ chạy độc lập mà còn liên kết được với nhau, dữ liệu nhất quán, giao dịch an toàn, có khả năng xử lý lỗi và có thể kiểm thử lại.

Mục tiêu không phải là tạo ra một website có nhiều chức năng, mà là xây dựng một hệ thống có thể vận hành được trong điều kiện kinh doanh thực tế.
