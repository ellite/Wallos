<?php

/*
  Stores the instance Gotify server. Only the address is shared: the
  application token stays personal, so each account's messages keep arriving
  under its own Gotify application.
*/

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint_admin.php';
require_once '../../includes/instance_config.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

$configuration = wallos_get_effective_admin_configuration($db);

if (isset($configuration['managed_fields']['gotify_server'])) {
    die(json_encode([
        "success" => false,
        "message" => translate('managed_by_environment', $i18n)
    ]));
}

$server = trim((string) ($data['gotifyserver'] ?? ''));

if ($server !== '') {
    $parsed = parse_url($server);

    if (!is_array($parsed) || !isset($parsed['scheme'], $parsed['host'])
        || !in_array(strtolower($parsed['scheme']), ['http', 'https'], true)) {
        die(json_encode([
            "success" => false,
            "message" => translate('error', $i18n)
        ]));
    }
}

$stmt = $db->prepare('UPDATE admin SET gotify_server = :server WHERE id = 1');

if ($stmt === false) {
    die(json_encode([
        "success" => false,
        "message" => translate('error', $i18n)
    ]));
}

$stmt->bindValue(':server', $server, SQLITE3_TEXT);

if ($stmt->execute() === false) {
    die(json_encode([
        "success" => false,
        "message" => translate('error', $i18n)
    ]));
}

echo json_encode([
    "success" => true,
    "message" => translate('instance_gotify_saved', $i18n)
]);
