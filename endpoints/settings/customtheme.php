<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

$main_color = $data['mainColor'];
$accent_color = $data['accentColor'];
$hover_color = $data['hoverColor'];

// Validate input, should be a color in #RRGGBB format
if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $main_color) || !preg_match('/^#[0-9A-Fa-f]{6}$/', $accent_color) || !preg_match('/^#[0-9A-Fa-f]{6}$/', $hover_color)) {
    die(json_encode([
        "success" => false,
        "message" => translate("error", $i18n)
    ]));
}

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