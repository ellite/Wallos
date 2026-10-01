<?php

require_once WALLOS_ROOT . '/tests/fixtures/pushover_integration.php';

wallos_test('pushover real cron maps account data and batches only selected reminders offline', function () {
    $scratch = WALLOS_TEST_TMP . '/pushover-cron-' . uniqid('', true);
    mkdir($scratch, 0700, true);
    try {
        foreach (['includes', 'libs', 'endpoints/cronjobs'] as $directory) {
            pushover_cron_copy_directory(WALLOS_ROOT . '/' . $directory, $scratch . '/' . $directory);
        }
        mkdir($scratch . '/db');
        copy(wallos_test_database(), $scratch . '/db/wallos.db');
        $db = new SQLite3($scratch . '/db/wallos.db');
        wallos_test_create_user($db, 1, 'alice');
        wallos_test_create_user($db, 2, 'bob');
        $db->exec("UPDATE user SET language = CASE id WHEN 1 THEN 'de' ELSE 'en' END");
        $db->exec("UPDATE admin SET server_url = 'https://wallos.example/billing'");
        foreach (['household', 'categories', 'payment_methods', 'subscriptions', 'notification_settings', 'pushover_notifications'] as $table) {
            $db->exec('DELETE FROM ' . $table);
        }
        $db->exec("INSERT INTO household (id, name, user_id) VALUES (1, 'Alice', 1), (2, 'Bob', 2)");
        $db->exec("INSERT INTO categories (id, name, user_id) VALUES (1, 'News', 1), (2, 'Music', 2)");
        $db->exec("INSERT INTO payment_methods (id, name, user_id) VALUES (1, 'Alice Visa', 1), (2, 'Bob Private Bank', 2)");
        $db->exec("INSERT INTO notification_settings (user_id, days) VALUES (1, 0), (2, 0)");
        $db->exec("INSERT INTO pushover_notifications (user_id, enabled, token, user_key)
            VALUES (1, 1, 'fake-token-a', 'fake-key-a'), (2, 1, 'fake-token-b', 'fake-key-b')");

        $today = gmdate('Y-m-d');
        $tomorrow = (new DateTimeImmutable('tomorrow', new DateTimeZone('UTC')))->format('Y-m-d');
        $rows = [
            // owner, name, notify, inactive, date, payment method, auto renewal
            [1, 'Annual &amp; News', 1, 0, $today, 1, 1],
            [1, 'Manual service', 1, 0, $today, 1, 0],
            [1, 'Foreign method reference', 1, 0, $today, 2, 1],
            [1, 'Notifications off', 0, 0, $today, 1, 1],
            [1, 'Inactive service', 1, 1, $today, 1, 1],
            [1, 'Not due yet', 1, 0, $tomorrow, 1, 1],
            [2, 'Music subscription', 1, 0, $today, 2, 1],
        ];
        $stmt = $db->prepare('INSERT INTO subscriptions
            (user_id, name, price, currency_id, next_payment, cycle, frequency,
             payment_method_id, payer_user_id, category_id, notify, inactive,
             notify_days_before, auto_renew, url, notes)
            VALUES (:owner, :name, 150, :currency, :date, 4, 1,
                :method, :owner, :owner, :notify, :inactive, -1, :auto, \'\', \'\')');
        foreach ($rows as [$owner, $name, $notify, $inactive, $date, $method, $auto]) {
            foreach (['owner' => $owner, 'currency' => wallos_test_currency_id($owner, 0),
                'method' => $method, 'notify' => $notify, 'inactive' => $inactive, 'auto' => $auto] as $key => $value) {
                $stmt->bindValue(':' . $key, $value, SQLITE3_INTEGER);
            }
            $stmt->bindValue(':name', $name, SQLITE3_TEXT);
            $stmt->bindValue(':date', $date, SQLITE3_TEXT);
            $stmt->execute();
        }
        $db->close();

        $result = pushover_integration_run($scratch, 'endpoints/cronjobs/sendnotifications.php');
        assert_same(0, $result['exit'], 'actual cron exits successfully: ' . $result['stderr'] . $result['stdout']);
        assert_same('', $result['stderr'], 'no warnings in actual cron');
        $deliveries = $result['deliveries'];
        assert_same(2, count($deliveries), 'one bundled delivery per account');
        if (count($deliveries) !== 2) {
            return;
        }
        $byToken = [];
        foreach ($deliveries as $delivery) {
            assert_same('https://api.pushover.net/1/messages.json', $delivery['url'], 'correct provider endpoint');
            $payload = $delivery['payload'];
            $byToken[$payload['token']] = $payload;
            assert_same('https://wallos.example/billing/subscriptions.php', $payload['url'], 'configured base path');
        }
        $alice = $byToken['fake-token-a'];
        $bob = $byToken['fake-token-b'];
        assert_same('fake-key-a', $alice['user'], 'first account credentials');
        assert_same('3 anstehende Zahlungen', $alice['title'], 'German payer batch');
        foreach (['Annual & News', 'Manual service', 'Foreign method reference', '150,00',
            'Alice Visa', 'Manuelle Verlängerung erforderlich'] as $text) {
            assert_contains($text, $alice['message'], 'cron passes ' . $text);
        }
        foreach (['Bob Private Bank', 'Music subscription', 'Notifications off', 'Inactive service', 'Not due yet'] as $text) {
            assert_not_contains($text, $alice['message'], 'cron excludes ' . $text);
        }
        assert_same('fake-key-b', $bob['user'], 'second account credentials');
        assert_same('Music subscription · Payment today', $bob['title'], 'second account language');
        assert_contains('Bob Private Bank', $bob['message'], 'second account own method');
        assert_contains('150.00', $bob['message'], 'second account number formatting');
        assert_not_contains('Alice Visa', $bob['message'], 'first account method does not leak');

        // Exercise the actual period-start branch, both beside due reminders
        // and with no selected reminders. Anchor today for a deterministic start.
        $db = new SQLite3($scratch . '/db/wallos.db');
        $db->exec("UPDATE notification_settings SET period_summary_at_period_start = 1");
        $db->exec("UPDATE user SET budget_period_type = 'weekly', budget_period_anchor_date = '$today', period_budget = 1000");
        $db->close();
        $combined = pushover_integration_run($scratch, 'endpoints/cronjobs/sendnotifications.php');
        assert_same(0, $combined['exit'], 'combined period-start cron exits');
        assert_same('', $combined['stderr'], 'combined cron has no warnings');
        assert_same(2, count($combined['deliveries']), 'period summary stays in payer bundle');
        $byToken = [];
        foreach ($combined['deliveries'] as $delivery) {
            $byToken[$delivery['payload']['token']] = $delivery['payload'];
        }
        $german = $byToken['fake-token-a']['message'] ?? '';
        $english = $byToken['fake-token-b']['message'] ?? '';
        assert_contains('Annual & News', $german, 'combined reminder retained');
        assert_contains('Betrag für Zahlungszeitraum', $german, 'German summary label');
        assert_contains('Verbleibend', $german, 'German remaining label');
        assert_contains('750,00', $german, 'German period sum includes all active scheduled costs');
        assert_contains('Amount for Pay Period', $english, 'second account summary label');

        $db = new SQLite3($scratch . '/db/wallos.db');
        $db->exec('UPDATE subscriptions SET notify = 0');
        $db->close();
        $summaryOnly = pushover_integration_run($scratch, 'endpoints/cronjobs/sendnotifications.php');
        assert_same(0, $summaryOnly['exit'], 'summary-only cron exits');
        assert_same('', $summaryOnly['stderr'], 'summary-only cron has no warnings');
        assert_same(2, count($summaryOnly['deliveries']), 'both accounts receive summary without due reminders');
        foreach ($summaryOnly['deliveries'] as $delivery) {
            $payload = $delivery['payload'];
            assert_not_contains('Annual & News', $payload['message'], 'no disabled individual reminder');
            if ($payload['token'] === 'fake-token-a') {
                assert_contains('Budgetzeitraum', $payload['title'], 'German summary-only title');
                assert_contains('Betrag für Zahlungszeitraum', $payload['message'], 'German summary-only text');
            }
        }
    } finally {
        pushover_cron_remove_directory($scratch);
    }
});
