<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/logo_cleanup.php';
require_once '../../includes/subscription_categories.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

function bulkEditFail($i18n)
{
    die(json_encode([
        "success" => false,
        "message" => translate("error", $i18n)
    ]));
}

$ids = array_values(array_unique(array_filter(array_map('intval', (array) ($data['ids'] ?? [])))));
$action = $data['action'] ?? '';
$value = isset($data['value']) ? (int) $data['value'] : null;

if (empty($ids)) {
    bulkEditFail($i18n);
}

// The ids are integers at this point, so they can go into the IN list as they are.
$idList = implode(',', $ids);

$stmt = $db->prepare("SELECT COUNT(*) AS count FROM subscriptions WHERE id IN ($idList) AND user_id = :userId");
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
if ((int) $row['count'] !== count($ids)) {
    bulkEditFail($i18n);
}

$updates = [
    'enable_notifications' => "notify = 1",
    'disable_notifications' => "notify = 0",
    'set_notify_days' => "notify = 1, notify_days_before = :value",
    'set_category' => "category_id = :value",
    'set_payment_method' => "payment_method_id = :value",
];

if ($action === 'set_notify_days' && ($value === null || $value < -1 || $value > 180)) {
    bulkEditFail($i18n);
}

if ($action === 'set_category' || $action === 'set_payment_method') {
    $query = $action === 'set_category'
        ? "SELECT id FROM categories WHERE id = :id AND user_id = :userId"
        : "SELECT id FROM payment_methods WHERE id = :id AND user_id = :userId AND enabled = 1";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':id', $value, SQLITE3_INTEGER);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    if ($stmt->execute()->fetchArray(SQLITE3_ASSOC) === false) {
        bulkEditFail($i18n);
    }
}

if (isset($updates[$action])) {
    $stmt = $db->prepare("UPDATE subscriptions SET " . $updates[$action] . " WHERE id IN ($idList) AND user_id = :userId");
    if (strpos($updates[$action], ':value') !== false) {
        $stmt->bindValue(':value', $value, SQLITE3_INTEGER);
    }
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    if (!$stmt->execute()) {
        bulkEditFail($i18n);
    }

    // The subscription_categories rows are the full list the filter and the
    // statistics read, category_id is only the first of them: replace them.
    if ($action === 'set_category') {
        foreach ($ids as $subscriptionId) {
            saveSubscriptionCategories($db, $subscriptionId, [$value]);
        }
    }
} elseif ($action === 'delete') {
    // Same clean up as endpoints/subscription/delete.php, for each subscription.
    $logos = [];
    $stmt = $db->prepare("SELECT logo, logo_variant FROM subscriptions WHERE id IN ($idList) AND user_id = :userId");
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $logos[] = $row['logo'];
        $logos[] = $row['logo_variant'];
    }

    // Subscription ids can be reused by SQLite, so the payer and category rows
    // of a deleted subscription must not be left behind for the next one.
    foreach (['subscription_payers', 'subscription_categories'] as $table) {
        $stmt = $db->prepare("DELETE FROM $table WHERE subscription_id IN (SELECT id FROM subscriptions WHERE id IN ($idList) AND user_id = :userId)");
        $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
        $stmt->execute();
    }

    $stmt = $db->prepare("DELETE FROM subscriptions WHERE id IN ($idList) AND user_id = :userId");
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    if (!$stmt->execute()) {
        bulkEditFail($i18n);
    }

    $stmt = $db->prepare("UPDATE subscriptions SET replacement_subscription_id = NULL WHERE replacement_subscription_id IN ($idList) AND user_id = :userId");
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $stmt->execute();

    foreach (array_unique($logos) as $logo) {
        deleteLogoFileIfUnused($db, $logo, '../../images/uploads/logos/');
    }
} else {
    bulkEditFail($i18n);
}

echo json_encode([
    "success" => true,
    "count" => count($ids)
]);
$db->close();
