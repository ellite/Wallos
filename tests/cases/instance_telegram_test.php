<?php
/*
  One Telegram bot for the whole installation.

  A bot token names a bot, not a person: one bot can message everybody who has
  started a chat with it. What is personal is the chat id. Before this, every
  member of a household had to create their own bot through BotFather and paste
  its token into Wallos, which is the kind of setup step that ends with one
  person in the house getting notifications and nobody else.

  The rule is the one the field already implied: a user who has a token of
  their own keeps using it, and an empty token means the instance bot, if the
  deployment configured one.
*/

require_once WALLOS_ROOT . '/includes/instance_config.php';

function instance_telegram_set($db, $token)
{
    $stmt = $db->prepare('UPDATE admin SET telegram_bot_token = :token WHERE id = 1');
    $stmt->bindValue(':token', $token, SQLITE3_TEXT);
    $stmt->execute();
}

wallos_test('an account without a bot token of its own sends through the instance bot', function () {
    $db = wallos_test_open_database();

    instance_telegram_set($db, 'instance-token');

    assert_same('instance-token', wallos_telegram_bot_token($db, ''),
        'an empty token is the instance bot');
    assert_same('instance-token', wallos_telegram_bot_token($db, '   '),
        'and so is a field with nothing but space in it');

    $db->close();
});

wallos_test('an account that has its own bot keeps it', function () {
    $db = wallos_test_open_database();

    instance_telegram_set($db, 'instance-token');

    assert_same('own-token', wallos_telegram_bot_token($db, 'own-token'),
        'the token on the account wins, which is every installation configured today');

    $db->close();
});

wallos_test('an installation without an instance bot behaves as it did', function () {
    $db = wallos_test_open_database();

    assert_same('', wallos_telegram_bot_token($db, ''),
        'no token anywhere is still no token');
    assert_same('own-token', wallos_telegram_bot_token($db, 'own-token'),
        'and an account with its own is unaffected');

    $db->close();
});

wallos_test('the environment owns the token when it is set', function () {
    $db = wallos_test_open_database();

    instance_telegram_set($db, 'database-token');
    putenv('WALLOS_TELEGRAM_BOT_TOKEN=environment-token');

    $configuration = wallos_get_effective_admin_configuration($db);

    assert_same('environment-token', $configuration['settings']['telegram_bot_token'],
        'the variable wins over the row');
    assert_true(isset($configuration['managed_fields']['telegram_bot_token']),
        'and the page is told the field is managed, so it renders read-only');

    putenv('WALLOS_TELEGRAM_BOT_TOKEN');
    $db->close();
});

wallos_test('every place that sends a Telegram message resolves the token the same way', function () {
    $senders = [
        'endpoints/cronjobs/sendnotifications.php',
        'endpoints/cronjobs/sendcancellationnotifications.php',
        'endpoints/notifications/testtelegramnotifications.php',
    ];

    foreach ($senders as $path) {
        $source = file_get_contents(WALLOS_ROOT . '/' . $path);

        assert_contains('wallos_telegram_bot_token(', $source, $path . ' resolves the token');
    }

    // The test button matters as much as the cron here: resolved in one and not
    // the other, a user with no token of their own would be told the channel
    // does not work while it does, or the other way round.
    $source = file_get_contents(WALLOS_ROOT . '/endpoints/notifications/testtelegramnotifications.php');
    assert_not_contains('$botToken = $data["bottoken"];', $source,
        'the test button no longer sends whatever the form held');
});

wallos_test('a token the environment owns cannot be overwritten through the admin page', function () {
    $source = file_get_contents(WALLOS_ROOT . '/endpoints/admin/saveinstancetelegram.php');

    assert_contains("managed_fields']['telegram_bot_token']", $source,
        'the endpoint refuses a write to a field the environment owns');
});
