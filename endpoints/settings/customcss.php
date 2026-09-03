<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

$customCss = $data['customCss'];

// The insert below replaces the row this delete removes, and only the insert
// was checked. A delete that fails while the insert succeeds leaves two rows
// for one user, and the readers of this table take whichever row they are
// handed first, so the page can go on serving the css this request replaced -
// after telling the user it was saved.
$stmt = $db->prepare('DELETE FROM custom_css_style WHERE user_id = :userId');
$stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);

if ($stmt->execute() === false) {
    die(json_encode([
        "success" => false,
        "message" => translate("error", $i18n)
    ]));
}

$stmt = $db->prepare('INSERT INTO custom_css_style (css, user_id) VALUES (:customCss, :userId)');
$stmt->bindParam(':customCss', $customCss, SQLITE3_TEXT);
$stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);

if ($stmt->execute()) {
    die(json_encode([
        "success" => true,
        "message" => translate("success", $i18n)
    ]));
} else {
    die(json_encode([
        "success" => false,
        "message" => translate("error", $i18n)
    ]));
}
