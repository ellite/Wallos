<?php

$query = "SELECT * FROM settings";
$result = $db->query($query);
$settings = $result->fetchArray(SQLITE3_ASSOC);
if ($settings) {
    $cookieExpire = time() + (30 * 24 * 60 * 60);
    setcookie('theme', $settings['dark_theme'] ? 'dark': 'light', $cookieExpire);
    $settings['theme'] = $settings['dark_theme'] ? 'dark': 'light';
    $settings['showMonthlyPrice'] = $settings['monthly_price'] ? 'true': 'false';
    $settings['convertCurrency'] = $settings['convert_currency'] ? 'true': 'false';
    $settings['removeBackground'] = $settings['remove_background'] ? 'true': 'false';
}

?>