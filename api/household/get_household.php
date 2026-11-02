<?php
/*
This API Endpoint accepts both POST and GET requests.
It receives the following parameters:
- api_key: the API key of the user.

It returns a JSON object with the following properties:
- success: whether the request was successful (boolean).
- title: the title of the response (string).
- household: an array of household members.
- notes: warning messages or additional information (array).

Example response:
{
  "success": true,
  "title": "household",
  "household": [
    {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "in_use": true
    },
    {
      "id": 2,
      "name": "Jane Doe",
      "email": "jane@example.com",
      "in_use": true
    }
  ],
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

    $sql = "SELECT * FROM household WHERE user_id = :userId";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':userId', $userId);
    $result = $stmt->execute();
    $household = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $household[] = $row;
    }

    foreach ($household as $key => $value) {
        unset($household[$key]['user_id']);
        // Check if is used in any subscriptions
        $sql = "SELECT * FROM subscriptions WHERE user_id = :userId AND payer_user_id = :householdId";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':userId', $userId);
        $stmt->bindValue(':householdId', $household[$key]['id']);
        $result = $stmt->execute();
        $subscription = $result->fetchArray(SQLITE3_ASSOC);
        if ($subscription) {
            $household[$key]['in_use'] = true;
        } else {
            $household[$key]['in_use'] = false;
        }
    }

    $response = [
        "success" => true,
        "title" => "household",
        "household" => $household,
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