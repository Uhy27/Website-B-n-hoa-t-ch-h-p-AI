<?php
declare(strict_types=1);
require_once __DIR__ . '/../../core/bootstrap.php';
api_only_method('GET');
try {
    $code = trim((string)($_GET['code'] ?? ''));
    if ($code === '') json_response(['success'=>false,'message'=>'Vui lòng cung cấp mã giảm giá.'],400);
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT v.code,v.description,v.discount_type,v.discount_value,v.min_order_value,v.max_discount_amount,v.start_at,v.end_at,v.is_active,v.usage_limit,v.used_count
                           FROM vouchers v WHERE v.code=:code LIMIT 1");
    $stmt->execute([':code'=>$code]); $v=$stmt->fetch();
    if (!$v) json_response(['success'=>false,'message'=>'Mã giảm giá không tồn tại.'],400);
    $now=bloomora_now();
    if (!(int)$v['is_active']) json_response(['success'=>false,'message'=>'Mã giảm giá đã bị vô hiệu hóa.'],400);
    if ($now < $v['start_at'] || $now > $v['end_at']) json_response(['success'=>false,'message'=>'Mã giảm giá không trong thời gian hiệu lực.'],400);
    if ((int)$v['used_count'] >= (int)$v['usage_limit']) json_response(['success'=>false,'message'=>'Mã giảm giá đã hết lượt sử dụng.'],400);
    json_response(['success'=>true,'data'=>[
        'code'=>$v['code'],'description'=>$v['description'],'discount_type'=>$v['discount_type'],
        'discount_value'=>(float)$v['discount_value'],'min_order_value'=>(float)$v['min_order_value'],
        'max_discount_amount'=>$v['max_discount_amount']!==null?(float)$v['max_discount_amount']:null,
        'expires_at'=>$v['end_at']
    ]]);
} catch (Throwable $e) { error_log('[Voucher Check] '.$e->getMessage()); json_response(['success'=>false,'message'=>'Không thể kiểm tra mã giảm giá.'],500); }