<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

if (!isset($data["webhook_url"]) || $data["webhook_url"] == "") {
    $response = [
        "success" => false,
        "message" => translate('fill_mandatory_fields', $i18n)
    ];
    echo json_encode($response);
} else {
    $enabled = $data["enabled"];
    $webhook_url = $data["webhook_url"];
    $bot_username = $data["bot_username"];
    $bot_iconemoji = $data["bot_icon_emoji"];

    $query = "SELECT COUNT(*) FROM mattermost_notifications WHERE user_id = :userId";
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
            $query = "INSERT INTO mattermost_notifications (enabled, webhook_url, user_id, bot_username, bot_icon_emoji)
                          VALUES (:enabled, :webhook_url, :userId, :bot_username, :bot_icon_emoji)";
        } else {
            $query = "UPDATE mattermost_notifications
                          SET enabled = :enabled, webhook_url = :webhook_url WHERE user_id = :userId";
        }

        $stmt = $db->prepare($query);
        $stmt->bindValue(':enabled', $enabled, SQLITE3_INTEGER);
        $stmt->bindValue(':webhook_url', $webhook_url, SQLITE3_TEXT);
        $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
        $stmt->bindValue(':bot_username', $bot_username, SQLITE3_TEXT);
        $stmt->bindValue(':bot_icon_emoji', $bot_iconemoji, SQLITE3_TEXT);

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