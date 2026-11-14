<?php
/**
 * BLOOMORA - Inventory Service
 * Stock deduction, refund, manual adjustment, and low-stock queries.
 */

class InventoryService
{
    /**
     * Deduct stock for a sale.
     *
     * @param PDO      $pdo
     * @param int      $productId
     * @param int      $quantity   Positive number of units sold
     * @param int|null $orderId
     * @param int|null $userId
     * @return void
     */
    public static function deductStock(PDO $pdo, int $productId, int $quantity, ?int $orderId = null, ?int $userId = null): void
    {
        // Decrease stock_quantity
        $stmt = $pdo->prepare(
            "UPDATE products SET stock_quantity = stock_quantity - :qty WHERE id = :pid"
        );
        $stmt->execute([':qty' => $quantity, ':pid' => $productId]);

        // Fetch remaining stock for the log
        $remaining = self::getCurrentStock($pdo, $productId);

        // Insert inventory log
        $stmt = $pdo->prepare(
            "INSERT INTO inventory_logs (product_id, change_quantity, remaining_quantity, reason, order_id, user_id, note, created_at)
             VALUES (:pid, :change, :remaining, 'sale', :oid, :uid, NULL, NOW())"
        );
        $stmt->execute([
            ':pid'       => $productId,
            ':change'    => -$quantity,
            ':remaining' => $remaining,
            ':oid'       => $orderId,
            ':uid'       => $userId
        ]);
    }

    /**
     * Refund stock after an order cancellation.
     *
     * @param PDO      $pdo
     * @param int      $productId
     * @param int      $quantity   Positive number of units to return
     * @param int|null $orderId
     * @param int|null $userId
     * @return void
     */
    public static function refundStock(PDO $pdo, int $productId, int $quantity, ?int $orderId = null, ?int $userId = null): void
    {
        $stmt = $pdo->prepare(
            "UPDATE products SET stock_quantity = stock_quantity + :qty WHERE id = :pid"
        );
        $stmt->execute([':qty' => $quantity, ':pid' => $productId]);

        $remaining = self::getCurrentStock($pdo, $productId);

        $stmt = $pdo->prepare(
            "INSERT INTO inventory_logs (product_id, change_quantity, remaining_quantity, reason, order_id, user_id, note, created_at)
             VALUES (:pid, :change, :remaining, 'cancel_refund', :oid, :uid, NULL, NOW())"
        );
        $stmt->execute([
            ':pid'       => $productId,
            ':change'    => $quantity,
            ':remaining' => $remaining,
            ':oid'       => $orderId,
            ':uid'       => $userId
        ]);
    }

    /**
     * Manually adjust stock to a new absolute quantity (admin use).
     *
     * @param PDO         $pdo
     * @param int         $productId
     * @param int         $newQuantity  The desired stock level
     * @param string      $reason       One of the inventory_logs reason ENUM values
     * @param int|null    $userId       Admin who made the adjustment
     * @param string|null $note         Optional note
     * @return void
     */
    public static function adjustStock(PDO $pdo, int $productId, int $newQuantity, string $reason, ?int $userId = null, ?string $note = null): void
    {
        $currentStock = self::getCurrentStock($pdo, $productId);
        $change = $newQuantity - $currentStock;

        $stmt = $pdo->prepare(
            "UPDATE products SET stock_quantity = :newQty WHERE id = :pid"
        );
        $stmt->execute([':newQty' => $newQuantity, ':pid' => $productId]);

        $stmt = $pdo->prepare(
            "INSERT INTO inventory_logs (product_id, change_quantity, remaining_quantity, reason, order_id, user_id, note, created_at)
             VALUES (:pid, :change, :remaining, :reason, NULL, :uid, :note, NOW())"
        );
        $stmt->execute([
            ':pid'       => $productId,
            ':change'    => $change,
            ':remaining' => $newQuantity,
            ':reason'    => $reason,
            ':uid'       => $userId,
            ':note'      => $note
        ]);
    }

    /**
     * Get products whose stock is at or below the given threshold.
     *
     * @param PDO $pdo
     * @param int $threshold
     * @return array
     */
    public static function getLowStockProducts(PDO $pdo, int $threshold = 5): array
    {
        $stmt = $pdo->prepare(
            "SELECT * FROM products WHERE stock_quantity <= :threshold ORDER BY stock_quantity ASC"
        );
        $stmt->execute([':threshold' => $threshold]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Helper: get current stock_quantity for a product.
     */
    private static function getCurrentStock(PDO $pdo, int $productId): int
    {
        $stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = :pid LIMIT 1");
        $stmt->execute([':pid' => $productId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (int)$row['stock_quantity'] : 0;
    }
}
