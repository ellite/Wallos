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

    $show_original_price = $data['value'];

    // Validate input
    if (!isset($show_original_price) || !is_bool($show_original_price)) {
        die(json_encode([
            "success" => false,
            "message" => translate("error", $i18n)
        ]));
    }

    $stmt = $db->prepare('UPDATE settings SET show_original_price = :show_original_price WHERE user_id = :userId');
    $stmt->bindParam(':show_original_price', $show_original_price, SQLITE3_INTEGER);
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