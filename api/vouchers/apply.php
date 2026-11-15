<?php
declare(strict_types=1);
require_once __DIR__ . '/../../core/bootstrap.php';
init_secure_session(); api_only_method('POST');
try {
    $pdo=getDBConnection(); $in=get_json_input();
    $code=trim((string)($in['code']??'')); $subtotal=(float)($in['subtotal']??0);
    $items=(isset($in['items'])&&is_array($in['items']))?$in['items']:[];
    $userId=!empty($_SESSION['user_id'])?(int)$_SESSION['user_id']:null;
    if ($code==='') json_response(['success'=>false,'message'=>'Vui lòng nhập mã giảm giá.'],400);
    if ($subtotal<=0 || empty($items)) json_response(['success'=>false,'message'=>'Giỏ hàng không hợp lệ.'],400);
    $res = PromotionService::checkVoucher($pdo,$code,$subtotal,$userId);
    if (!$res['valid']) json_response(['success'=>false,'message'=>$res['message']],400);
    $voucher=$res['voucher']; $discount=(float)$res['discountAmount'];

    // compute total cost for margin check
    $ids=[]; $qtyMap=[];
    foreach($items as $it){ $pid=(int)($it['productId'] ?? $it['id'] ?? 0); if($pid>0){$ids[$pid]=true; $qtyMap[$pid]=($qtyMap[$pid]??0)+max(1,(int)($it['quantity']??1));}}
    $totalCost=0.0;
    if(!empty($ids)){
        $ph=implode(',',array_fill(0,count($ids),'?'));
        $st=$pdo->prepare("SELECT id, cost_price FROM products WHERE id IN ($ph)");
        $st->execute(array_keys($ids));
        foreach($st->fetchAll() as $r){ $totalCost += (float)($r['cost_price']??0) * ($qtyMap[(int)$r['id']]??0); }
    }
    $minMargin=20.0;
    if(!empty($voucher['campaign_id'])){
        $c=$pdo->prepare('SELECT min_margin,budget_limit,budget_used,status FROM campaigns WHERE id=:id');
        $c->execute([':id'=>(int)$voucher['campaign_id']]); $camp=$c->fetch();
        if($camp){ $minMargin=(float)($camp['min_margin']??20.0);
            if(class_exists('BudgetService')){ $b=BudgetService::checkBudget($pdo,(int)$voucher['campaign_id'],$discount); if(!$b['hasBudget']) json_response(['success'=>false,'message'=>'Ngân sách khuyến mãi đã hết.'],400);} }
    }
    if($totalCost>0 && class_exists('MarginService')){
        $m=MarginService::evaluateSafety($subtotal,$discount,$totalCost,$minMargin);
        if(!$m['isSafe']){ $discount=min($discount,(float)$m['maxAllowedDiscount']); $discount=max(0.0,round($discount,2)); }
    }
    if($discount>$subtotal) $discount=$subtotal;
    json_response(['success'=>true,'discountAmount'=>round($discount,2),'finalSubtotal'=>round($subtotal-$discount,2),'voucherCode'=>$voucher['code'],'message'=>'Áp dụng mã giảm giá thành công.']);
} catch (Throwable $e){ error_log('[Voucher Apply] '.$e->getMessage()); json_response(['success'=>false,'message'=>'Không thể xử lý mã giảm giá.'],500);} 