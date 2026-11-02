<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$input = json_decode(file_get_contents('php://input'), true);
if (isset($input['avatar'])) {
    $baseDir = realpath("../../images/uploads/logos/avatars/");
    $avatar = $input['avatar'];
    $avatarPath = "images/uploads/logos/avatars/" . $avatar;

    $stmt = $db->prepare("SELECT id FROM uploaded_avatars WHERE user_id = :userId AND path = :path");
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $stmt->bindValue(':path', $avatarPath, SQLITE3_TEXT);
    $result = $stmt->execute();
    $ownership = $result->fetchArray(SQLITE3_ASSOC);

    if (!$ownership) {
        echo json_encode([
            "success" => false,
            "message" => "Security Error: You do not have permission to delete this file."
        ]);
        exit;
    }

    $cleanAvatar = rawurldecode($avatar);
    $cleanAvatar = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $cleanAvatar);

    $filePath = realpath($baseDir . DIRECTORY_SEPARATOR . $cleanAvatar);

    if ($filePath === false || strpos($filePath, $baseDir) !== 0) {
        echo json_encode([
            "success" => false,
            "message" => "Invalid file path."
        ]);
        exit;
    }

    $sql = "SELECT avatar FROM user WHERE id = :userId";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $userAvatar = $result->fetchArray(SQLITE3_ASSOC)['avatar'];

    // Check if $avatarPath matches the avatar in the user table
    if ($avatarPath === $userAvatar) {
        echo json_encode(array("success" => false, "message" => "Avatar in use"));
    } else {
        if (file_exists($filePath)) {
            unlink($filePath);
            $delStmt = $db->prepare("DELETE FROM uploaded_avatars WHERE id = :id");
            $delStmt->bindValue(':id', $ownership['id'], SQLITE3_INTEGER);
            $delStmt->execute();
            echo json_encode(array("success" => true, "message" => translate("success", $i18n)));
        } else {
            echo json_encode(array("success" => false, "message" => translate("error", $i18n)));
        }
    }
} else {
    echo json_encode(array("success" => false, "message" => translate("error", $i18n)));
}

?>