<?php
/**
 * BLOOMORA - Margin Safety Service
 * Evaluates whether a discount keeps the order above the minimum profit margin.
 */

class MarginService
{
    /**
     * Evaluate whether a discount is safe with respect to the minimum margin.
     *
     * Formula:
     *   netRevenue = subtotal - discountAmount
     *   grossProfit = netRevenue - totalCostPrice
     *   margin = (grossProfit / netRevenue) * 100
     *
     * @param float $subtotal         Cart subtotal (selling price total)
     * @param float $discountAmount   Discount to apply
     * @param float $totalCostPrice   Sum of cost_price * qty for all items
     * @param float $minMarginPercent Minimum acceptable margin (default 20%)
     * @return array ['isSafe'=>bool, 'actualMargin'=>float, 'maxAllowedDiscount'=>float, 'message'=>string]
     */
    public static function evaluateSafety(
        float $subtotal,
        float $discountAmount,
        float $totalCostPrice,
        float $minMarginPercent = 20.0
    ): array {
        $netRevenue = $subtotal - $discountAmount;

        // Guard against division by zero or negative net revenue
        if ($netRevenue <= 0) {
            return [
                'isSafe'             => false,
                'actualMargin'       => -100.0,
                'maxAllowedDiscount' => max(0, $subtotal - $totalCostPrice / (1 - $minMarginPercent / 100)),
                'message'            => 'Giảm giá vượt quá tổng đơn hàng, không đảm bảo lợi nhuận.'
            ];
        }

        $grossProfit = $netRevenue - $totalCostPrice;
        $actualMargin = ($grossProfit / $netRevenue) * 100.0;
        $actualMargin = round($actualMargin, 2);

        // maxAllowedDiscount: the largest discount that keeps margin >= minMarginPercent
        // margin = ((subtotal - discount - cost) / (subtotal - discount)) * 100 >= minMargin
        // => subtotal - discount - cost >= minMargin/100 * (subtotal - discount)
        // => (subtotal - discount)(1 - minMargin/100) >= cost
        // => subtotal - discount >= cost / (1 - minMargin/100)
        // => discount <= subtotal - cost / (1 - minMargin/100)
        $minMarginFraction = $minMarginPercent / 100.0;
        if ($minMarginFraction < 1.0) {
            $maxAllowedDiscount = $subtotal - ($totalCostPrice / (1.0 - $minMarginFraction));
        } else {
            $maxAllowedDiscount = 0.0;
        }
        $maxAllowedDiscount = round(max(0.0, $maxAllowedDiscount), 2);

        $isSafe = $actualMargin >= $minMarginPercent;

        if ($isSafe) {
            $message = "Biên lợi nhuận {$actualMargin}% đạt yêu cầu (>= {$minMarginPercent}%).";
        } else {
            $message = "Biên lợi nhuận {$actualMargin}% thấp hơn mức tối thiểu {$minMarginPercent}%. Giảm giá tối đa cho phép: " . number_format($maxAllowedDiscount, 0, ',', '.') . "đ.";
        }

        return [
            'isSafe'             => $isSafe,
            'actualMargin'       => $actualMargin,
            'maxAllowedDiscount' => $maxAllowedDiscount,
            'message'            => $message
        ];
    }
}
