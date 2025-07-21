<?php
require_once 'includes/connect.php';
session_start();

$logoutOIDC = false;

// Check if user is logged in with OIDC
if (isset($_SESSION['from_oidc']) && $_SESSION['from_oidc'] === true) {
    $logoutOIDC = true;
    // get OIDC settings
    $stmt = $db->prepare('SELECT * FROM oauth_settings WHERE id = 1');
    $result = $stmt->execute();
    $oidcSettings = $result->fetchArray(SQLITE3_ASSOC);
    $logoutUrl = $oidcSettings['logout_url'] ?? '';
}

// get token from cookie to remove from DB
if (isset($_SESSION['token'])) {
    $token = $_SESSION['token'];
    $sql = "DELETE FROM login_tokens WHERE token = :token AND user_id = :userId";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':token', $token, SQLITE3_TEXT);
    $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
    $stmt->execute();
}
$_SESSION = array();
session_destroy();
$cookieExpire = time() - 3600;
setcookie('wallos_login', '', $cookieExpire);
$db->close();

if ($logoutOIDC && !empty($logoutUrl)) {
    $returnTo = urlencode($oidcSettings['redirect_url'] ?? '');
    header("Location: $logoutUrl?post_logout_redirect_uri=$returnTo");
    exit();
}

header("Location: .");
exit();