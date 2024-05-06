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
            !isset($data["gotify_url"]) || $data["gotify_url"] == "" ||
            !isset($data["token"]) || $data["token"] == ""
        ) {
            $response = [
                "success" => false,
                "message" => translate('fill_mandatory_fields', $i18n)
            ];
            echo json_encode($response);
        } else {
            $enabled = $data["enabled"];
            $url = $data["gotify_url"];
            $token = $data["token"];

            $query = "SELECT COUNT(*) FROM gotify_notifications";
            $result = $db->querySingle($query);
    
            if ($result === false) {
                $response = [
                    "success" => false,
                    "message" => translate('error_saving_notifications', $i18n)
                ];
                echo json_encode($response);
            } else {
                if ($result == 0) {
                    $query = "INSERT INTO gotify_notifications (enabled, url, token)
                              VALUES (:enabled, :url, :token)";
                } else {
                    $query = "UPDATE gotify_notifications
                              SET enabled = :enabled, url = :url, token = :token";
                }
    
                $stmt = $db->prepare($query);
                $stmt->bindValue(':enabled', $enabled, SQLITE3_INTEGER);
                $stmt->bindValue(':url', $url, SQLITE3_TEXT);
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