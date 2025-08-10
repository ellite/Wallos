<?php
/*
This API Endpoint accepts both POST and GET requests.
It receives the following parameters:
- api_key: the API key of the user.

It returns a JSON object with the following properties:
- success: whether the request was successful (boolean).
- title: the title of the response (string).
- oidc_settings: an object containing the OIDC settings.
- notes: warning messages or additional information (array).

Example response:
{
  "success": true,
  "title": "oidc_settings",
  "oidc_settings": {
    "name": "Authentik",
    "client_id": "CJMLcyyS94cUMXkitNZuokayArnn23TXxpeUv48E",
    "client_secret": "SzfQBIibfN0gEAgCORrKnGnrYe9yqASWAYUuu1byelVosCHlnoqAdWlMDppblyuByb38Zw78AAlgMmdK6SWpGjOU4IiqaoltkAEh52trcqCB8briP1TqqXZdar4xfhVw",
    "authorization_url": "https://auth.bellamylab.com/application/o/authorize/",
    "token_url": "https://auth.bellamylab.com/application/o/token/",
    "user_info_url": "https://auth.bellamylab.com/application/o/userinfo/",
    "redirect_url": "http://localhost:80/wallos",
    "logout_url": "https://auth.bellamylab.com/application/o/wallos/end-session/",
    "user_identifier_field": "sub",
    "scopes": "openid email profile",
    "auth_style": "auto",
    "created_at": "2025-07-20 20:31:50",
    "updated_at": "2025-07-20 20:31:50",
    "auto_create_user": 0,
    "password_login_disabled": 0
  },
  "notes": []
}
*/

require_once '../../includes/connect_endpoint.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER["REQUEST_METHOD"] === "POST" || $_SERVER["REQUEST_METHOD"] === "GET") {
    // if the parameters are not set, return an error

    $apiKey = $_REQUEST['api_key'] ?? $_REQUEST['apiKey'] ?? null;

    if (!$apiKey) {
        $response = [
            "success" => false,
            "title" => "Missing parameters"
        ];
        echo json_encode($response);
        exit;
    }


    // Get user from API key
    $sql = "SELECT * FROM user WHERE api_key = :apiKey";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':apiKey', $apiKey);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);

    // If the user is not found, return an error
    if (!$user) {
        $response = [
            "success" => false,
            "title" => "Invalid API key"
        ];
        echo json_encode($response);
        exit;
    }

    $userId = $user['id'];

    if ($userId !== 1) {
        $response = [
            "success" => false,
            "title" => "Invalid user"
        ];
        echo json_encode($response);
        exit;
    }

    $sql = "SELECT * FROM 'oauth_settings' WHERE id = 1";
    $stmt = $db->prepare($sql);
    $result = $stmt->execute();
    $oidc_settings = $result->fetchArray(SQLITE3_ASSOC);

    if ($oidc_settings) {
        unset($oidc_settings['id']);
    }

    $response = [
        "success" => true,
        "title" => "oidc_settings",
        "oidc_settings" => $oidc_settings,
        "notes" => []
    ];

    echo json_encode($response);

    $db->close();

} else {
    $response = [
        "success" => false,
        "title" => "Invalid request method"
    ];
    echo json_encode($response);
    exit;
}

?>