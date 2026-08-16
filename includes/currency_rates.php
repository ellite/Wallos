<?php
/*
  Exchange-rate lookups for price conversion.

  Rates change once a day at most, but conversion happens once per subscription,
  once per statistics row and once per calendar entry. Looking them up per row
  turns rendering a list into one query per item, so they are loaded once and
  answered from memory for the rest of the request.
*/

/**
 * Returns the exchange rates as [currency_id => rate].
 *
 * Passing a user id restricts the map to that user's currencies, matching the
 * lookups that filtered by user; omitting it covers every currency, matching
 * the lookups that resolved a currency by id alone.
 *
 * The map is cached per database connection, so a second connection — a test,
 * or a job that reopens the database — never sees another connection's rates.
 *
 * @param SQLite3  $db
 * @param int|null $userId
 * @return array<int, float>
 */
function wallos_currency_rates($db, $userId = null)
{
    // Keyed by the connection object itself: an id would be reused once a
    // connection is closed, and the next one would inherit stale rates.
    static $cache = null;

    if ($cache === null) {
        $cache = new WeakMap();
    }

    $key = $userId === null ? 'all' : (int) $userId;
    $connectionRates = $cache[$db] ?? [];

    if (isset($connectionRates[$key])) {
        return $connectionRates[$key];
    }

    $rates = [];

    if ($userId === null) {
        $stmt = $db->prepare('SELECT id, rate FROM currencies');
    } else {
        $stmt = $db->prepare('SELECT id, rate FROM currencies WHERE user_id = :userId');
        $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    }

    $result = $stmt ? $stmt->execute() : false;

    while ($result && $row = $result->fetchArray(SQLITE3_ASSOC)) {
        $rates[(int) $row['id']] = (float) $row['rate'];
    }

    $connectionRates[$key] = $rates;
    $cache[$db] = $connectionRates;

    return $rates;
}

/**
 * Converts a price from the given currency into the user's main currency.
 *
 * An unknown currency or a missing rate leaves the price untouched, which is
 * what the per-row lookups did when they found no row.
 *
 * @param float|int|string $price
 * @param int              $currencyId
 * @param SQLite3          $db
 * @param int|null         $userId
 * @return float
 */
function wallos_convert_price($price, $currencyId, $db, $userId = null)
{
    $rates = wallos_currency_rates($db, $userId);
    $rate = $rates[(int) $currencyId] ?? null;

    if (empty($rate)) {
        return (float) $price;
    }

    return (float) $price / $rate;
}
