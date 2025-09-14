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

    if (!isset($data["token"]) || $data["token"] == "") {
        $response = [
            "success" => false,
            "message" => translate('fill_mandatory_fields', $i18n)
        ];
        echo json_encode($response);
    } else {
        // Set the message parameters
        $title = translate('wallos_notification', $i18n);
        $message = translate('test_notification', $i18n);

        $token = $data["token"];

        $ch = curl_init();

        // Set the URL and other options for PushPlus
        $postData = [
            "token" => $token,
            "title" => "您的订阅到期拉",
            "content" => $message,
            "template" => "json"
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://www.pushplus.plus/send',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($postData),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 10
        ]);

        // Execute the request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        // Close the cURL session
        curl_close($ch);

        // Check if the message was sent successfully
        if ($response === false) {
            die(json_encode([
                "success" => false,
                "message" => translate('notification_failed', $i18n) . ": " . $curlError
            ]));
        } else {
            $responseData = json_decode($response, true);
            if (isset($responseData['code']) && $responseData['code'] == 200) {
                die(json_encode([
                    "success" => true,
                    "message" => translate('notification_sent_successfuly', $i18n)
                ]));
            } else {
                $errorMsg = isset($responseData['msg']) ? $responseData['msg'] : translate('notification_failed', $i18n);
                die(json_encode([
                    "success" => false,
                    "message" => $errorMsg
                ]));
            }
        }
    }
} else {
    die(json_encode([
        "success" => false,
        "message" => translate("invalid_request_method", $i18n)
    ]));
}
?>