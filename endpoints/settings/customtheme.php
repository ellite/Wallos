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

    $main_color = $data['mainColor'];
    $accent_color = $data['accentColor'];
    $hover_color = $data['hoverColor'];

    if ($main_color == $accent_color) {
        die(json_encode([
            "success" => false,
            "message" => translate("main_accent_color_error", $i18n)
        ]));
    }

    $stmt = $db->prepare('DELETE FROM custom_colors WHERE user_id = :userId');
    $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
    $stmt->execute();

    $stmt = $db->prepare('INSERT INTO custom_colors (main_color, accent_color, hover_color, user_id) VALUES (:main_color, :accent_color, :hover_color, :userId)');
    $stmt->bindParam(':main_color', $main_color, SQLITE3_TEXT);
    $stmt->bindParam(':accent_color', $accent_color, SQLITE3_TEXT);
    $stmt->bindParam(':hover_color', $hover_color, SQLITE3_TEXT);
    $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);

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