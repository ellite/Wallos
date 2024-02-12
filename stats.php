<?php
  require_once 'includes/header.php';

  function getPricePerMonth($cycle, $frequency, $price) {
    switch ($cycle) {
    case 1:
        $numberOfPaymentsPerMonth = (30 / $frequency); 
        return $price * $numberOfPaymentsPerMonth;
        break;
    case 2:
        $numberOfPaymentsPerMonth = (4.35 / $frequency);
        return $price * $numberOfPaymentsPerMonth;
        break;
    case 3:
        $numberOfPaymentsPerMonth = (1 / $frequency);
        return $price * $numberOfPaymentsPerMonth;
        break;
    case 4:
      $numberOfMonths = (12 * $frequency);
      return $price / $numberOfMonths;
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
          return $price / $fromRate;
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

// Get categories
$categories = array();
$query = "SELECT * FROM categories";
$result = $db->query($query);
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $categoryId = $row['id'];
    $categories[$categoryId] = $row;
    $categoryCost[$categoryId]['cost'] = 0;
    $categoryCost[$categoryId]['name'] = $row['name'];
}

// Get code of main currency to display on statistics
$query = "SELECT c.code
          FROM currencies c
          INNER JOIN user u ON c.id = u.main_currency
          WHERE u.id = 1";
$stmt = $db->prepare($query);
$result = $stmt->execute();
$row = $result->fetchArray(SQLITE3_ASSOC);
$code = $row['code'];


// Calculate active subscriptions
$query = "SELECT COUNT(*) AS active_subscriptions FROM subscriptions WHERE activated = true";
$stmt = $db->prepare($query);
$stmt->bindParam(':criteria', $criteria, SQLITE3_INTEGER);
$result = $stmt->execute();
$row = $result->fetchArray(SQLITE3_ASSOC);
$activeSubscriptions = $row['active_subscriptions'];

// Calculate inactive subscriptions
$query = "SELECT COUNT(*) AS inactive_subscriptions FROM subscriptions WHERE activated = false";
$stmt = $db->prepare($query);
$stmt->bindParam(':inactive', $inactive, SQLITE3_INTEGER);
$result = $stmt->execute();
$row = $result->fetchArray(SQLITE3_ASSOC);
$inactiveSubscriptions = $row['inactive_subscriptions'];

// Calculate total monthly price
$mostExpensiveSubscription = 0;
$amountDueThisMonth = 0;
$totalCostPerMonth = 0;

$query = "SELECT name, price, frequency, cycle, currency_id, next_payment, payer_user_id, category_id FROM subscriptions WHERE activated = true";
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
      $categoryId = $subscription['category_id'];
      $originalSubscriptionPrice = getPriceConverted($price, $currency, $db);
      $price = getPricePerMonth($cycle, $frequency, $originalSubscriptionPrice);
      $totalCostPerMonth += $price;
      $memberCost[$payerId]['cost'] += $price;
      $categoryCost[$categoryId]['cost'] += $price;
      if ($price > $mostExpensiveSubscription) {
        $mostExpensiveSubscription = $price;
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

    }
  
    // Calculate yearly price
    $totalCostPerYear = $totalCostPerMonth * 12;
  
    // Calculate average subscription monthly cost
    $averageSubscriptionCost = $totalCostPerMonth / $activeSubscriptions;
  } else {
    $totalCostPerYear = 0;
    $averageSubscriptionCost = 0;
  }
}
 
?>
<section class="contain">
  <h2><?= translate('general_statistics', $i18n) ?></h2>
  <div class="statistics">
    <div class="statistic">
      <span><?= $activeSubscriptions ?></span>
      <div class="title"><?= translate('active_subscriptions', $i18n) ?></div>
    </div>
      <?php  if ($inactiveSubscriptions > 0) {
      ?>
      <div class="statistic">
          <span><?= $inactiveSubscriptions ?></span>
          <div class="title"><?= translate('inactive_subscriptions', $i18n) ?></div>
      </div>
      <?php
      }
      ?>
    <div class="statistic">
      <span><?= CurrencyFormatter::format($totalCostPerMonth, $code) ?></span>
      <div class="title"><?= translate('monthly_cost', $i18n) ?></div>
    </div>
    <div class="statistic">
      <span><?= CurrencyFormatter::format($totalCostPerYear, $code) ?></span>
      <div class="title"><?= translate('yearly_cost', $i18n) ?></div>
    </div>
    <div class="statistic">
      <span><?= CurrencyFormatter::format($averageSubscriptionCost, $code) ?></span>
      <div class="title"><?= translate('average_monthly', $i18n) ?></div>
    </div>
    <div class="statistic">
      <span><?= CurrencyFormatter::format($mostExpensiveSubscription, $code) ?></span>
      <div class="title"><?= translate('most_expensive', $i18n) ?></div>
    </div>
    <div class="statistic">
      <span><?= CurrencyFormatter::format($amountDueThisMonth, $code) ?></span>
      <div class="title"><?= translate('amount_due', $i18n) ?></div>
    </div>
    <?php
      $numberOfElements = 6;
      if (($numberOfElements + 1) % 3 == 0) {
        ?>
          <div class="statistic empty"></div>
        <?php
      }
    ?>  
  </div>
  <h2><?= translate('split_views', $i18n) ?></h2>
  <div class="graphs">
      <?php
        $categoryDataPoints = [];
        foreach ($categoryCost as $category) {
          if ($category['cost'] != 0) {
            $categoryDataPoints[] = [
                "label" => $category['name'],
                "y"     => $category["cost"],
            ];
          }
        }

        $showCategoryCostGraph = count($categoryDataPoints) > 1;

        $memberDataPoints = [];
        foreach ($memberCost as $member) {
          if ($member['cost'] != 0) {
            $memberDataPoints[] = [
                "label" => $member['name'],
                "y"     => $member["cost"],
            ];
            
          }
        }

        $showMemberCostGraph = count($memberDataPoints) > 1;

        if ($showMemberCostGraph) {
          ?>
          <section class="graph">
            <header>
              <?= translate('household_split', $i18n) ?>
              <div class="sub-header">(<?= translate('monthly_cost', $i18n) ?>)</div>
            </header>
            <canvas id="memberSplitChart"></canvas>
        </section>
          <?php
        }
      
        if ($showCategoryCostGraph) {
          ?>
          <section class="graph">
            <header>
              <?= translate('category_split', $i18n) ?>
              <div class="sub-header">(<?= translate('monthly_cost', $i18n) ?>)</div>
            </header>
            <canvas id="categorySplitChart" style="height: 370px; width: 100%;"></canvas>
          </section>
          <?php
        }

      ?>
  </div>
</section>
<?php 
  if ($showCategoryCostGraph || $showMemberCostGraph) {
    ?>
      <script src="scripts/libs/chart.js"></script>
      <script type="text/javascript">
      window.onload = function() {
        loadGraph("categorySplitChart", <?php echo json_encode($categoryDataPoints, JSON_NUMERIC_CHECK); ?>, "<?= $code ?>", <?= $showCategoryCostGraph ?>);
        loadGraph("memberSplitChart", <?php echo json_encode($memberDataPoints, JSON_NUMERIC_CHECK); ?>, "<?= $code ?>", <?= $showMemberCostGraph ?>);
      }
    </script>
    <?php
  }
?>
<script src="scripts/stats.js?<?= $version ?>"></script>
<?php
  require_once 'includes/footer.php';
?>