<?php
/*
This API Endpoint accepts POST requests only.
It receives the following parameters:
- api_key: the API key of the user (must be user ID 1 / admin).
- registrations_open: (optional) '1' or '0' (allow new signups).
- max_users: (optional) maximum allowed users (integer).
- require_email_verification: (optional) '1' or '0'.
- server_url: (optional) url of this wallos instance.
- smtp_address: (optional) SMTP server address.
- smtp_port: (optional) SMTP port (integer).
- smtp_username: (optional) SMTP login username.
- smtp_password: (optional) SMTP login password.
- from_email: (optional) outgoing email address.
- encryption: (optional) 'tls' or 'ssl'.
- login_disabled: (optional) '1' or '0' (disable standard login).
- update_notification: (optional) '1' or '0' (check for wallos updates).
- oidc_oauth_enabled: (optional) '1' or '0' (enable OIDC login).
- local_webhook_notifications_allowlist: (optional) comma-separated IP/hosts allowlist.

It returns a JSON object with the following properties:
- success: whether the request was successful (boolean).
- title: the title of the response (string).
- message: detailed information or error message (string).

Example response:
{
  "success": true,
  "title": "Admin settings saved",
  "message": "Global admin settings have been updated successfully."
}
*/

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/ssrf_helper.php';

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
        'message' => 'Only the admin user (user ID 1) can update global settings.'
    ]);
    exit;
}

// Fetch current admin settings
$adminSql = "SELECT * FROM 'admin' WHERE id = 1";
$adminResult = $db->query($adminSql);
$adminSettings = $adminResult->fetchArray(SQLITE3_ASSOC);

if (!$adminSettings) {
    echo json_encode([
        'success' => false,
        'title' => 'Configuration error',
        'message' => 'Settings row not found in the database.'
    ]);
    exit;
}

// Validation & Checks
$registrations_open = isset($_POST['registrations_open']) ? intval($_POST['registrations_open']) : intval($adminSettings['registrations_open']);
$login_disabled = isset($_POST['login_disabled']) ? intval($_POST['login_disabled']) : intval($adminSettings['login_disabled']);

if ($login_disabled === 1) {
    if ($registrations_open === 1) {
        echo json_encode([
            'success' => false,
            'title' => 'Validation error',
            'message' => 'Registrations cannot be open if password login is disabled.'
        ]);
        exit;
    }

    $userCount = $db->querySingle("SELECT COUNT(*) FROM user");
    if ($userCount > 1) {
        echo json_encode([
            'success' => false,
            'title' => 'Validation error',
            'message' => 'Password login cannot be disabled if there is more than one user.'
        ]);
        exit;
    }
}

$require_email_verification = isset($_POST['require_email_verification']) ? intval($_POST['require_email_verification']) : intval($adminSettings['require_email_verification']);
$server_url = isset($_POST['server_url']) ? trim($_POST['server_url']) : $adminSettings['server_url'];

if ($require_email_verification === 1 && empty($server_url)) {
    echo json_encode([
        'success' => false,
        'title' => 'Validation error',
        'message' => 'Email verification requires a server URL.'
    ]);
    exit;
}

// SMTP checks
$smtp_address = $_POST['smtp_address'] ?? $adminSettings['smtp_address'];
$smtp_port = $_POST['smtp_port'] ?? $adminSettings['smtp_port'];

if (!empty($smtp_address) && !empty($smtp_port)) {
    $smtp_port_int = intval($smtp_port);
    if ($smtp_port_int < 1 || $smtp_port_int > 65535) {
        echo json_encode([
            'success' => false,
            'title' => 'Validation error',
            'message' => 'SMTP port must be a valid number between 1 and 65535.'
        ]);
        exit;
    }

    if (!validate_smtp_host($smtp_address, $smtp_port_int, $db)) {
        echo json_encode([
            'success' => false,
            'title' => 'Security Block',
            'message' => 'Security Error: SMTP host must not target link-local or loopback addresses.'
        ]);
        exit;
    }
}

// Build Update Query
$fields = [];
$params = [];

$columnsMap = [
    'registrations_open' => SQLITE3_INTEGER,
    'max_users' => SQLITE3_INTEGER,
    'require_email_verification' => SQLITE3_INTEGER,
    'server_url' => SQLITE3_TEXT,
    'smtp_address' => SQLITE3_TEXT,
    'smtp_port' => SQLITE3_INTEGER,
    'smtp_username' => SQLITE3_TEXT,
    'smtp_password' => SQLITE3_TEXT,
    'from_email' => SQLITE3_TEXT,
    'encryption' => SQLITE3_TEXT,
    'login_disabled' => SQLITE3_INTEGER,
    'update_notification' => SQLITE3_INTEGER,
    'oidc_oauth_enabled' => SQLITE3_INTEGER,
    'local_webhook_notifications_allowlist' => SQLITE3_TEXT
];

if (wallos_get_effective_ssrf_allowlist($db)['is_managed']) {
    unset($columnsMap['local_webhook_notifications_allowlist']);
}

foreach ($columnsMap as $postKey => $dataType) {
    if (isset($_POST[$postKey])) {
        $fields[] = "$postKey = :$postKey";
        if ($dataType === SQLITE3_INTEGER) {
            $params[$postKey] = [
                'val' => intval($_POST[$postKey]),
                'type' => SQLITE3_INTEGER
            ];
        } else {
            $params[$postKey] = [
                'val' => $_POST[$postKey],
                'type' => SQLITE3_TEXT
            ];
        }
    }
}

if (!empty($fields)) {
    $sqlUpdate = "UPDATE admin SET " . implode(', ', $fields) . " WHERE id = 1";
    $stmtUpdate = $db->prepare($sqlUpdate);
    foreach ($params as $key => $data) {
        $stmtUpdate->bindValue(':' . $key, $data['val'], $data['type']);
    }
    $resultUpdate = $stmtUpdate->execute();

    if (!$resultUpdate) {
        echo json_encode([
            'success' => false,
            'title' => 'Database error',
            'message' => 'Failed to save admin settings.'
        ]);
        exit;
    }
}

echo json_encode([
    'success' => true,
    'title' => 'Admin settings saved',
    'message' => 'Global admin settings have been updated successfully.'
]);

$db->close();
?>
