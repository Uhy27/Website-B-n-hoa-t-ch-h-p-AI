<?php
/**
 * API: Lấy lịch sử đơn hàng của tài khoản đang đăng nhập
 * Endpoint: GET /api/user/orders.php
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

init_secure_session();

if (empty($_SESSION['user_id'])) {
    json_response(['success' => false, 'message' => 'Vui lòng đăng nhập để xem lịch sử đơn hàng!'], 401);
}

$userId = (int)$_SESSION['user_id'];

try {
    $pdo = getDBConnection();

    $stmt = $pdo->prepare("SELECT id, order_code, receiver_name, receiver_phone, receiver_address, city,
                                  delivery_date, delivery_time_slot, card_message, ribbon_message,
                                  subtotal, shipping_fee, total_amount, payment_method, payment_status,
                                  order_status, photo_proof, created_at
                           FROM orders
                           WHERE user_id = :user_id
                           ORDER BY id DESC");
    $stmt->execute([':user_id' => $userId]);
    $orders = $stmt->fetchAll();

    if (!empty($orders)) {
        $orderIds = array_column($orders, 'id');
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $itemStmt = $pdo->prepare("SELECT order_id, product_name, product_sku, unit_price, quantity, total_price 
                                   FROM order_items 
                                   WHERE order_id IN ($placeholders)");
        $itemStmt->execute($orderIds);
        $allItems = $itemStmt->fetchAll();

        $itemsByOrder = [];
        foreach ($allItems as $it) {
            $itemsByOrder[$it['order_id']][] = $it;
        }

        foreach ($orders as &$ord) {
            $ord['items'] = $itemsByOrder[$ord['id']] ?? [];
        }
    }

    json_response([
        'success' => true,
        'count' => count($orders),
        'data' => $orders
    ]);

} catch (Exception $e) {
    error_log("[User Orders Error] " . $e->getMessage());
    json_response(['success' => false, 'message' => 'Lỗi tải lịch sử đơn hàng.'], 500);
}
?>
