<?php
/*
This API Endpoint accepts both POST and GET requests.
It receives the following parameters:
- api_key: the API key of the user (string, required).
- id / subscription_id: the ID of the subscription to retrieve (integer, required).
- convert_currency: whether to convert the price to the user's main currency (boolean, default false).

It returns a JSON object with the following properties:
- success: whether the request was successful (boolean).
- title: the title of the response (string).
- subscription: an object containing the subscription details.
- notes: warning messages or additional information (array).

Example response:
{
  "success": true,
  "title": "subscription",
  "subscription": {
    "id": 1,
    "name": "Netflix",
    "logo": "1719827361-payments-netflix.png",
    "price": 15.99,
    "currency_id": 1,
    "start_date": "2026-01-01",
    "next_payment": "2026-08-01",
    "cycle": 3,
    "frequency": 1,
    "auto_renew": 1,
    "notes": "Premium plan",
    "payment_method_id": 2,
    "payer_user_id": 1,
    "category_id": 3,
    "notify": 1,
    "url": "https://netflix.com",
    "inactive": 0,
    "notify_days_before": 2,
    "user_id": 1,
    "cancelation_date": null,
    "cancellation_date": "",
    "category_name": "Entertainment",
    "payer_user_name": "John Doe",
    "payment_method_name": "PayPal"
  },
  "notes": []
}
*/

require_once '../../includes/connect_endpoint.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER["REQUEST_METHOD"] === "POST" || $_SERVER["REQUEST_METHOD"] === "GET") {
    $apiKey = $_REQUEST['api_key'] ?? $_REQUEST['apiKey'] ?? null;
    $subscriptionId = $_REQUEST['id'] ?? $_REQUEST['subscription_id'] ?? $_REQUEST['subscriptionId'] ?? null;

    if (!$apiKey || !$subscriptionId) {
        echo json_encode([
            "success" => false,
            "title" => "Missing parameters",
            "message" => "Both API key and subscription ID are required."
        ]);
        exit;
    }

    $subscriptionId = intval($subscriptionId);

    // Authenticate user
    $sql = "SELECT * FROM user WHERE api_key = :apiKey";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':apiKey', $apiKey, SQLITE3_TEXT);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);

    if (!$user) {
        echo json_encode([
            "success" => false,
            "title" => "Invalid API key",
            "message" => "Unauthorized access."
        ]);
        exit;
    }

    $userId = $user['id'];
    $userCurrencyId = $user['main_currency'];

    // Retrieve subscription and check ownership
    $subSql = "SELECT * FROM subscriptions WHERE id = :subscriptionId AND user_id = :userId";
    $subStmt = $db->prepare($subSql);
    $subStmt->bindValue(':subscriptionId', $subscriptionId, SQLITE3_INTEGER);
    $subStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $subResult = $subStmt->execute();
    $subscription = $subResult->fetchArray(SQLITE3_ASSOC);

    if (!$subscription) {
        echo json_encode([
            "success" => false,
            "title" => "Subscription not found",
            "message" => "Subscription not found or does not belong to you."
        ]);
        exit;
    }

    // Resolve Category Name
    $catSql = "SELECT name FROM categories WHERE id = :categoryId AND user_id = :userId";
    $catStmt = $db->prepare($catSql);
    $catStmt->bindValue(':categoryId', $subscription['category_id'], SQLITE3_INTEGER);
    $catStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $catResult = $catStmt->execute();
    $categoryRow = $catResult->fetchArray(SQLITE3_ASSOC);
    $subscription['category_name'] = $categoryRow ? $categoryRow['name'] : 'No category';

    // Resolve Payer Name
    $payerSql = "SELECT name FROM household WHERE id = :payerId AND user_id = :userId";
    $payerStmt = $db->prepare($payerSql);
    $payerStmt->bindValue(':payerId', $subscription['payer_user_id'], SQLITE3_INTEGER);
    $payerStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $payerResult = $payerStmt->execute();
    $payerRow = $payerResult->fetchArray(SQLITE3_ASSOC);
    $subscription['payer_user_name'] = $payerRow ? $payerRow['name'] : 'Unknown member';

    // Resolve Payment Method Name
    $pmSql = "SELECT name FROM payment_methods WHERE id = :pmId AND (user_id = :userId OR user_id = 0 OR user_id IS NULL)";
    $pmStmt = $db->prepare($pmSql);
    $pmStmt->bindValue(':pmId', $subscription['payment_method_id'], SQLITE3_INTEGER);
    $pmStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $pmResult = $pmStmt->execute();
    $pmRow = $pmResult->fetchArray(SQLITE3_ASSOC);
    $subscription['payment_method_name'] = $pmRow ? $pmRow['name'] : 'Unknown payment method';

    // Optional Currency Conversion
    if (isset($_REQUEST['convert_currency']) && $_REQUEST['convert_currency'] === 'true' && $subscription['currency_id'] != $userCurrencyId) {
        $updateSql = "SELECT * FROM last_exchange_update WHERE user_id = :userId";
        $updateStmt = $db->prepare($updateSql);
        $updateStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
        $updateResult = $updateStmt->execute();
        $lastExchangeUpdate = $updateResult->fetchArray(SQLITE3_ASSOC);
        $canConvertCurrency = !empty($lastExchangeUpdate['date']);

        if ($canConvertCurrency) {
            $currSql = "SELECT rate FROM currencies WHERE id = :currencyId AND user_id = :userId";
            $currStmt = $db->prepare($currSql);
            $currStmt->bindValue(':currencyId', $subscription['currency_id'], SQLITE3_INTEGER);
            $currStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
            $currResult = $currStmt->execute();
            $subCurrency = $currResult->fetchArray(SQLITE3_ASSOC);

            if ($subCurrency) {
                $subscription['price'] = $subscription['price'] / $subCurrency['rate'];
            }
        }
    }

    echo json_encode([
        "success" => true,
        "title" => "subscription",
        "subscription" => $subscription,
        "notes" => []
    ]);

    $db->close();
} else {
    echo json_encode([
        "success" => false,
        "title" => "Invalid request method",
        "message" => "Only GET and POST requests are supported."
    ]);
}
?>
