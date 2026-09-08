<?php
// deleteLogoFileIfUnused() removes a detached logo file, but only when no other
// row still names it (clone.php shares a filename between two subscriptions).

require_once WALLOS_ROOT . '/includes/logo_cleanup.php';

// A logos directory holding the given files, under the test tmp tree.
function logo_cleanup_dir($files)
{
    $dir = WALLOS_TEST_TMP . '/logos-' . uniqid('', true) . '/';
    mkdir($dir, 0777, true);
    foreach ($files as $name) {
        file_put_contents($dir . $name, 'x');
    }
    return $dir;
}

function logo_cleanup_add_subscription($db, $userId, $logo, $variant = null)
{
    $stmt = $db->prepare('INSERT INTO subscriptions (user_id, name, price, currency_id, next_payment, cycle, frequency, inactive, logo, logo_variant)
                          VALUES (:userId, :name, 1, :currencyId, :next, 3, 1, 0, :logo, :variant)');
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $stmt->bindValue(':name', 'sub-' . $logo, SQLITE3_TEXT);
    $stmt->bindValue(':currencyId', wallos_test_currency_id($userId, 0), SQLITE3_INTEGER);
    $stmt->bindValue(':next', date('Y-m-d', strtotime('+10 days')), SQLITE3_TEXT);
    $stmt->bindValue(':logo', $logo, SQLITE3_TEXT);
    $stmt->bindValue(':variant', $variant, $variant === null ? SQLITE3_NULL : SQLITE3_TEXT);
    $stmt->execute();
}

wallos_test('an unreferenced logo file is deleted', function () {
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'alice');

    $dir = logo_cleanup_dir(['orphan.png']);
    deleteLogoFileIfUnused($db, 'orphan.png', $dir);

    assert_true(!file_exists($dir . 'orphan.png'), 'the orphan was removed');
    $db->close();
});

wallos_test('a logo still used by a subscription is kept', function () {
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'alice');
    logo_cleanup_add_subscription($db, 1, 'used.png');

    $dir = logo_cleanup_dir(['used.png']);
    deleteLogoFileIfUnused($db, 'used.png', $dir);

    assert_true(file_exists($dir . 'used.png'), 'a referenced logo is not touched');
    $db->close();
});

wallos_test('a file still used only as a themed variant is kept', function () {
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'alice');
    logo_cleanup_add_subscription($db, 1, 'logo.png', 'logo-variant.png');

    $dir = logo_cleanup_dir(['logo-variant.png']);
    deleteLogoFileIfUnused($db, 'logo-variant.png', $dir);

    assert_true(file_exists($dir . 'logo-variant.png'), 'the variant is still referenced');
    $db->close();
});

wallos_test('a logo shared by a clone survives deleting the original', function () {
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'alice');
    // Two subscriptions naming the same file, as clone.php leaves them.
    logo_cleanup_add_subscription($db, 1, 'shared.png');
    logo_cleanup_add_subscription($db, 1, 'shared.png');

    // First one is deleted, then the endpoint asks to clean its logo.
    $db->exec("DELETE FROM subscriptions WHERE id = (SELECT MIN(id) FROM subscriptions WHERE logo = 'shared.png')");

    $dir = logo_cleanup_dir(['shared.png']);
    deleteLogoFileIfUnused($db, 'shared.png', $dir);
    assert_true(file_exists($dir . 'shared.png'), 'the clone still uses it');

    // Now the clone goes too.
    $db->exec("DELETE FROM subscriptions WHERE logo = 'shared.png'");
    deleteLogoFileIfUnused($db, 'shared.png', $dir);
    assert_true(!file_exists($dir . 'shared.png'), 'nothing references it any more');

    $db->close();
});

wallos_test('a logo still used by a payment method is kept', function () {
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'alice');
    $db->exec("INSERT INTO payment_methods (name, icon, enabled, user_id) VALUES ('Card', 'pm-icon.png', 1, 1)");

    $dir = logo_cleanup_dir(['pm-icon.png']);
    deleteLogoFileIfUnused($db, 'pm-icon.png', $dir);

    assert_true(file_exists($dir . 'pm-icon.png'), 'a payment method icon is not an orphan');
    $db->close();
});

wallos_test('deleting an account clears its logos but not another account\'s', function () {
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 2, 'alice');
    wallos_test_create_user($db, 3, 'bob');

    logo_cleanup_add_subscription($db, 2, 'alice-1.png', 'alice-1-variant.png');
    logo_cleanup_add_subscription($db, 2, 'alice-2.png');
    logo_cleanup_add_subscription($db, 3, 'bob-1.png');
    $db->exec("INSERT INTO payment_methods (name, icon, enabled, user_id) VALUES ('Card', 'alice-pm.png', 1, 2)");
    $db->exec("INSERT INTO payment_methods (name, icon, enabled, user_id) VALUES ('Cash', 'images/uploads/icons/cash.png', 1, 2)");

    $dir = logo_cleanup_dir(['alice-1.png', 'alice-1-variant.png', 'alice-2.png', 'alice-pm.png', 'bob-1.png']);

    // What endpoints/settings/deleteaccount.php does: gather, delete rows, sweep.
    $files = [];
    $r = $db->query('SELECT logo, logo_variant FROM subscriptions WHERE user_id = 2');
    while ($row = $r->fetchArray(SQLITE3_ASSOC)) {
        $files[] = $row['logo'];
        $files[] = $row['logo_variant'];
    }
    $r = $db->query("SELECT icon FROM payment_methods WHERE user_id = 2 AND icon NOT LIKE 'images/uploads/icons/%'");
    while ($row = $r->fetchArray(SQLITE3_ASSOC)) {
        $files[] = $row['icon'];
    }
    $db->exec('DELETE FROM subscriptions WHERE user_id = 2');
    $db->exec('DELETE FROM payment_methods WHERE user_id = 2');
    foreach (array_unique(array_filter($files)) as $file) {
        deleteLogoFileIfUnused($db, $file, $dir);
    }

    assert_true(!file_exists($dir . 'alice-1.png'), 'account logo removed');
    assert_true(!file_exists($dir . 'alice-1-variant.png'), 'account logo variant removed');
    assert_true(!file_exists($dir . 'alice-2.png'), 'second account logo removed');
    assert_true(!file_exists($dir . 'alice-pm.png'), 'account payment method icon removed');
    assert_true(file_exists($dir . 'bob-1.png'), 'the other account keeps its logo');

    $db->close();
});

wallos_test('the helper stays inside the logos directory', function () {
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'alice');

    $dir = logo_cleanup_dir([]);
    $outside = dirname(rtrim($dir, '/')) . '/keep.png';
    file_put_contents($outside, 'x');

    deleteLogoFileIfUnused($db, '../keep.png', $dir);
    assert_true(file_exists($outside), 'a traversal path does not escape the directory');

    deleteLogoFileIfUnused($db, '', $dir);
    deleteLogoFileIfUnused($db, null, $dir);

    $db->close();
});
