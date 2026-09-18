<?php
/*
  The instance SMTP and the public URL can come from the deployment.

  Wallos keeps them in the admin table, entered through the admin page, and that
  is still where they come from when nothing else says otherwise. What this adds
  is the layer above: an operator who runs this in a container can put the mail
  server in the compose file, where the port and the volume already are, and put
  the password in a mounted file instead of in the database.

  The cases below are the ones a layer like this gets wrong:

    - it must be inert. An installation that sets nothing must read exactly what
      it read before, or a feature nobody asked for has changed everybody's mail.
    - an empty variable is a typo, not a configuration. Treating it as one
      switches mail off and shows the field as managed and blank.
    - a value the environment owns must never be written back to the database,
      or removing the variable silently restores a stale mail server.
    - a secret that comes from a file must not be rendered into the page source,
      which is the whole reason it was mounted rather than passed.
*/

require_once WALLOS_ROOT . '/includes/instance_config.php';

/**
 * Runs $body with the given environment, and puts the environment back.
 *
 * @param array    $variables name => value, or null to unset
 * @param callable $body
 */
function instance_config_with_env($variables, callable $body)
{
    $previous = [];

    foreach ($variables as $name => $value) {
        $previous[$name] = getenv($name);

        if ($value === null) {
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);
            continue;
        }

        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
    }

    try {
        $body();
    } finally {
        foreach ($previous as $name => $value) {
            unset($_ENV[$name], $_SERVER[$name]);

            if ($value === false) {
                putenv($name);
                continue;
            }

            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
        }
    }
}

/**
 * The admin row as an installation that has configured SMTP by hand has it.
 *
 * @param SQLite3 $db
 */
function instance_config_store_smtp($db)
{
    $stmt = $db->prepare("UPDATE admin SET smtp_address = 'stored.example.com', smtp_port = '587',
                          encryption = 'tls', smtp_username = 'stored-user',
                          smtp_password = 'stored-password', from_email = 'stored@example.com',
                          server_url = 'https://stored.example.com'");
    $stmt->execute();
}

wallos_test('with no variables set, the stored settings are what comes back', function () {
    // The case that matters most, because it is every installation that has not
    // asked for any of this.
    $db = wallos_test_open_database();
    instance_config_store_smtp($db);

    instance_config_with_env([
        'WALLOS_SMTP_HOST' => null,
        'WALLOS_SMTP_PORT' => null,
        'WALLOS_SMTP_ENCRYPTION' => null,
        'WALLOS_SMTP_USERNAME' => null,
        'WALLOS_SMTP_PASSWORD' => null,
        'WALLOS_SMTP_PASSWORD_FILE' => null,
        'WALLOS_SMTP_FROM' => null,
        'WALLOS_SERVER_URL' => null,
    ], function () use ($db) {
        $configuration = wallos_get_effective_admin_configuration($db);

        assert_same('stored.example.com', $configuration['settings']['smtp_address'], 'the stored host');
        assert_same('stored-password', $configuration['settings']['smtp_password'], 'the stored password');
        assert_same([], $configuration['managed_fields'], 'nothing is managed by the environment');
        assert_same([], $configuration['notes'], 'and there is nothing to report');
    });

    $db->close();
});

wallos_test('a variable replaces the stored value and says which field it owns', function () {
    $db = wallos_test_open_database();
    instance_config_store_smtp($db);

    instance_config_with_env([
        'WALLOS_SMTP_HOST' => 'mail.example.net',
        'WALLOS_SMTP_PORT' => '2525',
        'WALLOS_SMTP_ENCRYPTION' => 'ssl',
        'WALLOS_SERVER_URL' => 'https://wallos.example.net',
    ], function () use ($db) {
        $configuration = wallos_get_effective_admin_configuration($db);

        assert_same('mail.example.net', $configuration['settings']['smtp_address'], 'the host comes from the environment');
        assert_same('2525', $configuration['settings']['smtp_port'], 'and the port');
        assert_same('ssl', $configuration['settings']['encryption'], 'and the encryption');
        assert_same('https://wallos.example.net', $configuration['settings']['server_url'], 'and the public URL');

        assert_same('WALLOS_SMTP_HOST', $configuration['managed_fields']['smtp_address'] ?? null,
            'the field names the variable that owns it');

        // Untouched fields keep coming from the database, so an installation can
        // move one value into the deployment without moving all of them.
        assert_same('stored-user', $configuration['settings']['smtp_username'], 'the username is still the stored one');
        assert_true(!isset($configuration['managed_fields']['smtp_username']), 'and is not reported as managed');
    });

    $db->close();
});

wallos_test('an empty variable is a typo rather than a configuration', function () {
    // Reading it as "the host is now nothing" would switch mail off and show
    // the field as managed and blank, which looks like the feature working.
    $db = wallos_test_open_database();
    instance_config_store_smtp($db);

    instance_config_with_env(['WALLOS_SMTP_HOST' => '   '], function () use ($db) {
        $configuration = wallos_get_effective_admin_configuration($db);

        assert_same('stored.example.com', $configuration['settings']['smtp_address'],
            'the stored host is kept');
        assert_true(!isset($configuration['managed_fields']['smtp_address']),
            'and the field is not claimed by the environment');
        assert_contains('WALLOS_SMTP_HOST', implode(' ', $configuration['notes']),
            'the admin page is told the variable was ignored');
    });

    $db->close();
});

wallos_test('a value that is not one of the allowed ones is refused, not stored', function () {
    $db = wallos_test_open_database();
    instance_config_store_smtp($db);

    instance_config_with_env([
        'WALLOS_SMTP_ENCRYPTION' => 'starttls',
        'WALLOS_SMTP_PORT' => 'not-a-port',
    ], function () use ($db) {
        $configuration = wallos_get_effective_admin_configuration($db);

        assert_same('tls', $configuration['settings']['encryption'], 'the stored encryption is kept');
        // Cast, because smtp_port is an INTEGER column and the environment
        // hands strings: what matters is the number, not which of the two the
        // value came from.
        assert_same('587', (string) $configuration['settings']['smtp_port'], 'and the stored port');

        $notes = implode(' ', $configuration['notes']);
        assert_contains('WALLOS_SMTP_ENCRYPTION', $notes, 'the encryption value is reported');
        assert_contains('WALLOS_SMTP_PORT', $notes, 'and the port');
    });

    $db->close();
});

wallos_test('a port at the edges is accepted and one outside them is not', function () {
    $db = wallos_test_open_database();
    instance_config_store_smtp($db);

    foreach (['1' => '1', '65535' => '65535', '0' => '587', '65536' => '587', '-1' => '587', '25 ' => '25'] as $set => $expected) {
        instance_config_with_env(['WALLOS_SMTP_PORT' => $set], function () use ($db, $set, $expected) {
            $configuration = wallos_get_effective_admin_configuration($db);

            assert_same($expected, (string) $configuration['settings']['smtp_port'],
                'WALLOS_SMTP_PORT=' . var_export($set, true) . ' resolves to ' . $expected);
        });
    }

    $db->close();
});

wallos_test('a secret can come from a mounted file', function () {
    $db = wallos_test_open_database();
    instance_config_store_smtp($db);

    $path = WALLOS_TEST_TMP . '/smtp-password-' . uniqid('', true);
    // A file written by Docker or Kubernetes ends in a newline, and the newline
    // is not part of the password.
    file_put_contents($path, "from-the-file\n");

    instance_config_with_env(['WALLOS_SMTP_PASSWORD_FILE' => $path], function () use ($db) {
        $configuration = wallos_get_effective_admin_configuration($db);

        assert_same('from-the-file', $configuration['settings']['smtp_password'],
            'the password is the content of the file, without its newline');
        assert_contains('WALLOS_SMTP_PASSWORD_FILE',
            $configuration['managed_fields']['smtp_password'] ?? '',
            'and the field names the file variable, not the plain one');
    });

    unlink($path);
    $db->close();
});

wallos_test('the file wins over the plain variable, and a missing file is reported', function () {
    $db = wallos_test_open_database();
    instance_config_store_smtp($db);

    $path = WALLOS_TEST_TMP . '/smtp-password-' . uniqid('', true);
    file_put_contents($path, 'from-the-file');

    instance_config_with_env([
        'WALLOS_SMTP_PASSWORD' => 'from-the-variable',
        'WALLOS_SMTP_PASSWORD_FILE' => $path,
    ], function () use ($db) {
        $configuration = wallos_get_effective_admin_configuration($db);

        // An installation that has moved a secret into a file did that
        // deliberately; a leftover variable must not quietly win.
        assert_same('from-the-file', $configuration['settings']['smtp_password'],
            'the file wins over the plain variable');
    });

    unlink($path);

    instance_config_with_env([
        'WALLOS_SMTP_PASSWORD' => 'from-the-variable',
        'WALLOS_SMTP_PASSWORD_FILE' => $path,
    ], function () use ($db) {
        $configuration = wallos_get_effective_admin_configuration($db);

        // Falling back to the plain variable here would be worse than failing:
        // the secret was moved out of the environment on purpose.
        assert_same('stored-password', $configuration['settings']['smtp_password'],
            'a file that is not there does not fall back to the variable');
        assert_contains('WALLOS_SMTP_PASSWORD_FILE', implode(' ', $configuration['notes']),
            'and the admin page is told why');
    });

    $db->close();
});

wallos_test('a fresh installation that configured nothing by hand can still send mail', function () {
    // The point of the whole thing, asked as the application asks it.
    // passwordreset.php refuses to run unless smtp_address and server_url are
    // both set, and on a fresh volume the admin row has neither. An operator
    // who put them in the compose file has configured this installation, and
    // the page has to agree — otherwise the feature is configured and
    // invisible, which is worse than not configured at all.
    $db = wallos_test_open_database();

    $stored = wallos_get_admin_settings($db);
    assert_true(empty($stored['smtp_address']), 'a fresh installation has no mail server');

    instance_config_with_env([
        'WALLOS_SMTP_HOST' => 'mail.example.net',
        'WALLOS_SMTP_PORT' => '587',
        'WALLOS_SMTP_USERNAME' => 'wallos',
        'WALLOS_SMTP_PASSWORD' => 'secret',
        'WALLOS_SMTP_ENCRYPTION' => 'tls',
        'WALLOS_SERVER_URL' => 'https://wallos.example.net',
    ], function () use ($db) {
        $settings = wallos_get_admin_settings($db);

        // The exact condition passwordreset.php puts on the page.
        assert_true($settings['smtp_address'] != "" && $settings['server_url'] != "",
            'the password reset page opens');

        // And the one the mail job puts on itself before building a PHPMailer.
        assert_true((bool) ($settings['smtp_address'] && $settings['smtp_port']
            && $settings['smtp_username'] && $settings['smtp_password'] && $settings['encryption']),
            'and the mail job finds a complete configuration');
    });

    $db->close();
});

wallos_test('a value the environment owns is never written back to the database', function () {
    // The inputs are disabled in the admin page, but a disabled input still has
    // a value a script can read and post, so the endpoint has to decide. If it
    // did not, removing the variable later would silently restore a stale mail
    // server that nobody remembers configuring.
    $source = file_get_contents(WALLOS_ROOT . '/endpoints/admin/savesmtpsettings.php');

    assert_contains("wallos_get_effective_admin_configuration(\$db)['managed_fields']", $source,
        'the save endpoint asks which fields the environment owns');
    assert_contains('if (isset($managedFields[$column])) {', $source,
        'and skips them when building the update');
    assert_true(strpos($source, "UPDATE admin SET smtp_address = :smtp_address, smtp_port") === false,
        'the fixed statement that wrote every column is gone');
});

wallos_test('a managed secret is not rendered into the page source', function () {
    // The reason a secret is mounted rather than passed is that it should not
    // be readable from somewhere else. Printing it into the admin page as the
    // value of an input undoes that.
    $source = file_get_contents(WALLOS_ROOT . '/admin.php');

    assert_contains("isset(\$instanceManagedFields['smtp_password'])", $source,
        'the password field asks whether the deployment owns it');

    $managedBlock = substr($source, strpos($source, "isset(\$instanceManagedFields['smtp_password'])"), 700);
    $else = strpos($managedBlock, 'else');
    assert_true($else !== false, 'the field has both branches');

    assert_true(strpos(substr($managedBlock, 0, $else), "htmlspecialchars(\$settings['smtp_password'])") === false,
        'the managed branch renders an empty value rather than the secret');
});

wallos_test('every reader of the instance mail settings goes through one place', function () {
    // Two readers that disagree is how "I configured it and the reset link
    // never appeared" happens: the mail job uses the environment and the login
    // page still asks the database whether mail is configured at all.
    foreach ([
        'login.php',
        'passwordreset.php',
        'endpoints/cronjobs/sendresetpasswordemails.php',
        'endpoints/cronjobs/sendverificationemails.php',
        'admin.php',
    ] as $path) {
        $source = file_get_contents(WALLOS_ROOT . '/' . $path);

        assert_contains('instance_config.php', $source, $path . ' includes the instance configuration');
        assert_true(preg_match('/SELECT \* FROM admin|SELECT registrations_open, max_users, server_url, smtp_address FROM admin/', $source) !== 1,
            $path . ' does not read the admin row directly any more');
    }
});
