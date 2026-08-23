<?php
/*
This API Endpoint accepts both POST and GET requests.
It receives the following parameters:
- convert_currency: whether to convert to the main currency (boolean) default false.
- api_key: the API key of the user.

It returns a downloadable VCAL file with the active subscriptions
*/

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/currency_rates.php';
require_once '../../includes/ical_helper.php';

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

    function getPriceConverted($price, $currency, $database)
    {
        return wallos_convert_price($price, $currency, $database);
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

    // Get notification settings
    $notificationQuery = "SELECT days FROM notification_settings WHERE user_id = :userId";
    $notificationQueryStmt = $db->prepare($notificationQuery);
    $notificationQueryStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $notificationResult = $notificationQueryStmt->execute();
    $globalNotificationDays = 1; // Default value
    if ($row = $notificationResult->fetchArray(SQLITE3_ASSOC)) {
        $globalNotificationDays = $row['days'];
    }

    foreach ($subscriptions as $subscription) {
        $subscriptionToReturn = $subscription;

        $wasConverted = isset($_REQUEST['convert_currency']) && $_REQUEST['convert_currency'] === 'true' && $canConvertCurrency && $subscription['currency_id'] != $userCurrencyId;
        if ($wasConverted) {
            $subscriptionToReturn['price'] = getPriceConverted($subscription['price'], $subscription['currency_id'], $db);
            // Converted prices are now in the user's main currency, so the
            // symbol shown alongside them has to switch too.
            $subscriptionToReturn['currency_id'] = $userCurrencyId;
        } else {
            $subscriptionToReturn['price'] = $subscription['price'];
        }

        $subscriptionToReturn['category_name'] = isset($categories[$subscription['category_id']]) ? $categories[$subscription['category_id']] : 'No category';
        $subscriptionToReturn['payer_user_name'] = isset($members[$subscription['payer_user_id']]) ? $members[$subscription['payer_user_id']] : 'Unknown member';
        $subscriptionToReturn['payment_method_name'] = isset($paymentMethods[$subscription['payment_method_id']]) ? $paymentMethods[$subscription['payment_method_id']] : 'Unknown payment method';

        $subscriptionsToReturn[] = $subscriptionToReturn;
    }

    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="subscriptions.ics"');

    $icsContent = "BEGIN:VCALENDAR\nVERSION:2.0\nPRODID:-//Wallos//iCalendar//EN\nNAME:Wallos\nX-WR-CALNAME:Wallos\n";

    foreach ($subscriptionsToReturn as $subscription) {
        $subscription['payer_user'] = $subscription['payer_user_name'];
        $subscription['category'] = $subscription['category_name'];
        $subscription['payment_method'] = $subscription['payment_method_name'];
        $subscription['currency'] = isset($currencies[$subscription['currency_id']]) ? $currencies[$subscription['currency_id']]['symbol'] : '';
        $subscription['trigger'] = ($subscription['notify_days_before'] == -1) ? $globalNotificationDays : ($subscription['notify_days_before'] ?: 1);
        $subscription['price'] = number_format($subscription['price'], 2);

        $uid = 'wallos-subscription-' . $subscription['id'] . '@wallos';
        $summary = icalEscape(html_entity_decode($subscription['name'], ENT_QUOTES, 'UTF-8'));
        $notes = icalEscape(html_entity_decode($subscription['notes'], ENT_QUOTES, 'UTF-8'));
        $category = icalEscape($subscription['category']);
        $paymentMethod = icalEscape($subscription['payment_method']);
        $payer = icalEscape($subscription['payer_user']);
        $description = "Price: {$subscription['currency']}{$subscription['price']}\\nCategory: {$category}\\nPayment Method: {$paymentMethod}\\nPayer: {$payer}\\nNotes: {$notes}";
        $dtstamp = gmdate('Ymd\THis\Z');
        $dtstart = (new DateTime($subscription['next_payment']))->format('Ymd');
        $dtend = (new DateTime($subscription['next_payment']))->format('Ymd');
        $location = icalEscape(isset($subscription['url']) ? $subscription['url'] : '');
        $alarm_trigger = '-P' . $subscription['trigger'] . 'D';

        $icsContent .= <<<ICS
        BEGIN:VEVENT
        UID:$uid
        DTSTAMP:$dtstamp
        SUMMARY:$summary
        DESCRIPTION:$description
        DTSTART;VALUE=DATE:$dtstart
        DTEND;VALUE=DATE:$dtend
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
