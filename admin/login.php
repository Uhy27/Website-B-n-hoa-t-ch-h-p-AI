<?php
/**
 * TRANG ĐĂNG NHẬP DÀNH RIÊNG CHO QUẢN TRỊ VIÊN & NHÂN VIÊN BLOOMORA
 */
header('Content-Type: text/html; charset=UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Nếu đã đăng nhập với quyền admin/staff, chuyển thẳng vào Dashboard
if (isset($_SESSION['user_id']) && in_array($_SESSION['role_name'] ?? '', ['admin', 'staff'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Đăng Nhập Quản Trị | Bloomora Florist Admin</title>

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
              900: '#881337',
            }
          }
        }
      }
    }
  </script>
</head>
<body class="bg-stone-900 text-stone-100 min-h-screen flex items-center justify-center p-4 relative overflow-hidden">

  <!-- Họa tiết hoa nền mờ sang trọng -->
  <div class="absolute -top-24 -left-24 w-96 h-96 bg-rose-900/30 rounded-full blur-3xl pointer-events-none"></div>
  <div class="absolute -bottom-24 -right-24 w-96 h-96 bg-amber-900/20 rounded-full blur-3xl pointer-events-none"></div>

  <div class="w-full max-w-md relative z-10">
    
    <!-- Logo & Tiêu đề -->
    <div class="text-center mb-8">
      <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-rose-700 to-rose-500 flex items-center justify-center text-white mx-auto shadow-lg shadow-rose-600/30 mb-3">
        <i class="ph ph-flower-lotus text-3xl"></i>
      </div>
      <h1 class="font-serif-title text-2xl sm:text-3xl font-bold tracking-wide text-white">BLOOMORA ADMIN</h1>
      <p class="text-xs text-stone-400 mt-1">Cổng Quản Trị Hệ Thống Hoa Tươi & Đơn Hàng</p>
    </div>

    <!-- Hộp đăng nhập -->
    <div class="bg-stone-800/90 border border-stone-700/80 rounded-3xl p-6 sm:p-8 shadow-2xl backdrop-blur-md">
      
      <div id="loginAlert" class="hidden mb-4 p-3 rounded-xl text-xs font-semibold"></div>

      <form id="adminLoginForm" onsubmit="handleAdminLogin(event)" class="space-y-5">
        
        <div>
          <label class="block text-xs font-semibold text-stone-300 mb-1.5">Email Quản Trị</label>
          <div class="relative">
            <input 
              type="email" 
              id="adminEmail" 
              required
              value="admin@bloomora.vn"
              placeholder="admin@bloomora.vn"
              class="w-full bg-stone-900/90 border border-stone-700 rounded-xl px-4 py-3 pl-10 text-xs text-white placeholder-stone-500 focus:outline-none focus:border-rose-500 focus:ring-1 focus:ring-rose-500 transition"
            />
            <i class="ph ph-envelope text-stone-400 absolute left-3.5 top-3.5 text-base"></i>
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold text-stone-300 mb-1.5">Mật Khẩu</label>
          <div class="relative">
            <input 
              type="password" 
              id="adminPassword" 
              required
              value="admin123"
              placeholder="••••••••"
              class="w-full bg-stone-900/90 border border-stone-700 rounded-xl px-4 py-3 pl-10 pr-10 text-xs text-white placeholder-stone-500 focus:outline-none focus:border-rose-500 focus:ring-1 focus:ring-rose-500 transition"
            />
            <i class="ph ph-lock text-stone-400 absolute left-3.5 top-3.5 text-base"></i>
            <button 
              type="button" 
              onclick="togglePasswordVisibility()" 
              class="absolute right-3.5 top-3.5 text-stone-400 hover:text-white text-base"
              title="Ẩn/hiện mật khẩu"
            >
              <i id="passwordEyeIcon" class="ph ph-eye"></i>
            </button>
          </div>
        </div>

        <!-- Gợi ý tài khoản demo -->
        <div class="bg-stone-900/60 p-3 rounded-xl border border-stone-700/50 text-[11px] text-stone-400 space-y-1">
          <div class="flex items-center text-amber-400 font-semibold">
            <i class="ph ph-info mr-1"></i> Tài khoản quản trị mặc định:
          </div>
          <div>Email: <strong class="text-white font-mono">admin@bloomora.vn</strong> | Pass: <strong class="text-white font-mono">admin123</strong></div>
        </div>

        <button 
          type="submit" 
          id="loginBtn"
          class="w-full py-3.5 bg-gradient-to-r from-rose-600 to-rose-700 hover:from-rose-700 hover:to-rose-800 text-white rounded-xl font-bold text-xs shadow-lg shadow-rose-600/30 transition flex items-center justify-center space-x-2"
        >
          <span>Đăng Nhập Quản Trị</span>
          <i class="ph ph-arrow-right-bold"></i>
        </button>

      </form>
    </div>

    <!-- Quay lại trang mua hoa -->
    <div class="text-center mt-6">
      <a href="../index.html" class="text-xs text-stone-400 hover:text-white transition flex items-center justify-center space-x-1">
        <i class="ph ph-arrow-left"></i>
        <span>Quay về trang Cửa Hàng Bloomora</span>
      </a>
    </div>

  </div>

  <script>
    function togglePasswordVisibility() {
      const passInput = document.getElementById('adminPassword');
      const eyeIcon = document.getElementById('passwordEyeIcon');
      if (passInput.type === 'password') {
        passInput.type = 'text';
        eyeIcon.className = 'ph ph-eye-slash';
      } else {
        passInput.type = 'password';
        eyeIcon.className = 'ph ph-eye';
      }
    }

    async function handleAdminLogin(e) {
      e.preventDefault();
      const email = document.getElementById('adminEmail').value.trim();
      const password = document.getElementById('adminPassword').value;
      const alertBox = document.getElementById('loginAlert');
      const loginBtn = document.getElementById('loginBtn');

      loginBtn.disabled = true;
      loginBtn.innerHTML = `<i class="ph ph-spinner animate-spin text-base"></i><span>Đang xác thực...</span>`;
      alertBox.classList.add('hidden');

      try {
        const res = await fetch('../api/auth/login.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ email, password, require_admin: true })
        });
        const json = await res.json();

        if (json.success) {
          alertBox.className = 'mb-4 p-3 rounded-xl text-xs font-semibold bg-emerald-900/60 border border-emerald-500 text-emerald-200 block';
          alertBox.textContent = 'Đăng nhập thành công! Đang chuyển hướng...';
          setTimeout(() => {
            window.location.href = 'index.php';
          }, 600);
        } else {
          alertBox.className = 'mb-4 p-3 rounded-xl text-xs font-semibold bg-rose-900/60 border border-rose-500 text-rose-200 block';
          alertBox.textContent = json.message || 'Email hoặc mật khẩu không chính xác';
          loginBtn.disabled = false;
          loginBtn.innerHTML = `<span>Đăng Nhập Quản Trị</span><i class="ph ph-arrow-right-bold"></i>`;
        }
      } catch (err) {
        alertBox.className = 'mb-4 p-3 rounded-xl text-xs font-semibold bg-rose-900/60 border border-rose-500 text-rose-200 block';
        alertBox.textContent = 'Lỗi kết nối máy chủ. Vui lòng thử lại!';
        loginBtn.disabled = false;
        loginBtn.innerHTML = `<span>Đăng Nhập Quản Trị</span><i class="ph ph-arrow-right-bold"></i>`;
      }
    }
  </script>
</body>
</html>

