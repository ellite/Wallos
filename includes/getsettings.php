<?php

$query = "SELECT * FROM settings WHERE user_id = :userId";
$stmt = $db->prepare($query);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();

$settings = $result->fetchArray(SQLITE3_ASSOC);
if ($settings !== false) {
    $themeMapping = array(0 => 'light', 1 => 'dark', 2 => 'automatic');
    $themeKey = isset($settings['dark_theme']) ? $settings['dark_theme'] : 2;
    $themeValue = $themeMapping[$themeKey];
    $settings['update_theme_setttings'] = false;
    if (isset($_COOKIE['inUseTheme']) && $settings['dark_theme'] == 2) {
        $inUseTheme = $_COOKIE['inUseTheme'];
        $settings['theme'] = $inUseTheme;
    } else {
        $settings['theme'] = $themeValue;
    }
    if ($themeValue == "automatic") {
        $settings['update_theme_setttings'] = true;
    }
    $settings['color_theme'] = $settings['color_theme'] ? $settings['color_theme'] : "blue";
    $settings['showMonthlyPrice'] = $settings['monthly_price'] ? 'true': 'false';
    $settings['convertCurrency'] = $settings['convert_currency'] ? 'true': 'false';
    $settings['removeBackground'] = $settings['remove_background'] ? 'true': 'false';
    $settings['hideDisabledSubscriptions'] = $settings['hide_disabled'] ? 'true': 'false';
    $settings['disabledToBottom'] = $settings['disabled_to_bottom'] ? 'true': 'false';
    $settings['showOriginalPrice'] = $settings['show_original_price'] ? 'true': 'false';
    $settings['mobileNavigation'] = $settings['mobile_nav'] ? 'true': 'false';
    $settings['showSubscriptionProgress'] = $settings['show_subscription_progress'] ? 'true': 'false';
}

$query = "SELECT * FROM custom_colors WHERE user_id = :userId";
$stmt = $db->prepare($query);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$customColors = $result->fetchArray(SQLITE3_ASSOC);

if ($customColors !== false) {
    $settings['customColors'] = $customColors;
}

$query = "SELECT * FROM custom_css_style WHERE user_id = :userId";
$stmt = $db->prepare($query);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$customCss = $result->fetchArray(SQLITE3_ASSOC);
if ($customCss !== false) {
    $settings['customCss'] = $customCss['css'];
}

$query = "SELECT * FROM admin";
$result = $db->query($query);
$adminSettings = $result->fetchArray(SQLITE3_ASSOC);

if ($adminSettings !== false) {
    $settings['disableLogin'] = $adminSettings['login_disabled'];
    $settings['update_notification'] = $adminSettings['update_notification'];
    $settings['latest_version'] = $adminSettings['latest_version'];
}

?>