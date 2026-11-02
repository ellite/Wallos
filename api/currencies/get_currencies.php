<?php
/*
This API Endpoint accepts both POST and GET requests.
It receives the following parameters:
- api_key: the API key of the user.

It returns a JSON object with the following properties:
- success: whether the request was successful (boolean).
- title: the title of the response (string).
- main_currency: the main currency of the user (integer).
- currencies: an array of currencies.
- notes: warning messages or additional information (array).

Example response:
{
  "success": true,
  "title": "currencies",
  "main_currency": 3,
  "currencies": [
    {
      "id": 1,
      "name": "US Dollar",
      "symbol": "$",
      "code": "USD",
      "rate": "1.1000",
      "in_use": true
    },
    {
      "id": 2,
      "name": "Japanese Yen",
      "symbol": "¥",
      "code": "JPY",
      "rate": "150.0000",
      "in_use": true
    },
    {
      "id": 3,
      "name": "Euro",
      "symbol": "€",
      "code": "EUR",
      "rate": "1.0000",
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

    $sql = "SELECT * FROM currencies WHERE user_id = :userId";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':userId', $userId);
    $result = $stmt->execute();
    $currencies = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $currencies[] = $row;
    }

    foreach ($currencies as $key => $value) {
        unset($currencies[$key]['user_id']);
        // Check if it's in use in any subscription
        $currencyId = $currencies[$key]['id'];
        $sql = "SELECT COUNT(*) as count FROM subscriptions WHERE user_id = :userId AND currency_id = :currencyId";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':currencyId', $currencyId);
        $stmt->bindValue(':userId', $userId);
        $result = $stmt->execute();
        $count = $result->fetchArray(SQLITE3_ASSOC);
        if ($count['count'] > 0) {
            $currencies[$key]['in_use'] = true;
        } else {
            $currencies[$key]['in_use'] = false;
        }
    }

    $mainCurrency = $user['main_currency'];

    $response = [
        "success" => true,
        "title" => "currencies",
        "main_currency" => $mainCurrency,
        "currencies" => $currencies,
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