<?php

// Try to extract first and last name from "name"
$fullName = $userInfo['name'] ?? '';
$parts = explode(' ', trim($fullName), 2);
$firstname = $parts[0] ?? '';
$lastname = $parts[1] ?? '';

// Defaults
$language = 'en';
$avatar = "images/avatars/0.svg";
$budget = 0;
$main_currency_id = 1; // Euro
$password = bin2hex(random_bytes(16)); // 32-character random password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Insert user
$query = "INSERT INTO user (username, email, oidc_sub, main_currency, avatar, language, budget, firstname, lastname, password, api_key)
          VALUES (:username, :email, :oidc_sub, :main_currency, :avatar, :language, :budget, :firstname, :lastname, :password, :api_key)";
$stmt = $db->prepare($query);
$stmt->bindValue(':username', $username, SQLITE3_TEXT);
$stmt->bindValue(':email', $email, SQLITE3_TEXT);
$stmt->bindValue(':oidc_sub', $oidcSub, SQLITE3_TEXT);
$stmt->bindValue(':main_currency', $main_currency_id, SQLITE3_INTEGER);
$stmt->bindValue(':avatar', $avatar, SQLITE3_TEXT);
$stmt->bindValue(':language', $language, SQLITE3_TEXT);
$stmt->bindValue(':budget', $budget, SQLITE3_INTEGER);
$stmt->bindValue(':firstname', $firstname, SQLITE3_TEXT);
$stmt->bindValue(':lastname', $lastname, SQLITE3_TEXT);
$stmt->bindValue(':password', $hashedPassword, SQLITE3_TEXT);
$stmt->bindValue(':api_key', bin2hex(random_bytes(32)), SQLITE3_TEXT);

if (!$stmt->execute()) {
    die("Failed to create user");
}

// Get the user data into $userData
$stmt = $db->prepare("SELECT * FROM user WHERE username = :username");
$stmt->bindValue(':username', $username, SQLITE3_TEXT);
$result = $stmt->execute();
$userData = $result->fetchArray(SQLITE3_ASSOC);
$newUserId = $userData['id'];

// Household
$stmt = $db->prepare("INSERT INTO household (name, user_id) VALUES (:name, :user_id)");
$stmt->bindValue(':name', $username, SQLITE3_TEXT);
$stmt->bindValue(':user_id', $newUserId, SQLITE3_INTEGER);
$stmt->execute();

require_once __DIR__ . '/../default_names.php';

// Categories
$stmt = $db->prepare("INSERT INTO categories (name, \"order\", user_id) VALUES (:name, :order, :user_id)");
foreach (default_categories($language) as $index => $name) {
    $stmt->bindValue(':name', $name, SQLITE3_TEXT);
    $stmt->bindValue(':order', $index + 1, SQLITE3_INTEGER);
    $stmt->bindValue(':user_id', $newUserId, SQLITE3_INTEGER);
    $stmt->execute();
}

// Payment Methods
$stmt = $db->prepare("INSERT INTO payment_methods (name, icon, \"order\", user_id) VALUES (:name, :icon, :order, :user_id)");
foreach (default_payment_methods($language) as $index => $method) {
    $stmt->bindValue(':name', $method['name'], SQLITE3_TEXT);
    $stmt->bindValue(':icon', $method['icon'], SQLITE3_TEXT);
    $stmt->bindValue(':order', $index + 1, SQLITE3_INTEGER);
    $stmt->bindValue(':user_id', $newUserId, SQLITE3_INTEGER);
    $stmt->execute();
}

// Currencies
$currencies = [
    ['name' => 'Euro', 'symbol' => '€', 'code' => 'EUR'],
    ['name' => 'US Dollar', 'symbol' => '$', 'code' => 'USD'],
    ['name' => 'Japanese Yen', 'symbol' => '¥', 'code' => 'JPY'],
    ['name' => 'Bulgarian Lev', 'symbol' => 'лв', 'code' => 'BGN'],
    ['name' => 'Czech Republic Koruna', 'symbol' => 'Kč', 'code' => 'CZK'],
    ['name' => 'Danish Krone', 'symbol' => 'kr', 'code' => 'DKK'],
    ['name' => 'British Pound Sterling', 'symbol' => '£', 'code' => 'GBP'],
    ['name' => 'Hungarian Forint', 'symbol' => 'Ft', 'code' => 'HUF'],
    ['name' => 'Polish Zloty', 'symbol' => 'zł', 'code' => 'PLN'],
    ['name' => 'Romanian Leu', 'symbol' => 'lei', 'code' => 'RON'],
    ['name' => 'Swedish Krona', 'symbol' => 'kr', 'code' => 'SEK'],
    ['name' => 'Swiss Franc', 'symbol' => 'Fr', 'code' => 'CHF'],
    ['name' => 'Icelandic Króna', 'symbol' => 'kr', 'code' => 'ISK'],
    ['name' => 'Norwegian Krone', 'symbol' => 'kr', 'code' => 'NOK'],
    ['name' => 'Russian Ruble', 'symbol' => '₽', 'code' => 'RUB'],
    ['name' => 'Turkish Lira', 'symbol' => '₺', 'code' => 'TRY'],
    ['name' => 'Australian Dollar', 'symbol' => '$', 'code' => 'AUD'],
    ['name' => 'Brazilian Real', 'symbol' => 'R$', 'code' => 'BRL'],
    ['name' => 'Canadian Dollar', 'symbol' => '$', 'code' => 'CAD'],
    ['name' => 'Chinese Yuan', 'symbol' => '¥', 'code' => 'CNY'],
    ['name' => 'Hong Kong Dollar', 'symbol' => 'HK$', 'code' => 'HKD'],
    ['name' => 'Indonesian Rupiah', 'symbol' => 'Rp', 'code' => 'IDR'],
    ['name' => 'Israeli New Sheqel', 'symbol' => '₪', 'code' => 'ILS'],
    ['name' => 'Indian Rupee', 'symbol' => '₹', 'code' => 'INR'],
    ['name' => 'South Korean Won', 'symbol' => '₩', 'code' => 'KRW'],
    ['name' => 'Mexican Peso', 'symbol' => 'Mex$', 'code' => 'MXN'],
    ['name' => 'Malaysian Ringgit', 'symbol' => 'RM', 'code' => 'MYR'],
    ['name' => 'New Zealand Dollar', 'symbol' => 'NZ$', 'code' => 'NZD'],
    ['name' => 'Philippine Peso', 'symbol' => '₱', 'code' => 'PHP'],
    ['name' => 'Singapore Dollar', 'symbol' => 'S$', 'code' => 'SGD'],
    ['name' => 'Thai Baht', 'symbol' => '฿', 'code' => 'THB'],
    ['name' => 'South African Rand', 'symbol' => 'R', 'code' => 'ZAR'],
    ['name' => 'Ukrainian Hryvnia', 'symbol' => '₴', 'code' => 'UAH'],
    ['name' => 'New Taiwan Dollar', 'symbol' => 'NT$', 'code' => 'TWD'],
];

$stmt = $db->prepare("INSERT INTO currencies (name, symbol, code, rate, user_id) 
                      VALUES (:name, :symbol, :code, :rate, :user_id)");
foreach ($currencies as $currency) {
    $stmt->bindValue(':name', $currency['name'], SQLITE3_TEXT);
    $stmt->bindValue(':symbol', $currency['symbol'], SQLITE3_TEXT);
    $stmt->bindValue(':code', $currency['code'], SQLITE3_TEXT);
    $stmt->bindValue(':rate', 1.0, SQLITE3_FLOAT);
    $stmt->bindValue(':user_id', $newUserId, SQLITE3_INTEGER);
    $stmt->execute();
}

// Get actual Euro currency ID
$stmt = $db->prepare("SELECT id FROM currencies WHERE code = 'EUR' AND user_id = :user_id");
$stmt->bindValue(':user_id', $newUserId, SQLITE3_INTEGER);
$result = $stmt->execute();
$currency = $result->fetchArray(SQLITE3_ASSOC);
if ($currency) {
    $stmt = $db->prepare("UPDATE user SET main_currency = :main_currency WHERE id = :user_id");
    $stmt->bindValue(':main_currency', $currency['id'], SQLITE3_INTEGER);
    $stmt->bindValue(':user_id', $newUserId, SQLITE3_INTEGER);
    $stmt->execute();
}

$userData['main_currency'] = $currency['id'];

// Insert settings
$stmt = $db->prepare("INSERT INTO settings (dark_theme, monthly_price, convert_currency, remove_background, color_theme, hide_disabled, user_id, disabled_to_bottom, show_original_price, mobile_nav, week_starts_sunday) 
                      VALUES (2, 0, 0, 0, 'blue', 0, :user_id, 0, 0, 0, 0)");
$stmt->bindValue(':user_id', $newUserId, SQLITE3_INTEGER);
$stmt->execute();

// Log the user in
require_once('oidc_login.php');