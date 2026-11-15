<?php
declare(strict_types=1);
require_once __DIR__ . '/../../core/bootstrap.php';
api_only_method('GET');
try {
    $pdo=getDBConnection(); $now=bloomora_now();
    $st=$pdo->prepare("SELECT id,name,slug,description,campaign_type,discount_type,discount_value,start_at,end_at
                       FROM campaigns WHERE status='active' AND approval_status='approved' AND start_at <= :n1 AND end_at >= :n2
                       ORDER BY end_at ASC");
    $st->execute([':n1'=>$now,':n2'=>$now]);
    $rows=$st->fetchAll();
    json_response(['success'=>true,'count'=>count($rows),'data'=>$rows]);
} catch (Throwable $e){ error_log('[Promotions Active] '.$e->getMessage()); json_response(['success'=>false,'message'=>'Không thể tải danh sách khuyến mãi.'],500);} 