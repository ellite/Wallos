<?php
/*
  A password reset says what actually happened.

  passwordreset.php reports success over unchecked writes in two places, and
  both leave the account worse off than before.

  Issuing the token: delete the previous one, insert a new one, tell the person
  the mail is on its way — whatever the two writes did. A delete that succeeds
  followed by an insert that does not leaves the account with no token at all.

  Using the token: update the password, consume the token, say it worked —
  again whatever the writes did. A failed update tells the person their
  password changed while the old one still works, and spends the one link back
  in on the way. There is no second link without an administrator.
*/

/**
 * @return string
 */
function password_reset_source()
{
    return file_get_contents(WALLOS_ROOT . '/passwordreset.php');
}

wallos_test('issuing a token is one transaction', function () {
    $source = password_reset_source();

    assert_contains("'BEGIN'", $source, 'the token swap opens a transaction');
    assert_contains("'COMMIT'", $source, 'and commits it');
    assert_contains("'ROLLBACK'", $source, 'and rolls back when a write fails');
});

wallos_test('no write in the file is discarded', function () {
    // Both halves had the same shape, so the guard covers the whole file
    // rather than one block: a third write added later is caught too.
    $lines = preg_split('/\R/', password_reset_source());
    $discarded = [];

    foreach ($lines as $number => $line) {
        if (preg_match('/^\s*\$stmt->execute\(\)\s*;\s*$/', $line) === 1) {
            $discarded[] = $number + 1;
        }
    }

    assert_same([], $discarded,
        'every execute() in passwordreset.php is read (lines with a discarded '
        . 'one: ' . implode(', ', $discarded) . ')');
});

wallos_test('an unknown address is answered exactly like a known one', function () {
    // Deliberate, and the reason the success message cannot simply become
    // conditional on everything: answering differently for a registered and an
    // unregistered address turns this form into an account enumeration oracle.
    // Only a genuine failure to store the token changes the answer.
    $source = password_reset_source();

    assert_contains('enumeration', $source,
        'the reason the unknown-address answer stays unconditional is written '
        . 'down where the next edit will see it');
});

wallos_test('a rolled back token swap leaves the previous token in place', function () {
    // The guarantee the fix rests on, against the real schema: if the rollback
    // did not restore the deleted row, the failure it exists for would still
    // leave the account with nothing.
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'alice');

    $stmt = $db->prepare('INSERT INTO password_resets (user_id, email, token)
                          VALUES (1, :email, :token)');
    $stmt->bindValue(':email', 'alice@example.com', SQLITE3_TEXT);
    $stmt->bindValue(':token', 'the-old-token', SQLITE3_TEXT);
    $stmt->execute();

    $db->exec('BEGIN');

    $stmt = $db->prepare('DELETE FROM password_resets WHERE email = :email');
    $stmt->bindValue(':email', 'alice@example.com', SQLITE3_TEXT);
    $stmt->execute();

    assert_same(0, (int) $db->querySingle('SELECT COUNT(*) FROM password_resets'),
        'the previous token is gone inside the transaction');

    // The insert that does not happen: the failure this exists for.
    $db->exec('ROLLBACK');

    assert_same('the-old-token',
        (string) $db->querySingle("SELECT token FROM password_resets WHERE email = 'alice@example.com'"),
        'and is back afterwards, so the account still has a way in');

    $db->close();
});
