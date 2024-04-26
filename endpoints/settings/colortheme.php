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
        
        $color = $data['color'];

        $stmt = $db->prepare('UPDATE settings SET color_theme = :color');
        $stmt->bindParam(':color', $color, SQLITE3_TEXT);

        if ($stmt->execute()) {
            die(json_encode([
                "success" => true,
                "message" => translate("success", $i18n)
            ]));
        } else {
            die(json_encode([
                "success" => false,
                "message" => translate("error", $i18n)
            ]));
        }
    }

?>