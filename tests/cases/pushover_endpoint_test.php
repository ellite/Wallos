<?php

require_once WALLOS_ROOT . '/tests/fixtures/pushover_integration.php';

wallos_test('pushover settings endpoint previews fictional account-localized data and enforces request guards offline', function () {
    $scratch = WALLOS_TEST_TMP . '/pushover-endpoint-' . uniqid('', true);
    mkdir($scratch, 0700, true);
    try {
        foreach (['includes', 'libs', 'endpoints/notifications'] as $directory) {
            pushover_cron_copy_directory(WALLOS_ROOT . '/' . $directory, $scratch . '/' . $directory);
        }
        mkdir($scratch . '/db');
        copy(wallos_test_database(), $scratch . '/db/wallos.db');
        $db = new SQLite3($scratch . '/db/wallos.db');
        wallos_test_create_user($db, 1, 'alice');
        $db->exec("UPDATE user SET language = 'de', main_currency = " . wallos_test_currency_id(1, 1));
        $db->exec("UPDATE admin SET server_url = 'https://wallos.example/settings-test'");
        $db->exec("INSERT INTO subscriptions (user_id, name, price) VALUES (1, 'PRIVATE LIVE SUBSCRIPTION', 9876)");
        $db->exec("INSERT INTO pushover_notifications (user_id, enabled, token, user_key)
            VALUES (1, 1, 'stored-credentials', 'stored-user')");
        $db->close();

        $script = 'endpoints/notifications/testpushovernotifications.php';
        $success = pushover_integration_run($scratch, $script, [], true);
        assert_same(0, $success['exit'], 'settings endpoint exits: ' . $success['stderr']);
        assert_same('', $success['stderr'], 'settings endpoint has no warnings');
        $response = json_decode($success['stdout'], true);
        assert_same(true, $response['success'] ?? null, 'provider acceptance reported');
        assert_same(1, count($success['deliveries']), 'exactly one sample push');
        $payload = $success['deliveries'][0]['payload'] ?? [];
        assert_same('posted-fake-token', $payload['token'] ?? null, 'unsaved posted token is tested');
        assert_same('posted-fake-key', $payload['user'] ?? null, 'unsaved posted user key is tested');
        assert_contains('Test · Beispielabo · Zahlung morgen', $payload['title'] ?? '', 'stored German language overrides English browser cookie');
        assert_contains('9,99', $payload['message'] ?? '', 'account-localized fictional amount');
        assert_contains('$', $payload['message'] ?? '', 'account main currency USD, not hardcoded EUR');
        assert_contains('Visa', $payload['message'] ?? '', 'payment method preview');
        assert_not_contains('PRIVATE LIVE SUBSCRIPTION', json_encode($payload), 'preview never selects real subscriptions');
        assert_same('https://wallos.example/settings-test/subscriptions.php', $payload['url'] ?? null, 'configured link');

        $failed = pushover_integration_run($scratch, $script, [
            'WALLOS_TEST_PUSHOVER_HTTP' => '400', 'WALLOS_TEST_PUSHOVER_RESPONSE' => '{"status":0,"errors":["fake rejection"]}',
        ], true);
        assert_same('', $failed['stderr'], 'rejection handled cleanly');
        $response = json_decode($failed['stdout'], true);
        assert_same(false, $response['success'] ?? null, 'provider failure is not reported as success');
        assert_same(1, count($failed['deliveries']), 'failure does not automatically retry');

        foreach ([
            'GET' => ['WALLOS_TEST_METHOD' => 'GET'],
            'CSRF' => ['WALLOS_TEST_CSRF' => 'wrong-token'],
            'logged out' => ['WALLOS_TEST_AUTHENTICATED' => 'no'],
            'missing token' => ['WALLOS_TEST_REQUEST_BODY' => '{"user_key":"posted-key"}'],
        ] as $case => $environment) {
            $blocked = pushover_integration_run($scratch, $script, $environment, true);
            assert_same(0, $blocked['exit'], $case . ' request exits cleanly');
            assert_same('', $blocked['stderr'], $case . ' request has no warnings');
            $response = json_decode($blocked['stdout'], true);
            assert_same(false, $response['success'] ?? null, $case . ' request rejected');
            assert_same([], $blocked['deliveries'], $case . ' request cannot send');
        }
    } finally {
        pushover_cron_remove_directory($scratch);
    }
});
