<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

if (
    !isset($data["bottoken"]) || $data["bottoken"] == "" ||
    !isset($data["chatid"]) || $data["chatid"] == ""
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
    
    // Use custom template if provided
    if (isset($data["message_template"]) && !empty($data["message_template"])) {
        $template = $data["message_template"];
        $template = str_replace('{name}', 'Test Subscription', $template);
        $template = str_replace('{price}', '100 ₽', $template);
        $template = str_replace('{next_payment}', date('Y-m-d', strtotime('+7 days')), $template);
        $template = str_replace('{days_left}', '7 days', $template);
        $template = str_replace('{url}', 'https://example.com', $template);
        $template = str_replace('{category}', 'Test', $template);
        $template = str_replace('{notes}', 'Test notification', $template);
        $message = $template;
    }

    $botToken = $data["bottoken"];
    $chatId = $data["chatid"];

    $ch = curl_init();

    // Set the URL and other options
    curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot" . $botToken . "/sendMessage");
    curl_setopt($ch, CURLOPT_POST, 1);
    $postFields = [
        'chat_id' => $chatId,
        'text' => $message,
    ];
    if (isset($data["message_template"]) && !empty($data["message_template"])) {
        $postFields['parse_mode'] = 'HTML';
    }
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

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