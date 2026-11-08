<?php
/* The notification crons run without a session, so their text has to come from
   the recipient's language file, not from literals in the cron. */

wallos_test('the notification crons carry no hard-coded English text', function () {
    $renewal = file_get_contents(WALLOS_ROOT . '/endpoints/cronjobs/sendnotifications.php');
    $cancellation = file_get_contents(WALLOS_ROOT . '/endpoints/cronjobs/sendcancellationnotifications.php');

    foreach (["Subject = 'Wallos Notification'", "\$title = 'Wallos Notification'", "'title' => 'Wallos Notification'"] as $literal) {
        assert_not_contains($literal, $renewal, 'renewal cron: ' . $literal);
    }
    foreach (['up for cancellation', "Subject = 'Wallos Cancellation Notification'", '" for "'] as $literal) {
        assert_not_contains($literal, $cancellation, 'cancellation cron: ' . $literal);
    }
});

wallos_test('the cancellation cron loads each recipient\'s language', function () {
    $cancellation = file_get_contents(WALLOS_ROOT . '/endpoints/cronjobs/sendcancellationnotifications.php');

    assert_contains('SELECT id, username, language FROM user', $cancellation, 'the query reads the language');
    assert_contains('resolve_language($userToNotify[\'language\']', $cancellation, 'the language is resolved per user');
    assert_contains("includes/i18n/' . \$userLanguage . '.php'", $cancellation, 'the language file is loaded per user');
});

wallos_test('every language has the notification keys', function () {
    $keys = [
        'wallos_notification', 'wallos_cancellation_notification',
        'notification_renewals_intro', 'notification_renewals_intro_named', 'notification_subscription_line',
        'notification_due_today', 'notification_due_tomorrow', 'notification_due_in_days',
        'notification_cancellation_intro', 'notification_cancellation_intro_named', 'notification_cancellation_line',
    ];
    $missing = [];
    foreach (glob(WALLOS_ROOT . '/includes/i18n/*.php') as $file) {
        $language = basename($file, '.php');
        if (in_array($language, ['getlang', 'languages'], true)) {
            continue;
        }
        $i18n = [];
        include $file;
        foreach ($keys as $key) {
            if (!isset($i18n[$key])) {
                $missing[] = $language . ':' . $key;
            }
        }
    }
    assert_same([], $missing, 'missing keys: ' . implode(', ', $missing));
});

wallos_test('the translated notification formats keep their placeholders', function () {
    $expected = [
        'notification_renewals_intro_named' => ['%s' => 1],
        'notification_subscription_line' => ['%s' => 3],
        'notification_due_in_days' => ['%d' => 1],
        'notification_cancellation_intro_named' => ['%s' => 1],
        'notification_cancellation_line' => ['%s' => 2],
    ];
    $broken = [];
    foreach (glob(WALLOS_ROOT . '/includes/i18n/*.php') as $file) {
        $language = basename($file, '.php');
        if (in_array($language, ['getlang', 'languages'], true)) {
            continue;
        }
        $i18n = [];
        include $file;
        foreach ($expected as $key => $placeholders) {
            foreach ($placeholders as $placeholder => $count) {
                if (substr_count($i18n[$key] ?? '', $placeholder) !== $count) {
                    $broken[] = $language . ':' . $key;
                }
            }
        }
    }
    assert_same([], $broken, 'wrong placeholders: ' . implode(', ', $broken));
});
