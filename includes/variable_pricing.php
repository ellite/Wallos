<?php

/*
 * Variable monthly pricing for subscriptions whose bill changes every month
 * (electricity, mobile, gas, water).
 *
 * has_variable_price = 0 leaves every existing calculation untouched.
 * has_variable_price = 1 stores one actual amount per calendar month. Totals
 * use that month's actual, then the most recent preceding actual, then subscriptions.price.
 * The amount is already a month's cost, so it is not divided or multiplied by
 * the billing cycle. Fixed-price subscriptions keep using subscriptions.price.
 */

const WALLOS_PRICE_HISTORY_LIMIT = 240;

function variable_pricing_supports_cycle($cycle, $frequency): bool
{
    return (int) $cycle === 3 && (int) $frequency === 1;
}

function subscription_has_variable_price(array $subscription): bool
{
    return !empty($subscription['has_variable_price']);
}

function subscription_price_month(array $subscription, ?string $fallback = null): string
{
    $candidate = '';
    if (!empty($subscription['next_payment'])) {
        $candidate = substr((string) $subscription['next_payment'], 0, 7);
    }
    if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $candidate)) {
        return $candidate;
    }

    return $fallback ?? date('Y-m');
}

/**
 * subscription id => [ 'YYYY-MM' => price ].
 * Pass null to load every account (admin "all users" API only).
 *
 * @return array<int, array<string, float>>
 */
function load_price_history_index(?SQLite3 $db, ?int $userId): array
{
    $index = [];
    if ($db === null) {
        return $index;
    }

    if ($userId === null) {
        $stmt = $db->prepare('SELECT subscription_id, period, price FROM subscription_price_history');
    } else {
        $stmt = $db->prepare(
            'SELECT h.subscription_id, h.period, h.price
             FROM subscription_price_history h
             INNER JOIN subscriptions s ON s.id = h.subscription_id
             WHERE s.user_id = :userId'
        );
        $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    }

    if ($stmt === false) {
        return $index;
    }

    $result = $stmt->execute();
    if (!$result) {
        return $index;
    }

    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $index[(int) $row['subscription_id']][$row['period']] = (float) $row['price'];
    }

    return $index;
}

/**
 * @return array<int, array{period: string, price: float, note: string}>
 */
function price_history_for_subscription(?SQLite3 $db, int $subscriptionId): array
{
    $history = [];
    if ($db === null) {
        return $history;
    }
    $stmt = $db->prepare(
        'SELECT period, price, note FROM subscription_price_history
         WHERE subscription_id = :sid ORDER BY period ASC'
    );
    if ($stmt === false) {
        return $history;
    }
    $stmt->bindValue(':sid', $subscriptionId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    if (!$result) {
        return $history;
    }

    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $history[] = [
            'period' => $row['period'],
            'price' => (float) $row['price'],
            'note' => $row['note'] ?? '',
        ];
    }

    return $history;
}

/**
 * Unconverted amount for $yearMonth.
 * Variable subscriptions: that month, else the latest preceding month, else the base price.
 */
function effective_subscription_price(array $subscription, string $yearMonth, array $historyIndex): float
{
    $base = (float) ($subscription['price'] ?? 0);
    if (!subscription_has_variable_price($subscription)) {
        return $base;
    }

    $entries = $historyIndex[(int) ($subscription['id'] ?? 0)] ?? [];
    if (isset($entries[$yearMonth])) {
        return (float) $entries[$yearMonth];
    }
    if ($entries) {
        $periods = array_keys($entries);
        rsort($periods, SORT_STRING);
        foreach ($periods as $period) {
            if ($period < $yearMonth) {
                return (float) $entries[$period];
            }
        }
    }

    return $base;
}

/**
 * Decode the form payload. null means "leave stored history alone".
 * An array (possibly empty) is the full replacement set.
 *
 * @return array<int, array{period: string, price: float, note: string}>|null
 */
function parse_price_history_json($json): ?array
{
    if ($json === null || $json === '') {
        return null;
    }
    if (!is_string($json) || strlen($json) > 1048576 || substr(ltrim($json), 0, 1) !== '[') {
        throw new InvalidArgumentException('price_history_invalid');
    }
    try {
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        throw new InvalidArgumentException('price_history_invalid');
    }
    if (!is_array($decoded) || !array_is_list($decoded)) {
        throw new InvalidArgumentException('price_history_invalid');
    }

    $byPeriod = [];
    foreach ($decoded as $entry) {
        if (!is_array($entry)) {
            throw new InvalidArgumentException('price_history_invalid');
        }
        $period = isset($entry['period']) && is_string($entry['period']) ? $entry['period'] : '';
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            throw new InvalidArgumentException('price_history_invalid');
        }
        if (!isset($entry['price']) || !is_numeric($entry['price'])) {
            throw new InvalidArgumentException('price_history_invalid');
        }
        $price = (float) $entry['price'];
        if (!is_finite($price)) {
            throw new InvalidArgumentException('price_history_invalid');
        }

        if (isset($entry['note']) && !is_string($entry['note'])) {
            throw new InvalidArgumentException('price_history_invalid');
        }
        $note = isset($entry['note']) ? trim(strip_tags($entry['note'])) : '';
        $note = preg_replace('/[\x00-\x1F\x7F]/u', '', $note) ?? '';
        $note = mb_substr($note, 0, 200);

        $byPeriod[$period] = [
            'period' => $period,
            'price' => $price,
            'note' => $note,
        ];
        if (count($byPeriod) > WALLOS_PRICE_HISTORY_LIMIT) {
            throw new InvalidArgumentException('price_history_limit');
        }
    }

    ksort($byPeriod);

    return array_values($byPeriod);
}

// Checked SQLite failures become endpoint JSON errors; do not emit a warning
// into that response before the caller can report the failure.
function replace_subscription_price_history(?SQLite3 $db, int $subscriptionId, array $entries): bool
{
    if ($db === null) {
        return false;
    }

    if (!@$db->exec('SAVEPOINT subscription_price_history_save')) {
        return false;
    }

    $delete = @$db->prepare('DELETE FROM subscription_price_history WHERE subscription_id = :sid');
    if ($delete === false) {
        @$db->exec('ROLLBACK TO subscription_price_history_save');
        @$db->exec('RELEASE subscription_price_history_save');
        return false;
    }
    $delete->bindValue(':sid', $subscriptionId, SQLITE3_INTEGER);
    if (!@$delete->execute()) {
        @$db->exec('ROLLBACK TO subscription_price_history_save');
        @$db->exec('RELEASE subscription_price_history_save');
        return false;
    }

    $insert = @$db->prepare(
        'INSERT INTO subscription_price_history (subscription_id, period, price, note)
         VALUES (:sid, :period, :price, :note)'
    );
    if ($insert === false) {
        @$db->exec('ROLLBACK TO subscription_price_history_save');
        @$db->exec('RELEASE subscription_price_history_save');
        return false;
    }

    foreach ($entries as $entry) {
        $insert->bindValue(':sid', $subscriptionId, SQLITE3_INTEGER);
        $insert->bindValue(':period', $entry['period'], SQLITE3_TEXT);
        $insert->bindValue(':price', $entry['price'], SQLITE3_FLOAT);
        $insert->bindValue(':note', $entry['note'], SQLITE3_TEXT);
        if (!@$insert->execute()) {
            @$db->exec('ROLLBACK TO subscription_price_history_save');
            @$db->exec('RELEASE subscription_price_history_save');
            return false;
        }
        $insert->reset();
        $insert->clear();
    }

    return @$db->exec('RELEASE subscription_price_history_save');
}

/**
 * Save the prepared subscription write and its optional history as one unit.
 * null entries retain history, including when variable pricing is turned off.
 * Returns the saved id, or null on a database failure. Validation errors are
 * thrown for the endpoint to translate, after rolling back every write.
 */
function save_subscription_with_price_history(
    SQLite3 $db,
    SQLite3Stmt $statement,
    int $userId,
    ?int $subscriptionId,
    ?array $entries
): ?int {
    $transactionStarted = false;
    try {
        if (!@$db->exec('BEGIN IMMEDIATE')) {
            return null;
        }
        $transactionStarted = true;
        $previous = null;
        if ($subscriptionId !== null) {
            $owner = @$db->prepare('SELECT currency_id FROM subscriptions WHERE id = :id AND user_id = :userId');
            if (!$owner) {
                throw new RuntimeException('Subscription lookup failed');
            }
            $owner->bindValue(':id', $subscriptionId, SQLITE3_INTEGER);
            $owner->bindValue(':userId', $userId, SQLITE3_INTEGER);
            $result = @$owner->execute();
            $previous = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;
            if (!$previous) {
                throw new RuntimeException('Subscription not found');
            }
        }

        if (!@$statement->execute()) {
            throw new RuntimeException('Subscription write failed');
        }
        $savedId = $subscriptionId ?? (int) $db->lastInsertRowID();
        $lookup = @$db->prepare('SELECT currency_id, cycle, frequency, has_variable_price
                               FROM subscriptions WHERE id = :id AND user_id = :userId');
        if (!$lookup) {
            throw new RuntimeException('Subscription lookup failed');
        }
        $lookup->bindValue(':id', $savedId, SQLITE3_INTEGER);
        $lookup->bindValue(':userId', $userId, SQLITE3_INTEGER);
        $result = @$lookup->execute();
        $saved = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;
        if (!$saved) {
            throw new RuntimeException('Subscription not found');
        }
        if (subscription_has_variable_price($saved)
            && !variable_pricing_supports_cycle($saved['cycle'], $saved['frequency'])) {
            throw new InvalidArgumentException('variable_price_monthly_only');
        }
        if ($previous && (int) $previous['currency_id'] !== (int) $saved['currency_id'] && $entries !== []) {
            $history = @$db->prepare('SELECT 1 FROM subscription_price_history WHERE subscription_id = :id LIMIT 1');
            if (!$history) {
                throw new RuntimeException('History lookup failed');
            }
            $history->bindValue(':id', $savedId, SQLITE3_INTEGER);
            $result = @$history->execute();
            if (!$result) {
                throw new RuntimeException('History lookup failed');
            }
            if ($result->fetchArray(SQLITE3_ASSOC)) {
                throw new InvalidArgumentException('price_history_currency');
            }
        }
        if ($entries !== null && !replace_subscription_price_history($db, $savedId, $entries)) {
            throw new RuntimeException('History write failed');
        }
        if (!@$db->exec('COMMIT')) {
            throw new RuntimeException('Subscription commit failed');
        }

        return $savedId;
    } catch (Throwable $error) {
        if ($transactionStarted) {
            @$db->exec('ROLLBACK');
        }
        if ($error instanceof InvalidArgumentException) {
            throw $error;
        }
        return null;
    }
}
