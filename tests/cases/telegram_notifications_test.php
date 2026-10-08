<?php
/*
  Telegram notifications can target a topic inside a group chat by sending
  message_thread_id. The payload builder is shared by the test button and both
  crons; the crons and endpoints are checked to use it, since they cannot be
  included here (they need PHPMailer and a live request).
*/

require_once WALLOS_ROOT . '/includes/telegram_helper.php';

wallos_test('the payload has no message_thread_id when no topic is set', function () {
    foreach ([null, '', '   '] as $empty) {
        assert_same(['chat_id' => '-1001', 'text' => 'Hi'], wallos_telegram_payload('-1001', 'Hi', $empty),
            'an empty topic sends to the general chat');
    }
});

wallos_test('a numeric topic ID is sent as an integer message_thread_id', function () {
    assert_same(['chat_id' => '-1001', 'text' => 'Hi', 'message_thread_id' => 123],
        wallos_telegram_payload('-1001', 'Hi', '123'), 'string topic becomes int');
    assert_same(123, wallos_telegram_payload('-1001', 'Hi', 123)['message_thread_id'], 'int topic is kept');
    assert_same(7, wallos_telegram_payload('-1001', 'Hi', ' 7 ')['message_thread_id'], 'whitespace is trimmed');
});

wallos_test('invalid or non-positive topic IDs are ignored', function () {
    foreach (['abc', '12a', '-5', '1.5', '0', '00'] as $bad) {
        assert_true(!isset(wallos_telegram_payload('-1001', 'Hi', $bad)['message_thread_id']),
            "topic '$bad' is not sent");
    }
});

wallos_test('the migration adds message_thread_id and it round-trips', function () {
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'alice');

    $db->exec("INSERT INTO telegram_notifications (enabled, bot_token, chat_id, user_id) VALUES (1, 't', '-1001', 1)");
    $row = $db->querySingle('SELECT * FROM telegram_notifications WHERE user_id = 1', true);
    assert_same('', $row['message_thread_id'], 'existing rows default to no topic');

    $db->exec("UPDATE telegram_notifications SET message_thread_id = '123' WHERE user_id = 1");
    assert_same('123', $db->querySingle('SELECT message_thread_id FROM telegram_notifications WHERE user_id = 1'),
        'topic ID is stored');

    $db->close();
});

wallos_test('every Telegram send path uses the shared payload builder', function () {
    foreach ([
        'endpoints/cronjobs/sendnotifications.php',
        'endpoints/cronjobs/sendcancellationnotifications.php',
        'endpoints/notifications/testtelegramnotifications.php',
    ] as $file) {
        $source = file_get_contents(WALLOS_ROOT . '/' . $file);
        assert_contains('wallos_telegram_payload(', $source, $file . ' builds its payload with the helper');
        assert_contains('telegram_helper.php', $source, $file . ' includes the helper');
    }

    foreach (['sendnotifications', 'sendcancellationnotifications'] as $cron) {
        assert_contains("row[\"message_thread_id\"]",
            file_get_contents(WALLOS_ROOT . "/endpoints/cronjobs/$cron.php"), "$cron.php reads the stored topic");
    }
});

wallos_test('the save endpoint rejects a non-numeric topic and persists the column', function () {
    $source = file_get_contents(WALLOS_ROOT . '/endpoints/notifications/savetelegramnotifications.php');
    assert_contains('ctype_digit($message_thread_id)', $source, 'topic is validated as digits');
    assert_contains('message_thread_id = :message_thread_id', $source, 'update writes the topic');
    assert_contains(':message_thread_id, :userId', $source, 'insert writes the topic');
});

wallos_test('bot token and chat ID are mandatory in the UI and the requests, the topic is not', function () {
    $settings = file_get_contents(WALLOS_ROOT . '/settings.php');
    $js = file_get_contents(WALLOS_ROOT . '/scripts/notifications.js');

    assert_true(preg_match('/id="telegrambottoken"[^>]*\\brequired\\b/', $settings) === 1, 'bot token input is required');
    assert_true(preg_match('/id="telegramchatid"[^>]*\\brequired\\b/', $settings) === 1, 'chat ID input is required');
    assert_true(preg_match('/id="telegramthreadid"[^>]*\\brequired\\b/', $settings) === 0, 'topic input is optional');
    assert_equals(2, substr_count($js, 'telegramRequiredFieldsValid()') - 1, 'save and test both validate before sending');

    foreach (['save' => ['bot_token', 'chat_id'], 'test' => ['bottoken', 'chatid']] as $kind => $keys) {
        $source = file_get_contents(WALLOS_ROOT . "/endpoints/notifications/{$kind}telegramnotifications.php");
        foreach ($keys as $key) {
            assert_contains('$data["' . $key . '"] == ""', $source, "$kind endpoint rejects an empty $key");
        }
        assert_not_contains('$data["message_thread_id"] == ""', $source, "$kind endpoint does not require the topic");
        assert_not_contains('$data["messagethreadid"] == ""', $source, "$kind endpoint does not require the topic");
    }
});

wallos_test('mandatory notification inputs are marked with an asterisk via CSS', function () {
    $css = file_get_contents(WALLOS_ROOT . '/styles/styles.css');
    assert_contains('.form-group-inline:has(> input:required)::after', $css, 'required inputs get an asterisk');
});

wallos_test('direct-message chats (positive IDs) never get a topic', function () {
    foreach (['12345678', '+12345678', ' 42 '] as $dm) {
        assert_true(!wallos_telegram_chat_supports_topics($dm), "$dm is a direct message");
        assert_true(!isset(wallos_telegram_payload($dm, 'Hi', '123')['message_thread_id']),
            "topic is dropped for chat $dm");
    }
    foreach (['-1001234567890', '-100123', '@mychannel'] as $group) {
        assert_true(wallos_telegram_chat_supports_topics($group), "$group can have topics");
    }
    assert_same(123, wallos_telegram_payload('-1001234567890', 'Hi', '123')['message_thread_id'], 'group topic kept');

    $source = file_get_contents(WALLOS_ROOT . '/endpoints/notifications/savetelegramnotifications.php');
    assert_contains('wallos_telegram_chat_supports_topics($chat_id)', $source, 'save rejects a topic on a direct-message chat');
});
