<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/widgets.php';

$postData = file_get_contents('php://input');
$data = json_decode($postData, true);

$widgets = $data['widgets'] ?? null;
$normalized = wallos_normalize_dashboard_widget_layout_input($widgets);

if ($normalized === null) {
    die(json_encode([
        'success' => false,
        'message' => translate('error', $i18n),
    ]));
}

// Drop payment method ids that don't belong to this user.
$ownedStmt = $db->prepare('SELECT id FROM payment_methods WHERE user_id = :userId');
$ownedStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$ownedResult = $ownedStmt->execute();
$ownedIds = [];
while ($row = $ownedResult->fetchArray(SQLITE3_ASSOC)) {
    $ownedIds[(int) $row['id']] = true;
}
foreach ($normalized as &$entry) {
    if (isset($entry['payment_method_ids'])) {
        $entry['payment_method_ids'] = array_values(array_filter(
            $entry['payment_method_ids'],
            function ($id) use ($ownedIds) {
                return isset($ownedIds[(int) $id]);
            }
        ));
    }
}
unset($entry);

// Keep legacy boolean columns in sync (OR of instances for payment_method_budget).
$legacyEnabled = [];
foreach (wallos_widget_ids() as $widgetId) {
    $legacyEnabled[$widgetId] = false;
}
foreach ($normalized as $entry) {
    if (!empty($entry['enabled'])) {
        $legacyEnabled[$entry['widget_id']] = true;
    }
}

$sets = ['dashboard_widget_layout = :layout'];
foreach ($legacyEnabled as $widgetId => $enabled) {
    $sets[] = wallos_widget_setting_column($widgetId) . ' = :flag_' . $widgetId;
}

// Single statement: layout + legacy flags are written atomically.
$stmt = $db->prepare('UPDATE settings SET ' . implode(', ', $sets) . ' WHERE user_id = :userId');
$stmt->bindValue(':layout', json_encode($normalized, JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
foreach ($legacyEnabled as $widgetId => $enabled) {
    $stmt->bindValue(':flag_' . $widgetId, $enabled ? 1 : 0, SQLITE3_INTEGER);
}
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);

if ($stmt->execute()) {
    die(json_encode([
        'success' => true,
        'message' => translate('success', $i18n),
        'widgets' => $normalized,
    ]));
}

die(json_encode([
    'success' => false,
    'message' => translate('error', $i18n),
]));
