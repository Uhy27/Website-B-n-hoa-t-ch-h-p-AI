/**
 * Bloomora AI Shopping & Flower Consultation Chatbot Widget
 * Phase 1: Grounded Tool-Calling & Dynamic Product Cards
 */

(function () {
  'use strict';

  // 1. Session Initialization
  const STORAGE_KEY = 'bloomora_ai_session_id';
  let sessionId = localStorage.getItem(STORAGE_KEY);
  if (!sessionId) {
    sessionId = 'sess_' + Math.random().toString(36).substring(2, 12) + Date.now().toString(36);
    localStorage.setItem(STORAGE_KEY, sessionId);
  }

  let isOpen = false;
  let isSending = false;

  // 2. Build DOM Elements
  function createWidgetDOM() {
    let btn = document.getElementById('bloomora-chat-btn');
    if (!btn) {
      btn = document.createElement('button');
      btn.id = 'bloomora-chat-btn';
      btn.className = 'bloomora-chat-launcher';
      btn.setAttribute('aria-label', 'Mở Trợ lý AI Bloomora');
      btn.innerHTML = `
        <span class="bloomora-launcher-badge">🌸</span>
        <span class="font-bold text-xs tracking-wide">Trợ lý AI Bloomora</span>
      `;
      btn.style.position = 'fixed';
      btn.style.bottom = '24px';
      btn.style.right = '24px';
      btn.style.zIndex = '9999';
      document.body.appendChild(btn);
    }

    // Modal container
    let modal = document.getElementById('bloomora-chat-modal');
    if (!modal) {
      modal = document.createElement('div');
      modal.id = 'bloomora-chat-modal';
      modal.className = 'bloomora-chat-window';
      modal.setAttribute('role', 'dialog');
      modal.setAttribute('aria-modal', 'true');
      modal.setAttribute('aria-label', 'Cửa sổ tư vấn hoa AI');
      modal.innerHTML = `
        <!-- Header -->
        <div class="bloomora-chat-header">
          <div class="bloomora-header-info">
            <div class="bloomora-header-avatar">
              <i class="ph ph-flower-lotus"></i>
            </div>
            <div class="bloomora-header-text">
              <h3>Trợ lý Hoa Bloomora</h3>
              <div class="bloomora-header-status">
                <span class="bloomora-status-dot"></span>
                <span>Tư vấn trực tuyến • Chuẩn giá thật</span>
              </div>
            </div>
          </div>
          <button id="bloomora-chat-close" class="bloomora-chat-close-btn" aria-label="Đóng chat">
            <i class="ph ph-x"></i>
          </button>
        </div>

        <!-- Messages Body -->
        <div id="bloomora-chat-msgs" class="bloomora-chat-messages"></div>

        <!-- Footer Input -->
        <form id="bloomora-chat-form" class="bloomora-chat-footer">
          <input type="text" id="bloomora-chat-input" class="bloomora-chat-input" 
                 placeholder="Ví dụ: Hoa trắng dưới 500k tặng mẹ..." 
                 autocomplete="off" maxlength="300" required />
          <button type="submit" id="bloomora-chat-send" class="bloomora-chat-send-btn" aria-label="Gửi tin nhắn">
            <i class="ph ph-paper-plane-right-fill"></i>
          </button>
        </form>
      `;
      document.body.appendChild(modal);
    }

    // Event listeners
    const closeBtn = document.getElementById('bloomora-chat-close');
    const form = document.getElementById('bloomora-chat-form');
    const input = document.getElementById('bloomora-chat-input');

    btn.addEventListener('click', toggleChat);
    if (closeBtn) closeBtn.addEventListener('click', toggleChat);

    if (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        const val = input.value.trim();
        if (!val || isSending) return;
        sendMessage(val);
        input.value = '';
      });
    }

    // Close on Escape
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && isOpen) {
        toggleChat();
      }
    });

    // Initial greeting bubble
    showInitialGreeting();
  }

  function toggleChat() {
    isOpen = !isOpen;
    const modal = document.getElementById('bloomora-chat-modal');
    const input = document.getElementById('bloomora-chat-input');

    if (isOpen) {
      modal.classList.add('open');
      setTimeout(() => input.focus(), 200);
      scrollToBottom();
    } else {
      modal.classList.remove('open');
    }
  }

  function showInitialGreeting() {
    const greetingText = `Dạ Bloomora kính chào quý khách! 🌸\n\nEm là **Trợ lý AI của Bloomora**. Em có thể tư vấn mẫu hoa theo **dịp tặng**, **đối tượng nhận**, **màu sắc** và **ngân sách** thật từ tiệm hoa.\n\nBạn có thể thử bấm các gợi ý bên dưới hoặc nhắn trực tiếp cho em nhé:`;
    const initialChips = [
      'Hoa trắng dưới 500k tặng mẹ',
      'Hoa hồng đỏ tặng người yêu',
      'Kệ hoa khai trương tài lộc',
      'Cách giữ hoa tươi lâu'
    ];
    renderBotBubble(greetingText, [], initialChips);
  }

  // 3. Send Message to Backend API
  async function sendMessage(text) {
    if (isSending) return;
    isSending = true;

    // Render User Message
    renderUserBubble(text);
    const sendBtn = document.getElementById('bloomora-chat-send');
    sendBtn.disabled = true;

    // Show Typing Indicator
    const typingId = showTypingIndicator();
    scrollToBottom();

    try {
      const response = await fetch('api/chat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          session_id: sessionId,
          message: text
        })
      });

      const data = await response.json();
      removeTypingIndicator(typingId);

      if (data && data.success) {
        renderBotBubble(data.message, data.products || [], data.quick_replies || []);
      } else {
        renderBotBubble(
          data.message || 'Dạ hệ thống tư vấn đang bận một chút, quý khách có thể liên hệ hotline 1900 8888 để được hỗ trợ ngay ạ!',
          [],
          ['Liên hệ hotline', 'Xem tất cả sản phẩm']
        );
      }
    } catch (err) {
      console.error('[Chatbot Error]', err);
      removeTypingIndicator(typingId);
      renderBotBubble(
        'Dạ kết nối với hệ thống tư vấn gặp gián đoạn. Bạn thử lại sau ít giây hoặc gọi trực tiếp Hotline 1900 8888 nhé!',
        [],
        ['Thử lại', 'Xem hoa sinh nhật']
      );
    } finally {
      isSending = false;
      sendBtn.disabled = false;
      scrollToBottom();
    }
  }

  // 4. Render Message Bubbles
  function renderUserBubble(text) {
    const msgsContainer = document.getElementById('bloomora-chat-msgs');
    const row = document.createElement('div');
    row.className = 'bloomora-msg-row user';
    row.innerHTML = `
      <div class="bloomora-msg-bubble">
        ${escapeHtml(text)}
        <span class="bloomora-msg-time">${getCurrentTime()}</span>
      </div>
    `;
    msgsContainer.appendChild(row);
  }

  function renderBotBubble(text, products = [], quickReplies = []) {
    const msgsContainer = document.getElementById('bloomora-chat-msgs');
    const row = document.createElement('div');
    row.className = 'bloomora-msg-row bot';

    let formattedText = formatMarkdown(text);

    let html = `
      <div class="bloomora-msg-bubble">
        <div>${formattedText}</div>
    `;

    // Render Grounded Product Cards from JSON Payload
    if (products && products.length > 0) {
      html += `<div class="bloomora-product-cards">`;
      products.forEach(p => {
        const thumb = p.thumbnail || 'https://images.unsplash.com/photo-1561181286-d3fee7d55364?auto=format&fit=crop&w=400&q=80';
        const price = p.formatted_price || formatPrice(p.price);
        const reasonTag = p.match_reasons && p.match_reasons[0] ? p.match_reasons[0] : (p.category_name || 'Hoa tươi thiết kế');
        const scoreBadge = p.score ? `<span class="bloomora-card-tag">⭐ ${p.score}% phù hợp</span>` : '';

        html += `
          <div class="bloomora-product-card" data-product-id="${p.id}">
            <img class="bloomora-card-thumb" src="${thumb}" alt="${escapeHtml(p.name)}" loading="lazy" 
                 onerror="this.src='https://images.unsplash.com/photo-1561181286-d3fee7d55364?auto=format&fit=crop&w=400&q=80'" />
            <div class="bloomora-card-body">
              <div class="bloomora-card-title">${escapeHtml(p.name)}</div>
              <div class="bloomora-card-meta">
                <span class="bloomora-card-price">${price}</span>
                ${scoreBadge}
              </div>
              <div class="bloomora-card-actions">
                <button type="button" class="bloomora-card-btn-view" onclick="window.bloomoraChatOpenDetail(${p.id})">
                  <i class="ph ph-eye"></i> Xem mẫu
                </button>
                <button type="button" class="bloomora-card-btn-cart" onclick="window.bloomoraChatAddToCart(${p.id})">
                  <i class="ph ph-shopping-bag"></i> Mua ngay
                </button>
              </div>
            </div>
          </div>
        `;
      });
      html += `</div>`;
    }

    // Render Quick Action Chips
    if (quickReplies && quickReplies.length > 0) {
      html += `<div class="bloomora-quick-replies">`;
      quickReplies.forEach(chip => {
        html += `<button type="button" class="bloomora-chip" onclick="window.bloomoraChatSendChip('${escapeHtml(chip)}')">${escapeHtml(chip)}</button>`;
      });
      html += `</div>`;
    }

    html += `
        <span class="bloomora-msg-time">${getCurrentTime()}</span>
      </div>
    `;

    row.innerHTML = html;
    msgsContainer.appendChild(row);
    scrollToBottom();
  }

  // 5. Typing Indicator
  function showTypingIndicator() {
    const msgsContainer = document.getElementById('bloomora-chat-msgs');
    const id = 'typing-' + Date.now();
    const row = document.createElement('div');
    row.id = id;
    row.className = 'bloomora-msg-row bot';
    row.innerHTML = `
      <div class="bloomora-typing-bubble">
        <span class="bloomora-dot"></span>
        <span class="bloomora-dot"></span>
        <span class="bloomora-dot"></span>
      </div>
    `;
    msgsContainer.appendChild(row);
    return id;
  }

  function removeTypingIndicator(id) {
    const el = document.getElementById(id);
    if (el) el.remove();
  }

  function scrollToBottom() {
    const msgsContainer = document.getElementById('bloomora-chat-msgs');
    if (msgsContainer) {
      msgsContainer.scrollTop = msgsContainer.scrollHeight;
    }
  }

  // 6. Global Hooks for Product Card Actions
  window.bloomoraChatOpenDetail = function (productId) {
    if (typeof window.openQuickView === 'function') {
      window.openQuickView(productId);
    } else {
      const card = document.querySelector(`.product-card[data-id="${productId}"]`);
      if (card) {
        card.scrollIntoView({ behavior: 'smooth' });
        card.classList.add('ring-4', 'ring-rose-500');
        setTimeout(() => card.classList.remove('ring-4', 'ring-rose-500'), 2500);
      }
    }
  };

  window.bloomoraChatAddToCart = function (productId) {
    if (typeof window.addToCart === 'function') {
      window.addToCart(productId, 1);
      if (typeof window.showToast === 'function') {
        window.showToast('Đã thêm sản phẩm từ Trợ lý AI vào giỏ hàng!', 'success');
      }
    } else {
      window.bloomoraChatOpenDetail(productId);
    }
  };

  window.bloomoraChatSendChip = function (query) {
    if (isSending) return;
    sendMessage(query);
  };

  // Helper Utilities
  function escapeHtml(str) {
    if (!str) return '';
    return str.toString()
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function formatMarkdown(text) {
    if (!text) return '';
    let s = escapeHtml(text);
    // Bold **text**
    s = s.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    // Bullet points
    s = s.replace(/^[•\-\*]\s+(.*)$/gm, '&bull; $1');
    // Line breaks
    s = s.replace(/\n/g, '<br>');
    return s;
  }

  function formatPrice(num) {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(num);
  }

  function getCurrentTime() {
    const d = new Date();
    return d.getHours().toString().padStart(2, '0') + ':' + d.getMinutes().toString().padStart(2, '0');
  }

  // 7. Auto-hide floating actions & chatbot whenever ANY modal or drawer is open
  function checkModalsState() {
    const cartDrawer = document.getElementById('cartDrawer');
    const isCartOpen = cartDrawer && !cartDrawer.classList.contains('translate-x-full');
    const isCheckoutOpen = document.getElementById('checkoutModal') && !document.getElementById('checkoutModal').classList.contains('hidden');
    const isQuickViewOpen = document.getElementById('quickViewModal') && !document.getElementById('quickViewModal').classList.contains('hidden');
    const isAuthOpen = document.getElementById('authModal') && !document.getElementById('authModal').classList.contains('hidden');
    const isOrdersOpen = document.getElementById('myOrdersModal') && !document.getElementById('myOrdersModal').classList.contains('hidden');
    const isTrackingOpen = document.getElementById('orderTrackingModal') && !document.getElementById('orderTrackingModal').classList.contains('hidden');
    const isSuccessOpen = document.getElementById('orderSuccessModal') && !document.getElementById('orderSuccessModal').classList.contains('hidden');
    const isBodyLocked = document.body.classList.contains('overflow-hidden');

    const shouldHide = isCartOpen || isCheckoutOpen || isQuickViewOpen || isAuthOpen || isOrdersOpen || isTrackingOpen || isSuccessOpen || isBodyLocked;

    const floatingGroup = document.getElementById('floatingActionsGroup');
    const chatModal = document.getElementById('bloomora-chat-modal');

    if (shouldHide) {
      if (floatingGroup) floatingGroup.classList.add('hidden-by-modal');
      if (chatModal) {
        chatModal.classList.remove('open');
        chatModal.classList.add('hidden-by-modal');
        isOpen = false;
      }
    } else {
      if (floatingGroup) floatingGroup.classList.remove('hidden-by-modal');
      if (chatModal) chatModal.classList.remove('hidden-by-modal');
    }
  }

  // Setup observer and event listeners
  function initModalStateWatcher() {
    const observer = new MutationObserver(checkModalsState);
    observer.observe(document.body, { attributes: true, attributeFilter: ['class'] });

    const drawer = document.getElementById('cartDrawer');
    if (drawer) {
      observer.observe(drawer, { attributes: true, attributeFilter: ['class'] });
    }

    ['click', 'keydown', 'touchend'].forEach(evt => {
      document.addEventListener(evt, () => setTimeout(checkModalsState, 80), { passive: true });
    });

    checkModalsState();
  }

  // Initialize on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      createWidgetDOM();
      initModalStateWatcher();
    });
  } else {
    createWidgetDOM();
    initModalStateWatcher();
  }

})();
