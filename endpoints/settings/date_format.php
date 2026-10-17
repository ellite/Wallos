<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/date_format.php';

$postData = file_get_contents('php://input');
$data = json_decode($postData, true);
$format = parse_date_format($data['value'] ?? null);

if ($format === null) {
    die(json_encode([
        'success' => false,
        'message' => translate('error', $i18n),
    ]));
}

$stmt = $db->prepare('UPDATE settings SET date_format = :format WHERE user_id = :userId');
$stmt->bindValue(':format', $format, SQLITE3_TEXT);
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
