<?php

// Helpers for subscriptions in several categories, the counterpart of
// subscription_payers.php. subscription_categories is the full list,
// subscriptions.category_id keeps the first of them (see migrations/000061.php).

require_once __DIR__ . '/subscription_payers.php';

// True when every id is a category of this user.
function categoriesBelongToUser($db, $userId, array $categoryIds)
{
    $stmt = $db->prepare("SELECT id FROM categories WHERE id = :id AND user_id = :userId");
    foreach ($categoryIds as $categoryId) {
        $stmt->bindValue(':id', $categoryId, SQLITE3_INTEGER);
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

// Replaces the categories of a subscription.
function saveSubscriptionCategories($db, $subscriptionId, array $categoryIds)
{
    $delete = $db->prepare("DELETE FROM subscription_categories WHERE subscription_id = :subscriptionId");
    $delete->bindValue(':subscriptionId', $subscriptionId, SQLITE3_INTEGER);
    $delete->execute();

    $insert = $db->prepare("INSERT OR IGNORE INTO subscription_categories (subscription_id, category_id) VALUES (:subscriptionId, :categoryId)");
    foreach ($categoryIds as $categoryId) {
        $insert->bindValue(':subscriptionId', $subscriptionId, SQLITE3_INTEGER);
        $insert->bindValue(':categoryId', $categoryId, SQLITE3_INTEGER);
        $insert->execute();
        $insert->reset();
    }
}

// Category ids per subscription id, in the order they were stored.
// Subscriptions without rows fall back to their primary category.
function getCategoriesBySubscription($db, array $subscriptions)
{
    $categories = [];
    $ids = array_values(array_filter(array_map(fn($s) => isset($s['id']) ? intval($s['id']) : 0, $subscriptions)));
    if (count($ids) > 0) {
        $result = $db->query("SELECT subscription_id, category_id FROM subscription_categories WHERE subscription_id IN (" . implode(',', $ids) . ") ORDER BY rowid");
        while ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
            $categories[$row['subscription_id']][] = intval($row['category_id']);
        }
    }
    foreach ($subscriptions as $subscription) {
        $id = $subscription['id'] ?? null;
        if ($id !== null && !isset($categories[$id]) && !empty($subscription['category_id'])) {
            $categories[$id] = [intval($subscription['category_id'])];
        }
    }
    return $categories;
}

// SQL condition matching subscriptions in any of the given categories.
// Placeholders are named :categoryFilter0, :categoryFilter1, ... and returned in $params.
function categoryFilterCondition(array $categoryIds, &$params, $idColumn = 'id')
{
    $placeholders = [];
    foreach (array_values($categoryIds) as $i => $categoryId) {
        $placeholders[] = ":categoryFilter{$i}";
        $params[":categoryFilter{$i}"] = intval($categoryId);
    }
    return "EXISTS (SELECT 1 FROM subscription_categories sc WHERE sc.subscription_id = {$idColumn} AND sc.category_id IN (" . implode(',', $placeholders) . "))";
}

?>
