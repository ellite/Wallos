<?php
/*
  The defaults for the add subscription form start out matching the form as it
  was before they became configurable: auto renewal on, notifications off.
*/

wallos_test('the new subscription defaults keep the current form behaviour', function () {
    $db = wallos_test_open_database();

    $row = $db->querySingle('SELECT default_auto_renew, default_notifications FROM settings WHERE user_id = 1', true);
    assert_same(1, (int) $row['default_auto_renew'], 'auto renewal stays on by default');
    assert_same(0, (int) $row['default_notifications'], 'notifications stay off by default');

    require WALLOS_ROOT . '/migrations/000063.php';
    $row = $db->querySingle('SELECT default_auto_renew, default_notifications FROM settings WHERE user_id = 1', true);
    assert_same(1, (int) $row['default_auto_renew'], 'the migration is safe to run twice');
    assert_same(0, (int) $row['default_notifications'], 'the migration is safe to run twice');

    $db->close();
});
