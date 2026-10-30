<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/chatgpt_auth.php';

$data = json_decode(file_get_contents('php://input'), true);
$callbackUrl = is_array($data) && is_string($data['url'] ?? null) ? $data['url'] : '';

try {
    $status = chatgpt_auth_complete($db, $userId, $callbackUrl);
    echo json_encode(['success' => true, 'message' => translate('success', $i18n), 'email' => $status['email'] ?? null]);
} catch (ChatGPTAuthException $e) {
    echo json_encode(chatgpt_auth_error_response($e, $i18n));
}
