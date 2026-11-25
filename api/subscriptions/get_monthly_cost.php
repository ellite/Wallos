<?php
require_once __DIR__ . "/../../includes/inputvalidation.php";
/*
This API Endpoint accepts both POST and GET requests.
It receives the following parameters:
- month: the month for which the cost is to be calculated (integer).
- year: the year for which the cost is to be calculated (integer).
- api_key: the API key of the user (string).

It returns a JSON object with the following properties:
- success: whether the request was successful (boolean).
- title: a string with "${month} ${year}" (e.g., "March 2025").
- monthly_cost: a float with the total cost for the given month.
- localized_monthly_cost: a string with the total cost formatted according to the user's locale and currency.
- currency_code: a string with the currency code of the user's main currency.
- currency_symbol: a string with the currency symbol of the user's main currency.
- notes: warning messages or additional information (array).

Example response:
{
  "success": true,
  "title": "March 2025",
  "monthly_cost": "120.24",
  "localized_monthly_cost": "€120.24",
  "currency_code": "EUR",
  "currency_symbol": "€",
  "notes": []
}
*/

require_once '../../includes/connect_endpoint.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER["REQUEST_METHOD"] === "POST" || $_SERVER["REQUEST_METHOD"] === "GET") {
    // if the parameters are not set, return an error

    $apiKey = $_REQUEST['api_key'] ?? $_REQUEST['apiKey'] ?? null;

    if (!$apiKey || !isset($_REQUEST['month']) || !isset($_REQUEST['year'])) {
        $response = [
            "success" => false,
            "title" => "Missing parameters"
        ];
        echo json_encode($response);
        exit;
    }

    $month = $_REQUEST['month'];
    $year = $_REQUEST['year'];

    $sql = "SELECT * FROM user WHERE api_key = :apiKey";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':apiKey', $apiKey);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);
    // If the user is not found or the API key is invalid, return an error
    if (!$user) {
        echo json_encode([
            "success" => false,
            "title" => "Invalid API key",
            "notes" => ["User not found or API key invalid."]
        ]);
        exit;
    }

    $sql = "SELECT * FROM last_exchange_update";
    $result = $db->query($sql);
    $lastExchangeUpdate = $result->fetchArray(SQLITE3_ASSOC);

    $userId = $user['id'];
    $userCurrencyId = $user['main_currency'];
    $needsCurrencyConversion = false;
    $canConvertCurrency = empty($lastExchangeUpdate['date']) ? false : true;

    $sql = "SELECT * FROM currencies WHERE id = :currencyId";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':currencyId', $userCurrencyId);
    $result = $stmt->execute();
    $currency = $result->fetchArray(SQLITE3_ASSOC);
    $currency_code = $currency['code'];
    $currency_symbol = $currency['symbol'];


    $title = date('F Y', strtotime($year . '-' . $month . '-01'));
    $monthlyCost = 0;
    $notes = [];
    $currencies = [];

    $sql = "SELECT * FROM subscriptions WHERE user_id = :userId AND inactive = 0";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':userId', $userId);
    $result = $stmt->execute();
    $subscriptions = [];
    while ($subscription = $result->fetchArray(SQLITE3_ASSOC)) {
        $subscriptions[] = $subscription;
        if ($subscription['currency_id'] !== $userCurrencyId) {
            $needsCurrencyConversion = true;
        }
    }

    if ($needsCurrencyConversion) {
        if (!$canConvertCurrency) {
            $notes[] = "You are using multiple currencies, but the exchange rates have not been updated yet. Please check your Fixer API Key.";
        } else {
            $sql = "SELECT * FROM currencies WHERE user_id = :userId";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':userId', $userId);
            $result = $stmt->execute();
            while ($currency = $result->fetchArray(SQLITE3_ASSOC)) {
                $currencies[$currency['id']] = $currency['rate'];
            }
        }
    }

    // Calculate the monthly cost based on the next_payment_date, payment cycle, and payment frequency
    foreach ($subscriptions as $subscription) {
        if (!wallos_valid_payment_date($subscription['next_payment'])) {
            continue;
        }
        $nextPaymentDate = strtotime($subscription['next_payment']);
        $cycle = (int)$subscription['cycle'];
        $frequency = $subscription['frequency'];
        $startOfMonth = strtotime($year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01');
        $endOfMonth = strtotime('+1 month', $startOfMonth);
        if ($cycle === 5) {
            $startDate = $nextPaymentDate;
        } else {
            if (!validate_frequency($frequency) || !validate_frequency($subscription['cycle'], 1, 4)) {
                continue;
            }
            $units = [1 => 'days', 2 => 'weeks', 3 => 'months', 4 => 'years'];
            $incrementString = '+' . (int)$frequency . ' ' . $units[$cycle];
            $decrementString = '-' . (int)$frequency . ' ' . $units[$cycle];
            $startDate = $nextPaymentDate;
            $safety = 0;
            while ($startDate > $startOfMonth) {
                $previous = $startDate;
                $startDate = strtotime($decrementString, $startDate);
                if ($startDate === false || $startDate >= $previous || ++$safety > 10000) {
                    continue 2;
                }
            }
        }
        $rowCost = 0;
        $safety = 0;
        // Calculate the monthly cost
        for ($date = $startDate; $date < $endOfMonth;) {
            if (date('Y-m', $date) == $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT)) {
                $price = $subscription['price'];
                if (
                    $userCurrencyId !== $subscription['currency_id']
                    && $canConvertCurrency
                    && isset($currencies[$userCurrencyId], $currencies[$subscription['currency_id']])
                    && (float)$currencies[$subscription['currency_id']] !== 0.0
                ) {
                    $price *= $currencies[$userCurrencyId] / $currencies[$subscription['currency_id']];
                }
                $rowCost += $price;
            }
            if ($cycle === 5) {
                break;
            }
            $previous = $date;
            $date = strtotime($incrementString, $date);
            if ($date === false || $date <= $previous || ++$safety > 10000) {
                continue 2;
            }
        }
        $monthlyCost += $rowCost;
    }

    $formatter = new NumberFormatter('en_US', NumberFormatter::CURRENCY);
    $localizedMonthlyCost = $formatter->formatCurrency($monthlyCost, $currency_code);

    echo json_encode([
        'success' => true,
        'title' => $title,
        'monthly_cost' => number_format($monthlyCost, 2),
        'localized_monthly_cost' => $localizedMonthlyCost,
        'currency_code' => $currency_code,
        'currency_symbol' => $currency_symbol,
        'notes' => $notes
    ], JSON_UNESCAPED_UNICODE);

}
?>