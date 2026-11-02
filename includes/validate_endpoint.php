<?php
// All requests should be POST requests
// CSRF Token must be included and match the token stored on the session
// User must be logged in
//
// Each refusal says so in the status line as well as in the body. It used to
// answer 200 with success: false, which is a refusal only a reader who parses
// the body can see: fetch() resolves, response.ok is true, a proxy or a log
// records a successful request, and a session that has expired is
// indistinguishable from a save that went through until somebody looks at the
// JSON. The body is unchanged, so every caller that reads it keeps working.

require_once __DIR__ . '/../libs/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // 405: the endpoint exists, the method does not belong to it.
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Invalid request method"]);
    exit;
}

$csrf = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!verify_csrf_token($csrf)) {
    // 403: the request is understood and refused. Not 401 - the credential
    // that is missing here is not one a browser can be asked to supply.
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Invalid CSRF token"]);
    exit;
}

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    // 401: there is no session, and signing in is what changes that.
    http_response_code(401);
    echo json_encode(["success" => false, "message" => translate('session_expired', $i18n)]);
    exit;
}
