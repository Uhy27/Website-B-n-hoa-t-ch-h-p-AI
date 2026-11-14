<?php
/**
 * API Quản Trị Danh Mục (Admin Categories API)
 * GET:    Danh sách danh mục kèm số lượng sản phẩm
 * POST:   Tạo danh mục mới
 * PUT:    Cập nhật danh mục (theo id)
 * DELETE: Xoá mềm danh mục (is_active=0)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

date_default_timezone_set('Asia/Ho_Chi_Minh');
require_admin_auth();

try {
    $pdo = getDBConnection();
    $method = $_SERVER['REQUEST_METHOD'];

    // ================================================================
    // GET — Danh sách danh mục + product_count
    // ================================================================
    if ($method === 'GET') {
        $sql = "SELECT c.id, c.name, c.slug, c.icon, c.badge, c.display_order, c.is_active,
                       COUNT(p.id) AS product_count
                FROM categories c
                LEFT JOIN products p ON p.category_id = c.id AND p.is_active = 1
                GROUP BY c.id
                ORDER BY c.display_order ASC, c.id ASC";

        $stmt = $pdo->query($sql);
        $categories = $stmt->fetchAll();

        json_response([
            'success' => true,
            'data'    => $categories
        ]);
    }

    // ================================================================
    // POST — Tạo danh mục mới
    // ================================================================
    if ($method === 'POST') {
        $input = get_json_input();

        if (!isset($input['name']) || trim($input['name']) === '') {
            json_response(['success' => false, 'message' => 'Tên danh mục là bắt buộc.'], 400);
        }

        if (!isset($input['slug']) || trim($input['slug']) === '') {
            json_response(['success' => false, 'message' => 'Slug danh mục là bắt buộc.'], 400);
        }

        // Kiểm tra slug trùng
        $checkSlug = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE slug = :slug");
        $checkSlug->execute([':slug' => trim($input['slug'])]);
        if ((int)$checkSlug->fetchColumn() > 0) {
            json_response(['success' => false, 'message' => 'Slug danh mục đã tồn tại.'], 409);
        }

        $sql = "INSERT INTO categories (name, slug, icon, badge, display_order, is_active)
                VALUES (:name, :slug, :icon, :badge, :display_order, :is_active)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':name'          => trim($input['name']),
            ':slug'          => trim($input['slug']),
            ':icon'          => isset($input['icon']) ? trim($input['icon']) : 'ph-flower-lotus',
            ':badge'         => isset($input['badge']) ? trim($input['badge']) : null,
            ':display_order' => isset($input['display_order']) ? (int)$input['display_order'] : 0,
            ':is_active'     => isset($input['is_active']) ? (int)$input['is_active'] : 1,
        ]);

        $newId = (int)$pdo->lastInsertId();

        json_response([
            'success' => true,
            'message' => 'Tạo danh mục thành công!',
            'data'    => ['id' => $newId]
        ], 201);
    }

    // ================================================================
    // PUT — Cập nhật danh mục
    // ================================================================
    if ($method === 'PUT') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            json_response(['success' => false, 'message' => 'Thiếu tham số id danh mục.'], 400);
        }

        $check = $pdo->prepare("SELECT id FROM categories WHERE id = :id");
        $check->execute([':id' => $id]);
        if (!$check->fetch()) {
            json_response(['success' => false, 'message' => 'Danh mục không tồn tại.'], 404);
        }

        $input = get_json_input();
        if (empty($input)) {
            json_response(['success' => false, 'message' => 'Không có dữ liệu cập nhật.'], 400);
        }

        $allowedFields = ['name', 'slug', 'icon', 'badge', 'display_order', 'is_active'];
        $sets   = [];
        $params = [':id' => $id];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $input)) {
                $sets[] = "`{$field}` = :{$field}";
                $params[":{$field}"] = $input[$field];
            }
        }

        if (empty($sets)) {
            json_response(['success' => false, 'message' => 'Không có trường hợp lệ nào để cập nhật.'], 400);
        }

        // Nếu cập nhật slug, kiểm tra trùng
        if (isset($input['slug'])) {
            $checkSlug = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE slug = :slug AND id != :cid");
            $checkSlug->execute([':slug' => trim($input['slug']), ':cid' => $id]);
            if ((int)$checkSlug->fetchColumn() > 0) {
                json_response(['success' => false, 'message' => 'Slug danh mục đã tồn tại cho danh mục khác.'], 409);
            }
        }

        $sql = "UPDATE categories SET " . implode(', ', $sets) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        json_response([
            'success' => true,
            'message' => 'Cập nhật danh mục thành công!'
        ]);
    }

    // ================================================================
    // DELETE — Xoá mềm danh mục (is_active = 0)
    // ================================================================
    if ($method === 'DELETE') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            json_response(['success' => false, 'message' => 'Thiếu tham số id danh mục.'], 400);
        }

        $check = $pdo->prepare("SELECT id, is_active FROM categories WHERE id = :id");
        $check->execute([':id' => $id]);
        $category = $check->fetch();

        if (!$category) {
            json_response(['success' => false, 'message' => 'Danh mục không tồn tại.'], 404);
        }

        if ((int)$category['is_active'] === 0) {
            json_response(['success' => false, 'message' => 'Danh mục đã bị vô hiệu hoá trước đó.'], 400);
        }

        $stmt = $pdo->prepare("UPDATE categories SET is_active = 0 WHERE id = :id");
        $stmt->execute([':id' => $id]);

        json_response([
            'success' => true,
            'message' => 'Đã vô hiệu hoá danh mục thành công!'
        ]);
    }

    json_response(['success' => false, 'message' => 'Phương thức HTTP không được hỗ trợ.'], 405);

} catch (Exception $e) {
    error_log("[Admin Categories Error] " . $e->getMessage());
    json_response([
        'success' => false,
        'message' => 'Lỗi hệ thống khi xử lý danh mục.'
    ], 500);
}
?>
