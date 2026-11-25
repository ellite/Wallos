<?php
require_once WALLOS_ROOT . '/includes/payment_schedule.php';

wallos_test('payment schedules reject coerced frequency and cycle values', function () {
    foreach (['0.0', '0', 0, '-1', ' 0', '+0', '0e0', 'abc', '', 367, [], true, 1.0, null] as $value) {
        assert_same(false, validate_frequency($value), 'invalid frequency rejected');
    }
    foreach (range(1, 366) as $value) {
        assert_true(validate_frequency($value) && validate_frequency((string)$value), 'valid integer accepted');
    }
    foreach (['2026-02-30', 'yesterday', '', '2026-1-01'] as $date) {
        assert_same(false, wallos_valid_payment_date($date), 'invalid date rejected');
    }
});

wallos_test('payment catch-up terminates and manual renewal advances future dates', function () {
    $now = new DateTime('2026-11-17');
    foreach ([[0, 1, '2020-01-01'], [-1, 1, '2020-01-01'], [1, 5, '2020-01-01'], [1, 1, 'invalid'], [1, 1, '0001-01-01']] as [$frequency, $cycle, $date]) {
        $rejected = false;
        try { wallos_advance_payment($date, $frequency, $cycle, $now); }
        catch (Throwable $e) { $rejected = true; }
        assert_true($rejected, 'unsafe schedule rejected');
    }
    assert_same('2026-11-17', wallos_advance_payment('2026-11-15', 1, 1, $now)->format('Y-m-d'), 'payment date');
    assert_same('2026-12-19', wallos_advance_payment('2026-12-18', 1, 1, $now, true)->format('Y-m-d'), 'payment date');
});

wallos_test('payment cron isolates poisoned rows and records completion', function () {
    $db = new SQLite3(':memory:');
    $db->exec('CREATE TABLE subscriptions (id INTEGER PRIMARY KEY, next_payment TEXT, frequency INTEGER, cycle INTEGER, auto_renew INTEGER DEFAULT 1, inactive INTEGER DEFAULT 0)');
    $db->exec('CREATE TABLE last_update_next_payment_date (date TEXT)');
    $db->exec("INSERT INTO subscriptions (id,next_payment,frequency,cycle) VALUES (1,'2026-11-01',1,1),(2,'2026-11-01',0,1),(3,'2026-11-01',-1,1),(4,'2026-11-01',1,5),(5,'0000-invalid',1,1),(6,'2026-11-01',1,1)");
    $source = file_get_contents(WALLOS_ROOT . '/endpoints/cronjobs/updatenextpayment.php');
    $start = strpos($source, '$query = "SELECT id, next_payment');
    $end = strpos($source, 'echo "Updated next payment dates"');
    $currentDate = new DateTime('2026-11-17');
    $currentDateString = $currentDate->format('Y-m-d');
    $started = microtime(true);
    eval(substr($source, $start, $end - $start));
    assert_true(microtime(true) - $started < 2, 'cron completes promptly');
    assert_same(2, $db->querySingle("SELECT COUNT(*) FROM subscriptions WHERE id IN (1,6) AND next_payment = '2026-11-17'"), 'healthy rows advance');
    assert_same('2026-11-17', $db->querySingle('SELECT date FROM last_update_next_payment_date'), 'completion recorded');
});

wallos_test('payment migration repairs legacy data idempotently', function () {
    $db = new SQLite3(':memory:');
    $db->exec('CREATE TABLE subscriptions (frequency INTEGER, cycle INTEGER, auto_renew INTEGER)');
    $db->exec('INSERT INTO subscriptions VALUES (NULL,1,1),(0,1,1),(-1,1,1),(2,5,1),(366,4,1)');
    include WALLOS_ROOT . '/migrations/000069.php';
    include WALLOS_ROOT . '/migrations/000069.php';
    assert_same(0, $db->querySingle('SELECT COUNT(*) FROM subscriptions WHERE frequency IS NULL OR frequency < 1 OR (cycle = 5 AND auto_renew = 1)'), 'legacy rows repaired');
    assert_same(366, $db->querySingle('SELECT frequency FROM subscriptions WHERE cycle = 4'), 'valid frequency preserved');
});

wallos_test('all monthly price calculators tolerate nonpositive frequencies', function () {
    foreach (['includes/stats_calculations.php', 'includes/list_subscriptions.php', 'endpoints/cronjobs/storetotalyearlycost.php', 'endpoints/cronjobs/generaterecommendations.php', 'endpoints/ai/generate_recommendations.php'] as $file) {
        $source = file_get_contents(WALLOS_ROOT . '/' . $file);
        $start = strpos($source, 'function getPricePerMonth(');
        $end = strpos($source, "\n}", $start) + 2;
        $function = str_replace('function getPricePerMonth', 'return function', substr($source, $start, $end - $start));
        $calculate = eval($function . ';');
        foreach ([0, -1] as $frequency) {
            foreach ([1,2,3,4,5] as $cycle) {
                assert_true(is_numeric($calculate($cycle, $frequency, 10)), $file . ' safe monthly price');
            }
        }
    }
});

wallos_test('monthly cost skips poisoned schedules and counts one-time purchases once', function () {
    $source = file_get_contents(WALLOS_ROOT . '/api/subscriptions/get_monthly_cost.php');
    $start = strpos($source, '    foreach ($subscriptions as $subscription) {');
    $end = strpos($source, '    $formatter =', $start);
    $year = 2026; $month = 11; $userCurrencyId = 1; $canConvertCurrency = false;
    $monthlyCost = 0;
    $subscriptions = [];
    foreach ([[1,1,'2026-11-01'],[0,1,'2026-11-01'],[-1,1,'2026-11-01'],[1,5,'2026-11-15'],[1,1,'bad'],[1,99,'2026-11-01'],[1,1,'0001-01-01']] as [$frequency,$cycle,$next]) {
        $subscriptions[] = ['frequency'=>$frequency,'cycle'=>$cycle,'next_payment'=>$next,'price'=>10,'currency_id'=>1];
    }
    eval(substr($source, $start, $end - $start));
    assert_same(310, $monthlyCost, '30 daily payments plus one purchase');
});

wallos_test('form write validation rejects forged schedules and normalizes one-time purchases', function () {
    $source = file_get_contents(WALLOS_ROOT . '/endpoints/subscription/add.php');
    $start = strpos($source, 'if (!validate_frequency($cycle, 1, 5))');
    $end = strpos($source, '$startDate', $start);
    // Rejections terminate the request; throw instead so each input can be tested.
    $fragment = substr($source, $start, $end - $start);
    $fragment = preg_replace('/die\(json_encode\((.*?)\)\);/s', 'throw new InvalidArgumentException();', $fragment);
    foreach ([[0,1],['0.0',1],[367,1],[[],1],[1,0],[1,'1.5'],[1,[]]] as [$frequency,$cycle]) {
        $nextPayment = '2026-11-17'; $autoRenew = true; $rejected = false;
        try { eval($fragment); } catch (InvalidArgumentException $e) { $rejected = true; }
        assert_true($rejected, 'forged schedule rejected');
    }
    $frequency = 0; $cycle = '5'; $autoRenew = true; $nextPayment = '2026-11-17';
    eval($fragment);
    assert_same(1, $frequency, 'one-time frequency');
    assert_same(false, $autoRenew, 'one-time renewal disabled');
});

wallos_test('API add and edit validate raw frequencies before integer coercion', function () {
    $source = file_get_contents(WALLOS_ROOT . '/api/subscriptions/set_subscriptions.php');
    preg_match_all('/if \(!validate_frequency\(\$frequency\)\) \{.*?\$frequency = \(int\)\$frequency;/s', $source, $matches);
    assert_same(2, count($matches[0]), 'both API actions validate');
    foreach ($matches[0] as $fragment) {
        $fragment = str_replace('exit;', 'throw new InvalidArgumentException();', $fragment);
        foreach (['0.0','0',0,'-1',' 0','+0','0e0','abc','',367,[]] as $frequency) {
            $rejected = false;
            ob_start();
            try { eval($fragment); } catch (InvalidArgumentException $e) { $rejected = true; }
            $response = json_decode(ob_get_clean(), true);
            assert_true($rejected && $response['success'] === false, 'API rejects invalid frequency');
            assert_same('Parameter "frequency" must be an integer between 1 and 366.', $response['message'], 'API error contract');
        }
        foreach ([1,366,'1','366'] as $frequency) {
            eval($fragment);
            assert_true(is_int($frequency) && $frequency >= 1 && $frequency <= 366, 'valid frequency normalized');
        }
    }
});

wallos_test('clone clamps corrupt schedules and disables one-time renewal', function () {
    $source = file_get_contents(WALLOS_ROOT . '/endpoints/subscription/clone.php');
    $start = strpos($source, "$" . "subscriptionToClone['cycle'] =");
    $end = strpos($source, '$query = "INSERT', $start);
    foreach ([[0,1,1],[999,1,366],[4,5,1]] as [$frequency,$cycle,$expected]) {
        $subscriptionToClone = ['frequency'=>$frequency,'cycle'=>$cycle,'auto_renew'=>1];
        eval(substr($source, $start, $end - $start));
        assert_same($expected, $subscriptionToClone['frequency'], 'clone frequency bounded');
        if ($cycle === 5) { assert_same(0, $subscriptionToClone['auto_renew'], 'one-time clone cannot renew'); }
    }
});
