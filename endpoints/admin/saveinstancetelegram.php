<?php

/*
  Stores the instance Telegram bot token: one bot for the whole installation,
  so a household does not need one bot per person. The chat id stays personal
  and is saved with each user's own notification settings.
*/

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint_admin.php';
require_once '../../includes/instance_config.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

$configuration = wallos_get_effective_admin_configuration($db);

// A token the environment owns is not editable here, the same way the SMTP
// password is not: the page shows it as managed, and a posted value for it is
// ignored rather than written into a column nothing reads.
if (isset($configuration['managed_fields']['telegram_bot_token'])) {
    die(json_encode([
        "success" => false,
        "message" => translate('managed_by_environment', $i18n)
    ]));
}

$token = trim((string) ($data['telegrambottoken'] ?? ''));

$stmt = $db->prepare('UPDATE admin SET telegram_bot_token = :token WHERE id = 1');

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
    "message" => translate('instance_telegram_saved', $i18n)
]);
