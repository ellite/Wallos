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


        // Close the cURL session
        curl_close($ch);

        // Check if the message was sent successfully
        if ($response === false) {
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
} else {
    die(json_encode([
        "success" => false,
        "message" => translate("invalid_request_method", $i18n)
    ]));
}

?>