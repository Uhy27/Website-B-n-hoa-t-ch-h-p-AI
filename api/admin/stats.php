<?php
/**
 * API Thống kê tổng quan (Dashboard Stats)
 * Endpoint: GET /api/admin/stats.php
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $pdo = getDBConnection();

    $totalRevenue = (float)$pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE order_status != 'cancelled'")->fetchColumn();
    $totalOrders  = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $pendingOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE order_status IN ('received', 'preparing')")->fetchColumn();
    $deliveringOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = 'delivering'")->fetchColumn();
    $completedOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = 'completed'")->fetchColumn();
    $totalProducts = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn();

    echo json_encode([
        'success' => true,
        'data' => [
            'totalRevenue' => $totalRevenue,
            'totalOrders' => $totalOrders,
            'pendingOrders' => $pendingOrders,
            'deliveringOrders' => $deliveringOrders,
            'completedOrders' => $completedOrders,
            'totalProducts' => $totalProducts
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi thống kê: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

