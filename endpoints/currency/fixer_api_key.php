<?php
    require_once '../../includes/connect_endpoint.php';
    session_start();

    if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
        if ($_SERVER["REQUEST_METHOD"] === "POST") {
            $newApiKey = isset($_POST["api_key"]) ? $_POST["api_key"] : "";
            $removeOldKey = "DELETE FROM fixer";
            $db->exec($removeOldKey);
            $testKeyUrl = "http://data.fixer.io/api/latest?access_key=$newApiKey";
            $response = file_get_contents($testKeyUrl);
            $apiData = json_decode($response, true);
            if ($apiData['success'] && $apiData['success'] == 1) {
                if (!empty($newApiKey)) {
                    $insertNewKey = "INSERT INTO fixer (api_key) VALUES (:api_key)";
                    $stmt = $db->prepare($insertNewKey);
                    $stmt->bindParam(":api_key", $newApiKey, SQLITE3_TEXT);
                    $result = $stmt->execute();
                    if ($result) {
                        echo json_encode(["success" => true, "message" => translate('api_key_saved', $i18n)]);
                    } else {
                        $response = [
                            "success" => false,
                            "message" => translate('failed_to_store_api_key', $i18n)
                        ];
                        echo json_encode($response);
                    }
                } else {
                    echo json_encode(["success" => true, "message" => translate('apy_key_saved', $i18n)]);
                }
            } else {
                $response = [
                    "success" => false,
                    "message" => translate('invalid_api_key', $i18n)
                ];
                echo json_encode($response);
            }
        }
    }
    
?>