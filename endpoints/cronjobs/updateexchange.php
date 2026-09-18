<?php
require_once 'validate.php';
require_once __DIR__ . '/../../includes/connect_endpoint_crontabs.php';
require_once __DIR__ . '/../../includes/exchange_rate_freshness.php';
require_once __DIR__ . '/../../includes/frankfurter.php';

require 'settimezone.php';

// Get all user ids

if (php_sapi_name() == 'cli') {
    $date = new DateTime('now');
    echo "\n" . $date->format('Y-m-d') . " " . $date->format('H:i:s') . "<br />\n";
}

$query = "SELECT id, username FROM user";
$stmt = $db->prepare($query);
$usersToUpdateExchange = $stmt->execute();

while ($userToUpdateExchange = $usersToUpdateExchange->fetchArray(SQLITE3_ASSOC)) {
    $userId = $userToUpdateExchange['id'];
    echo "For user: " . $userToUpdateExchange['username'] . "<br />";

    // Asked before anything is read or fetched. This job also runs on every
    // container start, so without this a deploy costs one provider request per
    // account, and a free plan's monthly allowance goes on refreshing rates
    // that were already current.
    if (wallos_rates_refreshed_today($db, $userId)) {
        echo "Rates are already current today.<br />";
        continue;
    }

    $query = "SELECT api_key, provider FROM fixer WHERE user_id = :userId";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    if ($result) {
        $row = $result->fetchArray(SQLITE3_ASSOC);

        if ($row) {
            $apiKey = $row['api_key'];
            $provider = $row['provider'];

            $codes = "";
            $query = "SELECT id, name, symbol, code FROM currencies WHERE user_id = :userId";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
            $result = $stmt->execute();
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $codes .= $row['code'] . ",";
            }
            $codes = rtrim($codes, ',');
            $query = "SELECT u.main_currency, c.code FROM user u LEFT JOIN currencies c ON u.main_currency = c.id WHERE u.id = :userId";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);
            $mainCurrencyCode = $row['code'];
            $mainCurrencyId = $row['main_currency'];

            if ((int) $provider === 2) {
                // frankfurter.dev publishes the ECB reference rates without an
                // account, and prices in any currency it lists, so it is asked
                // in the user's own main currency and the conversion below has
                // nothing left to do. No key, no header, and https, because
                // there is no account behind it to authenticate.
                //
                // The helper answers in the same {"rates": ...} shape the two
                // providers below do, so everything after this branch is
                // unchanged.
                $apiData = frankfurter_latest_rates($mainCurrencyCode, $codes);
            } elseif ($provider === 1) {
                $api_url = "https://api.apilayer.com/fixer/latest?base=EUR&symbols=" . $codes;
                $context = stream_context_create([
                    'http' => [
                        'method' => 'GET',
                        'header' => 'apikey: ' . $apiKey,
                    ]
                ]);
                $response = file_get_contents($api_url, false, $context);
                $apiData = json_decode($response, true);
            } else {
                $api_url = "http://data.fixer.io/api/latest?access_key=" . $apiKey . "&base=EUR&symbols=" . $codes;
                $response = file_get_contents($api_url);
                $apiData = json_decode($response, true);
            }

            if ((int) $provider === 2) {
                // The answer already is in the main currency, so there is
                // nothing to divide through by. The loop below still writes the
                // main currency's own row as 1.0, which is a rule of this
                // application rather than a number read out of a response.
                $mainCurrencyToEUR = 1.0;
            } else {
                $mainCurrencyToEUR = $apiData['rates'][$mainCurrencyCode];
            }

            if ($apiData !== null && isset($apiData['rates'])) {
                // One user's rates and their refresh date are one unit of work:
                // a failure halfway through would otherwise leave some rows
                // converted against the new base and some against the old one.
                $db->exec('BEGIN');

                // Every user has their own currency rows, converted against their own
                // main currency, so the write must be scoped to the user being refreshed.
                $updateQuery = "UPDATE currencies SET rate = :rate WHERE code = :code AND user_id = :userId";
                $updateStmt = $db->prepare($updateQuery);
                $updateFailed = false;

                foreach ($apiData['rates'] as $currencyCode => $rate) {
                    if ($currencyCode === $mainCurrencyCode) {
                        $exchangeRate = 1.0;
                    } else {
                        $exchangeRate = $rate / $mainCurrencyToEUR;
                    }

                    $updateStmt->bindValue(':rate', $exchangeRate, SQLITE3_TEXT);
                    $updateStmt->bindValue(':code', $currencyCode, SQLITE3_TEXT);
                    $updateStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
                    $updateResult = $updateStmt->execute();
                    $updateStmt->reset();

                    if (!$updateResult) {
                        echo "Error updating rate for currency: $currencyCode <br />";
                        $updateFailed = true;
                        break;
                    }
                }

                if ($updateFailed) {
                    $db->exec('ROLLBACK');
                    echo "Exchange rates update rolled back for this user.<br />";
                } else {
                    $currentDate = new DateTime();
                    $formattedDate = $currentDate->format('Y-m-d');

                    $deleteQuery = "DELETE FROM last_exchange_update WHERE user_id = :userId";
                    $deleteStmt = $db->prepare($deleteQuery);
                    $deleteStmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
                    $deleteResult = $deleteStmt->execute();

                    $query = "INSERT INTO last_exchange_update (date, user_id) VALUES (:formattedDate, :userId)";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':formattedDate', $formattedDate, SQLITE3_TEXT);
                    $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
                    $result = $stmt->execute();

                    $db->exec('COMMIT');

                    // A currency the provider would not price keeps the rate it had, and
                    // saying which one is the difference between a total somebody can
                    // trust and one they cannot. Only the Frankfurter path fills this.
                    $held = isset($apiData['held']) && is_array($apiData['held']) ? $apiData['held'] : [];
                    echo "Rates updated successfully!" . ($held === []
                        ? ""
                        : " Not priced, so left unchanged: " . htmlspecialchars(implode(', ', $held)) . ".") . "<br />";
                }
            } else {
                // A refresh that stored nothing must not pass for one that
                // worked. This branch had no else at all, so a provider
                // answering with no rates moved on to the next user without a
                // word, and the only trace left was that the stored rates had
                // not moved.
                $failureMessage = "Exchange rates update failed. The currency provider returned no rates.";

                if (is_array($apiData) && isset($apiData['message']) && is_string($apiData['message'])) {
                    // frankfurter.dev explains itself in the body - it answers
                    // 422 with {"message":"invalid currency: XYZ"} and names the
                    // one code it objected to - and its own wording says more
                    // than a guess made here would.
                    $failureMessage .= " " . htmlspecialchars($apiData['message']);
                }

                echo $failureMessage . "<br />";
            }
        } else {
            echo "Exchange rates update skipped. No fixer.io api key provided<br />";
            $apiKey = null;
        }
    } else {
        echo "Exchange rates update skipped. No fixer.io api key provided<br />";
        $apiKey = null;
    }
}
$db->close();

?>