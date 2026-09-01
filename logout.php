<?php
require_once 'includes/connect.php';
require_once 'includes/oidc_settings.php';
$secondsInMonth = 30 * 24 * 60 * 60;
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => $secondsInMonth,             
        'httponly' => true,          
        'samesite' => 'Lax'          
    ]);
    session_start();
}

$logoutOIDC = false;

// Check if user is logged in with OIDC
if (isset($_SESSION['from_oidc']) && $_SESSION['from_oidc'] === true) {
    $logoutOIDC = true;
    $oidcConfiguration = wallos_get_effective_oidc_configuration($db);
    $oidcSettings = $oidcConfiguration['settings'];
    $logoutUrl = $oidcSettings['logout_url'] ?? '';
}

// get token from cookie to remove from DB
//
// $userId is not assigned anywhere in this file, so the statement bound null
// and "user_id = null" matched no row: every logout left a usable remember-me
// token behind, and the next request signed the browser straight back in. On a
// shared machine that is the whole point of logging out.
//
// The token identifies the row on its own — it is 32 random bytes and the
// credential itself — so scoping the delete by user adds nothing and was the
// only reason this failed. The result is checked because a delete that could
// not run and a token that was not there are different outcomes.
if (isset($_SESSION['token'])) {
    $token = $_SESSION['token'];
    $sql = "DELETE FROM login_tokens WHERE token = :token";
    $stmt = $db->prepare($sql);

    if ($stmt === false) {
        error_log('Wallos: could not prepare the login token deletion on logout; '
            . 'any browser still holding the cookie stays signed in');
    } else {
        $stmt->bindParam(':token', $token, SQLITE3_TEXT);

        if ($stmt->execute() === false) {
            error_log('Wallos: could not revoke the login token on logout; '
                . 'any browser still holding the cookie stays signed in');
        }
    }
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

?>
<!DOCTYPE html>
<html>
<head>
<script>
  async function clearAndRedirect() {
    if ('caches' in window) {
      await caches.delete('pages-cache-v1');
    }
    sessionStorage.removeItem('sw_prefetched');
    window.location.href = '.';
  }
  clearAndRedirect();
</script>
</head>
<body></body>
</html>
<?php
exit();
