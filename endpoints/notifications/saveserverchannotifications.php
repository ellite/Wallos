<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

if (!isset($data["sendkey"]) || $data["sendkey"] == "") {
    $response = [
        "success" => false,
        "message" => translate('fill_mandatory_fields', $i18n)
    ];
    echo json_encode($response);
} else {
    $enabled = $data["enabled"];
    $sendkey = $data["sendkey"];

    $query = "SELECT COUNT(*) FROM serverchan_notifications WHERE user_id = :userId";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":userId", $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    if ($result === false) {
        $response = [
            "success" => false,
            "message" => translate('error_saving_notifications', $i18n)
        ];
        echo json_encode($response);
    } else {
        $row = $result->fetchArray();
        $count = $row[0];
        if ($count == 0) {
            $query = "INSERT INTO serverchan_notifications (enabled, sendkey, user_id)
                      VALUES (:enabled, :sendkey, :userId)";
        } else {
            $query = "UPDATE serverchan_notifications
                      SET enabled = :enabled, sendkey = :sendkey WHERE user_id = :userId";
        }

        $stmt = $db->prepare($query);
        $stmt->bindValue(':enabled', $enabled, SQLITE3_INTEGER);
        $stmt->bindValue(':sendkey', $sendkey, SQLITE3_TEXT);
        $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);

        if ($stmt->execute()) {
            $response = [
                "success" => true,
                "message" => translate('notifications_settings_saved', $i18n)
            ];
            echo json_encode($response);
        } else {
            $response = [
                "success" => false,
                "message" => translate('error_saving_notifications', $i18n)
            ];
            echo json_encode($response);
        }
    }
}
