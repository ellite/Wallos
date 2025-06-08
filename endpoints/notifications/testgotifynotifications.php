<?php
require_once '../../includes/connect_endpoint.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die(json_encode([
        "success" => false,
        "message" => translate('session_expired', $i18n)
    ]));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $postData = file_get_contents("php://input");
    $data = json_decode($postData, true);

    if (
        !isset($data["gotify_url"]) || $data["gotify_url"] == "" ||
        !isset($data["token"]) || $data["token"] == ""
    ) {
        $response = [
            "success" => false,
            "message" => translate('fill_mandatory_fields', $i18n)
        ];
        die(json_encode($response));
    } else {
        // Set the message parameters
        $title = translate('wallos_notification', $i18n);
        $message = translate('test_notification', $i18n);
        $priority = 5;

        $url = $data["gotify_url"];
        $token = $data["token"];
        $ignore_ssl = $data["ignore_ssl"];

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

        $ch = curl_init();

        // Set the URL and other options
        curl_setopt($ch, CURLOPT_URL, $url . "/message?token=" . $token);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'title' => $title,
            'message' => $message,
            'priority' => $priority,
        ]));
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
        if ($response === false || $httpCode < 200 || $httpCode >= 300) {
            die(json_encode([
                "success" => false,
                "message" => translate('notification_failed', $i18n),
                "response" => $response,
                "http_code" => $httpCode
            ]));
        } else {
            die(json_encode([
                "success" => true,
                "message" => translate('notification_sent_successfuly', $i18n),
                "response" => $response 
            ]));
        }
    }
} else {
    die(json_encode([
        "success" => false,
        "message" => translate("invalid_request_method", $i18n)
    ]));
}
?>