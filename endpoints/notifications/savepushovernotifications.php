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
            !isset($data["user_key"]) || $data["user_key"] == "" ||
            !isset($data["token"]) || $data["token"] == ""
        ) {
            $response = [
                "success" => false,
                "message" => translate('fill_mandatory_fields', $i18n)
            ];
            echo json_encode($response);
        } else {
            $enabled = $data["enabled"];
            $user_key = $data["user_key"];
            $token = $data["token"];

            $query = "SELECT COUNT(*) FROM pushover_notifications";
            $result = $db->querySingle($query);
    
            if ($result === false) {
                $response = [
                    "success" => false,
                    "message" => translate('error_saving_notifications', $i18n)
                ];
                echo json_encode($response);
            } else {
                if ($result == 0) {
                    $query = "INSERT INTO pushover_notifications (enabled, user_key, token)
                              VALUES (:enabled, :user_key, :token)";
                } else {
                    $query = "UPDATE pushover_notifications
                              SET enabled = :enabled, user_key = :user_key, token = :token";
                }
    
                $stmt = $db->prepare($query);
                $stmt->bindValue(':enabled', $enabled, SQLITE3_INTEGER);
                $stmt->bindValue(':user_key', $user_key, SQLITE3_TEXT);
                $stmt->bindValue(':token', $token, SQLITE3_TEXT);
    
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