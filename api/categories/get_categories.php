<?php
/*
This API Endpoint accepts both POST and GET requests.
It receives the following parameters:
- api_key: the API key of the user.

It returns a JSON object with the following properties:
- success: whether the request was successful (boolean).
- title: the title of the response (string).
- categories: an array of categories.
- notes: warning messages or additional information (array).

Example response:
{
  "success": true,
  "title": "categories",
  "categories": [
    {
      "id": 1,
      "name": "General",
      "order": 1,
      "in_use": true
    },
    {
      "id": 2,
      "name": "Entertainment",
      "order": 2,
      "in_use": true
    },
    {
      "id": 3,
      "name": "Music",
      "order": 3,
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

    $sql = "SELECT * FROM categories WHERE user_id = :userId";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':userId', $userId);
    $result = $stmt->execute();
    $categories = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $categories[] = $row;
    }

    foreach ($categories as $key => $value) {
        unset($categories[$key]['user_id']);
        // Check if it's in use in any subscription
        $categoryId = $categories[$key]['id'];
        $sql = "SELECT COUNT(*) as count FROM subscriptions WHERE user_id = :userId AND category_id = :categoryId";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':categoryId', $categoryId);
        $stmt->bindValue(':userId', $userId);
        $result = $stmt->execute();
        $count = $result->fetchArray(SQLITE3_ASSOC);
        if ($count['count'] > 0) {
            $categories[$key]['in_use'] = true;
        } else {
            $categories[$key]['in_use'] = false;
        }
    }

    $response = [
        "success" => true,
        "title" => "categories",
        "categories" => $categories,
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