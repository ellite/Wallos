<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

if (
    !isset($data["url"]) || $data["url"] == ""
) {
    $response = [
        "success" => false,
        "message" => translate('fill_mandatory_fields', $i18n)
    ];
    echo json_encode($response);
} else {
    // Set the message parameters
    $title = translate('wallos_notification', $i18n);
    $message = translate('test_notification', $i18n);

    $webhook_url = $data["url"];
    $bot_username = $data["bot_username"];
    $bot_avatar_url = $data["bot_avatar"];

    // Validate URL scheme
    $parsedUrl = parse_url($webhook_url);
    if (
        !isset($parsedUrl['scheme']) ||
        !in_array(strtolower($parsedUrl['scheme']), ['http', 'https']) ||
        !filter_var($webhook_url, FILTER_VALIDATE_URL)
    ) {
        die(json_encode([
            "success" => false,
            "message" => translate("error", $i18n)
        ]));
    }

    $postfields = [
        'content' => $message,
        'embeds' => [
            [
                'title' => $title,
                'description' => $message,
                'color' => hexdec("FF0000")
            ]
        ]
    ];

    if (!empty($bot_username)) {
        $postfields['username'] = $bot_username;
    }

    if (!empty($bot_avatar_url)) {
        $postfields['avatar_url'] = $bot_avatar_url;
    }

    $ch = curl_init();

    // Set the URL and other options
    curl_setopt($ch, CURLOPT_URL, $webhook_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postfields));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Execute the request
    $response = curl_exec($ch);

    // Close the cURL session
    curl_close($ch);

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