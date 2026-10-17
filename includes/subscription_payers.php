<?php

// Helpers for subscriptions paid by several household members.
//
// subscription_payers is the full list. subscriptions.payer_user_id keeps the
// first of them (the primary payer), see migrations/000060.php.

// Accepts an array or a comma separated string, returns unique positive ints
// in the order given.
function parsePayerIds($input)
{
    if (is_string($input)) {
        $input = explode(',', $input);
    }
    if (!is_array($input)) {
        return [];
    }
    $ids = [];
    foreach ($input as $value) {
        if ($value === null || $value === '' || !is_numeric($value)) {
            continue;
        }
        $id = intval($value);
        if ($id > 0 && !in_array($id, $ids, true)) {
            $ids[] = $id;
        }
    }
    return $ids;
}

// True when every id is a household member of this user.
function payersBelongToUser($db, $userId, array $payerIds)
{
    $stmt = $db->prepare("SELECT id FROM household WHERE id = :id AND user_id = :userId");
    foreach ($payerIds as $payerId) {
        $stmt->bindValue(':id', $payerId, SQLITE3_INTEGER);
        $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $found = $result && $result->fetchArray();
        $stmt->reset();
        if (!$found) {
            return false;
        }
    }
    return true;
}

// Replaces the payers of a subscription.
function saveSubscriptionPayers($db, $subscriptionId, array $payerIds)
{
    $delete = $db->prepare("DELETE FROM subscription_payers WHERE subscription_id = :subscriptionId");
    $delete->bindValue(':subscriptionId', $subscriptionId, SQLITE3_INTEGER);
    $delete->execute();

    $insert = $db->prepare("INSERT OR IGNORE INTO subscription_payers (subscription_id, payer_id) VALUES (:subscriptionId, :payerId)");
    foreach ($payerIds as $payerId) {
        $insert->bindValue(':subscriptionId', $subscriptionId, SQLITE3_INTEGER);
        $insert->bindValue(':payerId', $payerId, SQLITE3_INTEGER);
        $insert->execute();
        $insert->reset();
    }
}

// Payer ids per subscription id, in the order they were stored. Subscriptions
// without rows fall back to their primary payer so callers never see an
// empty list for a subscription that has one.
function getPayersBySubscription($db, array $subscriptions)
{
    $payers = [];
    $ids = array_values(array_filter(array_map(fn($s) => isset($s['id']) ? intval($s['id']) : 0, $subscriptions)));
    if (count($ids) > 0) {
        $result = $db->query("SELECT subscription_id, payer_id FROM subscription_payers WHERE subscription_id IN (" . implode(',', $ids) . ") ORDER BY rowid");
        while ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
            $payers[$row['subscription_id']][] = intval($row['payer_id']);
        }
    }
    foreach ($subscriptions as $subscription) {
        $id = $subscription['id'] ?? null;
        if ($id !== null && !isset($payers[$id]) && !empty($subscription['payer_user_id'])) {
            $payers[$id] = [intval($subscription['payer_user_id'])];
        }
    }
    return $payers;
}

// Names of the given payers joined for display, skipping unknown ids.
function payerNamesLabel(array $payerIds, array $nameById, $separator = ', ')
{
    $names = [];
    foreach ($payerIds as $payerId) {
        if (isset($nameById[$payerId]) && $nameById[$payerId] !== '') {
            $names[] = $nameById[$payerId];
        }
    }
    return implode($separator, $names);
}

// SQL condition matching subscriptions paid by any of the given members.
// $column is the subscriptions id column, placeholders are named
// :payerFilter0, :payerFilter1, ... and returned in $params.
function payerFilterCondition(array $memberIds, &$params, $idColumn = 'id')
{
    $placeholders = [];
    foreach (array_values($memberIds) as $i => $memberId) {
        $placeholders[] = ":payerFilter{$i}";
        $params[":payerFilter{$i}"] = intval($memberId);
    }
    return "EXISTS (SELECT 1 FROM subscription_payers sp WHERE sp.subscription_id = {$idColumn} AND sp.payer_id IN (" . implode(',', $placeholders) . "))";
}

?>
