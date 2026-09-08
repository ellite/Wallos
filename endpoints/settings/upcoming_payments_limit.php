<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/upcoming_payments.php';

$postData = file_get_contents('php://input');
$data = json_decode($postData, true);
$limit = parse_upcoming_payments_limit($data['value'] ?? null);

if ($limit === null) {
    die(json_encode([
        'success' => false,
        'message' => translate('error', $i18n),
    ]));
}

$stmt = $db->prepare('UPDATE settings SET upcoming_payments_limit = :limit WHERE user_id = :userId');
$stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);

if ($stmt->execute()) {
    die(json_encode([
        'success' => true,
        'message' => translate('success', $i18n),
    ]));
}

die(json_encode([
    'success' => false,
    'message' => translate('error', $i18n),
]));
