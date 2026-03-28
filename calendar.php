<?php
require_once 'includes/header.php';

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

// Get budget from user table
$query = "SELECT budget FROM user WHERE id = :userId";
$stmt = $db->prepare($query);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$row = $result->fetchArray(SQLITE3_ASSOC);
$budget = $row['budget'] ?? 0;

$currentMonth = date('m');
$currentYear = date('Y');
$sameAsCurrent = false;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['month']) && isset($_GET['year'])) {
  // Don't allow viewing past months
  $selectedMonth = str_pad($_GET['month'], 2, '0', STR_PAD_LEFT);
  $selectedYear = $_GET['year'];

  $selectedTimestamp = strtotime($selectedYear . '-' . $selectedMonth . '-01');
  $currentTimestamp = strtotime($currentYear . '-' . $currentMonth . '-01');

  if ($selectedTimestamp < $currentTimestamp) {
    $calendarMonth = $currentMonth;
    $calendarYear = $currentYear;
  } else {
    $calendarMonth = $selectedMonth;
    $calendarYear = $selectedYear;
  }

  if ($calendarMonth == $currentMonth && $calendarYear == $currentYear) {
    $sameAsCurrent = true;
  }
} else {
  $calendarMonth = $currentMonth;
  $calendarYear = $currentYear;
  $sameAsCurrent = true;
}

$currenciesInUse = [];
$numberOfSubscriptionsToPayThisMonth = 0;
$totalCostThisMonth = 0;
$amountDueThisMonth = 0;

$query = "SELECT * FROM subscriptions WHERE user_id = :user_id AND inactive = 0";
$stmt = $db->prepare($query);
$stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$subscriptions = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
  $subscriptions[] = $row;
  $currenciesInUse[] = $row['currency_id'];
}

$currenciesInUse = array_unique($currenciesInUse);
$usesMultipleCurrencies = count($currenciesInUse) > 1;

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

$yearsToLoad = $calendarYear - $currentYear + 1;
?>

<section class="contain">
  <?php
  if ($showCantConverErrorMessage) {
    ?>
    <div class="error-box">
      <div class="error-message">
        <i class="fa-solid fa-exclamation-circle"></i>
        <?= translate('cant_convert_currency', $i18n) ?>
      </div>
    </div>
    <?php
  }
  ?>
  <div class="split-header">
    <h2>
    <?= translate('calendar', $i18n) ?>
      <button class="button export-ical" onClick="showExportPopup()" title="<?= translate('export_icalendar', $i18n) ?>">
        <?php require_once 'images/siteicons/svg/export_ical.php'; ?>
      </button>
    </h2>
    <div id="subscriptions_calendar" class="subscription-modal">
        <div class="modal-header">
            <h3><?= translate('export_icalendar', $i18n) ?></h3>
            <span class="fa-solid fa-xmark close-modal" onclick="closePopup()"></span>
        </div>
        <div class="form-group-inline">
            <input id="iCalendarUrl" type="text" value="" readonly>
            <input type="hidden" id="apiKey" value="<?= $userData['api_key'] ?>">
            <button onclick="copyToClipboard()" class="button tiny"> <?= translate('copy_to_clipboard', $i18n) ?> </button>
        </div>
    </div>

    <div class="calendar-nav">
      <?php
      if (!$sameAsCurrent) {
        ?>
        <button class="button secondary-button tiny" onClick="currentMoth()" title="<?= translate('reset', $i18n) ?>"><i
            class="fa-solid fa-calendar-day"></i></button>
        <button class="button tiny" id="prev" onclick="prevMonth(<?= $calendarMonth ?>, <?= $calendarYear ?>)"><i
            class="fa-solid fa-chevron-left"></i></button>
        <?php
      }
      ?>
      <span id="month" class="month"><?= translate('month-' . $calendarMonth, $i18n) ?> <?= $calendarYear ?></span>
      <button class="button tiny" id="next" onclick="nextMonth(<?= $calendarMonth ?>, <?= $calendarYear ?>)"><i
          class="fa-solid fa-chevron-right"></i></button>
    </div>
  </div>
  <div>
    <?php
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $calendarMonth, $calendarYear);
    $firstDay = mktime(0, 0, 0, $calendarMonth, 1, $calendarYear);
    $firstDayOfWeek = date('N', $firstDay) - 1; // Adjusted to make Monday (1) the first day
    $dayOfWeek = 0;
    $day = 1;
    $days = 1;
    $week = 1;
    $today = date('Y-m-d');
    $today = explode('-', $today);
    $todayYear = $today[0];
    $todayMonth = $today[1];
    $todayDay = $today[2];
    $today = $todayYear . '-' . $todayMonth . '-' . $todayDay;
    $today = strtotime($today);
    ?>

    <div class="calendar">
      <div class="calendar-header">
        <div class="calendar-cell"><?= translate('mon', $i18n) ?></div>
        <div class="calendar-cell"><?= translate('tue', $i18n) ?></div>
        <div class="calendar-cell"><?= translate('wed', $i18n) ?></div>
        <div class="calendar-cell"><?= translate('thu', $i18n) ?></div>
        <div class="calendar-cell"><?= translate('fri', $i18n) ?></div>
        <div class="calendar-cell"><?= translate('sat', $i18n) ?></div>
        <div class="calendar-cell"><?= translate('sun', $i18n) ?></div>
      </div>
      <div class="calendar-body">
        <div class="week calendar-row">
          <?php
          for ($i = 0; $i < $firstDayOfWeek; $i++) { // Fill empty cells if month doesn't start on Monday
            ?>
            <div class="calendar-cell empty">
              <div class="calendar-cell-header">
                <span class="day">&nbsp;</span>
              </div>
              <div class="calendar-cell-content"></div>
            </div>
            <?php
          }
          for ($i = $firstDayOfWeek; $i < 7; $i++) {
            if ($day <= $daysInMonth) {
              $dayClass = ($day == $todayDay && $calendarMonth == $todayMonth && $calendarYear == $todayYear) ? "today" : "";
              ?>
              <div class="calendar-cell <?= $dayClass ?>">
                <div class="calendar-cell-header">
                  <span class="day"><?= $day ?></span>
                </div>
                <div class="calendar-cell-content">
                  <?php
                  foreach ($subscriptions as $subscription) {
                    $nextPaymentDate = strtotime($subscription['next_payment']);
                    $subscriptionStartDate = !empty($subscription['start_date'])
                      ? strtotime($subscription['start_date'])
                      : $nextPaymentDate;
                    $cycle = $subscription['cycle']; // Integer from 1 to 4
                    $frequency = $subscription['frequency'];

                    $endDate = strtotime("+" . $yearsToLoad . " years", $nextPaymentDate);

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
                    $startOfMonth = strtotime($calendarYear . '-' . str_pad($calendarMonth, 2, '0', STR_PAD_LEFT) . '-01');

                    // Find the first payment date of the month by moving backwards
                    $startDate = $nextPaymentDate;
                    while ($startDate > $startOfMonth) {
                      $startDate = strtotime("-" . $incrementString, $startDate);
                    }

                    for ($date = $startDate; $date <= $endDate; $date = strtotime($incrementString, $date)) {
                      if ($date < $subscriptionStartDate) {
                        continue;
                      }
                      if (date('Y-m', $date) == $calendarYear . '-' . str_pad($calendarMonth, 2, '0', STR_PAD_LEFT)) {
                        if (date('d', $date) == $day) {
                          $totalCostThisMonth += getPriceConverted($subscription['price'], $subscription['currency_id'], $db, $userId);
                          $numberOfSubscriptionsToPayThisMonth++;
                          if ($date > $today) {
                            $amountDueThisMonth += getPriceConverted($subscription['price'], $subscription['currency_id'], $db, $userId);
                          }
                          ?>
                          <div class="calendar-subscription-title" onClick="openSubscriptionModal(<?= $subscription['id'] ?>)">
                            <?= htmlspecialchars($subscription['name']) ?>
                          </div>
                          <?php
                        }
                      }
                    }
                  }
                  ?>
                </div>
              </div>
              <?php
              $day++;
            }
          }
          while ($day <= $daysInMonth) {
            if ($dayOfWeek % 7 == 0) {
              ?>
            </div>
            <div class="week calendar-row">
              <?php
            }
            $dayClass = ($day == $todayDay && $calendarMonth == $todayMonth && $calendarYear == $todayYear) ? "today" : "";
            ?>
            <div class="calendar-cell <?= $dayClass ?>">
              <div class="calendar-cell-header">
                <span class="day"><?= $day ?></span>
              </div>
              <div class="calendar-cell-content">
                <?php
                foreach ($subscriptions as $subscription) {
                  $nextPaymentDate = strtotime($subscription['next_payment']);
                  $subscriptionStartDate = !empty($subscription['start_date'])
                    ? strtotime($subscription['start_date'])
                    : $nextPaymentDate;
                  $cycle = $subscription['cycle']; // Integer from 1 to 4
                  $frequency = $subscription['frequency'];

                  $endDate = strtotime("+" . $yearsToLoad . " years", $nextPaymentDate);

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
                  $startOfMonth = strtotime($calendarYear . '-' . str_pad($calendarMonth, 2, '0', STR_PAD_LEFT) . '-01');

                  // Find the first payment date of the month by moving backwards
                  $startDate = $nextPaymentDate;
                  while ($startDate > $startOfMonth) {
                    $startDate = strtotime("-" . $incrementString, $startDate);
                  }

                  for ($date = $startDate; $date <= $endDate; $date = strtotime($incrementString, $date)) {
                    if ($date < $subscriptionStartDate) {
                      continue;
                    }
                    if (date('Y-m', $date) == $calendarYear . '-' . str_pad($calendarMonth, 2, '0', STR_PAD_LEFT)) {
                      if (date('d', $date) == $day) {
                        $totalCostThisMonth += getPriceConverted($subscription['price'], $subscription['currency_id'], $db, $userId);
                        $numberOfSubscriptionsToPayThisMonth++;
                        if ($date > $today) {
                          $amountDueThisMonth += getPriceConverted($subscription['price'], $subscription['currency_id'], $db, $userId);
                        }
                        ?>
                        <div class="calendar-subscription-title" onClick="openSubscriptionModal(<?= $subscription['id'] ?>)">
                          <?= $subscription['name'] ?>
                        </div>
                        <?php
                      }
                    }
                  }
                }
                ?>
              </div>
            </div>
            <?php
            $day++;
            $dayOfWeek++;
          }
          while ($dayOfWeek % 7 != 0) { // Fill the rest of the week with empty cells
            ?>
            <div class="calendar-cell empty">
              <div class="calendar-cell-header">
                <span class="day">&nbsp;</span>
              </div>
              <div class="calendar-cell-content"></div>
            </div>
            <?php
            $dayOfWeek++;
          }
          ?>
        </div>
      </div>
    </div>

    <?php
      if ($budget > 0 && $totalCostThisMonth > $budget) {
        $overBudgetAmount = $totalCostThisMonth - $budget;
        $overBudgetAmount = CurrencyFormatter::format($overBudgetAmount, $code);
        ?>
          <div class="over-budget">
            <i class="fa-solid fa-exclamation-triangle"></i>
            <?= translate('over_budget_warning', $i18n) ?>  (<?= $overBudgetAmount ?>)
          </div>
        <?php
      }
    ?>    

    <div class="calendar-monthly-stats">
      <div class="calendar-monthly-stats-header">
        <h3><?= translate("stats", $i18n) ?></h3>
      </div>
      <div class="statistics">
        <div class="statistic">
          <span>
            <?= $numberOfSubscriptionsToPayThisMonth ?></span>
          <div class="title"><?= translate("active_subscriptions", $i18n) ?></div>
        </div>
        <div class="statistic">
          <span><?= CurrencyFormatter::format($totalCostThisMonth, $code) ?></span>
          <div class="title"><?= translate("total_cost", $i18n) ?></div>
        </div>
        <div class="statistic">
          <span><?= CurrencyFormatter::format($amountDueThisMonth, $code) ?></span>
          <div class="title"><?= translate("amount_due", $i18n) ?></div>
        </div>
      </div>
    </div>

</section>

<div id="subscriptionModal" class="subscription-modal">
  <div class="modal-content">
    <div id="subscriptionModalContent"></div>
  </div>
</div>

<script src="scripts/calendar.js?<?= $version ?>"></script>
<?php
require_once 'includes/footer.php';
?>