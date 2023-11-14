<?php
require_once 'includes/connect.php';
require_once 'includes/checkuser.php';

if ($userCount > 0) {
    header("Location: login.php");
    exit();
}

$theme = "light";
if (isset($_COOKIE['theme'])) {
    $theme = $_COOKIE['theme'];
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
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $main_currency = $_POST['main_currency'];
    $avatar = "0";

    if ($password != $confirm_password) {
        $passwordMismatch = true;
    } else {
        $query = "INSERT INTO user (username, email, password, main_currency, avatar) VALUES (:username, :email, :password, :main_currency, :avatar)";
        $stmt = $db->prepare($query);
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $stmt->bindValue(':password', $hashedPassword, SQLITE3_TEXT);
        $stmt->bindValue(':main_currency', $main_currency, SQLITE3_TEXT);
        $stmt->bindValue(':avatar', $avatar, SQLITE3_TEXT);
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
<html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <title>Wallos - Subscription Tracker</title>
        <link rel="icon" type="image/png" href="images/icon/favicon.ico" sizes="16x16">
        <link rel="apple-touch-icon" sizes="180x180" href="images/icon/apple-touch-icon.png">
        <link rel="manifest" href="images/icon/site.webmanifest">
        <link rel="stylesheet" href="styles/login.css">
        <link rel="stylesheet" href="styles/login-dark-theme.css" id="dark-theme" <?= $theme == "light" ? "disabled" : "" ?>>
    </head>
    <body>
        <div class="content">
            <section class="container">
                <header>
                <?php 
                    if ($theme == "light") {
                        ?> <img src="images/wallossolid.png" alt="Wallos Logo" title="Wallos - Subscription Tracker" /> <?php
                    } else {
                        ?> <img src="images/wallossolidwhite.png" alt="Wallos Logo" title="Wallos - Subscription Tracker" /> <?php
                    }
                ?>
                    <p>
                        You need to create an account before you're able to login.
                    </p>
                </header>
                <form action="registration.php" method="post">
                    <div class="form-group">
                        <label for="username">Username:</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password:</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password:</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    <div class="form-group">
                        <label for="currency">Main Currency:</label>
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
                    <?php
                        if ($passwordMismatch) {
                            ?>
                            <sup class="error">
                                Passwords do not match.
                            </sup>
                            <?php
                        }
                    ?>
                    <?php
                        if ($registrationFailed) {
                            ?>
                            <sup class="error">
                                Registration failed, please try again.
                            </sup>
                            <?php
                        }
                    ?>
                    <div class="form-group">
                        <input type="submit" value="Register">
                    </div>
                </form>
            </section>
        </div>
        
    </body>
</html>