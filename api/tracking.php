<?php
/**
 * API: Tra cứu hành trình đơn hoa (Order Tracking)
 * Endpoint: GET /api/tracking.php?code=BL89241
 * Bảo mật: Ẩn danh người tặng nếu is_anonymous = 1, hỗ trợ tra cứu chính xác
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

try {
    $pdo = getDBConnection();

    $code = isset($_GET['code']) ? strtoupper(trim($_GET['code'])) : '';
    $phone = isset($_GET['phone']) ? trim($_GET['phone']) : '';

    if (empty($code) && empty($phone)) {
        json_response(['success' => false, 'message' => 'Vui lòng cung cấp mã đơn hàng hoặc số điện thoại!'], 400);
    }

    $where = [];
    $params = [];

    if (!empty($code)) {
        $where[] = "o.order_code = :code";
        $params[':code'] = $code;
    }

    if (!empty($phone)) {
        $where[] = "(o.sender_phone = :phone OR o.receiver_phone = :phone)";
        $params[':phone'] = $phone;
    }

    $sql = "SELECT o.id, o.order_code, o.sender_name, o.sender_phone, o.is_anonymous,
                   o.receiver_name, o.receiver_phone, o.receiver_address, o.city,
                   o.delivery_date, o.delivery_time_slot, o.card_message, o.ribbon_message,
                   o.total_amount, o.payment_method, o.payment_status, o.order_status,
                   o.photo_proof, o.created_at
            FROM orders o
            WHERE " . implode(" OR ", $where) . "
            ORDER BY o.id DESC LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $order = $stmt->fetch();

    if (!$order) {
        json_response(['success' => false, 'message' => 'Không tìm thấy đơn hàng với thông tin đã nhập.'], 404);
    }

    // Bảo vệ quyền riêng tư nếu khách hàng chọn tặng ẩn danh
    if (!empty($order['is_anonymous'])) {
        $order['sender_name'] = 'Người tặng ẩn danh';
        $order['sender_phone'] = '***.***.****';
    }

    // Lấy danh sách sản phẩm trong đơn
    $itemSql = "SELECT oi.product_id, oi.product_name, oi.product_sku, oi.unit_price, oi.quantity, oi.total_price,
                       COALESCE(p.thumbnail, '') AS thumbnail
                FROM order_items oi
                LEFT JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = :order_id";
    $itemStmt = $pdo->prepare($itemSql);
    $itemStmt->execute([':order_id' => $order['id']]);
    $items = $itemStmt->fetchAll();

    // Lấy lịch sử các bước trạng thái
    $historySql = "SELECT status, note, created_at
                   FROM order_status_history
                   WHERE order_id = :order_id
                   ORDER BY id ASC";
    $histStmt = $pdo->prepare($historySql);
    $histStmt->execute([':order_id' => $order['id']]);
    $history = $histStmt->fetchAll();

    json_response([
        'success' => true,
        'data' => [
            'order' => $order,
            'items' => $items,
            'history' => $history
        ]
    ]);

} catch (Exception $e) {
    error_log("[Tracking Error] " . $e->getMessage());
    json_response([
        'success' => false,
        'message' => 'Lỗi tra cứu đơn hàng.'
    ], 500);
}
?>