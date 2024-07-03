<?php

$query = "SELECT * FROM settings WHERE user_id = :userId";
$stmt = $db->prepare($query);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();

$settings = $result->fetchArray(SQLITE3_ASSOC);
if ($settings) {
    $cookieExpire = time() + (30 * 24 * 60 * 60);
    $themeMapping = array(0 => 'light', 1 => 'dark', 2 => 'automatic');
    $themeKey = isset($settings['dark_theme']) ? $settings['dark_theme'] : 2;
    $themeValue = $themeMapping[$themeKey];
    setcookie('theme', $themeValue, $cookieExpire);
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
}

$query = "SELECT * FROM custom_colors WHERE user_id = :userId";
$stmt = $db->prepare($query);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$customColors = $result->fetchArray(SQLITE3_ASSOC);

if ($customColors) {
    $settings['customColors'] = $customColors;
}

$query = "SELECT * FROM custom_css_style WHERE user_id = :userId";
$stmt = $db->prepare($query);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$customCss = $result->fetchArray(SQLITE3_ASSOC);
if ($customCss) {
    $settings['customCss'] = $customCss['css'];
}

$query = "SELECT * FROM admin";
$result = $db->query($query);
$adminSettings = $result->fetchArray(SQLITE3_ASSOC);

if ($adminSettings) {
    $settings['disableLogin'] = $adminSettings['login_disabled'];
}

?>