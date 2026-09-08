<?php
/*
  A replace pair is checked as a pair.

  Several places save a per-user row by deleting the old one and inserting the
  new one. The insert's result decided the answer the user got. The delete's
  result was thrown away.

  Nothing in the schema stops the second row: none of these tables has a unique
  index on user_id. So a delete that fails while the insert succeeds leaves two
  rows for one user, every reader of those tables takes whichever row it is
  handed first, and the endpoint has already answered "saved". The setting the
  user just replaced goes on being served, and there is nothing anywhere to
  suggest why.

  The guard finds the pairs by their shape rather than from a list, so a copy
  written next year is covered on the day it appears. It is deliberately about
  the asymmetry: where the insert's result is read, the delete's has to be read
  too. A pair that reads neither half is a different and larger repair - those
  need the two writes to become one unit of work, not one more if - and this
  guard leaves them alone rather than half-describing them.
*/

/**
 * Every PHP file of the application itself.
 *
 * libs/ is vendored third-party code and tests/ is this suite.
 *
 * @return string[]
 */
function delete_before_replace_files()
{
    $files = [];
    $walk = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(WALLOS_ROOT, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($walk as $file) {
        $path = $file->getPathname();

        if (substr($path, -4) !== '.php') {
            continue;
        }

        $relative = ltrim(substr($path, strlen(WALLOS_ROOT)), '/');

        if (strpos($relative, 'libs/') === 0 || strpos($relative, 'tests/') === 0) {
            continue;
        }

        $files[] = $relative;
    }

    sort($files);

    return $files;
}

/**
 * Whether the value a statement returned is looked at.
 *
 * @param string[] $lines
 * @param int      $index Line holding the execute() call.
 * @return bool
 */
function delete_before_replace_result_is_read($lines, $index)
{
    $line = $lines[$index];

    // A statement of its own, with nothing done to what it returns.
    if (preg_match('/^\s*\$\w+(?:->\w+)*->execute\s*\(\s*\)\s*;\s*$/', $line) === 1) {
        return false;
    }

    // Assigned to a name: that name has to reach a condition, otherwise the
    // assignment is only a longer way of dropping the result.
    if (preg_match('/(\$\w+)\s*=\s*@?\$\w+(?:->\w+)*->execute\s*\(/', $line, $match) === 1) {
        $window = implode("\n", array_slice($lines, $index, 12));
        $name = preg_quote($match[1], '/');

        return preg_match('/\b(?:if|while)\s*\(\s*!?\s*' . $name . '\b/', $window) === 1
            || preg_match('/' . $name . '\s*(?:===|!==|==|!=)/', $window) === 1;
    }

    // if ($stmt->execute()), $stmt->execute() === false, and the like.
    return true;
}

/**
 * The first execute() belonging to the statement that starts at $index.
 *
 * @param string[] $lines
 * @param int      $index
 * @param int      $reach How many lines a bind block may take.
 * @return int|null
 */
function delete_before_replace_execute_line($lines, $index, $reach = 16)
{
    $last = min(count($lines) - 1, $index + $reach);

    for ($i = $index; $i <= $last; $i++) {
        if (strpos($lines[$i], '->execute(') !== false) {
            return $i;
        }
    }

    return null;
}

/**
 * Every delete that a later insert into the same table replaces.
 *
 * @param string $source
 * @return array[] table, delete/insert line numbers and whether each is read
 */
function delete_before_replace_pairs($source)
{
    $lines = preg_split('/\R/', $source);
    $count = count($lines);
    $pairs = [];

    foreach ($lines as $number => $line) {
        if (preg_match('/DELETE\s+FROM\s+["\'`]?(\w+)["\'`]?/i', $line, $match) !== 1) {
            continue;
        }

        $table = $match[1];
        $deleteExecute = delete_before_replace_execute_line($lines, $number);

        if ($deleteExecute === null) {
            continue;
        }

        // The insert that puts the row back. It can be a long way down - in
        // google_search.php a whole API call sits between the two - but a
        // second delete of the same table in between means the insert belongs
        // to that one instead, as on the clearing path in api/fixer.
        $insert = null;
        $quoted = preg_quote($table, '/');
        $pattern = '/INSERT\s+(?:OR\s+\w+\s+)?INTO\s+["\'`]?' . $quoted . '["\'`]?\W/i';

        for ($i = $deleteExecute + 1; $i < $count; $i++) {
            if (preg_match('/DELETE\s+FROM\s+["\'`]?' . $quoted . '["\'`]?\W/i', $lines[$i]) === 1) {
                break;
            }

            if (preg_match($pattern, $lines[$i]) === 1) {
                $insert = $i;
                break;
            }
        }

        if ($insert === null) {
            continue;
        }

        $insertExecute = delete_before_replace_execute_line($lines, $insert);

        if ($insertExecute === null) {
            continue;
        }

        $pairs[] = [
            'table' => $table,
            'delete' => $number + 1,
            'insert' => $insert + 1,
            'deleteIsRead' => delete_before_replace_result_is_read($lines, $deleteExecute),
            'insertIsRead' => delete_before_replace_result_is_read($lines, $insertExecute),
        ];
    }

    return $pairs;
}

wallos_test('a delete is checked wherever the insert that replaces it is', function () {
    $governed = 0;

    foreach (delete_before_replace_files() as $path) {
        foreach (delete_before_replace_pairs(file_get_contents(WALLOS_ROOT . '/' . $path)) as $pair) {
            if (!$pair['insertIsRead']) {
                // Neither half is read. Left alone on purpose: adding a check
                // to the delete alone would not make that file honest, and this
                // guard would then be calling it fixed.
                continue;
            }

            $governed++;

            assert_true($pair['deleteIsRead'],
                $path . ' line ' . $pair['delete'] . ': the delete from ' . $pair['table']
                . ' drops its result, while the insert that replaces the row on line '
                . $pair['insert'] . ' reads its own. A failed delete then answers "saved" '
                . 'over two rows for one user.');
        }
    }

    // A guard that finds nothing passes every assertion above it.
    assert_true($governed >= 6,
        'the tree was swept and the replace pairs were found (' . $governed . ' found)');
});

wallos_test('the second row really does get in, and reading the result is what stops it', function () {
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'theme');

    $save = function ($colour) use ($db) {
        $stmt = $db->prepare('INSERT INTO custom_colors (main_color, accent_color, hover_color, user_id)
                              VALUES (:main, :accent, :hover, 1)');
        $stmt->bindValue(':main', $colour, SQLITE3_TEXT);
        $stmt->bindValue(':accent', '#00ffff', SQLITE3_TEXT);
        $stmt->bindValue(':hover', '#00008b', SQLITE3_TEXT);
        $stmt->execute();
    };

    $rows = function () use ($db) {
        return (int) $db->querySingle('SELECT COUNT(*) FROM custom_colors WHERE user_id = 1');
    };

    $refuseDeletes = function () use ($db) {
        // A delete fails for reasons the endpoint cannot see coming: a database
        // locked by another request, a disk that is full or has gone read-only.
        // SQLite reports all of them the same way, through the statement
        // result, so a trigger that refuses the delete reproduces the case
        // exactly and without waiting for a race.
        $db->exec("CREATE TRIGGER wallos_test_refuse_delete BEFORE DELETE ON custom_colors
                   BEGIN SELECT RAISE(ABORT, 'refused'); END");
    };

    // What the user saved last week.
    $save('#000000');
    $refuseDeletes();

    // The shape these endpoints had. The @ only silences the warning SQLite3
    // prints beside the return value the caller is about to ignore.
    $stmt = $db->prepare('DELETE FROM custom_colors WHERE user_id = 1');
    $dropped = @$stmt->execute();
    $save('#ff0000');

    assert_true($dropped === false, 'the delete did fail, so the rest of this test means something');
    assert_same(2, $rows(),
        'unchecked, a failed delete leaves the replacement beside the row it should have replaced');

    // Which of the two any page gets is decided by nothing: every reader of
    // this table selects by user_id and takes the first row of the result.
    $reader = $db->query('SELECT * FROM custom_colors WHERE user_id = 1');
    assert_true($reader->fetchArray(SQLITE3_ASSOC) !== false,
        'and the reader answers with one of them without being able to say which');

    // Back to one row, then the shape they have now.
    $db->exec('DROP TRIGGER wallos_test_refuse_delete');
    $db->exec('DELETE FROM custom_colors WHERE user_id = 1');
    $save('#000000');
    $refuseDeletes();

    $stmt = $db->prepare('DELETE FROM custom_colors WHERE user_id = 1');

    if (@$stmt->execute() !== false) {
        $save('#ff0000');
    }

    assert_same(1, $rows(),
        'checked, the request stops instead of adding a second row, and the user is told so');

    $db->close();
});
