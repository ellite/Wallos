<?php
require_once '../../includes/connect_endpoint.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die(json_encode([
        "success" => false,
        "message" => translate('session_expired', $i18n)
    ]));
}

require_once '../../includes/getdbkeys.php';

$subscriptions = array();

$query = "SELECT * FROM subscriptions WHERE user_id = :userId";
$stmt = $db->prepare($query);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();

while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $subscriptionDetails = array(
        'Name' => str_replace(',', ' ', $row['name']),
        'Next Payment' => $row['next_payment'],
        'Categpry' => str_replace(',', ' ', $categories[$row['category_id']]['name']),
        'Payment Method' => str_replace(',', ' ', $payment_methods[$row['payment_method_id']]['name']),
        'Paid By' => str_replace(',', ' ', $members[$row['payer_user_id']]['name']),
        'Price' => $currencies[$row['currency_id']]['symbol'] . $row['price'],
        'Notes' => str_replace(',', ' ', $row['notes']),
        'URL' => $row['url'],
        'State' => $row['inactive'] ? 'Disabled' : 'Enabled',
        'Notifications' => $row['notify'] ? 'Enabled' : 'Disabled',
        'Cancellation Date' => $row['cancellation_date']
    );

    $subscriptions[] = $subscriptionDetails;
}

die(json_encode([
    "success" => true,
    "subscriptions" => $subscriptions
]));


?>