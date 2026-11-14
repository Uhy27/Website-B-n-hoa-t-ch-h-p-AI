<?php
/**
 * API Quản Trị Đơn Hàng (Admin Orders API)
 * GET: Lấy danh sách toàn bộ đơn hàng (kèm chi tiết sản phẩm)
 * POST: Cập nhật trạng thái đơn (5 bước) & gắn link ảnh hoa thực tế cắm xong
 * Bảo mật: Chỉ dành riêng cho Quản trị viên & Nhân viên (Admin/Staff)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

// Xác thực nghiêm ngặt quyền Admin / Staff
require_admin_auth();

try {
    $pdo = getDBConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $status = isset($_GET['status']) ? trim($_GET['status']) : '';
        $where = [];
        $params = [];

        if ($status !== '' && $status !== 'all') {
            $where[] = "order_status = :status";
            $params[':status'] = $status;
        }

        $sql = "SELECT id, order_code, user_id, sender_name, sender_phone, is_anonymous,
                       receiver_name, receiver_phone, receiver_address, city,
                       delivery_date, delivery_time_slot, card_message, ribbon_message,
                       subtotal, shipping_fee, total_amount, payment_method, payment_status,
                       order_status, photo_proof, created_at
                FROM orders";

        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $sql .= " ORDER BY id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll();

        // Lấy danh sách order_items cho các đơn hàng để Admin nắm rõ chi tiết sản phẩm
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
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = get_json_input();

        $orderId = isset($input['orderId']) ? (int)$input['orderId'] : 0;
        $newStatus = isset($input['status']) ? trim($input['status']) : '';
        $photoProof = isset($input['photoProof']) ? trim($input['photoProof']) : null;
        $note = isset($input['note']) ? trim($input['note']) : 'Cập nhật trạng thái bởi Quản trị viên';
        $currentAdminId = (int)($_SESSION['user_id'] ?? 1);

        $validStatuses = ['received', 'preparing', 'photo_ready', 'delivering', 'completed', 'cancelled'];
        if (!$orderId || !in_array($newStatus, $validStatuses, true)) {
            json_response(['success' => false, 'message' => 'Mã đơn hàng hoặc trạng thái không hợp lệ!'], 400);
        }

        $pdo->beginTransaction();

        // Kiểm tra đơn hàng có tồn tại không
        $checkOrderStmt = $pdo->prepare("SELECT id, order_status FROM orders WHERE id = :id FOR UPDATE");
        $checkOrderStmt->execute([':id' => $orderId]);
        $existingOrder = $checkOrderStmt->fetch();

        if (!$existingOrder) {
            $pdo->rollBack();
            json_response(['success' => false, 'message' => 'Đơn hàng không tồn tại!'], 404);
        }

        // Cập nhật orders
        $updateSql = "UPDATE orders SET order_status = :status";
        $params = [':status' => $newStatus, ':id' => $orderId];

        if ($photoProof !== null) {
            $updateSql .= ", photo_proof = :photo";
            $params[':photo'] = $photoProof;
        }

        // Nếu trạng thái là completed, có thể tự động đánh dấu payment_status = 'paid' nếu COD
        if ($newStatus === 'completed') {
            $updateSql .= ", payment_status = 'paid'";
        }

        $updateSql .= " WHERE id = :id";
        $stmt = $pdo->prepare($updateSql);
        $stmt->execute($params);

        // Ghi vào bảng lịch sử với ID admin thực tế
        $histSql = "INSERT INTO order_status_history (order_id, status, note, created_by)
                    VALUES (:order_id, :status, :note, :created_by)";
        $histStmt = $pdo->prepare($histSql);
        $histStmt->execute([
            ':order_id' => $orderId,
            ':status' => $newStatus,
            ':note' => $note,
            ':created_by' => $currentAdminId
        ]);

        $pdo->commit();

        json_response([
            'success' => true,
            'message' => 'Cập nhật trạng thái đơn hàng thành công!'
        ]);
    }

    json_response(['success' => false, 'message' => 'Phương thức HTTP không được hỗ trợ'], 405);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("[Admin Orders Error] " . $e->getMessage());
    json_response([
        'success' => false,
        'message' => 'Lỗi xử lý hệ thống đơn hàng quản trị.'
    ], 500);
}
?>