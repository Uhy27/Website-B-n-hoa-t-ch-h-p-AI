<?php
/**
 * API: Kiểm Tra Mã Giảm Giá (Quick Check)
 * Endpoint: GET /api/vouchers/check.php?code=BLOOMORA10
 * Kiểm tra nhanh voucher có hợp lệ không, trả về thông tin cơ bản
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['success' => false, 'message' => 'Chỉ chấp nhận phương thức GET'], 405);
}

try {
    $code = isset($_GET['code']) ? trim($_GET['code']) : '';

    if (empty($code)) {
        json_response(['success' => false, 'message' => 'Vui lòng cung cấp mã giảm giá (tham số ?code=)!'], 400);
    }

    $pdo = getDBConnection();

    $stmt = $pdo->prepare("
        SELECT code, description, discount_type, discount_value, 
               min_order_value, max_discount_amount, end_at AS expires_at,
               is_active, start_at, usage_limit, used_count
        FROM vouchers 
        WHERE code = :code 
        LIMIT 1
    ");
    $stmt->execute([':code' => $code]);
    $voucher = $stmt->fetch();

    if (!$voucher) {
        json_response(['success' => false, 'message' => 'Mã giảm giá không tồn tại!'], 400);
    }

    // Kiểm tra trạng thái hoạt động
    if (!(int)$voucher['is_active']) {
        json_response(['success' => false, 'message' => 'Mã giảm giá đã bị vô hiệu hóa!'], 400);
    }

    // Kiểm tra thời gian hiệu lực
    $now = date('Y-m-d H:i:s');
    if ($now < $voucher['start_at']) {
        json_response(['success' => false, 'message' => 'Mã giảm giá chưa đến thời gian áp dụng!'], 400);
    }
    if ($now > $voucher['expires_at']) {
        json_response(['success' => false, 'message' => 'Mã giảm giá đã hết hạn!'], 400);
    }

    // Kiểm tra số lượt sử dụng
    if ((int)$voucher['used_count'] >= (int)$voucher['usage_limit']) {
        json_response(['success' => false, 'message' => 'Mã giảm giá đã hết lượt sử dụng!'], 400);
    }

    // Trả về thông tin voucher hợp lệ
    json_response([
        'success' => true,
        'data' => [
            'code' => $voucher['code'],
            'description' => $voucher['description'],
            'discount_type' => $voucher['discount_type'],
            'discount_value' => (float)$voucher['discount_value'],
            'min_order_value' => (float)$voucher['min_order_value'],
            'max_discount_amount' => $voucher['max_discount_amount'] !== null ? (float)$voucher['max_discount_amount'] : null,
            'expires_at' => $voucher['expires_at']
        ]
    ]);

} catch (Exception $e) {
    error_log("[Voucher Check Error] " . $e->getMessage());
    json_response([
        'success' => false,
        'message' => 'Không thể kiểm tra mã giảm giá. Vui lòng thử lại sau!'
    ], 500);
}
?>
