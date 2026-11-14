<?php
/**
 * API Thống kê tổng quan (Dashboard Stats)
 * Endpoint: GET /api/admin/stats.php
 * Bảo mật: Chỉ dành riêng cho Quản trị viên & Nhân viên (Admin/Staff)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

// Bảo vệ chặt chẽ quyền Admin
require_admin_auth();

try {
    $pdo = getDBConnection();

    $totalRevenue = (float)$pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE order_status != 'cancelled'")->fetchColumn();
    $totalOrders  = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $pendingOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE order_status IN ('received', 'preparing')")->fetchColumn();
    $deliveringOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = 'delivering'")->fetchColumn();
    $completedOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = 'completed'")->fetchColumn();
    $totalProducts = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn();

    json_response([
        'success' => true,
        'data' => [
            'totalRevenue' => $totalRevenue,
            'totalOrders' => $totalOrders,
            'pendingOrders' => $pendingOrders,
            'deliveringOrders' => $deliveringOrders,
            'completedOrders' => $completedOrders,
            'totalProducts' => $totalProducts
        ]
    ]);

} catch (Exception $e) {
    error_log("[Admin Stats Error] " . $e->getMessage());
    json_response([
        'success' => false,
        'message' => 'Lỗi thống kê hệ thống.'
    ], 500);
}
?>