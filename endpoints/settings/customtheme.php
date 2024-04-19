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
        
        $main_color = $data['mainColor'];
        $accent_color = $data['accentColor'];
        $hover_color = $data['hoverColor'];

        $stmt = $db->prepare('DELETE FROM custom_colors');
        $stmt->execute();

        $stmt = $db->prepare('INSERT INTO custom_colors (main_color, accent_color, hover_color) VALUES (:main_color, :accent_color, :hover_color)');
        $stmt->bindParam(':main_color', $main_color, SQLITE3_TEXT);
        $stmt->bindParam(':accent_color', $accent_color, SQLITE3_TEXT);
        $stmt->bindParam(':hover_color', $hover_color, SQLITE3_TEXT);

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