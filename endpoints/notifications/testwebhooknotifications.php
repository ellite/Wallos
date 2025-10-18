<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

// Variables available: {{days_until}}, {{subscription_name}}, {{subscription_price}}, {{subscription_currency}}, {{subscription_category}}, {{subscription_date}}, {{subscription_payer}}, {{subscription_days_until_payment}}, {{subscription_notes}}, {{subscription_url}}
$fakeSubscription = [
    "days_until" => 5,
    "subscription_name" => "Test Subscription",
    "subscription_price" => 10.00,
    "subscription_currency" => "USD",
    "subscription_category" => "Test Category",
    "subscription_date" => date("Y-m-d H:i:s"),
    "subscription_payer" => "Test Payer",
    "subscription_days_until_payment" => 30,
    "subscription_notes" => "Test Notes",
    "subscription_url" => "https://example.com/test-subscription"
];

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

if (
    !isset($data["requestmethod"]) || $data["requestmethod"] == "" ||
    !isset($data["url"]) || $data["url"] == "" ||
    !isset($data["payload"]) || $data["payload"] == ""
) {
    $response = [
        "success" => false,
        "message" => translate('fill_mandatory_fields', $i18n)
    ];
    die(json_encode($response));
} else {
    $requestmethod = $data["requestmethod"];
    $url = $data["url"];
    $payload = $data["payload"];

    // Validate URL scheme
    $parsedUrl = parse_url($url);
    if (
        !isset($parsedUrl['scheme']) ||
        !in_array(strtolower($parsedUrl['scheme']), ['http', 'https']) ||
        !filter_var($url, FILTER_VALIDATE_URL)
    ) {
        die(json_encode([
            "success" => false,
            "message" => translate("error", $i18n)
        ]));
    }

    // Replace placeholders in the payload with fake subscription data
    foreach ($fakeSubscription as $key => $value) {
        $placeholder = "{{" . $key . "}}";
        $payload = str_replace($placeholder, $value, $payload);
    }

    $customheaders = json_decode($data["customheaders"], true);
    $ignore_ssl = $data["ignore_ssl"];

    $ch = curl_init();

    // Set the URL and other options
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $requestmethod);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    if (!empty($customheaders)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $customheaders);
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    if ($ignore_ssl) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    }

    // Execute the request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // Close the cURL session
    curl_close($ch);

    // Check if the message was sent successfully
    if ($response === false || $httpCode >= 400) {
        die(json_encode([
            "success" => false,
            "message" => translate('notification_failed', $i18n),
            "response" => curl_error($ch)
        ]));
    } else {
        die(json_encode([
            "success" => true,
            "message" => translate('notification_sent_successfuly', $i18n),
            "response" => $response
        ]));
    }
}