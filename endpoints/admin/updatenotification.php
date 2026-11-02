<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint_admin.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

$updateNotification = $data['notificationEnabled'];

// Save settings
$stmt = $db->prepare('UPDATE admin SET update_notification = :update_notification');
$stmt->bindValue(':update_notification', $updateNotification, SQLITE3_INTEGER);
$result = $stmt->execute();

if ($result) {
    die(json_encode([
        "success" => true,
        "message" => translate('success', $i18n)
    ]));
} else {
    die(json_encode([
        "success" => false,
        "message" => translate('error', $i18n)
    ]));
}