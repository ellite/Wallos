<?php
/*
  A subscription can be paid by several household members. subscription_payers
  is the full list, subscriptions.payer_user_id keeps the first one.
*/

require_once WALLOS_ROOT . '/includes/subscription_payers.php';

function payers_test_subscription($db, $userId, $payerId)
{
    $stmt = $db->prepare("INSERT INTO subscriptions (name, price, currency_id, next_payment, cycle, frequency, user_id, payer_user_id, inactive) VALUES ('Netflix', 12, 1, '2026-12-01', 3, 1, :userId, :payerId, 0)");
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $stmt->bindValue(':payerId', $payerId, SQLITE3_INTEGER);
    $stmt->execute();

    return $db->lastInsertRowID();
}

function payers_test_member($db, $userId, $name)
{
    $stmt = $db->prepare("INSERT INTO household (name, user_id) VALUES (:name, :userId)");
    $stmt->bindValue(':name', $name, SQLITE3_TEXT);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $stmt->execute();

    return $db->lastInsertRowID();
}

wallos_test('payer ids are parsed from lists, strings and junk alike', function () {
    assert_same([1, 2], parsePayerIds('1,2'), 'comma separated string');
    assert_same([3, 1], parsePayerIds(['3', 1, '3']), 'array keeps order, drops duplicates');
    assert_same([5], parsePayerIds('5'), 'single id');
    assert_same([], parsePayerIds(''), 'empty string');
    assert_same([], parsePayerIds(null), 'null');
    assert_same([2], parsePayerIds('abc,,0,-4,2'), 'non numeric and non positive values are dropped');
});

wallos_test('the migration gives every existing subscription its current payer, and can run twice', function () {
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'alice');
    $memberId = payers_test_member($db, 1, 'Alice');
    $subscriptionId = payers_test_subscription($db, 1, $memberId);
    $withoutPayer = payers_test_subscription($db, 1, null);

    $db->exec('DELETE FROM subscription_payers');
    require WALLOS_ROOT . '/migrations/000060.php';
    require WALLOS_ROOT . '/migrations/000060.php';

    $payers = getPayersBySubscription($db, [['id' => $subscriptionId, 'payer_user_id' => $memberId], ['id' => $withoutPayer, 'payer_user_id' => null]]);
    $rows = $db->querySingle('SELECT COUNT(*) FROM subscription_payers');
    assert_same([$memberId], $payers[$subscriptionId], 'the existing payer is carried over');
    assert_same(false, isset($payers[$withoutPayer]), 'a subscription without payer gets none');
    assert_same(1, $rows, 'running twice does not duplicate rows');

    $db->close();
});

wallos_test('saving payers replaces the list and ownership is checked per member', function () {
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'alice');
    wallos_test_create_user($db, 2, 'bob');
    $a = payers_test_member($db, 1, 'Alice');
    $b = payers_test_member($db, 1, 'Ben');
    $other = payers_test_member($db, 2, 'Mallory');
    $subscriptionId = payers_test_subscription($db, 1, $a);

    saveSubscriptionPayers($db, $subscriptionId, [$a, $b]);
    $row = ['id' => $subscriptionId, 'payer_user_id' => $a];
    assert_same([$a, $b], getPayersBySubscription($db, [$row])[$subscriptionId], 'both payers stored in order');

    saveSubscriptionPayers($db, $subscriptionId, [$b]);
    assert_same([$b], getPayersBySubscription($db, [$row])[$subscriptionId], 'the list is replaced, not appended');

    assert_true(payersBelongToUser($db, 1, [$a, $b]), 'own members accepted');
    assert_true(!payersBelongToUser($db, 1, [$a, $other]), 'one foreign member rejects the list');
    assert_true(payersBelongToUser($db, 1, []), 'no payers is not a foreign payer');

    $db->close();
});

wallos_test('filtering by a member finds the subscriptions they share in, not only the ones they lead', function () {
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'alice');
    $a = payers_test_member($db, 1, 'Alice');
    $b = payers_test_member($db, 1, 'Ben');
    $shared = payers_test_subscription($db, 1, $a);
    $onlyA = payers_test_subscription($db, 1, $a);
    saveSubscriptionPayers($db, $shared, [$a, $b]);
    saveSubscriptionPayers($db, $onlyA, [$a]);

    $params = [];
    $condition = payerFilterCondition([$b], $params);
    $stmt = $db->prepare("SELECT id FROM subscriptions WHERE user_id = 1 AND $condition");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, SQLITE3_INTEGER);
    }
    $found = [];
    $result = $stmt->execute();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $found[] = $row['id'];
    }
    assert_same([$shared], $found, 'only the shared subscription matches the second payer');

    $db->close();
});

wallos_test('payer names are joined for display and unknown ids are skipped', function () {
    assert_same('Alice, Ben', payerNamesLabel([1, 2, 9], [1 => 'Alice', 2 => 'Ben']), 'default separator');
    assert_same('Alice / Ben', payerNamesLabel([1, 2], [1 => 'Alice', 2 => 'Ben'], ' / '), 'custom separator');
    assert_same('', payerNamesLabel([], [1 => 'Alice']), 'no payers');
});

wallos_test('the statistics split a price evenly between its payers and every payer counts it', function () {
    $source = file_get_contents(WALLOS_ROOT . '/includes/stats_calculations.php');
    assert_contains('$fullPrice / count($subscriptionPayerIds)', $source, 'member cost divides by the number of payers');
    assert_contains('$totalCostPerMonth += $price;', $source, 'the total still counts the price once');
});

wallos_test('filtering statistics by member counts that member\'s share of a shared subscription', function () {
    $source = file_get_contents(WALLOS_ROOT . '/includes/stats_calculations.php');
    assert_contains('array_intersect($subscriptionPayerIds, $filteredMemberIds)) / count($subscriptionPayerIds)', $source, 'share is selected payers over all payers');
    assert_contains('$originalSubscriptionPrice *= $memberShare;', $source, 'the amount due this month uses the share too');
});

wallos_test('email reaches every payer while the other channels keep to the primary payer', function () {
    foreach (['sendnotifications.php', 'sendcancellationnotifications.php'] as $file) {
        $source = file_get_contents(WALLOS_ROOT . '/endpoints/cronjobs/' . $file);
        assert_contains('foreach ($notifyEmail as $payerUserId => $perUser)', $source, $file . ' sends email per payer');
        assert_contains('$notify[$rowSubscription[\'payer_user_id\']][$i] = $entry;', $source, $file . ' keeps the primary payer for the other channels');
        assert_contains('$notifyOtherPayers[$extraPayerId][$i] = $entry;', $source, $file . ' collects the other payers');
        assert_contains('No email set for', $source, $file . ' skips extra payers without an address');
    }
});

/* Categories work the same way, see migrations/000061.php. */

require_once WALLOS_ROOT . '/includes/subscription_categories.php';

function categories_test_category($db, $userId, $name)
{
    $stmt = $db->prepare("INSERT INTO categories (name, user_id) VALUES (:name, :userId)");
    $stmt->bindValue(':name', $name, SQLITE3_TEXT);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $stmt->execute();

    return $db->lastInsertRowID();
}

wallos_test('the categories migration carries each subscription\'s category over, and can run twice', function () {
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'alice');
    $categoryId = categories_test_category($db, 1, 'Video');
    $subscriptionId = payers_test_subscription($db, 1, null);
    $db->exec("UPDATE subscriptions SET category_id = $categoryId WHERE id = $subscriptionId");

    $db->exec('DELETE FROM subscription_categories');
    require WALLOS_ROOT . '/migrations/000061.php';
    require WALLOS_ROOT . '/migrations/000061.php';

    $found = getCategoriesBySubscription($db, [['id' => $subscriptionId, 'category_id' => $categoryId]]);
    assert_same([$categoryId], $found[$subscriptionId], 'the existing category is carried over');
    assert_same(1, $db->querySingle("SELECT COUNT(*) FROM subscription_categories WHERE subscription_id = $subscriptionId"), 'running twice does not duplicate rows');

    $db->close();
});

wallos_test('categories are saved as a list, checked for ownership and filterable by any of them', function () {
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'alice');
    wallos_test_create_user($db, 2, 'bob');
    $video = categories_test_category($db, 1, 'Video');
    $music = categories_test_category($db, 1, 'Music');
    $foreign = categories_test_category($db, 2, 'Other');
    $shared = payers_test_subscription($db, 1, null);
    $videoOnly = payers_test_subscription($db, 1, null);

    saveSubscriptionCategories($db, $shared, [$video, $music]);
    saveSubscriptionCategories($db, $videoOnly, [$video]);
    $row = ['id' => $shared, 'category_id' => $video];
    assert_same([$video, $music], getCategoriesBySubscription($db, [$row])[$shared], 'both categories stored in order');

    assert_true(categoriesBelongToUser($db, 1, [$video, $music]), 'own categories accepted');
    assert_true(!categoriesBelongToUser($db, 1, [$video, $foreign]), 'one foreign category rejects the list');

    $params = [];
    $condition = categoryFilterCondition([$music], $params);
    $stmt = $db->prepare("SELECT id FROM subscriptions WHERE user_id = 1 AND $condition");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, SQLITE3_INTEGER);
    }
    $found = [];
    $result = $stmt->execute();
    while ($r = $result->fetchArray(SQLITE3_ASSOC)) {
        $found[] = $r['id'];
    }
    assert_same([$shared], $found, 'filtering by the second category finds the shared subscription');

    $db->close();
});

wallos_test('the statistics split a price evenly between its categories, so they add up to the total', function () {
    $source = file_get_contents(WALLOS_ROOT . '/includes/stats_calculations.php');
    assert_contains('$fullPrice / count($subscriptionCategoryIds)', $source, 'category cost divides by the number of categories');
    assert_contains('array_intersect($subscriptionCategoryIds, $filteredCategoryIds)) / count($subscriptionCategoryIds)', $source, 'a category filter counts that category\'s share');
});

/* Bulk actions (endpoints/subscriptions/bulkedit.php) have to keep the junction tables in step. */

wallos_test('bulk delete removes the payer and category rows too, and bulk set-category replaces the category list', function () {
    $source = file_get_contents(WALLOS_ROOT . '/endpoints/subscriptions/bulkedit.php');
    assert_contains("foreach (['subscription_payers', 'subscription_categories'] as \$table)", $source,
        'a deleted subscription leaves no payer or category rows behind for a reused id');
    assert_true(strpos($source, "DELETE FROM \$table") < strpos($source, 'DELETE FROM subscriptions WHERE id IN'),
        'the junction rows are removed while the subscriptions can still be found');
    assert_contains('saveSubscriptionCategories($db, $subscriptionId, [$value])', $source,
        'set-category writes the list the filter and the statistics read');
});
