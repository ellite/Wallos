<?php
/*
This API Endpoint accepts both POST and GET requests.
It receives the following parameters:
- api_key: the API key of the user.

It returns a JSON object with the following properties:
- success: whether the request was successful (boolean).
- title: the title of the response (string).
- payment_methods: an array of payment methods.
- notes: warning messages or additional information (array).

Example response:
{
  "success": true,
  "title": "payment_methods",
  "payment_methods": [
    {
      "id": 1,
      "name": "PayPal",
      "icon": "images/uploads/icons/paypal.png",
      "enabled": 1,
      "order": 1,
      "in_use": true
    },
    {
      "id": 2,
      "name": "Credit Card",
      "icon": "images/uploads/icons/creditcard.png",
      "enabled": 1,
      "order": 2,
      "in_use": true
    },
    {
      "id": 3,
      "name": "Bank Transfer",
      "icon": "images/uploads/icons/banktransfer.png",
      "enabled": 1,
      "order": 3,
      "in_use": false
    },
    {
      "id": 4,
      "name": "Direct Debit",
      "icon": "images/uploads/icons/directdebit.png",
      "enabled": 1,
      "order": 4,
      "in_use": false
    },
    {
      "id": 5,
      "name": "Cash",
      "icon": "images/uploads/icons/cash.png",
      "enabled": 1,
      "order": 5,
      "in_use": false
    },
    {
      "id": 6,
      "name": "Google Pay",
      "icon": "images/uploads/icons/googlepay.png",
      "enabled": 1,
      "order": 6,
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

    $sql = "SELECT * FROM payment_methods WHERE user_id = :userId";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':userId', $userId);
    $result = $stmt->execute();
    $payment_methods = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $payment_methods[] = $row;
    }

    foreach ($payment_methods as $key => $value) {
        unset($payment_methods[$key]['user_id']);
        // Check if is used in any subscriptions
        $sql = "SELECT * FROM subscriptions WHERE user_id = :userId AND payment_method_id = :paymentMethodId";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':userId', $userId);
        $stmt->bindValue(':paymentMethodId', $payment_methods[$key]['id']);
        $result = $stmt->execute();
        $subscription = $result->fetchArray(SQLITE3_ASSOC);
        if ($subscription) {
            $payment_methods[$key]['in_use'] = true;
        } else {
            $payment_methods[$key]['in_use'] = false;
        }
    }

    $response = [
        "success" => true,
        "title" => "payment_methods",
        "payment_methods" => $payment_methods,
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