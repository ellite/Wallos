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

    $stmt = $db->prepare("SELECT id, logo, logo_text_color, logo_variant, name, price, currency_id, next_payment, inactive
        FROM subscriptions
        WHERE user_id = :userId
          AND next_payment >= date('now')
          AND inactive = 0
          AND cycle != 5
        ORDER BY next_payment ASC
        LIMIT :limit");
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
    $result = $stmt->execute();

    $subscriptions = [];
    while ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
        $subscriptions[] = $row;
    }

    return $subscriptions;
}
