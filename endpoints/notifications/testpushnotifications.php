<?php

/*
  Sends a test push to every device this account has registered, rather than
  to a single not-yet-saved configuration the way every other channel's test
  button does - there is no config to test here that saving a subscription
  did not already commit; what "test" means for push is "does at least one
  of my devices actually receive this".
*/

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/ssrf_helper.php';
require_once '../../includes/webpush_helper.php';
require_once '../../includes/instance_config.php';

$query = "SELECT id, endpoint, p256dh, auth FROM push_subscriptions WHERE user_id = :userId";
$stmt = $db->prepare($query);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();

$subscriptions = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $subscriptions[] = $row;
}

if (empty($subscriptions)) {
    die(json_encode([
        "success" => false,
        "message" => translate('no_devices_registered', $i18n)
    ]));
}

$vapidKeys = webpush_get_vapid_keys($db);
if ($vapidKeys === false) {
    die(json_encode([
        "success" => false,
        "message" => translate('notification_failed', $i18n)
    ]));
}

$adminSettings = wallos_get_admin_settings($db);
$subject = !empty($adminSettings['from_email']) ? 'mailto:' . $adminSettings['from_email'] : 'mailto:wallos@wallosapp.com';

$payload = json_encode([
    'title' => translate('wallos_notification', $i18n),
    'body' => translate('test_notification', $i18n),
]);

$sent = 0;
$failed = 0;

foreach ($subscriptions as $subscription) {
    $ssrf = is_url_safe_for_ssrf($subscription['endpoint'], $db, $userId);
    if (!$ssrf) {
        $failed++;
        continue;
    }

    $result = webpush_send($subscription, $payload, $vapidKeys, $subject, 2419200, $ssrf);

    if ($result['prune']) {
        $pruneStmt = $db->prepare('DELETE FROM push_subscriptions WHERE id = :id');
        $pruneStmt->bindValue(':id', $subscription['id'], SQLITE3_INTEGER);
        $pruneStmt->execute();
    }

    if ($result['success']) {
        $sent++;
    } else {
        $failed++;
    }
}

if ($sent > 0) {
    echo json_encode([
        "success" => true,
        "message" => translate('notification_sent_successfuly', $i18n)
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => translate('notification_failed', $i18n)
    ]);
}
