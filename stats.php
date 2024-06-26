<?php
require_once 'includes/header.php';

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

//Get household members
$members = array();
$query = "SELECT * FROM household WHERE user_id = :userId";
$stmt = $db->prepare($query);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
  $memberId = $row['id'];
  $members[$memberId] = $row;
  $memberCost[$memberId]['cost'] = 0;
  $memberCost[$memberId]['name'] = $row['name'];
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
  $categoryCost[$categoryId]['cost'] = 0;
  $categoryCost[$categoryId]['name'] = $row['name'];
}

// Get payment methods
$paymentMethodCount = array();
$query = "SELECT * FROM payment_methods WHERE user_id = :userId AND enabled = 1";
$stmt = $db->prepare($query);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
  $paymentMethodId = $row['id'];
  $paymentMethodCount[$paymentMethodId] = $row;
  $paymentMethodCount[$paymentMethodId]['count'] = 0;
  $paymentMethodCount[$paymentMethodId]['name'] = $row['name'];
}

// Get code of main currency to display on statistics
$query = "SELECT c.code
          FROM currencies c
          INNER JOIN user u ON c.id = u.main_currency
          WHERE u.id = :userId";
$stmt = $db->prepare($query);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$row = $result->fetchArray(SQLITE3_ASSOC);
$code = $row['code'];

$activeSubscriptions = 0;
$inactiveSubscriptions = 0;
// Calculate total monthly price
$mostExpensiveSubscription = array();
$mostExpensiveSubscription['price'] = 0;
$amountDueThisMonth = 0;
$totalCostPerMonth = 0;
$totalSavingsPerMonth = 0;

$statsSubtitleParts = [];
$query = "SELECT name, price, logo, frequency, cycle, currency_id, next_payment, payer_user_id, category_id, payment_method_id, inactive FROM subscriptions";
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
  $statsSubtitleParts[] = $paymentMethodCount[$_GET['payment']]['name'];
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
if ($result) {
  while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $subscriptions[] = $row;
  }
  if (isset($subscriptions)) {
    foreach ($subscriptions as $subscription) {
      $name = $subscription['name'];
      $price = $subscription['price'];
      $logo = $subscription['logo'];
      $frequency = $subscription['frequency'];
      $cycle = $subscription['cycle'];
      $currency = $subscription['currency_id'];
      $next_payment = $subscription['next_payment'];
      $payerId = $subscription['payer_user_id'];
      $categoryId = $subscription['category_id'];
      $paymentMethodId = $subscription['payment_method_id'];
      $inactive = $subscription['inactive'];
      $originalSubscriptionPrice = getPriceConverted($price, $currency, $db, $userId);
      $price = getPricePerMonth($cycle, $frequency, $originalSubscriptionPrice);

      if ($inactive == 0) {
        $activeSubscriptions++;
        $totalCostPerMonth += $price;
        $memberCost[$payerId]['cost'] += $price;
        $categoryCost[$categoryId]['cost'] += $price;
        $paymentMethodCount[$paymentMethodId]['count'] += 1;
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
      }

    }

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

if (isset($userData['budget']) && $userData['budget'] > 0) {
  $budget = $userData['budget'];
  $budgetLeft = $budget - $totalCostPerMonth;
  $budgetLeft = $budgetLeft < 0 ? 0 : $budgetLeft;
  $budgetUsed = ($totalCostPerMonth / $budget) * 100;
  $budgetUsed = $budgetUsed > 100 ? 100 : $budgetUsed;
  if ($totalCostPerMonth > $budget) {
    $overBudgetAmount = $totalCostPerMonth - $budget;
  }
}

$numberOfElements = 6;
?>
<section class="contain">
  <div class="split-header">
    <h2>
      <?= translate('general_statistics', $i18n) ?> <span class="header-subtitle"><?= $statsSubtitle ?></span>
    </h2>
    <div class="filtermenu">
      <button class="button secondary-button" id="filtermenu-button">
        <i class="fa-solid fa-filter"></i>
        <?= translate("filter", $i18n) ?>
      </button>
      <div class="filtermenu-content">
        <?php
        if (count($members) > 1) {
          ?>
          <div class="filtermenu-submenu">
            <div class="filter-title" onClick="toggleSubMenu('member')"><?= translate("member", $i18n) ?></div>
            <div class="filtermenu-submenu-content" id="filter-member">
              <?php
              foreach ($members as $member) {
                $selectedClass = '';
                if (isset($_GET['member']) && $_GET['member'] == $member['id']) {
                  $selectedClass = 'selected';
                }
                ?>
                <div class="filter-item <?= $selectedClass ?>" data-memberid="<?= $member['id'] ?>"><?= $member['name'] ?>
                </div>
                <?php
              }
              ?>
            </div>
          </div>
          <?php
        }
        ?>
        <?php
        if (count($categories) > 1) {
          ?>
          <div class="filtermenu-submenu">
            <div class="filter-title" onClick="toggleSubMenu('category')"><?= translate("category", $i18n) ?></div>
            <div class="filtermenu-submenu-content" id="filter-category">
              <?php
              foreach ($categories as $category) {
                if ($category['name'] == "No category") {
                  $category['name'] = translate("no_category", $i18n);
                }
                $selectedClass = '';
                if (isset($_GET['category']) && $_GET['category'] == $category['id']) {
                  $selectedClass = 'selected';
                }
                ?>
                <div class="filter-item <?= $selectedClass ?>" data-categoryid="<?= $category['id'] ?>">
                  <?= $category['name'] ?></div>
                <?php
              }
              ?>
            </div>
          </div>
          <?php
        }
        ?>
        <?php
        if (count($paymentMethodCount) > 1) {
          ?>
          <div class="filtermenu-submenu">
            <div class="filter-title" onClick="toggleSubMenu('payment')"><?= translate("payment_method", $i18n) ?></div>
            <div class="filtermenu-submenu-content" id="filter-payment">
              <?php
              foreach ($paymentMethodCount as $payment) {
                $selectedClass = '';
                if (isset($_GET['payment']) && $_GET['payment'] == $payment['id']) {
                  $selectedClass = 'selected';
                }
                ?>
                <div class="filter-item <?= $selectedClass ?>" data-paymentid="<?= $payment['id'] ?>">
                  <?= $payment['name'] ?></div>
                <?php
              }
              ?>
            </div>
          </div>
          <?php
        }
        ?>
        <?php
        if (isset($_GET['member']) || isset($_GET['category']) || isset($_GET['payment'])) {
          ?>
          <div class="filtermenu-submenu">
            <div class="filter-title filter-clear" onClick="clearFilters()">
              <i class="fa-solid fa-times-circle"></i> <?= translate("clear", $i18n) ?>
            </div>
          </div>
          <?php
        }
        ?>
      </div>
    </div>
  </div>
  </div>
  </div>
  <div class="statistics">
    <div class="statistic">
      <span><?= $activeSubscriptions ?></span>
      <div class="title"><?= translate('active_subscriptions', $i18n) ?></div>
    </div>
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
    <div class="statistic short">
      <span><?= CurrencyFormatter::format($mostExpensiveSubscription['price'], $code) ?></span>
      <div class="title"><?= translate('most_expensive', $i18n) ?></div>
      <?php
      if (isset($mostExpensiveSubscription['logo']) && $mostExpensiveSubscription['logo'] != '') {
        ?>
        <div class="subtitle">
          <img src="images/uploads/logos/<?= $mostExpensiveSubscription['logo'] ?>"
            alt="<?= $mostExpensiveSubscription['name'] ?>" title="<?= $mostExpensiveSubscription['name'] ?>" />
        </div>
        <?php
      } else if (isset($mostExpensiveSubscription['name']) && $mostExpensiveSubscription['name'] != '') {
        ?>
          <div class="subtitle"><?= $mostExpensiveSubscription['name'] ?></div>
        <?php
      }
      ?>
    </div>
    <div class="statistic">
      <span><?= CurrencyFormatter::format($amountDueThisMonth, $code) ?></span>
      <div class="title"><?= translate('amount_due', $i18n) ?></div>
    </div>
    <?php
    if (isset($budgetUsed)) {
      $numberOfElements += 1;
      ?>
      <div class="statistic">
        <span><?= number_format($budgetUsed, 2) ?>%</span>
        <div class="title"><?= translate('percentage_budget_used', $i18n) ?></div>
      </div>
      <?php
    }
    if (isset($budgetLeft)) {
      $numberOfElements += 1;
      ?>
      <div class="statistic">
        <span><?= CurrencyFormatter::format($budgetLeft, $code) ?></span>
        <div class="title"><?= translate('budget_remaining', $i18n) ?></div>
      </div>
      <?php
    }
    if (isset($overBudgetAmount)) {
      $numberOfElements += 1;
      ?>
      <div class="statistic">
        <span><?= CurrencyFormatter::format($overBudgetAmount, $code) ?></span>
        <div class="title"><?= translate('amount_over_budget', $i18n) ?></div>
      </div>
      <?php
    }
    if ($inactiveSubscriptions > 0) {
      $numberOfElements += 3;
      ?>
      <div class="statistic">
        <span><?= $inactiveSubscriptions ?></span>
        <div class="title"><?= translate('inactive_subscriptions', $i18n) ?></div>
      </div>
      <div class="statistic">
        <span><?= CurrencyFormatter::format($totalSavingsPerMonth, $code) ?></span>
        <div class="title"><?= translate('monthly_savings', $i18n) ?></div>
      </div>
      <div class="statistic">
        <span><?= CurrencyFormatter::format($totalSavingsPerMonth * 12, $code) ?></span>
        <div class="title"><?= translate('yearly_savings', $i18n) ?></div>
      </div>
      <?php
    }

    if (($numberOfElements + 1) % 3 == 0) {
      ?>
      <div class="statistic empty"></div>
      <?php
    }
    ?>
  </div>
  <?php
  $categoryDataPoints = [];
  if (isset($categoryCost)) {
    foreach ($categoryCost as $category) {
      if ($category['cost'] != 0) {
        $categoryDataPoints[] = [
          "label" => $category['name'],
          "y" => $category["cost"],
        ];
      }
    }
  }

  $showCategoryCostGraph = count($categoryDataPoints) > 1;

  $memberDataPoints = [];
  if (isset($memberCost)) {
    foreach ($memberCost as $member) {
      if ($member['cost'] != 0) {
        $memberDataPoints[] = [
          "label" => $member['name'],
          "y" => $member["cost"],
        ];

      }
    }
  }

  $showMemberCostGraph = count($memberDataPoints) > 1;

  $paymentMethodDataPoints = [];
  foreach ($paymentMethodCount as $paymentMethod) {
    if ($paymentMethod['count'] != 0) {
      $paymentMethodDataPoints[] = [
        "label" => $paymentMethod['name'],
        "y" => $paymentMethod["count"],
      ];
    }
  }

  $showPaymentMethodCountGraph = count($paymentMethodDataPoints) > 1;
  if ($showCategoryCostGraph || $showMemberCostGraph || $showPaymentMethodCountGraph) {
    ?>
    <h2><?= translate('split_views', $i18n) ?></h2>
    <div class="graphs">
      <?php
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

      if ($showPaymentMethodCountGraph) {
        ?>
        <section class="graph">
          <header>
            <?= translate('payment_method_split', $i18n) ?>
          </header>
          <canvas id="paymentMethidSplitChart" style="height: 370px; width: 100%;"></canvas>
        </section>
        <?php
      }

      ?>
    </div>
    <?php
  }
  ?>

</section>
<?php
if ($showCategoryCostGraph || $showMemberCostGraph || $showPaymentMethodCountGraph) {
  ?>
  <script src="scripts/libs/chart.js"></script>
  <script type="text/javascript">
    window.onload = function () {
      loadGraph("categorySplitChart", <?php echo json_encode($categoryDataPoints, JSON_NUMERIC_CHECK); ?>, "<?= $code ?>", <?= $showCategoryCostGraph ?>);
      loadGraph("memberSplitChart", <?php echo json_encode($memberDataPoints, JSON_NUMERIC_CHECK); ?>, "<?= $code ?>", <?= $showMemberCostGraph ?>);
      loadGraph("paymentMethidSplitChart", <?php echo json_encode($paymentMethodDataPoints, JSON_NUMERIC_CHECK); ?>, "", <?= $showPaymentMethodCountGraph ?>);
    }
  </script>
  <?php
}
?>
<script src="scripts/stats.js?<?= $version ?>"></script>
<?php
require_once 'includes/footer.php';
?>