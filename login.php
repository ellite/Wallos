<?php
require_once 'includes/connect.php';
require_once 'includes/checkuser.php';

require_once 'includes/i18n/languages.php';
require_once 'includes/i18n/getlang.php';
require_once 'includes/i18n/' . $lang . '.php';

require_once 'includes/version.php';

if ($userCount == 0) {
    header("Location: registration.php");
    exit();
}

session_start();
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $db->close();
    header("Location: .");
    exit();
}

// Check if login is disabled
$adminQuery = "SELECT login_disabled FROM admin";
$adminResult = $db->query($adminQuery);
$adminRow = $adminResult->fetchArray(SQLITE3_ASSOC);
if ($adminRow['login_disabled'] == 1) {

    $query = "SELECT id, username, main_currency, language FROM user WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':id', 1, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);

    if ($row === false) {
        // Something is wrong with admin user. Reenable login
        $updateQuery = "UPDATE admin SET login_disabled = 0";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->execute();

        $db->close();
        header("Location: login.php");
    } else {
        $userId = $row['id'];
        $main_currency = $row['main_currency'];
        $username = $row['username'];
        $language = $row['language'];

        $_SESSION['username'] = $username;
        $_SESSION['loggedin'] = true;
        $_SESSION['main_currency'] = $main_currency;
        $_SESSION['userId'] = $userId;
        $cookieExpire = time() + (30 * 24 * 60 * 60);
        setcookie('language', $language, [
            'expires' => $cookieExpire,
            'samesite' => 'Strict'
        ]);

        $query = "SELECT color_theme FROM settings";
        $stmt = $db->prepare($query);
        $result = $stmt->execute();
        $settings = $result->fetchArray(SQLITE3_ASSOC);
        setcookie('colorTheme', $settings['color_theme'], [
            'expires' => $cookieExpire,
            'samesite' => 'Strict'
        ]);

        $cookieValue = $username . "|" . "abc123ABC" . "|" . $main_currency;
        setcookie('wallos_login', $cookieValue, [
            'expires' => $cookieExpire,
            'samesite' => 'Strict'
        ]);

        $db->close();
        header("Location: .");
    }
}


$theme = "light";
$updateThemeSettings = false;
if (isset($_COOKIE['theme'])) {
    $theme = $_COOKIE['theme'];
} else {
    $updateThemeSettings = true;
}

$colorTheme = "blue";
if (isset($_COOKIE['colorTheme'])) {
    $colorTheme = $_COOKIE['colorTheme'];
}

$loginFailed = false;
$hasSuccessMessage = (isset($_GET['validated']) && $_GET['validated'] == "true") || (isset($_GET['registered']) && $_GET['registered'] == true) ? true : false;
$userEmailWaitingVerification = false;
if (isset($_POST['username']) && isset($_POST['password'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $rememberMe = isset($_POST['remember']) ? true : false;

    $query = "SELECT id, password, main_currency, language FROM user WHERE username = :username";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);

    if ($row) {
        $hashedPasswordFromDb = $row['password'];
        $userId = $row['id'];
        $main_currency = $row['main_currency'];
        $language = $row['language'];
        if (password_verify($password, $hashedPasswordFromDb)) {

            // Check if the user is in the email_verification table
            $query = "SELECT 1 FROM email_verification WHERE user_id = :userId";
            $stmt = $db->prepare($query);
            $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $verificationRow = $result->fetchArray(SQLITE3_ASSOC);

            if ($verificationRow) {
                $userEmailWaitingVerification = true;
                $loginFailed = true;
            } else {
                $_SESSION['username'] = $username;
                $_SESSION['loggedin'] = true;
                $_SESSION['main_currency'] = $main_currency;
                $_SESSION['userId'] = $userId;
                $cookieExpire = time() + (30 * 24 * 60 * 60);
                setcookie('language', $language, [
                    'expires' => $cookieExpire,
                    'samesite' => 'Strict'
                ]);

                if ($rememberMe) {
                    $query = "SELECT color_theme FROM settings";
                    $stmt = $db->prepare($query);
                    $result = $stmt->execute();
                    $settings = $result->fetchArray(SQLITE3_ASSOC);
                    setcookie('colorTheme', $settings['color_theme'], [
                        'expires' => $cookieExpire,
                        'samesite' => 'Strict'
                    ]);

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
                }
                $db->close();
                header("Location: .");
                exit();
            }

        } else {
            $loginFailed = true;
        }
    } else {
        $loginFailed = true;
    }
}

//Check if registration is open
$registrations = false;
$adminQuery = "SELECT registrations_open, max_users, server_url, smtp_address FROM admin";
$adminResult = $db->query($adminQuery);
$adminRow = $adminResult->fetchArray(SQLITE3_ASSOC);
$registrationsOpen = $adminRow['registrations_open'];
$maxUsers = $adminRow['max_users'];

if ($registrationsOpen == 1 && $maxUsers == 0) {
    $registrations = true;
} else if ($registrationsOpen == 1 && $maxUsers > 0) {
    $userCountQuery = "SELECT COUNT(id) as userCount FROM user";
    $userCountResult = $db->query($userCountQuery);
    $userCountRow = $userCountResult->fetchArray(SQLITE3_ASSOC);
    $userCount = $userCountRow['userCount'];
    if ($userCount < $maxUsers) {
        $registrations = true;
    }
}

$resetPasswordEnabled = false;
if ($adminRow['smtp_address'] != "" && $adminRow['server_url'] != "") {
    $resetPasswordEnabled = true;
}

?>
<!DOCTYPE html>
<html dir="<?= $languages[$lang]['dir'] ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="<?= $theme == "light" ? "#FFFFFF" : "#222222" ?>" id="theme-color" />
    <meta name="apple-mobile-web-app-title" content="Wallos">
    <title>Wallos - Subscription Tracker</title>
    <link rel="icon" type="image/png" href="images/icon/favicon.ico" sizes="16x16">
    <link rel="apple-touch-icon" href="images/icon/apple-touch-icon.png">
    <link rel="apple-touch-icon" sizes="152x152" href="images/icon/apple-touch-icon-152.png">
    <link rel="apple-touch-icon" sizes="180x180" href="images/icon/apple-touch-icon-180.png">
    <link rel="manifest" href="manifest.json">
    <link rel="stylesheet" href="styles/theme.css?<?= $version ?>">
    <link rel="stylesheet" href="styles/login.css?<?= $version ?>">
    <link rel="stylesheet" href="styles/themes/red.css?<?= $version ?>" id="red-theme" <?= $colorTheme != "red" ? "disabled" : "" ?>>
    <link rel="stylesheet" href="styles/themes/green.css?<?= $version ?>" id="green-theme" <?= $colorTheme != "green" ? "disabled" : "" ?>>
    <link rel="stylesheet" href="styles/themes/yellow.css?<?= $version ?>" id="yellow-theme" <?= $colorTheme != "yellow" ? "disabled" : "" ?>>
    <link rel="stylesheet" href="styles/themes/purple.css?<?= $version ?>" id="purple-theme" <?= $colorTheme != "purple" ? "disabled" : "" ?>>
    <link rel="stylesheet" href="styles/font-awesome.min.css">
    <link rel="stylesheet" href="styles/barlow.css">
    <link rel="stylesheet" href="styles/login-dark-theme.css?<?= $version ?>" id="dark-theme" <?= $theme == "light" ? "disabled" : "" ?>>
    <script type="text/javascript">
        window.update_theme_settings = "<?= $updateThemeSettings ?>";
        window.color_theme = "<?= $colorTheme ?>";
    </script>
    <script type="text/javascript" src="scripts/login.js?<?= $version ?>"></script>
</head>

<body class="<?= $languages[$lang]['dir'] ?>">
    <div class="content">
        <section class="container">
            <header>
                <div class="logo-image" title="Wallos - Subscription Tracker">
                    <?php include "images/siteicons/svg/logo.php"; ?>
                </div>
                <p>
                    <?= translate('please_login', $i18n) ?>
                </p>
            </header>
            <form action="login.php" method="post">
                <div class="form-group">
                    <label for="username"><?= translate('username', $i18n) ?>:</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password"><?= translate('password', $i18n) ?>:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group-inline">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember"><?= translate('stay_logged_in', $i18n) ?></label>
                </div>
                <div class="form-group">
                    <input type="submit" value="<?= translate('login', $i18n) ?>">
                </div>
                <?php
                if ($loginFailed) {
                    ?>
                    <ul class="error-box">
                        <?php
                        if ($userEmailWaitingVerification) {
                            ?>
                            <li><i
                                    class="fa-solid fa-triangle-exclamation"></i><?= translate('user_email_waiting_verification', $i18n) ?>
                            </li>
                            <?php
                        } else {
                            ?>
                            <li><i class="fa-solid fa-triangle-exclamation"></i><?= translate('login_failed', $i18n) ?></li>
                            <?php
                        }
                        ?>
                    </ul>
                    <?php
                }
                if ($hasSuccessMessage) {
                    ?>
                    <ul class="success-box">
                        <?php
                        if (isset($_GET['validated']) && $_GET['validated'] == "true") {
                            ?>
                            <li><i class="fa-solid fa-check"></i><?= translate('email_verified', $i18n) ?></li>
                            <?php
                        } else if (isset($_GET['registered']) && $_GET['registered']) {
                            ?>
                                <li><i class="fa-solid fa-check"></i><?= translate('registration_successful', $i18n) ?></li>
                                <?php
                                if (isset($_GET['requireValidation']) && $_GET['requireValidation'] == true) {
                                    ?>
                                    <li><?= translate('user_email_waiting_verification', $i18n) ?></li>
                                <?php
                                }
                        }
                        ?>
                    </ul>
                    <?php
                }

                if ($resetPasswordEnabled) {
                    ?>
                    <div class="login-form-link">
                        <a href="passwordreset.php"><?= translate('forgot_password', $i18n) ?></a>
                    </div>
                    <?php
                }
                ?>
                <?php
                if ($registrations) {
                    ?>
                    <div class="separator">
                        <input type="button" class="secondary-button" onclick="openRegitrationPage()"
                            value="<?= translate('register', $i18n) ?>"></input>
                    </div>
                    <?php
                }
                ?>
            </form>
        </section>
    </div>
    <script type="text/javascript">
        function openRegitrationPage() {
            window.location.href = "registration.php";
        }
    </script>
</body>

</html>