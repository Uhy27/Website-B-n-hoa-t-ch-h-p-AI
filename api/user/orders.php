<?php
declare(strict_types=1);
require_once __DIR__ . '/../../core/bootstrap.php';
init_secure_session(); api_only_method('GET');
if (empty($_SESSION['user_id'])) json_response(['success'=>false,'message'=>'Vui lòng đăng nhập để xem lịch sử đơn hàng.'],401);
try {
    $pdo=getDBConnection(); $uid=(int)$_SESSION['user_id'];
    $st=$pdo->prepare("SELECT id,order_code,receiver_name,receiver_phone,receiver_address,city,delivery_date,delivery_time_slot,card_message,ribbon_message,subtotal,shipping_fee,discount_amount,total_amount,payment_method,payment_status,order_status,photo_proof,created_at FROM orders WHERE user_id=:uid ORDER BY id DESC");
    $st->execute([':uid'=>$uid]); $orders=$st->fetchAll();
    if(!empty($orders)){
        $ids=array_column($orders,'id'); $ph=implode(',',array_fill(0,count($ids),'?'));
        $it=$pdo->prepare("SELECT order_id,product_name,product_sku,unit_price,quantity,total_price FROM order_items WHERE order_id IN ($ph)");
        $it->execute($ids); $by=[]; foreach($it->fetchAll() as $r){ $by[$r['order_id']][]=$r; }
        foreach($orders as &$o){ $o['items']=$by[$o['id']]??[]; }
    }
    json_response(['success'=>true,'count'=>count($orders),'data'=>$orders]);
} catch (Throwable $e){ error_log('[User Orders] '.$e->getMessage()); json_response(['success'=>false,'message'=>'Lỗi tải lịch sử đơn hàng.'],500);} 