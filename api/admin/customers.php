<?php
/**
 * API Quản Trị Khách Hàng (Admin Customers API)
 * GET: Danh sách khách hàng (role_id=3) kèm total_orders, total_spent
 * PUT: Cập nhật trạng thái khách hàng (active/inactive/banned)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

date_default_timezone_set('Asia/Ho_Chi_Minh');
require_admin_auth();

try {
    $pdo = getDBConnection();
    $method = $_SERVER['REQUEST_METHOD'];

    // ================================================================
    // GET — Danh sách khách hàng (phân trang + tìm kiếm)
    // ================================================================
    if ($method === 'GET') {
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = max(1, min(100, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';

        $where  = ["u.role_id = 3"];
        $params = [];

        if ($search !== '') {
            $where[] = "(u.full_name LIKE :search OR u.email LIKE :search2 OR u.phone LIKE :search3)";
            $params[':search']  = "%{$search}%";
            $params[':search2'] = "%{$search}%";
            $params[':search3'] = "%{$search}%";
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        // Đếm tổng
        $countSql = "SELECT COUNT(*) FROM users u {$whereClause}";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Lấy danh sách kèm thống kê đơn hàng
        $sql = "SELECT u.id, u.email, u.full_name, u.phone, u.avatar, u.status,
                       u.created_at, u.updated_at,
                       COUNT(o.id) AS total_orders,
                       COALESCE(SUM(CASE WHEN o.order_status != 'cancelled' THEN o.total_amount ELSE 0 END), 0) AS total_spent
                FROM users u
                LEFT JOIN orders o ON o.user_id = u.id
                {$whereClause}
                GROUP BY u.id
                ORDER BY u.created_at DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $customers = $stmt->fetchAll();

        json_response([
            'success' => true,
            'data' => [
                'total' => $total,
                'page'  => $page,
                'limit' => $limit,
                'items' => $customers
            ]
        ]);
    }

    // ================================================================
    // PUT — Cập nhật trạng thái khách hàng
    // ================================================================
    if ($method === 'PUT') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            json_response(['success' => false, 'message' => 'Thiếu tham số id khách hàng.'], 400);
        }

        // Kiểm tra khách hàng tồn tại và đúng role
        $check = $pdo->prepare("SELECT id, status FROM users WHERE id = :id AND role_id = 3");
        $check->execute([':id' => $id]);
        $customer = $check->fetch();

        if (!$customer) {
            json_response(['success' => false, 'message' => 'Khách hàng không tồn tại.'], 404);
        }

        $input = get_json_input();
        $newStatus = isset($input['status']) ? trim($input['status']) : '';

        $validStatuses = ['active', 'inactive', 'banned'];
        if (!in_array($newStatus, $validStatuses, true)) {
            json_response([
                'success' => false,
                'message' => 'Trạng thái không hợp lệ. Chỉ chấp nhận: active, inactive, banned.'
            ], 400);
        }

        $stmt = $pdo->prepare("UPDATE users SET status = :status WHERE id = :id AND role_id = 3");
        $stmt->execute([':status' => $newStatus, ':id' => $id]);

        json_response([
            'success' => true,
            'message' => 'Cập nhật trạng thái khách hàng thành công!'
        ]);
    }

    json_response(['success' => false, 'message' => 'Phương thức HTTP không được hỗ trợ.'], 405);

} catch (Exception $e) {
    error_log("[Admin Customers Error] " . $e->getMessage());
    json_response([
        'success' => false,
        'message' => 'Lỗi hệ thống khi xử lý khách hàng.'
    ], 500);
}
?>
