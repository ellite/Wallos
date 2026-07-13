<?php

/**
 * Attempts to restore a logged-in session from the persistent "remember me"
 * login cookie (set at login when "stay logged in" is checked).
 *
 * PHP's session data can be garbage-collected (default ~24 minutes of
 * inactivity) long before the remember-me cookie's 30-day lifetime expires.
 * Full page loads recover from this transparently; this function lets
 * AJAX/API endpoints (via connect_endpoint.php) do the same instead of
 * silently behaving as logged-out after an idle period.
 *
 * On success, populates $_SESSION (regenerating the session id) and
 * returns the user's row. On any failure, returns false and leaves
 * $_SESSION untouched.
 */
function restoreSessionFromRememberMeCookie($db)
{
    if (!isset($_COOKIE['wallos_login'])) {
        return false;
    }

    $cookie = explode('|', $_COOKIE['wallos_login'], 3);
    if (count($cookie) !== 3) {
        return false;
    }
    [$username, $token, $main_currency] = $cookie;

    $sql = "SELECT * FROM user WHERE username = :username";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $result = $stmt->execute();

    if (!$result) {
        return false;
    }

    $userData = $result->fetchArray(SQLITE3_ASSOC);
    if (!isset($userData['id'])) {
        return false;
    }

    $userId = $userData['id'];
    $main_currency = $userData['main_currency'];

    $adminQuery = "SELECT login_disabled FROM admin";
    $adminResult = $db->query($adminQuery);
    $adminRow = $adminResult->fetchArray(SQLITE3_ASSOC);

    if ($adminRow['login_disabled'] == 1) {
        $sql = "SELECT * FROM login_tokens WHERE user_id = :userId";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':userId', $userId, SQLITE3_TEXT);
    } else {
        $sql = "SELECT * FROM login_tokens WHERE user_id = :userId AND token = :token";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':userId', $userId, SQLITE3_TEXT);
        $stmt->bindParam(':token', $token, SQLITE3_TEXT);
    }
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);

    if ($row == false) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['username'] = $username;
    $_SESSION['token'] = $token;
    $_SESSION['loggedin'] = true;
    $_SESSION['main_currency'] = $main_currency;
    $_SESSION['userId'] = $userId;

    return $userData;
}

?>
