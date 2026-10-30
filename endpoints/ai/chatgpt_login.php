<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/chatgpt_auth.php';

try {
    echo json_encode(['success' => true, 'auth_url' => chatgpt_auth_start($db, $userId)]);
} catch (ChatGPTAuthException $e) {
    echo json_encode(chatgpt_auth_error_response($e, $i18n));
}
