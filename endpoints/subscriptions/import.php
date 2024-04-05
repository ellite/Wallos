<?php

require_once '../../includes/connect_endpoint.php';

session_start();

if (! isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die(json_encode([
        "success" => false,
        "message" => translate('session_expired', $i18n)
    ]));
}

if (empty($_FILES['import']['name'])) {
    die(json_encode([
        "success" => false,
        "message" => translate('no_file', $i18n)
    ]));
}

$fileType = mime_content_type($_FILES['import']['tmp_name']);

if (strpos($fileType, 'json') === false) {
    die(json_encode([
        "success" => false,
        "message" => translate('file_type_error', $i18n)
    ]));
}

$fileContents = file_get_contents($_FILES['import']['tmp_name']);

$json = json_decode($fileContents);

if (! $json
    || ! is_array($json)
    || ! $json[0] ?? false
    || ! $json[0]->name ?? false
    || ! $json[0]->id ?? false
) {
    die(json_encode([
        "success" => false,
        "message" => translate('invalid_json', $i18n)
    ]));
}

require_once '../../includes/getdbkeys.php';

$query = "SELECT name FROM subscriptions";

$result = $db->query($query);

$currentSubscriptions = [];

while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $currentSubscriptions[] = $row['name'];
}

foreach ($json as $subscription) {
    if (in_array($subscription->name, $currentSubscriptions)) {
        continue;
    }

    $sql = "INSERT INTO subscriptions (name, logo, price, currency_id, next_payment, cycle, frequency, notes, 
    payment_method_id, payer_user_id, category_id, notify, inactive, url) 
    VALUES (:name, :logo, :price, :currencyId, :nextPayment, :cycle, :frequency, :notes, 
    :paymentMethodId, :payerUserId, :categoryId, :notify, :inactive, :url)";

    $stmt = $db->prepare($sql);

    $stmt->bindValue(':name', $subscription->name, SQLITE3_TEXT);
    $stmt->bindValue(':logo', $subscription->logo, SQLITE3_TEXT);
    $stmt->bindValue(':price', $subscription->price, SQLITE3_FLOAT);
    $stmt->bindValue(':currencyId', $subscription->currency_id, SQLITE3_INTEGER);
    $stmt->bindValue(':nextPayment', $subscription->next_payment, SQLITE3_TEXT);
    $stmt->bindValue(':cycle', $subscription->cycle->days, SQLITE3_INTEGER);
    $stmt->bindValue(':frequency', $subscription->frequency->id, SQLITE3_INTEGER);
    $stmt->bindValue(':notes', $subscription->notes, SQLITE3_TEXT);
    $stmt->bindValue(':paymentMethodId', $subscription->payment_method_id, SQLITE3_INTEGER);
    $stmt->bindValue(':payerUserId', $subscription->payer_user_id, SQLITE3_INTEGER);
    $stmt->bindValue(':categoryId', $subscription->category_id, SQLITE3_INTEGER);
    $stmt->bindValue(':notify', $subscription->notify, SQLITE3_INTEGER);
    $stmt->bindValue(':inactive', $subscription->inactive, SQLITE3_INTEGER);
    $stmt->bindValue(':url', $subscription->url, SQLITE3_TEXT);

    $stmt->execute();
}

die(json_encode([
    "success" => true,
    "message" => translate('subscriptions_imported', $i18n)
]));

?>
