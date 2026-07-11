<?php
/*
This API Endpoint accepts POST requests only.
It receives the following parameters:
- api_key: the API key of the user (for Wallos authentication).
- dark_theme: (optional) '0' (light), '1' (dark), or '2' (automatic).
- color_theme: (optional) 'blue', 'green', 'red', 'yellow', 'purple', or 'custom'.
- monthly_price: (optional) '1' or '0' (show monthly prices).
- convert_currency: (optional) '1' or '0' (convert to main currency).
- show_original_price: (optional) '1' or '0' (show original prices next to converted).
- mobile_nav: (optional) '1' or '0' (use mobile navigation menu).
- show_subscription_progress: (optional) '1' or '0' (show subscription progress bars).
- week_starts_sunday: (optional) '1' or '0' (start calendar weeks on Sunday).
- disabled_to_bottom: (optional) '1' or '0' (move disabled subscriptions to bottom).
- hide_disabled: (optional) '1' or '0' (hide disabled subscriptions).
- remove_background: (optional) '1' or '0' (remove background from logos).
- square_icons: (optional) '1' or '0' (use square icon frames).
- main_color: (optional) Custom theme primary color (hex format, e.g. #0000ff).
- accent_color: (optional) Custom theme accent color (hex format, e.g. #00ffff).
- hover_color: (optional) Custom theme hover color (hex format, e.g. #00008b).
- css: (optional) Custom CSS styling rules to apply to the web interface.

It returns a JSON object with the following properties:
- success: whether the request was successful (boolean).
- title: the title of the response (string).
- message: detailed information or error message (string).

Example response:
{
  "success": true,
  "title": "Settings updated",
  "message": "User settings have been saved successfully."
}
*/

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/inputvalidation.php';

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

// 1. Process Custom CSS
if (isset($_POST['css'])) {
    $customCss = $_POST['css'];
    
    $stmtDelCss = $db->prepare('DELETE FROM custom_css_style WHERE user_id = :userId');
    $stmtDelCss->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $stmtDelCss->execute();

    $stmtInsCss = $db->prepare('INSERT INTO custom_css_style (css, user_id) VALUES (:customCss, :userId)');
    $stmtInsCss->bindValue(':customCss', $customCss, SQLITE3_TEXT);
    $stmtInsCss->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $stmtInsCss->execute();
}

// 2. Process Custom Colors
if (isset($_POST['main_color']) || isset($_POST['accent_color']) || isset($_POST['hover_color']) ||
    isset($_POST['mainColor']) || isset($_POST['accentColor']) || isset($_POST['hoverColor'])) {
    
    // Fetch current custom colors to allow partial updates
    $colorSql = "SELECT * FROM custom_colors WHERE user_id = :userId";
    $colorStmt = $db->prepare($colorSql);
    $colorStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $colorResult = $colorStmt->execute();
    $currentColor = $colorResult->fetchArray(SQLITE3_ASSOC) ?: [
        'main_color' => '#0000ff',
        'accent_color' => '#00ffff',
        'hover_color' => '#00008b'
    ];

    $main_color = $_POST['main_color'] ?? $_POST['mainColor'] ?? $currentColor['main_color'];
    $accent_color = $_POST['accent_color'] ?? $_POST['accentColor'] ?? $currentColor['accent_color'];
    $hover_color = $_POST['hover_color'] ?? $_POST['hoverColor'] ?? $currentColor['hover_color'];

    // Validate colors
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $main_color) || 
        !preg_match('/^#[0-9A-Fa-f]{6}$/', $accent_color) || 
        !preg_match('/^#[0-9A-Fa-f]{6}$/', $hover_color)) {
        echo json_encode([
            'success' => false,
            'title' => 'Invalid colors',
            'message' => 'Custom colors must be in #RRGGBB format.'
        ]);
        exit;
    }
    if ($main_color === $accent_color) {
        echo json_encode([
            'success' => false,
            'title' => 'Color validation failed',
            'message' => 'Main color and accent color cannot be the same.'
        ]);
        exit;
    }

    // Delete & Insert
    $delColors = $db->prepare('DELETE FROM custom_colors WHERE user_id = :userId');
    $delColors->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $delColors->execute();

    $insColors = $db->prepare('INSERT INTO custom_colors (main_color, accent_color, hover_color, user_id) VALUES (:main_color, :accent_color, :hover_color, :userId)');
    $insColors->bindValue(':main_color', $main_color, SQLITE3_TEXT);
    $insColors->bindValue(':accent_color', $accent_color, SQLITE3_TEXT);
    $insColors->bindValue(':hover_color', $hover_color, SQLITE3_TEXT);
    $insColors->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $insColors->execute();
}

// 3. Process Settings table updates
$updateFields = [];
$params = [];

$binarySettings = [
    'monthly_price' => 'monthly_price',
    'convert_currency' => 'convert_currency',
    'remove_background' => 'remove_background',
    'hide_disabled' => 'hide_disabled',
    'disabled_to_bottom' => 'disabled_to_bottom',
    'show_original_price' => 'show_original_price',
    'mobile_nav' => 'mobile_nav',
    'show_subscription_progress' => 'show_subscription_progress',
    'week_starts_sunday' => 'week_starts_sunday',
    'square_icons' => 'square_icons'
];

foreach ($binarySettings as $postKey => $dbCol) {
    if (isset($_POST[$postKey])) {
        $val = $_POST[$postKey];
        if ($val === '1' || $val === '0' || $val === 1 || $val === 0) {
            $updateFields[] = "`$dbCol` = :$postKey";
            $params[$postKey] = [
                'val' => ($val === '1' || $val === 1) ? 1 : 0,
                'type' => SQLITE3_INTEGER
            ];
        } else {
            echo json_encode([
                'success' => false,
                'title' => 'Invalid parameter',
                'message' => "Parameter '$postKey' must be 0 or 1."
            ]);
            exit;
        }
    }
}

if (isset($_POST['dark_theme'])) {
    $darkTheme = $_POST['dark_theme'];
    if (in_array($darkTheme, ['0', '1', '2', 0, 1, 2], true)) {
        $updateFields[] = "`dark_theme` = :dark_theme";
        $params['dark_theme'] = [
            'val' => intval($darkTheme),
            'type' => SQLITE3_INTEGER
        ];
    } else {
        echo json_encode([
            'success' => false,
            'title' => 'Invalid parameter',
            'message' => "Parameter 'dark_theme' must be 0 (light), 1 (dark), or 2 (automatic)."
        ]);
        exit;
    }
}

if (isset($_POST['color_theme'])) {
    $colorTheme = $_POST['color_theme'];
    $allowedThemes = ['blue', 'green', 'red', 'yellow', 'purple', 'custom'];
    if (in_array($colorTheme, $allowedThemes, true)) {
        $updateFields[] = "`color_theme` = :color_theme";
        $params['color_theme'] = [
            'val' => $colorTheme,
            'type' => SQLITE3_TEXT
        ];
    } else {
        echo json_encode([
            'success' => false,
            'title' => 'Invalid parameter',
            'message' => "Parameter 'color_theme' must be one of: " . implode(', ', $allowedThemes) . "."
        ]);
        exit;
    }
}

// Perform update if fields were specified
if (!empty($updateFields)) {
    $sqlUpdate = "UPDATE settings SET " . implode(', ', $updateFields) . " WHERE user_id = :userId";
    $stmtUpdate = $db->prepare($sqlUpdate);
    $stmtUpdate->bindValue(':userId', $userId, SQLITE3_INTEGER);
    foreach ($params as $paramKey => $paramData) {
        $stmtUpdate->bindValue(':' . $paramKey, $paramData['val'], $paramData['type']);
    }
    $resultUpdate = $stmtUpdate->execute();

    if (!$resultUpdate) {
        echo json_encode([
            'success' => false,
            'title' => 'Database error',
            'message' => 'Failed to save settings to the database.'
        ]);
        exit;
    }
}

echo json_encode([
    'success' => true,
    'title' => 'Settings updated',
    'message' => 'User settings have been saved successfully.'
]);

$db->close();
?>
