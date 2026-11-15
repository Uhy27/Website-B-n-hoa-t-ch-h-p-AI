<?php
declare(strict_types=1);
/** GET /api/tracking.php?code=BLxxxxx or phone */
require_once __DIR__ . '/../core/bootstrap.php';
api_only_method('GET');
try {
    $pdo = getDBConnection();
    $code = strtoupper(trim((string)($_GET['code'] ?? '')));
    $phone = trim((string)($_GET['phone'] ?? ''));
    if ($code === '' && $phone === '') json_response(['success'=>false,'message'=>'Vui lòng cung cấp mã đơn hàng hoặc số điện thoại.'],400);

    $conds=[]; $params=[];
    if ($code !== '') { $conds[]='o.order_code = :code'; $params[':code']=$code; }
    if ($phone !== '') { $conds[]='(o.sender_phone = :phone OR o.receiver_phone = :phone2)'; $params[':phone']=$phone; $params[':phone2']=$phone; }
    $sql = "SELECT o.* FROM orders o WHERE " . implode(' OR ', $conds) . " ORDER BY o.id DESC LIMIT 1";
    $stmt=$pdo->prepare($sql); $stmt->execute($params);
    $order=$stmt->fetch();
    if (!$order) json_response(['success'=>false,'message'=>'Không tìm thấy đơn hàng phù hợp.'],404);

    if (!empty($order['is_anonymous'])) { $order['sender_name']='Người tặng ẩn danh'; $order['sender_phone']='***.***.****'; }

    $i=$pdo->prepare("SELECT oi.product_id,oi.product_name,oi.product_sku,oi.unit_price,oi.quantity,oi.total_price,COALESCE(p.thumbnail,'') AS thumbnail
                      FROM order_items oi LEFT JOIN products p ON oi.product_id=p.id WHERE oi.order_id=:oid");
    $i->execute([':oid'=>$order['id']]); $items=$i->fetchAll();

    $h=$pdo->prepare("SELECT status,note,created_at FROM order_status_history WHERE order_id=:oid ORDER BY id ASC");
    $h->execute([':oid'=>$order['id']]); $history=$h->fetchAll();

    json_response(['success'=>true,'data'=>['order'=>$order,'items'=>$items,'history'=>$history]]);
} catch (Throwable $e) {
    error_log('[Tracking Error] '.$e->getMessage());
    json_response(['success'=>false,'message'=>'Lỗi tra cứu đơn hàng.'],500);
}