<?php
/**
 * BLOOMORA ADMIN DASHBOARD - BẢO VỆ PHIÊN ĐĂNG NHẬP
 */
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/database.php';

init_secure_session();

// Kiểm tra quyền Admin/Staff
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_name'] ?? '', ['admin', 'staff'])) {
    header('Location: login.php');
    exit;
}

$adminName = $_SESSION['full_name'] ?? 'Quản Trị Viên';
$adminRole = $_SESSION['role_name'] === 'admin' ? 'Quản Trị Viên' : 'Nghệ Nhân Florist';
$adminEmail = $_SESSION['email'] ?? 'admin@bloomora.vn';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Bloomora Admin | Quản Trị Hệ Thống Hoa Tươi</title>
  
  <!-- Google Fonts Chuẩn Tiếng Việt -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">

  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/@phosphor-icons/web"></script>
  <link rel="stylesheet" href="../css/style.css">
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            rose: {
              50: '#fff1f2',
              100: '#ffe4e6',
              500: '#f43f5e',
              600: '#e11d48',
              700: '#be123c',
              800: '#9f1239',
            }
          }
        }
      }
    }
  </script>
</head>
<body class="bg-stone-100 text-stone-800 antialiased min-h-screen flex flex-col">

  <!-- TOP HEADER ADMIN -->
  <header class="bg-stone-900 text-white border-b border-stone-800 sticky top-0 z-30">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
      <div class="flex items-center space-x-3">
        <a href="index.php" class="flex items-center space-x-2">
          <div class="w-9 h-9 rounded-xl bg-rose-600 flex items-center justify-center text-white">
            <i class="ph ph-flower-lotus text-xl"></i>
          </div>
          <span class="font-serif-title text-xl font-bold tracking-wide">BLOOMORA ADMIN</span>
        </a>
        <span class="text-[11px] bg-rose-900/60 text-rose-300 px-2.5 py-0.5 rounded-full font-semibold border border-rose-700/50">v1.2 Secure</span>
      </div>

      <div class="flex items-center space-x-3 sm:space-x-4">
        <a href="../index.html" class="flex items-center space-x-1.5 text-xs text-stone-300 hover:text-white bg-stone-800 px-3 py-1.5 rounded-lg border border-stone-700 transition">
          <i class="ph ph-arrow-square-out text-sm"></i>
          <span>Xem Cửa Hàng</span>
        </a>

        <!-- Thông tin Admin đang đăng nhập -->
        <div class="flex items-center space-x-2 text-xs">
          <div class="w-8 h-8 rounded-full bg-rose-600 text-white font-bold flex items-center justify-center text-xs shadow">
            <?php echo mb_substr($adminName, 0, 1); ?>
          </div>
          <div class="hidden sm:block text-left">
            <span class="block font-semibold text-white leading-tight"><?php echo htmlspecialchars($adminName); ?></span>
            <span class="text-[10px] text-rose-400 block"><?php echo htmlspecialchars($adminRole); ?></span>
          </div>
        </div>

        <!-- Nút Đăng Xuất -->
        <button 
          onclick="handleAdminLogout()"
          class="flex items-center space-x-1 text-xs text-stone-400 hover:text-rose-400 bg-stone-800 hover:bg-stone-700/80 px-2.5 py-1.5 rounded-lg border border-stone-700 transition"
          title="Đăng xuất khỏi trang quản trị"
        >
          <i class="ph ph-sign-out text-sm"></i>
          <span class="hidden md:inline">Đăng Xuất</span>
        </button>
      </div>
    </div>
  </header>

  <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full space-y-6">
    
    <!-- NAVIGATION TABS -->
    <div class="flex items-center space-x-2 border-b border-stone-200 pb-3">
      <button 
        type="button" 
        id="tabBtnOrders" 
        onclick="switchAdminTab('orders')"
        class="px-5 py-2.5 rounded-xl font-bold text-xs bg-rose-600 text-white shadow-sm flex items-center space-x-2 transition"
      >
        <i class="ph ph-shopping-cart text-base"></i>
        <span>Quản Lý Đơn Hàng</span>
      </button>

      <button 
        type="button" 
        id="tabBtnReviews" 
        onclick="switchAdminTab('reviews')"
        class="px-5 py-2.5 rounded-xl font-bold text-xs bg-white text-stone-600 hover:text-stone-900 border border-stone-200 hover:bg-stone-50 transition flex items-center space-x-2"
      >
        <i class="ph ph-star text-base text-amber-500"></i>
        <span>Quản Lý Đánh Giá & Ảnh Thực Tế</span>
        <span id="adminPendingBadge" class="hidden bg-amber-500 text-white text-[10px] px-2 py-0.5 rounded-full font-extrabold animate-pulse">0</span>
      </button>
    </div>

    <!-- ================= TAB 1: ORDERS SECTION ================= -->
    <div id="ordersSectionWrap" class="space-y-8">
      <!-- STATS CARDS (AD02) -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
      <!-- Doanh thu -->
      <div class="bg-white p-5 rounded-2xl border border-stone-200/80 shadow-sm flex items-center justify-between">
        <div>
          <span class="text-xs text-stone-400 font-semibold block mb-1">Tổng Doanh Thu</span>
          <h3 id="statRevenue" class="text-2xl font-bold text-stone-900 font-serif-title">-- ₫</h3>
          <span class="text-[11px] text-emerald-600 font-medium">↑ Doanh thu thực tế</span>
        </div>
        <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-2xl">
          <i class="ph ph-money"></i>
        </div>
      </div>

      <!-- Tổng đơn -->
      <div class="bg-white p-5 rounded-2xl border border-stone-200/80 shadow-sm flex items-center justify-between">
        <div>
          <span class="text-xs text-stone-400 font-semibold block mb-1">Tổng Đơn Hàng</span>
          <h3 id="statTotalOrders" class="text-2xl font-bold text-stone-900">--</h3>
          <span class="text-[11px] text-stone-500">Cả khách vãng lai & TV</span>
        </div>
        <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-2xl">
          <i class="ph ph-shopping-cart"></i>
        </div>
      </div>

      <!-- Đang xử lý / Cắm hoa -->
      <div class="bg-white p-5 rounded-2xl border border-stone-200/80 shadow-sm flex items-center justify-between">
        <div>
          <span class="text-xs text-stone-400 font-semibold block mb-1">Đơn Cần Cắm & Chụp Ảnh</span>
          <h3 id="statPendingOrders" class="text-2xl font-bold text-amber-600">--</h3>
          <span class="text-[11px] text-amber-600 font-medium">Đang trong tiến trình</span>
        </div>
        <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-2xl">
          <i class="ph ph-flower"></i>
        </div>
      </div>

      <!-- Đang giao -->
      <div class="bg-white p-5 rounded-2xl border border-stone-200/80 shadow-sm flex items-center justify-between">
        <div>
          <span class="text-xs text-stone-400 font-semibold block mb-1">Shipper Đang Giao</span>
          <h3 id="statDeliveringOrders" class="text-2xl font-bold text-rose-600">--</h3>
          <span class="text-[11px] text-rose-600 font-medium">Hỏa tốc nội thành</span>
        </div>
        <div class="w-12 h-12 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center text-2xl">
          <i class="ph ph-truck"></i>
        </div>
      </div>
    </div>

    <!-- ORDER MANAGEMENT SECTION (AD08) -->
    <div class="bg-white rounded-3xl border border-stone-200/80 shadow-sm overflow-hidden">
      <div class="p-5 sm:p-6 border-b border-stone-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <h2 class="text-lg font-bold text-stone-900 font-serif-title">Quản Lý Đơn Hàng & Tiến Trình Cắm Hoa</h2>
          <p class="text-xs text-stone-400">Xem đơn hàng, cập nhật 5 bước trạng thái và đính kèm ảnh hoa thực tế</p>
        </div>

        <!-- Bộ lọc trạng thái đơn -->
        <div class="flex items-center space-x-2 text-xs">
          <select id="statusFilter" onchange="loadAdminOrders()" class="bg-stone-50 border border-stone-200 rounded-xl px-3 py-2 text-stone-700 font-medium focus:ring-1 focus:ring-rose-500 focus:outline-none">
            <option value="all">Tất cả trạng thái</option>
            <option value="received">1. Đã tiếp nhận</option>
            <option value="preparing">2. Đang cắm hoa</option>
            <option value="photo_ready">3. Đã chụp ảnh duyệt</option>
            <option value="delivering">4. Đang giao hàng</option>
            <option value="completed">5. Đã hoàn thành</option>
            <option value="cancelled">Đã hủy</option>
          </select>
          <button onclick="loadAdminOrders()" class="p-2 bg-stone-100 hover:bg-stone-200 rounded-xl text-stone-600 transition" title="Làm mới">
            <i class="ph ph-arrows-clockwise text-base"></i>
          </button>
        </div>
      </div>

      <!-- BẢNG DANH SÁCH ĐƠN HÀNG -->
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-stone-50 border-b border-stone-200 text-stone-500 uppercase tracking-wider font-semibold">
              <th class="py-3.5 px-4">Mã Đơn</th>
              <th class="py-3.5 px-4">Người Đặt & Nhận</th>
              <th class="py-3.5 px-4">Địa Chỉ & Hẹn Giờ</th>
              <th class="py-3.5 px-4">Sản Phẩm</th>
              <th class="py-3.5 px-4">Lời Chúc / Băng Rôn</th>
              <th class="py-3.5 px-4">Số Tiền</th>
              <th class="py-3.5 px-4">Trạng Thái</th>
              <th class="py-3.5 px-4">Ảnh Hoa Duyệt</th>
              <th class="py-3.5 px-4 text-right">Thao Tác</th>
            </tr>
          </thead>
          <tbody id="ordersTableBody" class="divide-y divide-stone-100">
            <tr>
              <td colspan="9" class="text-center py-10 text-stone-400">Đang tải dữ liệu đơn hàng...</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
    </div>
    <!-- END TAB 1: ORDERS SECTION -->

    <!-- ================= TAB 2: REVIEWS SECTION ================= -->
    <div id="reviewsSectionWrap" class="space-y-8 hidden">
      <!-- STATS CARDS FOR REVIEWS -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <!-- Tổng review -->
        <div class="bg-white p-5 rounded-2xl border border-stone-200/80 shadow-sm flex items-center justify-between">
          <div>
            <span class="text-xs text-stone-400 font-semibold block mb-1">Tổng Lượt Đánh Giá</span>
            <h3 id="statRevTotal" class="text-2xl font-bold text-stone-900 font-serif-title">--</h3>
            <span class="text-[11px] text-stone-500 font-medium">Toàn hệ thống</span>
          </div>
          <div class="w-12 h-12 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-2xl">
            <i class="ph ph-chat-circle-dots"></i>
          </div>
        </div>

        <!-- Chờ duyệt (Pending) -->
        <div class="bg-white p-5 rounded-2xl border border-stone-200/80 shadow-sm flex items-center justify-between">
          <div>
            <span class="text-xs text-stone-400 font-semibold block mb-1">Đánh Giá Chờ Duyệt</span>
            <h3 id="statRevPending" class="text-2xl font-bold text-amber-600 font-serif-title">--</h3>
            <span class="text-[11px] text-amber-600 font-medium">Cần kiểm duyệt ngay</span>
          </div>
          <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-2xl">
            <i class="ph ph-hourglass-high"></i>
          </div>
        </div>

        <!-- Đã duyệt (Approved) -->
        <div class="bg-white p-5 rounded-2xl border border-stone-200/80 shadow-sm flex items-center justify-between">
          <div>
            <span class="text-xs text-stone-400 font-semibold block mb-1">Đã Duyệt Công Khai</span>
            <h3 id="statRevApproved" class="text-2xl font-bold text-emerald-600 font-serif-title">--</h3>
            <span class="text-[11px] text-emerald-600 font-medium">Đang hiển thị trên web</span>
          </div>
          <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-2xl">
            <i class="ph ph-check-circle"></i>
          </div>
        </div>

        <!-- Từ chối / Ẩn -->
        <div class="bg-white p-5 rounded-2xl border border-stone-200/80 shadow-sm flex items-center justify-between">
          <div>
            <span class="text-xs text-stone-400 font-semibold block mb-1">Bị Từ Chối / Đang Ẩn</span>
            <h3 id="statRevRejected" class="text-2xl font-bold text-rose-600 font-serif-title">--</h3>
            <span class="text-[11px] text-rose-600 font-medium">Vi phạm hoặc tạm ẩn</span>
          </div>
          <div class="w-12 h-12 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center text-2xl">
            <i class="ph ph-prohibit"></i>
          </div>
        </div>
      </div>

      <!-- REVIEW MANAGEMENT TABLE CARD -->
      <div class="bg-white rounded-3xl border border-stone-200/80 shadow-sm overflow-hidden">
        <div class="p-5 sm:p-6 border-b border-stone-100 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
          <div>
            <h2 class="text-lg font-bold text-stone-900 font-serif-title">Kiểm Duyệt & Phản Hồi Đánh Giá</h2>
            <p class="text-xs text-stone-400">Xác minh đơn hàng, duyệt hình ảnh thực tế và gửi phản hồi chính thức từ Bloomora</p>
          </div>

          <!-- Toolbar bộ lọc review -->
          <div class="flex flex-wrap items-center gap-2 text-xs w-full md:w-auto">
            <!-- Tìm kiếm -->
            <div class="relative flex-1 sm:w-60">
              <input type="text" id="searchReviewInput" onkeyup="if(event.key==='Enter') loadAdminReviews(1)" placeholder="Tìm theo tên khách, hoa, mã đơn..."
                class="w-full bg-stone-50 border border-stone-200 rounded-xl pl-8 pr-3 py-2 text-stone-800 placeholder-stone-400 focus:ring-1 focus:ring-rose-500 focus:outline-none" />
              <i class="ph ph-magnifying-glass absolute left-2.5 top-2.5 text-stone-400"></i>
            </div>

            <!-- Lọc trạng thái -->
            <select id="statusReviewFilter" onchange="loadAdminReviews(1)" class="bg-stone-50 border border-stone-200 rounded-xl px-3 py-2 text-stone-700 font-medium focus:ring-1 focus:ring-rose-500 focus:outline-none">
              <option value="all">Tất cả trạng thái</option>
              <option value="pending">⏳ Chờ duyệt</option>
              <option value="approved">✓ Đã duyệt</option>
              <option value="hidden">👁️ Đang ẩn</option>
              <option value="rejected">✕ Bị từ chối</option>
            </select>

            <!-- Lọc số sao -->
            <select id="ratingReviewFilter" onchange="loadAdminReviews(1)" class="bg-stone-50 border border-stone-200 rounded-xl px-3 py-2 text-stone-700 font-medium focus:ring-1 focus:ring-rose-500 focus:outline-none">
              <option value="all">Tất cả số sao</option>
              <option value="5">5 Sao ★★★★★</option>
              <option value="4">4 Sao ★★★★☆</option>
              <option value="3">3 Sao ★★★☆☆</option>
              <option value="2">2 Sao ★★☆☆☆</option>
              <option value="1">1 Sao ★☆☆☆☆</option>
            </select>

            <button onclick="loadAdminReviews(1)" class="p-2 bg-stone-100 hover:bg-stone-200 rounded-xl text-stone-600 transition" title="Làm mới">
              <i class="ph ph-arrows-clockwise text-base"></i>
            </button>
          </div>
        </div>

        <!-- BẢNG DANH SÁCH REVIEW -->
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="bg-stone-50 border-b border-stone-200 text-stone-500 uppercase tracking-wider font-semibold">
                <th class="py-3.5 px-4">Sản Phẩm Hoa</th>
                <th class="py-3.5 px-4">Khách Hàng & Đơn Hàng</th>
                <th class="py-3.5 px-4">Đánh Giá & Nhận Xét</th>
                <th class="py-3.5 px-4">Ảnh Hoa Khách Chụp</th>
                <th class="py-3.5 px-4">Trạng Thái</th>
                <th class="py-3.5 px-4">Phản Hồi Từ Shop</th>
                <th class="py-3.5 px-4 text-right">Thao Tác</th>
              </tr>
            </thead>
            <tbody id="reviewsTableBody" class="divide-y divide-stone-100">
              <tr>
                <td colspan="7" class="text-center py-10 text-stone-400">Đang tải danh sách đánh giá...</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- PHÂN TRANG REVIEWS -->
        <div id="reviewsPaginationWrap" class="p-4 border-t border-stone-100 flex items-center justify-between text-xs text-stone-500">
          <span id="reviewsPageInfo">Hiển thị 0 đánh giá</span>
          <div id="reviewsPageBtns" class="flex items-center space-x-1"></div>
        </div>
      </div>
    </div>

  </main>

  <!-- MODAL ĐỔI TRẠNG THÁI & TẢI ẢNH DUYỆT (PHOTO PROOF) -->
  <div id="updateOrderModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
    <div onclick="closeUpdateOrderModal()" class="fixed inset-0 bg-stone-900/50 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl z-10 border border-stone-200">
      <div class="flex items-center justify-between pb-3 border-b border-stone-100 mb-4">
        <div>
          <h3 class="font-bold text-base text-stone-900">Cập Nhật Tiến Độ Đơn Hoa</h3>
          <p class="text-xs text-stone-400" id="modalOrderCodeDisplay">Mã: BL00000</p>
        </div>
        <button onclick="closeUpdateOrderModal()" class="w-8 h-8 rounded-full hover:bg-stone-100 flex items-center justify-center text-stone-400">
          <i class="ph ph-x"></i>
        </button>
      </div>

      <form onsubmit="handleUpdateOrderSubmit(event)" class="space-y-4 text-xs">
        <input type="hidden" id="modalOrderId" />

        <!-- Đổi trạng thái 5 bước -->
        <div>
          <label class="block font-semibold text-stone-700 mb-1">1. Trạng Thái Mới Của Đơn Hàng:</label>
          <select id="modalOrderStatus" class="w-full bg-stone-50 border border-stone-300 rounded-xl px-3 py-2.5 font-medium text-stone-800 focus:ring-2 focus:ring-rose-500 focus:outline-none">
            <option value="received">1. Đã tiếp nhận đơn hàng</option>
            <option value="preparing">2. Florist đang cắm hoa</option>
            <option value="photo_ready">3. Đã chụp ảnh hoa thật (Khách có thể duyệt)</option>
            <option value="delivering">4. Shipper đang trên đường đi giao</option>
            <option value="completed">5. Đã giao hoa thành công & hoàn tất</option>
            <option value="cancelled">Hủy đơn hàng</option>
          </select>
        </div>

        <!-- Link ảnh hoa cắm thực tế (Photo proof) -->
        <div>
          <label class="block font-semibold text-stone-700 mb-1">2. Link Ảnh Chụp Hoa Thực Tế Đã Cắm Xong:</label>
          <input 
            type="url" 
            id="modalPhotoProof" 
            placeholder="https://images.unsplash.com/..." 
            class="w-full bg-stone-50 border border-stone-300 rounded-xl px-3 py-2 text-stone-800 focus:ring-2 focus:ring-rose-500 focus:outline-none"
          />
          <span class="text-[11px] text-stone-400 mt-1 block">Khách hàng sẽ nhìn thấy ảnh chụp này khi tra cứu tiến độ đơn hoa.</span>
        </div>

        <!-- Ghi chú cập nhật -->
        <div>
          <label class="block font-semibold text-stone-700 mb-1">3. Ghi Chú Tiến Độ:</label>
          <input 
            type="text" 
            id="modalNote" 
            placeholder="Ví dụ: Hoa đã cắm xong, in băng rôn vi tính rõ đẹp..." 
            class="w-full bg-stone-50 border border-stone-300 rounded-xl px-3 py-2 text-stone-800 focus:ring-2 focus:ring-rose-500 focus:outline-none"
          />
        </div>

        <div class="pt-3 border-t border-stone-100 flex items-center justify-end space-x-2">
          <button type="button" onclick="closeUpdateOrderModal()" class="px-4 py-2 bg-stone-100 hover:bg-stone-200 text-stone-600 rounded-xl font-medium transition">
            Hủy
          </button>
          <button type="submit" id="modalSaveBtn" class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl font-bold shadow transition">
            Lưu Thay Đổi
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- MODAL TỪ CHỐI ĐÁNH GIÁ (REJECT REVIEW) -->
  <div id="rejectReviewModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
    <div onclick="closeRejectModal()" class="fixed inset-0 bg-stone-900/50 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl z-10 border border-stone-200">
      <div class="flex items-center justify-between pb-3 border-b border-stone-100 mb-4">
        <div>
          <h3 class="font-bold text-base text-stone-900">Từ Chối Đánh Giá Vi Phạm</h3>
          <p class="text-xs text-stone-400">Ghi nhận lý do kiểm duyệt không hợp lệ theo tiêu chuẩn Bloomora</p>
        </div>
        <button onclick="closeRejectModal()" class="w-8 h-8 rounded-full hover:bg-stone-100 flex items-center justify-center text-stone-400">
          <i class="ph ph-x"></i>
        </button>
      </div>

      <form onsubmit="handleRejectSubmit(event)" class="space-y-4 text-xs">
        <input type="hidden" id="rejectReviewId" />

        <div>
          <label class="block font-semibold text-stone-700 mb-1">Chọn hoặc nhập lý do từ chối:</label>
          <select id="rejectPresetReason" onchange="if(this.value) document.getElementById('rejectCustomReason').value = this.value" class="w-full bg-stone-50 border border-stone-300 rounded-xl px-3 py-2 text-stone-800 mb-2 focus:ring-1 focus:ring-rose-500 focus:outline-none">
            <option value="">-- Chọn lý do mẫu --</option>
            <option value="Nội dung chứa từ ngữ thô tục, xúc phạm hoặc kích động">Từ ngữ thô tục, xúc phạm</option>
            <option value="Hình ảnh đính kèm không liên quan đến hoa hoặc không phù hợp">Ảnh không phù hợp / không liên quan</option>
            <option value="Đánh giá có tính chất spam, quảng cáo hoặc thông tin sai lệch">Spam / Quảng cáo</option>
            <option value="Nội dung để lộ thông tin riêng tư cá nhân">Lộ thông tin cá nhân</option>
          </select>

          <textarea id="rejectCustomReason" required rows="3" placeholder="Nhập lý do chi tiết từ chối..."
                    class="w-full bg-stone-50 border border-stone-300 rounded-xl p-2.5 text-stone-800 focus:ring-1 focus:ring-rose-500 focus:outline-none"></textarea>
        </div>

        <div class="pt-3 border-t border-stone-100 flex items-center justify-end space-x-2">
          <button type="button" onclick="closeRejectModal()" class="px-4 py-2 bg-stone-100 hover:bg-stone-200 text-stone-600 rounded-xl font-medium transition">
            Hủy
          </button>
          <button type="submit" id="rejectSubmitBtn" class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl font-bold shadow transition">
            Xác Nhận Từ Chối
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- MODAL PHẢN HỒI ĐÁNH GIÁ (SHOP REPLY) -->
  <div id="replyReviewModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
    <div onclick="closeReplyModal()" class="fixed inset-0 bg-stone-900/50 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl z-10 border border-stone-200">
      <div class="flex items-center justify-between pb-3 border-b border-stone-100 mb-4">
        <div>
          <h3 class="font-bold text-base text-stone-900">Phản Hồi Chính Thức Từ Bloomora</h3>
          <p class="text-xs text-stone-400" id="replyModalCustomerName">Gửi lời cảm ơn hoặc hỗ trợ khách hàng</p>
        </div>
        <button onclick="closeReplyModal()" class="w-8 h-8 rounded-full hover:bg-stone-100 flex items-center justify-center text-stone-400">
          <i class="ph ph-x"></i>
        </button>
      </div>

      <form onsubmit="handleReplySubmit(event)" class="space-y-4 text-xs">
        <input type="hidden" id="replyReviewId" />

        <div class="p-3 bg-stone-50 rounded-xl border border-stone-100 text-stone-700">
          <span class="block text-[11px] text-stone-400 font-semibold mb-1">Nhận xét của khách:</span>
          <p id="replyModalReviewText" class="italic text-xs"></p>
        </div>

        <div>
          <label class="block font-semibold text-stone-700 mb-1">Nội dung phản hồi công khai của Shop:</label>
          <textarea id="replyContentInput" required rows="4" 
                    placeholder="Dạ Bloomora chân thành cảm ơn quý khách đã tin tưởng và ủng hộ..."
                    class="w-full bg-stone-50 border border-stone-300 rounded-xl p-3 text-stone-800 focus:ring-2 focus:ring-rose-500 focus:outline-none"></textarea>
          <span class="text-[11px] text-stone-400 mt-1 block">Phản hồi này sẽ được hiển thị công khai ngay dưới đánh giá của khách trên trang sản phẩm.</span>
        </div>

        <div class="pt-3 border-t border-stone-100 flex items-center justify-end space-x-2">
          <button type="button" onclick="closeReplyModal()" class="px-4 py-2 bg-stone-100 hover:bg-stone-200 text-stone-600 rounded-xl font-medium transition">
            Hủy
          </button>
          <button type="submit" id="replySubmitBtn" class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl font-bold shadow transition flex items-center space-x-1.5">
            <i class="ph ph-paper-plane-right-bold"></i>
            <span>Đăng Phản Hồi</span>
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- MODAL XEM ẢNH PHÓNG TO (ADMIN LIGHTBOX) -->
  <div id="adminLightboxModal" class="fixed inset-0 z-[60] hidden flex items-center justify-center bg-black/80 backdrop-blur-sm p-4" onclick="closeAdminLightbox()">
    <button type="button" onclick="closeAdminLightbox()" class="absolute top-4 right-4 text-white hover:text-rose-400 text-2xl p-2 transition">
      <i class="ph ph-x-bold"></i>
    </button>
    <img id="adminLightboxImg" src="" alt="Ảnh hoa thực tế" class="max-w-full max-h-[90vh] rounded-2xl object-contain shadow-2xl border border-white/20" onclick="event.stopPropagation()" />
  </div>

  <footer class="bg-white border-t border-stone-200 py-4 text-center text-xs text-stone-400">
    Bloomora Florist Management System • Xây dựng theo tiêu chuẩn MVC & REST API
  </footer>

  <script>
    // Hàm chống XSS bảo vệ toàn diện
    function escapeHtml(str) {
      if (str === null || str === undefined) return '';
      return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    function formatMoney(amount) {
      return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount);
    }

    const STATUS_MAP = {
      'received': { text: '1. Tiếp nhận', bg: 'bg-stone-100 text-stone-700' },
      'preparing': { text: '2. Đang cắm hoa', bg: 'bg-amber-100 text-amber-800' },
      'photo_ready': { text: '3. Đã chụp ảnh', bg: 'bg-blue-100 text-blue-800' },
      'delivering': { text: '4. Đang giao', bg: 'bg-purple-100 text-purple-800' },
      'completed': { text: '5. Hoàn thành', bg: 'bg-emerald-100 text-emerald-800' },
      'cancelled': { text: 'Đã hủy', bg: 'bg-rose-100 text-rose-800' },
    };

    let currentOrders = [];
    let currentAdminReviews = [];
    let activeAdminTab = 'orders';

    document.addEventListener('DOMContentLoaded', () => {
      loadStats();
      loadAdminOrders();
      checkReviewsPendingCount();
    });

    // Chuyển đổi giữa 2 tab Quản lý Đơn hàng & Quản lý Đánh giá
    function switchAdminTab(tab) {
      activeAdminTab = tab;
      const tabOrders = document.getElementById('tabBtnOrders');
      const tabReviews = document.getElementById('tabBtnReviews');
      const wrapOrders = document.getElementById('ordersSectionWrap');
      const wrapReviews = document.getElementById('reviewsSectionWrap');

      if (tab === 'orders') {
        tabOrders.className = 'px-5 py-2.5 rounded-xl font-bold text-xs bg-rose-600 text-white shadow-sm flex items-center space-x-2 transition';
        tabReviews.className = 'px-5 py-2.5 rounded-xl font-bold text-xs bg-white text-stone-600 hover:text-stone-900 border border-stone-200 hover:bg-stone-50 transition flex items-center space-x-2';
        wrapOrders.classList.remove('hidden');
        wrapReviews.classList.add('hidden');
      } else {
        tabReviews.className = 'px-5 py-2.5 rounded-xl font-bold text-xs bg-rose-600 text-white shadow-sm flex items-center space-x-2 transition';
        tabOrders.className = 'px-5 py-2.5 rounded-xl font-bold text-xs bg-white text-stone-600 hover:text-stone-900 border border-stone-200 hover:bg-stone-50 transition flex items-center space-x-2';
        wrapOrders.classList.add('hidden');
        wrapReviews.classList.remove('hidden');
        loadAdminReviews(1);
      }
    }

    async function checkReviewsPendingCount() {
      try {
        const res = await fetch('../api/admin/reviews.php?limit=1');
        const json = await res.json();
        if (json.success && json.stats) {
          const pending = Number(json.stats.pending || 0);
          const badge = document.getElementById('adminPendingBadge');
          if (badge) {
            if (pending > 0) {
              badge.textContent = pending;
              badge.classList.remove('hidden');
            } else {
              badge.classList.add('hidden');
            }
          }
        }
      } catch (e) {}
    }

    async function loadStats() {
      try {
        const res = await fetch('../api/admin/stats.php');
        if (res.status === 401) {
          window.location.href = 'login.php';
          return;
        }
        const json = await res.json();
        if (json.success) {
          document.getElementById('statRevenue').textContent = formatMoney(json.data.totalRevenue);
          document.getElementById('statTotalOrders').textContent = json.data.totalOrders;
          document.getElementById('statPendingOrders').textContent = json.data.pendingOrders;
          document.getElementById('statDeliveringOrders').textContent = json.data.deliveringOrders;
        }
      } catch (e) {
        console.error('Lỗi tải stats:', e);
      }
    }

    async function loadAdminOrders() {
      const status = document.getElementById('statusFilter').value;
      const tbody = document.getElementById('ordersTableBody');
      tbody.innerHTML = `<tr><td colspan="9" class="text-center py-8 text-stone-400">Đang tải danh sách đơn...</td></tr>`;

      try {
        const url = `../api/admin/orders.php?status=${encodeURIComponent(status)}`;
        const res = await fetch(url);
        if (res.status === 401) {
          window.location.href = 'login.php';
          return;
        }
        const json = await res.json();

        if (json.success) {
          currentOrders = json.data;
          renderOrdersTable(json.data);
        } else {
          tbody.innerHTML = `<tr><td colspan="9" class="text-center py-8 text-rose-500">${escapeHtml(json.message)}</td></tr>`;
        }
      } catch (e) {
        tbody.innerHTML = `<tr><td colspan="9" class="text-center py-8 text-rose-500">Lỗi kết nối API Admin</td></tr>`;
      }
    }

    function renderOrdersTable(orders) {
      const tbody = document.getElementById('ordersTableBody');
      if (orders.length === 0) {
        tbody.innerHTML = `<tr><td colspan="9" class="text-center py-8 text-stone-400">Không có đơn hàng nào phù hợp bộ lọc.</td></tr>`;
        return;
      }

      tbody.innerHTML = orders.map(o => {
        const st = STATUS_MAP[o.order_status] || { text: o.order_status, bg: 'bg-stone-100 text-stone-700' };
        
        // Render tóm tắt danh sách sản phẩm trong đơn
        const itemsSummary = (o.items && o.items.length > 0)
          ? o.items.map(it => `<div>• ${escapeHtml(it.product_name)} <span class="text-rose-600 font-bold">x${it.quantity}</span></div>`).join('')
          : '<span class="text-stone-400 italic">Đơn tiêu chuẩn</span>';

        return `
          <tr class="hover:bg-stone-50 transition">
            <td class="py-3 px-4 font-mono font-bold text-rose-600">
              ${escapeHtml(o.order_code)}
            </td>
            <td class="py-3 px-4">
              <div class="font-bold text-stone-900">${escapeHtml(o.receiver_name)} (${escapeHtml(o.receiver_phone)})</div>
              <div class="text-[11px] text-stone-400">Người đặt: ${o.is_anonymous == 1 ? '<em class="text-rose-500">Ẩn danh</em>' : escapeHtml(o.sender_name)}</div>
            </td>
            <td class="py-3 px-4 max-w-xs">
              <div class="truncate text-stone-700" title="${escapeHtml(o.receiver_address)}">${escapeHtml(o.receiver_address)}</div>
              <div class="text-[11px] text-stone-400">${escapeHtml(o.delivery_date)} • <strong class="text-stone-600">${escapeHtml(o.delivery_time_slot)}</strong></div>
            </td>
            <td class="py-3 px-4 max-w-xs text-[11px] text-stone-600">
              ${itemsSummary}
            </td>
            <td class="py-3 px-4 max-w-xs truncate text-stone-600 italic" title="${escapeHtml(o.ribbon_message || o.card_message || 'Không in')}">
              "${escapeHtml(o.ribbon_message || o.card_message || 'Không in')}"
            </td>
            <td class="py-3 px-4 font-bold text-stone-900">
              ${formatMoney(o.total_amount)}
              <span class="block text-[10px] text-stone-400 font-normal uppercase">${escapeHtml(o.payment_method)} • <span class="${o.payment_status === 'paid' ? 'text-emerald-600 font-bold' : 'text-amber-600'}">${escapeHtml(o.payment_status)}</span></span>
            </td>
            <td class="py-3 px-4">
              <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold ${st.bg}">
                ${st.text}
              </span>
            </td>
            <td class="py-3 px-4">
              ${o.photo_proof ? `
                <a href="${escapeHtml(o.photo_proof)}" target="_blank" rel="noopener noreferrer" class="block w-10 h-10 rounded-lg overflow-hidden border border-stone-300 shadow hover:opacity-80 transition" title="Bấm để xem ảnh to">
                  <img src="${escapeHtml(o.photo_proof)}" class="w-full h-full object-cover" />
                </a>
              ` : `
                <span class="text-stone-300 text-[11px]">Chưa chụp</span>
              `}
            </td>
            <td class="py-3 px-4 text-right">
              <button 
                onclick="openUpdateOrderModal(${o.id})"
                class="px-3 py-1.5 bg-stone-900 hover:bg-black text-white rounded-lg text-xs font-semibold shadow transition"
              >
                Cập nhật
              </button>
            </td>
          </tr>
        `;
      }).join('');
    }

    function openUpdateOrderModal(orderId) {
      const order = currentOrders.find(o => o.id == orderId);
      if (!order) return;

      document.getElementById('modalOrderId').value = order.id;
      document.getElementById('modalOrderCodeDisplay').textContent = `Mã: ${order.order_code} - Người nhận: ${order.receiver_name}`;
      document.getElementById('modalOrderStatus').value = order.order_status;
      document.getElementById('modalPhotoProof').value = order.photo_proof || '';
      document.getElementById('modalNote').value = 'Đã hoàn thành bước: ' + order.order_status;

      document.getElementById('updateOrderModal').classList.remove('hidden');
    }

    function closeUpdateOrderModal() {
      document.getElementById('updateOrderModal').classList.add('hidden');
    }

    async function handleUpdateOrderSubmit(e) {
      e.preventDefault();

      const orderId = document.getElementById('modalOrderId').value;
      const status = document.getElementById('modalOrderStatus').value;
      const photoProof = document.getElementById('modalPhotoProof').value.trim();
      const note = document.getElementById('modalNote').value.trim();
      const saveBtn = document.getElementById('modalSaveBtn');

      saveBtn.disabled = true;
      saveBtn.textContent = 'Đang lưu...';

      try {
        const res = await fetch('../api/admin/orders.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ orderId, status, photoProof, note })
        });
        const json = await res.json();

        if (json.success) {
          closeUpdateOrderModal();
          loadStats();
          loadAdminOrders();
          alert('Cập nhật trạng thái đơn thành công!');
        } else {
          alert('Lỗi: ' + json.message);
        }
      } catch (err) {
        alert('Lỗi kết nối máy chủ');
      } finally {
        saveBtn.disabled = false;
        saveBtn.textContent = 'Lưu Thay Đổi';
      }
    }

    // ==========================================================================
    // QUẢN LÝ ĐÁNH GIÁ (ADMIN REVIEWS MANAGEMENT)
    // ==========================================================================
    let currentReviewsPage = 1;

    async function loadAdminReviews(page = 1) {
      currentReviewsPage = page;
      const status = document.getElementById('statusReviewFilter')?.value || 'all';
      const rating = document.getElementById('ratingReviewFilter')?.value || 'all';
      const q = document.getElementById('searchReviewInput')?.value || '';

      const tbody = document.getElementById('reviewsTableBody');
      if (tbody) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center py-10 text-stone-400">Đang tải danh sách đánh giá...</td></tr>`;
      }

      try {
        const query = new URLSearchParams({
          page: page,
          limit: 15,
          status: status,
          rating: rating,
          q: q
        });

        const res = await fetch(`../api/admin/reviews.php?${query.toString()}`);
        if (res.status === 401) {
          window.location.href = 'login.php';
          return;
        }

        const json = await res.json();
        if (!json.success) {
          if (tbody) tbody.innerHTML = `<tr><td colspan="7" class="text-center py-8 text-rose-500">${escapeHtml(json.message)}</td></tr>`;
          return;
        }

        // Cập nhật stats
        if (json.stats) {
          const s = json.stats;
          document.getElementById('statRevTotal').textContent = s.total || 0;
          document.getElementById('statRevPending').textContent = s.pending || 0;
          document.getElementById('statRevApproved').textContent = s.approved || 0;
          document.getElementById('statRevRejected').textContent = (Number(s.rejected || 0) + Number(s.hidden || 0));

          const badge = document.getElementById('adminPendingBadge');
          if (badge) {
            const pending = Number(s.pending || 0);
            if (pending > 0) {
              badge.textContent = pending;
              badge.classList.remove('hidden');
            } else {
              badge.classList.add('hidden');
            }
          }
        }

        // Render table
        renderAdminReviewsTable(json.data || [], json.pagination);
      } catch (err) {
        console.error(err);
        if (tbody) tbody.innerHTML = `<tr><td colspan="7" class="text-center py-8 text-rose-500">Lỗi kết nối máy chủ tải đánh giá</td></tr>`;
      }
    }

    function renderAdminReviewsTable(reviews, pagination) {
      const tbody = document.getElementById('reviewsTableBody');
      if (!tbody) return;

      if (!reviews || reviews.length === 0) {
        tbody.innerHTML = `
          <tr>
            <td colspan="7" class="text-center py-12 text-stone-400">
              <i class="ph ph-chat-circle-dots text-3xl mb-2 block text-stone-300"></i>
              Không tìm thấy đánh giá nào phù hợp với bộ lọc hiện tại.
            </td>
          </tr>
        `;
        renderReviewsPagination(pagination);
        return;
      }

      const statusBadges = {
        'pending': '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800">⏳ Chờ duyệt</span>',
        'approved': '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800">✓ Đã duyệt</span>',
        'hidden': '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-stone-100 text-stone-600">👁️ Đang ẩn</span>',
        'rejected': '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-rose-100 text-rose-800">✕ Từ chối</span>'
      };

      const html = reviews.map(r => {
        // Product thumbnail
        const pImg = r.product_image || 'https://images.unsplash.com/photo-1563245372-f21724e3856d?auto=format&fit=crop&w=150&q=80';
        
        // Stars
        let stars = '';
        for (let i = 1; i <= 5; i++) {
          stars += `<i class="ph-fill ph-star ${i <= r.rating ? 'text-amber-400' : 'text-stone-200'}"></i>`;
        }

        // Tags
        let tagsHtml = '';
        if (r.badges && r.badges.length) {
          tagsHtml = `<div class="flex flex-wrap gap-1 mt-1">` + 
            r.badges.map(b => `<span class="text-[10px] bg-stone-100 text-stone-600 px-1.5 py-0.5 rounded">${escapeHtml(b)}</span>`).join('') +
            `</div>`;
        }

        // Customer real photos
        let imagesHtml = '<span class="text-stone-300 italic text-[11px]">Không có ảnh</span>';
        if (r.images && r.images.length > 0) {
          imagesHtml = `<div class="flex items-center space-x-1.5 overflow-x-auto py-1">` +
            r.images.map(img => `
              <img src="../${escapeHtml(img.image_url)}" 
                   onclick="openAdminLightbox('../${escapeHtml(img.image_url)}')" 
                   alt="Ảnh hoa thực tế" 
                   title="Nhấn để xem kích thước lớn"
                   class="w-11 h-11 rounded-lg object-cover border border-stone-200 cursor-pointer hover:opacity-80 transition shadow-sm flex-shrink-0" />
            `).join('') +
            `</div>`;
        }

        // Shop reply
        let replyHtml = '';
        if (r.reply) {
          replyHtml = `
            <div class="p-2 bg-rose-50/70 rounded-xl border border-rose-100 text-[11px]">
              <div class="font-bold text-rose-700 flex items-center justify-between mb-0.5">
                <span>Bloomora Florist:</span>
                <span class="text-[10px] text-stone-400 font-normal">${escapeHtml(r.reply.created_at || '')}</span>
              </div>
              <p class="text-stone-700 line-clamp-2">${escapeHtml(r.reply.reply_content)}</p>
            </div>
          `;
        } else {
          replyHtml = `
            <button onclick="openReplyModal(${r.id}, '${escapeHtml(r.user_name || 'Khách hàng')}', '${escapeHtml(r.comment || '')}')" 
                    class="text-[11px] font-semibold text-rose-600 hover:text-rose-700 flex items-center space-x-1 hover:underline">
              <i class="ph ph-chat-text-bold"></i>
              <span>Gửi phản hồi shop</span>
            </button>
          `;
        }

        // Action buttons
        let actionsHtml = `<div class="flex items-center justify-end space-x-1.5 whitespace-nowrap">`;
        if (r.moderation_status === 'pending') {
          actionsHtml += `
            <button onclick="handleModerateReview(${r.id}, 'approve')" class="px-2.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold text-[11px] shadow-sm transition flex items-center space-x-1" title="Duyệt đánh giá này để hiển thị trên web">
              <i class="ph ph-check-bold"></i>
              <span>Duyệt</span>
            </button>
            <button onclick="openRejectModal(${r.id})" class="px-2.5 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-lg font-semibold text-[11px] transition" title="Từ chối vì vi phạm quy định">
              <i class="ph ph-x-bold"></i>
              <span>Từ chối</span>
            </button>
            <button onclick="handleModerateReview(${r.id}, 'hide')" class="p-1.5 bg-stone-100 hover:bg-stone-200 text-stone-600 rounded-lg transition" title="Tạm ẩn">
              <i class="ph ph-eye-slash text-sm"></i>
            </button>
          `;
        } else if (r.moderation_status === 'approved') {
          actionsHtml += `
            <button onclick="handleModerateReview(${r.id}, 'hide')" class="px-2.5 py-1 bg-stone-100 hover:bg-stone-200 text-stone-600 rounded-lg font-semibold text-[11px] transition" title="Ẩn đánh giá này">
              <i class="ph ph-eye-slash"></i>
              <span>Ẩn</span>
            </button>
          `;
          if (!r.reply) {
            actionsHtml += `
              <button onclick="openReplyModal(${r.id}, '${escapeHtml(r.user_name || 'Khách hàng')}', '${escapeHtml(r.comment || '')}')" class="px-2.5 py-1 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-lg font-semibold text-[11px] transition" title="Phản hồi">
                <i class="ph ph-chat-text"></i>
                <span>Phản hồi</span>
              </button>
            `;
          }
        } else if (r.moderation_status === 'hidden') {
          actionsHtml += `
            <button onclick="handleModerateReview(${r.id}, 'approve')" class="px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 rounded-lg font-semibold text-[11px] transition" title="Bỏ ẩn và duyệt lại">
              <i class="ph ph-check"></i>
              <span>Duyệt lại</span>
            </button>
          `;
        } else if (r.moderation_status === 'rejected') {
          actionsHtml += `
            <button onclick="handleModerateReview(${r.id}, 'approve')" class="px-2.5 py-1 bg-stone-100 hover:bg-stone-200 text-stone-600 rounded-lg font-semibold text-[11px] transition" title="Xem xét duyệt lại">
              <span>Duyệt lại</span>
            </button>
          `;
        }
        actionsHtml += `</div>`;

        // Date
        const dateStr = r.created_at ? r.created_at.substring(0, 16) : '';

        return `
          <tr class="hover:bg-stone-50/60 transition">
            <td class="py-3 px-4">
              <div class="flex items-center space-x-2.5 min-w-[160px]">
                <img src="${escapeHtml(pImg)}" alt="Ảnh hoa" class="w-10 h-10 rounded-xl object-cover border border-stone-200 flex-shrink-0" />
                <div class="truncate">
                  <span class="font-bold text-stone-900 block truncate" title="${escapeHtml(r.product_name)}">${escapeHtml(r.product_name)}</span>
                  <span class="text-[11px] text-stone-400">ID: #${r.product_id}</span>
                </div>
              </div>
            </td>
            <td class="py-3 px-4">
              <div class="min-w-[140px]">
                <div class="font-bold text-stone-800 flex items-center space-x-1">
                  <span>${escapeHtml(r.user_name || 'Khách hàng')}</span>
                  ${r.is_verified_buyer ? '<i class="ph-fill ph-seal-check text-emerald-600 text-xs" title="Đã xác thực đơn hàng"></i>' : ''}
                </div>
                <div class="text-[11px] text-stone-400 mt-0.5">
                  <span class="font-mono text-stone-500">${escapeHtml(r.order_code || '')}</span>
                  <span class="block">${escapeHtml(dateStr)}</span>
                </div>
              </div>
            </td>
            <td class="py-3 px-4 max-w-xs">
              <div>
                <div class="flex items-center space-x-0.5 text-xs">
                  ${stars}
                  <span class="font-bold text-stone-700 ml-1.5">${r.rating}/5</span>
                </div>
                ${r.title ? `<p class="font-bold text-stone-800 text-[11px] mt-1">${escapeHtml(r.title)}</p>` : ''}
                <p class="text-stone-600 text-xs mt-1 leading-relaxed">${escapeHtml(r.comment || '')}</p>
                ${tagsHtml}
              </div>
            </td>
            <td class="py-3 px-4">
              ${imagesHtml}
            </td>
            <td class="py-3 px-4 whitespace-nowrap">
              <div>
                ${statusBadges[r.moderation_status] || r.moderation_status}
                ${r.moderation_status === 'rejected' && r.reject_reason ? `
                  <p class="text-[10px] text-rose-500 italic mt-1 max-w-[140px] truncate" title="${escapeHtml(r.reject_reason)}">
                    Lý do: ${escapeHtml(r.reject_reason)}
                  </p>
                ` : ''}
              </div>
            </td>
            <td class="py-3 px-4 min-w-[160px] max-w-xs">
              ${replyHtml}
            </td>
            <td class="py-3 px-4 text-right">
              ${actionsHtml}
            </td>
          </tr>
        `;
      }).join('');

      tbody.innerHTML = html;
      renderReviewsPagination(pagination);
    }

    function renderReviewsPagination(p) {
      const infoEl = document.getElementById('reviewsPageInfo');
      const btnsEl = document.getElementById('reviewsPageBtns');
      if (!infoEl || !btnsEl || !p) return;

      infoEl.textContent = `Hiển thị trang ${p.page} / ${p.total_pages || 1} (Tổng cộng ${p.total} đánh giá)`;

      if (p.total_pages <= 1) {
        btnsEl.innerHTML = '';
        return;
      }

      let btns = '';
      if (p.page > 1) {
        btns += `<button onclick="loadAdminReviews(${p.page - 1})" class="px-2.5 py-1 bg-stone-100 hover:bg-stone-200 rounded-lg text-stone-600 text-xs font-semibold">« Trước</button>`;
      }
      for (let i = 1; i <= p.total_pages; i++) {
        if (i === 1 || i === p.total_pages || (i >= p.page - 1 && i <= p.page + 1)) {
          const activeClass = i === p.page ? 'bg-rose-600 text-white font-bold' : 'bg-stone-100 text-stone-600 hover:bg-stone-200';
          btns += `<button onclick="loadAdminReviews(${i})" class="px-2.5 py-1 rounded-lg text-xs font-medium ${activeClass}">${i}</button>`;
        } else if (i === p.page - 2 || i === p.page + 2) {
          btns += `<span class="px-1 text-stone-300">...</span>`;
        }
      }
      if (p.page < p.total_pages) {
        btns += `<button onclick="loadAdminReviews(${p.page + 1})" class="px-2.5 py-1 bg-stone-100 hover:bg-stone-200 rounded-lg text-stone-600 text-xs font-semibold">Sau »</button>`;
      }
      btnsEl.innerHTML = btns;
    }

    async function handleModerateReview(reviewId, action, reason = null) {
      const confirmMessages = {
        'approve': 'Bạn có chắc muốn duyệt đánh giá này để xuất bản công khai lên website?',
        'hide': 'Bạn có chắc muốn ẩn đánh giá này khỏi trang sản phẩm?'
      };
      if (confirmMessages[action] && !confirm(confirmMessages[action])) return;

      try {
        const res = await fetch('../api/admin/reviews.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'moderate',
            review_id: reviewId,
            status: action,
            reason: reason
          })
        });
        const json = await res.json();
        if (json.success) {
          loadAdminReviews(currentReviewsPage);
          checkReviewsPendingCount();
        } else {
          alert('Lỗi: ' + json.message);
        }
      } catch (err) {
        alert('Lỗi kết nối máy chủ');
      }
    }

    // Modal Từ chối (Reject)
    function openRejectModal(reviewId) {
      document.getElementById('rejectReviewId').value = reviewId;
      document.getElementById('rejectPresetReason').value = '';
      document.getElementById('rejectCustomReason').value = '';
      document.getElementById('rejectReviewModal').classList.remove('hidden');
    }

    function closeRejectModal() {
      document.getElementById('rejectReviewModal').classList.add('hidden');
    }

    async function handleRejectSubmit(e) {
      e.preventDefault();
      const reviewId = document.getElementById('rejectReviewId').value;
      const reason = document.getElementById('rejectCustomReason').value.trim();
      if (!reason) {
        alert('Vui lòng nhập lý do từ chối đánh giá');
        return;
      }
      const btn = document.getElementById('rejectSubmitBtn');
      btn.disabled = true;
      btn.textContent = 'Đang xử lý...';

      try {
        await handleModerateReview(Number(reviewId), 'reject', reason);
        closeRejectModal();
      } finally {
        btn.disabled = false;
        btn.textContent = 'Xác Nhận Từ Chối';
      }
    }

    // Modal Phản hồi của Shop (Shop Reply)
    function openReplyModal(reviewId, customerName, reviewContent) {
      document.getElementById('replyReviewId').value = reviewId;
      document.getElementById('replyModalCustomerName').textContent = `Khách hàng: ${customerName}`;
      document.getElementById('replyModalReviewText').textContent = `"${reviewContent || 'Đánh giá 5 sao'}"`;
      document.getElementById('replyContentInput').value = '';
      document.getElementById('replyReviewModal').classList.remove('hidden');
    }

    function closeReplyModal() {
      document.getElementById('replyReviewModal').classList.add('hidden');
    }

    async function handleReplySubmit(e) {
      e.preventDefault();
      const reviewId = document.getElementById('replyReviewId').value;
      const content = document.getElementById('replyContentInput').value.trim();
      if (!content) {
        alert('Vui lòng nhập nội dung phản hồi');
        return;
      }
      const btn = document.getElementById('replySubmitBtn');
      btn.disabled = true;
      btn.textContent = 'Đang đăng...';

      try {
        const res = await fetch('../api/admin/reviews.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'reply',
            review_id: Number(reviewId),
            reply_content: content
          })
        });
        const json = await res.json();
        if (json.success) {
          closeReplyModal();
          loadAdminReviews(currentReviewsPage);
          alert('Đã đăng phản hồi từ Bloomora thành công!');
        } else {
          alert('Lỗi: ' + json.message);
        }
      } catch (err) {
        alert('Lỗi kết nối máy chủ');
      } finally {
        btn.disabled = false;
        btn.innerHTML = `<i class="ph ph-paper-plane-right-bold"></i><span>Đăng Phản Hồi</span>`;
      }
    }

    // Lightbox xem ảnh lớn phóng to
    function openAdminLightbox(url) {
      const modal = document.getElementById('adminLightboxModal');
      const img = document.getElementById('adminLightboxImg');
      if (modal && img) {
        img.src = url;
        modal.classList.remove('hidden');
      }
    }

    function closeAdminLightbox() {
      const modal = document.getElementById('adminLightboxModal');
      if (modal) modal.classList.add('hidden');
    }

    async function handleAdminLogout() {
      if (!confirm('Bạn có chắc chắn muốn đăng xuất khỏi trang quản trị?')) return;
      try {
        await fetch('../api/auth/logout.php');
        window.location.href = 'login.php';
      } catch (e) {
        window.location.href = 'login.php';
      }
    }
  </script>
</body>
</html>
