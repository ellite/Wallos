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

    $openRegistrations = $data['open_registrations'];
    $maxUsers = $data['max_users'];
    $requireEmailVerification = $data['require_email_validation'];
    $serverUrl = $data['server_url'];

    if ($requireEmailVerification == 1 && $serverUrl == "") {
        echo json_encode([
            "success" => false,
            "message" => translate('fill_all_fields', $i18n)
        ]);
        die();
    }

    $sql = "UPDATE admin SET registrations_open = :openRegistrations, max_users = :maxUsers, require_email_verification = :requireEmailVerification, server_url = :serverUrl";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':openRegistrations', $openRegistrations, SQLITE3_INTEGER);
    $stmt->bindParam(':maxUsers', $maxUsers, SQLITE3_INTEGER);
    $stmt->bindParam(':requireEmailVerification', $requireEmailVerification, SQLITE3_INTEGER);
    $stmt->bindParam(':serverUrl', $serverUrl, SQLITE3_TEXT);
    $result = $stmt->execute();

    if ($result) {
        echo json_encode([
            "success" => true,
            "message" => translate('success', $i18n)
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => translate('error', $i18n)
        ]);
    }
}

?>