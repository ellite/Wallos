<?php

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

// Get categories
$categories = array();
$query = "SELECT * FROM categories WHERE user_id = :userId ORDER BY 'order' ASC";
$stmt = $db->prepare($query);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
  $categoryId = $row['id'];
  $categories[$categoryId] = $row;
  $categories[$categoryId]['count'] = 0;
  $categoryCost[$categoryId]['cost'] = 0;
  $categoryCost[$categoryId]['name'] = $row['name'];
}

// Get payment methods
$paymentMethods = array();
$query = "SELECT * FROM payment_methods WHERE user_id = :userId AND enabled = 1";
$stmt = $db->prepare($query);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
  $paymentMethodId = $row['id'];
  $paymentMethods[$paymentMethodId] = $row;
  $paymentMethods[$paymentMethodId]['count'] = 0;
  $paymentMethodsCount[$paymentMethodId]['count'] = 0;
  $paymentMethodsCount[$paymentMethodId]['name'] = $row['name'];
}

//Get household members
$members = array();
$query = "SELECT * FROM household WHERE user_id = :userId";
$stmt = $db->prepare($query);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
  $memberId = $row['id'];
  $members[$memberId] = $row;
  $members[$memberId]['count'] = 0;
  $memberCost[$memberId]['cost'] = 0;
  $memberCost[$memberId]['name'] = $row['name'];
}

$activeSubscriptions = 0;
$inactiveSubscriptions = 0;
// Calculate total monthly price
$mostExpensiveSubscription = array();
$mostExpensiveSubscription['price'] = 0;
$amountDueThisMonth = 0;
$totalCostPerMonth = 0;
$totalSavingsPerMonth = 0;
$totalCostsInReplacementsPerMonth = 0;

$statsSubtitleParts = [];
$query = "SELECT name, price, logo, frequency, cycle, currency_id, next_payment, payer_user_id, category_id, payment_method_id, inactive, replacement_subscription_id FROM subscriptions";
$conditions = [];
$params = [];

if (isset($_GET['member'])) {
    $conditions[] = "payer_user_id = :member";
    $params[':member'] = $_GET['member'];
    $statsSubtitleParts[] = $members[$_GET['member']]['name'];
}

if (isset($_GET['category'])) {
    $conditions[] = "category_id = :category";
    $params[':category'] = $_GET['category'];
    $statsSubtitleParts[] = $categories[$_GET['category']]['name'] == "No category" ? translate("no_category", $i18n) : $categories[$_GET['category']]['name'];
}

if (isset($_GET['payment'])) {
    $conditions[] = "payment_method_id = :payment";
    $params[':payment'] = $_GET['payment'];
    $statsSubtitleParts[] = $paymentMethodsCount[$_GET['payment']]['name'];
}

$conditions[] = "user_id = :userId";
$params[':userId'] = $userId;

if (!empty($conditions)) {
    $query .= " WHERE " . implode(' AND ', $conditions);
}

$stmt = $db->prepare($query);
$statsSubtitle = !empty($statsSubtitleParts) ? '(' . implode(', ', $statsSubtitleParts) . ')' : "";

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, SQLITE3_INTEGER);
}

$result = $stmt->execute();
$usesMultipleCurrencies = false;

if ($result) {
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $subscriptions[] = $row;
    }
    if (isset($subscriptions)) {
        $replacementSubscriptions = array();

        foreach ($subscriptions as $subscription) {
            $name = $subscription['name'];
            $price = $subscription['price'];
            $logo = $subscription['logo'];
            $frequency = $subscription['frequency'];
            $cycle = $subscription['cycle'];
            $currency = $subscription['currency_id'];
            if ($currency != $userData['main_currency']) {
                $usesMultipleCurrencies = true;
            }
            $next_payment = $subscription['next_payment'];
            $payerId = $subscription['payer_user_id'];
            $members[$payerId]['count'] += 1;
            $categoryId = $subscription['category_id'];
            $categories[$categoryId]['count'] += 1;
            $paymentMethodId = $subscription['payment_method_id'];
            $paymentMethods[$paymentMethodId]['count'] += 1;
            $inactive = $subscription['inactive'];
            $replacementSubscriptionId = $subscription['replacement_subscription_id'];
            $originalSubscriptionPrice = getPriceConverted($price, $currency, $db, $userId);
            $price = getPricePerMonth($cycle, $frequency, $originalSubscriptionPrice);

            if ($inactive == 0) {
                $activeSubscriptions++;
                $totalCostPerMonth += $price;
                $memberCost[$payerId]['cost'] += $price;
                $categoryCost[$categoryId]['cost'] += $price;
                $paymentMethodsCount[$paymentMethodId]['count'] += 1;
                if ($price > $mostExpensiveSubscription['price']) {
                    $mostExpensiveSubscription['price'] = $price;
                    $mostExpensiveSubscription['name'] = $name;
                    $mostExpensiveSubscription['logo'] = $logo;
                }

                // Calculate ammount due this month
                $nextPaymentDate = DateTime::createFromFormat('Y-m-d', trim($next_payment));
                $tomorrow = new DateTime('tomorrow');
                $endOfMonth = new DateTime('last day of this month');

                if ($nextPaymentDate >= $tomorrow && $nextPaymentDate <= $endOfMonth) {
                    $timesToPay = 1;
                    $daysInMonth = $endOfMonth->diff($tomorrow)->days + 1;
                    $daysRemaining = $endOfMonth->diff($nextPaymentDate)->days + 1;
                    if ($cycle == 1) {
                        $timesToPay = $daysRemaining / $frequency;
                    }
                    if ($cycle == 2) {
                        $weeksInMonth = ceil($daysInMonth / 7);
                        $weeksRemaining = ceil($daysRemaining / 7);
                        $timesToPay = $weeksRemaining / $frequency;
                    }
                    $amountDueThisMonth += $originalSubscriptionPrice * $timesToPay;
                }
            } else {
                $inactiveSubscriptions++;
                $totalSavingsPerMonth += $price;

                // Check if it has a replacement subscription and if it was not already counted
                if ($replacementSubscriptionId && !in_array($replacementSubscriptionId, $replacementSubscriptions)) {
                    $query = "SELECT price, currency_id, cycle, frequency FROM subscriptions WHERE id = :replacementSubscriptionId";
                    $stmt = $db->prepare($query);
                    $stmt->bindValue(':replacementSubscriptionId', $replacementSubscriptionId, SQLITE3_INTEGER);
                    $result = $stmt->execute();
                    $replacementSubscription = $result->fetchArray(SQLITE3_ASSOC);
                    if ($replacementSubscription) {
                        $replacementSubscriptionPrice = getPriceConverted($replacementSubscription['price'], $replacementSubscription['currency_id'], $db, $userId);
                        $replacementSubscriptionPrice = getPricePerMonth($replacementSubscription['cycle'], $replacementSubscription['frequency'], $replacementSubscriptionPrice);
                        $totalCostsInReplacementsPerMonth += $replacementSubscriptionPrice;
                    }
                }

                $replacementSubscriptions[] = $replacementSubscriptionId;
            }

        }

        // Subtract the total cost of replacement subscriptions from the total savings
        $totalSavingsPerMonth -= $totalCostsInReplacementsPerMonth;

        // Calculate yearly price
        $totalCostPerYear = $totalCostPerMonth * 12;

        // Calculate average subscription monthly cost
        if ($activeSubscriptions > 0) {
            $averageSubscriptionCost = $totalCostPerMonth / $activeSubscriptions;
        } else {
            $totalCostPerYear = 0;
            $averageSubscriptionCost = 0;
        }
    } else {
        $totalCostPerYear = 0;
        $averageSubscriptionCost = 0;
    }
}

$showVsBudgetGraph = false;
$vsBudgetDataPoints = [];
if (isset($userData['budget']) && $userData['budget'] > 0) {
    $budget = $userData['budget'];
    $budgetLeft = $budget - $totalCostPerMonth;
    $budgetLeft = $budgetLeft < 0 ? 0 : $budgetLeft;
    $budgetUsed = ($totalCostPerMonth / $budget) * 100;
    $budgetUsed = $budgetUsed > 100 ? 100 : $budgetUsed;
    if ($totalCostPerMonth > $budget) {
        $overBudgetAmount = $totalCostPerMonth - $budget;
    }
    $showVsBudgetGraph = true;
    $vsBudgetDataPoints = [
        [
            "label" => translate('budget_remaining', $i18n),
            "y" => $budgetLeft,
        ],
        [
            "label" => translate('total_cost', $i18n),
            "y" => $totalCostPerMonth,
        ],
    ];
}

$showCantConverErrorMessage = false;
if ($usesMultipleCurrencies) {
    $query = "SELECT api_key FROM fixer WHERE user_id = :userId";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    if ($result->fetchArray(SQLITE3_ASSOC) === false) {
        $showCantConverErrorMessage = true;
    }
}

$query = "SELECT * FROM total_yearly_cost WHERE user_id = :userId";
$stmt = $db->prepare($query);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();

$totalMonthlyCostDataPoints = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $totalMonthlyCostDataPoints[] = [
        "label" => html_entity_decode($row['date']),
        "y" => round($row['cost'] / 12, 2),
    ];
}

$showTotalMonthlyCostGraph = count($totalMonthlyCostDataPoints) > 1;

?>