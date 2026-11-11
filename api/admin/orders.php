<?php
/**
 * API Quản Trị Đơn Hàng (Admin Orders API)
 * GET: Lấy danh sách toàn bộ đơn hàng
 * POST: Cập nhật trạng thái đơn (5 bước) & gắn link ảnh hoa thực tế cắm xong
 */

require_once __DIR__ . '/../../config/database.php';

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

        $sql = "SELECT id, order_code, sender_name, sender_phone, is_anonymous,
                       receiver_name, receiver_phone, receiver_address, city,
                       delivery_date, delivery_time_slot, card_message, ribbon_message,
                       subtotal, total_amount, payment_method, payment_status,
                       order_status, photo_proof, created_at
                FROM orders";

        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $sql .= " ORDER BY id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'count' => count($orders),
            'data' => $orders
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $orderId = isset($input['orderId']) ? (int)$input['orderId'] : 0;
        $newStatus = isset($input['status']) ? trim($input['status']) : '';
        $photoProof = isset($input['photoProof']) ? trim($input['photoProof']) : null;
        $note = isset($input['note']) ? trim($input['note']) : 'Cập nhật trạng thái bởi Quản trị viên';

        if (!$orderId || !$newStatus) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Thiếu orderId hoặc trạng thái mới!']);
            exit;
        }

        $pdo->beginTransaction();

        // Cập nhật orders
        $updateSql = "UPDATE orders SET order_status = :status";
        $params = [':status' => $newStatus, ':id' => $orderId];

        if ($photoProof !== null) {
            $updateSql .= ", photo_proof = :photo";
            $params[':photo'] = $photoProof;
        }

        $updateSql .= " WHERE id = :id";
        $stmt = $pdo->prepare($updateSql);
        $stmt->execute($params);

        // Ghi vào bảng lịch sử
        $histSql = "INSERT INTO order_status_history (order_id, status, note, created_by)
                    VALUES (:order_id, :status, :note, 1)";
        $histStmt = $pdo->prepare($histSql);
        $histStmt->execute([
            ':order_id' => $orderId,
            ':status' => $newStatus,
            ':note' => $note
        ]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Cập nhật trạng thái đơn hàng thành công!'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi xử lý Admin Orders: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

