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
            !isset($data["webhook_url"]) || $data["webhook_url"] == "" ||
            !isset($data["payload"]) || $data["payload"] == ""
        ) {
            $response = [
                "success" => false,
                "errorMessage" => translate('fill_mandatory_fields', $i18n)
            ];
            echo json_encode($response);
        } else {
            $enabled = $data["enabled"];
            $url = $data["webhook_url"];
            $headers = $data["headers"];
            $payload = $data["payload"];

            $query = "SELECT COUNT(*) FROM webhook_notifications";
            $result = $db->querySingle($query);
    
            if ($result === false) {
                $response = [
                    "success" => false,
                    "errorMessage" => translate('error_saving_notifications', $i18n)
                ];
                echo json_encode($response);
            } else {
                if ($result == 0) {
                    $query = "INSERT INTO webhook_notifications (enabled, url, headers, payload)
                              VALUES (:enabled, :url, :headers, :payload)";
                } else {
                    $query = "UPDATE webhook_notifications
                              SET enabled = :enabled, url = :url, headers = :headers, payload = :payload";
                }
    
                $stmt = $db->prepare($query);
                $stmt->bindValue(':enabled', $enabled, SQLITE3_INTEGER);
                $stmt->bindValue(':url', $url, SQLITE3_TEXT);
                $stmt->bindValue(':headers', $headers, SQLITE3_TEXT);
                $stmt->bindValue(':payload', $payload, SQLITE3_TEXT);
    
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