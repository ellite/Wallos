<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$input = file_get_contents('php://input');
$data = json_decode($input, true);

$recommendationId = isset($data['id']) ? (int) $data['id'] : 0;

if ($recommendationId <= 0) {
    $response = [
        "success" => false,
        "message" => translate('error', $i18n)
    ];
    echo json_encode($response);
    exit;
}

// Delete the recommendation for the user
$stmt = $db->prepare("DELETE FROM ai_recommendations WHERE id = ? AND user_id = ?");
$stmt->bindValue(1, $recommendationId, SQLITE3_INTEGER);
$stmt->bindValue(2, $userId, SQLITE3_INTEGER);
$result = $stmt->execute();

if ($db->changes() > 0) {
    $response = [
        "success" => true,
        "message" => translate('success', $i18n)
    ];
} else {
    $response = [
        "success" => false,
        "message" => translate('error', $i18n)
    ];
}

echo json_encode($response);