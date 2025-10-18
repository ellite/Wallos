<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint_admin.php';

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