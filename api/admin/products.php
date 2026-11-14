<?php
/**
 * API Quản Trị Sản Phẩm (Admin Products API)
 * GET:    Danh sách sản phẩm (phân trang, tìm kiếm, lọc danh mục/trạng thái)
 * POST:   Tạo sản phẩm mới
 * PUT:    Cập nhật sản phẩm (theo id)
 * DELETE: Xoá mềm sản phẩm (is_active=0)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

date_default_timezone_set('Asia/Ho_Chi_Minh');
require_admin_auth();

try {
    $pdo = getDBConnection();
    $method = $_SERVER['REQUEST_METHOD'];

    // ================================================================
    // GET — Danh sách sản phẩm (phân trang + tìm kiếm + lọc)
    // ================================================================
    if ($method === 'GET') {
        $page  = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(1, min(100, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $search     = isset($_GET['search']) ? trim($_GET['search']) : '';
        $categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
        $status     = isset($_GET['status']) ? trim($_GET['status']) : '';

        $where  = [];
        $params = [];

        if ($search !== '') {
            $where[] = "(p.name LIKE :search OR p.sku LIKE :search2)";
            $params[':search']  = "%{$search}%";
            $params[':search2'] = "%{$search}%";
        }

        if ($categoryId > 0) {
            $where[] = "p.category_id = :category_id";
            $params[':category_id'] = $categoryId;
        }

        if ($status === 'active') {
            $where[] = "p.is_active = 1";
        } elseif ($status === 'inactive') {
            $where[] = "p.is_active = 0";
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Đếm tổng
        $countSql = "SELECT COUNT(*) FROM products p {$whereClause}";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Lấy danh sách
        $sql = "SELECT p.id, p.category_id, c.name AS categoryName, p.sku, p.name, p.slug,
                       p.price, p.sale_price, p.cost_price, p.stock_quantity,
                       p.primary_flower, p.color, p.color_name, p.size_dimensions,
                       p.meaning, p.tags, p.thumbnail, p.is_featured, p.is_active,
                       p.rating, p.reviews_count, p.created_at,
                       CASE WHEN p.price > 0 THEN ROUND(((p.price - p.cost_price) / p.price) * 100, 2) ELSE 0 END AS profit_margin
                FROM products p
                LEFT JOIN categories c ON c.id = p.category_id
                {$whereClause}
                ORDER BY p.id DESC
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
    // POST — Tạo sản phẩm mới
    // ================================================================
    if ($method === 'POST') {
        $input = get_json_input();

        // Kiểm tra các trường bắt buộc
        $required = ['name', 'category_id', 'sku', 'price', 'cost_price', 'stock_quantity', 'thumbnail'];
        foreach ($required as $field) {
            if (!isset($input[$field]) || (is_string($input[$field]) && trim($input[$field]) === '')) {
                json_response(['success' => false, 'message' => "Trường '{$field}' là bắt buộc."], 400);
            }
        }

        // Kiểm tra SKU trùng
        $checkSku = $pdo->prepare("SELECT COUNT(*) FROM products WHERE sku = :sku");
        $checkSku->execute([':sku' => trim($input['sku'])]);
        if ((int)$checkSku->fetchColumn() > 0) {
            json_response(['success' => false, 'message' => 'Mã SKU đã tồn tại. Vui lòng chọn mã khác.'], 409);
        }

        // Tạo slug tự động nếu không truyền
        $slug = isset($input['slug']) && trim($input['slug']) !== ''
            ? trim($input['slug'])
            : $this->generateSlug($input['name'] ?? '');

        // Hàm tạo slug inline
        $slugStr = trim($input['name']);
        if (isset($input['slug']) && trim($input['slug']) !== '') {
            $slugStr = trim($input['slug']);
        }
        $slug = mb_strtolower($slugStr, 'UTF-8');
        // Chuyển đổi ký tự tiếng Việt
        $vietnamese = [
            'à','á','ạ','ả','ã','â','ầ','ấ','ậ','ẩ','ẫ','ă','ằ','ắ','ặ','ẳ','ẵ',
            'è','é','ẹ','ẻ','ẽ','ê','ề','ế','ệ','ể','ễ',
            'ì','í','ị','ỉ','ĩ',
            'ò','ó','ọ','ỏ','õ','ô','ồ','ố','ộ','ổ','ỗ','ơ','ờ','ớ','ợ','ở','ỡ',
            'ù','ú','ụ','ủ','ũ','ư','ừ','ứ','ự','ử','ữ',
            'ỳ','ý','ỵ','ỷ','ỹ',
            'đ'
        ];
        $ascii = [
            'a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a',
            'e','e','e','e','e','e','e','e','e','e','e',
            'i','i','i','i','i',
            'o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o',
            'u','u','u','u','u','u','u','u','u','u','u',
            'y','y','y','y','y',
            'd'
        ];
        $slug = str_replace($vietnamese, $ascii, $slug);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        $slug = trim($slug, '-');

        // Đảm bảo slug duy nhất
        $baseSlug = $slug;
        $counter = 1;
        while (true) {
            $checkSlug = $pdo->prepare("SELECT COUNT(*) FROM products WHERE slug = :slug");
            $checkSlug->execute([':slug' => $slug]);
            if ((int)$checkSlug->fetchColumn() === 0) break;
            $slug = $baseSlug . '-' . $counter++;
        }

        $sql = "INSERT INTO products
                (name, category_id, sku, slug, price, cost_price, sale_price, stock_quantity,
                 primary_flower, color, color_name, size_dimensions, meaning, tags,
                 thumbnail, is_featured, is_active, rating)
                VALUES
                (:name, :category_id, :sku, :slug, :price, :cost_price, :sale_price, :stock_quantity,
                 :primary_flower, :color, :color_name, :size_dimensions, :meaning, :tags,
                 :thumbnail, :is_featured, :is_active, :rating)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':name'            => trim($input['name']),
            ':category_id'     => (int)$input['category_id'],
            ':sku'             => trim($input['sku']),
            ':slug'            => $slug,
            ':price'           => (float)$input['price'],
            ':cost_price'      => (float)$input['cost_price'],
            ':sale_price'      => isset($input['sale_price']) ? (float)$input['sale_price'] : null,
            ':stock_quantity'   => (int)$input['stock_quantity'],
            ':primary_flower'  => isset($input['primary_flower']) ? trim($input['primary_flower']) : null,
            ':color'           => isset($input['color']) ? trim($input['color']) : null,
            ':color_name'      => isset($input['color_name']) ? trim($input['color_name']) : null,
            ':size_dimensions'  => isset($input['size_dimensions']) ? trim($input['size_dimensions']) : null,
            ':meaning'         => isset($input['meaning']) ? trim($input['meaning']) : null,
            ':tags'            => isset($input['tags']) ? trim($input['tags']) : null,
            ':thumbnail'       => trim($input['thumbnail']),
            ':is_featured'     => isset($input['is_featured']) ? (int)$input['is_featured'] : 1,
            ':is_active'       => isset($input['is_active']) ? (int)$input['is_active'] : 1,
            ':rating'          => isset($input['rating']) ? (float)$input['rating'] : 5.00,
        ]);

        $newId = (int)$pdo->lastInsertId();

        json_response([
            'success' => true,
            'message' => 'Tạo sản phẩm thành công!',
            'data'    => ['id' => $newId]
        ], 201);
    }

    // ================================================================
    // PUT — Cập nhật sản phẩm
    // ================================================================
    if ($method === 'PUT') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            json_response(['success' => false, 'message' => 'Thiếu tham số id sản phẩm.'], 400);
        }

        // Kiểm tra sản phẩm tồn tại
        $check = $pdo->prepare("SELECT id FROM products WHERE id = :id");
        $check->execute([':id' => $id]);
        if (!$check->fetch()) {
            json_response(['success' => false, 'message' => 'Sản phẩm không tồn tại.'], 404);
        }

        $input = get_json_input();
        if (empty($input)) {
            json_response(['success' => false, 'message' => 'Không có dữ liệu cập nhật.'], 400);
        }

        // Danh sách trường cho phép cập nhật
        $allowedFields = [
            'name', 'category_id', 'sku', 'slug', 'price', 'cost_price', 'sale_price',
            'stock_quantity', 'primary_flower', 'color', 'color_name', 'size_dimensions',
            'meaning', 'tags', 'thumbnail', 'is_featured', 'is_active', 'rating'
        ];

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

        // Nếu cập nhật SKU, kiểm tra trùng
        if (isset($input['sku'])) {
            $checkSku = $pdo->prepare("SELECT COUNT(*) FROM products WHERE sku = :sku AND id != :pid");
            $checkSku->execute([':sku' => trim($input['sku']), ':pid' => $id]);
            if ((int)$checkSku->fetchColumn() > 0) {
                json_response(['success' => false, 'message' => 'Mã SKU đã tồn tại cho sản phẩm khác.'], 409);
            }
        }

        $sql = "UPDATE products SET " . implode(', ', $sets) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        json_response([
            'success' => true,
            'message' => 'Cập nhật sản phẩm thành công!'
        ]);
    }

    // ================================================================
    // DELETE — Xoá mềm sản phẩm (is_active = 0)
    // ================================================================
    if ($method === 'DELETE') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            json_response(['success' => false, 'message' => 'Thiếu tham số id sản phẩm.'], 400);
        }

        $check = $pdo->prepare("SELECT id, is_active FROM products WHERE id = :id");
        $check->execute([':id' => $id]);
        $product = $check->fetch();

        if (!$product) {
            json_response(['success' => false, 'message' => 'Sản phẩm không tồn tại.'], 404);
        }

        if ((int)$product['is_active'] === 0) {
            json_response(['success' => false, 'message' => 'Sản phẩm đã bị vô hiệu hoá trước đó.'], 400);
        }

        $stmt = $pdo->prepare("UPDATE products SET is_active = 0 WHERE id = :id");
        $stmt->execute([':id' => $id]);

        json_response([
            'success' => true,
            'message' => 'Đã vô hiệu hoá sản phẩm thành công!'
        ]);
    }

    json_response(['success' => false, 'message' => 'Phương thức HTTP không được hỗ trợ.'], 405);

} catch (Exception $e) {
    error_log("[Admin Products Error] " . $e->getMessage());
    json_response([
        'success' => false,
        'message' => 'Lỗi hệ thống khi xử lý sản phẩm.'
    ], 500);
}
?>
