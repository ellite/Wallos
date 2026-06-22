<?php
// Handle OIDC first
$secondsInMonth = 30 * 24 * 60 * 60;
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => $secondsInMonth,             
        'httponly' => true,          
        'samesite' => 'Lax'          
    ]);
    session_start();
}

if (isset($_GET['code']) && isset($_GET['state'])) {
    // This request is coming from the OIDC login flow
    $code = $_GET['code'];
    $state = $_GET['state'];
    $expectedState = $_SESSION['oidc_state'] ?? null;

    if (
        !is_string($code) || $code === '' ||
        !is_string($state) || $state === '' ||
        !is_string($expectedState) || $expectedState === '' ||
        !hash_equals($expectedState, $state)
    ) {
        unset($_SESSION['oidc_state']);
        $db->close();
        header("Location: login.php?error=oidc_invalid_state");
        exit();
    }

    unset($_SESSION['oidc_state']);

    if (!isset($_SESSION['oidc_state']) || !hash_equals($_SESSION['oidc_state'], $state)) {
        header("Location: login.php");
        exit();
    }
    unset($_SESSION['oidc_state']);

    require_once 'includes/oidc/handle_oidc_callback.php';

} else {
    if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
        $username = $_SESSION['username'];
        $main_currency = $_SESSION['main_currency'];
        $sql = "SELECT * FROM user WHERE username = :username";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $result = $stmt->execute();
        $userData = $result->fetchArray(SQLITE3_ASSOC);
        $userId = $userData['id'];

        if ($userData === false) {
            header('Location: logout.php');
            exit();
        } else {
            $_SESSION['userId'] = $userData['id'];
        }

        if ($userData['avatar'] == "") {
            $userData['avatar'] = "0";
        }
    } else {

        if (isset($_COOKIE['wallos_login'])) {
            $cookie = explode('|', $_COOKIE['wallos_login'], 3);
            $username = $cookie[0];
            $token = $cookie[1];
            $main_currency = $cookie[2];

            $sql = "SELECT * FROM user WHERE username = :username";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':username', $username, SQLITE3_TEXT);
            $result = $stmt->execute();

            if ($result) {
                $userData = $result->fetchArray(SQLITE3_ASSOC);
                if (!isset($userData['id'])) {
                    $db->close();
                    header("Location: logout.php");
                    exit();
                }

                if ($userData['avatar'] == "") {
                    $userData['avatar'] = "0";
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

                if ($row != false) {
                    session_regenerate_id(true);
                    $_SESSION['username'] = $username;
                    $_SESSION['token'] = $token;
                    $_SESSION['loggedin'] = true;
                    $_SESSION['main_currency'] = $main_currency;
                    $_SESSION['userId'] = $userId;
                } else {
                    $db->close();
                    header("Location: logout.php");
                    exit();
                }
            } else {
                $db->close();
                header("Location: logout.php");
                exit();
            }


        } else {
            $db->close();
            header("Location: login.php");
            exit();
        }
    }
}


?>