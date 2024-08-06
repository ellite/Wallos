<?php

require_once '../../includes/connect_endpoint.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die(json_encode([
        "success" => false,
        "message" => translate('session_expired', $i18n)
    ]));
}

// Check that user is an admin
if ($userId !== 1) {
    die(json_encode([
        "success" => false,
        "message" => translate('error', $i18n)
    ]));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $postData = file_get_contents("php://input");
    $data = json_decode($postData, true);

    $updateNotification = $data['notificationEnabled'];

    // Save settings
    $stmt = $db->prepare('UPDATE admin SET update_notification = :update_notification');
    $stmt->bindValue(':update_notification', $updateNotification, SQLITE3_INTEGER);
    $result = $stmt->execute();

    if ($result) {
        die(json_encode([
            "success" => true,
            "message" => translate('success', $i18n)
        ]));
    } else {
        die(json_encode([
            "success" => false,
            "message" => translate('error', $i18n)
        ]));
    }

}

?>