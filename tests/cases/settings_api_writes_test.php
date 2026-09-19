<?php
/*
  The settings API answers for the writes it made, not for the ones it attempted.

  `api/settings/set_settings.php` contradicted itself. The settings UPDATE near
  the end reads its own result and answers "Database error" when it fails. The
  four writes above it — the delete and insert that replace the custom css, and
  the pair that replace the custom colours — discarded theirs, and the file
  finished with `success: true` either way.

  The consequence is not a lost setting. It is two rows where there should be
  one: a delete that fails while the insert succeeds leaves both, and every
  reader of these tables selects by user_id and takes the first row it is
  handed. Which of the two that is, is decided by nothing. So the page can go on
  serving the css or the theme this request was meant to replace, after the API
  has said it was saved.

  The delete-before-replace gate does not catch this one, and says why in its
  own comment: it governs a pair only when the insert is read, because adding a
  check to the delete alone would not make the file honest. Reading both is what
  brings this pair under that gate — after this change the pair is governed, and
  a future edit that drops either check fails there too.
*/

/**
 * @return string
 */
function settings_api_source()
{
    return file_get_contents(WALLOS_ROOT . '/api/settings/set_settings.php');
}

wallos_test('no write in the settings API is discarded', function () {
    // The whole file rather than the four lines that had it: a fifth write
    // added later is caught by the same rule, without anybody remembering to
    // come back here.
    $lines = preg_split('/\R/', settings_api_source());
    $discarded = [];

    foreach ($lines as $number => $line) {
        // A statement of its own, with nothing done to what it returns.
        if (preg_match('/^\s*\$\w+->execute\(\)\s*;\s*$/', $line) === 1) {
            $discarded[] = $number + 1;
        }
    }

    assert_same([], $discarded,
        'every execute() in api/settings/set_settings.php is read (lines with a '
        . 'discarded one: ' . implode(', ', $discarded) . ')');
});

wallos_test('a failure in either replacement is answered, not reported as saved', function () {
    // The file already knew how to say this; it simply did not say it here.
    $source = settings_api_source();

    assert_true(substr_count($source, "'title' => 'Database error'") >= 3,
        'the custom css, the custom colours and the settings update all answer '
        . 'the same way when a write fails');

    $success = strpos($source, "'title' => 'Settings updated'");
    $cssGuard = strpos($source, '$cssStored');
    $colorGuard = strpos($source, '$colorsStored');

    assert_true($cssGuard !== false && $cssGuard < $success,
        'the css result is known before the success answer is written');
    assert_true($colorGuard !== false && $colorGuard < $success,
        'and so is the colour result');
});

wallos_test('the second row really does get in, and reading the result is what stops it', function () {
    // Not a demonstration of the file — a demonstration of what its previous
    // shape allowed, against the real schema. A trigger refuses the delete,
    // which is how a locked database or a read-only disk looks from here:
    // SQLite reports all of them through the statement result, and through
    // nothing else.
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'alice');

    $save = function ($css) use ($db) {
        $stmt = $db->prepare('INSERT INTO custom_css_style (css, user_id) VALUES (:css, 1)');
        $stmt->bindValue(':css', $css, SQLITE3_TEXT);
        $stmt->execute();
    };

    $rows = function () use ($db) {
        return (int) $db->querySingle('SELECT COUNT(*) FROM custom_css_style WHERE user_id = 1');
    };

    $refuseDeletes = function () use ($db) {
        $db->query("CREATE TRIGGER settings_api_refuse_delete BEFORE DELETE ON custom_css_style
                    BEGIN SELECT RAISE(ABORT, 'refused'); END");
    };

    $save('body { color: black }');
    $refuseDeletes();

    // The shape the file had. The @ only silences the warning SQLite prints
    // beside the return value the caller is about to ignore.
    $stmt = $db->prepare('DELETE FROM custom_css_style WHERE user_id = 1');
    $dropped = @$stmt->execute();
    $save('body { color: red }');

    assert_true($dropped === false, 'the delete did fail, so the rest of this means something');
    assert_same(2, $rows(),
        'unchecked, a failed delete leaves the replacement beside the row it should have replaced');

    // Which of the two any page gets is decided by nothing: every reader of
    // this table selects by user_id and takes the first row of the result.
    $reader = $db->query('SELECT css FROM custom_css_style WHERE user_id = 1');
    assert_true($reader->fetchArray(SQLITE3_ASSOC) !== false,
        'and a reader answers with one of them without being able to say which');

    // Back to one row, then the shape the file has now.
    $db->query('DROP TRIGGER settings_api_refuse_delete');
    $db->query('DELETE FROM custom_css_style WHERE user_id = 1');
    $save('body { color: black }');
    $refuseDeletes();

    $stmt = $db->prepare('DELETE FROM custom_css_style WHERE user_id = 1');

    if (@$stmt->execute() !== false) {
        $save('body { color: red }');
    }

    assert_same(1, $rows(),
        'read, the request stops instead of adding a second row, and the caller is told');

    $db->close();
});
