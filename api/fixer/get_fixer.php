<?php
/*
This API Endpoint accepts both POST and GET requests.
It receives the following parameters:
- api_key: the API key of the user.

It returns a JSON object with the following properties:
- success: whether the request was successful (boolean).
- title: the title of the response (string).
- fixer: an object containing the Fixer settings.
- notes: warning messages or additional information (array).

Example response:
{
  "success": true,
  "title": "fixer",
  "fixer": {
    "api_key": "********",
    "provider": 0,
    "provider_name": "Fixer.io"
  },
  "notes": []
}
*/

require_once '../../includes/connect_endpoint.php';

header('Content-Type: application/json, charset=UTF-8');

if ($_SERVER["REQUEST_METHOD"] === "POST" || $_SERVER["REQUEST_METHOD"] === "GET") {
    // if the parameters are not set, return an error

    if (!isset($_REQUEST['api_key'])) {
        $response = [
            "success" => false,
            "title" => "Missing parameters"
        ];
        echo json_encode($response);
        exit;
    }

    $apiKey = $_REQUEST['api_key'];

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
    $providers = [
        0 => "Fixer.io",
        1 => "APILayer.com"
    ]; 

    $query = "SELECT * FROM fixer WHERE user_id = :userId";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':userId', $userId);
    $result = $stmt->execute();
    $fixer = $result->fetchArray(SQLITE3_ASSOC);

    $notes = [];

    if ($fixer) {
        unset($fixer['user_id']);
        $fixer['provider_name'] = $providers[$fixer['provider']];
        if ($fixer['api_key']) {
            $fixer['api_key'] = "********";
        }
    } else {
        $fixer = [];
        $notes[] = "No fixer settings found";
    }

    $response = [
        "success" => true,
        "title" => "fixer",
        "fixer" => $fixer,
        "notes" => $notes
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