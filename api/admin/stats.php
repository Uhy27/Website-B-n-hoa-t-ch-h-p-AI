<?php
declare(strict_types=1);
require_once __DIR__ . '/../../core/bootstrap.php';
require_admin_auth();
try {
    $pdo=getDBConnection();
    $totalRevenue = (float)$pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE order_status != 'cancelled'")->fetchColumn();
    $totalOrders  = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $pendingOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE order_status IN ('received','preparing')")->fetchColumn();
    $deliveringOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE order_status='delivering'")->fetchColumn();
    $completedOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE order_status='completed'")->fetchColumn();
    $totalProducts = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE is_active=1")->fetchColumn();
    $lowStock = 0; $activeCampaigns = 0; $totalCustomers = 0;
    try { $lowStock = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE is_active=1 AND stock_quantity <= 5")->fetchColumn(); } catch(Throwable $e){}
    try { $activeCampaigns = (int)$pdo->query("SELECT COUNT(*) FROM campaigns WHERE status='active' AND approval_status='approved'")->fetchColumn(); } catch(Throwable $e){}
    try { $totalCustomers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role_id=3")->fetchColumn(); } catch(Throwable $e){}
    json_response(['success'=>true,'data'=>compact('totalRevenue','totalOrders','pendingOrders','deliveringOrders','completedOrders','totalProducts','lowStock','activeCampaigns','totalCustomers')]);
} catch (Throwable $e){ error_log('[Admin Stats] '.$e->getMessage()); json_response(['success'=>false,'message'=>'Lỗi thống kê hệ thống.'],500);} 