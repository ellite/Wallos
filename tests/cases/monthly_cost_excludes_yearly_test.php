<?php
/*
  A yearly subscription is not money that leaves the account this month. Some
  people want the monthly figure to mean exactly that, so the total can leave
  yearly plans out while the yearly figure keeps counting them.
*/

/**
 * Runs the real statistics calculation on a fixture account and returns the
 * totals it produced.
 *
 * stats_calculations.php declares functions at file scope, so it can only be
 * included once per process. Each case therefore runs in its own subprocess,
 * the same way budget_period_test.php does it.
 *
 * @param bool $excludeYearly
 * @return array<string, float>
 */
function monthly_cost_run_stats($excludeYearly)
{
    $databasePath = wallos_test_database();

    $db = new SQLite3($databasePath);
    $db->busyTimeout(5000);
    wallos_test_create_user($db, 1, 'stats');
    $currencyId = wallos_test_currency_id(1, 0);

    $subscriptions = [
        ['Monthly', 10, 3, 1],
        ['Yearly', 240, 4, 1],
    ];

    foreach ($subscriptions as $subscription) {
        list($name, $price, $cycle, $frequency) = $subscription;
        $stmt = $db->prepare("INSERT INTO subscriptions
            (name, price, currency_id, next_payment, cycle, frequency, inactive, auto_renew,
             user_id, category_id, payer_user_id, payment_method_id, start_date)
            VALUES (:name, :price, :currency, '2027-01-01', :cycle, :frequency, 0, 1, 1, 1, 1, 1, '2020-01-01')");
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':price', $price, SQLITE3_INTEGER);
        $stmt->bindValue(':currency', $currencyId, SQLITE3_INTEGER);
        $stmt->bindValue(':cycle', $cycle, SQLITE3_INTEGER);
        $stmt->bindValue(':frequency', $frequency, SQLITE3_INTEGER);
        $stmt->execute();
    }
    $db->close();

    $runner = WALLOS_TEST_TMP . '/monthly-cost-runner.php';
    file_put_contents($runner, <<<'RUNNER'
<?php
// stats_calculations.php expects a caller to have set these up.
$db = new SQLite3($argv[1]);
$db->busyTimeout(5000);
$userId = 1;
$userData = $db->querySingle('SELECT * FROM user WHERE id = 1', true);
$i18n = [];
$settings = ['exclude_yearly_from_monthly' => (int) $argv[3]];
function translate($text, $translations) { return $text; }

require $argv[2] . '/includes/stats_calculations.php';

echo json_encode([
    'monthly' => round($totalCostPerMonth, 2),
    'yearly' => round($totalCostPerYear, 2),
]);
RUNNER);

    $command = sprintf(
        '%s %s %s %s %s',
        escapeshellarg(PHP_BINARY),
        escapeshellarg($runner),
        escapeshellarg($databasePath),
        escapeshellarg(WALLOS_ROOT),
        $excludeYearly ? '1' : '0'
    );

    $output = shell_exec($command);
    $totals = json_decode((string) $output, true);

    assert_true(is_array($totals), 'the statistics calculation produced totals (output: ' . trim((string) $output) . ')');

    return is_array($totals) ? $totals : ['monthly' => -1, 'yearly' => -1];
}

wallos_test('the setting that leaves yearly plans out of the monthly cost exists and is off by default', function () {
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'alice');

    $column = $db->query("SELECT * FROM pragma_table_info('settings') WHERE name='exclude_yearly_from_monthly'");
    assert_true($column->fetchArray(SQLITE3_ASSOC) !== false, 'settings.exclude_yearly_from_monthly exists');

    $value = $db->querySingle("SELECT exclude_yearly_from_monthly FROM settings WHERE user_id = 1");
    assert_same(0, (int) $value, 'existing accounts keep counting yearly plans in the monthly cost');
});

wallos_test('with the setting off a yearly plan stays in the monthly cost', function () {
    $totals = monthly_cost_run_stats(false);

    assert_equals(30.0, (float) $totals['monthly'], 'monthly plan plus the yearly plan spread over twelve months');
    assert_equals(360.0, (float) $totals['yearly'], 'the yearly figure is the monthly total times twelve');
});

wallos_test('with the setting on the monthly cost leaves yearly plans out', function () {
    $totals = monthly_cost_run_stats(true);

    assert_equals(10.0, (float) $totals['monthly'], 'only the monthly plan is left in the monthly cost');
    assert_equals(360.0, (float) $totals['yearly'], 'the yearly figure still counts the yearly plan');
});
