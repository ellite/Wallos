<?php
/*
  One ntfy server for the whole installation.

  What is personal about ntfy is the topic. The server address is not: a
  household that self-hosts ntfy runs one server, and before this every member
  had to know its address and type it in.

  The case this file watches hardest is the credential. The headers are an
  authorisation for the server they belong to, so an account that points ntfy
  at a server of its own must never have the installation's token sent there.
  That is not a preference, it is the difference between sharing a server and
  handing a token to whoever types an address into a settings page.
*/

require_once WALLOS_ROOT . '/includes/instance_config.php';

function instance_ntfy_set($db, $server, $headers = '')
{
    $stmt = $db->prepare('UPDATE admin SET ntfy_server = :server, ntfy_headers = :headers WHERE id = 1');
    $stmt->bindValue(':server', $server, SQLITE3_TEXT);
    $stmt->bindValue(':headers', $headers, SQLITE3_TEXT);
    $stmt->execute();
}

wallos_test('an account without a server of its own publishes to the instance server', function () {
    $db = wallos_test_open_database();

    instance_ntfy_set($db, 'https://ntfy.example', '{"Authorization": "Bearer instance"}');

    $settings = wallos_ntfy_settings($db, '', '');

    assert_same('https://ntfy.example', $settings['host'], 'the instance server is used');
    assert_same('{"Authorization": "Bearer instance"}', $settings['headers'],
        'with the credential that belongs to it');

    $db->close();
});

wallos_test('the instance credential never reaches a server the account chose', function () {
    $db = wallos_test_open_database();

    instance_ntfy_set($db, 'https://ntfy.example', '{"Authorization": "Bearer instance"}');

    $settings = wallos_ntfy_settings($db, 'https://ntfy.mine.example', '');

    assert_same('https://ntfy.mine.example', $settings['host'], 'the account keeps its own server');
    assert_same('', $settings['headers'],
        'and the installation token is not sent there - not overridden, not read at all');

    $withOwnHeaders = wallos_ntfy_settings($db, 'https://ntfy.mine.example', '{"Authorization": "Bearer mine"}');
    assert_same('{"Authorization": "Bearer mine"}', $withOwnHeaders['headers'],
        'its own credential travels with its own server, as before');

    $db->close();
});

wallos_test('an account on the instance server may still use a token of its own', function () {
    $db = wallos_test_open_database();

    instance_ntfy_set($db, 'https://ntfy.example', '{"Authorization": "Bearer instance"}');

    $settings = wallos_ntfy_settings($db, '', '{"Authorization": "Bearer mine"}');

    assert_same('https://ntfy.example', $settings['host'], 'the server is the shared one');
    assert_same('{"Authorization": "Bearer mine"}', $settings['headers'],
        'and the account authenticates as itself, which is how per-user tokens stay possible');

    $db->close();
});

wallos_test('an installation without an instance server behaves as it did', function () {
    $db = wallos_test_open_database();

    $empty = wallos_ntfy_settings($db, '', '{"Authorization": "Bearer mine"}');
    assert_same('', $empty['host'], 'no server anywhere is still no server');
    assert_same('{"Authorization": "Bearer mine"}', $empty['headers'], 'and the row is untouched');

    $own = wallos_ntfy_settings($db, 'https://ntfy.mine.example', '');
    assert_same('https://ntfy.mine.example', $own['host'], 'an account with its own is unaffected');

    $db->close();
});

wallos_test('the environment owns the server and the credential when they are set', function () {
    $db = wallos_test_open_database();

    instance_ntfy_set($db, 'https://database.example', '{"Authorization": "Bearer database"}');
    putenv('WALLOS_NTFY_SERVER=https://environment.example');
    putenv('WALLOS_NTFY_HEADERS={"Authorization": "Bearer environment"}');

    $configuration = wallos_get_effective_admin_configuration($db);

    assert_same('https://environment.example', $configuration['settings']['ntfy_server'],
        'the variable wins over the row');
    assert_true(isset($configuration['managed_fields']['ntfy_headers']),
        'and the page is told the credential is managed, so it is never rendered as a value');

    putenv('WALLOS_NTFY_SERVER');
    putenv('WALLOS_NTFY_HEADERS');
    $db->close();
});

wallos_test('every place that sends to ntfy resolves the server the same way', function () {
    foreach ([
        'endpoints/cronjobs/sendnotifications.php',
        'endpoints/cronjobs/sendcancellationnotifications.php',
        'endpoints/notifications/testntfynotifications.php',
    ] as $path) {
        $source = file_get_contents(WALLOS_ROOT . '/' . $path);

        assert_contains('wallos_ntfy_settings(', $source, $path . ' resolves the server');
    }

    // The resolution has to happen before the SSRF check reads the host, or an
    // instance server would be sent to unchecked.
    $cron = file_get_contents(WALLOS_ROOT . '/endpoints/cronjobs/sendnotifications.php');
    assert_true(
        strpos($cron, 'wallos_ntfy_settings(') < strpos($cron, 'is_url_safe_for_ssrf($ntfy[\'host\']'),
        'the host is resolved before it is checked'
    );
});
