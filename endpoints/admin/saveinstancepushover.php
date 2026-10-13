<?php

/*
  Stores the instance Pushover application token. The user key stays personal
  and is saved with each user's own notification settings.
*/

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint_admin.php';
require_once '../../includes/instance_config.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

$configuration = wallos_get_effective_admin_configuration($db);

if (isset($configuration['managed_fields']['pushover_token'])) {
    die(json_encode([
        "success" => false,
        "message" => translate('managed_by_environment', $i18n)
    ]));
}

$token = trim((string) ($data['pushovertoken'] ?? ''));

$stmt = $db->prepare('UPDATE admin SET pushover_token = :token WHERE id = 1');

if ($stmt === false) {
    die(json_encode([
        "success" => false,
        "message" => translate('error', $i18n)
    ]));
}

$stmt->bindValue(':token', $token, SQLITE3_TEXT);

if ($stmt->execute() === false) {
    die(json_encode([
        "success" => false,
        "message" => translate('error', $i18n)
    ]));
}

echo json_encode([
    "success" => true,
    "message" => translate('instance_pushover_saved', $i18n)
]);
