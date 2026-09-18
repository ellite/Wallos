<?php

/*
  Saves one device's push subscription - called by the frontend right after
  a successful pushManager.subscribe(), not by anything the user fills in
  themselves. That still makes the endpoint URL something to validate: it is
  attacker-influenceable through this very request body (see #1220's fix to
  this same class of problem elsewhere in this codebase), even though a
  legitimate one only ever comes from a real push service and is always
  https. Both are checked before anything is stored, the same way every
  other channel's webhook-shaped URL already is.
*/

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/ssrf_helper.php';
require_once '../../includes/webpush_helper.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

$endpoint = isset($data['endpoint']) ? trim((string) $data['endpoint']) : '';
$p256dh = isset($data['keys']['p256dh']) ? trim((string) $data['keys']['p256dh']) : '';
$auth = isset($data['keys']['auth']) ? trim((string) $data['keys']['auth']) : '';
$userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255) : '';

if ($endpoint === '' || $p256dh === '' || $auth === '') {
    die(json_encode([
        "success" => false,
        "message" => translate('fill_mandatory_fields', $i18n)
    ]));
}

$parsedEndpoint = parse_url($endpoint);
if (
    !$parsedEndpoint ||
    !isset($parsedEndpoint['scheme'], $parsedEndpoint['host']) ||
    strtolower($parsedEndpoint['scheme']) !== 'https'
) {
    die(json_encode([
        "success" => false,
        "message" => translate('error', $i18n)
    ]));
}

// A real subscription's keys are always exactly this shape - 65 raw bytes
// for an uncompressed P-256 point, 16 for the auth secret. Anything else
// cannot come from a real browser subscription and could only ever fail
// every future send, so it is refused here rather than stored to fail later.
if (strlen(webpush_base64url_decode($p256dh)) !== 65 || strlen(webpush_base64url_decode($auth)) !== 16) {
    die(json_encode([
        "success" => false,
        "message" => translate('error', $i18n)
    ]));
}

validate_webhook_url_for_ssrf($endpoint, $db, $i18n, $userId);

$query = "SELECT id FROM push_subscriptions WHERE user_id = :userId AND endpoint = :endpoint";
$stmt = $db->prepare($query);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$stmt->bindValue(':endpoint', $endpoint, SQLITE3_TEXT);
$result = $stmt->execute();

if ($result === false) {
    die(json_encode([
        "success" => false,
        "message" => translate('error_saving_notifications', $i18n)
    ]));
}

$existing = $result->fetchArray(SQLITE3_ASSOC);

if ($existing) {
    // The same device subscribing again - a browser can rotate keys for an
    // existing endpoint, so the row is refreshed rather than left stale or
    // duplicated under the same endpoint.
    $subscriptionId = (int) $existing['id'];
    $query = "UPDATE push_subscriptions SET p256dh = :p256dh, auth = :auth, user_agent = :userAgent WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':id', $subscriptionId, SQLITE3_INTEGER);
} else {
    $query = "INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth, user_agent, created_at)
              VALUES (:userId, :endpoint, :p256dh, :auth, :userAgent, :createdAt)";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $stmt->bindValue(':endpoint', $endpoint, SQLITE3_TEXT);
    $stmt->bindValue(':createdAt', (new DateTime('now'))->format('Y-m-d H:i:s'), SQLITE3_TEXT);
}

$stmt->bindValue(':p256dh', $p256dh, SQLITE3_TEXT);
$stmt->bindValue(':auth', $auth, SQLITE3_TEXT);
$stmt->bindValue(':userAgent', $userAgent, SQLITE3_TEXT);

if ($stmt->execute()) {
    // Handed back so the settings page can add or refresh this device's row
    // itself, rather than reloading the whole page to pick it up.
    echo json_encode([
        "success" => true,
        "message" => translate('notifications_settings_saved', $i18n),
        "subscription" => [
            "id" => $existing ? $subscriptionId : (int) $db->lastInsertRowID(),
            "user_agent" => $userAgent !== '' ? $userAgent : translate('unknown_device', $i18n),
            "is_new" => !$existing,
        ],
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => translate('error_saving_notifications', $i18n)
    ]);
}
