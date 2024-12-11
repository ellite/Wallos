<?php

require_once __DIR__ . '/../../includes/connect_endpoint_crontabs.php';

if (php_sapi_name() == 'cli') {
    $date = new DateTime('now');
    echo "\n" . $date->format('Y-m-d') . " " . $date->format('H:i:s') . "<br />\n";
}

$currentDate = new DateTime();
$currentDateString = $currentDate->format('Y-m-d');

function getPriceConverted($price, $currency, $database, $userId)
{
  $query = "SELECT rate FROM currencies WHERE id = :currency AND user_id = :userId";
  $stmt = $database->prepare($query);
  $stmt->bindParam(':currency', $currency, SQLITE3_INTEGER);
  $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
  $result = $stmt->execute();

  $exchangeRate = $result->fetchArray(SQLITE3_ASSOC);
  if ($exchangeRate === false) {
    return $price;
  } else {
    $fromRate = $exchangeRate['rate'];
    return $price / $fromRate;
  }
}

// Get all users

$query = "SELECT id, main_currency FROM user";
$stmt = $db->prepare($query);
$result = $stmt->execute();

while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $userId = $row['id'];
    $userCurrencyId = $row['main_currency'];
    $totalYearlyCost = 0;

    $query = "SELECT * FROM subscriptions WHERE user_id = :userId";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
    $resultSubscriptions = $stmt->execute();

    while ($rowSubscriptions = $resultSubscriptions->fetchArray(SQLITE3_ASSOC)) {
        $price = getPriceConverted($rowSubscriptions['price'], $rowSubscriptions['currency_id'], $db, $userId);
        $totalYearlyCost += $price;
    }

    $query = "INSERT INTO total_yearly_cost (user_id, date, cost, currency) VALUES (:userId, :date, :cost, :currency)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
    $stmt->bindParam(':date', $currentDateString, SQLITE3_TEXT);
    $stmt->bindParam(':cost', $totalYearlyCost, SQLITE3_FLOAT);
    $stmt->bindParam(':currency', $userCurrencyId, SQLITE3_INTEGER);

    if ($stmt->execute()) {
        echo "Inserted total yearly cost for user " . $userId . " with cost " . $totalYearlyCost . "<br />\n";
    } else {
        echo "Error inserting total yearly cost for user " . $userId . "<br />\n";
    }
}








?>