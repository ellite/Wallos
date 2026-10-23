<?php
require_once '../../includes/connect_endpoint.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die(json_encode([
        "success" => false,
        "message" => translate('session_expired', $i18n)
    ]));
}

require_once '../../includes/getdbkeys.php';
require_once '../../includes/subscription_payers.php';
require_once '../../includes/subscription_categories.php';

$subscriptions = array();

$query = "SELECT * FROM subscriptions WHERE user_id = :userId";
$stmt = $db->prepare($query);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();

while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $cycle = $cycles[$row['cycle']]['name'];
    $frequency =$row['frequency'];

    $cyclesMap = array(
        'Daily' => 'Days',
        'Weekly' => 'Weeks',
        'Monthly' => 'Months',
        'Yearly' => 'Years'
    );

    if ($frequency == 1) {
        $cyclePrint = $cycle;
    } else {
        $cyclePrint = "Every " . $frequency . " " . $cyclesMap[$cycle];
    }

    $payerIds = getPayersBySubscription($db, [$row])[$row['id']] ?? [];
    $categoryIds = getCategoriesBySubscription($db, [$row])[$row['id']] ?? [];

    $subscriptionDetails = array(
        'Name' => str_replace(',', ' ', $row['name']),
        'Payment Cycle' => $cyclePrint,
        'Next Payment' => $row['next_payment'],
        'Renewal' => $row['auto_renew'] ? 'Automatic' : 'Manual',
        'Category' => str_replace(',', ' ', payerNamesLabel($categoryIds, array_map(fn($c) => $c['name'] ?? '', $categories), ' / ')),
        'Payment Method' => str_replace(',', ' ', $payment_methods[$row['payment_method_id'] ?? '']['name'] ?? ''),
        'Paid By' => str_replace(',', ' ', payerNamesLabel($payerIds, array_map(fn($m) => $m['name'] ?? '', $members), ' / ')),
        'Price' => ($currencies[$row['currency_id'] ?? '']['symbol'] ?? '') . $row['price'],
        'Notes' => str_replace(',', ' ', $row['notes'] ?? ''),
        'URL' => $row['url'],
        'State' => $row['inactive'] ? 'Disabled' : 'Enabled',
        'Notifications' => $row['notify'] ? 'Enabled' : 'Disabled',
        'Cancellation Date' => $row['cancellation_date'],
        'Active' => $row['inactive'] ? 'No' : 'Yes',
    );

    $subscriptions[] = $subscriptionDetails;
}

die(json_encode([
    "success" => true,
    "subscriptions" => $subscriptions
]));


?>
