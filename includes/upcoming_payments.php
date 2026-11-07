<?php

const UPCOMING_PAYMENTS_LIMITS = [3, 5, 10, 20];

/**
 * Parse a dashboard limit and return null for unsupported values.
 *
 * @param mixed $limit
 * @return int|null
 */
function parse_upcoming_payments_limit($limit)
{
    if (is_string($limit) && ctype_digit($limit)) {
        $limit = (int) $limit;
    }

    return is_int($limit) && in_array($limit, UPCOMING_PAYMENTS_LIMITS, true) ? $limit : null;
}

/**
 * Return a supported dashboard limit, falling back to the current default.
 *
 * @param mixed $limit
 * @return int
 */
function normalize_upcoming_payments_limit($limit)
{
    return parse_upcoming_payments_limit($limit) ?? 3;
}

if (!function_exists('getPaymentCycleDays')) {
    function getPaymentCycleDays($cycle, $frequency)
    {
        switch ($cycle) {
            case 1: return 1 * $frequency;
            case 2: return 7 * $frequency;
            case 3: return 30 * $frequency;
            case 4: return 365 * $frequency;
            default: return 0;
        }
    }
}

if (!function_exists('isPaidThisCycle')) {
    function isPaidThisCycle($paidAt, $cycle, $frequency, $nextPayment)
    {
        if (!$paidAt || $cycle === 5) {
            return false;
        }

        $paidDate = new DateTime($paidAt);
        $nextPaymentDate = new DateTime($nextPayment);
        $currentDate = new DateTime((new DateTime('now'))->format('Y-m-d'));
        $paymentCycleDays = getPaymentCycleDays($cycle, $frequency);

        if ($paymentCycleDays <= 0) {
            return false;
        }

        if ($currentDate > $nextPaymentDate) {
            // Overdue: the cycle window runs forward from next_payment
            $cycleEnd = clone $nextPaymentDate;
            $cycleEnd->modify('+' . $paymentCycleDays . ' days');
            return $paidDate >= $nextPaymentDate && $paidDate <= $cycleEnd;
        }

        // Normal: walk back from next_payment to find the cycle containing today
        $daysUntilNextPayment = $currentDate->diff($nextPaymentDate)->days;
        $cyclesBack = max(1, (int) ceil($daysUntilNextPayment / $paymentCycleDays));

        $cycleStart = clone $nextPaymentDate;
        $cycleStart->modify('-' . ($cyclesBack * $paymentCycleDays) . ' days');

        return $paidDate >= $cycleStart && $paidDate <= $nextPaymentDate;
    }
}

/**
 * Fetch the upcoming subscriptions shown on the dashboard.
 *
 * @param SQLite3 $db
 * @param int      $userId
 * @param mixed    $limit
 * @return array
 */
function get_upcoming_payments($db, $userId, $limit)
{
    $limit = normalize_upcoming_payments_limit($limit);

    $stmt = $db->prepare("SELECT id, logo, logo_text_color, logo_variant, name, price, currency_id, next_payment, inactive, cycle, frequency, paid_at
        FROM subscriptions
        WHERE user_id = :userId
          AND next_payment >= date('now')
          AND inactive = 0
          AND cycle != 5
        ORDER BY next_payment ASC");
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    $subscriptions = [];
    while ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
        // Skip subscriptions already marked as paid for the current cycle.
        if (isPaidThisCycle($row['paid_at'] ?? null, $row['cycle'], $row['frequency'], $row['next_payment'])) {
            continue;
        }
        $subscriptions[] = $row;
        if (count($subscriptions) >= $limit) {
            break;
        }
    }

    return $subscriptions;
}
