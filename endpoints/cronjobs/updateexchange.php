<?php
    require_once __DIR__ . '/../../includes/connect_endpoint_crontabs.php';

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

                    if (!$updateResult) {
                        echo "Error updating rate for currency: $currencyCode";
                    }
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
                echo "Rates updated successfully!";
            }
        } else {
            echo "Exchange rates update skipped. No fixer.io api key provided";
            $apiKey = null;
        }
    } else {
        echo "Exchange rates update skipped. No fixer.io api key provided";
        $apiKey = null;
    }
?>