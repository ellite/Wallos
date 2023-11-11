<?php
  require_once 'includes/header.php';

  function getPricePerMonth($cycle, $frequency, $price) {
    switch ($cycle) {
    case 1:
        echo "1";
        $numberOfPaymentsPerMonth = (30 / $frequency); 
        return $price * $numberOfPaymentsPerMonth;
        break;
    case 2:
        echo "2";
        $numberOfPaymentsPerMonth = (4.35 / $frequency);
        return $price * $numberOfPaymentsPerMonth;
        break;
    case 3:
        echo "3";
        $numberOfPaymentsPerMonth = (1 / $frequency);
        return $price * $numberOfPaymentsPerMonth;
        break;
    case 4:
        if ($frequency === 1) {
          return $price / 12;
          break;
        }
        $numberOfPaymentsPerMonth = (0.0833310 / $frequency);
        return $price * $numberOfPaymentsPerMonth;
        break;  
    }
  }


  function getPriceConverted($price, $currency, $database) {
      $query = "SELECT rate FROM currencies WHERE id = :currency";
      $stmt = $database->prepare($query);
      $stmt->bindParam(':currency', $currency, SQLITE3_INTEGER);
      $result = $stmt->execute();
      
      $exchangeRate = $result->fetchArray(SQLITE3_ASSOC);
      if ($exchangeRate === false) {
          return $price;
      } else {
          $fromRate = $exchangeRate['rate'];
          return number_format($price * $fromRate, 2, ".", "");
      }
  }

//Get household members
$members = array();
$query = "SELECT * FROM household";
$result = $db->query($query);
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $memberId = $row['id'];
    $members[$memberId] = $row;
    $memberCost[$memberId]['cost'] = 0;
    $memberCost[$memberId]['name'] = $row['name'];
}  

// Get symbol of main currency to display on statistics
$query = "SELECT c.symbol
          FROM currencies c
          INNER JOIN user u ON c.id = u.main_currency
          WHERE u.id = 1";
$stmt = $db->prepare($query);
$result = $stmt->execute();
$row = $result->fetchArray(SQLITE3_ASSOC);
$symbol = $row['symbol'];


// Calculate active subscriptions
$query = "SELECT COUNT(*) AS active_subscriptions FROM subscriptions";
$stmt = $db->prepare($query);
$stmt->bindParam(':criteria', $criteria, SQLITE3_INTEGER);
$result = $stmt->execute();
$row = $result->fetchArray(SQLITE3_ASSOC);
$activeSubscriptions = $row['active_subscriptions'];

// Calculate total monthly price
$mostExpensiveSubscription = 0;
$amountDueThisMonth = 0;
$totalCostPerMonth = 0;
$query = "SELECT name, price, frequency, cycle, currency_id, next_payment, payer_user_id FROM subscriptions";
$result = $db->query($query);
if ($result) {
  while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $subscriptions[] = $row;
  }
  if (isset($subscriptions)) {
    foreach ($subscriptions as $subscription) {
      $name = $subscription['name'];
      $price = $subscription['price'];
      $frequency = $subscription['frequency'];
      $cycle = $subscription['cycle'];
      $currency = $subscription['currency_id'];
      $next_payment = $subscription['next_payment'];
      $payerId = $subscription['payer_user_id'];
      $originalSubscriptionPrice = getPriceConverted($price, $currency, $db);
      $price = getPricePerMonth($cycle, $frequency, $originalSubscriptionPrice);
      $totalCostPerMonth += $price;
      $memberCost[$payerId]['cost'] += $price;
      if ($price > $mostExpensiveSubscription) {
        $mostExpensiveSubscription = $price;
      }

      $memberCost[$payerId]['cost'] = number_format($memberCost[$payerId]['cost'], 2, ".", "");
      if ((int)$memberCost[$payerId]['cost'] == $memberCost[$payerId]['cost']) {
        $memberCost[$payerId]['cost'] = (int)$memberCost[$payerId]['cost'];
      }
      
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
    }
    $mostExpensiveSubscription = number_format($mostExpensiveSubscription, 2, ".", "");
  
    // Calculate yearly price
    $totalCostPerYear = $totalCostPerMonth * 12;
    $totalCostPerYear = number_format($totalCostPerYear, 2, ".", "");
    if ((int)$totalCostPerYear == $totalCostPerYear) {
      $totalCostPerYear = (int)$totalCostPerYear;
    }
  
    // Calculate average subscription monthly cost
    $averageSubscriptionCost = $totalCostPerMonth / $activeSubscriptions;
    $averageSubscriptionCost = number_format($averageSubscriptionCost, 2, ".", "");
    if ((int)$averageSubscriptionCost == $averageSubscriptionCost) {
      $averageSubscriptionCost = (int)$averageSubscriptionCost;
    }

    $totalCostPerMonth = number_format($totalCostPerMonth, 2, ".", "");
    if ((int)$totalCostPerMonth == $totalCostPerMonth) {
      $totalCostPerMonth = (int)$totalCostPerMonth;
    }
  } else {
    $totalCostPerYear = 0;
    $averageSubscriptionCost = 0;
  }
}
 
?>
<section class="contain">
  <div class="statistics">
    <div class="statistic">
      <span><?= $activeSubscriptions ?></span>
      <div class="title">Active Subscriptions</div>
    </div>
    <div class="statistic">
      <span><?= $totalCostPerMonth ?><?= $symbol ?></span>
      <div class="title">Monthly Cost</div>
    </div>
    <div class="statistic">
      <span><?= $totalCostPerYear ?><?= $symbol ?></span>
      <div class="title">Yearly Cost</div>
    </div>
    <div class="statistic">
      <span><?= $averageSubscriptionCost ?><?= $symbol ?></span>
      <div class="title">Average Monthly Subscription Cost</div>
    </div>
    <div class="statistic">
      <span><?= $mostExpensiveSubscription ?><?= $symbol ?></span>
      <div class="title">Most Expensive Subscription Cost</div>
    </div>
    <div class="statistic">
      <span><?= $amountDueThisMonth ?><?= $symbol ?></span>
      <div class="title">Amount due this month</div>
    </div>
    <?php
      $numberOfElements = 6;
      foreach($memberCost as $member) {
        ?>
          <div class="statistic">
            <span><?= $member['cost'] ?><?= $symbol ?></span>
            <div class="title"><?= $member['name'] ?>'s monthly costs</div>
          </div>
        <?php
        $numberOfElements++;
      }
      if (($numberOfElements + 1) % 3 == 0) {
        ?>
          <div class="statistic empty"><div>
        <?php
      }
    ?>
  </div>
</section>
<?php
  require_once 'includes/footer.php';
?>