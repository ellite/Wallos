<?php
require_once 'validate.php';
require_once __DIR__ . '/../../includes/connect_endpoint_crontabs.php';
require_once __DIR__ . '/../../includes/currency_rates.php';
require_once __DIR__ . '/../../includes/variable_pricing.php';

require 'settimezone.php';

if (php_sapi_name() == 'cli') {
    $date = new DateTime('now');
    echo "\n" . $date->format('Y-m-d') . " " . $date->format('H:i:s') . "<br />\n";
}

$currentDate = new DateTime();
$currentDateString = $currentDate->format('Y-m-d');

function getPricePerMonth($cycle, $frequency, $price)
{
  switch ($cycle) {
    case 1:
      $numberOfPaymentsPerMonth = (30 / $frequency);
      return $price * $numberOfPaymentsPerMonth;
    case 2:
      $numberOfPaymentsPerMonth = (4.35 / $frequency);
      return $price * $numberOfPaymentsPerMonth;
    case 3:
      $numberOfPaymentsPerMonth = (1 / $frequency);
      return $price * $numberOfPaymentsPerMonth;
    case 4:
      $numberOfMonths = (12 * $frequency);
      return $price / $numberOfMonths;
  }
}

function getPriceConverted($price, $currency, $database, $userId)
{
  return wallos_convert_price($price, $currency, $database, $userId);
}

// Get all users

$query = "SELECT id, main_currency FROM user";
$stmt = $db->prepare($query);
$result = $stmt->execute();

while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $userId = $row['id'];
    $userCurrencyId = $row['main_currency'];
    $totalYearlyCost = 0;
    $priceHistoryIndex = load_price_history_index($db, (int) $userId);
    $currentYearMonth = date('Y-m');

    $query = "SELECT * FROM subscriptions WHERE user_id = :userId AND inactive = 0";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
    $resultSubscriptions = $stmt->execute();

    while ($rowSubscriptions = $resultSubscriptions->fetchArray(SQLITE3_ASSOC)) {
        $rawPrice = effective_subscription_price($rowSubscriptions, $currentYearMonth, $priceHistoryIndex);
        $originalSubscriptionPrice = getPriceConverted($rawPrice, $rowSubscriptions['currency_id'], $db, $userId);
        if (subscription_has_variable_price($rowSubscriptions) && (int) $rowSubscriptions['cycle'] !== 5) {
            $price = $originalSubscriptionPrice * 12;
        } else {
            $price = getPricePerMonth($rowSubscriptions['cycle'], $rowSubscriptions['frequency'], $originalSubscriptionPrice) * 12;
        }
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