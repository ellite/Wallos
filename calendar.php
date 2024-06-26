<?php
require_once 'includes/header.php';

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

$query = "SELECT * FROM subscriptions WHERE user_id = :user_id AND inactive = 0";
$stmt = $db->prepare($query);
$stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$subscriptions = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
  $subscriptions[] = $row;
}

$yearsToLoad = $calendarYear - $currentYear + 1;
?>

<section class="contain">
  <div class="split-header">
    <h2>Calendar</h2>
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
      <span id="month"><?= translate('month-' . $calendarMonth, $i18n) ?> <?= $calendarYear ?></span>
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
                      if (date('Y-m', $date) == $calendarYear . '-' . str_pad($calendarMonth, 2, '0', STR_PAD_LEFT)) {
                        if (date('d', $date) == $day) {
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
                    if (date('Y-m', $date) == $calendarYear . '-' . str_pad($calendarMonth, 2, '0', STR_PAD_LEFT)) {
                      if (date('d', $date) == $day) {
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