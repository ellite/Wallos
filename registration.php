<?php
require_once 'includes/connect.php';
require_once 'includes/checkuser.php';

require_once 'includes/i18n/languages.php';
require_once 'includes/i18n/getlang.php';
require_once 'includes/i18n/' . $lang . '.php';

require_once 'includes/version.php';

function validate($value) {
    $value = trim($value);
    $value = stripslashes($value);
    $value = htmlspecialchars($value);
    $value = htmlentities($value);
    return $value;
}

if ($userCount > 0) {
    header("Location: login.php");
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

$currencies = array();
$query = "SELECT * FROM currencies";
$result = $db->query($query);
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $currencyId = $row['id'];
    $currencies[$currencyId] = $row;
}

$passwordMismatch = false;
$registrationFailed = false;
if (isset($_POST['username'])) {
    $username = validate($_POST['username']);
    $email = validate($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $main_currency = $_POST['main_currency'];
    $language = $_POST['language'];
    $avatar = "images/avatars/0.svg";

    if ($password != $confirm_password) {
        $passwordMismatch = true;
    } else {
        $query = "INSERT INTO user (username, email, password, main_currency, avatar, language) VALUES (:username, :email, :password, :main_currency, :avatar, :language)";
        $stmt = $db->prepare($query);
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $stmt->bindValue(':password', $hashedPassword, SQLITE3_TEXT);
        $stmt->bindValue(':main_currency', $main_currency, SQLITE3_TEXT);
        $stmt->bindValue(':avatar', $avatar, SQLITE3_TEXT);
        $stmt->bindValue(':language', $language, SQLITE3_TEXT);
        $result = $stmt->execute();

        if ($result) {
            $deleteQuery = "DELETE FROM household";
            $stmtDelete = $db->prepare($deleteQuery);
            $stmtDelete->execute();

            $deleteQuery = "DELETE FROM subscriptions";
            $stmtDelete = $db->prepare($deleteQuery);
            $stmtDelete->execute();

            $deleteQuery = "DELETE FROM fixer";
            $stmtDelete = $db->prepare($deleteQuery);
            $stmtDelete->execute();

            $query = "INSERT INTO household (name) VALUES (:name)";
            $stmt = $db->prepare($query);
            $stmt->bindValue(':name', $username, SQLITE3_TEXT);
            $stmt->execute();
            $db->close();
            header("Location: login.php");
            exit();
        } else {
           $registrationFailed = true;
        }
    }
}
?>
<!DOCTYPE html>
<html dir="<?= $languages[$lang]['dir'] ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <meta name="theme-color" content="<?= $theme == "light" ? "#FFFFFF" : "#222222" ?>"/>
        <title>Wallos - Subscription Tracker</title>
        <link rel="icon" type="image/png" href="images/icon/favicon.ico" sizes="16x16">
        <link rel="apple-touch-icon" sizes="180x180" href="images/icon/apple-touch-icon.png">
        <link rel="manifest" href="manifest.json">
        <link rel="stylesheet" href="styles/theme.css?<?= $version ?>">
        <link rel="stylesheet" href="styles/login.css?<?= $version ?>">
        <link rel="stylesheet" href="styles/themes/red.css?<?= $version ?>" id="red-theme" <?= $colorTheme != "red" ? "disabled" : "" ?>>
        <link rel="stylesheet" href="styles/themes/green.css?<?= $version ?>" id="green-theme" <?= $colorTheme != "green" ? "disabled" : "" ?>>
        <link rel="stylesheet" href="styles/themes/yellow.css?<?= $version ?>" id="yellow-theme" <?= $colorTheme != "yellow" ? "disabled" : "" ?>>
        <link rel="stylesheet" href="styles/login-dark-theme.css?<?= $version ?>" id="dark-theme" <?= $theme == "light" ? "disabled" : "" ?>>
        <link rel="stylesheet" href="styles/barlow.css">
        <script type="text/javascript" src="scripts/registration.js?<?= $version ?>"></script>
    </head>
    <body class="<?= $languages[$lang]['dir'] ?>">
        <div class="content">
            <section class="container">
                <header>
                <?php 
                    if ($theme == "light") {
                        ?> <img src="images/siteicons/<?= $colorTheme ?>/wallos.png" alt="Wallos Logo" title="Wallos - Subscription Tracker" width="215" /> <?php
                    } else {
                        ?> <img src="images/siteicons/<?= $colorTheme ?>/walloswhite.png" alt="Wallos Logo" title="Wallos - Subscription Tracker" width="215" /> <?php
                    }
                ?>
                    <p>
                        <?= translate('create_account', $i18n) ?>
                    </p>
                </header>
                <form action="registration.php" method="post">
                    <div class="form-group">
                        <label for="username"><?= translate('username', $i18n) ?>:</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="email"><?= translate('email', $i18n) ?>:</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="password"><?= translate('password', $i18n) ?>:</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password"><?= translate('confirm_password', $i18n) ?>:</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    <div class="form-group">
                        <label for="currency"><?= translate('main_currency', $i18n) ?>:</label>
                        <select id="currency" name="main_currency" placeholder="Currency">
                        <?php
                            foreach ($currencies as $currency) {
                        ?>
                            <option value="<?= $currency['id'] ?>"><?= $currency['name'] ?></option>
                        <?php   
                            }
                        ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="language"><?= translate('language', $i18n) ?>:</label>
                        <select id="language" name="language" placeholder="Language" onchange="changeLanguage(this.value)">
                        <?php 
                            foreach ($languages as $code => $language) {
                                $selected = ($code === $lang) ? 'selected' : '';
                        ?>
                            <option value="<?= $code ?>" <?= $selected ?>><?= $language['name'] ?></option>
                        <?php
                            }
                        ?>
                        </select>
                    </div>
                    <?php
                        if ($passwordMismatch) {
                            ?>
                            <sup class="error">
                                <?= translate('passwords_dont_match', $i18n) ?>
                            </sup>
                            <?php
                        }
                    ?>
                    <?php
                        if ($registrationFailed) {
                            ?>
                            <sup class="error">
                                <?= translate('registration_failed', $i18n) ?>
                            </sup>
                            <?php
                        }
                    ?>
                    <div class="form-group">
                        <input type="submit" value="<?= translate('register', $i18n) ?>">
                    </div>
                </form>
                <div class="separator">
                    <input type="button" class="secondary-button" value="<?= translate('restore_database', $i18n) ?>" id="restoreDB" onClick="openRestoreDBFileSelect()" />    
                    <input type="file" name="restoreDBFile" id="restoreDBFile" style="display: none;" onChange="restoreDB()" accept=".zip">
                </div>
            </section>
        </div>
        <?php
            require_once 'includes/footer.php';
        ?>
    </body>
</html>