<?php
/*
This API Endpoint accepts POST requests only.
It receives the following parameters:
- api_key: the API key of the user (must be user ID 1 / admin).
- oidc_enabled: (optional) '1' to enable OIDC logins, '0' to disable.
- name: (optional) provider name.
- client_id: (optional) OAuth client ID.
- client_secret: (optional) OAuth client secret.
- authorization_url: (optional) authorization endpoint.
- token_url: (optional) token endpoint.
- user_info_url: (optional) userinfo endpoint.
- redirect_url: (optional) callback/redirect URL.
- logout_url: (optional) logout/end-session URL.
- user_identifier_field: (optional) field identifier (e.g. sub).
- scopes: (optional) scope list.
- auth_style: (optional) authentication style (auto/header/params).
- auto_create_user: (optional) '1' to auto-register new OIDC users, '0' otherwise.
- password_login_disabled: (optional) '1' to disable password logins, '0' otherwise.
- require_email_verified: (optional) '1' to reject unverified emails, '0' otherwise.

It returns a JSON object with the following properties:
- success: whether the request was successful (boolean).
- title: the title of the response (string).
- message: detailed information or error message (string).

Example response:
{
  "success": true,
  "title": "OIDC settings saved",
  "message": "OIDC configurations have been saved successfully."
}
*/

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/ssrf_helper.php';
require_once '../../includes/oidc_settings.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'title' => 'Invalid request method',
        'message' => 'Only POST requests are allowed.'
    ]);
    exit;
}

$apiKey = $_POST['api_key'] ?? $_POST['apiKey'] ?? null;

// Authenticate user first
if (!$apiKey) {
    echo json_encode([
        'success' => false,
        'title' => 'Missing API key',
        'message' => 'API key is required.'
    ]);
    exit;
}

$sql = "SELECT * FROM user WHERE api_key = :apiKey";
$stmt = $db->prepare($sql);
$stmt->bindValue(':apiKey', $apiKey, SQLITE3_TEXT);
$result = $stmt->execute();
$user = $result->fetchArray(SQLITE3_ASSOC);

if (!$user) {
    echo json_encode([
        'success' => false,
        'title' => 'Unauthorized',
        'message' => 'Invalid API key.'
    ]);
    exit;
}

$userId = $user['id'];

// Must be Admin user (ID 1)
if ($userId !== 1) {
    echo json_encode([
        'success' => false,
        'title' => 'Forbidden',
        'message' => 'Only the admin user (user ID 1) can update OIDC configurations.'
    ]);
    exit;
}

// 1. Handle OIDC Enabled Toggle
if (isset($_POST['oidc_enabled'])) {
    if (wallos_has_oidc_env_value('OIDC_ENABLED')) {
        echo json_encode([
            'success' => false,
            'title' => 'Environment override',
            'message' => 'OIDC enablement is managed by the OIDC_ENABLED environment variable.'
        ]);
        exit;
    }

    $oidcEnabled = ($_POST['oidc_enabled'] === '1' || $_POST['oidc_enabled'] === 1) ? 1 : 0;
    $stmtEnabled = $db->prepare('UPDATE admin SET oidc_oauth_enabled = :oidcEnabled WHERE id = 1');
    $stmtEnabled->bindParam(':oidcEnabled', $oidcEnabled, SQLITE3_INTEGER);
    $stmtEnabled->execute();
}

// 2. Handle OIDC detailed configurations
$oidcConfiguration = wallos_get_effective_oidc_configuration($db);
$managedFields = $oidcConfiguration['managed_fields'];
$dbSettings = wallos_get_db_oidc_settings($db);

$incomingMapping = [
    'name' => $_POST['name'] ?? null,
    'client_id' => $_POST['client_id'] ?? null,
    'client_secret' => $_POST['client_secret'] ?? null,
    'authorization_url' => $_POST['authorization_url'] ?? null,
    'token_url' => $_POST['token_url'] ?? null,
    'user_info_url' => $_POST['user_info_url'] ?? null,
    'redirect_url' => $_POST['redirect_url'] ?? null,
    'logout_url' => $_POST['logout_url'] ?? null,
    'user_identifier_field' => $_POST['user_identifier_field'] ?? null,
    'scopes' => $_POST['scopes'] ?? null,
    'auth_style' => $_POST['auth_style'] ?? null,
    'auto_create_user' => isset($_POST['auto_create_user']) ? intval($_POST['auto_create_user']) : null,
    'password_login_disabled' => isset($_POST['password_login_disabled']) ? intval($_POST['password_login_disabled']) : null,
    'require_email_verified' => isset($_POST['require_email_verified']) ? intval($_POST['require_email_verified']) : null,
];

// Merge if not managed by environment
$hasConfigChange = false;
foreach ($incomingMapping as $field => $value) {
    if ($value !== null) {
        if (isset($managedFields[$field])) {
            // Cannot modify field overridden by environment variables
            continue;
        }
        $dbSettings[$field] = $value;
        $hasConfigChange = true;
    }
}

if ($hasConfigChange) {
    // SSRF validations
    if ($dbSettings['token_url'] && validate_oidc_endpoint_url($dbSettings['token_url'], $db) === false) {
        echo json_encode([
            "success" => false,
            "title" => "Security Error",
            "message" => "Security Error: Token URL must not target link-local or loopback addresses."
        ]);
        exit;
    }

    if ($dbSettings['user_info_url'] && validate_oidc_endpoint_url($dbSettings['user_info_url'], $db) === false) {
        echo json_encode([
            "success" => false,
            "title" => "Security Error",
            "message" => "Security Error: User Info URL must not target link-local or loopback addresses."
        ]);
        exit;
    }

    // Save to oauth_settings table
    $checkStmt = $db->prepare('SELECT COUNT(*) as count FROM oauth_settings WHERE id = 1');
    $resultCheck = $checkStmt->execute();
    $rowCheck = $resultCheck->fetchArray(SQLITE3_ASSOC);

    if ($rowCheck['count'] > 0) {
        $stmtSave = $db->prepare('UPDATE oauth_settings SET 
                name = :oidcName, 
                client_id = :oidcClientId, 
                client_secret = :oidcClientSecret, 
                authorization_url = :oidcAuthUrl, 
                token_url = :oidcTokenUrl, 
                user_info_url = :oidcUserInfoUrl, 
                redirect_url = :oidcRedirectUrl, 
                logout_url = :oidcLogoutUrl, 
                user_identifier_field = :oidcUserIdentifierField, 
                scopes = :oidcScopes, 
                auth_style = :oidcAuthStyle,
                auto_create_user = :oidcAutoCreateUser,
                password_login_disabled = :oidcPasswordLoginDisabled,
                require_email_verified = :oidcRequireEmailVerified
                WHERE id = 1');
    } else {
        $stmtSave = $db->prepare('INSERT INTO oauth_settings (
                id, name, client_id, client_secret, authorization_url, token_url, user_info_url, redirect_url, logout_url, user_identifier_field, scopes, auth_style, auto_create_user, password_login_disabled, require_email_verified
            ) VALUES (
                1, :oidcName, :oidcClientId, :oidcClientSecret, :oidcAuthUrl, :oidcTokenUrl, :oidcUserInfoUrl, :oidcRedirectUrl, :oidcLogoutUrl, :oidcUserIdentifierField, :oidcScopes, :oidcAuthStyle, :oidcAutoCreateUser, :oidcPasswordLoginDisabled, :oidcRequireEmailVerified
            )');
    }

    $stmtSave->bindValue(':oidcName', $dbSettings['name'], SQLITE3_TEXT);
    $stmtSave->bindValue(':oidcClientId', $dbSettings['client_id'], SQLITE3_TEXT);
    $stmtSave->bindValue(':oidcClientSecret', $dbSettings['client_secret'], SQLITE3_TEXT);
    $stmtSave->bindValue(':oidcAuthUrl', $dbSettings['authorization_url'], SQLITE3_TEXT);
    $stmtSave->bindValue(':oidcTokenUrl', $dbSettings['token_url'], SQLITE3_TEXT);
    $stmtSave->bindValue(':oidcUserInfoUrl', $dbSettings['user_info_url'], SQLITE3_TEXT);
    $stmtSave->bindValue(':oidcRedirectUrl', $dbSettings['redirect_url'], SQLITE3_TEXT);
    $stmtSave->bindValue(':oidcLogoutUrl', $dbSettings['logout_url'], SQLITE3_TEXT);
    $stmtSave->bindValue(':oidcUserIdentifierField', $dbSettings['user_identifier_field'], SQLITE3_TEXT);
    $stmtSave->bindValue(':oidcScopes', $dbSettings['scopes'], SQLITE3_TEXT);
    $stmtSave->bindValue(':oidcAuthStyle', $dbSettings['auth_style'], SQLITE3_TEXT);
    $stmtSave->bindValue(':oidcAutoCreateUser', $dbSettings['auto_create_user'], SQLITE3_INTEGER);
    $stmtSave->bindValue(':oidcPasswordLoginDisabled', $dbSettings['password_login_disabled'], SQLITE3_INTEGER);
    $stmtSave->bindValue(':oidcRequireEmailVerified', $dbSettings['require_email_verified'], SQLITE3_INTEGER);
    
    $resultSave = $stmtSave->execute();
    if (!$resultSave) {
        echo json_encode([
            'success' => false,
            'title' => 'Database error',
            'message' => 'Failed to save OIDC configurations.'
        ]);
        exit;
    }
}

echo json_encode([
    'success' => true,
    'title' => 'OIDC settings saved',
    'message' => 'OIDC configurations have been saved successfully.'
]);

$db->close();
?>
