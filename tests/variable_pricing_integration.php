<?php
/*
 * Exercise the real save/API endpoints and statistics against disposable data.
 * php tests/variable_pricing_integration.php          runs every case
 * php tests/variable_pricing_integration.php <case>   runs one case
 */

$cases = ['save', 'save-failure', 'limit', 'invalid', 'cycle', 'currency', 'toggle', 'clone', 'clone-failure', 'stats', 'api', 'list'];
$case = $argv[1] ?? null;
if ($case === null) {
    $failed = 0;
    foreach ($cases as $name) {
        $process = proc_open([PHP_BINARY, __FILE__, $name], [1 => STDOUT, 2 => STDERR], $pipes);
        if (!$process || proc_close($process) !== 0) {
            $failed++;
        }
    }
    exit($failed === 0 ? 0 : 1);
}
if (!in_array($case, $cases, true)) {
    throw new InvalidArgumentException('Unknown integration case');
}

require __DIR__ . '/bootstrap.php';
$databaseFile = wallos_test_database();
$db = new SQLite3($databaseFile);
wallos_test_create_user($db, 1, 'owner');
$db->exec('DELETE FROM categories WHERE user_id = 1');
$db->exec('DELETE FROM household WHERE user_id = 1');
$db->exec('DELETE FROM payment_methods WHERE user_id = 1');
$db->exec("INSERT INTO categories (id, user_id, name) VALUES (10000, 1, 'Other')");
$db->exec("INSERT INTO household (id, user_id, name) VALUES (10000, 1, 'Owner')");
$db->exec("INSERT INTO payment_methods (id, user_id, name, icon) VALUES (10000, 1, 'Bank', '')");
$db->exec("UPDATE user SET api_key = 'integration-key' WHERE id = 1");
$month = date('Y-m');
$paymentDate = $month . '-15';
$db->exec("INSERT INTO subscriptions (id, user_id, name, price, currency_id, next_payment, cycle, frequency,
                                      inactive, auto_renew, has_variable_price)
           VALUES (1, 1, 'Energy', 80, 9010, '$paymentDate', 3, 1, 0, 1, 1)");
$db->exec("INSERT INTO subscription_price_history (subscription_id, period, price)
           VALUES (1, '$month', 130)");

function copy_integration_directory($source, $destination)
{
    mkdir($destination, 0700, true);
    foreach (new DirectoryIterator($source) as $entry) {
        if ($entry->isDot()) {
            continue;
        }
        $target = $destination . '/' . $entry->getFilename();
        if ($entry->isDir()) {
            copy_integration_directory($entry->getPathname(), $target);
        } else {
            copy($entry->getPathname(), $target);
        }
    }
}

function remove_integration_directory($directory)
{
    foreach (new DirectoryIterator($directory) as $entry) {
        if ($entry->isDot()) {
            continue;
        }
        if ($entry->isDir() && !$entry->isLink()) {
            remove_integration_directory($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($directory);
}

$sandbox = WALLOS_TEST_TMP . '/integration-' . uniqid();
mkdir($sandbox . '/db', 0700, true);
mkdir($sandbox . '/endpoints/subscription', 0700, true);
copy_integration_directory(WALLOS_ROOT . '/includes', $sandbox . '/includes');
copy_integration_directory(WALLOS_ROOT . '/libs', $sandbox . '/libs');
if ($case === 'list') {
    mkdir($sandbox . '/images', 0700);
    copy_integration_directory(WALLOS_ROOT . '/images/siteicons', $sandbox . '/images/siteicons');
    $db->exec('UPDATE subscriptions SET currency_id = 9011, category_id = 10000, payer_user_id = 10000,
                                       payment_method_id = 10000 WHERE id = 1');
    $db->exec('DELETE FROM settings WHERE user_id = 1');
    $db->exec("INSERT INTO settings (user_id, dark_theme, color_theme, monthly_price, convert_currency, show_original_price)
               VALUES (1, 0, 'blue', 0, 1, 1)");
}
$db->close();
copy($databaseFile, $sandbox . '/db/wallos.db');
chdir($sandbox . '/endpoints/subscription');
session_start();
$_SESSION = ['loggedin' => true, 'userId' => 1, 'csrf_token' => 'integration-csrf'];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_FILES = [];
$_POST = [
    'id' => 1, 'name' => 'Energy', 'price' => 90, 'currency_id' => 9010, 'frequency' => 1,
    'cycle' => 3, 'next_payment' => $paymentDate, 'auto_renew' => 'on', 'start_date' => '',
    'payment_method_id' => 10000, 'payer_user_id' => 10000, 'category_id' => 10000, 'notes' => '',
    'url' => '', 'logo-url' => '', 'notify_days_before' => -1, 'replacement_subscription_id' => 0,
    'has_variable_price' => 'on', 'csrf_token' => 'integration-csrf',
    'price_history' => json_encode([['period' => $month, 'price' => 140, 'note' => 'actual']]),
];
if ($case === 'save-failure' || $case === 'clone-failure') {
    $db = new SQLite3($sandbox . '/db/wallos.db');
    $db->exec("CREATE TRIGGER fail_history BEFORE INSERT ON subscription_price_history
               BEGIN SELECT RAISE(ABORT, 'simulated storage failure'); END");
    $db->close();
} elseif ($case === 'limit') {
    $rows = [];
    for ($i = 0; $i < 241; $i++) {
        $rows[] = ['period' => sprintf('%04d-%02d', 2000 + intdiv($i, 12), 1 + $i % 12), 'price' => $i];
    }
    $_POST['price_history'] = json_encode($rows);
} elseif ($case === 'invalid') {
    $_POST['price_history'] = '[{"period":"2026-13","price":100}]';
} elseif ($case === 'cycle') {
    $_POST['cycle'] = 4;
} elseif ($case === 'currency') {
    $_POST['currency_id'] = 9011;
} elseif ($case === 'toggle') {
    unset($_POST['has_variable_price']);
}

ob_start();
register_shutdown_function(function () use ($case, $sandbox, $month, $databaseFile) {
    $output = ob_get_clean();
    $response = json_decode($output, true);
    // Some endpoints exit before closing the connection. Release their SQLite
    // handles before removing the disposable database, including on PHP-Wasm.
    foreach ($GLOBALS as $value) {
        try {
            if ($value instanceof SQLite3Result) {
                @$value->finalize();
            } elseif ($value instanceof SQLite3Stmt) {
                @$value->close();
            }
        } catch (Throwable $error) {
            // A handle already closed by the endpoint needs no further cleanup.
        }
    }
    try {
        if (($GLOBALS['db'] ?? null) instanceof SQLite3) {
            @$GLOBALS['db']->close();
        }
    } catch (Throwable $error) {
        // The endpoint already closed the connection.
    }
    $database = new SQLite3($sandbox . '/db/wallos.db');
    $subscription = $database->querySingle('SELECT * FROM subscriptions WHERE id = 1', true);
    $actual = (float) $database->querySingle('SELECT price FROM subscription_price_history WHERE subscription_id = 1');
    $success = false;
    if ($case === 'save') {
        $success = ($response['status'] ?? '') === 'Success' && (float) $subscription['price'] === 90.0 && $actual === 140.0;
    } elseif (in_array($case, ['save-failure', 'limit', 'invalid', 'cycle', 'currency'], true)) {
        $success = ($response['status'] ?? '') === 'Error' && (float) $subscription['price'] === 80.0
            && (int) $subscription['currency_id'] === 9010 && $actual === 130.0;
    } elseif ($case === 'toggle') {
        $success = ($response['status'] ?? '') === 'Success' && (int) $subscription['has_variable_price'] === 0
            && $actual === 130.0;
    } elseif ($case === 'stats') {
        $success = ($response['monthly'] ?? null) === 150 && ($response['budget'] ?? null) === 140;
    } elseif ($case === 'api') {
        $success = ($response['success'] ?? false) && ($response['monthly_cost'] ?? '') === '140.00';
    } elseif ($case === 'clone') {
        $cloneId = (int) ($response['id'] ?? 0);
        $success = ($response['success'] ?? false) && $cloneId !== 1
            && (float) $database->querySingle('SELECT price FROM subscription_price_history WHERE subscription_id = ' . $cloneId) === 130.0;
    } elseif ($case === 'clone-failure') {
        $success = ($response['success'] ?? true) === false
            && (int) $database->querySingle('SELECT COUNT(*) FROM subscriptions') === 1 && $actual === 130.0;
    } elseif ($case === 'list') {
        $success = str_contains($output, '118.18') && str_contains($output, '130.00')
            && !str_contains($output, '80.00');
    }
    $database->close();
    chdir(WALLOS_ROOT);
    remove_integration_directory($sandbox);
    unlink($databaseFile);
    echo ($success ? 'PASS ' : 'FAIL ') . $case . "\n";
    if (!$success) {
        echo $output . "\n";
        exit(1);
    }
});

if ($case === 'clone' || $case === 'clone-failure') {
    // CLI has no HTTP request body. Supply php://input while executing the real
    // authenticated clone endpoint, restoring the built-in wrapper on close.
    class IntegrationRequestBody {
        public $context;
        private $position = 0;
        public function stream_open($path, $mode, $options, &$openedPath)
        {
            return $path === 'php://input';
        }
        public function stream_read($count)
        {
            $chunk = substr('{"id":1}', $this->position, $count);
            $this->position += strlen($chunk);
            return $chunk;
        }
        public function stream_eof()
        {
            return $this->position >= strlen('{"id":1}');
        }
        public function stream_stat()
        {
            return [];
        }
        public function stream_close()
        {
            stream_wrapper_restore('php');
        }
    }
    stream_wrapper_unregister('php');
    stream_wrapper_register('php', IntegrationRequestBody::class);
    require WALLOS_ROOT . '/endpoints/subscription/clone.php';
} elseif ($case === 'list') {
    require WALLOS_ROOT . '/endpoints/subscriptions/get.php';
} elseif ($case === 'stats' || $case === 'api') {
    $db = new SQLite3($sandbox . '/db/wallos.db');
    $db->exec("INSERT INTO subscriptions (user_id, name, price, currency_id, next_payment, cycle, frequency,
                                          inactive, auto_renew, has_variable_price)
               VALUES (1, 'Fixed monthly', 11, 9011, '$paymentDate', 3, 1, 0, 1, 0)");
    $annualDate = (new DateTime('first day of next month'))->format('Y-m') . '-15';
    $db->exec("INSERT INTO subscriptions (user_id, name, price, currency_id, next_payment, cycle, frequency,
                                          inactive, auto_renew, has_variable_price)
               VALUES (1, 'Fixed annual', 120, 9010, '$annualDate', 4, 1, 0, 1, 0)");
    $db->exec("INSERT INTO last_exchange_update (user_id, date) VALUES (1, '" . date('Y-m-d') . "')");
    $db->exec('UPDATE subscriptions SET category_id = 10000, payer_user_id = 10000, payment_method_id = 10000 WHERE user_id = 1');
    if ($case === 'stats') {
        $userId = 1;
        $userData = $db->querySingle('SELECT * FROM user WHERE id = 1', true);
        require $sandbox . '/includes/i18n/languages.php';
        require $sandbox . '/includes/i18n/getlang.php';
        require $sandbox . '/includes/i18n/en.php';
        $_GET = [];
        require $sandbox . '/includes/stats_calculations.php';
        $end = (new DateTime($month . '-01'))->modify('last day of this month');
        $budget = computeAmountNeededInPeriod($subscriptions, new DateTime($month . '-01'), $end, $db, 1);
        echo json_encode(['monthly' => $totalCostPerMonth, 'budget' => $budget]);
    } else {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_REQUEST = ['api_key' => 'integration-key', 'month' => (int) date('n'), 'year' => date('Y')];
        require WALLOS_ROOT . '/api/subscriptions/get_monthly_cost.php';
    }
} else {
    require WALLOS_ROOT . '/endpoints/subscription/add.php';
}
