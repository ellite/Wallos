<?php
    require_once '../../includes/connect_endpoint.php';
    session_start();

    function update_exchange_rate($db) {
        $query = "SELECT api_key FROM fixer";
        $result = $db->query($query);

        if ($result) {
            $row = $result->fetchArray(SQLITE3_ASSOC);
            
            if ($row) {
                $apiKey = $row['api_key'];

                $codes = "";
                $query = "SELECT id, name, symbol, code FROM currencies";
                $result = $db->query($query);
                while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                    $codes .= $row['code'].",";
                }
                $codes = rtrim($codes, ',');

                $query = "SELECT u.main_currency, c.code FROM user u LEFT JOIN currencies c ON u.main_currency = c.id WHERE u.id = 1";
                $stmt = $db->prepare($query);
                $result = $stmt->execute();
                $row = $result->fetchArray(SQLITE3_ASSOC);
                $mainCurrencyCode = $row['code'];
                $mainCurrencyId = $row['main_currency'];

                $api_url = "http://data.fixer.io/api/latest?access_key=". $apiKey . "&base=EUR&symbols=" . $codes;
                $response = file_get_contents($api_url);
                $apiData = json_decode($response, true);

                $mainCurrencyToEUR = $apiData['rates'][$mainCurrencyCode];

                if ($apiData !== null && isset($apiData['rates'])) {
                    foreach ($apiData['rates'] as $currencyCode => $rate) {
                        if ($currencyCode === $mainCurrencyCode) {
                            $exchangeRate = 1.0;
                        } else {
                            $exchangeRate = $rate / $mainCurrencyToEUR;
                        }
                        $updateQuery = "UPDATE currencies SET rate = :rate WHERE code = :code";
                        $updateStmt = $db->prepare($updateQuery);
                        $updateStmt->bindParam(':rate', $exchangeRate, SQLITE3_TEXT);
                        $updateStmt->bindParam(':code', $currencyCode, SQLITE3_TEXT);
                        $updateResult = $updateStmt->execute();
                    }
                    $currentDate = new DateTime();
                    $formattedDate = $currentDate->format('Y-m-d');

                    $deleteQuery = "DELETE FROM last_exchange_update";
                    $deleteStmt = $db->prepare($deleteQuery);
                    $deleteResult = $deleteStmt->execute();

                    $query = "INSERT INTO last_exchange_update (date) VALUES (:formattedDate)";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':formattedDate', $formattedDate, SQLITE3_TEXT);
                    $result = $stmt->execute();

                    $db->close();
                }
            } 
        }
    }

    $query = "SELECT main_currency FROM user WHERE id = 1";
    $stmt = $db->prepare($query);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    $mainCurrencyId = $row['main_currency'];

    if (isset($_SESSION['username']) && isset($_POST['username']) && isset($_POST['email']) && isset($_POST['avatar'])) {
        $oldUsername = $_SESSION['username'];
        $username = $_POST['username'];
        $email = $_POST['email'];
        $avatar = $_POST['avatar'];
        $main_currency = $_POST['main_currency'];
        $language = $_POST['language'];

        if (isset($_POST['password']) && $_POST['password'] != "") {
            $password = $_POST['password'];
            if (isset($_POST['confirm_password'])) {
                $confirm = $_POST['confirm_password'];
                if ($password != $confirm) {
                    $response = [
                        "success" => false,
                        "errorMessage" => translate('passwords_dont_match', $i18n)
                    ];
                    echo json_encode($response);
                    exit();
                }
            } else {
                $response = [
                    "success" => false,
                    "errorMessage" => translate('passwords_dont_match', $i18n)
                ];
                echo json_encode($response);
                exit();
            }
        }

        if (isset($_POST['password']) && $_POST['password'] != "") {
            $sql = "UPDATE user SET avatar = :avatar, username = :username, email = :email, password = :password, main_currency = :main_currency, language = :language WHERE id = 1";
        } else {
            $sql = "UPDATE user SET avatar = :avatar, username = :username, email = :email, main_currency = :main_currency, language = :language WHERE id = 1";
        }
        
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':avatar', $avatar, SQLITE3_TEXT);
        $stmt->bindParam(':username', $username, SQLITE3_TEXT);
        $stmt->bindParam(':email', $email, SQLITE3_TEXT);
        $stmt->bindParam(':main_currency', $main_currency, SQLITE3_INTEGER);
        $stmt->bindParam(':language', $language, SQLITE3_TEXT);

        if (isset($_POST['password']) && $_POST['password'] != "") {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt->bindParam(':password', $hashedPassword, SQLITE3_TEXT);
        }

        $result = $stmt->execute();

        if ($result) {
            $cookieExpire = time() + (30 * 24 * 60 * 60);
            setcookie('language', $language, $cookieExpire, '/');
            if ($username != $oldUsername) {
                $_SESSION['username'] = $username;
                if (isset($_COOKIE['wallos_login'])) {
                    $cookie = explode('|', $_COOKIE['wallos_login'], 2) ;
                    $token = $cookie[1];
                    $cookieValue = $username . "|" . $token . "|" . $main_currency;
                }
            }
            $_SESSION['avatar'] = $avatar;
            $_SESSION['main_currency'] = $main_currency;

            if ($main_currency != $mainCurrencyId) {
                update_exchange_rate($db);
            }

            $response = [
                "success" => true,
                "message" => translate('user_details_saved', $i18n)
            ];
            echo json_encode($response);
        } else {
            $response = [
                "success" => false,
                "errorMessage" => translate('error_updating_user_data', $i18n)
            ];
            echo json_encode($response);
        }

        exit();
    } else {
        $response = [
            "success" => false,
            "errorMessage" => translate('fill_all_fields', $i18n)
        ];
        echo json_encode($response);
        exit();
    }
?>