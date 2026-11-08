<?php

/*
  Stores the instance ntfy server and the credential it may require. The topic
  stays personal and is saved with each user's own notification settings.
*/

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint_admin.php';
require_once '../../includes/ssrf_helper.php';
require_once '../../includes/instance_config.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

$configuration = wallos_get_effective_admin_configuration($db);

$server = trim((string) ($data['ntfyserver'] ?? ''));
$headers = (string) ($data['ntfyheaders'] ?? '');

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

$columns = [];

if (!isset($configuration['managed_fields']['ntfy_server'])) {
    $columns['ntfy_server'] = $server;
}

if (!isset($configuration['managed_fields']['ntfy_headers'])) {
    $columns['ntfy_headers'] = $headers;
}

// A field the environment owns is not editable here, the same way the SMTP
// password is not: a posted value for it is ignored rather than written into a
// column nothing reads.
if (empty($columns)) {
    die(json_encode([
        "success" => false,
        "message" => translate('managed_by_environment', $i18n)
    ]));
}

foreach ($columns as $column => $value) {
    $stmt = $db->prepare('UPDATE admin SET ' . $column . ' = :value WHERE id = 1');

    if ($stmt === false) {
        die(json_encode([
            "success" => false,
            "message" => translate('error', $i18n)
        ]));
    }

    $stmt->bindValue(':value', $value, SQLITE3_TEXT);

    if ($stmt->execute() === false) {
        die(json_encode([
            "success" => false,
            "message" => translate('error', $i18n)
        ]));
    }
}

echo json_encode([
    "success" => true,
    "message" => translate('instance_ntfy_saved', $i18n)
]);
