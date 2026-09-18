<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

$enabled = !empty($data["enabled"]) ? 1 : 0;

$query = "SELECT COUNT(*) FROM push_notifications WHERE user_id = :userId";
$stmt = $db->prepare($query);
$stmt->bindValue(":userId", $userId, SQLITE3_INTEGER);
$result = $stmt->execute();

if ($result === false) {
    echo json_encode([
        "success" => false,
        "message" => translate('error_saving_notifications', $i18n)
    ]);
} else {
    $row = $result->fetchArray();
    $count = $row[0];

    if ($count == 0) {
        $query = "INSERT INTO push_notifications (enabled, user_id) VALUES (:enabled, :userId)";
    } else {
        $query = "UPDATE push_notifications SET enabled = :enabled WHERE user_id = :userId";
    }

    $stmt = $db->prepare($query);
    $stmt->bindValue(':enabled', $enabled, SQLITE3_INTEGER);
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
}
