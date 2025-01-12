<?php
/*
This API Endpoint accepts both POST and GET requests.
It receives the following parameters:
- convert_currency: whether to convert to the main currency (boolean) default false.
- api_key: the API key of the user.

It returns a downloadable VCAL file with the active subscriptions
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

    $sql = "SELECT * FROM subscriptions WHERE user_id = :userId AND inactive = 0 ORDER BY next_payment ASC";

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
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

    $stmt->bindValue(':inactive', false, SQLITE3_INTEGER);
    $result = $stmt->execute();

    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="subscriptions.ics"');

    if ($result === false) {
        die("BEGIN:VCALENDAR\nVERSION:2.0\nPRODID:NAME:\nEND:VCALENDAR");
    }

    $icsContent = "BEGIN:VCALENDAR\nVERSION:2.0\nPRODID:-//Wallos//iCalendar//EN\nNAME:Wallos\nX-WR-CALNAME:Wallos\n";

    while ($subscription = $result->fetchArray(SQLITE3_ASSOC)) {
        $subscription['payer_user'] = $members[$subscription['payer_user_id']];
        $subscription['category'] = $categories[$subscription['category_id']];
        $subscription['payment_method'] = $paymentMethods[$subscription['payment_method_id']];
        $subscription['currency'] = $currencies[$subscription['currency_id']]['symbol'];
        $subscription['trigger'] = $subscription['notify_days_before'] ? $subscription['notify_days_before'] : 1;
        $subscription['price'] = number_format($subscription['price'], 2);

        $uid = uniqid();
        $summary = "Wallos: " . $subscription['name'];
        $description = "Price: {$subscription['currency']}{$subscription['price']}\\nCategory: {$subscription['category']}\\nPayment Method: {$subscription['payment_method']}\\nPayer: {$subscription['payer_user']}\\nNotes: {$subscription['notes']}";
        $dtstart = (new DateTime($subscription['next_payment']))->format('Ymd');
        $dtend = (new DateTime($subscription['next_payment']))->format('Ymd');
        $location = isset($subscription['url']) ? $subscription['url'] : '';
        $alarm_trigger = '-' . $subscription['trigger'] . 'D';

        $icsContent .= <<<ICS
        BEGIN:VEVENT
        UID:$uid
        SUMMARY:$summary
        DESCRIPTION:$description
        DTSTART:$dtstart
        DTEND:$dtend
        LOCATION:$location
        STATUS:CONFIRMED
        TRANSP:OPAQUE
        BEGIN:VALARM
        ACTION:DISPLAY
        DESCRIPTION:Reminder
        TRIGGER:$alarm_trigger
        END:VALARM
        END:VEVENT
        
        ICS;
    }

    $icsContent .= "END:VCALENDAR\n";
    echo $icsContent;
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