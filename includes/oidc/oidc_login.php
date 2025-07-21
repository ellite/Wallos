<?php

if (!isset($userData)) {
    die("User data missing for OIDC login.");
}

$userId = $userData['id'];
$username = $userData['username'];
$language = $userData['language'];
$main_currency = $userData['main_currency'];

$_SESSION['username'] = $username;
$_SESSION['loggedin'] = true;
$_SESSION['main_currency'] = $main_currency;
$_SESSION['userId'] = $userId;
$_SESSION['from_oidc'] = true; // Indicate this session is from OIDC login

$cookieExpire = time() + (86400 * 30); // 30 days

// generate remember token
$token = bin2hex(random_bytes(32));
$addLoginTokens = "INSERT INTO login_tokens (user_id, token) VALUES (:userId, :token)";
$addLoginTokensStmt = $db->prepare($addLoginTokens);
$addLoginTokensStmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
$addLoginTokensStmt->bindParam(':token', $token, SQLITE3_TEXT);
$addLoginTokensStmt->execute();

$_SESSION['token'] = $token;
$cookieValue = $username . "|" . $token . "|" . $main_currency;
setcookie('wallos_login', $cookieValue, [
    'expires' => $cookieExpire,
    'samesite' => 'Strict'
]);

// Set language cookie
setcookie('language', $language, [
    'expires' => $cookieExpire,
    'samesite' => 'Strict'
]);

// Set sort order default
if (!isset($_COOKIE['sortOrder'])) {
    setcookie('sortOrder', 'next_payment', [
        'expires' => $cookieExpire,
        'samesite' => 'Strict'
    ]);
}

// Set color theme
$query = "SELECT color_theme FROM settings WHERE user_id = :userId";
$stmt = $db->prepare($query);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$settings = $result->fetchArray(SQLITE3_ASSOC);
setcookie('colorTheme', $settings['color_theme'], [
    'expires' => $cookieExpire,
    'samesite' => 'Strict'
]);

// Done
$db->close();
header("Location: .");
exit();
