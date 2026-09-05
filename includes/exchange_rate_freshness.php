<?php

/**
 * Whether this account's exchange rates were already refreshed today.
 *
 * The scheduled job runs daily and again on every container start, and the
 * provider is asked once per account with a key. Without this, a deploy costs
 * one request per account — and on a free plan that is how a month's allowance
 * disappears in a fortnight. What the user sees then is not an error: the rates
 * simply stop moving, and nothing on any screen says why.
 *
 * The date read here is the one updateexchange.php writes after a refresh that
 * succeeded, so a run that failed is retried rather than skipped.
 *
 * Deliberately a comparison of two Y-m-d strings rather than of DateTime
 * objects. That is how the value is stored, it is the comparison the manual
 * endpoint was written to make, and it needs no object built from a value that
 * might be missing.
 *
 * @param SQLite3 $db
 * @param int     $userId
 * @return bool false whenever the answer is not certain, so an uncertain case
 *              refreshes rather than silently skipping
 */
function wallos_rates_refreshed_today($db, $userId)
{
    $statement = $db->prepare('SELECT date FROM last_exchange_update WHERE user_id = :userId');

    if ($statement === false) {
        return false;
    }

    $statement->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $statement->execute();

    if ($result === false) {
        return false;
    }

    $row = $result->fetchArray(SQLITE3_ASSOC);

    return $row !== false && !empty($row['date'])
        && $row['date'] >= (new DateTime())->format('Y-m-d');
}
