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

$secondsInMonth = 30 * 24 * 60 * 60;
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => $secondsInMonth,             
        'httponly' => true,          
        'samesite' => 'Lax'          
    ]);
    session_start();
}
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $db->close();
    header("Location: .");
    exit();
}

$theme = "light";
if (isset($_COOKIE['theme'])) {
    $theme = $_COOKIE['theme'];
}

$colorTheme = "blue";
if (isset($_COOKIE['colorTheme'])) {
    $colorTheme = $_COOKIE['colorTheme'];
}

$validated = false;

if (isset($_GET['email']) && isset($_GET['token'])) {
    $email = $_GET['email'];
    $token = $_GET['token'];

    $query = "SELECT * FROM email_verification WHERE email = :email AND token = :token";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
    $stmt->bindValue(':token', $token, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);

    if ($row) {
        $query = "DELETE FROM email_verification WHERE email = :email AND token = :token";
        $stmt = $db->prepare($query);
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $stmt->bindValue(':token', $token, SQLITE3_TEXT);
        $stmt->execute();

        $validated = true;

        header("Location: login.php?validated=true");
        exit;

    } else {
        $query = "SELECT require_email_verification FROM admin";
        $stmt = $db->prepare($query);
        $result = $stmt->execute();
        $settings = $result->fetchArray(SQLITE3_ASSOC);

        if ($settings['require_email_verification'] != 1) {
            header("Location: .");
            exit;
        }
    }
}

?>
<!DOCTYPE html>
<html dir="<?= $languages[$lang]['dir'] ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="<?= $theme == "light" ? "#FFFFFF" : "#12151C" ?>" />
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
    <script type="text/javascript" src="scripts/auth-theme.js?<?= $version ?>"></script>
</head>

<body class="<?= $languages[$lang]['dir'] ?>">
    <button type="button" class="theme-toggle" id="theme-toggle" title="<?= translate('theme', $i18n) ?>"
        aria-label="<?= translate('theme', $i18n) ?>">
        <i class="fa-solid <?= $theme == "dark" ? "fa-sun" : "fa-moon" ?>"></i>
    </button>
    <div class="content auth-split">
        <aside class="auth-brand" aria-hidden="true">
            <div class="auth-brand-logo">
                <?php include "images/siteicons/svg/logo.php"; ?>
            </div>
            <div class="auth-brand-text">
                <h1><?= translate('auth_tagline', $i18n) ?></h1>
                <p><?= translate('auth_tagline_sub', $i18n) ?></p>
            </div>
            <div class="auth-brand-footer">Wallos &mdash; Subscription Tracker</div>
        </aside>
        <section class="container">
            <header>
                <div class="logo-image" title="Wallos - Subscription Tracker">
                    <?php include "images/siteicons/svg/logo.php"; ?>
                </div>
            </header>
            <div class="message">
                <?php
                if ($validated == false) {
                    ?>
                    <ul class="error-box">
                        <li><i
                                class="fa-solid fa-triangle-exclamation"></i><?= translate('email_verification_failed', $i18n) ?>
                        </li>
                    </ul>
                    <?php
                }
                ?>
            </div>
            <div class="separator"></div>
            <input type="button" class="button" onclick="window.location.href='login.php'"
                value="<?= translate('login', $i18n) ?>"></input>
        </section>
    </div>
</body>

</html>