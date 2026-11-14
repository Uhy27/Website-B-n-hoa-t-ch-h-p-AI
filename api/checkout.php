<?php
/**
 * API: Tạo Đơn Hàng Mới (Checkout Transaction)
 * Endpoint: POST /api/checkout.php
 * Chống gian lận giá F12, tính toán tiền ở Server, trừ tồn kho & sinh mã VietQR chuẩn
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

init_secure_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Chỉ chấp nhận phương thức POST'], 405);
}

try {
    $pdo = getDBConnection();

    // Nhận payload JSON an toàn
    $input = get_json_input();

    $senderName   = isset($input['senderName']) ? trim($input['senderName']) : 'Khách hàng';
    $senderPhone  = isset($input['senderPhone']) ? trim($input['senderPhone']) : '';
    $isAnonymous  = !empty($input['isAnonymous']) ? 1 : 0;
    
    $receiverName = isset($input['receiverName']) ? trim($input['receiverName']) : '';
    $receiverPhone = isset($input['receiverPhone']) ? trim($input['receiverPhone']) : '';
    $address      = isset($input['address']) ? trim($input['address']) : '';
    $city         = isset($input['city']) ? trim($input['city']) : 'TP. Hồ Chí Minh';
    
    $deliveryDate = isset($input['deliveryDate']) ? trim($input['deliveryDate']) : date('Y-m-d');
    $deliveryTime = isset($input['deliveryTime']) ? trim($input['deliveryTime']) : 'Hỏa tốc 60-90 phút';
    
    $bannerMessage = isset($input['bannerMessage']) ? trim($input['bannerMessage']) : '';
    $paymentMethod = isset($input['paymentMethod']) && in_array($input['paymentMethod'], ['vietqr', 'cod'], true) 
                     ? $input['paymentMethod'] : 'vietqr';
    $items         = isset($input['items']) && is_array($input['items']) ? $input['items'] : [];

    // Nhận diện tài khoản đang đăng nhập (nếu có)
    $userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

    // Validation thông tin người nhận và người đặt
    if (empty($receiverName) || empty($receiverPhone) || empty($address)) {
        json_response(['success' => false, 'message' => 'Vui lòng cung cấp đầy đủ tên người nhận, số điện thoại và địa chỉ giao hoa!'], 400);
    }

    if (empty($items)) {
        json_response(['success' => false, 'message' => 'Giỏ hàng của bạn đang trống!'], 400);
    }

    // BẮT ĐẦU TRANSACTION AN TOÀN
    $pdo->beginTransaction();

    // 1. Tính toán giá tiền thực tế từ Database (Không bao giờ tin Client)
    $subtotal = 0;
    $orderItemsData = [];

    // Bảng giá phụ kiện đính kèm chính thức từ hệ thống
    $addonPrices = [
        'gau-bong' => ['name' => 'Gấu Bông Teddy Cao Cấp', 'price' => 80000],
        'socola'   => ['name' => 'Hộp Socola Ferrero Rocher', 'price' => 120000],
        'nen-thom' => ['name' => 'Nến Thơm Tinh Dầu Tự Nhiên', 'price' => 150000],
        'banh-kem' => ['name' => 'Bánh Kem Mini Chúc Mừng', 'price' => 180000]
    ];

    $productStmt = $pdo->prepare("SELECT id, name, sku, price, stock_quantity, thumbnail 
                                  FROM products 
                                  WHERE (id = :id OR sku = :sku) AND is_active = 1 
                                  LIMIT 1 FOR UPDATE");

    $updateStockStmt = $pdo->prepare("UPDATE products 
                                     SET stock_quantity = GREATEST(0, stock_quantity - :qty) 
                                     WHERE id = :id");

    foreach ($items as $item) {
        $itemId = isset($item['id']) ? $item['id'] : (isset($item['productId']) ? $item['productId'] : null);
        $qty = max(1, (int)($item['quantity'] ?? 1));

        if (!$itemId) {
            continue;
        }

        $productStmt->execute([':id' => $itemId, ':sku' => $itemId]);
        $prod = $productStmt->fetch();

        if (!$prod) {
            continue;
        }

        // Kiểm tra số lượng tồn kho
        if ($prod['stock_quantity'] < $qty) {
            $pdo->rollBack();
            json_response([
                'success' => false, 
                'message' => "Mẫu hoa '{$prod['name']}' hiện chỉ còn {$prod['stock_quantity']} sản phẩm trong kho!"
            ], 400);
        }

        $unitPrice = (float)$prod['price'];
        
        // Tính tiền addons kèm theo nếu có
        $addonsExtra = 0;
        $addonNames = [];
        if (!empty($item['addons']) && is_array($item['addons'])) {
            foreach ($item['addons'] as $add) {
                $aid = is_array($add) ? ($add['id'] ?? '') : $add;
                if (isset($addonPrices[$aid])) {
                    $addonsExtra += $addonPrices[$aid]['price'];
                    $addonNames[] = $addonPrices[$aid]['name'];
                }
            }
        }

        $lineTotal = ($unitPrice + $addonsExtra) * $qty;
        $subtotal += $lineTotal;

        $displayName = $prod['name'];
        if (!empty($addonNames)) {
            $displayName .= ' (Kèm: ' . implode(', ', $addonNames) . ')';
        }

        $orderItemsData[] = [
            'product_id' => $prod['id'],
            'product_name' => $displayName,
            'product_sku' => $prod['sku'],
            'unit_price' => $unitPrice + $addonsExtra,
            'quantity' => $qty,
            'total_price' => $lineTotal,
            'thumbnail' => $prod['thumbnail']
        ];

        // Tự động trừ kho sản phẩm
        $updateStockStmt->execute([':qty' => $qty, ':id' => $prod['id']]);
    }

    if (empty($orderItemsData)) {
        $pdo->rollBack();
        json_response(['success' => false, 'message' => 'Các sản phẩm trong giỏ hàng hiện không hợp lệ hoặc đã ngừng bán!'], 400);
    }

    $shippingFee = 0; // Chính sách: Miễn phí giao hoa nội thành
    $totalAmount = $subtotal + $shippingFee;

    // 2. Tạo Mã Đơn Hàng Độc Nhất (BL + 5 chữ số ngẫu nhiên)
    $maxAttempts = 10;
    $orderCode = '';
    do {
        $orderCode = 'BL' . mt_rand(10000, 99999);
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE order_code = :code");
        $checkStmt->execute([':code' => $orderCode]);
        $maxAttempts--;
    } while ($checkStmt->fetchColumn() > 0 && $maxAttempts > 0);

    if (empty($orderCode)) {
        $orderCode = 'BL' . date('ymd') . mt_rand(100, 999);
    }

    // 3. Insert vào bảng orders (Gắn user_id nếu khách hàng đã đăng nhập)
    $orderSql = "INSERT INTO orders (
                    order_code, user_id, sender_name, sender_phone, is_anonymous,
                    receiver_name, receiver_phone, receiver_address, city,
                    delivery_date, delivery_time_slot, card_message, ribbon_message,
                    subtotal, shipping_fee, total_amount, payment_method,
                    payment_status, order_status
                 ) VALUES (
                    :order_code, :user_id, :sender_name, :sender_phone, :is_anonymous,
                    :receiver_name, :receiver_phone, :receiver_address, :city,
                    :delivery_date, :delivery_time_slot, :card_message, :ribbon_message,
                    :subtotal, :shipping_fee, :total_amount, :payment_method,
                    'unpaid', 'received'
                 )";

    $orderStmt = $pdo->prepare($orderSql);
    $orderStmt->execute([
        ':order_code' => $orderCode,
        ':user_id' => $userId,
        ':sender_name' => $senderName,
        ':sender_phone' => $senderPhone,
        ':is_anonymous' => $isAnonymous,
        ':receiver_name' => $receiverName,
        ':receiver_phone' => $receiverPhone,
        ':receiver_address' => $address,
        ':city' => $city,
        ':delivery_date' => $deliveryDate,
        ':delivery_time_slot' => $deliveryTime,
        ':card_message' => $bannerMessage,
        ':ribbon_message' => $bannerMessage,
        ':subtotal' => $subtotal,
        ':shipping_fee' => $shippingFee,
        ':total_amount' => $totalAmount,
        ':payment_method' => $paymentMethod
    ]);

    $orderId = $pdo->lastInsertId();

    // 4. Insert vào bảng order_items (Lưu snapshot giá chuẩn tại thời điểm mua)
    $itemInsertSql = "INSERT INTO order_items (order_id, product_id, product_name, product_sku, unit_price, quantity, total_price)
                      VALUES (:order_id, :product_id, :product_name, :product_sku, :unit_price, :quantity, :total_price)";
    $itemStmt = $pdo->prepare($itemInsertSql);

    foreach ($orderItemsData as $item) {
        $itemStmt->execute([
            ':order_id' => $orderId,
            ':product_id' => $item['product_id'],
            ':product_name' => $item['product_name'],
            ':product_sku' => $item['product_sku'],
            ':unit_price' => $item['unit_price'],
            ':quantity' => $item['quantity'],
            ':total_price' => $item['total_price']
        ]);
    }

    // 5. Ghi log lịch sử trạng thái ban đầu
    $historySql = "INSERT INTO order_status_history (order_id, status, note, created_by)
                   VALUES (:order_id, 'received', 'Hệ thống đã tiếp nhận đơn đặt hoa thành công', :created_by)";
    $historyStmt = $pdo->prepare($historySql);
    $historyStmt->execute([
        ':order_id' => $orderId,
        ':created_by' => $userId
    ]);

    // COMMIT TOÀN BỘ TRANSACTION
    $pdo->commit();

    // Sinh link VietQR chuẩn NAPAS 24/7
    $vietqrUrl = "https://img.vietqr.io/image/MB-0908123456-compact.png?amount={$totalAmount}&addInfo={$orderCode}&accountName=BLOOMORA%20FLORIST";

    json_response([
        'success' => true,
        'message' => 'Đặt hoa thành công!',
        'data' => [
            'orderId' => (int)$orderId,
            'orderCode' => $orderCode,
            'totalAmount' => $totalAmount,
            'paymentMethod' => $paymentMethod,
            'vietqrUrl' => $vietqrUrl,
            'senderName' => $isAnonymous ? 'Người tặng ẩn danh' : $senderName,
            'receiverName' => $receiverName,
            'receiverPhone' => $receiverPhone,
            'address' => $address,
            'deliveryDate' => $deliveryDate,
            'deliveryTime' => $deliveryTime,
            'bannerMessage' => $bannerMessage
        ]
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("[Checkout Error] " . $e->getMessage());
    json_response([
        'success' => false,
        'message' => 'Không thể hoàn tất đơn hàng. Vui lòng kiểm tra lại thông tin hoặc thử lại sau ít phút.'
    ], 500);
}
?>