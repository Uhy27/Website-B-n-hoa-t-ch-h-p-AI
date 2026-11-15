<?php
declare(strict_types=1);
/** GET /api/categories.php */
require_once __DIR__ . '/../core/bootstrap.php';
api_only_method('GET');
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT c.id,c.name,c.slug,c.icon,c.badge,c.display_order,COUNT(p.id) AS product_count
                         FROM categories c LEFT JOIN products p ON c.id=p.category_id AND p.is_active=1
                         WHERE c.is_active=1 GROUP BY c.id ORDER BY c.display_order ASC, c.id ASC");
    $categories = $stmt->fetchAll();
    foreach ($categories as &$c) { $c['id']=(int)$c['id']; $c['product_count']=(int)$c['product_count']; }
    $total = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE is_active=1")->fetchColumn();
    array_unshift($categories, ['id'=>'all','name'=>'Tất Cả Mẫu Hoa','slug'=>'all','icon'=>'ph-flower-lotus','badge'=>null,'product_count'=>$total]);
    json_response(['success'=>true,'data'=>$categories]);
} catch (Throwable $e) {
    error_log('[Categories API Error] '.$e->getMessage());
    json_response(['success'=>false,'message'=>'Lỗi tải danh mục sản phẩm.'],500);
}