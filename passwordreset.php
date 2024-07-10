<?php

require_once 'includes/connect.php';
require_once 'includes/checkuser.php';

require_once 'includes/i18n/languages.php';
require_once 'includes/i18n/getlang.php';
require_once 'includes/i18n/' . $lang . '.php';

require_once 'includes/version.php';

session_start();
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $db->close();
    header("Location: .");
    exit();
}

$requestMode = true;
$resetMode = false;

$theme = "light";
if (isset($_COOKIE['theme'])) {
    $theme = $_COOKIE['theme'];
}

$colorTheme = "blue";
if (isset($_COOKIE['colorTheme'])) {
    $colorTheme = $_COOKIE['colorTheme'];
}

$settings = $db->querySingle("SELECT * FROM admin", true);
if ($settings['smtp_address'] == "" || $settings['server_url'] == "") {
    header("Location: .");
} else {
    $resetPasswordEnabled = true;
}

$hasSuccessMessage = false;
$hasErrorMessage = false;
$passwordsMismatch = false;
$hideForm = false;

if (isset($_POST['email']) && $_POST['email'] != "" && isset($_GET['submit']) && $_GET['submit'] && !(isset($_GET['token'])) && !(isset($_POST['token']))) {
    $requestMode = true;
    $resetMode = false;
    $email = $_POST['email'];
    $user = $db->querySingle("SELECT * FROM user WHERE email = '$email'", true);
    if ($user) {
        $db->exec("DELETE FROM password_resets WHERE email = '$email'");
        $token = bin2hex(random_bytes(32));
        $db->exec("INSERT INTO password_resets (user_id, email, token) VALUES (" . $user['id'] . ", '$email', '$token')");
    }
    $hasSuccessMessage = true;
}

if (isset($_GET['token']) && $_GET['token'] != "" && isset($_GET['email']) && $_GET['email'] != "") {
    $requestMode = false;
    $resetMode = true;
    $token = $_GET['token'];
    $email = $_GET['email'];
    $matchCount = "SELECT COUNT(*) FROM password_resets WHERE token = :token and email = :email";
    $stmt = $db->prepare($matchCount);
    $stmt->bindValue(':token', $token, SQLITE3_TEXT);
    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
    $count = $stmt->execute()->fetchArray(SQLITE3_NUM);
    if ($count[0] == 0) {
        $hasErrorMessage = true;
        $hideForm = true;
    }
}

if (isset($_POST['password']) && $_POST['password'] != "" && isset($_POST['confirm_password']) && $_POST['confirm_password'] != "" && isset($_GET['submit']) && $_GET['submit']) {
    $requestMode = false;
    $resetMode = true;
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $token = $_POST['token'];
    $email = $_POST['email'];
    $resetQuery = "SELECT * FROM password_resets WHERE token = :token AND email = :email";
    $stmt = $db->prepare($resetQuery);
    $stmt->bindValue(':token', $token, SQLITE3_TEXT);
    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
    $reset = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

    if ($reset) {
        $user = $db->querySingle("SELECT * FROM user WHERE email = '" . $reset['email'] . "'", true);
        if ($password == $confirmPassword) {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $db->exec("UPDATE user SET password = '$passwordHash' WHERE id = " . $user['id']);
            $db->exec("DELETE FROM password_resets WHERE token = '$token'");
            $hasSuccessMessage = true;
            $hideForm = true;
        } else {
            $hasErrorMessage = true;
            $passwordsMismatch = true;
        }
    } else {
        $hasSuccessMessage = false;
        $hasErrorMessage = true;
    }
}

?>
<!DOCTYPE html>
<html dir="<?= $languages[$lang]['dir'] ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="<?= $theme == "light" ? "#FFFFFF" : "#222222" ?>" />
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
</head>

<body class="<?= $languages[$lang]['dir'] ?>">
    <div class="content">
        <section class="container">
            <header>
                <div class="logo-image" title="Wallos - Subscription Tracker">
                    <?php include "images/siteicons/svg/logo.php"; ?>
                </div>
                <p>
                    <?= translate('reset_password', $i18n) ?>
                </p>
            </header>
            <form action="passwordreset.php?submit=true" method="post">
                <?php
                if ($requestMode) {
                    if (!$hideForm) {
                        ?>
                        <div class="form-group">
                            <label for="email"><?= translate('email', $i18n) ?>:</label>
                            <input type="text" id="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <input type="submit" value="<?= translate('reset_password', $i18n) ?>">
                        </div>
                        <?php
                    }
                    if ($hasSuccessMessage) {
                        ?>
                        <ul class="success-box">
                            <li><i class="fa-solid fa-check"></i><?= translate('reset_sent_check_email', $i18n) ?></li>
                        </ul>
                        <?php
                    }
                    if ($hasErrorMessage) {
                        ?>
                        <ul class="error-box">
                            <li><i class="fa-solid fa-triangle-exclamation"></i><?= translate('error', $i18n) ?></li>
                        </ul>
                        <?php
                    }
                }
                if ($resetMode) {
                    if (!$hideForm) {
                        ?>
                        <div class="form-group">
                            <input type="hidden" name="token" value="<?= $token ?>">
                            <input type="hidden" name="email" value="<?= $email ?>">
                            <label for="password"><?= translate('password', $i18n) ?>:</label>
                            <input type="password" id="password" name="password" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password"><?= translate('confirm_password', $i18n) ?>:</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                        <div class="form-group">
                            <input type="submit" value="<?= translate('reset_password', $i18n) ?>">
                        </div>
                        <?php
                    }
                    if ($hasErrorMessage) {
                        if ($passwordsMismatch) {
                            ?>
                            <ul class="error-box">
                                <li><i class="fa-solid fa-triangle-exclamation"></i><?= translate('passwords_dont_match', $i18n) ?>
                                </li>
                            </ul>
                            <?php
                        } else {
                            ?>
                            <ul class="error-box">
                                <li><i class="fa-solid fa-triangle-exclamation"></i><?= translate('error', $i18n) ?></li>
                            </ul>
                            <?php
                        }
                    }
                    if ($hasSuccessMessage) {
                        ?>
                        <ul class="success-box">
                            <li><i class="fa-solid fa-check"></i><?= translate('password_reset_successful', $i18n) ?></li>
                        </ul>
                        <?php
                    }
                }
                ?>
                <div class="login-form-link">
                    <a href="login.php"><?= translate('login', $i18n) ?></a>
                </div>
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

?>