<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/instance_config.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

// An empty bot token is allowed: it means the instance bot, if the deployment
// configured one, the same as it does to the cron.
$botToken = wallos_telegram_bot_token($db, $data["bottoken"] ?? '');

if ($botToken === "" || !isset($data["chatid"]) || $data["chatid"] == "") {
    $response = [
        "success" => false,
        "message" => translate('fill_mandatory_fields', $i18n)
    ];
    echo json_encode($response);
} else {
    // Set the message parameters
    $title = translate('wallos_notification', $i18n);
    $message = translate('test_notification', $i18n);

    $chatId = $data["chatid"];

    $ch = curl_init();

    // Set the URL and other options
    curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot" . $botToken . "/sendMessage");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'chat_id' => $chatId,
        'text' => $message,
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    // Execute the request
    $response = curl_exec($ch);

    // Close the cURL session
    unset($ch);

    // Check if the message was sent successfully
    if ($response === false) {
        die(json_encode([
            "success" => false,
            "message" => translate('notification_failed', $i18n)
        ]));
    } else {
        die(json_encode([
            "success" => true,
            "message" => translate('notification_sent_successfuly', $i18n)
        ]));
    }
}