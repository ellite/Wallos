<?php
    require_once '../../includes/connect_endpoint.php';
    session_start();

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
            !isset($data["bot_token"]) || $data["bot_token"] == "" ||
            !isset($data["chat_id"]) || $data["chat_id"] == ""
        ) {
            $response = [
                "success" => false,
                "errorMessage" => translate('fill_mandatory_fields', $i18n)
            ];
            echo json_encode($response);
        } else {
            $enabled = $data["enabled"];
            $bot_token = $data["bot_token"];
            $chat_id = $data["chat_id"];

            $query = "SELECT COUNT(*) FROM telegram_notifications";
            $result = $db->querySingle($query);
    
            if ($result === false) {
                $response = [
                    "success" => false,
                    "errorMessage" => translate('error_saving_notifications', $i18n)
                ];
                echo json_encode($response);
            } else {
                if ($result == 0) {
                    $query = "INSERT INTO telegram_notifications (enabled, bot_token, chat_id)
                              VALUES (:enabled, :bot_token, :chat_id)";
                } else {
                    $query = "UPDATE telegram_notifications
                              SET enabled = :enabled, bot_token = :bot_token, chat_id = :chat_id";
                }
    
                $stmt = $db->prepare($query);
                $stmt->bindValue(':enabled', $enabled, SQLITE3_INTEGER);
                $stmt->bindValue(':bot_token', $bot_token, SQLITE3_TEXT);
                $stmt->bindValue(':chat_id', $chat_id, SQLITE3_TEXT);
    
                if ($stmt->execute()) {
                    $response = [
                        "success" => true,
                        "message" => translate('notifications_settings_saved', $i18n)
                    ];
                    echo json_encode($response);
                } else {
                    $response = [
                        "success" => false,
                        "errorMessage" => translate('error_saving_notifications', $i18n)
                    ];
                    echo json_encode($response);
                }
            }
        }
    }
?>