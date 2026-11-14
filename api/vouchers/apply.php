<?php
/**
 * API: Áp Dụng Mã Giảm Giá (Voucher)
 * Endpoint: POST /api/vouchers/apply.php
 * Kiểm tra & tính toán giảm giá voucher cho giỏ hàng, bảo vệ biên lợi nhuận
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

// Tải service nếu có (không crash nếu chưa tồn tại)
$servicesPath = __DIR__ . '/../../app/Services/';
foreach (['PromotionService.php', 'MarginService.php'] as $svc) {
    if (file_exists($servicesPath . $svc)) {
        require_once $servicesPath . $svc;
    }
}

init_secure_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Chỉ chấp nhận phương thức POST'], 405);
}

try {
    $pdo = getDBConnection();
    $input = get_json_input();

    // Validate input
    $code = isset($input['code']) ? trim($input['code']) : '';
    $subtotal = isset($input['subtotal']) ? (float)$input['subtotal'] : 0;
    $items = isset($input['items']) && is_array($input['items']) ? $input['items'] : [];

    if (empty($code)) {
        json_response(['success' => false, 'message' => 'Vui lòng nhập mã giảm giá!'], 400);
    }

    if ($subtotal <= 0) {
        json_response(['success' => false, 'message' => 'Giá trị đơn hàng không hợp lệ!'], 400);
    }

    if (empty($items)) {
        json_response(['success' => false, 'message' => 'Giỏ hàng không có sản phẩm nào!'], 400);
    }

    // Truy vấn voucher theo mã
    $voucherStmt = $pdo->prepare("
        SELECT v.*, c.min_margin, c.budget_limit, c.budget_used, c.status AS campaign_status
        FROM vouchers v
        LEFT JOIN campaigns c ON v.campaign_id = c.id
        WHERE v.code = :code
        LIMIT 1
    ");
    $voucherStmt->execute([':code' => $code]);
    $voucher = $voucherStmt->fetch();

    if (!$voucher) {
        json_response(['success' => false, 'message' => 'Mã giảm giá không tồn tại!'], 400);
    }

    // Kiểm tra voucher có đang hoạt động
    if (!(int)$voucher['is_active']) {
        json_response(['success' => false, 'message' => 'Mã giảm giá đã bị vô hiệu hóa!'], 400);
    }

    // Kiểm tra thời gian hiệu lực
    $now = date('Y-m-d H:i:s');
    if ($now < $voucher['start_at'] || $now > $voucher['end_at']) {
        json_response(['success' => false, 'message' => 'Mã giảm giá đã hết hạn hoặc chưa đến thời gian áp dụng!'], 400);
    }

    // Kiểm tra số lượt sử dụng
    if ((int)$voucher['used_count'] >= (int)$voucher['usage_limit']) {
        json_response(['success' => false, 'message' => 'Mã giảm giá đã hết lượt sử dụng!'], 400);
    }

    // Kiểm tra giới hạn mỗi người dùng (nếu đã đăng nhập)
    $userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    if ($userId && (int)$voucher['per_user_limit'] > 0) {
        $usageStmt = $pdo->prepare("
            SELECT COUNT(*) FROM voucher_usages 
            WHERE voucher_id = :vid AND user_id = :uid AND status = 'used'
        ");
        $usageStmt->execute([':vid' => $voucher['id'], ':uid' => $userId]);
        $userUsed = (int)$usageStmt->fetchColumn();

        if ($userUsed >= (int)$voucher['per_user_limit']) {
            json_response(['success' => false, 'message' => 'Bạn đã sử dụng hết lượt áp dụng mã giảm giá này!'], 400);
        }
    }

    // Kiểm tra giá trị đơn hàng tối thiểu
    if ($subtotal < (float)$voucher['min_order_value']) {
        $minFormatted = number_format((float)$voucher['min_order_value'], 0, ',', '.');
        json_response([
            'success' => false,
            'message' => "Đơn hàng tối thiểu {$minFormatted}₫ để sử dụng mã giảm giá này!"
        ], 400);
    }

    // Tính toán giảm giá
    $discountAmount = 0;
    if ($voucher['discount_type'] === 'percentage') {
        $discountAmount = $subtotal * ((float)$voucher['discount_value'] / 100);
    } else {
        // fixed_amount
        $discountAmount = (float)$voucher['discount_value'];
    }

    // Giới hạn tối đa
    if (!empty($voucher['max_discount_amount']) && $discountAmount > (float)$voucher['max_discount_amount']) {
        $discountAmount = (float)$voucher['max_discount_amount'];
    }

    // Không giảm nhiều hơn subtotal
    if ($discountAmount > $subtotal) {
        $discountAmount = $subtotal;
    }

    // Kiểm tra biên lợi nhuận (margin safety) nếu có cost_price
    $minMargin = isset($voucher['min_margin']) ? (float)$voucher['min_margin'] : 0;
    if ($minMargin > 0 && !empty($items)) {
        // Tính tổng cost_price cho các items
        $productIds = [];
        $itemMap = []; // productId => quantity
        foreach ($items as $it) {
            $pid = isset($it['productId']) ? (int)$it['productId'] : (isset($it['id']) ? (int)$it['id'] : 0);
            if ($pid > 0) {
                $productIds[] = $pid;
                $qty = max(1, (int)($it['quantity'] ?? 1));
                $itemMap[$pid] = ($itemMap[$pid] ?? 0) + $qty;
            }
        }

        if (!empty($productIds)) {
            $placeholders = implode(',', array_fill(0, count($productIds), '?'));
            try {
                $costStmt = $pdo->prepare("SELECT id, cost_price FROM products WHERE id IN ($placeholders)");
                $costStmt->execute(array_values($productIds));
                $costRows = $costStmt->fetchAll();

                $totalCost = 0;
                foreach ($costRows as $cr) {
                    $qty = $itemMap[(int)$cr['id']] ?? 0;
                    $totalCost += (float)$cr['cost_price'] * $qty;
                }

                if ($totalCost > 0) {
                    // margin = (subtotal - discount - totalCost) / (subtotal - discount) * 100
                    $afterDiscount = $subtotal - $discountAmount;
                    if ($afterDiscount > 0) {
                        $margin = (($afterDiscount - $totalCost) / $afterDiscount) * 100;
                        if ($margin < $minMargin) {
                            // Giới hạn discount để duy trì biên lợi nhuận tối thiểu
                            // minMargin = (subtotal - newDiscount - totalCost) / (subtotal - newDiscount) * 100
                            // => subtotal - newDiscount - totalCost = minMargin/100 * (subtotal - newDiscount)
                            // => (subtotal - newDiscount)(1 - minMargin/100) = totalCost
                            // => subtotal - newDiscount = totalCost / (1 - minMargin/100)
                            $factor = 1 - ($minMargin / 100);
                            if ($factor > 0) {
                                $maxFinalPrice = $totalCost / $factor;
                                $cappedDiscount = $subtotal - $maxFinalPrice;
                                if ($cappedDiscount > 0 && $cappedDiscount < $discountAmount) {
                                    $discountAmount = round($cappedDiscount, 2);
                                } elseif ($cappedDiscount <= 0) {
                                    $discountAmount = 0;
                                }
                            }
                        }
                    }
                }
            } catch (PDOException $e) {
                // cost_price column might not exist yet, skip margin check
                error_log("[Voucher Apply] Margin check skipped: " . $e->getMessage());
            }
        }
    }

    $discountAmount = round($discountAmount, 2);
    $finalSubtotal = round($subtotal - $discountAmount, 2);

    json_response([
        'success' => true,
        'discountAmount' => $discountAmount,
        'finalSubtotal' => $finalSubtotal,
        'voucherCode' => $voucher['code'],
        'message' => 'Áp dụng mã giảm giá thành công!'
    ]);

} catch (Exception $e) {
    error_log("[Voucher Apply Error] " . $e->getMessage());
    json_response([
        'success' => false,
        'message' => 'Không thể xử lý mã giảm giá. Vui lòng thử lại sau!'
    ], 500);
}
?>
