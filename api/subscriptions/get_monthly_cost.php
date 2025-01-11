<?php
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

header('Content-Type: application/json, charset=UTF-8');

if ($_SERVER["REQUEST_METHOD"] === "POST" || $_SERVER["REQUEST_METHOD"] === "GET") {
    // if the parameters are not set, return an error

    if (!isset($_REQUEST['month']) || !isset($_REQUEST['year']) || !isset($_REQUEST['api_key'])) {
        $response = [
            "success" => false,
            "title" => "Missing parameters"
        ];
        echo json_encode($response);
        exit;
    }

    $month = $_REQUEST['month'];
    $year = $_REQUEST['year'];
    $apiKey = $_REQUEST['api_key'];

    $sql = "SELECT * FROM user WHERE api_key = :apiKey";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':apiKey', $apiKey);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);

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
            $currencies = [];
            while ($currency = $result->fetchArray(SQLITE3_ASSOC)) {
                $currencies[$currency['id']] = $currency['rate'];
            }
        }
    }

    // Calculate the monthly cost based on the next_payment_date, payment cycle, and payment frequency
    foreach ($subscriptions as $subscription) {
        $nextPaymentDate = strtotime($subscription['next_payment']);
        $cycle = $subscription['cycle']; // Integer from 1 to 4
        $frequency = $subscription['frequency'];

        // Determine the strtotime increment string based on cycle
        switch ($cycle) {
            case 1: // Days
                $incrementString = "+{$frequency} days";
                break;
            case 2: // Weeks
                $incrementString = "+{$frequency} weeks";
                break;
            case 3: // Months
                $incrementString = "+{$frequency} months";
                break;
            case 4: // Years
                $incrementString = "+{$frequency} years";
                break;
            default:
                $incrementString = "+{$frequency} months"; // Default case, if needed
        }

        // Calculate the start of the month
        $startOfMonth = strtotime($year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01');

        // Find the first payment date of the month by moving backwards
        $startDate = $nextPaymentDate;
        while ($startDate > $startOfMonth) {
            $startDate = strtotime("-" . $incrementString, $startDate);
        }

        // Calculate the monthly cost
        for ($date = $startDate; $date <= strtotime("+1 month", $startOfMonth); $date = strtotime($incrementString, $date)) {
            if (date('Y-m', $date) == $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT)) {
                $price = $subscription['price'];
                if ($userCurrencyId !== $subscription['currency_id']) {
                    $price *= $currencies[$userCurrencyId] / $currencies[$subscription['currency_id']];
                }
                $monthlyCost += $price;
            }
        }
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