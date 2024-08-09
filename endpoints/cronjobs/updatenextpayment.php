<?php

require_once 'validate.php';
require_once __DIR__ . '/../../includes/connect_endpoint_crontabs.php';

if (php_sapi_name() == 'cli') {
    $date = new DateTime('now');
    echo "\n" . $date->format('Y-m-d') . " " . $date->format('H:i:s') . "<br />\n";
}

$currentDate = new DateTime();
$currentDateString = $currentDate->format('Y-m-d');

$cycles = array();
$query = "SELECT * FROM cycles";
$result = $db->query($query);
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $cycleId = $row['id'];
    $cycles[$cycleId] = $row;
}

$query = "SELECT id, next_payment, frequency, cycle FROM subscriptions WHERE next_payment < :currentDate";
$stmt = $db->prepare($query);
$stmt->bindValue(':currentDate', $currentDate->format('Y-m-d'));
$result = $stmt->execute();

while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $subscriptionId = $row['id'];
    $nextPaymentDate = new DateTime($row['next_payment']);
    $frequency = $row['frequency'];
    $cycle = $cycles[$row['cycle']]['name'];

    // Calculate the interval to add based on the cycle
    $intervalSpec = "P";
    if ($cycle == 'Daily') {
        $intervalSpec .= "{$frequency}D";
    } elseif ($cycle === 'Weekly') {
        $intervalSpec .= "{$frequency}W";
    } elseif ($cycle === 'Monthly') {
        $intervalSpec .= "{$frequency}M";
    } elseif ($cycle === 'Yearly') {
        $intervalSpec .= "{$frequency}Y";
    }

    $interval = new DateInterval($intervalSpec);

    // Add intervals until the next payment date is in the future
    while ($nextPaymentDate < $currentDate) {
        $nextPaymentDate->add($interval);
    }

    // Update the subscription's next_payment date
    $updateQuery = "UPDATE subscriptions SET next_payment = :nextPaymentDate WHERE id = :subscriptionId";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bindValue(':nextPaymentDate', $nextPaymentDate->format('Y-m-d'));
    $updateStmt->bindValue(':subscriptionId', $subscriptionId);
    $updateStmt->execute();
}

$formattedDate = $currentDate->format('Y-m-d');

$deleteQuery = "DELETE FROM last_update_next_payment_date";
$deleteStmt = $db->prepare($deleteQuery);
$deleteResult = $deleteStmt->execute();

$query = "INSERT INTO last_update_next_payment_date (date) VALUES (:formattedDate)";
$stmt = $db->prepare($query);
$stmt->bindParam(':formattedDate', $currentDateString, SQLITE3_TEXT);
$result = $stmt->execute();

echo "Updated next payment dates";
?>