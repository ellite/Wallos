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
        !isset($data["host"]) || $data["host"] == "" ||
        !isset($data["topic"]) || $data["topic"] == ""
    ) {
        $response = [
            "success" => false,
            "message" => translate('fill_mandatory_fields', $i18n)
        ];
        echo json_encode($response);
    } else {
        $host = rtrim($data["host"], '/');
        $topic = $data["topic"];
        $headers = json_decode($data["headers"], true);
        if ($headers === null) {
            $headers = [];
        }
        $customheaders = array_map(function ($key, $value) {
            return "$key: $value";
        }, array_keys($headers), $headers);

        $url = "$host/$topic";

        // Set the message parameters
        $message = translate('test_notification', $i18n);

        $ch = curl_init();

        // Set the URL and other options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $message);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $customheaders);
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
        }

        die(json_encode([
            "success" => true,
            "message" => translate('notification_sent_successfuly', $i18n)
        ]));
    }

}

?>