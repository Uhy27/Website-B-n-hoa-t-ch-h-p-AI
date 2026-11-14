<?php
/**
 * BLOOMORA - Budget Service
 * Campaign budget checking, deduction, and refunding.
 */

class BudgetService
{
    /**
     * Check whether a campaign has enough remaining budget for a discount.
     *
     * @param PDO   $pdo
     * @param int   $campaignId
     * @param float $discountAmount
     * @return array ['hasBudget'=>bool, 'remaining'=>float]
     */
    public static function checkBudget(PDO $pdo, int $campaignId, float $discountAmount): array
    {
        $stmt = $pdo->prepare("SELECT budget_limit, budget_used FROM campaigns WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $campaignId]);
        $campaign = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$campaign) {
            return ['hasBudget' => false, 'remaining' => 0.0];
        }

        $remaining = (float)$campaign['budget_limit'] - (float)$campaign['budget_used'];

        return [
            'hasBudget' => $remaining >= $discountAmount,
            'remaining' => round($remaining, 2)
        ];
    }

    /**
     * Deduct a discount amount from a campaign's budget.
     * Logs the deduction and marks the campaign budget_exhausted when fully spent.
     *
     * @param PDO   $pdo
     * @param int   $campaignId
     * @param int   $orderId
     * @param float $amount
     * @return void
     */
    public static function deductBudget(PDO $pdo, int $campaignId, int $orderId, float $amount): void
    {
        // Update budget_used
        $stmt = $pdo->prepare(
            "UPDATE campaigns SET budget_used = budget_used + :amount, updated_at = NOW() WHERE id = :id"
        );
        $stmt->execute([':amount' => $amount, ':id' => $campaignId]);

        // Fetch updated values for the log
        $stmt = $pdo->prepare("SELECT budget_limit, budget_used FROM campaigns WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $campaignId]);
        $campaign = $stmt->fetch(PDO::FETCH_ASSOC);

        $budgetRemaining = (float)$campaign['budget_limit'] - (float)$campaign['budget_used'];

        // Insert budget log
        $stmt = $pdo->prepare(
            "INSERT INTO promotion_budget_logs (campaign_id, order_id, amount_deducted, budget_remaining, type, created_at)
             VALUES (:cid, :oid, :amount, :remaining, 'spend', NOW())"
        );
        $stmt->execute([
            ':cid'       => $campaignId,
            ':oid'       => $orderId,
            ':amount'    => $amount,
            ':remaining' => $budgetRemaining
        ]);

        // If budget exhausted, update campaign status
        if ((float)$campaign['budget_used'] >= (float)$campaign['budget_limit']) {
            $stmt = $pdo->prepare(
                "UPDATE campaigns SET status = 'budget_exhausted', updated_at = NOW() WHERE id = :id"
            );
            $stmt->execute([':id' => $campaignId]);
        }
    }

    /**
     * Refund a previously deducted budget amount (e.g. order cancellation).
     *
     * @param PDO   $pdo
     * @param int   $campaignId
     * @param int   $orderId
     * @param float $amount
     * @return void
     */
    public static function refundBudget(PDO $pdo, int $campaignId, int $orderId, float $amount): void
    {
        // Decrease budget_used (floor at 0)
        $stmt = $pdo->prepare(
            "UPDATE campaigns
             SET budget_used = GREATEST(budget_used - :amount, 0),
                 updated_at  = NOW()
             WHERE id = :id"
        );
        $stmt->execute([':amount' => $amount, ':id' => $campaignId]);

        // Fetch updated values
        $stmt = $pdo->prepare("SELECT budget_limit, budget_used FROM campaigns WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $campaignId]);
        $campaign = $stmt->fetch(PDO::FETCH_ASSOC);

        $budgetRemaining = (float)$campaign['budget_limit'] - (float)$campaign['budget_used'];

        // Insert refund log
        $stmt = $pdo->prepare(
            "INSERT INTO promotion_budget_logs (campaign_id, order_id, amount_deducted, budget_remaining, type, created_at)
             VALUES (:cid, :oid, :amount, :remaining, 'refund', NOW())"
        );
        $stmt->execute([
            ':cid'       => $campaignId,
            ':oid'       => $orderId,
            ':amount'    => $amount,
            ':remaining' => $budgetRemaining
        ]);

        // If campaign was budget_exhausted and now has room, re-activate it
        if ((float)$campaign['budget_used'] < (float)$campaign['budget_limit']) {
            $stmt = $pdo->prepare(
                "UPDATE campaigns SET status = 'active', updated_at = NOW()
                 WHERE id = :id AND status = 'budget_exhausted'"
            );
            $stmt->execute([':id' => $campaignId]);
        }
    }
}
