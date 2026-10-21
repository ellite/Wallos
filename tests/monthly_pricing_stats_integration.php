<?php
// Exercise the real statistics calculation with shared payers/categories after
// updating this feature branch to main. Every run uses a disposable database.
require __DIR__ . '/bootstrap.php';
require_once WALLOS_ROOT . '/includes/subscription_payers.php';
require_once WALLOS_ROOT . '/includes/subscription_categories.php';

$mode = $argv[1] ?? 'actual';
if (!in_array($mode, ['actual', 'fallback-base', 'fallback-previous'], true)) {
    throw new InvalidArgumentException('Unknown statistics integration case');
}
$db = wallos_test_open_database();
wallos_test_create_user($db, 1, 'stats-owner');
$db->exec("DELETE FROM household WHERE user_id = 1; DELETE FROM categories WHERE user_id = 1");
$db->exec("INSERT INTO household (id, user_id, name) VALUES (10000, 1, 'Alice'), (10001, 1, 'Bob')");
$db->exec("INSERT INTO categories (id, user_id, name) VALUES (10000, 1, 'Home'), (10001, 1, 'Utilities')");
$today = date('Y-m-d');
$fallback = $mode === 'fallback-previous' ? 'previous' : 'base';
$stmt = $db->prepare("INSERT INTO subscriptions (user_id, name, price, currency_id, next_payment, cycle, frequency,
    auto_renew, inactive, payer_user_id, category_id, has_variable_price, price_history_fallback)
    VALUES (1, 'Shared utility', 80, 9010, :today, 3, 1, 1, 0, 10000, 10000, 1, :fallback)");
$stmt->bindValue(':today', $today, SQLITE3_TEXT);
$stmt->bindValue(':fallback', $fallback, SQLITE3_TEXT);
$stmt->execute();
$subscriptionId = (int) $db->lastInsertRowID();
saveSubscriptionPayers($db, $subscriptionId, [10000, 10001]);
saveSubscriptionCategories($db, $subscriptionId, [10000, 10001]);
$historyMonth = $mode === 'actual' ? date('Y-m') : date('Y-m', strtotime('first day of last month'));
$stmt = $db->prepare('INSERT INTO subscription_price_history (subscription_id, period, price) VALUES (:id, :month, 140)');
$stmt->bindValue(':id', $subscriptionId, SQLITE3_INTEGER);
$stmt->bindValue(':month', $historyMonth, SQLITE3_TEXT);
$stmt->execute();
$userId = 1;
$userData = $db->querySingle('SELECT * FROM user WHERE id = 1', true);
require WALLOS_ROOT . '/includes/i18n/languages.php';
require WALLOS_ROOT . '/includes/i18n/getlang.php';
require WALLOS_ROOT . '/includes/i18n/en.php';
$_GET = ['member' => '10000', 'category' => '10000'];
require WALLOS_ROOT . '/includes/stats_calculations.php';

$expectedTotal = $mode === 'fallback-base' ? 20 : 35;
$expectedShare = $mode === 'fallback-base' ? 40 : 70;
$checks = [
    'filtered monthly cost' => abs($totalCostPerMonth - $expectedTotal) < 0.00001,
    'amount due respects the filtered share' => abs($amountDueThisMonth - $expectedTotal) < 0.00001,
    'payer breakdown uses the full effective amount' => abs($memberCost[10000]['cost'] - $expectedShare) < 0.00001,
    'category breakdown uses the full effective amount' => abs($categoryCost[10000]['cost'] - $expectedShare) < 0.00001,
    'active count' => $activeSubscriptions === 1,
    'no cancellation savings' => (float) $totalSavingsPerMonth === 0.0,
];
$db->close();
foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FAIL $mode: $label\n");
        exit(1);
    }
}
echo 'PASS ' . $mode . ' (' . count($checks) . " checks)\n";
