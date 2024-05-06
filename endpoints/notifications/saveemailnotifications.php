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
            !isset($data["smtpaddress"]) || $data["smtpaddress"] == "" ||
            !isset($data["smtpport"]) || $data["smtpport"] == "" ||
            !isset($data["smtpusername"]) || $data["smtpusername"] == "" ||
            !isset($data["smtppassword"]) || $data["smtppassword"] == ""
        ) {
            $response = [
                "success" => false,
                "message" => translate('fill_mandatory_fields', $i18n)
            ];
            echo json_encode($response);
        } else {
            $enabled = $data["enabled"];
            $smtpAddress = $data["smtpaddress"];
            $smtpPort = $data["smtpport"];
            $encryption = "tls";
            if (isset($data["encryption"])) {
                $encryption = $data["encryption"];
            }
            $smtpUsername = $data["smtpusername"];
            $smtpPassword = $data["smtppassword"];
            $fromEmail = $data["fromemail"];

            $query = "SELECT COUNT(*) FROM email_notifications";
            $result = $db->querySingle($query);
    
            if ($result === false) {
                $response = [
                    "success" => false,
                    "message" => translate('error_saving_notifications', $i18n)
                ];
                echo json_encode($response);
            } else {
                if ($result == 0) {
                    $query = "INSERT INTO email_notifications (enabled, smtp_address, smtp_port, smtp_username, smtp_password, from_email, encryption)
                              VALUES (:enabled, :smtpAddress, :smtpPort, :smtpUsername, :smtpPassword, :fromEmail, :encryption)";
                } else {
                    $query = "UPDATE email_notifications
                              SET enabled = :enabled, smtp_address = :smtpAddress, smtp_port = :smtpPort,
                                  smtp_username = :smtpUsername, smtp_password = :smtpPassword, from_email = :fromEmail, encryption = :encryption";
                }
    
                $stmt = $db->prepare($query);
                $stmt->bindValue(':enabled', $enabled, SQLITE3_INTEGER);
                $stmt->bindValue(':smtpAddress', $smtpAddress, SQLITE3_TEXT);
                $stmt->bindValue(':smtpPort', $smtpPort, SQLITE3_INTEGER);
                $stmt->bindValue(':smtpUsername', $smtpUsername, SQLITE3_TEXT);
                $stmt->bindValue(':smtpPassword', $smtpPassword, SQLITE3_TEXT);
                $stmt->bindValue(':fromEmail', $fromEmail, SQLITE3_TEXT);
                $stmt->bindValue(':encryption', $encryption, SQLITE3_TEXT);
    
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