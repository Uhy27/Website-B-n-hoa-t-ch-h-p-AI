<?php
/**
 * API: Lấy danh sách sản phẩm với bộ lọc đa chiều (Category, Price, Color, Search, Sort)
 * Endpoint: GET /api/products.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

try {
    $pdo = getDBConnection();

    $category = isset($_GET['category']) ? trim($_GET['category']) : 'all';
    $price    = isset($_GET['price']) ? trim($_GET['price']) : 'all';
    $color    = isset($_GET['color']) ? trim($_GET['color']) : 'all';
    $q        = isset($_GET['q']) ? trim($_GET['q']) : '';
    $sort     = isset($_GET['sort']) ? trim($_GET['sort']) : 'popular';

    $where = ["p.is_active = 1"];
    $params = [];

    // 1. Lọc theo Danh mục (slug)
    if ($category !== 'all' && $category !== '') {
        $where[] = "c.slug = :category";
        $params[':category'] = $category;
    }

    // 2. Lọc theo Khoảng giá
    if ($price === 'under-500') {
        $where[] = "p.price < 500000";
    } elseif ($price === '500-1000') {
        $where[] = "(p.price >= 500000 AND p.price <= 1000000)";
    } elseif ($price === '1000-2000') {
        $where[] = "(p.price > 1000000 AND p.price <= 2000000)";
    } elseif ($price === 'above-2000') {
        $where[] = "p.price > 2000000";
    }

    // 3. Lọc theo Tông màu
    if ($color !== 'all' && $color !== '') {
        $where[] = "p.color = :color";
        $params[':color'] = $color;
    }

    // 4. Tìm kiếm từ khóa (Prepared Statement chống SQLi)
    if ($q !== '') {
        $where[] = "(p.name LIKE :kw1 OR p.primary_flower LIKE :kw2 OR p.color_name LIKE :kw3 OR p.meaning LIKE :kw4 OR c.name LIKE :kw5)";
        $kw = "%" . $q . "%";
        $params[':kw1'] = $kw;
        $params[':kw2'] = $kw;
        $params[':kw3'] = $kw;
        $params[':kw4'] = $kw;
        $params[':kw5'] = $kw;
    }

    // 5. Sắp xếp
    $orderBy = "p.reviews_count DESC";
    if ($sort === 'price-asc') {
        $orderBy = "p.price ASC";
    } elseif ($sort === 'price-desc') {
        $orderBy = "p.price DESC";
    } elseif ($sort === 'rating') {
        $orderBy = "p.rating DESC, p.reviews_count DESC";
    }

    $sql = "SELECT p.id, p.category_id, p.sku, p.name, p.slug, p.price, p.sale_price,
                   p.stock_quantity, p.primary_flower, p.color, p.color_name,
                   p.size_dimensions, p.meaning, p.tags, p.thumbnail AS image,
                   p.rating, p.reviews_count AS reviewsCount,
                   c.name AS categoryName, c.slug AS categorySlug
            FROM products p
            INNER JOIN categories c ON p.category_id = c.id
            WHERE " . implode(" AND ", $where) . "
            ORDER BY " . $orderBy;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    // Format tags thành array và ép kiểu số chuẩn xác
    foreach ($products as &$p) {
        $p['id'] = (int)$p['id'];
        $p['price'] = (float)$p['price'];
        $p['originalPrice'] = $p['sale_price'] ? (float)$p['sale_price'] : (float)$p['price'];
        $p['stock_quantity'] = (int)$p['stock_quantity'];
        $p['rating'] = (float)$p['rating'];
        $p['reviewsCount'] = (int)$p['reviewsCount'];
        $p['tags'] = !empty($p['tags']) ? array_map('trim', explode(',', $p['tags'])) : [];
        $p['flowerTypes'] = $p['primary_flower'];
        $p['size'] = $p['size_dimensions'];
    }

    json_response([
        'success' => true,
        'count' => count($products),
        'data' => $products
    ]);

} catch (Exception $e) {
    error_log("[Products API Error] " . $e->getMessage());
    json_response([
        'success' => false,
        'message' => 'Lỗi truy vấn danh sách sản phẩm.'
    ], 500);
}
?>