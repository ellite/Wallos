<?php
/*
  Payment periods that do not follow the calendar.

  A period anchored on the user's payslip date is what makes "amount due"
  answer the question people actually ask: how much of this pay cheque is
  already spoken for. That only works if the window is computed from the
  anchor day rather than from the first of the month, if a short month cannot
  push the anchor past its end, and if the amount counts every payment that
  falls between today and the end of the window - including a one-time
  purchase, which has no cycle to walk but is still money to find.
*/

require_once WALLOS_ROOT . '/includes/budget_period_calculations.php';

/**
 * @return array{0: string, 1: string} The active period as [start, end].
 */
function budget_period_range($today, $periodType, $anchorDate)
{
    $period = getActiveBudgetPeriod(new DateTime($today), $periodType, $anchorDate);

    return [$period['start']->format('Y-m-d'), $period['end']->format('Y-m-d')];
}

wallos_test('a monthly period runs from the anchor day to the day before the next one', function () {
    // Paid on the 15th, asking after payday: the window is the one the last
    // pay cheque has to cover.
    assert_same(['2026-09-15', '2026-10-14'],
        budget_period_range('2026-09-20', 'monthly', '2026-09-15'),
        'a date after the anchor day sits in the period that opened this month');

    // Asking before payday: still inside the window the previous cheque opened.
    assert_same(['2026-08-15', '2026-09-14'],
        budget_period_range('2026-09-03', 'monthly', '2026-09-15'),
        'a date before the anchor day sits in the period that opened last month');

    // The anchor day itself opens a period rather than closing one.
    assert_same(['2026-09-15', '2026-10-14'],
        budget_period_range('2026-09-15', 'monthly', '2026-09-15'),
        'the anchor day is the first day of its period');
});

wallos_test('a monthly period clamps an anchor day the month is too short for', function () {
    // February has no 31st. The period has to end on the last day it does have,
    // not roll forward into March and overlap the next one.
    assert_same(['2026-01-31', '2026-02-27'],
        budget_period_range('2026-02-10', 'monthly', '2026-01-31'),
        'an anchor on the 31st clamps to the last day of February');

    assert_same(['2026-02-28', '2026-03-30'],
        budget_period_range('2026-02-28', 'monthly', '2026-01-31'),
        'the clamped February period runs on to the day before the March anchor');
});

wallos_test('a weekly period steps in whole weeks from the anchor', function () {
    assert_same(['2026-09-11', '2026-09-17'],
        budget_period_range('2026-09-12', 'weekly', '2026-08-14'),
        'the weekly period is the one containing today, counted from the anchor');

    assert_same(['2026-09-11', '2026-09-24'],
        budget_period_range('2026-09-12', 'fortnightly', '2026-08-14'),
        'a fortnightly period spans fourteen days from the same anchor');
});

wallos_test('the period amount counts only what is still to be paid inside the window', function () {
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'periods');
    $currencyId = wallos_test_currency_id(1, 0);

    $today = new DateTime('2026-09-12');
    $period = getActiveBudgetPeriod($today, 'monthly', '2026-09-15');
    assert_same('2026-09-14', $period['end']->format('Y-m-d'),
        'the fixture sits in the period ending the day before payday');

    $subscriptions = [
        // Due inside the window: counted.
        ['price' => 10.0, 'currency_id' => $currencyId, 'next_payment' => '2026-09-13',
         'cycle' => 3, 'frequency' => 1, 'inactive' => 0, 'auto_renew' => 1],
        // Due after the window closes: the next pay cheque covers it.
        ['price' => 99.0, 'currency_id' => $currencyId, 'next_payment' => '2026-09-20',
         'cycle' => 3, 'frequency' => 1, 'inactive' => 0, 'auto_renew' => 1],
        // Already paid earlier in the window: nothing left to find.
        ['price' => 77.0, 'currency_id' => $currencyId, 'next_payment' => '2026-10-05',
         'cycle' => 3, 'frequency' => 1, 'inactive' => 0, 'auto_renew' => 1],
        // Cancelled: not money that has to be found.
        ['price' => 50.0, 'currency_id' => $currencyId, 'next_payment' => '2026-09-13',
         'cycle' => 3, 'frequency' => 1, 'inactive' => 1, 'auto_renew' => 1],
    ];

    assert_equals(10.0,
        round(computeAmountNeededInPeriod($subscriptions, $today, $period['end'], $db, 1), 2),
        'only the active payment falling between today and the period end is counted');

    $db->close();
});

wallos_test('a weekly subscription is counted once per due date inside the window', function () {
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'weekly');
    $currencyId = wallos_test_currency_id(1, 0);

    $today = new DateTime('2026-09-01');
    $periodEnd = new DateTime('2026-09-30');

    $subscriptions = [
        ['price' => 5.0, 'currency_id' => $currencyId, 'next_payment' => '2026-09-04',
         'cycle' => 2, 'frequency' => 1, 'inactive' => 0, 'auto_renew' => 1],
    ];

    // Sep 4, 11, 18 and 25 all fall inside the window.
    assert_equals(20.0,
        round(computeAmountNeededInPeriod($subscriptions, $today, $periodEnd, $db, 1), 2),
        'every weekly occurrence in the window is counted');

    $db->close();
});

wallos_test('a one-time purchase inside the window is money the period has to cover', function () {
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'onetime');
    $currencyId = wallos_test_currency_id(1, 0);

    $today = new DateTime('2026-09-12');
    $periodEnd = new DateTime('2026-10-14');

    $inside = [
        ['price' => 250.0, 'currency_id' => $currencyId, 'next_payment' => '2026-09-30',
         'cycle' => 5, 'frequency' => 1, 'inactive' => 0, 'auto_renew' => 0],
    ];
    $outside = [
        ['price' => 250.0, 'currency_id' => $currencyId, 'next_payment' => '2026-11-30',
         'cycle' => 5, 'frequency' => 1, 'inactive' => 0, 'auto_renew' => 0],
    ];

    assert_equals(250.0,
        round(computeAmountNeededInPeriod($inside, $today, $periodEnd, $db, 1), 2),
        'a one-time purchase due inside the window is counted');

    assert_equals(0.0,
        round(computeAmountNeededInPeriod($outside, $today, $periodEnd, $db, 1), 2),
        'a one-time purchase due after the window is not');

    $db->close();
});

wallos_test('an invalid period type or anchor date falls back instead of throwing', function () {
    assert_same('monthly', sanitizeBudgetPeriodType('quarterly'),
        'an unsupported period type falls back to monthly');

    assert_same(date('Y-m-d'), sanitizeBudgetAnchorDate('2026-02-31'),
        'a date that does not exist falls back to today');

    assert_same(date('Y-m-d'), sanitizeBudgetAnchorDate('not-a-date'),
        'a malformed anchor date falls back to today');
});

wallos_test('scoping the statistics to the period is opt-in', function () {
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'optin');

    $row = $db->querySingle('SELECT use_custom_period FROM user WHERE id = 1', true);
    // querySingle() returns false when the column does not exist, and casting
    // that to int would read as an opt-out, so the row is checked first.
    assert_true(is_array($row) && array_key_exists('use_custom_period', $row),
        'the opt-in column exists on the user table');
    assert_same(0, (int) ($row['use_custom_period'] ?? -1),
        'a new account keeps calendar months until it asks for something else');

    // Migration 000053 gave every account an anchor date, so "the period is not
    // a calendar month" is true for almost everybody and cannot stand in for
    // consent. Only the flag may gate the statistics.
    $anchor = $db->querySingle('SELECT budget_period_anchor_date FROM user WHERE id = 1', true);
    assert_true(!empty($anchor['budget_period_anchor_date']),
        'the anchor date is populated regardless of the opt-in');

    $db->close();
});

wallos_test('the pay-period migration can run twice', function () {
    $db = wallos_test_open_database();

    // Re-running an ALTER TABLE that already applied would throw; the migration
    // guards each column on pragma_table_info, so a second run is a no-op.
    require WALLOS_ROOT . '/migrations/000060.php';

    foreach (['use_custom_period', 'budget_period_second_day'] as $column) {
        $row = $db->querySingle(
            'SELECT COUNT(*) AS columns FROM pragma_table_info(\'user\') WHERE name = \'' . $column . '\'',
            true
        );
        assert_same(1, (int) $row['columns'],
            $column . ' exists exactly once after a repeat run');
    }

    $db->close();
});

wallos_test('only the opt-in flag lets the period take over the statistics', function () {
    // The views must not read $periodDiffersFromCalendarMonth directly for this
    // decision: that variable is true for nearly every migrated account.
    foreach (['stats.php', 'index.php'] as $page) {
        $source = file_get_contents(WALLOS_ROOT . '/' . $page);

        assert_contains('$periodScopesStatistics', $source,
            $page . ' gates the period statistics on the opt-in');
        assert_not_contains('if ($periodDiffersFromCalendarMonth)', $source,
            $page . ' does not treat a non-calendar period as consent');
    }
});

/**
 * Runs the real statistics calculation against a fixture account and returns
 * the variables the pages read.
 *
 * stats_calculations.php declares functions at file scope, so it can only be
 * included once per process. Each case therefore runs in its own subprocess.
 *
 * @param bool $useCustomPeriod
 * @return array<string, mixed>
 */
function budget_period_run_stats($useCustomPeriod)
{
    $databasePath = wallos_test_database();

    $db = new SQLite3($databasePath);
    $db->busyTimeout(5000);
    wallos_test_create_user($db, 1, 'stats');
    $currencyId = wallos_test_currency_id(1, 0);

    // Paid on the 15th of every month: the period runs the 15th to the 14th.
    $stmt = $db->prepare("UPDATE user SET use_custom_period = :flag,
                          budget_period_type = 'monthly',
                          budget_period_anchor_date = '2020-01-15' WHERE id = 1");
    $stmt->bindValue(':flag', $useCustomPeriod ? 1 : 0, SQLITE3_INTEGER);
    $stmt->execute();

    // Due two days from now, so it lands inside the current period whichever
    // day the suite runs on.
    $stmt = $db->prepare("INSERT INTO subscriptions
        (name, price, currency_id, next_payment, cycle, frequency, inactive, auto_renew,
         user_id, category_id, payer_user_id, payment_method_id, start_date)
        VALUES ('Rent', 800, :currency, :nextPayment, 3, 1, 0, 1, 1, 1, 1, 1, '2020-01-15')");
    $stmt->bindValue(':currency', $currencyId, SQLITE3_INTEGER);
    $stmt->bindValue(':nextPayment', (new DateTime('+2 day'))->format('Y-m-d'), SQLITE3_TEXT);
    $stmt->execute();
    $db->close();

    $runner = WALLOS_TEST_TMP . '/stats-runner.php';
    file_put_contents($runner, <<<'RUNNER'
<?php
// stats_calculations.php expects a caller to have set these up.
$db = new SQLite3($argv[1]);
$db->busyTimeout(5000);
$userId = 1;
$userData = $db->querySingle('SELECT * FROM user WHERE id = 1', true);
$i18n = [];
function translate($text, $translations) { return $text; }

require $argv[2] . '/includes/stats_calculations.php';

echo json_encode([
    'periodScopesStatistics' => $periodScopesStatistics,
    'periodDiffersFromCalendarMonth' => $periodDiffersFromCalendarMonth,
    'amountNeededThisPeriod' => round($amountNeededThisPeriod, 2),
    'amountDueThisMonth' => round($amountDueThisMonth, 2),
]);
RUNNER);

    $command = escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($runner)
        . ' ' . escapeshellarg($databasePath) . ' ' . escapeshellarg(WALLOS_ROOT) . ' 2>/dev/null';

    return json_decode(shell_exec($command), true);
}

wallos_test('the statistics follow the calendar month until the period is switched on', function () {
    $off = budget_period_run_stats(false);
    assert_true(is_array($off), 'the statistics ran with the opt-in off');
    assert_true($off['periodDiffersFromCalendarMonth'],
        'the fixture period is genuinely not a calendar month');
    assert_same(false, $off['periodScopesStatistics'],
        'an account that did not opt in keeps calendar-month statistics');

    $on = budget_period_run_stats(true);
    assert_true(is_array($on), 'the statistics ran with the opt-in on');
    assert_true($on['periodScopesStatistics'],
        'opting in hands the statistics to the period');

    // Both numbers are always computed; the opt-in only decides which the
    // pages show. The payment two days out falls in the period either way.
    assert_equals(800.0, $on['amountNeededThisPeriod'],
        'the period amount picks up the payment due inside the window');
});

wallos_test('the settings warning fires exactly when the period is a calendar month', function () {
    // settings.php decides with getActiveBudgetPeriod(); settings.js uses the
    // shortcut "monthly and anchored on the 1st" so it can react before a save.
    // The two must agree, or the note contradicts what the pages do.
    $today = new DateTime('now');
    $calendarStart = $today->format('Y-m-01');
    $calendarEnd = $today->format('Y-m-t');
    $disagreements = [];

    foreach (['monthly', 'weekly', 'fortnightly', 'semimonthly'] as $periodType) {
        // Semi-monthly has a second payday, and both days matter: paid twice on
        // the 1st is the same thing as a calendar month.
        $secondDays = $periodType === 'semimonthly' ? range(1, 31) : [16];

        foreach ($secondDays as $secondDay) {
            for ($day = 1; $day <= 31; $day++) {
                $anchor = sprintf('2026-01-%02d', $day);
                $period = getActiveBudgetPeriod($today, $periodType, $anchor, $secondDay);

                $isCalendarMonth = $period['start']->format('Y-m-d') === $calendarStart
                    && $period['end']->format('Y-m-d') === $calendarEnd;
                $shortcutSaysSo = ($periodType === 'monthly' && $day === 1)
                    || ($periodType === 'semimonthly' && $day === 1 && $secondDay === 1);

                if ($isCalendarMonth !== $shortcutSaysSo) {
                    $disagreements[] = $periodType . ' ' . $anchor . '/' . $secondDay
                        . ' (period says ' . var_export($isCalendarMonth, true)
                        . ', shortcut says ' . var_export($shortcutSaysSo, true) . ')';
                }
            }
        }
    }

    assert_same([], $disagreements,
        'the shortcut matches the real period for every anchor day: ' . implode(' | ', $disagreements));

    // A guard that agrees because it never fires would pass the check above.
    $onTheFirst = getActiveBudgetPeriod($today, 'monthly', '2026-01-01');
    assert_same($calendarStart, $onTheFirst['start']->format('Y-m-d'),
        'an anchor on the 1st really does produce a calendar month');
});

wallos_test('the settings page and its script keep the warning in step', function () {
    $page = file_get_contents(WALLOS_ROOT . '/settings.php');
    $script = file_get_contents(WALLOS_ROOT . '/scripts/settings.js');
    $styles = file_get_contents(WALLOS_ROOT . '/styles/styles.css');

    assert_contains('id="customPeriodWarning"', $page,
        'the settings page renders the warning element');
    assert_contains('getActiveBudgetPeriod', $page,
        'the initial state is decided by the real period function, not a copy of the rule');
    assert_contains('customPeriodWarning', $script,
        'the script updates the warning as the controls change');

    // Two notes, and they contradict each other: "the period now drives your
    // statistics" is false exactly when "this period is a calendar month" is
    // true. Both the render and the script must gate them as complements, or a
    // user sees the page tell them two opposite things at once.
    assert_contains("\$useCustomPeriod && !\$customPeriodMatchesCalendarMonth", $page,
        'the what-it-changes note renders only when the period is not a calendar month');
    assert_contains("\$useCustomPeriod && \$customPeriodMatchesCalendarMonth", $page,
        'the same-as-a-month note renders only when it is');
    assert_contains('customPeriodActiveWarning', $script,
        'the script toggles the what-it-changes note too');
    assert_contains('activeNote.hidden = !(enabled && !isCalendarMonth)', $script,
        'the script gates the two notes as complements');

    // .settings-notes>p is display:flex, which beats the hidden attribute unless
    // a rule says otherwise; without it the warning would always be visible.
    assert_contains('.settings-notes>p[hidden]', $styles,
        'the hidden attribute actually hides the note');
});

wallos_test('a semi-monthly period covers the month without gaps or overlaps', function () {
    // Paid on the 15th and the last day: two periods per month, meeting exactly.
    $cases = [
        ['2026-03-01', '2026-02-28', '2026-03-14'],
        ['2026-03-14', '2026-02-28', '2026-03-14'],
        ['2026-03-15', '2026-03-15', '2026-03-30'],
        ['2026-03-31', '2026-03-31', '2026-04-14'],
    ];

    foreach ($cases as [$today, $expectedStart, $expectedEnd]) {
        $period = getActiveBudgetPeriod(new DateTime($today), 'semimonthly', '2026-01-15', 31);
        assert_same([$expectedStart, $expectedEnd],
            [$period['start']->format('Y-m-d'), $period['end']->format('Y-m-d')],
            'paid 15th and last day, on ' . $today);
    }

    // The other common pattern.
    $firstAndSixteenth = getActiveBudgetPeriod(new DateTime('2026-03-16'), 'semimonthly', '2026-01-01', 16);
    assert_same(['2026-03-16', '2026-03-31'],
        [$firstAndSixteenth['start']->format('Y-m-d'), $firstAndSixteenth['end']->format('Y-m-d')],
        'paid 1st and 16th, on the 16th');
});

wallos_test('a semi-monthly period survives any pair of paydays', function () {
    // Clamping can collide two paydays onto the same date (the 30th and the
    // 31st are both the 28th in February). The period must still be a real
    // window that contains today, never empty and never inverted.
    $problems = [];

    foreach (['2026-01-15', '2026-02-14', '2026-02-28', '2026-03-31', '2024-02-29'] as $todayString) {
        $today = new DateTime($todayString);

        for ($firstDay = 1; $firstDay <= 31; $firstDay++) {
            for ($secondDay = 1; $secondDay <= 31; $secondDay++) {
                $period = getActiveBudgetPeriod(
                    $today,
                    'semimonthly',
                    sprintf('2026-01-%02d', $firstDay),
                    $secondDay
                );

                if ($period['end'] < $period['start']) {
                    $problems[] = "inverted: days $firstDay/$secondDay on $todayString";
                } elseif ($today < $period['start'] || $today > $period['end']) {
                    $problems[] = "today outside: days $firstDay/$secondDay on $todayString";
                }
            }
        }
    }

    assert_same([], $problems,
        'every payday pair yields a window containing today: ' . implode(' | ', array_slice($problems, 0, 5)));
});

wallos_test('an out-of-range second payday falls back instead of breaking the period', function () {
    assert_same(16, sanitizeBudgetSecondDay(0), 'day zero falls back');
    assert_same(16, sanitizeBudgetSecondDay(32), 'day 32 falls back');
    assert_same(16, sanitizeBudgetSecondDay('nonsense'), 'a non-number falls back');
    assert_same(31, sanitizeBudgetSecondDay('31'), 'a numeric string is accepted');
    assert_same('semimonthly', sanitizeBudgetPeriodType('semimonthly'), 'the new type is allowed');
});

wallos_test('the calendar walks occurrences with the shared helper, not strtotime', function () {
    // strtotime('+1 months') overflows a month-end date: from 31 Aug it lands on
    // 1 Oct, skipping September and then drifting to the 1st for good. The
    // calendar used to page through dates that way.
    $subscription = [
        'next_payment' => '2026-08-31',
        'cycle' => 3,
        'frequency' => 1,
        'auto_renew' => 1,
    ];

    $occurrences = getSubscriptionOccurrencesInRange(
        $subscription,
        new DateTime('2026-08-01'),
        new DateTime('2026-12-31')
    );

    $dates = array_map(fn($date) => $date->format('Y-m-d'), $occurrences);
    assert_same(['2026-08-31', '2026-09-30', '2026-10-31', '2026-11-30', '2026-12-31'], $dates,
        'a month-end subscription bills at each month end');

    $calendar = file_get_contents(WALLOS_ROOT . '/calendar.php');
    assert_contains('getSubscriptionOccurrencesInRange', $calendar,
        'the calendar uses the shared helper');
    assert_not_contains('months", $date)', $calendar,
        'the overflowing strtotime walk is gone');
});
