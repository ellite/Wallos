<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/getdbkeys.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die(json_encode([
        "success" => false,
        "message" => translate('session_expired', $i18n)
    ]));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $postData = file_get_contents("php://input");
    $data = json_decode($postData, true);

    $id = $data['id'];

    $stmt = $db->prepare('SELECT * FROM subscriptions WHERE id = :id AND user_id = :userId');
    $stmt->bindParam(':id', $id, SQLITE3_INTEGER);
    $stmt->bindParam(':userId', $_SESSION['userId'], SQLITE3_INTEGER); // Assuming $_SESSION['userId'] holds the logged-in user's ID
    $result = $stmt->execute();

    if ($result === false) {
        die(json_encode([
            'success' => false,
            'message' => "Subscription not found"
        ]));
    }

    $subscription = $result->fetchArray(SQLITE3_ASSOC); // Fetch the subscription details as an associative array

    if ($subscription) {
        $subscription['payer_user'] = $members[$subscription['payer_user_id']]['name']; 
        $subscription['category'] = $categories[$subscription['category_id']]['name'];
        $subscription['payment_method'] = $payment_methods[$subscription['payment_method_id']]['name'];
        $subscription['currency'] = $currencies[$subscription['currency_id']]['symbol'];
        $subscription['trigger'] = $subscription['notify_days_before'] ? $subscription['notify_days_before'] : 1;
        $subscription['price'] = number_format($subscription['price'], 2);

       // Create ICS from subscription information
        $uid = uniqid();
        $summary = "Wallos: " . $subscription['name'];
        $description = "Price: {$subscription['currency']}{$subscription['price']}\nCategory: {$subscription['category']}\nPayment Method: {$subscription['payment_method']}\nPayer: {$subscription['payer_user']}\n\nNotes: {$subscription['notes']}";
        
        $dtstart = (new DateTime($subscription['next_payment']))->format('Ymd\THis\Z');
        $dtend = (new DateTime($subscription['next_payment']))->modify('+1 hour')->format('Ymd\THis\Z');
        $location = isset($subscription['url']) ? $subscription['url'] : '';
        $alarm_trigger = '-' . $subscription['trigger'] . 'D';

        $icsContent = <<<ICS
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Your Organization//Your Application//EN
        CALSCALE:GREGORIAN
        METHOD:PUBLISH
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
        END:VCALENDAR
        ICS;

        echo json_encode([
            'success' => true,
            'ics' => $icsContent,
            'name' => $subscription['name']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => "Subscription not found"
        ]);
    }
}
?>