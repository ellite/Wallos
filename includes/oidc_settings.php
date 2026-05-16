<?php

function wallos_get_oidc_defaults()
{
    return [
        'name' => '',
        'client_id' => '',
        'client_secret' => '',
        'authorization_url' => '',
        'token_url' => '',
        'user_info_url' => '',
        'redirect_url' => '',
        'logout_url' => '',
        'user_identifier_field' => 'sub',
        'scopes' => 'openid email profile',
        'auth_style' => 'auto',
        'auto_create_user' => 0,
        'password_login_disabled' => 0,
    ];
}

function wallos_get_oidc_env_value($name)
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

function wallos_has_oidc_env_value($name)
{
    return wallos_get_oidc_env_value($name) !== null;
}

function wallos_parse_oidc_boolean($value)
{
    if (is_bool($value)) {
        return $value ? 1 : 0;
    }

    $normalized = strtolower(trim((string) $value));
    if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
        return 1;
    }

    if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
        return 0;
    }

    return null;
}

function wallos_get_db_oidc_settings($db)
{
    $settings = wallos_get_oidc_defaults();

    $stmt = $db->prepare('SELECT * FROM oauth_settings WHERE id = 1');
    $result = $stmt->execute();
    $row = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;

    if ($row) {
        unset($row['id']);
        $settings = array_merge($settings, $row);
    }

    return $settings;
}

function wallos_get_db_oidc_enabled($db)
{
    $stmt = $db->prepare('SELECT oidc_oauth_enabled FROM admin WHERE id = 1');
    $result = $stmt->execute();
    $row = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;

    return $row ? (int) $row['oidc_oauth_enabled'] : 0;
}

function wallos_fetch_oidc_discovery_document($issuer)
{
    $issuer = rtrim(trim((string) $issuer), '/');
    if ($issuer === '') {
        return [null, 'OIDC_ISSUER is empty.'];
    }

    $url = $issuer . '/.well-known/openid-configuration';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode >= 400) {
        $error = $curlError !== '' ? $curlError : 'HTTP ' . $httpCode;
        return [null, 'OIDC discovery failed for ' . $url . ': ' . $error];
    }

    $document = json_decode($response, true);
    if (!is_array($document)) {
        return [null, 'OIDC discovery returned invalid JSON for ' . $url . '.'];
    }

    return [$document, null];
}

function wallos_get_effective_oidc_configuration($db)
{
    $settings = wallos_get_db_oidc_settings($db);
    $managedFields = [];
    $notes = [];
    $discoveryDocument = null;

    $enabled = wallos_get_db_oidc_enabled($db);
    if (wallos_has_oidc_env_value('OIDC_ENABLED')) {
        $parsedEnabled = wallos_parse_oidc_boolean(wallos_get_oidc_env_value('OIDC_ENABLED'));
        if ($parsedEnabled !== null) {
            $enabled = $parsedEnabled;
            $managedFields['enabled'] = 'OIDC_ENABLED';
        } else {
            $notes[] = 'Ignoring invalid boolean value in OIDC_ENABLED.';
        }
    }

    if (wallos_has_oidc_env_value('OIDC_CLIENT_SECRET_FILE')) {
        $managedFields['client_secret'] = 'OIDC_CLIENT_SECRET_FILE';
        $settings['client_secret'] = '';
        $secretFile = trim((string) wallos_get_oidc_env_value('OIDC_CLIENT_SECRET_FILE'));

        if ($secretFile === '') {
            $notes[] = 'OIDC_CLIENT_SECRET_FILE is empty.';
        } elseif (is_readable($secretFile)) {
            $settings['client_secret'] = rtrim((string) file_get_contents($secretFile), "\r\n");
        } else {
            $notes[] = 'OIDC client secret file is not readable: ' . $secretFile;
        }
    } elseif (wallos_has_oidc_env_value('OIDC_CLIENT_SECRET')) {
        $settings['client_secret'] = (string) wallos_get_oidc_env_value('OIDC_CLIENT_SECRET');
        $managedFields['client_secret'] = 'OIDC_CLIENT_SECRET';
    }

    if (wallos_has_oidc_env_value('OIDC_AUTO_CREATE_USER')) {
        $parsedValue = wallos_parse_oidc_boolean(wallos_get_oidc_env_value('OIDC_AUTO_CREATE_USER'));
        if ($parsedValue !== null) {
            $settings['auto_create_user'] = $parsedValue;
            $managedFields['auto_create_user'] = 'OIDC_AUTO_CREATE_USER';
        } else {
            $notes[] = 'Ignoring invalid boolean value in OIDC_AUTO_CREATE_USER.';
        }
    }

    if (wallos_has_oidc_env_value('OIDC_DISABLE_PASSWORD_LOGIN')) {
        $parsedValue = wallos_parse_oidc_boolean(wallos_get_oidc_env_value('OIDC_DISABLE_PASSWORD_LOGIN'));
        if ($parsedValue !== null) {
            $settings['password_login_disabled'] = $parsedValue;
            $managedFields['password_login_disabled'] = 'OIDC_DISABLE_PASSWORD_LOGIN';
        } else {
            $notes[] = 'Ignoring invalid boolean value in OIDC_DISABLE_PASSWORD_LOGIN.';
        }
    }

    if (wallos_has_oidc_env_value('OIDC_ISSUER')) {
        $issuer = (string) wallos_get_oidc_env_value('OIDC_ISSUER');
        $managedFields['issuer'] = 'OIDC_ISSUER';

        foreach (['authorization_url', 'token_url', 'user_info_url'] as $field) {
            $settings[$field] = '';
            $managedFields[$field] = 'OIDC_ISSUER';
        }

        if (trim($issuer) !== '') {
            [$discoveryDocument, $discoveryError] = wallos_fetch_oidc_discovery_document($issuer);
            if ($discoveryError !== null) {
                $notes[] = $discoveryError;
            } elseif ($discoveryDocument !== null) {
                $discoveryMap = [
                    'authorization_url' => 'authorization_endpoint',
                    'token_url' => 'token_endpoint',
                    'user_info_url' => 'userinfo_endpoint',
                ];

                foreach ($discoveryMap as $field => $documentField) {
                    if (isset($discoveryDocument[$documentField])) {
                        $settings[$field] = $discoveryDocument[$documentField];
                    }
                }
            }
        } else {
            $notes[] = 'Ignoring empty OIDC_ISSUER value.';
        }
    }

    $envFieldMap = [
        'name' => 'OIDC_PROVIDER_NAME',
        'client_id' => 'OIDC_CLIENT_ID',
        'authorization_url' => 'OIDC_AUTH_URL',
        'token_url' => 'OIDC_TOKEN_URL',
        'user_info_url' => 'OIDC_USERINFO_URL',
        'redirect_url' => 'OIDC_REDIRECT_URL',
        'logout_url' => 'OIDC_LOGOUT_URL',
        'user_identifier_field' => 'OIDC_USER_IDENTIFIER',
        'scopes' => 'OIDC_SCOPES',
    ];

    foreach ($envFieldMap as $field => $envVar) {
        if (wallos_has_oidc_env_value($envVar)) {
            $settings[$field] = (string) wallos_get_oidc_env_value($envVar);
            $managedFields[$field] = $envVar;
        }
    }

    $requiredFields = [
        'client_id',
        'client_secret',
        'authorization_url',
        'token_url',
        'user_info_url',
        'redirect_url',
        'user_identifier_field',
    ];
    $isConfigured = true;
    foreach ($requiredFields as $field) {
        if (trim((string) $settings[$field]) === '') {
            $isConfigured = false;
            break;
        }
    }

    return [
        'enabled' => (int) $enabled,
        'settings' => $settings,
        'managed_fields' => $managedFields,
        'notes' => $notes,
        'discovery_document' => $discoveryDocument,
        'is_configured' => $isConfigured,
    ];
}
