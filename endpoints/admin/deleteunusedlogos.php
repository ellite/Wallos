<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint_admin.php';

$query = 'SELECT logo FROM subscriptions';
$stmt = $db->prepare($query);
$result = $stmt->execute();

$logosOnDB = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $logosOnDB[] = $row['logo'];
}

$logosOnDB = array_unique($logosOnDB);

$uploadDir = '../../images/uploads/logos/';
$uploadFiles = scandir($uploadDir);

foreach ($uploadFiles as $file) {
    if ($file != '.' && $file != '..' && $file != 'avatars') {
        $logosOnDisk[] = ['logo' => $file];
    }
}

 // Get all logos in the payment_methods table
 $query = 'SELECT icon FROM payment_methods';
 $stmt = $db->prepare($query);
 $result = $stmt->execute();

 while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
     if (!strstr($row['icon'], "images/uploads/icons/")) {
         $logosOnDB[] = $row['icon'];
     }
 }

 $logosOnDB = array_unique($logosOnDB);

// Find and delete unused logos
$count = 0;
foreach ($logosOnDisk as $disk) {
    foreach ($logosOnDB as $db) {
        $found = false;
        if ($disk['logo'] == $db) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        unlink($uploadDir . $disk['logo']);
        $count++;
    }
}

echo json_encode([
    "success" => true,
    "message" => translate('success', $i18n),
    'count' => $count
]);


?>