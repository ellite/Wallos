<?php

const UPCOMING_PAYMENTS_LIMITS = [3, 5, 10, 20];
// 0 keeps the count-based list; any other value shows every payment due within that many days.
const UPCOMING_PAYMENTS_DAYS = [0, 7, 14, 30, 60, 90];

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

/**
 * Parse a dashboard date range and return null for unsupported values.
 *
 * @param mixed $days
 * @return int|null
 */
function parse_upcoming_payments_days($days)
{
    if (is_string($days) && ctype_digit($days)) {
        $days = (int) $days;
    }

    return is_int($days) && in_array($days, UPCOMING_PAYMENTS_DAYS, true) ? $days : null;
}

/**
 * Return a supported dashboard date range, falling back to the count-based list.
 *
 * @param mixed $days
 * @return int
 */
function normalize_upcoming_payments_days($days)
{
    return parse_upcoming_payments_days($days) ?? 0;
}

/**
 * Fetch the upcoming subscriptions shown on the dashboard.
 *
 * With a date range set, every subscription due within it is returned and the
 * limit is ignored; otherwise the first $limit upcoming subscriptions are.
 *
 * @param SQLite3 $db
 * @param int      $userId
 * @param mixed    $limit
 * @param mixed    $days
 * @return array
 */
function get_upcoming_payments($db, $userId, $limit, $days = 0)
{
    $limit = normalize_upcoming_payments_limit($limit);
    $days = normalize_upcoming_payments_days($days);

    $stmt = $db->prepare("SELECT id, logo, logo_text_color, logo_variant, name, price, currency_id, next_payment, inactive
        FROM subscriptions
        WHERE user_id = :userId
          AND next_payment >= date('now')
          AND (:days = 0 OR next_payment <= date('now', '+' || :days || ' days'))
          AND inactive = 0
          AND cycle != 5
        ORDER BY next_payment ASC
        LIMIT :limit");
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $stmt->bindValue(':days', $days, SQLITE3_INTEGER);
    $stmt->bindValue(':limit', $days === 0 ? $limit : -1, SQLITE3_INTEGER);
    $result = $stmt->execute();

    $subscriptions = [];
    while ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
        $subscriptions[] = $row;
    }

    return $subscriptions;
}
