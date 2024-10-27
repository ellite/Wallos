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
        !isset($data["webhook_url"]) || $data["webhook_url"] == "" ||
        !isset($data["payload"]) || $data["payload"] == ""
    ) {
        $response = [
            "success" => false,
            "message" => translate('fill_mandatory_fields', $i18n)
        ];
        echo json_encode($response);
    } else {
        $enabled = $data["enabled"];
        $url = $data["webhook_url"];
        $headers = $data["headers"];
        $payload = $data["payload"];
        $iterator = $data["iterator"];
        $ignore_ssl = $data["ignore_ssl"];

        $query = "SELECT COUNT(*) FROM webhook_notifications WHERE user_id = :userId";
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
                $query = "INSERT INTO webhook_notifications (enabled, url, headers, payload, iterator, user_id, ignore_ssl)
                              VALUES (:enabled, :url, :headers, :payload, :iterator, :userId, :ignore_ssl)";
            } else {
                $query = "UPDATE webhook_notifications
                              SET enabled = :enabled, url = :url, headers = :headers, payload = :payload, iterator = :iterator, ignore_ssl = :ignore_ssl WHERE user_id = :userId";
            }

            $stmt = $db->prepare($query);
            $stmt->bindValue(':enabled', $enabled, SQLITE3_INTEGER);
            $stmt->bindValue(':url', $url, SQLITE3_TEXT);
            $stmt->bindValue(':headers', $headers, SQLITE3_TEXT);
            $stmt->bindValue(':payload', $payload, SQLITE3_TEXT);
            $stmt->bindValue(':iterator', $iterator, SQLITE3_TEXT);
            $stmt->bindValue(':ignore_ssl', $ignore_ssl, SQLITE3_INTEGER);
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