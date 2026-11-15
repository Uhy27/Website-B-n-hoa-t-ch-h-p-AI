<?php
declare(strict_types=1);
/**
 * POST /api/checkout.php - Create order (server-side pricing, stock & voucher safe)
 */
require_once __DIR__ . '/../core/bootstrap.php';

init_secure_session();
api_only_method('POST');

try {
    $pdo = getDBConnection();
    $input = get_json_input();

    $senderName    = sanitize_string($input['senderName'] ?? 'Khách hàng', 100);
    $senderPhone   = sanitize_string($input['senderPhone'] ?? '', 20);
    $isAnonymous   = !empty($input['isAnonymous']) ? 1 : 0;
    $receiverName  = sanitize_string($input['receiverName'] ?? '', 100);
    $receiverPhone = sanitize_string($input['receiverPhone'] ?? '', 20);
    $address       = sanitize_string($input['address'] ?? '', 255);
    $city          = sanitize_string($input['city'] ?? 'TP. Hồ Chí Minh', 50);
    $deliveryDate  = sanitize_string($input['deliveryDate'] ?? date('Y-m-d'), 20);
    $deliveryTime  = sanitize_string($input['deliveryTime'] ?? 'Hỏa tốc 60-90 phút', 50);
    $bannerMessage = sanitize_string($input['bannerMessage'] ?? '', 500);
    $paymentMethod = in_array(($input['paymentMethod'] ?? 'vietqr'), ['vietqr', 'cod'], true) ? $input['paymentMethod'] : 'vietqr';
    $voucherCode   = sanitize_string($input['voucherCode'] ?? ($input['code'] ?? ''), 50);
    $items         = (isset($input['items']) && is_array($input['items'])) ? $input['items'] : [];
    $userId        = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

    if ($receiverName === '' || $receiverPhone === '' || $address === '') {
        json_response(['success' => false, 'message' => 'Vui lòng cung cấp đầy đủ tên, số điện thoại và địa chỉ người nhận.'], 400);
    }
    if (empty($items)) {
        json_response(['success' => false, 'message' => 'Giỏ hàng của bạn đang trống.'], 400);
    }

    $addonPrices = [
        'gau-bong' => ['name' => 'Gấu Bông Teddy Cao Cấp', 'price' => 80000],
        'socola'   => ['name' => 'Hộp Socola Ferrero Rocher', 'price' => 120000],
        'nen-thom' => ['name' => 'Nến Thơm Tinh Dầu Tự Nhiên', 'price' => 150000],
        'banh-kem' => ['name' => 'Bánh Kem Mini Chúc Mừng', 'price' => 180000],
    ];

    $pdo->beginTransaction();

    $productStmt = $pdo->prepare("SELECT id, name, sku, price, cost_price, stock_quantity, thumbnail
                                  FROM products WHERE (id = :id OR sku = :sku) AND is_active = 1 LIMIT 1 FOR UPDATE");
    $updateStockStmt = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - :qty WHERE id = :id");
    $logStmt = $pdo->prepare("INSERT INTO inventory_logs (product_id, change_quantity, remaining_quantity, reason, order_id, user_id, note, created_at)
                              VALUES (:pid, :chg, :rem, 'sale', :oid, :uid, :note, NOW())");

    $subtotal = 0.0;
    $totalCost = 0.0;
    $orderItemsData = [];

    foreach ($items as $item) {
        $itemId = $item['id'] ?? ($item['productId'] ?? null);
        $qty = max(1, (int)($item['quantity'] ?? 1));
        if ($itemId === null || $itemId === '') continue;

        $productStmt->execute([':id' => $itemId, ':sku' => $itemId]);
        $prod = $productStmt->fetch();
        if (!$prod) continue;

        if ((int)$prod['stock_quantity'] < $qty) {
            $pdo->rollBack();
            json_response(['success' => false, 'message' => "Sản phẩm '{$prod['name']}' chỉ còn {$prod['stock_quantity']} trong kho."], 400);
        }

        $unitPrice = (float)$prod['price'];
        $addonsExtra = 0.0; $addonNames = [];
        if (!empty($item['addons']) && is_array($item['addons'])) {
            foreach ($item['addons'] as $add) {
                $aid = is_array($add) ? ($add['id'] ?? '') : $add;
                if (isset($addonPrices[$aid])) { $addonsExtra += (float)$addonPrices[$aid]['price']; $addonNames[] = $addonPrices[$aid]['name']; }
            }
        }
        $lineUnit = $unitPrice + $addonsExtra;
        $lineTotal = $lineUnit * $qty;
        $subtotal += $lineTotal;
        $totalCost += ((float)($prod['cost_price'] ?? 0)) * $qty;

        $displayName = $prod['name'] . (!empty($addonNames) ? ' (Kèm: ' . implode(', ', $addonNames) . ')' : '');

        $orderItemsData[] = [
            'product_id' => (int)$prod['id'], 'product_name' => $displayName, 'product_sku' => $prod['sku'],
            'unit_price' => $lineUnit, 'quantity' => $qty, 'total_price' => $lineTotal,
            'stock_after' => (int)$prod['stock_quantity'] - $qty
        ];
    }

    if (empty($orderItemsData)) {
        $pdo->rollBack();
        json_response(['success' => false, 'message' => 'Không có sản phẩm hợp lệ trong giỏ hàng.'], 400);
    }

    // Voucher (server-side validation)
    $discountAmount = 0.0; $appliedVoucher = null;
    if ($voucherCode !== '' && class_exists('PromotionService')) {
        $res = PromotionService::checkVoucher($pdo, $voucherCode, $subtotal, $userId);
        if (!$res['valid']) { $pdo->rollBack(); json_response(['success' => false, 'message' => $res['message']], 400); }
        $appliedVoucher = $res['voucher'];
        $discountAmount = (float)$res['discountAmount'];

        // Budget check for campaign-linked voucher
        if (!empty($appliedVoucher['campaign_id']) && class_exists('BudgetService')) {
            $b = BudgetService::checkBudget($pdo, (int)$appliedVoucher['campaign_id'], $discountAmount);
            if (!$b['hasBudget']) { $pdo->rollBack(); json_response(['success' => false, 'message' => 'Ngân sách khuyến mãi đã hết.'], 400); }
        }
        // Margin safety
        $minMargin = isset($appliedVoucher['campaign_id']) ? 20.0 : 15.0;
        if (!empty($appliedVoucher['campaign_id'])) {
            $cStmt = $pdo->prepare('SELECT min_margin FROM campaigns WHERE id = :id');
            $cStmt->execute([':id' => (int)$appliedVoucher['campaign_id']]);
            $minMargin = (float)($cStmt->fetchColumn() ?: 20.0);
        }
        if ($totalCost > 0 && class_exists('MarginService')) {
            $m = MarginService::evaluateSafety($subtotal, $discountAmount, $totalCost, $minMargin);
            if (!$m['isSafe']) { $pdo->rollBack(); json_response(['success' => false, 'message' => $m['message']], 400); }
        }
    }

    $discountAmount = round(min($discountAmount, $subtotal), 2);
    $shippingFee = 0.00;
    $totalAmount = round($subtotal - $discountAmount + $shippingFee, 2);

    // Unique order code
    $orderCode = '';
    for ($i = 0; $i < 10; $i++) {
        $candidate = 'BL' . random_int(10000, 99999);
        $chk = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE order_code = :c');
        $chk->execute([':c' => $candidate]);
        if ((int)$chk->fetchColumn() === 0) { $orderCode = $candidate; break; }
    }
    if ($orderCode === '') $orderCode = 'BL' . date('ymdHis');

    // Insert order (supports optional voucher_code column)
    $hasVoucherCol = (bool)$pdo->query("SHOW COLUMNS FROM orders LIKE 'voucher_code'")->fetch();
    if ($hasVoucherCol) {
        $orderSql = "INSERT INTO orders (order_code,user_id,sender_name,sender_phone,is_anonymous,receiver_name,receiver_phone,receiver_address,city,delivery_date,delivery_time_slot,card_message,ribbon_message,voucher_code,subtotal,shipping_fee,discount_amount,total_amount,payment_method,payment_status,order_status)
                     VALUES (:order_code,:user_id,:sender_name,:sender_phone,:is_anonymous,:receiver_name,:receiver_phone,:receiver_address,:city,:delivery_date,:delivery_time_slot,:card_message,:ribbon_message,:voucher_code,:subtotal,:shipping_fee,:discount_amount,:total_amount,:payment_method,'unpaid','received')";
    } else {
        $orderSql = "INSERT INTO orders (order_code,user_id,sender_name,sender_phone,is_anonymous,receiver_name,receiver_phone,receiver_address,city,delivery_date,delivery_time_slot,card_message,ribbon_message,subtotal,shipping_fee,discount_amount,total_amount,payment_method,payment_status,order_status)
                     VALUES (:order_code,:user_id,:sender_name,:sender_phone,:is_anonymous,:receiver_name,:receiver_phone,:receiver_address,:city,:delivery_date,:delivery_time_slot,:card_message,:ribbon_message,:subtotal,:shipping_fee,:discount_amount,:total_amount,:payment_method,'unpaid','received')";
    }
    $params = [
        ':order_code'=>$orderCode, ':user_id'=>$userId, ':sender_name'=>$senderName, ':sender_phone'=>$senderPhone,
        ':is_anonymous'=>$isAnonymous, ':receiver_name'=>$receiverName, ':receiver_phone'=>$receiverPhone,
        ':receiver_address'=>$address, ':city'=>$city, ':delivery_date'=>$deliveryDate, ':delivery_time_slot'=>$deliveryTime,
        ':card_message'=>$bannerMessage, ':ribbon_message'=>$bannerMessage,
        ':subtotal'=>$subtotal, ':shipping_fee'=>$shippingFee, ':discount_amount'=>$discountAmount,
        ':total_amount'=>$totalAmount, ':payment_method'=>$paymentMethod
    ];
    if ($hasVoucherCol) $params[':voucher_code'] = ($appliedVoucher ? $appliedVoucher['code'] : null);
    $pdo->prepare($orderSql)->execute($params);
    $orderId = (int)$pdo->lastInsertId();

    $itemInsert = $pdo->prepare("INSERT INTO order_items (order_id,product_id,product_name,product_sku,unit_price,quantity,total_price)
                                 VALUES (:order_id,:product_id,:product_name,:product_sku,:unit_price,:quantity,:total_price)");
    foreach ($orderItemsData as $it) {
        $itemInsert->execute([
            ':order_id'=>$orderId, ':product_id'=>$it['product_id'], ':product_name'=>$it['product_name'],
            ':product_sku'=>$it['product_sku'], ':unit_price'=>$it['unit_price'], ':quantity'=>$it['quantity'], ':total_price'=>$it['total_price']
        ]);
        $updateStockStmt->execute([':qty'=>$it['quantity'], ':id'=>$it['product_id']]);
        // inventory log if table exists
        try {
            $logStmt->execute([
                ':pid'=>$it['product_id'], ':chg'=>-$it['quantity'], ':rem'=>$it['stock_after'],
                ':oid'=>$orderId, ':uid'=>$userId, ':note'=>'Bán hàng đơn '.$orderCode
            ]);
        } catch (Throwable $ignore) {}
    }

    // Record voucher usage & budget
    if ($appliedVoucher) {
        try {
            $pdo->prepare("INSERT INTO voucher_usages (voucher_id,order_id,user_id,discount_amount,status,used_at) VALUES (:vid,:oid,:uid,:amt,'used',NOW())")
                ->execute([':vid'=>$appliedVoucher['id'], ':oid'=>$orderId, ':uid'=>$userId, ':amt'=>$discountAmount]);
            $pdo->prepare("UPDATE vouchers SET used_count = used_count + 1 WHERE id = :id")->execute([':id'=>$appliedVoucher['id']]);
            if (!empty($appliedVoucher['campaign_id']) && class_exists('BudgetService')) {
                BudgetService::deductBudget($pdo, (int)$appliedVoucher['campaign_id'], $orderId, $discountAmount);
            }
        } catch (Throwable $e) { /* tables may not exist yet in legacy installs */ }
    }

    $pdo->prepare("INSERT INTO order_status_history (order_id,status,note,created_by) VALUES (:oid,'received','Hệ thống đã tiếp nhận đơn hàng',:uid)")
        ->execute([':oid'=>$orderId, ':uid'=>$userId]);

    $pdo->commit();

    $vietqrUrl = "https://img.vietqr.io/image/MB-0908123456-compact.png?amount=" . (int)round($totalAmount) . "&addInfo={$orderCode}&accountName=BLOOMORA%20FLORIST";
    json_response([
        'success'=>true, 'message'=>'Đặt hoa thành công!',
        'data'=>[
            'orderId'=>$orderId, 'orderCode'=>$orderCode, 'subtotal'=>$subtotal, 'discountAmount'=>$discountAmount,
            'totalAmount'=>$totalAmount, 'paymentMethod'=>$paymentMethod, 'vietqrUrl'=>$vietqrUrl,
            'senderName'=>$isAnonymous ? 'Người tặng ẩn danh' : $senderName,
            'receiverName'=>$receiverName, 'receiverPhone'=>$receiverPhone, 'address'=>$address,
            'deliveryDate'=>$deliveryDate, 'deliveryTime'=>$deliveryTime, 'bannerMessage'=>$bannerMessage
        ]
    ]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('[Checkout Error] ' . $e->getMessage());
    json_response(['success'=>false,'message'=>'Không thể hoàn tất đơn hàng. Vui lòng thử lại sau.'], 500);
}