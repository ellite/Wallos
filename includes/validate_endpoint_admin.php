<?php
require_once __DIR__ . '/validate_endpoint.php';
// Check that user is an admin
if ($userId !== 1) {
    // 403: signed in, and not allowed. Answering 200 here made an
    // administrative endpoint look to every log and proxy exactly like one
    // that had done the work.
    http_response_code(403);
    die(json_encode([
        "success" => false,
        "message" => translate('error', $i18n)
    ]));
}
