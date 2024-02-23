<?php

require_once '../../includes/connect_endpoint.php';

session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die(json_encode([
        "success" => false,
        "message" => translate('session_expired', $i18n)
    ]));
}

require_once '../../includes/getdbkeys.php';

$query = "SELECT * FROM subscriptions";

$result = $db->query($query);
if ($result) {
    $subscriptions = array();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        // Map foreign keys to their corresponding values
        $row['currency'] = $currencies[$row['currency_id']];
        $row['payment_method'] = $payment_methods[$row['payment_method_id']];
        $row['payer_user'] = $members[$row['payer_user_id']];
        $row['category'] = $categories[$row['category_id']];
        $row['cycle'] = $cycles[$row['cycle']];
        $row['frequency'] = $frequencies[$row['frequency']];
        
        $subscriptions[] = $row;
    }
    
    // Output JSON
    $json = json_encode($subscriptions, JSON_PRETTY_PRINT);
    
    // Set headers for file download
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="subscriptions.json"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Output JSON for download
    echo $json;
} else {
    echo json_encode(array('error' => 'Failed to fetch subscriptions.'));
}

?>