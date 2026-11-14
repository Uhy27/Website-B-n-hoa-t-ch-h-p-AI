<?php
/**
 * API Quản Trị Tồn Kho (Admin Inventory API)
 * GET:  Danh sách sản phẩm kèm thông tin tồn kho (hỗ trợ lọc low_stock)
 * GET:  Lịch sử nhập/xuất kho (?logs=1&product_id=N)
 * POST: Điều chỉnh tồn kho thủ công
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

date_default_timezone_set('Asia/Ho_Chi_Minh');
require_admin_auth();

try {
    $pdo = getDBConnection();
    $method = $_SERVER['REQUEST_METHOD'];

    // ================================================================
    // GET — Danh sách tồn kho hoặc lịch sử kho
    // ================================================================
    if ($method === 'GET') {

        // --- Lịch sử kho (inventory_logs) ---
        if (isset($_GET['logs']) && $_GET['logs'] == '1') {
            $productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
            if ($productId <= 0) {
                json_response(['success' => false, 'message' => 'Thiếu tham số product_id.'], 400);
            }

            // Kiểm tra sản phẩm tồn tại
            $check = $pdo->prepare("SELECT id, name FROM products WHERE id = :id");
            $check->execute([':id' => $productId]);
            $product = $check->fetch();
            if (!$product) {
                json_response(['success' => false, 'message' => 'Sản phẩm không tồn tại.'], 404);
            }

            $page   = max(1, (int)($_GET['page'] ?? 1));
            $limit  = max(1, min(100, (int)($_GET['limit'] ?? 50)));
            $offset = ($page - 1) * $limit;

            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM inventory_logs WHERE product_id = :pid");
            $countStmt->execute([':pid' => $productId]);
            $total = (int)$countStmt->fetchColumn();

            $sql = "SELECT il.id, il.product_id, il.change_quantity, il.remaining_quantity,
                           il.reason, il.order_id, il.user_id, il.note, il.created_at
                    FROM inventory_logs il
                    WHERE il.product_id = :pid
                    ORDER BY il.created_at DESC
                    LIMIT :limit OFFSET :offset";

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':pid', $productId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $logs = $stmt->fetchAll();

            json_response([
                'success' => true,
                'data' => [
                    'product' => $product,
                    'total'   => $total,
                    'page'    => $page,
                    'limit'   => $limit,
                    'items'   => $logs
                ]
            ]);
        }

        // --- Danh sách tồn kho ---
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = max(1, min(100, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $lowStockOnly = isset($_GET['low_stock_only']) && $_GET['low_stock_only'] == '1';

        $where = [];
        $params = [];

        if ($lowStockOnly) {
            $where[] = "p.stock_quantity <= 5";
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $countSql = "SELECT COUNT(*) FROM products p {$whereClause}";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $sql = "SELECT p.id, p.sku, p.name, p.thumbnail, p.stock_quantity, p.price, p.cost_price,
                       c.name AS categoryName,
                       CASE WHEN p.stock_quantity <= 5 THEN 1 ELSE 0 END AS low_stock
                FROM products p
                LEFT JOIN categories c ON c.id = p.category_id
                {$whereClause}
                ORDER BY p.stock_quantity ASC, p.id ASC
                LIMIT :limit OFFSET :offset";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();

        json_response([
            'success' => true,
            'data' => [
                'total' => $total,
                'page'  => $page,
                'limit' => $limit,
                'items' => $items
            ]
        ]);
    }

    // ================================================================
    // POST — Điều chỉnh tồn kho thủ công
    // ================================================================
    if ($method === 'POST') {
        $input = get_json_input();

        $productId = isset($input['product_id']) ? (int)$input['product_id'] : 0;
        $quantity  = isset($input['quantity']) ? (int)$input['quantity'] : 0;
        $reason    = isset($input['reason']) ? trim($input['reason']) : '';
        $note      = isset($input['note']) ? trim($input['note']) : '';

        if ($productId <= 0) {
            json_response(['success' => false, 'message' => 'Thiếu hoặc sai product_id.'], 400);
        }

        if ($quantity === 0) {
            json_response(['success' => false, 'message' => 'Số lượng điều chỉnh không được bằng 0.'], 400);
        }

        $validReasons = ['import', 'damage', 'audit_adjustment'];
        if (!in_array($reason, $validReasons, true)) {
            json_response([
                'success' => false,
                'message' => 'Lý do không hợp lệ. Chỉ chấp nhận: import, damage, audit_adjustment.'
            ], 400);
        }

        $pdo->beginTransaction();

        // Lấy thông tin sản phẩm hiện tại (khóa dòng)
        $check = $pdo->prepare("SELECT id, stock_quantity FROM products WHERE id = :id FOR UPDATE");
        $check->execute([':id' => $productId]);
        $product = $check->fetch();

        if (!$product) {
            $pdo->rollBack();
            json_response(['success' => false, 'message' => 'Sản phẩm không tồn tại.'], 404);
        }

        $newQuantity = (int)$product['stock_quantity'] + $quantity;
        if ($newQuantity < 0) {
            $pdo->rollBack();
            json_response([
                'success' => false,
                'message' => 'Tồn kho sau điều chỉnh không thể âm. Tồn kho hiện tại: ' . $product['stock_quantity']
            ], 400);
        }

        // Cập nhật số lượng sản phẩm
        $updateStmt = $pdo->prepare("UPDATE products SET stock_quantity = :qty WHERE id = :id");
        $updateStmt->execute([':qty' => $newQuantity, ':id' => $productId]);

        // Ghi log vào inventory_logs
        $logSql = "INSERT INTO inventory_logs (product_id, change_quantity, remaining_quantity, reason, user_id, note)
                   VALUES (:product_id, :change_quantity, :remaining_quantity, :reason, :user_id, :note)";
        $logStmt = $pdo->prepare($logSql);
        $logStmt->execute([
            ':product_id'         => $productId,
            ':change_quantity'    => $quantity,
            ':remaining_quantity' => $newQuantity,
            ':reason'             => $reason,
            ':user_id'            => $_SESSION['user_id'] ?? null,
            ':note'               => $note !== '' ? $note : null,
        ]);

        $pdo->commit();

        json_response([
            'success' => true,
            'message' => 'Điều chỉnh tồn kho thành công!',
            'data'    => [
                'product_id'     => $productId,
                'change'         => $quantity,
                'new_quantity'   => $newQuantity,
                'reason'         => $reason
            ]
        ]);
    }

    json_response(['success' => false, 'message' => 'Phương thức HTTP không được hỗ trợ.'], 405);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("[Admin Inventory Error] " . $e->getMessage());
    json_response([
        'success' => false,
        'message' => 'Lỗi hệ thống khi xử lý tồn kho.'
    ], 500);
}
?>
