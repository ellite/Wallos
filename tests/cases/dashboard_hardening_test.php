<?php

require_once WALLOS_ROOT . '/includes/widgets.php';
require_once WALLOS_ROOT . '/includes/database_bootstrap.php';

wallos_test('database probe never reports an unreadable file as needing recreation', function () {
    $missing = WALLOS_TEST_TMP . '/hardening-missing.db';
    @unlink($missing);
    assert_true(wallos_database_needs_create($missing), 'missing file needs create');

    $garbage = WALLOS_TEST_TMP . '/hardening-garbage.db';
    file_put_contents($garbage, str_repeat('not a sqlite database', 100));
    assert_true(!wallos_database_needs_create($garbage), 'unreadable file is left alone');
    @unlink($garbage);

    $empty = WALLOS_TEST_TMP . '/hardening-empty.db';
    @unlink($empty);
    $db = new SQLite3($empty);
    $db->exec('CREATE TABLE other (id INTEGER)');
    $db->close();
    assert_true(wallos_database_needs_create($empty), 'readable db without user table needs create');
    $db = new SQLite3($empty);
    $db->exec('CREATE TABLE user (id INTEGER)');
    $db->close();
    assert_true(!wallos_database_needs_create($empty), 'readable db with user table is kept');
    @unlink($empty);
});

wallos_test('legacy PMB entries get a stable id regardless of order', function () {
    $legacy = ['widget_id' => 'payment_method_budget', 'enabled' => true];
    $explicit = ['widget_id' => 'payment_method_budget', 'enabled' => true, 'instance_id' => 'pmb_default'];

    $a = wallos_normalize_dashboard_widget_layout_input([$legacy, $explicit]);
    $b = wallos_normalize_dashboard_widget_layout_input([$explicit, $legacy]);
    assert_true($a !== null && $b !== null, 'both orders are accepted');
    $again = wallos_normalize_dashboard_widget_layout_input([$legacy, $explicit]);
    assert_same($a, $again, 'normalizing twice is deterministic');
    $ids = array_column(array_filter($a, function ($w) { return $w['widget_id'] === 'payment_method_budget'; }), 'instance_id');
    assert_same(2, count(array_unique($ids)), 'ids are unique');
});

wallos_test('PMB instance ids are validated and the instance count is capped', function () {
    $bad = wallos_normalize_dashboard_widget_layout_input([
        ['widget_id' => 'payment_method_budget', 'enabled' => true, 'instance_id' => '"><script>'],
    ]);
    assert_true($bad === null, 'invalid instance_id is rejected');

    $many = [];
    for ($i = 0; $i < 11; $i++) {
        $many[] = ['widget_id' => 'payment_method_budget', 'enabled' => true, 'instance_id' => 'pmb_x' . $i];
    }
    assert_true(wallos_normalize_dashboard_widget_layout_input($many) === null, 'more than 10 instances rejected');
    assert_true(wallos_normalize_dashboard_widget_layout_input(array_slice($many, 0, 10)) !== null, '10 instances accepted');
});
