<?php
/**
 * BLOOMORA - Schedule Service
 * Checks promotion schedules and retrieves upcoming campaign events.
 * All times use Asia/Ho_Chi_Minh timezone.
 */

class ScheduleService
{
    /**
     * Check if a campaign is currently active according to its promotion_schedules.
     *
     * @param PDO $pdo
     * @param int $campaignId
     * @return bool
     */
    public static function isCampaignActiveNow(PDO $pdo, int $campaignId): bool
    {
        $tz  = new DateTimeZone('Asia/Ho_Chi_Minh');
        $now = new DateTimeImmutable('now', $tz);

        $currentDate      = $now->format('Y-m-d');
        $currentTime      = $now->format('H:i:s');
        $currentDayOfWeek = (int)$now->format('w'); // 0=Sun … 6=Sat
        $currentDayOfMonth = (int)$now->format('j');
        $currentMonth     = (int)$now->format('n');

        $stmt = $pdo->prepare(
            "SELECT * FROM promotion_schedules
             WHERE campaign_id = :cid AND is_active = 1"
        );
        $stmt->execute([':cid' => $campaignId]);
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($schedules)) {
            // No schedules defined — fall back to the campaign's own start_at/end_at
            return true;
        }

        foreach ($schedules as $schedule) {
            $matchesDate = false;

            switch ($schedule['schedule_type']) {
                case 'specific_date':
                    // day_of_month stores a specific date string "YYYY-MM-DD"
                    $matchesDate = ($schedule['day_of_month'] === $currentDate);
                    break;

                case 'date_range':
                    // Uses the campaign's start_at/end_at; the schedule row just
                    // constrains time-of-day via start_time/end_time.
                    $matchesDate = true;
                    break;

                case 'recurring_weekly':
                    $matchesDate = ($schedule['day_of_week'] !== null && (int)$schedule['day_of_week'] === $currentDayOfWeek);
                    break;

                case 'recurring_monthly':
                    // day_of_month can be comma-separated, e.g. "1,15"
                    if ($schedule['day_of_month'] !== null) {
                        $days = array_map('intval', explode(',', $schedule['day_of_month']));
                        $matchesDate = in_array($currentDayOfMonth, $days, true);
                    }
                    break;

                case 'holiday':
                    // month + day_of_month specify a holiday (e.g. month=10, day_of_month=20)
                    if ($schedule['month'] !== null && $schedule['day_of_month'] !== null) {
                        $matchesDate = ((int)$schedule['month'] === $currentMonth
                            && (int)$schedule['day_of_month'] === $currentDayOfMonth);
                    }
                    break;
            }

            if (!$matchesDate) {
                continue;
            }

            // Time-of-day window check
            $startTime = $schedule['start_time'] ?? '00:00:00';
            $endTime   = $schedule['end_time']   ?? '23:59:59';

            if ($currentTime >= $startTime && $currentTime <= $endTime) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the next N upcoming scheduled campaign events.
     *
     * @param PDO $pdo
     * @param int $limit
     * @return array
     */
    public static function getUpcomingEvents(PDO $pdo, int $limit = 5): array
    {
        $now = (new DateTimeImmutable('now', new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d H:i:s');

        $stmt = $pdo->prepare(
            "SELECT c.*, ps.schedule_type, ps.day_of_week, ps.day_of_month,
                    ps.month, ps.holiday_name, ps.start_time, ps.end_time
             FROM campaigns c
             INNER JOIN promotion_schedules ps ON ps.campaign_id = c.id AND ps.is_active = 1
             WHERE c.status IN ('scheduled', 'active')
               AND c.approval_status = 'approved'
               AND c.end_at >= :now
             ORDER BY c.start_at ASC
             LIMIT :lim"
        );
        $stmt->bindValue(':now', $now, PDO::PARAM_STR);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
