<?php

require_once 'validate.php';
require_once __DIR__ . '/../../includes/connect_endpoint_crontabs.php';

require 'settimezone.php';
require_once __DIR__ . '/../../includes/payment_schedule.php';

$date = new DateTime('now');
echo "\n" . $date->format('Y-m-d') . " " . $date->format('H:i:s') . "<br />\n";
echo $timezone . "<br />\n";

$currentDate = new DateTime();
$currentDateString = $currentDate->format('Y-m-d');

$cycles = array();
$query = "SELECT * FROM cycles";
$result = $db->query($query);
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $cycleId = $row['id'];
    $cycles[$cycleId] = $row;
}

$query = "SELECT id, next_payment, frequency, cycle FROM subscriptions WHERE next_payment < :currentDate AND auto_renew = 1 AND inactive = 0 AND cycle IN (1, 2, 3, 4)";
$stmt = $db->prepare($query);
$stmt->bindValue(':currentDate', $currentDate->format('Y-m-d'));
$result = $stmt->execute();

while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $subscriptionId = $row['id'];
    try {
        $nextPaymentDate = wallos_advance_payment($row['next_payment'], $row['frequency'], $row['cycle'], $currentDate);
    // Update the subscription's next_payment date
    $updateQuery = "UPDATE subscriptions SET next_payment = :nextPaymentDate WHERE id = :subscriptionId";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bindValue(':nextPaymentDate', $nextPaymentDate->format('Y-m-d'));
    $updateStmt->bindValue(':subscriptionId', $subscriptionId);
    $updateStmt->execute();
    } catch (Throwable $e) {
        error_log('Skipping subscription ' . $subscriptionId . ': ' . $e->getMessage());
        continue;
    }
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