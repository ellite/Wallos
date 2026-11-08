<?php

require_once WALLOS_ROOT . '/includes/database_bootstrap.php';

wallos_test('missing database file needs create', function () {
    $path = WALLOS_TEST_TMP . '/needs-create-missing.db';
    if (file_exists($path)) {
        unlink($path);
    }
    assert_true(wallos_database_needs_create($path), 'missing file needs create');
});

wallos_test('empty sqlite file without user table needs create', function () {
    $path = WALLOS_TEST_TMP . '/needs-create-empty.db';
    wallos_remove_database_files($path);
    $db = new SQLite3($path, SQLITE3_OPEN_CREATE | SQLITE3_OPEN_READWRITE);
    $db->exec('CREATE TABLE notifications (id INTEGER PRIMARY KEY)');
    $db->close();

    assert_true(wallos_database_needs_create($path), 'hollow schema needs recreate');
    wallos_remove_database_files($path);
});

wallos_test('sqlite file with user table does not need create', function () {
    $path = WALLOS_TEST_TMP . '/needs-create-ok.db';
    wallos_remove_database_files($path);
    $db = new SQLite3($path, SQLITE3_OPEN_CREATE | SQLITE3_OPEN_READWRITE);
    $db->exec('CREATE TABLE user (id INTEGER PRIMARY KEY, username TEXT)');
    $db->close();

    assert_true(!wallos_database_needs_create($path), 'valid schema keeps existing db');
    wallos_remove_database_files($path);
});
