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

if (!isset($_SESSION['totp_user_id'])) {
    $db->close();
    header("Location: login.php");
    exit();
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

$demoMode = getenv('DEMO_MODE');

$cookieExpire = time() + (30 * 24 * 60 * 60);
$invalidTotp = false;

if (isset($_POST['one-time-code'])) {
    $totp_code = $_POST['one-time-code'];

    $statement = $db->prepare('SELECT totp_secret, backup_codes FROM totp WHERE user_id = :id');
    $statement->bindValue(':id', $_SESSION['totp_user_id'], SQLITE3_INTEGER);
    $result = $statement->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    $totp_secret = $row['totp_secret'];
    $backupCodes = json_decode($row['backup_codes'], true);

    require_once 'libs/OTPHP/FactoryInterface.php';
    require_once 'libs/OTPHP/Factory.php';
    require_once 'libs/OTPHP/ParameterTrait.php';
    require_once 'libs/OTPHP/OTPInterface.php';
    require_once 'libs/OTPHP/OTP.php';
    require_once 'libs/OTPHP/TOTPInterface.php';
    require_once 'libs/OTPHP/TOTP.php';
    require_once 'libs/Psr/Clock/ClockInterface.php';
    require_once 'libs/OTPHP/InternalClock.php';
    require_once 'libs/constant_time_encoding/Binary.php';
    require_once 'libs/constant_time_encoding/EncoderInterface.php';
    require_once 'libs/constant_time_encoding/Base32.php';

    $clock = new OTPHP\InternalClock();

    $totp = OTPHP\TOTP::createFromSecret($totp_secret, $clock);
    $totp->setPeriod(30);
    $valid = $totp->verify($totp_code, null, 15);

    // If totp is not valid check backup codes
    if (!$valid) {
        if (in_array($totp_code, $backupCodes)) {
            $key = array_search($totp_code, $backupCodes);
            unset($backupCodes[$key]);
            $backupCodes = array_values($backupCodes);

            $statement = $db->prepare('UPDATE totp SET backup_codes = :backup_codes WHERE user_id = :id');
            $statement->bindValue(':backup_codes', json_encode($backupCodes), SQLITE3_TEXT);
            $statement->bindValue(':id', $_SESSION['totp_user_id'], SQLITE3_INTEGER);
            $statement->execute();

            $valid = true;
        } else {
            $invalidTotp = true;
        }
    } else {
        $statement = $db->prepare('UPDATE totp SET last_totp_used = :last_totp_used WHERE user_id = :id');
        $statement->bindValue(':last_totp_used', time(), SQLITE3_INTEGER);
        $statement->bindValue(':id', $_SESSION['totp_user_id'], SQLITE3_INTEGER);
        $statement->execute();
    }

    if ($valid) {
        $query = "SELECT id, username, main_currency, language FROM user WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindValue(':id', $_SESSION['totp_user_id'], SQLITE3_INTEGER);
        $result = $stmt->execute();
        $user = $result->fetchArray(SQLITE3_ASSOC);

        $_SESSION['username'] = $user['username'];
        $_SESSION['loggedin'] = true;
        $_SESSION['main_currency'] = $user['main_currency'];
        $_SESSION['userId'] = $user['id'];
        setcookie('language', $user['language'], [
            'expires' => $cookieExpire,
            'samesite' => 'Strict'
        ]);

        if (!isset($_COOKIE['sortOrder'])) {
            setcookie('sortOrder', 'next_payment', [
                'expires' => $cookieExpire,
                'samesite' => 'Strict'
            ]);
        }

        $query = "SELECT color_theme FROM settings WHERE user_id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindValue(':id', $_SESSION['totp_user_id'], SQLITE3_INTEGER);
        $result = $stmt->execute();
        $settings = $result->fetchArray(SQLITE3_ASSOC);
        setcookie('colorTheme', $settings['color_theme'], [
            'expires' => $cookieExpire,
            'samesite' => 'Strict'
        ]);

        unset($_SESSION['totp_user_id']);

        $db->close();
        header("Location: .");
        exit();
    }

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
                    <?= translate('insert_totp_code', $i18n) ?>
                </p>
            </header>
            <form action="totp.php" method="post">
                <div class="form-group">
                    <label for="one-time-code"><?= translate('totp_code', $i18n) ?>:</label>
                    <input type="text" id="one-time-code" name="one-time-code" autocomplete="one-time-code" required>
                </div>
                <div class="form-group">
                    <input type="submit" value="<?= translate('login', $i18n) ?>">
                </div>
                <?php
                if ($invalidTotp) {
                    ?>
                    <ul class="error-box">
                        <li>
                            <i class="fa-solid fa-triangle-exclamation"></i><?= translate('totp_code_incorrect', $i18n) ?>
                        </li>
                    </ul>
                    <?php
                }
                ?>

            </form>
        </section>
    </div>
</body>

</html>