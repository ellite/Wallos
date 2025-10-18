<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

if (
    !isset($data["user_key"]) || $data["user_key"] == "" ||
    !isset($data["token"]) || $data["token"] == ""
) {
    $response = [
        "success" => false,
        "message" => translate('fill_mandatory_fields', $i18n)
    ];
    echo json_encode($response);
} else {
    // Set the message parameters
    $message = translate('test_notification', $i18n);

    $user_key = $data["user_key"];
    $token = $data["token"];

    $ch = curl_init();

    // Set the URL and other options
    curl_setopt($ch, CURLOPT_URL, "https://api.pushover.net/1/messages.json");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'token' => $token,
        'user' => $user_key,
        'message' => $message,
    ]));
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