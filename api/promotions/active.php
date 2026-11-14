<?php
/**
 * API: Lấy Danh Sách Khuyến Mãi Đang Hoạt Động
 * Endpoint: GET /api/promotions/active.php
 * Public API cho storefront - hiển thị banner, Flash Sale, countdown
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['success' => false, 'message' => 'Chỉ chấp nhận phương thức GET'], 405);
}

try {
    $pdo = getDBConnection();

    $now = date('Y-m-d H:i:s');

    $stmt = $pdo->prepare("
        SELECT id, name, slug, description, campaign_type, 
               discount_type, discount_value, start_at, end_at
        FROM campaigns
        WHERE status = 'active' 
          AND approval_status = 'approved'
          AND start_at <= :now1
          AND end_at >= :now2
        ORDER BY end_at ASC
    ");
    $stmt->execute([':now1' => $now, ':now2' => $now]);
    $campaigns = $stmt->fetchAll();

    json_response([
        'success' => true,
        'count' => count($campaigns),
        'data' => $campaigns
    ]);

} catch (Exception $e) {
    error_log("[Promotions Active Error] " . $e->getMessage());
    json_response([
        'success' => false,
        'message' => 'Không thể tải danh sách khuyến mãi. Vui lòng thử lại sau!'
    ], 500);
}
?>
