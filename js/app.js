/**
 * BLOOMORA - LOGIC ỨNG DỤNG TÍCH HỢP REST API & MYSQL
 * Kết nối API Backend: categories.php, products.php, checkout.php, tracking.php, user/orders.php
 */

// Hàm mã hóa chuỗi HTML chống tấn công Stored / Reflected XSS
function escapeHtml(str) {
  if (str === null || str === undefined) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

// Format tiền tệ Việt Nam (VNĐ)
function formatCurrency(amount) {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount);
}

// Ứng dụng Quản lý State
const AppState = {
  categories: typeof CATEGORIES !== 'undefined' ? [...CATEGORIES] : [],
  products: typeof FLOWER_PRODUCTS !== 'undefined' ? [...FLOWER_PRODUCTS] : [],
  filteredProducts: typeof FLOWER_PRODUCTS !== 'undefined' ? [...FLOWER_PRODUCTS] : [],
  category: 'all',
  priceRange: 'all',
  color: 'all',
  searchQuery: '',
  sortBy: 'popular',
  cart: [],
  activeQuickViewProduct: null,
  activeRibbonText: 'Chúc Mừng Khai Trương Hồng Phát - Vạn Sự Hanh Thông',
  activeRibbonColor: 'red',
  lastCreatedOrder: null,
  currentUser: null,
  isLoading: false,
  reviews: {
    productId: null,
    summary: null,
    eligibility: null,
    items: [],
    pagination: null,
    ratingFilter: 'all',
    hasImageFilter: false,
    sortBy: 'priority',
    isFormOpen: false,
    selectedStars: 5,
    selectedTags: [],
    selectedFiles: []
  }
};

// Khởi tạo khi DOM sẵn sàng
document.addEventListener('DOMContentLoaded', async () => {
  loadCartFromStorage();
  setupEventListeners();
  updateCartUI();

  // Kiểm tra phiên đăng nhập người dùng
  await checkAuthSession();

  // Gọi API lấy Danh mục & Sản phẩm từ Database MySQL
  await fetchCategories();
  await fetchProducts();
  renderReviews();
});

// Lưu / Tải giỏ hàng từ localStorage
function saveCartToStorage() {
  localStorage.setItem('bloomora_cart', JSON.stringify(AppState.cart));
}

function loadCartFromStorage() {
  try {
    const saved = localStorage.getItem('bloomora_cart');
    if (saved) {
      AppState.cart = JSON.parse(saved);
    }
  } catch (e) {
    AppState.cart = [];
  }
}

// 1. GỌI API LẤY DANH MỤC TỪ DATABASE (api/categories.php)
async function fetchCategories() {
  try {
    const res = await fetch('api/categories.php');
    if (res.ok) {
      const json = await res.json();
      if (json.success && json.data) {
        AppState.categories = json.data;
      }
    }
  } catch (e) {
    console.warn('API Categories chưa sẵn sàng, dùng dữ liệu khởi tạo:', e);
  }
  renderCategories();
}

// Render danh mục ở thanh menu & bộ lọc
function renderCategories() {
  const container = document.getElementById('categoryContainer');
  if (!container) return;

  container.innerHTML = AppState.categories.map(cat => {
    const catSlug = cat.slug || cat.id;
    const isActive = catSlug === AppState.category;
    return `
      <button 
        onclick="setCategory('${catSlug}')"
        class="category-pill flex items-center space-x-2 px-4 py-2.5 rounded-full text-sm font-medium transition-all duration-200 border whitespace-nowrap ${
          isActive 
            ? 'active bg-rose-600 text-white border-rose-600 shadow-md' 
            : 'bg-white text-gray-700 border-gray-200 hover:border-rose-300 hover:text-rose-600'
        }"
      >
        <i class="ph ${cat.icon || 'ph-flower-lotus'} text-lg"></i>
        <span>${cat.name}</span>
        ${cat.badge ? `<span class="text-[10px] uppercase font-bold px-1.5 py-0.5 rounded-full ${isActive ? 'bg-white text-rose-600' : 'bg-rose-100 text-rose-600'}">${cat.badge}</span>` : ''}
        ${cat.product_count !== undefined ? `<span class="text-[10px] opacity-75 font-mono">(${cat.product_count})</span>` : ''}
      </button>
    `;
  }).join('');
}

// 2. GỌI API LẤY SẢN PHẨM TỪ MYSQL KÈM BỘ LỌC (api/products.php)
async function fetchProducts() {
  const container = document.getElementById('productsGrid');
  if (container) {
    container.innerHTML = `
      <div class="col-span-full py-16 text-center text-stone-400">
        <div class="w-10 h-10 border-4 border-rose-600 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
        <p class="text-xs font-semibold">Đang tải hoa tươi từ kho Bloomora...</p>
      </div>
    `;
  }

  try {
    const params = new URLSearchParams({
      category: AppState.category,
      price: AppState.priceRange,
      color: AppState.color,
      q: AppState.searchQuery,
      sort: AppState.sortBy
    });

    const res = await fetch(`api/products.php?${params.toString()}`);
    if (res.ok) {
      const json = await res.json();
      if (json.success && json.data) {
        AppState.filteredProducts = json.data;
        AppState.products = json.data;
        renderProducts();
        return;
      }
    }
  } catch (e) {
    console.warn('Lỗi gọi API Products, sử dụng local filter fallback:', e);
  }

  // Fallback lọc offline nếu cần
  filterProductsOffline();
}

// Lọc Offline phòng ngừa
function filterProductsOffline() {
  if (typeof FLOWER_PRODUCTS === 'undefined') return;
  let list = [...FLOWER_PRODUCTS];

  if (AppState.category !== 'all') {
    list = list.filter(p => p.category === AppState.category);
  }
  if (AppState.priceRange !== 'all') {
    switch (AppState.priceRange) {
      case 'under-500': list = list.filter(p => p.price < 500000); break;
      case '500-1000': list = list.filter(p => p.price >= 500000 && p.price <= 1000000); break;
      case '1000-2000': list = list.filter(p => p.price > 1000000 && p.price <= 2000000); break;
      case 'above-2000': list = list.filter(p => p.price > 2000000); break;
    }
  }
  if (AppState.color !== 'all') {
    list = list.filter(p => p.color === AppState.color);
  }
  if (AppState.searchQuery.trim() !== '') {
    const q = AppState.searchQuery.toLowerCase().trim();
    list = list.filter(p => p.name.toLowerCase().includes(q) || p.flowerTypes.toLowerCase().includes(q));
  }
  AppState.filteredProducts = list;
  renderProducts();
}

// Thay đổi danh mục
function setCategory(catId) {
  AppState.category = catId;
  renderCategories();
  fetchProducts();

  const titleEl = document.getElementById('currentSectionTitle');
  if (titleEl) {
    const current = AppState.categories.find(c => (c.slug || c.id) === catId);
    titleEl.textContent = current ? current.name : 'Tất Cả Mẫu Hoa';
  }
}

// Thay đổi khoảng giá
function setPriceRange(range) {
  AppState.priceRange = range;
  document.querySelectorAll('.price-filter-btn').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.range === range);
    if (btn.dataset.range === range) {
      btn.classList.add('bg-rose-50', 'text-rose-600', 'font-semibold', 'border-rose-400');
    } else {
      btn.classList.remove('bg-rose-50', 'text-rose-600', 'font-semibold', 'border-rose-400');
    }
  });
  fetchProducts();
}

// Thay đổi tông màu
function setColorFilter(color) {
  AppState.color = color;
  document.querySelectorAll('.color-filter-btn').forEach(btn => {
    btn.classList.toggle('ring-4', btn.dataset.color === color);
    btn.classList.toggle('ring-rose-500', btn.dataset.color === color);
  });
  fetchProducts();
}

// Render danh sách sản phẩm
function renderProducts() {
  const container = document.getElementById('productsGrid');
  const countEl = document.getElementById('productCount');
  if (!container) return;

  if (countEl) {
    countEl.textContent = `${AppState.filteredProducts.length} mẫu hoa`;
  }

  if (AppState.filteredProducts.length === 0) {
    container.innerHTML = `
      <div class="col-span-full py-16 text-center">
        <div class="w-20 h-20 bg-rose-50 text-rose-500 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl">
          <i class="ph ph-magnifying-glass"></i>
        </div>
        <h3 class="text-xl font-bold text-gray-800 mb-2">Không tìm thấy mẫu hoa phù hợp</h3>
        <p class="text-gray-500 text-sm max-w-md mx-auto mb-6">
          Rất tiếc không có mẫu hoa nào khớp với bộ lọc của bạn. Hãy thử chọn khoảng giá hoặc tông màu khác, hoặc liên hệ để Florist cắm theo yêu cầu riêng!
        </p>
        <button onclick="resetFilters()" class="px-5 py-2.5 bg-rose-600 text-white rounded-xl font-medium hover:bg-rose-700 transition">
          Xóa bộ lọc & Xem tất cả
        </button>
      </div>
    `;
    return;
  }

  container.innerHTML = AppState.filteredProducts.map(p => {
    const orig = p.originalPrice || p.price;
    const discount = orig > p.price ? Math.round(((orig - p.price) / orig) * 100) : 0;
    const tags = Array.isArray(p.tags) ? p.tags : [];

    return `
      <div class="product-card group bg-white rounded-2xl overflow-hidden border border-gray-100 shadow-sm flex flex-col justify-between">
        <!-- Ảnh & Badge -->
        <div class="relative overflow-hidden aspect-[4/5] bg-gray-100 cursor-pointer" onclick="openQuickView('${p.id}')">
          <img 
            src="${p.image || p.thumbnail}" 
            alt="${p.name}" 
            class="img-zoom w-full h-full object-cover object-center"
            loading="lazy"
          />
          
          <!-- Tags góc trên trái -->
          <div class="absolute top-3 left-3 flex flex-col gap-1.5 z-10">
            ${tags.map(t => `
              <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full shadow-sm ${
                t.includes('Bán chạy') ? 'bg-amber-500 text-white' : 
                t.includes('60') ? 'bg-emerald-600 text-white' : 
                'bg-rose-600 text-white'
              }">
                <i class="ph ${t.includes('60') ? 'ph-lightning' : 'ph-star-fill'} mr-1"></i>${t}
              </span>
            `).join('')}
          </div>

          <!-- Giảm giá góc trên phải -->
          ${discount > 0 ? `
            <div class="absolute top-3 right-3 bg-red-600 text-white font-bold text-xs px-2 py-1 rounded-lg shadow-sm z-10">
              -${discount}%
            </div>
          ` : ''}

          <!-- Nút hành động nhanh khi hover -->
          <div class="absolute inset-x-0 bottom-0 p-4 bg-gradient-to-t from-black/60 via-black/20 to-transparent flex items-center justify-between opacity-0 group-hover:opacity-100 transition-all duration-300 transform translate-y-2 group-hover:translate-y-0">
            <button 
              onclick="event.stopPropagation(); openQuickView('${p.id}')"
              class="flex-1 mr-2 py-2 bg-white/90 hover:bg-white text-gray-800 text-xs font-semibold rounded-lg shadow backdrop-blur transition flex items-center justify-center space-x-1"
            >
              <i class="ph ph-eye text-sm"></i>
              <span>Xem Nhanh</span>
            </button>
            <button 
              onclick="event.stopPropagation(); addToCart('${p.id}', 1)"
              class="w-9 h-9 bg-rose-600 hover:bg-rose-700 text-white rounded-lg shadow flex items-center justify-center transition"
              title="Thêm vào giỏ"
            >
              <i class="ph ph-shopping-cart-simple text-lg"></i>
            </button>
          </div>
        </div>

        <!-- Thông tin sản phẩm -->
        <div class="p-4 flex-1 flex flex-col justify-between">
          <div>
            <div class="flex items-center justify-between text-xs text-gray-400 mb-1.5">
              <span>${p.categoryName || 'Hoa Tươi'}</span>
              <div class="flex items-center text-amber-500 font-medium">
                <i class="ph ph-star-fill mr-1 text-xs"></i>
                <span>${p.rating || 5.0}</span>
                <span class="text-gray-400 ml-1">(${p.reviewsCount || 10})</span>
              </div>
            </div>

            <h3 
              onclick="openQuickView('${p.id}')"
              class="font-semibold text-gray-900 text-base leading-snug line-clamp-2 hover:text-rose-600 transition cursor-pointer mb-2"
              title="${p.name}"
            >
              ${p.name}
            </h3>

            <p class="text-xs text-gray-500 line-clamp-1 mb-3">
              <i class="ph ph-flower text-rose-500 mr-1"></i>${p.flowerTypes || p.primary_flower || ''}
            </p>
          </div>

          <!-- Giá & Nút mua -->
          <div class="pt-3 border-t border-gray-100 flex items-center justify-between">
            <div>
              <div class="text-lg font-bold text-rose-600">${formatCurrency(p.price)}</div>
              ${orig > p.price ? `
                <div class="text-xs text-gray-400 line-through">${formatCurrency(orig)}</div>
              ` : ''}
            </div>

            <button 
              onclick="addToCart('${p.id}', 1)"
              class="px-3.5 py-2 bg-rose-50 hover:bg-rose-600 text-rose-600 hover:text-white rounded-xl text-xs font-semibold transition-all duration-200 flex items-center space-x-1"
            >
              <i class="ph ph-plus-bold"></i>
              <span>Chọn mua</span>
            </button>
          </div>
        </div>
      </div>
    `;
  }).join('');
}

// Reset toàn bộ bộ lọc
function resetFilters() {
  AppState.category = 'all';
  AppState.priceRange = 'all';
  AppState.color = 'all';
  AppState.searchQuery = '';
  AppState.sortBy = 'popular';

  const searchInput = document.getElementById('searchInput');
  if (searchInput) searchInput.value = '';

  renderCategories();
  document.querySelectorAll('.price-filter-btn').forEach(btn => {
    btn.classList.remove('bg-rose-50', 'text-rose-600', 'font-semibold', 'border-rose-400');
  });
  document.querySelectorAll('.color-filter-btn').forEach(btn => {
    btn.classList.remove('ring-4', 'ring-rose-500');
  });

  fetchProducts();
}

// Helper cuộn mượt đến danh sách sản phẩm
function scrollToProducts() {
  const el = document.getElementById('productsSection');
  if (el) {
    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}

// 1. Lọc theo Danh mục từ Menu chính
function navFilterCategory(catSlug, title) {
  AppState.category = catSlug;
  AppState.searchQuery = '';
  AppState.priceRange = 'all';
  AppState.color = 'all';
  const searchInput = document.getElementById('searchInput');
  if (searchInput) searchInput.value = '';

  renderCategories();
  fetchProducts();

  const titleEl = document.getElementById('currentSectionTitle');
  if (titleEl) {
    titleEl.textContent = title || 'Danh Mục Hoa';
  }
  scrollToProducts();
}

// 2. Lọc theo Chủ đề (Occasion / Keyword)
function navFilterTheme(keyword, title) {
  AppState.category = 'all';
  AppState.searchQuery = keyword;
  AppState.priceRange = 'all';
  AppState.color = 'all';
  const searchInput = document.getElementById('searchInput');
  if (searchInput) searchInput.value = keyword;

  renderCategories();
  fetchProducts();

  const titleEl = document.getElementById('currentSectionTitle');
  if (titleEl) {
    titleEl.textContent = title || `Chủ Đề: ${keyword}`;
  }
  scrollToProducts();
}

// 3. Lọc theo Thiết kế (Bó, Giỏ, Hộp, Kệ, Chậu...)
function navFilterDesign(keyword, title) {
  AppState.category = 'all';
  AppState.searchQuery = keyword;
  AppState.priceRange = 'all';
  AppState.color = 'all';
  const searchInput = document.getElementById('searchInput');
  if (searchInput) searchInput.value = keyword;

  renderCategories();
  fetchProducts();

  const titleEl = document.getElementById('currentSectionTitle');
  if (titleEl) {
    titleEl.textContent = title || `Thiết Kế: ${keyword}`;
  }
  scrollToProducts();
}

// 4. Lọc theo Loại hoa (Hồng, Hướng dương, Lan hồ điệp...)
function navFilterFlower(keyword, title) {
  AppState.category = 'all';
  AppState.searchQuery = keyword;
  AppState.priceRange = 'all';
  AppState.color = 'all';
  const searchInput = document.getElementById('searchInput');
  if (searchInput) searchInput.value = keyword;

  renderCategories();
  fetchProducts();

  const titleEl = document.getElementById('currentSectionTitle');
  if (titleEl) {
    titleEl.textContent = title || `Loại Hoa: ${keyword}`;
  }
  scrollToProducts();
}

// 5. Lọc Hoa tươi giảm giá / Flash Sale
function navFilterSale() {
  AppState.category = 'all';
  AppState.searchQuery = '';
  AppState.priceRange = 'all';
  AppState.color = 'all';

  const titleEl = document.getElementById('currentSectionTitle');
  if (titleEl) {
    titleEl.textContent = '🔥 Hoa Tươi Giảm Giá 30% - Ưu Đãi Đặc Biệt';
  }

  fetch('api/products.php')
    .then(res => res.json())
    .then(json => {
      if (json.success && json.data) {
        const saleItems = json.data.filter(p => (p.originalPrice && p.originalPrice > p.price) || (p.sale_price && p.sale_price > 0));
        AppState.filteredProducts = saleItems.length > 0 ? saleItems : json.data.slice(0, 8);
        renderProducts();
      }
    })
    .catch(() => {
      if (typeof FLOWER_PRODUCTS !== 'undefined') {
        AppState.filteredProducts = FLOWER_PRODUCTS.filter(p => p.originalPrice > p.price);
        renderProducts();
      }
    });

  scrollToProducts();
}

// Modal Xem Nhanh Sản Phẩm (Quick View)
function openQuickView(productId) {
  const product = AppState.filteredProducts.find(p => p.id == productId) || (typeof FLOWER_PRODUCTS !== 'undefined' ? FLOWER_PRODUCTS.find(p => p.id == productId) : null);
  if (!product) return;

  AppState.activeQuickViewProduct = product;
  const isKhaiTruong = (product.categorySlug || product.category) === 'khai-truong';
  const isChiaBuon = (product.categorySlug || product.category) === 'chia-buon';

  AppState.activeRibbonText = isKhaiTruong
    ? 'Chúc Mừng Khai Trương Hồng Phát - Vạn Sự Hanh Thông' 
    : isChiaBuon
    ? 'Kính Viếng - Thành Kính Phân Ưu Cùng Tang Quyến'
    : 'Chúc Mừng Sinh Nhật - Tuổi Mới Luôn Xinh Đẹp & Bình An!';

  const modal = document.getElementById('quickViewModal');
  const content = document.getElementById('quickViewContent');
  if (!modal || !content) return;

  const tags = Array.isArray(product.tags) ? product.tags : [];

  content.innerHTML = `
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 p-6 lg:p-8">
      <!-- Cột 1: Ảnh to & Cam kết -->
      <div>
        <div class="rounded-2xl overflow-hidden aspect-[4/5] bg-gray-100 shadow-md relative">
          <img src="${product.image || product.thumbnail}" alt="${product.name}" class="w-full h-full object-cover" />
          <div class="absolute top-4 left-4 flex flex-col gap-2">
            ${tags.map(t => `
              <span class="text-xs font-bold px-3 py-1 rounded-full bg-rose-600 text-white shadow">
                ${t}
              </span>
            `).join('')}
          </div>
        </div>

        <!-- Cam kết chất lượng chuẩn Bloomora -->
        <div class="mt-4 grid grid-cols-3 gap-2 text-center">
          <div class="p-2.5 bg-rose-50 rounded-xl">
            <i class="ph ph-camera text-rose-600 text-xl block mb-1"></i>
            <span class="text-[11px] font-medium text-gray-700">Chụp ảnh duyệt trước</span>
          </div>
          <div class="p-2.5 bg-rose-50 rounded-xl">
            <i class="ph ph-truck text-rose-600 text-xl block mb-1"></i>
            <span class="text-[11px] font-medium text-gray-700">Giao hỏa tốc 60p</span>
          </div>
          <div class="p-2.5 bg-rose-50 rounded-xl">
            <i class="ph ph-shield-check text-rose-600 text-xl block mb-1"></i>
            <span class="text-[11px] font-medium text-gray-700">Đổi mới nếu hoa héo</span>
          </div>
        </div>
      </div>

      <!-- Cột 2: Chi tiết & Tùy biến Băng rôn / Thiệp -->
      <div class="flex flex-col justify-between">
        <div>
          <div class="flex items-center justify-between text-xs text-gray-500 mb-2">
            <span class="uppercase tracking-wider font-semibold text-rose-600">${product.categoryName || 'Hoa Tươi Nghệ Thuật'}</span>
            <span>Mã SP: <strong class="text-gray-800">${product.sku || product.id}</strong></span>
          </div>

          <h2 class="text-2xl font-bold text-gray-900 mb-1.5 font-serif-title">${product.name}</h2>

          <!-- Đánh giá sao & lượt xem ngay dưới tên sản phẩm -->
          <div class="flex items-center space-x-3 mb-3 text-xs">
            <button type="button" onclick="scrollToReviews()" class="flex items-center text-amber-500 hover:text-amber-600 font-semibold transition" title="Xem chi tiết đánh giá">
              <span class="flex text-amber-400 mr-1.5 text-sm" id="qvRatingStarsTop">
                ${renderStarsHTML(product.rating || 5.0)}
              </span>
              <strong class="text-stone-900 text-sm mr-1" id="qvRatingValTop">${product.rating ? Number(product.rating).toFixed(1) : '5.0'}</strong>
              <span class="text-stone-400 underline" id="qvReviewsCountTop">(${product.reviewsCount || product.reviews_count || 1} đánh giá)</span>
            </button>
            <span class="text-stone-300">•</span>
            <span class="text-emerald-700 bg-emerald-50 px-2.5 py-0.5 rounded-full text-[11px] font-bold flex items-center">
              <i class="ph ph-shield-check mr-1 text-sm"></i> 100% Đánh giá thật từ khách mua
            </span>
          </div>

          <div class="flex items-center space-x-3 mb-4">
            <span class="text-2xl font-extrabold text-rose-600">${formatCurrency(product.price)}</span>
            ${product.originalPrice > product.price ? `
              <span class="text-sm text-gray-400 line-through">${formatCurrency(product.originalPrice)}</span>
              <span class="text-xs font-bold px-2 py-0.5 rounded bg-red-100 text-red-600">
                Tiết kiệm ${formatCurrency(product.originalPrice - product.price)}
              </span>
            ` : ''}
          </div>

          <!-- Thông số & Thành phần hoa -->
          <div class="space-y-2 text-sm text-gray-600 mb-5 pb-5 border-b border-gray-100">
            <p><strong class="text-gray-800">Hoa thành phần:</strong> ${product.flowerTypes || product.primary_flower || ''}</p>
            <p><strong class="text-gray-800">Kích thước:</strong> ${product.size || product.size_dimensions || 'Tiêu chuẩn Bloomora'}</p>
            <p><strong class="text-gray-800">Tone màu:</strong> ${product.colorName || product.color_name || 'Tự nhiên'}</p>
            <p class="italic text-gray-500 pt-1">"${product.meaning || 'Mang lại niềm vui và may mắn trọn vẹn.'}"</p>
          </div>

          <!-- Live Preview Băng Rôn Chúc Mừng -->
          <div class="bg-gray-50 p-4 rounded-xl mb-5 border border-gray-200">
            <div class="flex items-center justify-between mb-2">
              <span class="text-xs font-bold text-gray-700 flex items-center">
                <i class="ph ph-text-t text-rose-600 mr-1.5 text-base"></i>
                In Băng Rôn / Viết Thiệp Miễn Phí
              </span>
              <span class="text-[10px] text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded font-medium">Tặng kèm miễn phí</span>
            </div>

            <div id="ribbonPreview" class="ribbon-banner-preview p-3 rounded-lg text-center text-sm mb-3">
              ${AppState.activeRibbonText}
            </div>

            <input 
              type="text" 
              id="ribbonTextInput"
              value="${AppState.activeRibbonText}"
              oninput="updateRibbonLiveText(this.value)"
              placeholder="Nhập nội dung in băng rôn hoặc thiệp..."
              class="w-full text-xs px-3 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-rose-500 focus:outline-none"
            />
          </div>

          <!-- Quà tặng kèm thêm (Add-ons) -->
          <div class="mb-6">
            <span class="text-xs font-bold text-gray-700 block mb-2">Tùy chọn quà tặng kèm thêm:</span>
            <div class="grid grid-cols-2 gap-2">
              ${(typeof GIFT_ADDONS !== 'undefined' ? GIFT_ADDONS : []).map(addon => `
                <label class="flex items-center p-2.5 bg-white border border-gray-200 rounded-xl cursor-pointer hover:border-rose-400 transition text-xs">
                  <input type="checkbox" name="qv_addon" value="${addon.id}" class="rounded text-rose-600 focus:ring-rose-500 mr-2">
                  <div class="flex-1 truncate">
                    <span class="font-medium text-gray-800 block truncate">${addon.name}</span>
                    <span class="text-rose-600 font-semibold">+${formatCurrency(addon.price)}</span>
                  </div>
                </label>
              `).join('')}
            </div>
          </div>
        </div>

        <!-- Nút đặt hàng -->
        <div class="pt-4 border-t border-gray-100 flex items-center space-x-3">
          <div class="flex items-center border border-gray-300 rounded-xl overflow-hidden bg-white">
            <button onclick="decrementQVQty()" class="px-3 py-2 text-gray-500 hover:bg-gray-100"><i class="ph ph-minus"></i></button>
            <span id="qvQtyDisplay" class="px-3 py-2 font-bold text-sm text-gray-800">1</span>
            <button onclick="incrementQVQty()" class="px-3 py-2 text-gray-500 hover:bg-gray-100"><i class="ph ph-plus"></i></button>
          </div>

          <button 
            onclick="addQuickViewToCart(false)"
            class="flex-1 py-3 px-4 bg-rose-50 text-rose-600 hover:bg-rose-100 rounded-xl font-bold text-sm transition flex items-center justify-center space-x-2"
          >
            <i class="ph ph-shopping-bag-open text-lg"></i>
            <span>Thêm Vào Giỏ</span>
          </button>

          <button 
            onclick="addQuickViewToCart(true)"
            class="flex-1 py-3 px-4 bg-rose-600 hover:bg-rose-700 text-white rounded-xl font-bold text-sm shadow-lg shadow-rose-600/30 transition flex items-center justify-center space-x-2"
          >
            <i class="ph ph-lightning text-lg"></i>
            <span>Mua Ngay</span>
          </button>
        </div>
      </div>
    </div>

    <!-- ================= KHỐI ĐÁNH GIÁ SẢN PHẨM & ẢNH THỰC TẾ ================= -->
    <div id="productReviewsSection" class="border-t border-stone-200 mt-6 pt-6 bg-stone-50/70 p-6 sm:p-8 rounded-b-3xl">
      <div id="productReviewsContent">
        <div class="text-center py-8 text-stone-400">
          <i class="ph ph-circle-notch animate-spin text-2xl text-rose-600"></i>
          <p class="text-xs mt-2">Đang tải đánh giá sản phẩm...</p>
        </div>
      </div>
    </div>
  `;

  modal.classList.remove('hidden');
  document.body.classList.add('overflow-hidden');
  updateFloatingActionsVisibility();

  // Tải dữ liệu đánh giá thực tế từ MySQL
  loadProductReviews(product.id, 1);
}

function closeQuickView() {
  const modal = document.getElementById('quickViewModal');
  if (modal) {
    modal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
    updateFloatingActionsVisibility();
  }
}

// ==========================================================================
// HỆ THỐNG ĐÁNH GIÁ SẢN PHẨM (PRODUCT REVIEWS & REAL PHOTOS ENGINE)
// ==========================================================================

function renderStarsHTML(rating, size = 'text-sm') {
  const num = Number(rating) || 5;
  const full = Math.floor(num);
  const half = (num - full) >= 0.5 ? 1 : 0;
  const empty = 5 - full - half;
  let html = '';
  for (let i = 0; i < full; i++) html += `<i class="ph-fill ph-star text-amber-400 ${size}"></i>`;
  if (half) html += `<i class="ph-fill ph-star-half text-amber-400 ${size}"></i>`;
  for (let i = 0; i < empty; i++) html += `<i class="ph ph-star text-stone-300 ${size}"></i>`;
  return html;
}

function scrollToReviews() {
  const section = document.getElementById('productReviewsSection');
  if (section) {
    section.scrollIntoView({ behavior: 'smooth' });
  }
}

async function loadProductReviews(productId, page = 1, append = false) {
  AppState.reviews.productId = productId;
  AppState.reviews.currentPage = page;

  const content = document.getElementById('productReviewsContent');
  if (!content) return;

  if (!append) {
    content.innerHTML = `
      <div class="text-center py-8 text-stone-400">
        <i class="ph ph-circle-notch animate-spin text-2xl text-rose-600"></i>
        <p class="text-xs mt-2">Đang tải đánh giá sản phẩm...</p>
      </div>
    `;
  }

  try {
    const params = new URLSearchParams({
      product_id: productId,
      page: page,
      limit: 5,
      rating: AppState.reviews.ratingFilter,
      has_image: AppState.reviews.hasImageFilter ? '1' : '0',
      sort: AppState.reviews.sortBy
    });

    const res = await fetch(`api/reviews.php?${params.toString()}`);
    const json = await res.json();

    if (!json.success) {
      content.innerHTML = `<div class="text-center py-6 text-xs text-rose-600">${escapeHtml(json.message || 'Lỗi tải đánh giá')}</div>`;
      return;
    }

    AppState.reviews.summary = json.summary;
    AppState.reviews.eligibility = json.eligibility;
    AppState.reviews.pagination = json.pagination;

    if (append) {
      AppState.reviews.items = [...AppState.reviews.items, ...json.data];
    } else {
      AppState.reviews.items = json.data;
    }

    renderReviewsUI(json.summary, json.eligibility, AppState.reviews.items, json.pagination);

    // Cập nhật điểm sao góc trên của modal
    const topStars = document.getElementById('qvRatingStarsTop');
    const topVal = document.getElementById('qvRatingValTop');
    const topCount = document.getElementById('qvReviewsCountTop');
    if (topStars && json.summary) {
      topStars.innerHTML = renderStarsHTML(json.summary.average_rating);
      if (topVal) topVal.textContent = json.summary.average_rating.toFixed(1);
      if (topCount) topCount.textContent = `(${json.summary.total_reviews} đánh giá)`;
    }
  } catch (err) {
    console.error('[Load Reviews Error]', err);
    content.innerHTML = `<div class="text-center py-6 text-xs text-stone-400">Không thể kết nối đến máy chủ để tải đánh giá.</div>`;
  }
}

function renderReviewsUI(summary, eligibility, reviews, pagination) {
  const container = document.getElementById('productReviewsContent');
  if (!container) return;

  const total = summary.total_reviews || 0;
  const avg = Number(summary.average_rating || 5).toFixed(1);
  const starsBreakdown = summary.stars_breakdown || {
    5: { count: 0, percentage: 0 },
    4: { count: 0, percentage: 0 },
    3: { count: 0, percentage: 0 },
    2: { count: 0, percentage: 0 },
    1: { count: 0, percentage: 0 }
  };
  const withImages = summary.with_images_count || 0;

  let html = `
    <!-- Header Khối Đánh Giá -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-5 border-b border-stone-200 gap-3">
      <div>
        <h3 class="text-xl font-bold text-stone-900 font-serif-title flex items-center gap-2">
          <span>Đánh Giá Từ Khách Hàng</span>
          <span class="text-xs bg-rose-100 text-rose-700 px-2.5 py-0.5 rounded-full font-bold">Xác minh đơn hàng</span>
        </h3>
        <p class="text-xs text-stone-500 mt-1">100% nhận xét và ảnh thực tế từ khách hàng đã đặt hoa thành công tại Bloomora</p>
      </div>

      <!-- Nút Viết Đánh Giá (Call to Action) -->
      <div>
        ${renderEligibilityCTA(eligibility)}
      </div>
    </div>

    <!-- KHỐI FORM GỬI ĐÁNH GIÁ (MẶC ĐỊNH ẨN, MỞ KHI BẤM NÚT) -->
    <div id="writeReviewFormWrap" class="${AppState.reviews.isFormOpen ? 'block' : 'hidden'} mt-5 p-5 bg-white rounded-2xl border border-rose-200 shadow-sm transition-all duration-300">
      ${renderWriteReviewForm(eligibility)}
    </div>

    <!-- KHỐI TỔNG QUAN ĐIỂM SỐ & BIỂU ĐỒ PHÂN BỐ SAO -->
    <div class="mt-6 p-5 sm:p-6 bg-white rounded-2xl border border-stone-200/80 shadow-xs grid grid-cols-1 md:grid-cols-12 gap-6 items-center">
      <!-- Cột 1: Điểm số trung bình -->
      <div class="md:col-span-4 text-center md:border-r border-stone-100 md:pr-6">
        <div class="text-5xl font-extrabold text-stone-900 font-serif-title">${avg}</div>
        <div class="flex justify-center text-amber-400 text-lg my-1.5">
          ${renderStarsHTML(avg, 'text-lg')}
        </div>
        <div class="text-xs text-stone-500 font-medium">Dựa trên <strong>${total}</strong> lượt đánh giá hợp lệ</div>
        ${withImages > 0 ? `
          <div class="mt-2 text-[11px] text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-full inline-flex items-center">
            <i class="ph ph-camera mr-1 text-xs"></i> <strong>${withImages}</strong> đánh giá có ảnh chụp hoa thật
          </div>
        ` : ''}
      </div>

      <!-- Cột 2: Phân bố sao thực tế -->
      <div class="md:col-span-8 space-y-2">
        ${[5, 4, 3, 2, 1].map(star => {
          const item = starsBreakdown[star] || { count: 0, percentage: 0 };
          return `
            <div class="flex items-center text-xs text-stone-600 gap-3 cursor-pointer hover:text-stone-900" onclick="filterReviewsByRating(${star})">
              <span class="w-12 font-medium flex items-center justify-end">
                <span>${star}</span>
                <i class="ph-fill ph-star text-amber-400 ml-1"></i>
              </span>
              <div class="review-progress-track">
                <div class="review-progress-fill" style="width: ${item.percentage}%;"></div>
              </div>
              <span class="w-16 text-right font-medium text-stone-400">
                ${item.count} (${item.percentage}%)
              </span>
            </div>
          `;
        }).join('')}
      </div>
    </div>

    <!-- BỘ LỌC ĐÁNH GIÁ (FILTER PILLS) & SẮP XẾP -->
    <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
      <div class="flex flex-wrap items-center gap-2">
        <button type="button" 
                onclick="filterReviewsByRating('all')" 
                class="review-pill-filter ${AppState.reviews.ratingFilter === 'all' && !AppState.reviews.hasImageFilter ? 'active' : ''}">
          Tất cả (${total})
        </button>

        ${[5, 4, 3, 2, 1].map(star => `
          <button type="button" 
                  onclick="filterReviewsByRating(${star})" 
                  class="review-pill-filter ${AppState.reviews.ratingFilter == star ? 'active' : ''}">
            ${star} Sao (${starsBreakdown[star]?.count || 0})
          </button>
        `).join('')}

        <button type="button" 
                onclick="toggleReviewsHasImage()" 
                class="review-pill-filter flex items-center gap-1 ${AppState.reviews.hasImageFilter ? 'active' : ''}">
          <i class="ph ph-camera text-sm"></i> Có hình ảnh (${withImages})
        </button>
      </div>

      <!-- Sắp xếp -->
      <div class="flex items-center space-x-2 text-xs">
        <span class="text-stone-400">Sắp xếp:</span>
        <select onchange="changeReviewsSort(this.value)" class="bg-white border border-stone-200 rounded-xl px-3 py-1.5 text-xs text-stone-700 font-medium focus:ring-1 focus:ring-rose-500 focus:outline-none">
          <option value="priority" ${AppState.reviews.sortBy === 'priority' ? 'selected' : ''}>Ưu tiên có ảnh thực tế</option>
          <option value="newest" ${AppState.reviews.sortBy === 'newest' ? 'selected' : ''}>Mới nhất</option>
          <option value="highest" ${AppState.reviews.sortBy === 'highest' ? 'selected' : ''}>Điểm đánh giá cao</option>
          <option value="lowest" ${AppState.reviews.sortBy === 'lowest' ? 'selected' : ''}>Điểm đánh giá thấp</option>
        </select>
      </div>
    </div>

    <!-- DANH SÁCH CÁC BÀI ĐÁNH GIÁ THỰC TẾ -->
    <div class="mt-6 space-y-4" id="reviewsListItems">
      ${renderReviewsList(reviews)}
    </div>

    <!-- PHÂN TRANG / XEM THÊM ĐÁNH GIÁ -->
    ${pagination && pagination.has_more ? `
      <div class="mt-6 text-center">
        <button type="button" onclick="loadMoreReviews()" class="px-6 py-2.5 bg-white hover:bg-stone-100 text-stone-700 font-semibold text-xs rounded-xl border border-stone-200 shadow-xs transition flex items-center space-x-1.5 mx-auto">
          <span>Xem Thêm Đánh Giá Khác</span>
          <i class="ph ph-caret-down"></i>
        </button>
      </div>
    ` : ''}
  `;

  container.innerHTML = html;
}

function renderEligibilityCTA(eligibility) {
  if (!eligibility) return '';

  if (eligibility.can_review) {
    return `
      <button type="button" onclick="toggleReviewForm()" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold shadow-md shadow-rose-600/20 transition flex items-center space-x-1.5">
        <i class="ph ph-pencil-simple-line-bold text-sm"></i>
        <span>${AppState.reviews.isFormOpen ? 'Đóng Form Đánh Giá' : 'Viết Đánh Giá Của Bạn'}</span>
      </button>
    `;
  }

  if (eligibility.reason === 'login_required') {
    return `
      <button type="button" onclick="openAuthModal('login')" class="px-3.5 py-1.5 bg-stone-100 hover:bg-stone-200 text-stone-700 rounded-xl text-xs font-medium transition flex items-center space-x-1">
        <i class="ph ph-user text-sm"></i>
        <span>Đăng nhập để đánh giá</span>
      </button>
    `;
  }

  if (eligibility.reason === 'already_reviewed') {
    return `
      <span class="text-xs text-emerald-700 bg-emerald-50 px-3 py-1.5 rounded-xl font-medium inline-flex items-center">
        <i class="ph ph-check-circle mr-1"></i> Bạn đã đánh giá sản phẩm này
      </span>
    `;
  }

  // Not purchased or not completed
  return `
    <span class="text-[11px] text-stone-400 bg-stone-100 px-3 py-1.5 rounded-xl inline-flex items-center" title="${escapeHtml(eligibility.message)}">
      <i class="ph ph-shield-check mr-1 text-emerald-600"></i> Đánh giá xác minh theo đơn mua
    </span>
  `;
}

function renderWriteReviewForm(eligibility) {
  if (!eligibility || !eligibility.can_review) return '';

  const tags = ['Hoa tươi lâu', 'Đóng gói cẩn thận', 'Giao đúng giờ', 'Giống hình 100%', 'Florist cắm rất khéo', 'Mùi thơm tự nhiên'];

  return `
    <form onsubmit="handleReviewSubmit(event)" class="space-y-4">
      <div class="flex items-center justify-between pb-3 border-b border-stone-100">
        <div>
          <h4 class="font-bold text-sm text-stone-900">Gửi Đánh Giá Cho Đơn Hàng #${escapeHtml(eligibility.order_code)}</h4>
          <p class="text-[11px] text-stone-400">Trải nghiệm thật của bạn sẽ giúp những người yêu hoa khác an tâm mua sắm</p>
        </div>
        <button type="button" onclick="toggleReviewForm()" class="text-stone-400 hover:text-stone-600">
          <i class="ph ph-x text-base"></i>
        </button>
      </div>

      <!-- Chọn số sao (Bắt buộc) -->
      <div>
        <label class="block text-xs font-semibold text-stone-700 mb-1.5">1. Bạn đánh giá sản phẩm này mấy sao? <span class="text-rose-600">*</span></label>
        <div class="star-rating-interactive" id="reviewStarPicker">
          ${[1, 2, 3, 4, 5].map(s => `
            <button type="button" onclick="setReviewStars(${s})" title="${s} sao">
              <i class="ph-fill ph-star text-2xl ${s <= AppState.reviews.selectedStars ? 'text-amber-400' : 'text-stone-300'}"></i>
            </button>
          `).join('')}
        </div>
        <span class="text-[11px] font-semibold text-amber-600 ml-2" id="starRatingLabel">
          ${getStarLabel(AppState.reviews.selectedStars)}
        </span>
      </div>

      <!-- Tiêu chí nhanh cho hoa tươi -->
      <div>
        <label class="block text-xs font-semibold text-stone-700 mb-1.5">2. Điểm bạn ưng ý nhất về bó hoa này:</label>
        <div class="flex flex-wrap gap-2">
          ${tags.map(t => {
            const isSel = AppState.reviews.selectedTags.includes(t);
            return `
              <button type="button" 
                      onclick="toggleReviewTag('${escapeHtml(t)}')"
                      class="px-3 py-1 rounded-full text-xs font-medium border transition ${
                        isSel 
                          ? 'bg-rose-50 border-rose-500 text-rose-700 font-semibold' 
                          : 'bg-stone-50 border-stone-200 text-stone-600 hover:border-stone-300'
                      }">
                ${t}
              </button>
            `;
          }).join('')}
        </div>
      </div>

      <!-- Nội dung nhận xét -->
      <div>
        <label class="block text-xs font-semibold text-stone-700 mb-1.5">3. Chia sẻ chi tiết trải nghiệm của bạn:</label>
        <textarea id="reviewCommentInput" rows="3" required maxlength="2000"
                  placeholder="Chia sẻ cảm nhận về độ tươi của hoa, màu sắc thực tế, cách đóng gói và thái độ giao hàng..." 
                  class="w-full text-xs p-3 bg-stone-50 border border-stone-300 rounded-xl focus:ring-2 focus:ring-rose-500 focus:outline-none"></textarea>
      </div>

      <!-- Tải ảnh thực tế (Tối đa 5 ảnh) -->
      <div>
        <label class="block text-xs font-semibold text-stone-700 mb-1.5">4. Đính kèm ảnh thực tế hoa bạn đã nhận (Tối đa 5 ảnh, định dạng JPG/PNG/WEBP):</label>
        <input type="file" id="reviewFileInput" accept="image/jpeg,image/png,image/webp" multiple onchange="handleReviewFilesChanged(event)" class="hidden" />

        <div class="flex flex-wrap gap-2.5 items-center">
          <button type="button" onclick="document.getElementById('reviewFileInput').click()" class="h-20 w-20 rounded-xl border-2 border-dashed border-stone-300 hover:border-rose-500 bg-stone-50 text-stone-400 hover:text-rose-600 flex flex-col items-center justify-center text-xs transition">
            <i class="ph ph-camera text-xl mb-1"></i>
            <span class="text-[10px] font-bold">Thêm ảnh</span>
          </button>

          <!-- Previews Container -->
          <div id="reviewPhotosPreviewGrid" class="flex flex-wrap gap-2.5">
            ${AppState.reviews.selectedFiles.map((fileObj, idx) => `
              <div class="relative w-20 h-20 rounded-xl overflow-hidden border border-stone-200 shadow-xs">
                <img src="${fileObj.previewUrl}" alt="Preview" class="w-full h-full object-cover" />
                <button type="button" onclick="removeReviewFile(${idx})" class="absolute top-1 right-1 w-5 h-5 rounded-full bg-black/60 text-white flex items-center justify-center text-[10px] hover:bg-rose-600 transition">
                  <i class="ph ph-x-bold"></i>
                </button>
              </div>
            `).join('')}
          </div>
        </div>
        <span class="text-[11px] text-stone-400 mt-1 block">Hình ảnh thực tế sẽ giúp cộng đồng đánh giá chính xác chất lượng cắm hoa của nghệ nhân.</span>
      </div>

      <!-- Action buttons -->
      <div class="pt-3 border-t border-stone-100 flex items-center justify-end space-x-2">
        <button type="button" onclick="toggleReviewForm()" class="px-4 py-2 bg-stone-100 hover:bg-stone-200 text-stone-600 rounded-xl text-xs font-medium transition">
          Hủy Bỏ
        </button>
        <button type="submit" id="submitReviewBtn" class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold shadow-md shadow-rose-600/30 transition flex items-center space-x-1.5">
          <i class="ph ph-paper-plane-right-bold"></i>
          <span>Gửi Đánh Giá Xác Minh</span>
        </button>
      </div>
    </form>
  `;
}

function renderReviewsList(reviews) {
  if (!reviews || reviews.length === 0) {
    return `
      <div class="text-center py-12 bg-white rounded-2xl border border-stone-100">
        <div class="w-12 h-12 rounded-full bg-rose-50 text-rose-500 flex items-center justify-center mx-auto text-xl mb-2">
          <i class="ph ph-chat-circle-dots"></i>
        </div>
        <h4 class="font-bold text-stone-700 text-sm">Chưa có đánh giá nào phù hợp với bộ lọc</h4>
        <p class="text-xs text-stone-400 mt-1">Hãy là người đầu tiên chia sẻ cảm nhận về mẫu hoa này nhé!</p>
      </div>
    `;
  }

  return reviews.map(r => {
    const initials = r.customer_name ? r.customer_name.trim().charAt(0).toUpperCase() : 'K';
    const images = Array.isArray(r.images) ? r.images : [];

    return `
      <div class="p-5 bg-white rounded-2xl border border-stone-100 shadow-xs space-y-3">
        <!-- Header bài đánh giá -->
        <div class="flex items-start justify-between">
          <div class="flex items-center space-x-3">
            <div class="w-9 h-9 rounded-full bg-gradient-to-tr from-rose-600 to-rose-400 text-white font-bold flex items-center justify-center text-xs shadow-xs">
              ${escapeHtml(initials)}
            </div>
            <div>
              <div class="flex items-center space-x-2">
                <span class="font-bold text-xs text-stone-900">${escapeHtml(r.customer_name)}</span>
                ${r.is_verified_purchase ? `
                  <span class="inline-flex items-center text-[10px] bg-emerald-50 text-emerald-700 font-bold px-2 py-0.5 rounded-full border border-emerald-200/60">
                    <i class="ph-bold ph-check text-[10px] mr-1"></i> Đã mua hàng
                  </span>
                ` : ''}
              </div>
              <div class="flex items-center space-x-2 mt-0.5 text-xs text-stone-400">
                <span class="flex text-amber-400">${renderStarsHTML(r.rating, 'text-xs')}</span>
                <span>•</span>
                <span class="text-[11px]">${escapeHtml(r.created_at_formatted || 'Gần đây')}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Tag tiêu chí hoa nếu có -->
        ${r.flower_quality_tag ? `
          <div>
            <span class="inline-block bg-rose-50 text-rose-700 text-[11px] font-semibold px-2.5 py-0.5 rounded-md">
              <i class="ph ph-sparkle mr-1"></i> ${escapeHtml(r.flower_quality_tag)}
            </span>
          </div>
        ` : ''}

        <!-- Nội dung nhận xét -->
        <p class="text-xs text-stone-700 leading-relaxed">${escapeHtml(r.comment || '')}</p>

        <!-- Thư viện ảnh thực tế từ khách -->
        ${images.length > 0 ? `
          <div class="flex flex-wrap gap-2.5 pt-1">
            ${images.map(img => `
              <div class="review-thumb-item w-16 h-16 sm:w-20 sm:h-20" onclick="openReviewLightbox('${escapeHtml(img)}')">
                <img src="${escapeHtml(img)}" alt="Ảnh hoa thực tế" loading="lazy" />
                <div class="thumb-overlay">
                  <i class="ph ph-magnifying-glass-plus text-base"></i>
                </div>
              </div>
            `).join('')}
          </div>
        ` : ''}

        <!-- Phản hồi chính thức từ Bloomora (Shop Reply) -->
        ${r.reply ? `
          <div class="mt-3.5 p-3.5 bg-rose-50/70 border-l-4 border-rose-600 rounded-r-2xl text-xs space-y-1">
            <div class="flex items-center space-x-2 text-rose-900 font-bold">
              <i class="ph ph-flower-lotus text-base text-rose-600"></i>
              <span>${escapeHtml(r.reply.staff_name || 'Bloomora Florist')}</span>
              <span class="text-[10px] bg-rose-600 text-white font-semibold px-1.5 py-0.2 rounded">Phản hồi chính thức</span>
            </div>
            <p class="text-stone-700 text-xs leading-relaxed pt-0.5">${escapeHtml(r.reply.reply_content)}</p>
          </div>
        ` : ''}
      </div>
    `;
  }).join('');
}

function getStarLabel(stars) {
  const map = {
    1: '1 sao - Rất không hài lòng',
    2: '2 sao - Chưa hài lòng',
    3: '3 sao - Bình thường',
    4: '4 sao - Rất hài lòng',
    5: '5 sao - Tuyệt vời, vượt mong đợi!'
  };
  return map[stars] || '5 sao - Tuyệt vời!';
}

function setReviewStars(stars) {
  AppState.reviews.selectedStars = stars;
  const picker = document.getElementById('reviewStarPicker');
  const label = document.getElementById('starRatingLabel');
  if (picker) {
    picker.innerHTML = [1, 2, 3, 4, 5].map(s => `
      <button type="button" onclick="setReviewStars(${s})" title="${s} sao">
        <i class="ph-fill ph-star text-2xl ${s <= stars ? 'text-amber-400' : 'text-stone-300'}"></i>
      </button>
    `).join('');
  }
  if (label) {
    label.textContent = getStarLabel(stars);
  }
}

function toggleReviewTag(tag) {
  const idx = AppState.reviews.selectedTags.indexOf(tag);
  if (idx >= 0) {
    AppState.reviews.selectedTags.splice(idx, 1);
  } else {
    AppState.reviews.selectedTags.push(tag);
  }
  // Re-render form part
  const formWrap = document.getElementById('writeReviewFormWrap');
  if (formWrap) {
    formWrap.innerHTML = renderWriteReviewForm(AppState.reviews.eligibility);
  }
}

function toggleReviewForm() {
  AppState.reviews.isFormOpen = !AppState.reviews.isFormOpen;
  const formWrap = document.getElementById('writeReviewFormWrap');
  if (formWrap) {
    if (AppState.reviews.isFormOpen) {
      formWrap.classList.remove('hidden');
      formWrap.scrollIntoView({ behavior: 'smooth' });
    } else {
      formWrap.classList.add('hidden');
    }
  }
}

function handleReviewFilesChanged(e) {
  const files = Array.from(e.target.files || []);
  if (files.length === 0) return;

  const currentCount = AppState.reviews.selectedFiles.length;
  const remaining = 5 - currentCount;

  if (remaining <= 0) {
    if (typeof showToast === 'function') showToast('Bạn chỉ có thể tải lên tối đa 5 ảnh thực tế.', 'warning');
    return;
  }

  const toAdd = files.slice(0, remaining);
  toAdd.forEach(file => {
    if (file.size > 5 * 1024 * 1024) {
      if (typeof showToast === 'function') showToast(`Ảnh ${file.name} vượt quá 5MB.`, 'warning');
      return;
    }
    const previewUrl = URL.createObjectURL(file);
    AppState.reviews.selectedFiles.push({ file, previewUrl });
  });

  // Re-render preview grid
  const grid = document.getElementById('reviewPhotosPreviewGrid');
  if (grid) {
    grid.innerHTML = AppState.reviews.selectedFiles.map((fileObj, idx) => `
      <div class="relative w-20 h-20 rounded-xl overflow-hidden border border-stone-200 shadow-xs">
        <img src="${fileObj.previewUrl}" alt="Preview" class="w-full h-full object-cover" />
        <button type="button" onclick="removeReviewFile(${idx})" class="absolute top-1 right-1 w-5 h-5 rounded-full bg-black/60 text-white flex items-center justify-center text-[10px] hover:bg-rose-600 transition">
          <i class="ph ph-x-bold"></i>
        </button>
      </div>
    `).join('');
  }
}

function removeReviewFile(idx) {
  if (AppState.reviews.selectedFiles[idx]) {
    URL.revokeObjectURL(AppState.reviews.selectedFiles[idx].previewUrl);
    AppState.reviews.selectedFiles.splice(idx, 1);
  }
  const grid = document.getElementById('reviewPhotosPreviewGrid');
  if (grid) {
    grid.innerHTML = AppState.reviews.selectedFiles.map((fileObj, i) => `
      <div class="relative w-20 h-20 rounded-xl overflow-hidden border border-stone-200 shadow-xs">
        <img src="${fileObj.previewUrl}" alt="Preview" class="w-full h-full object-cover" />
        <button type="button" onclick="removeReviewFile(${i})" class="absolute top-1 right-1 w-5 h-5 rounded-full bg-black/60 text-white flex items-center justify-center text-[10px] hover:bg-rose-600 transition">
          <i class="ph ph-x-bold"></i>
        </button>
      </div>
    `).join('');
  }
}

async function handleReviewSubmit(e) {
  e.preventDefault();
  const comment = document.getElementById('reviewCommentInput')?.value.trim();
  const rating = AppState.reviews.selectedStars;
  const productId = AppState.reviews.productId;

  if (!rating || rating < 1 || rating > 5) {
    if (typeof showToast === 'function') showToast('Vui lòng chọn số sao đánh giá.', 'warning');
    return;
  }

  const submitBtn = document.getElementById('submitReviewBtn');
  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.innerHTML = `<i class="ph ph-circle-notch animate-spin"></i><span>Đang gửi đánh giá...</span>`;
  }

  const formData = new FormData();
  formData.append('product_id', productId);
  formData.append('rating', rating);
  formData.append('comment', comment);
  if (AppState.reviews.selectedTags.length > 0) {
    formData.append('quality_tag', AppState.reviews.selectedTags.join(', '));
  }
  AppState.reviews.selectedFiles.forEach((item) => {
    formData.append('images[]', item.file);
  });

  try {
    const res = await fetch('api/reviews.php', {
      method: 'POST',
      body: formData
    });
    const json = await res.json();

    if (json.success) {
      if (typeof showToast === 'function') {
        showToast(json.message || 'Cảm ơn bạn đã chia sẻ trải nghiệm cùng Bloomora. Đánh giá của bạn đang chờ kiểm duyệt.', 'success');
      } else {
        alert(json.message);
      }
      AppState.reviews.isFormOpen = false;
      AppState.reviews.selectedFiles = [];
      AppState.reviews.selectedTags = [];
      loadProductReviews(productId, 1);
    } else {
      if (typeof showToast === 'function') {
        showToast(json.message || 'Không thể gửi đánh giá.', 'warning');
      } else {
        alert(json.message);
      }
    }
  } catch (err) {
    console.error('[Submit Review Error]', err);
    if (typeof showToast === 'function') showToast('Lỗi máy chủ khi gửi đánh giá.', 'error');
  } finally {
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.innerHTML = `<i class="ph ph-paper-plane-right-bold"></i><span>Gửi Đánh Giá Xác Minh</span>`;
    }
  }
}

function filterReviewsByRating(star) {
  AppState.reviews.ratingFilter = star;
  loadProductReviews(AppState.reviews.productId, 1);
}

function toggleReviewsHasImage() {
  AppState.reviews.hasImageFilter = !AppState.reviews.hasImageFilter;
  loadProductReviews(AppState.reviews.productId, 1);
}

function changeReviewsSort(sortVal) {
  AppState.reviews.sortBy = sortVal;
  loadProductReviews(AppState.reviews.productId, 1);
}

function loadMoreReviews() {
  const next = (AppState.reviews.currentPage || 1) + 1;
  loadProductReviews(AppState.reviews.productId, next, true);
}

function openReviewLightbox(imgSrc) {
  const modal = document.getElementById('reviewImageLightbox');
  const img = document.getElementById('reviewLightboxImg');
  if (modal && img) {
    img.src = imgSrc;
    modal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
    updateFloatingActionsVisibility();
  }
}

function closeReviewLightbox() {
  const modal = document.getElementById('reviewImageLightbox');
  if (modal) {
    modal.classList.add('hidden');
    // If quickview is still open, keep body overflow-hidden
    if (!document.getElementById('quickViewModal')?.classList.contains('hidden')) {
      document.body.classList.add('overflow-hidden');
    } else {
      document.body.classList.remove('overflow-hidden');
    }
    updateFloatingActionsVisibility();
  }
}

function updateRibbonLiveText(text) {
  AppState.activeRibbonText = text;
  const preview = document.getElementById('ribbonPreview');
  if (preview) {
    preview.textContent = text || 'Chúc mừng khai trương / Chúc mừng sinh nhật';
  }
}

let qvQuantity = 1;
function incrementQVQty() {
  qvQuantity++;
  document.getElementById('qvQtyDisplay').textContent = qvQuantity;
}
function decrementQVQty() {
  if (qvQuantity > 1) {
    qvQuantity--;
    document.getElementById('qvQtyDisplay').textContent = qvQuantity;
  }
}

function addQuickViewToCart(buyNow = false) {
  if (!AppState.activeQuickViewProduct) return;

  const checkedAddons = Array.from(document.querySelectorAll('input[name="qv_addon"]:checked'))
    .map(input => (typeof GIFT_ADDONS !== 'undefined' ? GIFT_ADDONS.find(a => a.id === input.value) : null))
    .filter(Boolean);

  addToCart(
    AppState.activeQuickViewProduct.id, 
    qvQuantity, 
    AppState.activeRibbonText, 
    checkedAddons
  );

  closeQuickView();
  qvQuantity = 1;

  if (buyNow) {
    openCheckoutDrawer();
  }
}

// Thêm vào giỏ hàng
function addToCart(productId, quantity = 1, ribbonText = '', addons = []) {
  const product = AppState.filteredProducts.find(p => p.id == productId) || (typeof FLOWER_PRODUCTS !== 'undefined' ? FLOWER_PRODUCTS.find(p => p.id == productId) : null);
  if (!product) return;

  const existing = AppState.cart.find(item => item.product.id == productId);

  if (existing) {
    existing.quantity += quantity;
    if (ribbonText) existing.ribbonText = ribbonText;
    if (addons.length > 0) existing.addons = addons;
  } else {
    AppState.cart.push({
      product,
      quantity,
      ribbonText: ribbonText || 'Miễn phí in băng rôn hoặc thiệp viết tay theo yêu cầu',
      addons: addons || []
    });
  }

  saveCartToStorage();
  updateCartUI();
  showToast(`Đã thêm "${product.name}" vào giỏ hàng!`, 'success');
}

function updateCartUI() {
  const badgeEls = document.querySelectorAll('.cart-badge');
  const totalCount = AppState.cart.reduce((sum, item) => sum + item.quantity, 0);

  badgeEls.forEach(el => {
    el.textContent = totalCount;
    el.classList.toggle('hidden', totalCount === 0);
  });

  renderCartItems();
}

function renderCartItems() {
  const listEl = document.getElementById('cartDrawerItems');
  const subtotalEl = document.getElementById('cartSubtotal');
  const totalEl = document.getElementById('cartTotal');
  if (!listEl) return;

  if (AppState.cart.length === 0) {
    listEl.innerHTML = `
      <div class="py-16 text-center">
        <div class="w-16 h-16 bg-gray-100 text-gray-400 rounded-full flex items-center justify-center mx-auto mb-3 text-2xl">
          <i class="ph ph-shopping-bag"></i>
        </div>
        <p class="text-gray-500 text-sm">Giỏ hàng của bạn đang trống</p>
        <button onclick="toggleCartDrawer(false)" class="mt-4 px-4 py-2 bg-rose-600 text-white rounded-xl text-xs font-semibold">
          Tiếp tục xem hoa
        </button>
      </div>
    `;
    if (subtotalEl) subtotalEl.textContent = formatCurrency(0);
    if (totalEl) totalEl.textContent = formatCurrency(0);
    return;
  }

  let subtotal = 0;

  listEl.innerHTML = AppState.cart.map((item, index) => {
    let itemPrice = item.product.price;
    const addonsTotal = (item.addons || []).reduce((sum, a) => sum + a.price, 0);
    const itemTotal = (itemPrice + addonsTotal) * item.quantity;
    subtotal += itemTotal;

    return `
      <div class="p-3 bg-gray-50 rounded-xl mb-3 border border-gray-100 flex space-x-3">
        <img src="${item.product.image || item.product.thumbnail}" alt="${item.product.name}" class="w-20 h-20 rounded-lg object-cover flex-shrink-0" />
        
        <div class="flex-1 min-w-0 flex flex-col justify-between">
          <div class="flex items-start justify-between">
            <h4 class="font-semibold text-gray-800 text-sm truncate">${item.product.name}</h4>
            <button onclick="removeCartItem(${index})" class="text-gray-400 hover:text-red-500 text-base">
              <i class="ph ph-trash"></i>
            </button>
          </div>

          <div class="text-xs text-rose-600 font-bold">
            ${formatCurrency(item.product.price)}
          </div>

          ${item.addons && item.addons.length > 0 ? `
            <div class="text-[11px] text-gray-500">
              Kèm: ${item.addons.map(a => a.name).join(', ')} (+${formatCurrency(addonsTotal)})
            </div>
          ` : ''}

          <div class="flex items-center justify-between mt-2">
            <div class="flex items-center border border-gray-300 rounded-lg bg-white overflow-hidden text-xs">
              <button onclick="updateCartItemQty(${index}, -1)" class="px-2 py-1 hover:bg-gray-100 text-gray-600"><i class="ph ph-minus"></i></button>
              <span class="px-2 py-1 font-bold text-gray-800">${item.quantity}</span>
              <button onclick="updateCartItemQty(${index}, 1)" class="px-2 py-1 hover:bg-gray-100 text-gray-600"><i class="ph ph-plus"></i></button>
            </div>
            <span class="font-bold text-gray-900 text-xs">${formatCurrency(itemTotal)}</span>
          </div>
        </div>
      </div>
    `;
  }).join('');

  if (subtotalEl) subtotalEl.textContent = formatCurrency(subtotal);
  if (totalEl) totalEl.textContent = formatCurrency(subtotal);
}

function updateCartItemQty(index, change) {
  if (!AppState.cart[index]) return;
  AppState.cart[index].quantity += change;
  if (AppState.cart[index].quantity <= 0) {
    AppState.cart.splice(index, 1);
  }
  saveCartToStorage();
  updateCartUI();
}

function removeCartItem(index) {
  AppState.cart.splice(index, 1);
  saveCartToStorage();
  updateCartUI();
}

function toggleCartDrawer(open) {
  const drawer = document.getElementById('cartDrawer');
  const backdrop = document.getElementById('cartBackdrop');
  if (!drawer || !backdrop) return;

  if (open) {
    backdrop.classList.remove('hidden');
    drawer.classList.remove('translate-x-full');
    document.body.classList.add('overflow-hidden');
    document.body.classList.add('cart-open');
    updateFloatingActionsVisibility();
  } else {
    drawer.classList.add('translate-x-full');
    backdrop.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
    document.body.classList.remove('cart-open');
    updateFloatingActionsVisibility();
  }
}

function openCheckoutDrawer() {
  if (AppState.cart.length === 0) {
    showToast('Giỏ hàng trống! Hãy chọn một mẫu hoa bạn ưng ý nhé.', 'warning');
    return;
  }
  toggleCartDrawer(false);

  const modal = document.getElementById('checkoutModal');
  if (!modal) return;

  let subtotal = 0;
  AppState.cart.forEach(item => {
    const addonsTotal = (item.addons || []).reduce((sum, a) => sum + a.price, 0);
    subtotal += (item.product.price + addonsTotal) * item.quantity;
  });

  const summaryEl = document.getElementById('checkoutSummaryTotal');
  if (summaryEl) summaryEl.textContent = formatCurrency(subtotal);

  // Tự động điền thông tin nếu đã đăng nhập tài khoản
  if (AppState.currentUser) {
    const senderNameInput = document.querySelector('input[name="senderName"]');
    const senderPhoneInput = document.querySelector('input[name="senderPhone"]');
    if (senderNameInput && !senderNameInput.value) senderNameInput.value = AppState.currentUser.fullName || '';
    if (senderPhoneInput && !senderPhoneInput.value) senderPhoneInput.value = AppState.currentUser.phone || '';
  }

  // Tự động gán ngày giao hoa mặc định là hôm nay nếu chưa chọn
  const dateInput = document.getElementById('checkoutDeliveryDate') || document.querySelector('input[name="deliveryDate"]');
  if (dateInput && !dateInput.value) {
    const today = new Date().toISOString().split('T')[0];
    dateInput.value = today;
    dateInput.min = today;
  }

  modal.classList.remove('hidden');
  document.body.classList.add('overflow-hidden');
  updateFloatingActionsVisibility();
}

function closeCheckoutModal() {
  const modal = document.getElementById('checkoutModal');
  if (modal) {
    modal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
    updateFloatingActionsVisibility();
  }
}

// 3. GỌI API ĐẶT HÀNG THỰC TẾ LƯU VÀO MYSQL (api/checkout.php)
async function handleCheckoutSubmit(event) {
  event.preventDefault();

  const form = event.target;
  const submitBtn = form.querySelector('button[type="submit"]');
  const originalBtnText = submitBtn ? submitBtn.innerHTML : '';

  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.innerHTML = `<i class="ph ph-spinner animate-spin text-base"></i><span>Đang xử lý đơn hoa...</span>`;
  }

  const payload = {
    senderName: form.senderName.value.trim() || 'Khách hàng',
    senderPhone: form.senderPhone.value.trim(),
    isAnonymous: form.isAnonymous.checked ? 1 : 0,
    receiverName: form.receiverName.value.trim(),
    receiverPhone: form.receiverPhone.value.trim(),
    address: form.address.value.trim(),
    city: 'TP. Hồ Chí Minh',
    deliveryDate: form.deliveryDate.value,
    deliveryTime: form.deliveryTime.value,
    bannerMessage: form.bannerMessage.value.trim(),
    paymentMethod: form.paymentMethod.value,
    items: AppState.cart.map(item => ({
      id: item.product.id,
      quantity: item.quantity,
      addons: item.addons || []
    }))
  };

  try {
    const res = await fetch('api/checkout.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    const json = await res.json();

    if (json.success && json.data) {
      // Đặt hàng thành công qua Database
      AppState.lastCreatedOrder = json.data;
      AppState.cart = [];
      saveCartToStorage();
      updateCartUI();
      closeCheckoutModal();
      showOrderSuccessModal(json.data);
      return;
    } else {
      showToast(json.message || 'Lỗi đặt hàng', 'warning');
    }
  } catch (e) {
    console.warn('Lỗi gọi API Checkout, sử dụng fallback:', e);
    // Fallback tạo order local nếu mất kết nối
    const localOrder = {
      orderCode: 'BL' + Math.floor(10000 + Math.random() * 90000),
      totalAmount: 1350000,
      paymentMethod: payload.paymentMethod,
      receiverName: payload.receiverName,
      receiverPhone: payload.receiverPhone,
      address: payload.address,
      deliveryDate: payload.deliveryDate,
      deliveryTime: payload.deliveryTime,
      bannerMessage: payload.bannerMessage,
      vietqrUrl: `https://img.vietqr.io/image/MB-0908123456-compact.png?amount=1350000&addInfo=BLOOMORA&accountName=BLOOMORA%20FLORIST`
    };
    AppState.lastCreatedOrder = localOrder;
    AppState.cart = [];
    saveCartToStorage();
    updateCartUI();
    closeCheckoutModal();
    showOrderSuccessModal(localOrder);
  } finally {
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.innerHTML = originalBtnText;
    }
  }
}

// Modal đặt hàng thành công & Sinh VietQR
function showOrderSuccessModal(order) {
  const modal = document.getElementById('orderSuccessModal');
  const container = document.getElementById('orderSuccessContent');
  if (!modal || !container) return;

  const orderCode = order.orderCode || order.id;
  const isVietQR = order.paymentMethod === 'vietqr';
  const qrUrl = order.vietqrUrl || `https://img.vietqr.io/image/MB-0908123456-compact.png?amount=${order.totalAmount}&addInfo=${orderCode}&accountName=BLOOMORA%20FLORIST`;

  container.innerHTML = `
    <div class="text-center p-6 lg:p-8">
      <div class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl">
        <i class="ph ph-check-circle-bold"></i>
      </div>

      <h3 class="text-2xl font-bold text-gray-900 mb-1 font-serif-title">Đặt Hoa Thành Công!</h3>
      <p class="text-xs text-gray-500 mb-4">Mã đơn hàng: <strong class="text-rose-600 text-sm font-mono">${orderCode}</strong></p>

      <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 mb-6 text-left flex items-start space-x-3 text-xs text-amber-900">
        <i class="ph ph-shield-check text-amber-600 text-xl flex-shrink-0 mt-0.5"></i>
        <div>
          <span class="font-bold block mb-0.5">Cam kết của Bloomora Florist:</span>
          Florist sẽ hoàn tất cắm hoa và gửi ảnh chụp thực tế qua Zalo/SĐT <strong>${order.senderPhone || order.receiverPhone}</strong> để bạn duyệt trước khi shipper xuất phát!
        </div>
      </div>

      ${isVietQR ? `
        <div class="vietqr-box text-left mb-6">
          <div class="flex items-center justify-between pb-3 border-b border-sky-400/40 mb-3">
            <span class="font-bold text-sm tracking-wider">QUÉT MÃ VIETQR TỰ ĐỘNG</span>
            <span class="text-xs bg-white text-sky-700 px-2.5 py-0.5 rounded-full font-bold">24/7 tức thì</span>
          </div>

          <div class="flex flex-col sm:flex-row items-center gap-4 bg-white p-4 rounded-xl text-gray-800">
            <img src="${qrUrl}" alt="Mã VietQR" class="w-44 h-44 object-contain rounded-lg border border-gray-200 shadow-sm" />
            <div class="space-y-1.5 text-xs flex-1">
              <p>Ngân hàng: <strong class="text-sky-700">MBBank (Quân Đội)</strong></p>
              <p>Số tài khoản: <strong class="font-mono text-sm text-gray-900 font-bold">0908 123 456</strong></p>
              <p>Chủ TK: <strong class="uppercase text-gray-900">TIEM HOA BLOOMORA</strong></p>
              <p>Số tiền: <strong class="text-rose-600 font-bold text-sm">${formatCurrency(order.totalAmount)}</strong></p>
              <p>Nội dung CK: <strong class="text-amber-600 font-mono bg-amber-50 px-2 py-0.5 rounded border border-amber-200">${orderCode}</strong></p>
              <p class="text-[11px] text-gray-500 pt-1 italic">* Mở app ngân hàng quét mã QR để chuyển khoản nhanh trong 30 giây.</p>
            </div>
          </div>
        </div>
      ` : `
        <div class="bg-gray-100 rounded-xl p-4 text-left text-xs text-gray-700 mb-6">
          <i class="ph ph-money text-base text-gray-500 mr-1"></i>
          Bạn đã chọn thanh toán <strong>Tiền mặt khi nhận hoa (COD)</strong> với số tiền <strong>${formatCurrency(order.totalAmount)}</strong>. Shipper sẽ liên hệ trước khi giao.
        </div>
      `}

      <div class="bg-gray-50 rounded-xl p-4 text-left text-xs space-y-1 text-gray-600 mb-6">
        <p><strong class="text-gray-800">Người nhận:</strong> ${escapeHtml(order.receiverName)} - ${escapeHtml(order.receiverPhone)}</p>
        <p><strong class="text-gray-800">Địa chỉ giao:</strong> ${escapeHtml(order.address)}</p>
        <p><strong class="text-gray-800">Thời gian giao:</strong> ${escapeHtml(order.deliveryDate)} (${escapeHtml(order.deliveryTime)})</p>
      </div>

      <div class="flex items-center space-x-3">
        <button 
          onclick="openOrderTracking('${orderCode}')"
          class="flex-1 py-3 bg-rose-600 hover:bg-rose-700 text-white rounded-xl font-bold text-sm shadow transition flex items-center justify-center space-x-1"
        >
          <i class="ph ph-map-pin-line text-base"></i>
          <span>Theo Dõi Tiến Độ Đơn Hoa</span>
        </button>
        <button 
          onclick="closeOrderSuccessModal()"
          class="py-3 px-5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-medium text-sm transition"
        >
          Hoàn Tất
        </button>
      </div>
    </div>
  `;

  modal.classList.remove('hidden');
  document.body.classList.add('overflow-hidden');
}

function closeOrderSuccessModal() {
  const modal = document.getElementById('orderSuccessModal');
  if (modal) {
    modal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
    updateFloatingActionsVisibility();
  }
}

// 4. GỌI API TRA CỨU TIẾN ĐỘ ĐƠN HOA (api/tracking.php)
function openOrderTracking(orderId = '') {
  closeOrderSuccessModal();
  const modal = document.getElementById('orderTrackingModal');
  if (!modal) return;

  const inputEl = document.getElementById('trackingInput');
  if (inputEl && orderId) {
    inputEl.value = orderId;
  }

  modal.classList.remove('hidden');
  document.body.classList.add('overflow-hidden');
  updateFloatingActionsVisibility();

  if (orderId) {
    lookupOrder(orderId);
  }
}

function closeOrderTrackingModal() {
  const modal = document.getElementById('orderTrackingModal');
  if (modal) {
    modal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
    updateFloatingActionsVisibility();
  }
}

async function lookupOrder(orderId) {
  const id = orderId || (document.getElementById('trackingInput') ? document.getElementById('trackingInput').value.trim() : '');
  const resultContainer = document.getElementById('trackingResult');
  if (!resultContainer) return;

  if (!id) {
    showToast('Vui lòng nhập mã đơn hàng!', 'warning');
    return;
  }

  resultContainer.innerHTML = `
    <div class="py-8 text-center text-stone-400 text-xs">
      <div class="w-8 h-8 border-3 border-rose-600 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
      Đang kiểm tra dữ liệu đơn hàng...
    </div>
  `;

  try {
    const res = await fetch(`api/tracking.php?code=${encodeURIComponent(id)}`);
    const json = await res.json();

    if (json.success && json.data) {
      renderTrackingResult(json.data.order, json.data.items, json.data.history);
      return;
    }
  } catch (e) {
    console.warn('Lỗi gọi API Tracking, dùng fallback demo:', e);
  }

  // Fallback nếu không tìm thấy trong DB
  resultContainer.innerHTML = `
    <div class="mt-4 p-4 bg-rose-50 border border-rose-200 rounded-xl text-center text-xs text-rose-700">
      Không tìm thấy đơn hàng với mã: <strong>${id}</strong>. Vui lòng kiểm tra lại mã trên hóa đơn hoặc tin nhắn SMS/Zalo.
    </div>
  `;
}

function renderTrackingResult(order, items, history) {
  const resultContainer = document.getElementById('trackingResult');
  if (!resultContainer) return;

  const STATUS_STEPS = [
    { key: 'received', title: '1. Đã tiếp nhận đơn hoa', desc: 'Hệ thống đã nhận đơn và thông báo cho Florist Bloomora.' },
    { key: 'preparing', title: '2. Nghệ nhân đang cắm hoa', desc: 'Hoa tươi tuyển chọn loại 1 Đà Lạt, in băng rôn vi tính.' },
    { key: 'photo_ready', title: '3. Đã chụp ảnh hoa thật', desc: 'Ảnh thực tế hoa cắm xong đã được gửi cho bạn duyệt.' },
    { key: 'delivering', title: '4. Shipper đang đi giao hoa', desc: `Đang vận chuyển hỏa tốc đến ${order.receiver_address}.` },
    { key: 'completed', title: '5. Đã giao thành công', desc: 'Hoa đã trao tận tay người nhận với nụ cười trọn vẹn.' }
  ];

  const currentIdx = STATUS_STEPS.findIndex(s => s.key === order.order_status);
  const activeIdx = currentIdx >= 0 ? currentIdx : 3;

  resultContainer.innerHTML = `
    <div class="mt-6 border-t border-gray-100 pt-6">
      <div class="flex items-center justify-between mb-4">
        <div>
          <span class="text-xs text-gray-500">Mã đơn hàng:</span>
          <h4 class="text-base font-bold text-gray-900 font-mono">${escapeHtml(order.order_code)}</h4>
        </div>
        <span class="px-3 py-1 bg-amber-100 text-amber-800 rounded-full text-xs font-semibold flex items-center">
          <span class="w-2 h-2 rounded-full bg-amber-500 animate-ping mr-2"></span>
          ${escapeHtml(STATUS_STEPS[activeIdx] ? STATUS_STEPS[activeIdx].title : order.order_status)}
        </span>
      </div>

      <!-- Trực quan tiến trình 5 bước -->
      <div class="relative pl-6 space-y-6 before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-rose-200">
        ${STATUS_STEPS.map((step, idx) => {
          const isPassed = idx <= activeIdx;
          const isCurrent = idx === activeIdx;

          return `
            <div class="relative flex items-start space-x-3 ${!isPassed ? 'opacity-40' : ''}">
              <div class="absolute -left-6 w-4 h-4 rounded-full ${
                isCurrent ? 'bg-amber-500 ring-4 ring-amber-100 animate-pulse' : 
                isPassed ? 'bg-emerald-500 ring-4 ring-white' : 
                'bg-gray-300 ring-4 ring-white'
              }"></div>
              <div>
                <h5 class="text-xs font-bold ${isCurrent ? 'text-amber-700' : 'text-gray-900'}">${escapeHtml(step.title)}</h5>
                <p class="text-[11px] text-gray-500">${escapeHtml(step.desc)}</p>
                
                ${step.key === 'photo_ready' && order.photo_proof ? `
                  <div class="mt-2">
                    <span class="text-[10px] font-bold text-rose-600 block mb-1">Ảnh hoa thực tế đã cắm:</span>
                    <a href="${escapeHtml(order.photo_proof)}" target="_blank" rel="noopener noreferrer" class="block w-24 h-24 rounded-lg overflow-hidden border border-rose-300 shadow hover:opacity-90 transition">
                      <img src="${escapeHtml(order.photo_proof)}" alt="Ảnh hoa thực tế" class="w-full h-full object-cover" />
                    </a>
                  </div>
                ` : ''}
              </div>
            </div>
          `;
        }).join('')}
      </div>
    </div>
  `;
}

function renderReviews() {
  const container = document.getElementById('reviewsContainer');
  if (!container || typeof REVIEWS_DATA === 'undefined') return;

  container.innerHTML = REVIEWS_DATA.map(rev => `
    <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm flex flex-col justify-between">
      <div>
        <div class="flex items-center space-x-1 text-amber-400 mb-3">
          ${Array(rev.rating).fill('<i class="ph ph-star-fill text-sm"></i>').join('')}
        </div>
        <p class="text-gray-700 text-sm leading-relaxed mb-4 italic">
          "${rev.comment}"
        </p>
      </div>

      <div class="flex items-center space-x-3 pt-4 border-t border-gray-100">
        <img src="${rev.avatar}" alt="${rev.name}" class="w-10 h-10 rounded-full object-cover border border-rose-100" />
        <div>
          <h5 class="text-sm font-bold text-gray-900">${rev.name}</h5>
          <span class="text-xs text-gray-400">${rev.location} • <strong class="text-rose-500">${rev.product}</strong></span>
        </div>
      </div>
    </div>
  `).join('');
}

function showToast(message, type = 'info') {
  let toastContainer = document.getElementById('toastContainer');
  if (!toastContainer) {
    toastContainer = document.createElement('div');
    toastContainer.id = 'toastContainer';
    toastContainer.className = 'fixed top-5 left-1/2 transform -translate-x-1/2 z-[9999] flex flex-col items-center space-y-2 pointer-events-none';
    document.body.appendChild(toastContainer);
  }

  const toast = document.createElement('div');
  const bgClass = type === 'success' ? 'bg-gray-900 text-white' : type === 'warning' ? 'bg-amber-600 text-white' : 'bg-rose-600 text-white';
  const icon = type === 'success' ? 'ph-check-circle-fill' : type === 'warning' ? 'ph-warning-fill' : 'ph-info-fill';

  toast.className = `toast-animate px-4 py-3 rounded-xl shadow-xl flex items-center space-x-2 text-xs font-semibold pointer-events-auto ${bgClass}`;
  toast.innerHTML = `
    <i class="ph ${icon} text-base"></i>
    <span>${message}</span>
  `;

  toastContainer.appendChild(toast);

  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transition = 'opacity 0.3s ease';
    setTimeout(() => toast.remove(), 300);
  }, 2800);
}

function setupEventListeners() {
  const searchInput = document.getElementById('searchInput');
  let debounceTimeout;
  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      clearTimeout(debounceTimeout);
      debounceTimeout = setTimeout(() => {
        AppState.searchQuery = e.target.value;
        fetchProducts();
      }, 300);
    });
  }

  const heroSearchBtn = document.getElementById('heroSearchBtn');
  if (heroSearchBtn) {
    heroSearchBtn.addEventListener('click', () => {
      const occEl = document.getElementById('heroOccasionSelect');
      const budgetEl = document.getElementById('heroBudgetSelect');

      if (occEl && occEl.value !== 'all') {
        AppState.category = occEl.value;
        renderCategories();
      }
      if (budgetEl && budgetEl.value !== 'all') {
        AppState.priceRange = budgetEl.value;
      }
      fetchProducts();

      const productsSection = document.getElementById('productsSection');
      if (productsSection) {
        productsSection.scrollIntoView({ behavior: 'smooth' });
      }
    });
  }

  const sortSelect = document.getElementById('sortSelect');
  if (sortSelect) {
    sortSelect.addEventListener('change', (e) => {
      AppState.sortBy = e.target.value;
      fetchProducts();
    });
  }

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      closeQuickView();
      closeCheckoutModal();
      closeOrderSuccessModal();
      closeOrderTrackingModal();
      closeAuthModal();
      toggleCartDrawer(false);
      toggleUserDropdown(false);
    }
  });

  // Đóng dropdown khi click ra ngoài
  document.addEventListener('click', (e) => {
    const authUserArea = document.getElementById('headerAuthUser');
    if (authUserArea && !authUserArea.contains(e.target)) {
      toggleUserDropdown(false);
    }
  });
}

// ==========================================
// PHÂN HỆ XÁC THỰC TÀI KHOẢN (AUTHENTICATION)
// ==========================================

// Kiểm tra phiên đăng nhập hiện tại từ API me.php
async function checkAuthSession() {
  try {
    const res = await fetch('api/auth/me.php');
    const json = await res.json();
    if (json.success && json.isLoggedIn && json.data) {
      AppState.currentUser = json.data;
    } else {
      AppState.currentUser = null;
    }
  } catch (e) {
    AppState.currentUser = null;
  }
  updateAuthUI();
}

// Cập nhật giao diện Header theo trạng thái đăng nhập
function updateAuthUI() {
  const guestEl = document.getElementById('headerAuthGuest');
  const userEl = document.getElementById('headerAuthUser');
  if (!guestEl || !userEl) return;

  if (AppState.currentUser) {
    guestEl.classList.add('hidden');
    userEl.classList.remove('hidden');

    const nameEl = document.getElementById('headerUserName');
    const avatarEl = document.getElementById('headerUserAvatar');
    const dropNameEl = document.getElementById('dropdownFullName');
    const dropEmailEl = document.getElementById('dropdownEmail');
    const dropRoleBadge = document.getElementById('dropdownRoleBadge');
    const adminLink = document.getElementById('dropdownAdminLink');

    const displayName = AppState.currentUser.fullName || AppState.currentUser.email;
    if (nameEl) nameEl.textContent = displayName.split(' ').pop(); // Tên ngắn gọn
    if (avatarEl) avatarEl.textContent = displayName.charAt(0).toUpperCase();
    if (dropNameEl) dropNameEl.textContent = displayName;
    if (dropEmailEl) dropEmailEl.textContent = AppState.currentUser.email;

    if (dropRoleBadge) {
      const isStaffOrAdmin = ['admin', 'staff'].includes(AppState.currentUser.role);
      dropRoleBadge.textContent = isStaffOrAdmin ? 'Quản Trị Viên' : 'Thành Viên';
      dropRoleBadge.className = isStaffOrAdmin 
        ? 'inline-block mt-1 text-[10px] bg-amber-100 text-amber-800 font-bold px-2 py-0.5 rounded-full uppercase' 
        : 'inline-block mt-1 text-[10px] bg-rose-50 text-rose-600 font-bold px-2 py-0.5 rounded-full uppercase';
    }

    if (adminLink) {
      if (['admin', 'staff'].includes(AppState.currentUser.role)) {
        adminLink.classList.remove('hidden');
      } else {
        adminLink.classList.add('hidden');
      }
    }
  } else {
    guestEl.classList.remove('hidden');
    userEl.classList.add('hidden');
  }
}

// Bật/tắt dropdown tài khoản
function toggleUserDropdown(forceState) {
  const menu = document.getElementById('userDropdownMenu');
  if (!menu) return;
  if (typeof forceState === 'boolean') {
    menu.classList.toggle('hidden', !forceState);
  } else {
    menu.classList.toggle('hidden');
  }
}

// Mở modal Auth
function openAuthModal(tab = 'login') {
  const modal = document.getElementById('authModal');
  if (!modal) return;
  modal.classList.remove('hidden');
  document.body.classList.add('overflow-hidden');
  updateFloatingActionsVisibility();
  switchAuthTab(tab);
  clearAuthAlert();
}

// Đóng modal Auth
function closeAuthModal() {
  const modal = document.getElementById('authModal');
  if (!modal) return;
  modal.classList.add('hidden');
  document.body.classList.remove('overflow-hidden');
  updateFloatingActionsVisibility();
}

// Chuyển giữa Tab Đăng Nhập & Đăng Ký
function switchAuthTab(tab) {
  const loginForm = document.getElementById('loginForm');
  const registerForm = document.getElementById('registerForm');
  const tabLoginBtn = document.getElementById('tabLoginBtn');
  const tabRegisterBtn = document.getElementById('tabRegisterBtn');

  clearAuthAlert();

  if (tab === 'login') {
    loginForm.classList.remove('hidden');
    registerForm.classList.add('hidden');
    tabLoginBtn.className = 'flex-1 py-2 rounded-xl transition bg-white text-stone-900 shadow-sm';
    tabRegisterBtn.className = 'flex-1 py-2 rounded-xl transition text-stone-500 hover:text-stone-900';
  } else {
    loginForm.classList.add('hidden');
    registerForm.classList.remove('hidden');
    tabLoginBtn.className = 'flex-1 py-2 rounded-xl transition text-stone-500 hover:text-stone-900';
    tabRegisterBtn.className = 'flex-1 py-2 rounded-xl transition bg-white text-stone-900 shadow-sm';
  }
}

// Ẩn/hiện mật khẩu
function toggleInputVisibility(inputId, btn) {
  const input = document.getElementById(inputId);
  if (!input) return;
  const icon = btn.querySelector('i');
  if (input.type === 'password') {
    input.type = 'text';
    if (icon) icon.className = 'ph ph-eye-slash';
  } else {
    input.type = 'password';
    if (icon) icon.className = 'ph ph-eye';
  }
}

// Điền nhanh tài khoản demo
function fillDemoAccount() {
  document.getElementById('loginEmail').value = 'khachhang@gmail.com';
  document.getElementById('loginPassword').value = 'admin123';
  showAuthAlert('Đã điền tài khoản mẫu! Bấm "Đăng Nhập Ngay" để tiếp tục.', 'info');
}

function showAuthAlert(msg, type = 'error') {
  const alertBox = document.getElementById('authAlert');
  if (!alertBox) return;
  alertBox.classList.remove('hidden');
  if (type === 'success') {
    alertBox.className = 'mb-4 p-3 rounded-xl text-xs font-semibold bg-emerald-50 border border-emerald-300 text-emerald-800 block';
  } else if (type === 'info') {
    alertBox.className = 'mb-4 p-3 rounded-xl text-xs font-semibold bg-blue-50 border border-blue-300 text-blue-800 block';
  } else {
    alertBox.className = 'mb-4 p-3 rounded-xl text-xs font-semibold bg-rose-50 border border-rose-300 text-rose-800 block';
  }
  alertBox.textContent = msg;
}

function clearAuthAlert() {
  const alertBox = document.getElementById('authAlert');
  if (alertBox) alertBox.classList.add('hidden');
}

// Xử lý nộp form Đăng Nhập
async function handleCustomerLoginSubmit(e) {
  e.preventDefault();
  const form = e.target;
  const email = form.email.value.trim();
  const password = form.password.value;
  const btn = document.getElementById('loginSubmitBtn');
  const originText = btn.innerHTML;

  btn.disabled = true;
  btn.innerHTML = `<i class="ph ph-spinner animate-spin text-base"></i><span>Đang xác thực...</span>`;
  clearAuthAlert();

  try {
    const res = await fetch('api/auth/login.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, password })
    });
    const json = await res.json();

    if (json.success && json.data) {
      AppState.currentUser = json.data;
      updateAuthUI();
      closeAuthModal();
      showToast(`Chào mừng ${json.data.fullName || 'bạn'} quay trở lại!`, 'success');
    } else {
      showAuthAlert(json.message || 'Email hoặc mật khẩu không chính xác.');
    }
  } catch (err) {
    showAuthAlert('Lỗi kết nối máy chủ. Vui lòng thử lại!');
  } finally {
    btn.disabled = false;
    btn.innerHTML = originText;
  }
}

// Xử lý nộp form Đăng Ký
async function handleCustomerRegisterSubmit(e) {
  e.preventDefault();
  const form = e.target;
  const fullName = form.fullName.value.trim();
  const phone = form.phone.value.trim();
  const email = form.email.value.trim();
  const password = form.password.value;
  const confirmPassword = form.confirmPassword.value;
  const btn = document.getElementById('regSubmitBtn');
  const originText = btn.innerHTML;

  if (password !== confirmPassword) {
    showAuthAlert('Mật khẩu xác nhận không trùng khớp!');
    return;
  }

  if (password.length < 6) {
    showAuthAlert('Mật khẩu phải chứa ít nhất 6 ký tự!');
    return;
  }

  btn.disabled = true;
  btn.innerHTML = `<i class="ph ph-spinner animate-spin text-base"></i><span>Đang tạo tài khoản...</span>`;
  clearAuthAlert();

  try {
    const res = await fetch('api/auth/register.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ fullName, phone, email, password })
    });
    const json = await res.json();

    if (json.success && json.data) {
      AppState.currentUser = json.data;
      updateAuthUI();
      closeAuthModal();
      showToast(`Chúc mừng ${json.data.fullName}, tạo tài khoản thành công!`, 'success');
    } else {
      showAuthAlert(json.message || 'Đăng ký thất bại. Vui lòng kiểm tra lại thông tin.');
    }
  } catch (err) {
    showAuthAlert('Lỗi kết nối máy chủ. Vui lòng thử lại!');
  } finally {
    btn.disabled = false;
    btn.innerHTML = originText;
  }
}

// Xử lý Đăng Xuất
async function handleCustomerLogout() {
  try {
    await fetch('api/auth/logout.php');
  } catch (e) {}
  AppState.currentUser = null;
  updateAuthUI();
  toggleUserDropdown(false);
  closeMyOrdersModal();
  showToast('Đã đăng xuất tài khoản thành công!', 'info');
}

// ==========================================
// PHÂN HỆ: LỊCH SỬ ĐƠN HÀNG CỦA TÔI
// ==========================================
async function openMyOrdersModal() {
  const modal = document.getElementById('myOrdersModal');
  const listContainer = document.getElementById('myOrdersList');
  if (!modal || !listContainer) return;

  modal.classList.remove('hidden');
  document.body.classList.add('overflow-hidden');
  updateFloatingActionsVisibility();

  if (!AppState.currentUser) {
    listContainer.innerHTML = `
      <div class="py-10 text-center text-stone-500 text-xs">
        <i class="ph ph-lock-key text-3xl block mb-2 text-rose-500"></i>
        <p class="font-bold mb-3">Vui lòng đăng nhập để xem danh sách đơn hàng đã đặt!</p>
        <button onclick="closeMyOrdersModal(); openAuthModal('login');" class="px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl font-bold transition">
          Đăng Nhập Ngay
        </button>
      </div>
    `;
    return;
  }

  listContainer.innerHTML = `
    <div class="py-10 text-center text-stone-400 text-xs">
      <div class="w-8 h-8 border-3 border-rose-600 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
      Đang tải danh sách đơn hàng của bạn...
    </div>
  `;

  try {
    const res = await fetch('api/user/orders.php');
    if (res.status === 401) {
      AppState.currentUser = null;
      updateAuthUI();
      openMyOrdersModal();
      return;
    }
    const json = await res.json();

    if (json.success && json.data) {
      renderMyOrders(json.data);
    } else {
      listContainer.innerHTML = `<div class="py-8 text-center text-stone-400 text-xs">${escapeHtml(json.message || 'Chưa có đơn hàng nào')}</div>`;
    }
  } catch (err) {
    listContainer.innerHTML = `<div class="py-8 text-center text-rose-500 text-xs">Lỗi tải dữ liệu từ máy chủ.</div>`;
  }
}

function closeMyOrdersModal() {
  const modal = document.getElementById('myOrdersModal');
  if (modal) {
    modal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
    updateFloatingActionsVisibility();
  }
}

function renderMyOrders(orders) {
  const container = document.getElementById('myOrdersList');
  if (!container) return;

  if (orders.length === 0) {
    container.innerHTML = `
      <div class="py-12 text-center text-stone-400 text-xs">
        <i class="ph ph-bag text-3xl block mb-2 text-stone-300"></i>
        <p class="font-medium text-stone-600 mb-2">Bạn chưa có đơn đặt hoa nào.</p>
        <button onclick="closeMyOrdersModal(); scrollToProducts();" class="px-4 py-2 bg-rose-600 text-white rounded-xl font-bold hover:bg-rose-700 transition">
          Khám Phá Mẫu Hoa Ngay
        </button>
      </div>
    `;
    return;
  }

  const STATUS_LABELS = {
    'received': { text: 'Đã tiếp nhận', cls: 'bg-stone-100 text-stone-700' },
    'preparing': { text: 'Đang cắm hoa', cls: 'bg-amber-100 text-amber-800' },
    'photo_ready': { text: 'Đã có ảnh duyệt', cls: 'bg-blue-100 text-blue-800' },
    'delivering': { text: 'Đang giao hàng', cls: 'bg-purple-100 text-purple-800' },
    'completed': { text: 'Đã hoàn thành', cls: 'bg-emerald-100 text-emerald-800' },
    'cancelled': { text: 'Đã hủy', cls: 'bg-rose-100 text-rose-800' },
  };

  container.innerHTML = orders.map(ord => {
    const st = STATUS_LABELS[ord.order_status] || { text: ord.order_status, cls: 'bg-stone-100 text-stone-700' };
    const itemsHtml = (ord.items && ord.items.length > 0)
      ? ord.items.map(it => `
          <div class="flex items-center justify-between text-xs py-1 border-b border-stone-100 last:border-0">
            <span class="text-stone-700">• ${escapeHtml(it.product_name)} <strong class="text-rose-600 font-mono">x${it.quantity}</strong></span>
            <span class="font-mono text-stone-900">${formatCurrency(it.total_price)}</span>
          </div>
        `).join('')
      : '<span class="text-stone-400 text-xs italic">Chi tiết đơn hàng</span>';

    return `
      <div class="bg-stone-50 border border-stone-200/80 rounded-2xl p-4 space-y-3">
        <div class="flex items-center justify-between pb-2 border-b border-stone-200">
          <div>
            <span class="text-[10px] text-stone-400 font-bold uppercase block">MÃ ĐƠN HÀNG</span>
            <span class="font-mono font-bold text-rose-600 text-sm">${escapeHtml(ord.order_code)}</span>
          </div>
          <div class="text-right">
            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold ${st.cls}">
              ${st.text}
            </span>
          </div>
        </div>

        <div>
          ${itemsHtml}
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[11px] text-stone-500 pt-2 border-t border-stone-100">
          <div>
            <span>Giao đến: <strong class="text-stone-800">${escapeHtml(ord.receiver_name)}</strong> (${escapeHtml(ord.receiver_phone)})</span>
            <p class="truncate text-stone-600">${escapeHtml(ord.receiver_address)}</p>
          </div>
          <div class="sm:text-right">
            <span>Hẹn giờ: <strong class="text-stone-800">${escapeHtml(ord.delivery_date)}</strong> (${escapeHtml(ord.delivery_time_slot)})</span>
            <div class="mt-1 font-bold text-rose-600 text-sm font-serif-title">${formatCurrency(ord.total_amount)}</div>
          </div>
        </div>

        <div class="flex items-center justify-between pt-2 border-t border-stone-200">
          <span class="text-[10px] text-stone-400 font-medium">Đặt lúc: ${escapeHtml(ord.created_at || '')}</span>
          <button 
            onclick="closeMyOrdersModal(); openOrderTracking('${escapeHtml(ord.order_code)}');"
            class="px-3 py-1.5 bg-stone-900 hover:bg-black text-white text-xs font-semibold rounded-lg shadow transition flex items-center space-x-1"
          >
            <i class="ph ph-map-pin"></i>
            <span>Xem Hành Trình</span>
          </button>
        </div>
      </div>
    `;
  }).join('');
}

// ==========================================================================
// TỰ ĐỘNG KIỂM SOÁT HIỂN THỊ CỦA NÚT NỔI & TRỢ LÝ AI (CHỈ HIỂN THỊ Ở TRANG NGOÀI)
// ==========================================================================
function updateFloatingActionsVisibility() {
  const floatingGroup = document.getElementById('floatingActionsGroup');
  const chatModal = document.getElementById('bloomora-chat-modal');
  if (!floatingGroup && !chatModal) return;

  const cartDrawer = document.getElementById('cartDrawer');
  const isCartOpen = cartDrawer && !cartDrawer.classList.contains('translate-x-full');
  const isCheckoutOpen = document.getElementById('checkoutModal') && !document.getElementById('checkoutModal').classList.contains('hidden');
  const isQuickViewOpen = document.getElementById('quickViewModal') && !document.getElementById('quickViewModal').classList.contains('hidden');
  const isAuthOpen = document.getElementById('authModal') && !document.getElementById('authModal').classList.contains('hidden');
  const isOrdersOpen = document.getElementById('myOrdersModal') && !document.getElementById('myOrdersModal').classList.contains('hidden');
  const isTrackingOpen = document.getElementById('orderTrackingModal') && !document.getElementById('orderTrackingModal').classList.contains('hidden');
  const isSuccessOpen = document.getElementById('orderSuccessModal') && !document.getElementById('orderSuccessModal').classList.contains('hidden');
  const isLightboxOpen = document.getElementById('reviewImageLightbox') && !document.getElementById('reviewImageLightbox').classList.contains('hidden');
  const isBodyLocked = document.body.classList.contains('overflow-hidden') || document.body.classList.contains('cart-open');

  const shouldHide = isCartOpen || isCheckoutOpen || isQuickViewOpen || isAuthOpen || isOrdersOpen || isTrackingOpen || isSuccessOpen || isLightboxOpen || isBodyLocked;

  if (shouldHide) {
    if (floatingGroup) {
      floatingGroup.style.setProperty('display', 'none', 'important');
      floatingGroup.classList.add('hidden-by-modal');
    }
    if (chatModal) {
      chatModal.classList.remove('open');
      chatModal.classList.add('hidden-by-modal');
    }
  } else {
    if (floatingGroup) {
      floatingGroup.style.display = 'flex';
      floatingGroup.classList.remove('hidden-by-modal');
    }
    if (chatModal) {
      chatModal.classList.remove('hidden-by-modal');
    }
  }
}
window.updateFloatingActionsVisibility = updateFloatingActionsVisibility;

// Khởi chạy theo dõi đa sự kiện để bảo đảm không bị ghim hay đè lên các trang chức năng
['click', 'keydown', 'touchend', 'popstate', 'resize'].forEach(evt => {
  document.addEventListener(evt, () => setTimeout(updateFloatingActionsVisibility, 50), { passive: true });
});
document.addEventListener('DOMContentLoaded', updateFloatingActionsVisibility);

