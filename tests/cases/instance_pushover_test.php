<?php
/*
  One Pushover application for the whole installation.

  An application token names the application a message is sent from, not the
  person it reaches: the user key is what names the person, and it stays on
  each user's own row. Before this, every member of a household had to register
  an application of their own at pushover.net, so notifications arrived from as
  many applications as there are people in the house.
*/

require_once WALLOS_ROOT . '/includes/instance_config.php';

function instance_pushover_set($db, $token)
{
    $stmt = $db->prepare('UPDATE admin SET pushover_token = :token WHERE id = 1');
    $stmt->bindValue(':token', $token, SQLITE3_TEXT);
    $stmt->execute();
}

wallos_test('an account without an application of its own sends through the instance one', function () {
    $db = wallos_test_open_database();

    instance_pushover_set($db, 'instance-token');

    assert_same('instance-token', wallos_pushover_token($db, ''), 'an empty field is the instance application');
    assert_same('instance-token', wallos_pushover_token($db, '   '), 'and so is a field of spaces');

    $db->close();
});

wallos_test('an account that registered its own application keeps it', function () {
    $db = wallos_test_open_database();

    instance_pushover_set($db, 'instance-token');

    assert_same('own-token', wallos_pushover_token($db, 'own-token'),
        'the token on the account wins, which is every installation configured today');

    $db->close();
});

wallos_test('an installation without an instance application behaves as it did', function () {
    $db = wallos_test_open_database();

    assert_same('', wallos_pushover_token($db, ''), 'no token anywhere is still no token');
    assert_same('own-token', wallos_pushover_token($db, 'own-token'), 'and an account with its own is unaffected');

    $db->close();
});

wallos_test('the user key is never shared', function () {
    // The boundary as a case rather than a comment: the instance configuration
    // has no user key to hand out, so a change that starts sharing one has to
    // change this first. A shared user key would send every household member's
    // reminders to one person's devices.
    $db = wallos_test_open_database();

    instance_pushover_set($db, 'instance-token');
    $settings = wallos_get_admin_settings($db);

    assert_true(!isset($settings['pushover_user_key']), 'there is no instance user key column at all');

    $db->close();
});

wallos_test('the environment owns the application token when it is set', function () {
    $db = wallos_test_open_database();

    instance_pushover_set($db, 'database-token');
    putenv('WALLOS_PUSHOVER_TOKEN=environment-token');

    $configuration = wallos_get_effective_admin_configuration($db);

    assert_same('environment-token', $configuration['settings']['pushover_token'], 'the variable wins over the row');
    assert_true(isset($configuration['managed_fields']['pushover_token']),
        'and the page is told the field is managed, so it is never rendered as a value');

    putenv('WALLOS_PUSHOVER_TOKEN');
    $db->close();
});

wallos_test('every place that sends to Pushover resolves the application the same way', function () {
    foreach ([
        'endpoints/cronjobs/sendnotifications.php',
        'endpoints/cronjobs/sendcancellationnotifications.php',
        'endpoints/notifications/testpushovernotifications.php',
    ] as $path) {
        $source = file_get_contents(WALLOS_ROOT . '/' . $path);

        assert_contains('wallos_pushover_token(', $source, $path . ' resolves the application token');
    }
});
