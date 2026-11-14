<?php
/**
 * API: Lấy danh sách danh mục hoa kèm số lượng sản phẩm
 * Endpoint: GET /api/categories.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

try {
    $pdo = getDBConnection();

    // Query lấy danh mục kèm đếm sản phẩm đang active
    $sql = "SELECT c.id, c.name, c.slug, c.icon, c.badge, c.display_order,
                   COUNT(p.id) AS product_count
            FROM categories c
            LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 1
            WHERE c.is_active = 1
            GROUP BY c.id
            ORDER BY c.display_order ASC";

    $stmt = $pdo->query($sql);
    $categories = $stmt->fetchAll();

    // Đếm tổng số sản phẩm
    $totalProducts = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn();

    $allCategory = [
        'id' => 'all',
        'name' => 'Tất Cả Mẫu Hoa',
        'slug' => 'all',
        'icon' => 'ph-flower-lotus',
        'badge' => null,
        'product_count' => $totalProducts
    ];

    array_unshift($categories, $allCategory);

    json_response([
        'success' => true,
        'data' => $categories
    ]);

} catch (Exception $e) {
    error_log("[Categories API Error] " . $e->getMessage());
    json_response([
        'success' => false,
        'message' => 'Lỗi tải danh mục sản phẩm.'
    ], 500);
}
?>