<?php

/*
  Removes one device's push subscription - either by its row id (the
  settings page's per-device "remove" button, which only ever knows the
  list it rendered) or by its endpoint (the "disable on this device" button,
  which only ever knows what the browser's own subscription object holds,
  never a database id). Exactly one of the two is expected; user_id scopes
  either lookup, so one account can never remove another's device.
*/

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

$id = isset($data['id']) ? (int) $data['id'] : 0;
$endpoint = isset($data['endpoint']) ? trim((string) $data['endpoint']) : '';

if ($id > 0) {
    $query = "DELETE FROM push_subscriptions WHERE id = :id AND user_id = :userId";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
} elseif ($endpoint !== '') {
    $query = "DELETE FROM push_subscriptions WHERE endpoint = :endpoint AND user_id = :userId";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':endpoint', $endpoint, SQLITE3_TEXT);
} else {
    die(json_encode([
        "success" => false,
        "message" => translate('fill_mandatory_fields', $i18n)
    ]));
}

$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => translate('notifications_settings_saved', $i18n)
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => translate('error_saving_notifications', $i18n)
    ]);
}
