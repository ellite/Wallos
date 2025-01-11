<?php
/*
This API Endpoint accepts both POST and GET requests.
It receives the following parameters:
- member: comma-separated IDs of the members to filter (integer) default null.
- category: the ID of the category to filter (integer) default null.
- payment_method: the ID of the payment method to filter (integer) default null.
- state: the state of the subscription to filter (boolean) default null [0 - active, 1 - inactive].
- disabled_to_bottom: whether to sort the inactive subscriptions to the bottom (boolean) default false.
- sort: the sorting method (string) default next_payment ['name', 'id', 'next_payment', 'price', 'payer_user_id', 'category_id', 'payment_method_id', 'inactive', 'alphanumeric'].
- convert_currency: whether to convert to the main currency (boolean) default false.
- api_key: the API key of the user.

It returns a JSON object with the following properties:
- success: whether the request was successful (boolean).
- title: the title of the response (string).
- subscriptions: an array of subscriptions.
- notes: warning messages or additional information (array).

Example response:
{
  "success": true,
  "title": "subscriptions",
  "subscriptions": [
    {
      "id": 1,
      "name": "Example Subscription",
      "logo": "example.png",
      "price": 10.00,
      "currency_id": 1,
      "start_date": "2024-09-01",
      "next_payment": "2024-09-01",
      "cycle": 1,
      "frequency": 1,
      "auto_renew": 1,
      "notes": "Example note",
      "payment_method_id": 1,
      "payer_user_id": 1,
      "category_id": 1,
      "notify": 1,
      "url": "https://example.com",
      "inactive": 0,
      "notify_days_before": 1,
      "user_id": 1,
      "cancelation_date": null,
      "cancellation_date": "",
      "category_name": "General",
      "payer_user_name": "John Doe",
      "payment_method_name": "PayPal"
    },
    {
      "id": 2,
      "name": "Another Subscription",
      "logo": "another.png",
      "price": 15.00,
      "currency_id": 2,
      "start_date": "2024-09-02",
      "next_payment": "2024-09-02",
      "cycle": 1,
      "frequency": 1,
      "auto_renew": 0,
      "notes": "",
      "payment_method_id": 2,
      "payer_user_id": 2,
      "category_id": 2,
      "notify": 0,
      "url": "",
      "inactive": 1,
      "notify_days_before": null,
      "user_id": 2,
      "cancelation_date": null,
      "cancellation_date": "",
      "category_name": "Entertainment",
      "payer_user_name": "Jane Doe",
      "payment_method_name": "Credit Card"
      "replacement_subscription_id": 1
    }
  ],
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

    function getPriceConverted($price, $currency, $database)
    {
        $query = "SELECT rate FROM currencies WHERE id = :currency";
        $stmt = $database->prepare($query);
        $stmt->bindParam(':currency', $currency, SQLITE3_INTEGER);
        $result = $stmt->execute();

        $exchangeRate = $result->fetchArray(SQLITE3_ASSOC);
        if ($exchangeRate === false) {
            return $price;
        } else {
            $fromRate = $exchangeRate['rate'];
            return $price / $fromRate;
        }
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
    $userCurrencyId = $user['main_currency'];

    // Get last exchange update date for user
    $sql = "SELECT * FROM last_exchange_update WHERE user_id = :userId";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':userId', $userId);
    $result = $stmt->execute();
    $lastExchangeUpdate = $result->fetchArray(SQLITE3_ASSOC);

    $canConvertCurrency = empty($lastExchangeUpdate['date']) ? false : true;

    // Get currencies for user
    $sql = "SELECT * FROM currencies WHERE user_id = :userId";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':userId', $userId);
    $result = $stmt->execute();
    $currencies = [];
    while ($currency = $result->fetchArray(SQLITE3_ASSOC)) {
        $currencies[$currency['id']] = $currency;
    }

    // Get categories for user
    $sql = "SELECT * FROM categories WHERE user_id = :userId";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':userId', $userId);
    $result = $stmt->execute();
    $categories = [];
    while ($category = $result->fetchArray(SQLITE3_ASSOC)) {
        $categories[$category['id']] = $category['name'];
    }

    // Get members for user
    $sql = "SELECT * FROM household WHERE user_id = :userId";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':userId', $userId);
    $result = $stmt->execute();
    $members = [];
    while ($member = $result->fetchArray(SQLITE3_ASSOC)) {
        $members[$member['id']] = $member['name'];
    }

    // Get payment methods for user
    $sql = "SELECT * FROM payment_methods WHERE user_id = :userId";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':userId', $userId);
    $result = $stmt->execute();
    $paymentMethods = [];
    while ($paymentMethod = $result->fetchArray(SQLITE3_ASSOC)) {
        $paymentMethods[$paymentMethod['id']] = $paymentMethod['name'];
    }

    $sort = "next_payment";
    if (isset($_REQUEST['sort'])) {
        $sort = $_REQUEST['sort'];
    }

    $sortOrder = $sort;
    $allowedSortCriteria = ['name', 'id', 'next_payment', 'price', 'payer_user_id', 'category_id', 'payment_method_id', 'inactive', 'alphanumeric'];
    $order = ($sort == "price" || $sort == "id") ? "DESC" : "ASC";

    if ($sort == "alphanumeric") {
        $sort = "name";
    }

    if (!in_array($sort, $allowedSortCriteria)) {
        $sort = "next_payment";
    }

    $sql = "SELECT * FROM subscriptions WHERE user_id = :userId";

    if (isset($_REQUEST['member'])) {
        $memberIds = explode(',', $_REQUEST['member']);
        $placeholders = array_map(function ($key) {
            return ":member{$key}";
        }, array_keys($memberIds));

        $sql .= " AND payer_user_id IN (" . implode(',', $placeholders) . ")";

        foreach ($memberIds as $key => $memberId) {
            $params[":member{$key}"] = $memberId;
        }
    }

    if (isset($_REQUEST['category'])) {
        $categoryIds = explode(',', $_REQUEST['category']);
        $placeholders = array_map(function ($key) {
            return ":category{$key}";
        }, array_keys($categoryIds));

        $sql .= " AND category_id IN (" . implode(',', $placeholders) . ")";

        foreach ($categoryIds as $key => $categoryId) {
            $params[":category{$key}"] = $categoryId;
        }
    }

    if (isset($_REQUEST['payment'])) {
        $paymentIds = explode(',', $_REQUEST['payment']);
        $placeholders = array_map(function ($key) {
            return ":payment{$key}";
        }, array_keys($paymentIds));

        $sql .= " AND payment_method_id IN (" . implode(',', $placeholders) . ")";

        foreach ($paymentIds as $key => $paymentId) {
            $params[":payment{$key}"] = $paymentId;
        }
    }

    if (isset($_REQUEST['state']) && $_REQUEST['state'] != "") {
        $sql .= " AND inactive = :inactive";
        $params[':inactive'] = $_REQUEST['state'];
    }

    $orderByClauses = [];

    if (isset($_REQUEST['disabled_to_bottom']) && $_REQUEST['disabled_to_bottom'] === 'true') {
        if (in_array($sort, ["payer_user_id", "category_id", "payment_method_id"])) {
            $orderByClauses[] = "$sort $order";
            $orderByClauses[] = "inactive ASC";
        } else {
            $orderByClauses[] = "inactive ASC";
            $orderByClauses[] = "$sort $order";
        }
    } else {
        $orderByClauses[] = "$sort $order";
        if ($sort != "inactive") {
            $orderByClauses[] = "inactive ASC";
        }
    }

    if ($sort != "next_payment") {
        $orderByClauses[] = "next_payment ASC";
    }

    $sql .= " ORDER BY " . implode(", ", $orderByClauses);

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);


    if (!empty($params)) {
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, SQLITE3_INTEGER);
        }
    }

    $result = $stmt->execute();

    if ($result) {
        $subscriptions = array();
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $subscriptions[] = $row;
        }
    }

    $subscriptionsToReturn = array();

    foreach ($subscriptions as $subscription) {
        $subscriptionToReturn = $subscription;

        if (isset($_REQUEST['convert_currency']) && $_REQUEST['convert_currency'] === 'true' && $canConvertCurrency && $subscription['currency_id'] != $userCurrencyId) {
            $subscriptionToReturn['price'] = getPriceConverted($subscription['price'], $subscription['currency_id'], $db);
        } else {
            $subscriptionToReturn['price'] = $subscription['price'];
        }

        $subscriptionToReturn['category_name'] = $categories[$subscription['category_id']];
        $subscriptionToReturn['payer_user_name'] = $members[$subscription['payer_user_id']];
        $subscriptionToReturn['payment_method_name'] = $paymentMethods[$subscription['payment_method_id']];

        $subscriptionsToReturn[] = $subscriptionToReturn;
    }

    $response = [
        "success" => true,
        "title" => "subscriptions",
        "subscriptions" => $subscriptionsToReturn,
        "notes" => []
    ];

    echo json_encode($response);

    $db->close();
    exit;


} else {
    $response = [
        "success" => false,
        "title" => "Invalid request method"
    ];
    echo json_encode($response);
    exit;
}


?>