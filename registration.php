<?php
require_once 'includes/connect.php';
require_once 'includes/checkuser.php';

require_once 'includes/i18n/languages.php';
require_once 'includes/i18n/getlang.php';
require_once 'includes/i18n/' . $lang . '.php';

require_once 'includes/version.php';

function validate($value)
{
    $value = trim($value);
    $value = stripslashes($value);
    $value = htmlspecialchars($value);
    $value = htmlentities($value);
    return $value;
}

// If there's already a user on the database, redirect to login page if registrations are closed or maxn users is reached
$stmt = $db->prepare('SELECT COUNT(*) as userCount FROM user');
$result = $stmt->execute();
$userCountResult = $result->fetchArray(SQLITE3_ASSOC);
$userCount = $userCountResult['userCount'];

if ($userCount > 0) {
    $stmt = $db->prepare('SELECT * FROM admin');
    $result = $stmt->execute();
    $settings = $result->fetchArray(SQLITE3_ASSOC);

    if ($settings['registrations_open'] == 0) {
        header("Location: login.php");
        exit();
    }

    if ($settings['max_users'] != 0) {

        if ($userCount >= $settings['max_users']) {
            header("Location: login.php");
            exit();
        }
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

$currencies = [
    ['id' => 1, 'name' => 'Euro', 'symbol' => '€', 'code' => 'EUR'],
    ['id' => 2, 'name' => 'US Dollar', 'symbol' => '$', 'code' => 'USD'],
    ['id' => 3, 'name' => 'Japanese Yen', 'symbol' => '¥', 'code' => 'JPY'],
    ['id' => 4, 'name' => 'Bulgarian Lev', 'symbol' => 'лв', 'code' => 'BGN'],
    ['id' => 5, 'name' => 'Czech Republic Koruna', 'symbol' => 'Kč', 'code' => 'CZK'],
    ['id' => 6, 'name' => 'Danish Krone', 'symbol' => 'kr', 'code' => 'DKK'],
    ['id' => 7, 'name' => 'British Pound Sterling', 'symbol' => '£', 'code' => 'GBP'],
    ['id' => 8, 'name' => 'Hungarian Forint', 'symbol' => 'Ft', 'code' => 'HUF'],
    ['id' => 9, 'name' => 'Polish Zloty', 'symbol' => 'zł', 'code' => 'PLN'],
    ['id' => 10, 'name' => 'Romanian Leu', 'symbol' => 'lei', 'code' => 'RON'],
    ['id' => 11, 'name' => 'Swedish Krona', 'symbol' => 'kr', 'code' => 'SEK'],
    ['id' => 12, 'name' => 'Swiss Franc', 'symbol' => 'Fr', 'code' => 'CHF'],
    ['id' => 13, 'name' => 'Icelandic Króna', 'symbol' => 'kr', 'code' => 'ISK'],
    ['id' => 14, 'name' => 'Norwegian Krone', 'symbol' => 'kr', 'code' => 'NOK'],
    ['id' => 15, 'name' => 'Russian Ruble', 'symbol' => '₽', 'code' => 'RUB'],
    ['id' => 16, 'name' => 'Turkish Lira', 'symbol' => '₺', 'code' => 'TRY'],
    ['id' => 17, 'name' => 'Australian Dollar', 'symbol' => '$', 'code' => 'AUD'],
    ['id' => 18, 'name' => 'Brazilian Real', 'symbol' => 'R$', 'code' => 'BRL'],
    ['id' => 19, 'name' => 'Canadian Dollar', 'symbol' => '$', 'code' => 'CAD'],
    ['id' => 20, 'name' => 'Chinese Yuan', 'symbol' => '¥', 'code' => 'CNY'],
    ['id' => 21, 'name' => 'Hong Kong Dollar', 'symbol' => 'HK$', 'code' => 'HKD'],
    ['id' => 22, 'name' => 'Indonesian Rupiah', 'symbol' => 'Rp', 'code' => 'IDR'],
    ['id' => 23, 'name' => 'Israeli New Sheqel', 'symbol' => '₪', 'code' => 'ILS'],
    ['id' => 24, 'name' => 'Indian Rupee', 'symbol' => '₹', 'code' => 'INR'],
    ['id' => 25, 'name' => 'South Korean Won', 'symbol' => '₩', 'code' => 'KRW'],
    ['id' => 26, 'name' => 'Mexican Peso', 'symbol' => 'Mex$', 'code' => 'MXN'],
    ['id' => 27, 'name' => 'Malaysian Ringgit', 'symbol' => 'RM', 'code' => 'MYR'],
    ['id' => 28, 'name' => 'New Zealand Dollar', 'symbol' => 'NZ$', 'code' => 'NZD'],
    ['id' => 29, 'name' => 'Philippine Peso', 'symbol' => '₱', 'code' => 'PHP'],
    ['id' => 30, 'name' => 'Singapore Dollar', 'symbol' => 'S$', 'code' => 'SGD'],
    ['id' => 31, 'name' => 'Thai Baht', 'symbol' => '฿', 'code' => 'THB'],
    ['id' => 32, 'name' => 'South African Rand', 'symbol' => 'R', 'code' => 'ZAR'],
];

$categories = [
    ['id' => 1, 'name' => 'No category'],
    ['id' => 2, 'name' => 'Entertainment'],
    ['id' => 3, 'name' => 'Music'],
    ['id' => 4, 'name' => 'Utilities'],
    ['id' => 5, 'name' => 'Food & Beverages'],
    ['id' => 6, 'name' => 'Health & Wellbeing'],
    ['id' => 7, 'name' => 'Productivity'],
    ['id' => 8, 'name' => 'Banking'],
    ['id' => 9, 'name' => 'Transport'],
    ['id' => 10, 'name' => 'Education'],
    ['id' => 11, 'name' => 'Insurance'],
    ['id' => 12, 'name' => 'Gaming'],
    ['id' => 13, 'name' => 'News & Magazines'],
    ['id' => 14, 'name' => 'Software'],
    ['id' => 15, 'name' => 'Technology'],
    ['id' => 16, 'name' => 'Cloud Services'],
    ['id' => 17, 'name' => 'Charity & Donations'],
];

$payment_methods = [
    ['id' => 1, 'name' => 'PayPal', 'icon' => 'images/uploads/icons/paypal.png'],
    ['id' => 2, 'name' => 'Credit Card', 'icon' => 'images/uploads/icons/creditcard.png'],
    ['id' => 3, 'name' => 'Bank Transfer', 'icon' => 'images/uploads/icons/banktransfer.png'],
    ['id' => 4, 'name' => 'Direct Debit', 'icon' => 'images/uploads/icons/directdebit.png'],
    ['id' => 5, 'name' => 'Money', 'icon' => 'images/uploads/icons/money.png'],
    ['id' => 6, 'name' => 'Google Pay', 'icon' => 'images/uploads/icons/googlepay.png'],
    ['id' => 7, 'name' => 'Samsung Pay', 'icon' => 'images/uploads/icons/samsungpay.png'],
    ['id' => 8, 'name' => 'Apple Pay', 'icon' => 'images/uploads/icons/applepay.png'],
    ['id' => 9, 'name' => 'Crypto', 'icon' => 'images/uploads/icons/crypto.png'],
    ['id' => 10, 'name' => 'Klarna', 'icon' => 'images/uploads/icons/klarna.png'],
    ['id' => 11, 'name' => 'Amazon Pay', 'icon' => 'images/uploads/icons/amazonpay.png'],
    ['id' => 12, 'name' => 'SEPA', 'icon' => 'images/uploads/icons/sepa.png'],
    ['id' => 13, 'name' => 'Skrill', 'icon' => 'images/uploads/icons/skrill.png'],
    ['id' => 14, 'name' => 'Sofort', 'icon' => 'images/uploads/icons/sofort.png'],
    ['id' => 15, 'name' => 'Stripe', 'icon' => 'images/uploads/icons/stripe.png'],
    ['id' => 16, 'name' => 'Affirm', 'icon' => 'images/uploads/icons/affirm.png'],
    ['id' => 17, 'name' => 'AliPay', 'icon' => 'images/uploads/icons/alipay.png'],
    ['id' => 18, 'name' => 'Elo', 'icon' => 'images/uploads/icons/elo.png'],
    ['id' => 19, 'name' => 'Facebook Pay', 'icon' => 'images/uploads/icons/facebookpay.png'],
    ['id' => 20, 'name' => 'GiroPay', 'icon' => 'images/uploads/icons/giropay.png'],
    ['id' => 21, 'name' => 'iDeal', 'icon' => 'images/uploads/icons/ideal.png'],
    ['id' => 22, 'name' => 'Union Pay', 'icon' => 'images/uploads/icons/unionpay.png'],
    ['id' => 23, 'name' => 'Interac', 'icon' => 'images/uploads/icons/interac.png'],
    ['id' => 24, 'name' => 'WeChat', 'icon' => 'images/uploads/icons/wechat.png'],
    ['id' => 25, 'name' => 'Paysafe', 'icon' => 'images/uploads/icons/paysafe.png'],
    ['id' => 26, 'name' => 'Poli', 'icon' => 'images/uploads/icons/poli.png'],
    ['id' => 27, 'name' => 'Qiwi', 'icon' => 'images/uploads/icons/qiwi.png'],
    ['id' => 28, 'name' => 'ShopPay', 'icon' => 'images/uploads/icons/shoppay.png'],
    ['id' => 29, 'name' => 'Venmo', 'icon' => 'images/uploads/icons/venmo.png'],
    ['id' => 30, 'name' => 'VeriFone', 'icon' => 'images/uploads/icons/verifone.png'],
    ['id' => 31, 'name' => 'WebMoney', 'icon' => 'images/uploads/icons/webmoney.png'],
];

$passwordMismatch = false;
$usernameExists = false;
$emailExists = false;
$registrationFailed = false;
$hasErrors = false;
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
        $hasErrors = true;
    }

    $emailQuery = "SELECT * FROM user WHERE email = :email";
    $stmtEmail = $db->prepare($emailQuery);
    $stmtEmail->bindValue(':email', $email, SQLITE3_TEXT);
    $resultEmail = $stmtEmail->execute();

    if ($resultEmail->fetchArray()) {
        $emailExists = true;
        $hasErrors = true;
    }

    $usernameQuery = "SELECT * FROM user WHERE username = :username";
    $stmtUsername = $db->prepare($usernameQuery);
    $stmtUsername->bindValue(':username', $username, SQLITE3_TEXT);
    $resultUsername = $stmtUsername->execute();

    if ($resultUsername->fetchArray()) {
        $usernameExists = true;
        $hasErrors = true;
    }

    $requireValidation = false;

    if ($hasErrors == false) {
        $query = "INSERT INTO user (username, email, password, main_currency, avatar, language, budget) VALUES (:username, :email, :password, :main_currency, :avatar, :language, :budget)";
        $stmt = $db->prepare($query);
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $stmt->bindValue(':password', $hashedPassword, SQLITE3_TEXT);
        $stmt->bindValue(':main_currency', 1, SQLITE3_TEXT);
        $stmt->bindValue(':avatar', $avatar, SQLITE3_TEXT);
        $stmt->bindValue(':language', $language, SQLITE3_TEXT);
        $stmt->bindValue(':budget', 0, SQLITE3_INTEGER);
        $result = $stmt->execute();

        if ($result) {

            // Get id of the newly created user
            $userId = $db->lastInsertRowID();

            // Add username as household member for that user
            $query = "INSERT INTO household (name, user_id) VALUES (:name, :user_id)";
            $stmt = $db->prepare($query);
            $stmt->bindValue(':name', $username, SQLITE3_TEXT);
            $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
            $stmt->execute();

            if ($userId > 1) {

                // Add categories for that user
                $query = 'INSERT INTO categories (name, "order", user_id) VALUES (:name, :order, :user_id)';
                $stmt = $db->prepare($query);
                foreach ($categories as $index => $category) {
                    $stmt->bindValue(':name', $category['name'], SQLITE3_TEXT);
                    $stmt->bindValue(':order', $index + 1, SQLITE3_INTEGER);
                    $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
                    $stmt->execute();
                }

                // Add payment methods for that user
                $query = 'INSERT INTO payment_methods (name, icon, "order", user_id) VALUES (:name, :icon, :order, :user_id)';
                $stmt = $db->prepare($query);
                foreach ($payment_methods as $index => $payment_method) {
                    $stmt->bindValue(':name', $payment_method['name'], SQLITE3_TEXT);
                    $stmt->bindValue(':icon', $payment_method['icon'], SQLITE3_TEXT);
                    $stmt->bindValue(':order', $index + 1, SQLITE3_INTEGER);
                    $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
                    $stmt->execute();
                }

                // Add currencies for that user
                $query = "INSERT INTO currencies (name, symbol, code, rate, user_id) VALUES (:name, :symbol, :code, :rate, :user_id)";
                $stmt = $db->prepare($query);
                foreach ($currencies as $currency) {
                    $stmt->bindValue(':name', $currency['name'], SQLITE3_TEXT);
                    $stmt->bindValue(':symbol', $currency['symbol'], SQLITE3_TEXT);
                    $stmt->bindValue(':code', $currency['code'], SQLITE3_TEXT);
                    $stmt->bindValue(':rate', 1, SQLITE3_FLOAT);
                    $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
                    $stmt->execute();
                }

                // Retrieve main currency id
                $query = "SELECT id FROM currencies WHERE code = :code AND user_id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->bindValue(':code', $main_currency, SQLITE3_TEXT);
                $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
                $result = $stmt->execute();
                $currency = $result->fetchArray(SQLITE3_ASSOC);

                // Update user main currency
                $query = "UPDATE user SET main_currency = :main_currency WHERE id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->bindValue(':main_currency', $currency['id'], SQLITE3_INTEGER);
                $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
                $stmt->execute();

                // Add settings for that user
                $query = "INSERT INTO settings (dark_theme, monthly_price, convert_currency, remove_background, color_theme, hide_disabled, user_id) 
                          VALUES (2, 0, 0, 0, 'blue', 0, :user_id)";
                $stmt = $db->prepare($query);
                $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
                $stmt->execute();

                // If email verification is required add the user to the email_verification table
                $query = "SELECT * FROM admin";
                $stmt = $db->prepare($query);
                $result = $stmt->execute();
                $settings = $result->fetchArray(SQLITE3_ASSOC);

                if ($settings['require_email_verification'] == 1) {
                    $query = "INSERT INTO email_verification (user_id, email, token, email_sent) VALUES (:user_id, :email, :token, 0)";
                    $stmt = $db->prepare($query);
                    $token = bin2hex(random_bytes(32));
                    $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
                    $stmt->bindValue(':token', $token, SQLITE3_TEXT);
                    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
                    $stmt->execute();

                    $requireValidation = true;
                }
            }

            $db->close();
            header("Location: login.php?registered=true&requireValidation=$requireValidation");
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
    <link rel="stylesheet" href="styles/login-dark-theme.css?<?= $version ?>" id="dark-theme" <?= $theme == "light" ? "disabled" : "" ?>>
    <link rel="stylesheet" href="styles/font-awesome.min.css">
    <link rel="stylesheet" href="styles/barlow.css">
    <script type="text/javascript">
        window.update_theme_settings = "<?= $updateThemeSettings ?>";
        window.colorTheme = "<?= $colorTheme ?>";
    </script>
    <script type="text/javascript" src="scripts/registration.js?<?= $version ?>"></script>
</head>

<body class="<?= $languages[$lang]['dir'] ?>">
    <div class="content">
        <section class="container">
            <header>
                <div class="logo-image" title="Wallos - Subscription Tracker">
                    <?php include "images/siteicons/svg/logo.php"; ?>
                </div>
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
                            <option value="<?= $currency['code'] ?>"><?= $currency['name'] ?></option>
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
                if ($hasErrors) {
                    ?>
                    <ul class="error-box">
                        <?php
                        if ($passwordMismatch) {
                            ?>
                            <li><i class="fa-solid fa-triangle-exclamation"></i><?= translate('passwords_dont_match', $i18n) ?>
                            </li>
                            <?php
                        }
                        ?>
                        <?php
                        if ($usernameExists) {
                            ?>
                            <li><i class="fa-solid fa-triangle-exclamation"></i><?= translate('username_exists', $i18n) ?></li>
                            <?php
                        }
                        ?>
                        <?php
                        if ($emailExists) {
                            ?>
                            <li><i class="fa-solid fa-triangle-exclamation"></i><?= translate('email_exists', $i18n) ?></li>
                            <?php
                        }
                        ?>
                        <?php
                        if ($registrationFailed) {
                            ?>
                            <li><i class="fa-solid fa-triangle-exclamation"></i><?= translate('registration_failed', $i18n) ?>
                            </li>
                            <?php
                        }
                        ?>
                    </ul>
                    <?php
                }
                ?>


                <div class="form-group">
                    <input type="submit" value="<?= translate('register', $i18n) ?>">
                </div>
            </form>
            <?php
            if ($userCount == 0) {
                ?>
                <div class="separator">
                    <input type="button" class="secondary-button" value="<?= translate('restore_database', $i18n) ?>"
                        id="restoreDB" onClick="openRestoreDBFileSelect()" />
                    <input type="file" name="restoreDBFile" id="restoreDBFile" style="display: none;" onChange="restoreDB()"
                        accept=".zip">
                </div>
                <?php
            }
            ?>
        </section>
    </div>
    <?php
    require_once 'includes/footer.php';
    ?>
</body>

</html>