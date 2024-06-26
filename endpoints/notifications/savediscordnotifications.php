<?php

require_once '../../includes/connect_endpoint.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die(json_encode([
        "success" => false,
        "message" => translate('session_expired', $i18n)
    ]));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $postData = file_get_contents("php://input");
    $data = json_decode($postData, true);

    if (
        !isset($data["url"]) || $data["url"] == ""
    ) {
        $response = [
            "success" => false,
            "message" => translate('fill_mandatory_fields', $i18n)
        ];
        echo json_encode($response);
    } else {
        $enabled = $data["enabled"];
        $webhook_url = $data["url"];
        $bot_username = $data["bot_username"];
        $bot_avatar_url = $data["bot_avatar"];

        $query = "SELECT COUNT(*) FROM discord_notifications WHERE user_id = :userId";
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
                $query = "INSERT INTO discord_notifications (enabled, webhook_url, bot_username, bot_avatar_url, user_id)
                              VALUES (:enabled, :webhook_url, :bot_username, :bot_avatar_url, :userId)";
            } else {
                $query = "UPDATE discord_notifications
                              SET enabled = :enabled, webhook_url = :webhook_url, bot_username = :bot_username, bot_avatar_url = :bot_avatar_url 
                              WHERE user_id = :userId";
            }

            $stmt = $db->prepare($query);
            $stmt->bindValue(':enabled', $enabled, SQLITE3_INTEGER);
            $stmt->bindValue(':webhook_url', $webhook_url, SQLITE3_TEXT);
            $stmt->bindValue(':bot_username', $bot_username, SQLITE3_TEXT);
            $stmt->bindValue(':bot_avatar_url', $bot_avatar_url, SQLITE3_TEXT);
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
}

?>