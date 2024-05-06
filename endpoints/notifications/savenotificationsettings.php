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

        if (!isset($data["days"]) || $data['days'] == "") {
            $response = [
                "success" => false,
                "message" => translate('fill_mandatory_fields', $i18n)
            ];
            echo json_encode($response);
        } else {
            $days = $data["days"];
            $query = "SELECT COUNT(*) FROM notification_settings";
            $result = $db->querySingle($query);
    
            if ($result === false) {
                $response = [
                    "success" => false,
                    "message" => translate('error_saving_notifications', $i18n)
                ];
                echo json_encode($response);
            } else {
                if ($result == 0) {
                    $query = "INSERT INTO notification_settings (days)
                              VALUES (:days)";
                } else {
                    $query = "UPDATE notification_settings SET days = :days";
                }
    
                $stmt = $db->prepare($query);
                $stmt->bindValue(':days', $days, SQLITE3_INTEGER);
    
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
    } else {
        $response = [
            "success" => false,
            "message" => "Invalid request method"
        ];
        echo json_encode($response);
        exit();
    }