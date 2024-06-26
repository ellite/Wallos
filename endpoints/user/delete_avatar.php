<?php

require_once '../../includes/connect_endpoint.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die(json_encode([
        "success" => false,
        "message" => translate('session_expired', $i18n)
    ]));
}

$input = json_decode(file_get_contents('php://input'), true);
if (isset($input['avatar'])) {
    $avatar = "images/uploads/logos/avatars/" . $input['avatar'];
    $sql = "SELECT avatar FROM user WHERE id = :userId";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $userAvatar = $result->fetchArray(SQLITE3_ASSOC)['avatar'];

    // Check if $avatar matches the avatar in the user table
    if ($avatar === $userAvatar) {
        echo json_encode(array("success" => false));
    } else {
        // The avatars do not match
        $filePath = "../../" . $avatar;
        if (file_exists($filePath)) {
            unlink($filePath);
            echo json_encode(array("success" => true, "message" => translate("success", $i18n)));
        } else {
            echo json_encode(array("success" => false, "message" => translate("error", $i18n)));
        }
    }
} else {
    echo json_encode(array("success" => false, "message" => translate("error", $i18n)));
}

?>