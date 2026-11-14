<?php
/**
 * BLOOMORA - Promotion Service
 * Voucher validation, active campaigns, discount calculation
 */

class PromotionService
{
    /**
     * Validate a voucher code against business rules.
     *
     * @param PDO    $pdo
     * @param string $code     Voucher code
     * @param float  $subtotal Cart subtotal before discount
     * @param int|null $userId Logged-in user id (null for guest)
     * @return array ['valid'=>bool, 'voucher'=>?array, 'discountAmount'=>float, 'message'=>string]
     */
    public static function checkVoucher(PDO $pdo, string $code, float $subtotal, ?int $userId): array
    {
        $now = (new DateTimeImmutable('now', new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d H:i:s');

        // 1. Fetch voucher by code
        $stmt = $pdo->prepare("SELECT * FROM vouchers WHERE code = :code LIMIT 1");
        $stmt->execute([':code' => $code]);
        $voucher = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$voucher) {
            return ['valid' => false, 'voucher' => null, 'discountAmount' => 0.0, 'message' => 'Mã giảm giá không tồn tại.'];
        }

        // 2. Active check
        if (!(int)$voucher['is_active']) {
            return ['valid' => false, 'voucher' => $voucher, 'discountAmount' => 0.0, 'message' => 'Mã giảm giá đã bị vô hiệu hóa.'];
        }

        // 3. Date range check
        if ($now < $voucher['start_at'] || $now > $voucher['end_at']) {
            return ['valid' => false, 'voucher' => $voucher, 'discountAmount' => 0.0, 'message' => 'Mã giảm giá không trong thời gian hiệu lực.'];
        }

        // 4. Global usage limit
        if ((int)$voucher['used_count'] >= (int)$voucher['usage_limit']) {
            return ['valid' => false, 'voucher' => $voucher, 'discountAmount' => 0.0, 'message' => 'Mã giảm giá đã hết lượt sử dụng.'];
        }

        // 5. Per-user limit (only when userId is provided)
        if ($userId !== null && (int)$voucher['per_user_limit'] > 0) {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM voucher_usages WHERE voucher_id = :vid AND user_id = :uid AND status = 'used'"
            );
            $stmt->execute([':vid' => $voucher['id'], ':uid' => $userId]);
            $userUsed = (int)$stmt->fetchColumn();

            if ($userUsed >= (int)$voucher['per_user_limit']) {
                return ['valid' => false, 'voucher' => $voucher, 'discountAmount' => 0.0, 'message' => 'Bạn đã sử dụng hết lượt cho mã giảm giá này.'];
            }
        }

        // 6. Minimum order value
        if ($subtotal < (float)$voucher['min_order_value']) {
            $formatted = number_format((float)$voucher['min_order_value'], 0, ',', '.');
            return ['valid' => false, 'voucher' => $voucher, 'discountAmount' => 0.0, 'message' => "Đơn hàng tối thiểu {$formatted}đ để sử dụng mã này."];
        }

        // 7. Calculate discount
        $discountAmount = self::calculateDiscount(
            $voucher['discount_type'],
            (float)$voucher['discount_value'],
            $subtotal,
            isset($voucher['max_discount_amount']) ? (float)$voucher['max_discount_amount'] : null
        );

        return [
            'valid'          => true,
            'voucher'        => $voucher,
            'discountAmount' => $discountAmount,
            'message'        => 'Áp dụng mã giảm giá thành công!'
        ];
    }

    /**
     * Get active, approved campaigns that are within budget and date range.
     *
     * @param PDO      $pdo
     * @param int|null $productId Filter to campaigns linked to this product
     * @return array
     */
    public static function getActiveCampaigns(PDO $pdo, ?int $productId = null): array
    {
        $now = (new DateTimeImmutable('now', new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d H:i:s');

        $sql = "SELECT c.* FROM campaigns c";

        if ($productId !== null) {
            $sql .= " INNER JOIN promotion_products pp ON pp.campaign_id = c.id AND pp.product_id = :productId";
        }

        $sql .= " WHERE c.status = 'active'
                    AND c.approval_status = 'approved'
                    AND c.start_at <= :now1
                    AND c.end_at   >= :now2
                    AND c.budget_used < c.budget_limit
                  ORDER BY c.start_at ASC";

        $stmt = $pdo->prepare($sql);
        $params = [':now1' => $now, ':now2' => $now];
        if ($productId !== null) {
            $params[':productId'] = $productId;
        }
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calculate the actual discount amount for a given type/value.
     *
     * @param string     $discountType      'percentage' or 'fixed_amount'
     * @param float      $discountValue     The raw value (e.g. 10 for 10% or 50000 for 50k)
     * @param float      $subtotal          Cart subtotal
     * @param float|null $maxDiscountAmount Cap for percentage discounts
     * @return float
     */
    public static function calculateDiscount(string $discountType, float $discountValue, float $subtotal, ?float $maxDiscountAmount): float
    {
        $discount = 0.0;

        if ($discountType === 'percentage') {
            $discount = $subtotal * ($discountValue / 100.0);
        } elseif ($discountType === 'fixed_amount') {
            $discount = $discountValue;
        }

        // Cap at max discount amount if specified
        if ($maxDiscountAmount !== null && $maxDiscountAmount > 0 && $discount > $maxDiscountAmount) {
            $discount = $maxDiscountAmount;
        }

        // Discount cannot exceed subtotal
        if ($discount > $subtotal) {
            $discount = $subtotal;
        }

        return round($discount, 2);
    }
}
