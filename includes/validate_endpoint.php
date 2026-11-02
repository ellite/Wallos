<?php
// All requests should be POST requests
// CSRF Token must be included and match the token stored on the session
// User must be logged in

require_once __DIR__ . '/../libs/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Invalid request method"]);
    exit;
}

$csrf = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!verify_csrf_token($csrf)) {
    echo json_encode(["success" => false, "message" => "Invalid CSRF token"]);
    exit;
}

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(["success" => false, "message" => translate('session_expired', $i18n)]);
    exit;
}