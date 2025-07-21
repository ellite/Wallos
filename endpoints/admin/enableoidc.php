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

    $oidcEnabled = isset($data['oidcEnabled']) ? $data['oidcEnabled'] : 0;

    $stmt = $db->prepare('UPDATE admin SET oidc_oauth_enabled = :oidcEnabled WHERE id = 1');
    $stmt->bindParam(':oidcEnabled', $oidcEnabled, SQLITE3_INTEGER);
    $stmt->execute();

    if ($db->changes() > 0) {
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

} else {
    die(json_encode([
        "success" => false,
        "message" => translate('error', $i18n)
    ]));
}