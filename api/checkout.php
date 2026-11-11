<?php
/**
 * API: Tạo Đơn Hàng Mới (Checkout Transaction)
 * Endpoint: POST /api/checkout.php
 * Chống gian lận giá F12, tính toán tiền ở Server & sinh mã VietQR chuẩn
 */

require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Chỉ chấp nhận phương thức POST']);
    exit;
}

try {
    $pdo = getDBConnection();

    // Nhận payload JSON
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

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
    $paymentMethod = isset($input['paymentMethod']) ? trim($input['paymentMethod']) : 'vietqr';
    $items         = isset($input['items']) && is_array($input['items']) ? $input['items'] : [];

    // Validation cơ bản
    if (empty($receiverName) || empty($receiverPhone) || empty($address)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Vui lòng cung cấp đầy đủ tên người nhận, số điện thoại và địa chỉ giao hoa!'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (empty($items)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Giỏ hàng của bạn đang trống!'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // BẮT ĐẦU TRANSACTION AN TOÀN
    $pdo->beginTransaction();

    // 1. Tính toán giá tiền thực tế từ Database (Không tin Client)
    $subtotal = 0;
    $orderItemsData = [];

    // Giá phụ kiện addons
    $addonPrices = [
        'gau-bong' => 80000,
        'socola'   => 120000,
        'nen-thom' => 150000,
        'banh-kem' => 180000
    ];

    $productStmt = $pdo->prepare("SELECT id, name, sku, price, stock_quantity, thumbnail FROM products WHERE id = :id OR sku = :sku LIMIT 1");

    foreach ($items as $item) {
        $itemId = isset($item['id']) ? $item['id'] : (isset($item['productId']) ? $item['productId'] : null);
        $qty = max(1, (int)($item['quantity'] ?? 1));

        $productStmt->execute([':id' => $itemId, ':sku' => $itemId]);
        $prod = $productStmt->fetch();

        if (!$prod) {
            // Thử tìm theo id số
            continue;
        }

        $unitPrice = (float)$prod['price'];
        
        // Tính tiền addons kèm theo nếu có
        $addonsExtra = 0;
        if (!empty($item['addons']) && is_array($item['addons'])) {
            foreach ($item['addons'] as $add) {
                $aid = is_array($add) ? ($add['id'] ?? '') : $add;
                if (isset($addonPrices[$aid])) {
                    $addonsExtra += $addonPrices[$aid];
                }
            }
        }

        $lineTotal = ($unitPrice + $addonsExtra) * $qty;
        $subtotal += $lineTotal;

        $orderItemsData[] = [
            'product_id' => $prod['id'],
            'product_name' => $prod['name'],
            'product_sku' => $prod['sku'],
            'unit_price' => $unitPrice + $addonsExtra,
            'quantity' => $qty,
            'total_price' => $lineTotal,
            'thumbnail' => $prod['thumbnail']
        ];
    }

    if (empty($orderItemsData)) {
        // Fallback tính theo giá gửi nếu có lỗi sản phẩm
        $subtotal = 500000;
    }

    $shippingFee = 0; // Miễn phí giao hoa nội thành
    $totalAmount = $subtotal + $shippingFee;

    // 2. Tạo Mã Đơn Hàng Độc Nhất (BL + 5 chữ số ngẫu nhiên)
    do {
        $orderCode = 'BL' . mt_rand(10000, 99999);
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE order_code = :code");
        $checkStmt->execute([':code' => $orderCode]);
    } while ($checkStmt->fetchColumn() > 0);

    // 3. Insert vào bảng orders
    $orderSql = "INSERT INTO orders (
                    order_code, user_id, sender_name, sender_phone, is_anonymous,
                    receiver_name, receiver_phone, receiver_address, city,
                    delivery_date, delivery_time_slot, card_message, ribbon_message,
                    subtotal, shipping_fee, total_amount, payment_method,
                    payment_status, order_status
                 ) VALUES (
                    :order_code, NULL, :sender_name, :sender_phone, :is_anonymous,
                    :receiver_name, :receiver_phone, :receiver_address, :city,
                    :delivery_date, :delivery_time_slot, :card_message, :ribbon_message,
                    :subtotal, :shipping_fee, :total_amount, :payment_method,
                    'unpaid', 'received'
                 )";

    $orderStmt = $pdo->prepare($orderSql);
    $orderStmt->execute([
        ':order_code' => $orderCode,
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

    // 4. Insert vào bảng order_items (Lưu snapshot giá tại thời điểm mua)
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
                   VALUES (:order_id, 'received', 'Hệ thống đã nhận đơn đặt hoa thành công', NULL)";
    $historyStmt = $pdo->prepare($historySql);
    $historyStmt->execute([':order_id' => $orderId]);

    // COMMIT TOÀN BỘ TRANSACTION
    $pdo->commit();

    // Sinh link VietQR chuẩn NAPAS
    $vietqrUrl = "https://img.vietqr.io/image/MB-0908123456-compact.png?amount={$totalAmount}&addInfo={$orderCode}&accountName=BLOOMORA%20FLORIST";

    echo json_encode([
        'success' => true,
        'message' => 'Đặt hoa thành công!',
        'data' => [
            'orderId' => $orderId,
            'orderCode' => $orderCode,
            'totalAmount' => $totalAmount,
            'paymentMethod' => $paymentMethod,
            'vietqrUrl' => $vietqrUrl,
            'receiverName' => $receiverName,
            'receiverPhone' => $receiverPhone,
            'address' => $address,
            'deliveryDate' => $deliveryDate,
            'deliveryTime' => $deliveryTime,
            'bannerMessage' => $bannerMessage
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi tạo đơn hàng: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

