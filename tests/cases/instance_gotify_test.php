<?php
/*
  One Gotify server for the whole installation.

  Only the address is shared. A Gotify application token is personal: it names
  the application a message arrives under, so each member of a household keeps
  their own and their notifications stay theirs. What nobody should have to
  look up is where the server is.
*/

require_once WALLOS_ROOT . '/includes/instance_config.php';

function instance_gotify_set($db, $server)
{
    $stmt = $db->prepare('UPDATE admin SET gotify_server = :server WHERE id = 1');
    $stmt->bindValue(':server', $server, SQLITE3_TEXT);
    $stmt->execute();
}

wallos_test('an account without a server of its own sends to the instance server', function () {
    $db = wallos_test_open_database();

    instance_gotify_set($db, 'https://gotify.example');

    assert_same('https://gotify.example', wallos_gotify_server($db, ''),
        'an empty field is the instance server');
    assert_same('https://gotify.example', wallos_gotify_server($db, '  '),
        'and so is a field with nothing but space in it');

    $db->close();
});

wallos_test('an account that has its own server keeps it', function () {
    $db = wallos_test_open_database();

    instance_gotify_set($db, 'https://gotify.example');

    assert_same('https://gotify.mine.example', wallos_gotify_server($db, 'https://gotify.mine.example'),
        'the address on the account wins, which is every installation configured today');

    $db->close();
});

wallos_test('an installation without an instance server behaves as it did', function () {
    $db = wallos_test_open_database();

    assert_same('', wallos_gotify_server($db, ''), 'no server anywhere is still no server');
    assert_same('https://gotify.mine.example', wallos_gotify_server($db, 'https://gotify.mine.example'),
        'and an account with its own is unaffected');

    $db->close();
});

wallos_test('the application token is never shared', function () {
    // Stated as a test rather than as a sentence in a comment: the instance
    // configuration has no token to hand out, and the resolver answers with an
    // address and nothing else. A future change that starts sharing a token
    // has to change this case first.
    $db = wallos_test_open_database();

    instance_gotify_set($db, 'https://gotify.example');

    $settings = wallos_get_admin_settings($db);

    assert_true(!isset($settings['gotify_token']), 'there is no instance token column at all');
    assert_true(is_string(wallos_gotify_server($db, '')), 'and the resolver answers with an address');

    $db->close();
});

wallos_test('the environment owns the server when it is set', function () {
    $db = wallos_test_open_database();

    instance_gotify_set($db, 'https://database.example');
    putenv('WALLOS_GOTIFY_SERVER=https://environment.example');

    $configuration = wallos_get_effective_admin_configuration($db);

    assert_same('https://environment.example', $configuration['settings']['gotify_server'],
        'the variable wins over the row');
    assert_true(isset($configuration['managed_fields']['gotify_server']),
        'and the page is told the field is managed, so it renders read-only');

    putenv('WALLOS_GOTIFY_SERVER');
    $db->close();
});

wallos_test('every place that sends to Gotify resolves the server the same way', function () {
    foreach ([
        'endpoints/cronjobs/sendnotifications.php',
        'endpoints/cronjobs/sendcancellationnotifications.php',
        'endpoints/notifications/testgotifynotifications.php',
    ] as $path) {
        $source = file_get_contents(WALLOS_ROOT . '/' . $path);

        assert_contains('wallos_gotify_server(', $source, $path . ' resolves the server');
    }

    $cron = file_get_contents(WALLOS_ROOT . '/endpoints/cronjobs/sendnotifications.php');
    assert_true(
        strpos($cron, 'wallos_gotify_server(') < strpos($cron, "is_url_safe_for_ssrf(\$gotify['serverUrl']"),
        'the address is resolved before it is checked'
    );
});
