<?php
/*
  Deleting an account removes what belongs to it.

  Both deletion paths — the self-service one and the administrator one — named
  their tables by hand, and the hand-written list had fallen twelve tables
  behind the schema. Two of the twelve hold credentials: `login_tokens` and
  `password_resets`.

  That is not only untidiness. The `user` table is declared
  `id INTEGER PRIMARY KEY` with no AUTOINCREMENT, so SQLite hands a deleted id
  straight back to the next account created — and that account inherited the
  leftovers, including a remember-me token that still worked.

  The list below is read from the schema rather than written down again, so a
  table added by a future migration is covered by this test on the day it
  appears, without anybody remembering to come back here.
*/

/**
 * Every table the schema gives a user_id column.
 *
 * @param SQLite3 $db
 * @return string[]
 */
function account_deletion_user_tables($db)
{
    $tables = [];
    $result = $db->query("SELECT name FROM sqlite_master WHERE type = 'table'
                          AND name NOT LIKE 'sqlite_%' ORDER BY name");

    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $columns = $db->query('PRAGMA table_info("' . $row['name'] . '")');

        while ($column = $columns->fetchArray(SQLITE3_ASSOC)) {
            if ($column['name'] === 'user_id') {
                $tables[] = $row['name'];
                break;
            }
        }
    }

    return $tables;
}

/**
 * The tables a deletion path names.
 *
 * @param string $path
 * @return string[]
 */
function account_deletion_tables_named($path)
{
    $source = file_get_contents(WALLOS_ROOT . '/' . $path);
    preg_match_all('/DELETE FROM\s+["\']?(\w+)["\']?/i', $source, $matches);

    return array_values(array_unique($matches[1]));
}

wallos_test('both deletion paths cover every table that belongs to a user', function () {
    $db = wallos_test_open_database();
    $userTables = account_deletion_user_tables($db);
    $db->close();

    // A guard that finds nothing passes every assertion after it.
    assert_true(count($userTables) >= 25,
        'the schema was read (' . count($userTables) . ' tables carry a user_id)');

    foreach ([
        'endpoints/settings/deleteaccount.php',
        'endpoints/admin/deleteuser.php',
    ] as $path) {
        $named = account_deletion_tables_named($path);
        $missing = array_values(array_diff($userTables, $named));

        assert_same([], $missing,
            $path . ' leaves nothing behind (missing: ' . implode(', ', $missing) . ')');
    }
});

wallos_test('the two paths agree with each other', function () {
    // They were two hand-maintained copies of one list, which is how they
    // drifted together and would drift apart. Whichever one a future table is
    // added to, this fails until it is added to both.
    $self = account_deletion_tables_named('endpoints/settings/deleteaccount.php');
    $admin = account_deletion_tables_named('endpoints/admin/deleteuser.php');

    sort($self);
    sort($admin);

    assert_same($self, $admin,
        'the self-service and administrator paths delete the same tables');
});

wallos_test('the credentials of a deleted account do not survive it', function () {
    // Named separately from the schema sweep above, because these two are the
    // reason the sweep is worth having: an id handed back out carries them to
    // whoever gets it next.
    foreach ([
        'endpoints/settings/deleteaccount.php',
        'endpoints/admin/deleteuser.php',
    ] as $path) {
        $named = account_deletion_tables_named($path);

        assert_true(in_array('login_tokens', $named, true),
            $path . ' removes the remember-me tokens');
        assert_true(in_array('password_resets', $named, true),
            $path . ' removes the outstanding password reset tokens');
    }
});
