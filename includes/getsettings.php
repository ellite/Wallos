<?php

$query = "SELECT * FROM settings";
$result = $db->query($query);
$settings = $result->fetchArray(SQLITE3_ASSOC);
if ($settings) {
    $cookieExpire = time() + (30 * 24 * 60 * 60);
    setcookie('theme', $settings['dark_theme'] ? 'dark': 'light', $cookieExpire);
    $settings['theme'] = $settings['dark_theme'] ? 'dark': 'light';
    $settings['color_theme'] = $settings['color_theme'] ? $settings['color_theme'] : "blue";
    $settings['showMonthlyPrice'] = $settings['monthly_price'] ? 'true': 'false';
    $settings['convertCurrency'] = $settings['convert_currency'] ? 'true': 'false';
    $settings['removeBackground'] = $settings['remove_background'] ? 'true': 'false';
}

$query = "SELECT * FROM custom_colors";
$result = $db->query($query);
$customColors = $result->fetchArray(SQLITE3_ASSOC);

if ($customColors) {
    $settings['customColors'] = $customColors;
}

?>