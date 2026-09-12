<?php
require_once 'includes/header.php';
require_once 'includes/currency_rates.php';

function getPriceConverted($price, $currency, $database, $userId)
{
  return wallos_convert_price($price, $currency, $database, $userId);
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

// When the user has opted into a pay period, the page is framed by that period
// instead of the calendar month: a month grid would keep answering the question
// they said was the wrong one.
require_once 'includes/budget_period_calculations.php';
$useCustomPeriod = !empty($userData['use_custom_period']);
$budgetPeriodType = sanitizeBudgetPeriodType($userData['budget_period_type'] ?? 'monthly');
$budgetPeriodAnchorDate = sanitizeBudgetAnchorDate($userData['budget_period_anchor_date'] ?? getDefaultBudgetAnchorDate());
$budgetPeriodSecondDay = sanitizeBudgetSecondDay($userData['budget_period_second_day'] ?? 16);

$todayDate = new DateTime('today');
$currentPeriod = getActiveBudgetPeriod($todayDate, $budgetPeriodType, $budgetPeriodAnchorDate, $budgetPeriodSecondDay);
$periodView = $useCustomPeriod
  && ($currentPeriod['start']->format('Y-m-d') !== $todayDate->format('Y-m-01')
    || $currentPeriod['end']->format('Y-m-d') !== $todayDate->format('Y-m-t'));

$periodOffset = 0;
if ($periodView) {
  // Unlike the month view, the period view can look backwards: "what did last
  // pay period actually cost" is a reasonable thing to ask.
  $periodOffset = isset($_GET['period']) ? (int) $_GET['period'] : 0;
  $periodOffset = max(-120, min(120, $periodOffset));

  // Step one period at a time through the same function that decides the
  // current one, so every frame is consistent with the statistics.
  $displayedPeriod = $currentPeriod;
  for ($step = 0; $step < abs($periodOffset); $step++) {
    $pivot = $periodOffset > 0
      ? (clone $displayedPeriod['end'])->modify('+1 day')
      : (clone $displayedPeriod['start'])->modify('-1 day');
    $displayedPeriod = getActiveBudgetPeriod($pivot, $budgetPeriodType, $budgetPeriodAnchorDate, $budgetPeriodSecondDay);
  }

  $periodStart = $displayedPeriod['start'];
  $periodEnd = $displayedPeriod['end'];
  $periodLabel = $displayedPeriod['label'];
  // formatBudgetPeriodLabel() drops the year when the period sits inside one,
  // which is fine beside "current period" but not on a calendar you can page
  // through: Aug 2026 and Aug 2036 would read identically.
  $periodHeading = $periodStart->format('Y') === $periodEnd->format('Y')
    ? $periodLabel . ', ' . $periodStart->format('Y')
    : $periodLabel;
  $sameAsCurrent = $periodOffset === 0;
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

$weekStartsSunday = !empty($settings['week_starts_sunday']);
$weekDays = [
  ['key' => 'mon', 'offset' => 0],
  ['key' => 'tue', 'offset' => 1],
  ['key' => 'wed', 'offset' => 2],
  ['key' => 'thu', 'offset' => 3],
  ['key' => 'fri', 'offset' => 4],
  ['key' => 'sat', 'offset' => 5],
  ['key' => 'sun', 'offset' => 6],
];

if ($weekStartsSunday) {
  $weekDays = [
    ['key' => 'sun', 'offset' => 6],
    ['key' => 'mon', 'offset' => 0],
    ['key' => 'tue', 'offset' => 1],
    ['key' => 'wed', 'offset' => 2],
    ['key' => 'thu', 'offset' => 3],
    ['key' => 'fri', 'offset' => 4],
    ['key' => 'sat', 'offset' => 5],
  ];
}
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
    <div class="calendar-title">
      <?php if ($periodView) { ?>
        <h2><?= htmlspecialchars($periodHeading, ENT_QUOTES, 'UTF-8') ?></h2>
        <div class="calendar-nav">
          <button class="button secondary-button" id="prev" onclick="prevPeriod(<?= $periodOffset ?>)">
            <i class="fa-solid fa-chevron-left"></i>
          </button>
          <button class="button secondary-button" id="next" onclick="nextPeriod(<?= $periodOffset ?>)">
            <i class="fa-solid fa-chevron-right"></i>
          </button>
          <?php if (!$sameAsCurrent) { ?>
            <button class="button secondary-button" onClick="currentPeriod()" title="<?= translate('reset', $i18n) ?>">
              <i class="fa-solid fa-calendar-day"></i>
            </button>
          <?php } ?>
        </div>
      <?php } else { ?>
        <h2><?= translate('month-' . $calendarMonth, $i18n) ?> <?= $calendarYear ?></h2>
        <div class="calendar-nav">
          <button class="button secondary-button" id="prev"
            onclick="prevMonth(<?= $calendarMonth ?>, <?= $calendarYear ?>)" <?= $sameAsCurrent ? 'disabled' : '' ?>>
            <i class="fa-solid fa-chevron-left"></i>
          </button>
          <button class="button secondary-button" id="next"
            onclick="nextMonth(<?= $calendarMonth ?>, <?= $calendarYear ?>)">
            <i class="fa-solid fa-chevron-right"></i>
          </button>
          <?php
          if (!$sameAsCurrent) {
            ?>
            <button class="button secondary-button" onClick="currentMoth()" title="<?= translate('reset', $i18n) ?>">
              <i class="fa-solid fa-calendar-day"></i>
            </button>
            <?php
          }
          ?>
        </div>
      <?php } ?>
    </div>
    <button class="button secondary-button export-ical" onClick="showExportPopup()"
      title="<?= translate('export_icalendar', $i18n) ?>" aria-label="<?= translate('export_icalendar', $i18n) ?>">
      <?php require_once 'images/siteicons/svg/export_ical.php'; ?>
    </button>
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
  </div>
  <div>
    <?php
    // One grid, one range. The month view spans the calendar month; the period
    // view spans one payday to the day before the next, which usually crosses a
    // month boundary. Everything below works off $rangeStart/$rangeEnd so the
    // two frames cannot drift apart.
    if ($periodView) {
      $rangeStart = clone $periodStart;
      $rangeEnd = clone $periodEnd;
    } else {
      $rangeStart = new DateTime(sprintf('%04d-%02d-01', (int) $calendarYear, (int) $calendarMonth));
      $rangeEnd = (clone $rangeStart)->modify('last day of this month');
    }

    $today = strtotime(date('Y-m-d'));
    $firstDayOfWeek = (int) $rangeStart->format('N') - 1;
    if ($weekStartsSunday) {
      $firstDayOfWeek = ($firstDayOfWeek + 1) % 7;
    }

    // Occurrences come from the same helper the statistics use. calendar.php
    // used to walk dates with strtotime('+1 months'), which overflows a
    // month-end date: a subscription billed on the 31st skipped September
    // entirely and then drifted to the 1st for good.
    $paymentsByDate = [];
    foreach ($subscriptions as $subscription) {
      $subscriptionStart = !empty($subscription['start_date'])
        ? DateTime::createFromFormat('!Y-m-d', trim($subscription['start_date']))
        : null;

      foreach (getSubscriptionOccurrencesInRange($subscription, $rangeStart, $rangeEnd) as $occurrence) {
        if ($subscriptionStart instanceof DateTime && $occurrence < $subscriptionStart) {
          continue;
        }

        $paymentsByDate[$occurrence->format('Y-m-d')][] = $subscription;
        $convertedPrice = getPriceConverted($subscription['price'], $subscription['currency_id'], $db, $userId);
        $totalCostThisMonth += $convertedPrice;
        $numberOfSubscriptionsToPayThisMonth++;
        if ($occurrence->getTimestamp() >= $today) {
          $amountDueThisMonth += $convertedPrice;
        }
      }
    }

    // A day number alone is ambiguous once the grid crosses a month boundary,
    // so name the month on the first cell and wherever a new one starts.
    $rangeSpansMonths = $rangeStart->format('Y-m') !== $rangeEnd->format('Y-m');
    $shortMonthFormatter = null;
    if ($rangeSpansMonths) {
      try {
        $shortMonthFormatter = new IntlDateFormatter($lang, IntlDateFormatter::SHORT, IntlDateFormatter::NONE, null, null, 'MMM');
      } catch (Throwable $e) {
        $shortMonthFormatter = new IntlDateFormatter('en', IntlDateFormatter::SHORT, IntlDateFormatter::NONE, null, null, 'MMM');
      }
    }
    ?>

    <div class="calendar">
      <div class="calendar-header">
        <?php foreach ($weekDays as $weekDay) { ?>
          <div class="calendar-cell"><?= translate($weekDay['key'], $i18n) ?></div>
        <?php } ?>
      </div>
      <div class="calendar-body">
        <div class="week calendar-row">
          <?php
          $dayOfWeek = 0;
          for ($i = 0; $i < $firstDayOfWeek; $i++) {
            echo '<div class="calendar-cell empty"></div>';
            $dayOfWeek++;
          }

          $cursor = clone $rangeStart;
          $isFirstCell = true;
          while ($cursor <= $rangeEnd) {
            if ($dayOfWeek > 0 && $dayOfWeek % 7 == 0) {
              echo '</div><div class="week calendar-row">';
            }
            $dateKey = $cursor->format('Y-m-d');
            $dayNumber = (int) $cursor->format('j');
            $isToday = $dateKey === date('Y-m-d');
            $monthMarker = ($shortMonthFormatter !== null && ($isFirstCell || $dayNumber === 1))
              ? $shortMonthFormatter->format($cursor)
              : null;
            ?>
            <div class="calendar-cell<?= $isToday ? ' today' : '' ?>">
              <span class="day">
                <?php if ($monthMarker !== null) { ?>
                  <span class="day-month"><?= htmlspecialchars($monthMarker, ENT_QUOTES, 'UTF-8') ?></span>
                <?php } ?>
                <?= $dayNumber ?>
              </span>
              <?php if (!empty($paymentsByDate[$dateKey])) { ?>
                <div class="calendar-cell-content">
                  <?php foreach ($paymentsByDate[$dateKey] as $payment) { ?>
                    <div class="calendar-event" onClick="showSubscriptionDetails(event, <?= $payment['id'] ?>)"
                      title="<?= htmlspecialchars($payment['name']) ?>">
                      <?= htmlspecialchars($payment['name']) ?>
                    </div>
                  <?php } ?>
                </div>
              <?php } ?>
            </div>
            <?php
            $isFirstCell = false;
            $dayOfWeek++;
            $cursor->modify('+1 day');
          }
          while ($dayOfWeek % 7 != 0) {
            echo '<div class="calendar-cell empty"></div>';
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
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span><?= translate('over_budget_warning', $i18n) ?> <strong>(<?= $overBudgetAmount ?>)</strong></span>
          </div>
        <?php
      }
    ?>    

    <div class="calendar-monthly-stats">
      <div class="calendar-monthly-stats-header">
        <h3><?= translate("stats", $i18n) ?></h3>
        <?php if ($periodView) { ?>
          <span class="period-range"><?= htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') ?></span>
        <?php } ?>
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
          <div class="title"><?= translate($periodView ? "amount_due_this_period" : "amount_due", $i18n) ?></div>
        </div>
      </div>
    </div>

</section>

<?php require_once 'includes/subscription_details_popup.php'; ?>
<script src="scripts/calendar.js?<?= $version ?>"></script>
<?php
require_once 'includes/footer.php';
?>