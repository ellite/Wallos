<?php

/*
  Instance settings the deployment can own.

  Wallos keeps its instance SMTP and its public URL in the admin table, entered
  through the admin page. That works, and it keeps working exactly as before -
  but it means the credentials of the mail server live in the database, and that
  somebody has to type them in again by hand after every fresh volume. In a
  container deployment the mail server is not a user preference; it is part of
  the deployment, like the port and the volume, and it belongs in the same place.

  The mechanism here is the one this project already uses for OIDC: read the
  variable, remember which field the environment owns, and show that field in
  the admin page without letting anybody edit it there. A secret may come from a
  file instead - OIDC_CLIENT_SECRET_FILE already does this - because a mounted
  file is how Docker and Kubernetes hand a secret to a container without putting
  it in the process environment where every `docker inspect` can read it.

  Nothing here changes an installation that sets no variable. Every value still
  comes from the admin table, and every field is still editable.
*/

/**
 * One environment variable, from wherever PHP put it.
 *
 * getenv() alone is not enough: some SAPI configurations populate $_ENV or
 * $_SERVER and not the process environment.
 *
 * @param string $name
 * @return string|null
 */
function wallos_env_value($name)
{
    $value = getenv($name);
    if ($value !== false) {
        return $value;
    }

    if (array_key_exists($name, $_ENV)) {
        return $_ENV[$name];
    }

    if (array_key_exists($name, $_SERVER)) {
        return $_SERVER[$name];
    }

    return null;
}

/**
 * @param string $name
 * @return bool
 */
function wallos_has_env_value($name)
{
    return wallos_env_value($name) !== null;
}

/**
 * A value that may be given directly or as the path to a file holding it.
 *
 * <NAME>_FILE wins over <NAME>, because an installation that has moved a secret
 * into a file has done so deliberately and a leftover environment variable must
 * not quietly take precedence over it.
 *
 * The trailing newline a file usually ends with is not part of a password, and
 * a value that is nothing but whitespace is not a configured value at all.
 *
 * @param string $name
 * @return array{value: string|null, source: string|null, note: string|null}
 */
function wallos_env_secret($name)
{
    $missing = ['value' => null, 'source' => null, 'note' => null];

    if (wallos_has_env_value($name . '_FILE')) {
        $path = trim((string) wallos_env_value($name . '_FILE'));

        if ($path === '') {
            return ['value' => null, 'source' => null, 'note' => $name . '_FILE is empty.'];
        }

        if (!is_readable($path)) {
            return ['value' => null, 'source' => null,
                'note' => $name . '_FILE names a file that cannot be read.'];
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return ['value' => null, 'source' => null,
                'note' => $name . '_FILE could not be read.'];
        }

        $contents = trim($contents);

        if ($contents === '') {
            return ['value' => null, 'source' => null,
                'note' => $name . '_FILE names an empty file.'];
        }

        return ['value' => $contents, 'source' => $name . '_FILE', 'note' => null];
    }

    if (wallos_has_env_value($name)) {
        return ['value' => (string) wallos_env_value($name), 'source' => $name, 'note' => null];
    }

    return $missing;
}

/**
 * The admin columns the environment can own, and the variable that owns each.
 *
 * @return array<string, string> column => variable name
 */
function wallos_instance_config_variables()
{
    return [
        'smtp_address' => 'WALLOS_SMTP_HOST',
        'smtp_port' => 'WALLOS_SMTP_PORT',
        'encryption' => 'WALLOS_SMTP_ENCRYPTION',
        'smtp_username' => 'WALLOS_SMTP_USERNAME',
        'smtp_password' => 'WALLOS_SMTP_PASSWORD',
        'from_email' => 'WALLOS_SMTP_FROM',
        'server_url' => 'WALLOS_SERVER_URL',
    ];
}

/**
 * The columns whose value may also come from a file.
 *
 * @return string[]
 */
function wallos_instance_config_secrets()
{
    return ['smtp_password'];
}

/**
 * The admin row, with anything the environment owns applied over it.
 *
 * Returns the row unchanged when no variable is set, which is every
 * installation that has not asked for this.
 *
 * @param SQLite3 $db
 * @return array{settings: array, managed_fields: array<string, string>, notes: string[]}
 */
function wallos_get_effective_admin_configuration($db)
{
    $settings = [];
    $stmt = $db->prepare('SELECT * FROM admin');

    if ($stmt !== false) {
        $result = $stmt->execute();
        $row = $result === false ? false : $result->fetchArray(SQLITE3_ASSOC);

        if ($row !== false) {
            $settings = $row;
        }
    }

    $managedFields = [];
    $notes = [];
    $secrets = wallos_instance_config_secrets();

    foreach (wallos_instance_config_variables() as $column => $variable) {
        if (in_array($column, $secrets, true)) {
            $secret = wallos_env_secret($variable);

            if ($secret['note'] !== null) {
                $notes[] = $secret['note'];
            }

            if ($secret['value'] === null) {
                continue;
            }

            $settings[$column] = $secret['value'];
            $managedFields[$column] = $secret['source'];
            continue;
        }

        if (!wallos_has_env_value($variable)) {
            continue;
        }

        $value = trim((string) wallos_env_value($variable));

        // An empty variable is not a configuration. Treating it as one would
        // let a typo in a compose file silently switch mail off with the admin
        // page showing the field as managed and blank.
        if ($value === '') {
            $notes[] = $variable . ' is empty and was ignored.';
            continue;
        }

        if ($column === 'encryption') {
            $encryption = strtolower($value);

            if (!in_array($encryption, ['none', 'tls', 'ssl'], true)) {
                $notes[] = $variable . ' must be none, tls or ssl; the stored value was kept.';
                continue;
            }

            $value = $encryption;
        }

        if ($column === 'smtp_port') {
            $port = (int) $value;

            if ((string) $port !== $value || $port < 1 || $port > 65535) {
                $notes[] = $variable . ' must be a port number; the stored value was kept.';
                continue;
            }
        }

        $settings[$column] = $value;
        $managedFields[$column] = $variable;
    }

    return ['settings' => $settings, 'managed_fields' => $managedFields, 'notes' => $notes];
}

/**
 * The admin row every consumer should read.
 *
 * A convenience over the function above for the callers that only want the
 * values - the mail jobs, the pages that ask whether mail is configured at all.
 *
 * @param SQLite3 $db
 * @return array
 */
function wallos_get_admin_settings($db)
{
    return wallos_get_effective_admin_configuration($db)['settings'];
}

/**
 * Fills in any posted form field the environment owns that came back empty.
 *
 * A managed field is either disabled-but-populated in a page, or - a secret
 * such as smtp_password - rendered blank on purpose, because a mounted secret
 * reaching the page source would have left the place it was mounted into.
 * An endpoint that reads exactly what a form posted then tests a blank managed
 * secret as though nothing were configured, when it is: the page calls the
 * field managed, the test fails, and nothing explains why.
 *
 * Only an empty posted value is replaced. A field the browser genuinely sent a
 * value for - including one somebody typed into a field the page disabled,
 * which a script can still do - keeps what was posted, since deciding what the
 * server trusts is `savesmtpsettings.php`'s job, not this one's.
 *
 * @param array<string, mixed>  $posted        Form field name => posted value.
 * @param array<string, string> $fieldColumns  Form field name => admin column.
 * @param array<string, string> $managedFields Admin column => variable name that owns it, from wallos_get_effective_admin_configuration().
 * @param array<string, mixed>  $settings      The effective admin settings (column => value) the fallback reads from.
 * @return array<string, mixed> $posted, with any empty managed field filled in.
 */
function wallos_fill_managed_form_fields($posted, $fieldColumns, $managedFields, $settings)
{
    foreach ($fieldColumns as $field => $column) {
        $value = isset($posted[$field]) ? trim((string) $posted[$field]) : '';

        if ($value === '' && isset($managedFields[$column])) {
            $posted[$field] = $settings[$column] ?? '';
        }
    }

    return $posted;
}
