<?php
/**
 * BLOOMORA ADMIN DASHBOARD - BẢO VỆ PHIÊN ĐĂNG NHẬP
 */
header('Content-Type: text/html; charset=UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
        <span class="text-[11px] bg-rose-900/60 text-rose-300 px-2.5 py-0.5 rounded-full font-semibold border border-rose-700/50">v1.0 Quản Trị</span>
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

  <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full space-y-8">
    
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
              <th class="py-3.5 px-4">Lời Chúc / Băng Rôn</th>
              <th class="py-3.5 px-4">Số Tiền</th>
              <th class="py-3.5 px-4">Trạng Thái</th>
              <th class="py-3.5 px-4">Ảnh Hoa Duyệt</th>
              <th class="py-3.5 px-4 text-right">Thao Tác</th>
            </tr>
          </thead>
          <tbody id="ordersTableBody" class="divide-y divide-stone-100">
            <tr>
              <td colspan="8" class="text-center py-10 text-stone-400">Đang tải dữ liệu đơn hàng...</td>
            </tr>
          </tbody>
        </table>
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
          <button type="submit" class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl font-bold shadow transition">
            Lưu Thay Đổi
          </button>
        </div>
      </form>
    </div>
  </div>

  <footer class="bg-white border-t border-stone-200 py-4 text-center text-xs text-stone-400">
    Bloomora Florist Management System • Xây dựng theo tiêu chuẩn MVC & REST API
  </footer>

  <script>
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

    document.addEventListener('DOMContentLoaded', () => {
      loadStats();
      loadAdminOrders();
    });

    async function loadStats() {
      try {
        const res = await fetch('../api/admin/stats.php');
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
      tbody.innerHTML = `<tr><td colspan="8" class="text-center py-8 text-stone-400">Đang tải danh sách đơn...</td></tr>`;

      try {
        const url = `../api/admin/orders.php?status=${status}`;
        const res = await fetch(url);
        const json = await res.json();

        if (json.success) {
          currentOrders = json.data;
          renderOrdersTable(json.data);
        }
      } catch (e) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center py-8 text-rose-500">Lỗi kết nối API Admin</td></tr>`;
      }
    }

    function renderOrdersTable(orders) {
      const tbody = document.getElementById('ordersTableBody');
      if (orders.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center py-8 text-stone-400">Không có đơn hàng nào phù hợp bộ lọc.</td></tr>`;
        return;
      }

      tbody.innerHTML = orders.map(o => {
        const st = STATUS_MAP[o.order_status] || { text: o.order_status, bg: 'bg-stone-100 text-stone-700' };
        return `
          <tr class="hover:bg-stone-50 transition">
            <td class="py-3 px-4 font-mono font-bold text-rose-600">
              ${o.order_code}
            </td>
            <td class="py-3 px-4">
              <div class="font-bold text-stone-900">${o.receiver_name} (${o.receiver_phone})</div>
              <div class="text-[11px] text-stone-400">Người đặt: ${o.is_anonymous == 1 ? '<em class="text-rose-500">Ẩn danh</em>' : o.sender_name}</div>
            </td>
            <td class="py-3 px-4 max-w-xs">
              <div class="truncate text-stone-700">${o.receiver_address}</div>
              <div class="text-[11px] text-stone-400">${o.delivery_date} • <strong class="text-stone-600">${o.delivery_time_slot}</strong></div>
            </td>
            <td class="py-3 px-4 max-w-xs truncate text-stone-600 italic">
              "${o.ribbon_message || o.card_message || 'Không in'}"
            </td>
            <td class="py-3 px-4 font-bold text-stone-900">
              ${formatMoney(o.total_amount)}
              <span class="block text-[10px] text-stone-400 font-normal uppercase">${o.payment_method}</span>
            </td>
            <td class="py-3 px-4">
              <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold ${st.bg}">
                ${st.text}
              </span>
            </td>
            <td class="py-3 px-4">
              ${o.photo_proof ? `
                <a href="${o.photo_proof}" target="_blank" class="block w-10 h-10 rounded-lg overflow-hidden border border-stone-300 shadow hover:opacity-80 transition" title="Bấm để xem ảnh to">
                  <img src="${o.photo_proof}" class="w-full h-full object-cover" />
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
      }
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

