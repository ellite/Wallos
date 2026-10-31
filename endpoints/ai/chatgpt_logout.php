<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/chatgpt_auth.php';

try {
    $confirmed = chatgpt_auth_logout($db, $userId);
} catch (ChatGPTAuthException $e) {
    // credentials that cannot be read cannot be revoked, but signing out still forgets them
    chatgpt_auth_delete($db, $userId);
    $confirmed = false;
}

echo json_encode([
    'success' => true,
    'message' => translate($confirmed ? 'success' : 'chatgpt_revocation_unconfirmed', $i18n),
]);
