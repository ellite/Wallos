<?php
/*
  ORDER BY names a column, not a string.

  `ORDER BY 'order'` sorts by a constant. Every row gets the same sort key, so
  SQLite returns them in whatever order it happens to have them and the list
  looks unsorted for no visible reason — the statement is valid, nothing warns,
  and the column the user drags into place is never consulted.

  The same query is written four other times in this codebase, all four with
  the column quoted as an identifier. This checks that they stay that way, and
  that a fifth is not written the other way.

  The rule is general on purpose: `ORDER BY '<anything>'` is a constant, and
  sorting by a constant is never what anybody means.
*/

wallos_test('no query sorts by a string constant', function () {
    $offenders = [];
    $checked = 0;

    $directory = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(WALLOS_ROOT, RecursiveDirectoryIterator::SKIP_DOTS));

    foreach ($directory as $file) {
        $path = str_replace(WALLOS_ROOT . '/', '', $file->getPathname());

        if ($file->getExtension() !== 'php'
            || strpos($path, 'libs/') === 0
            || strpos($path, 'tests/') === 0) {
            continue;
        }

        foreach (preg_split('/\R/', file_get_contents($file->getPathname())) as $number => $line) {
            if (stripos($line, 'ORDER BY') === false) {
                continue;
            }

            $checked++;

            // A single-quoted token straight after ORDER BY is a constant. An
            // identifier is bare, backticked or double-quoted.
            if (preg_match("/ORDER\s+BY\s+'/i", $line) === 1) {
                $offenders[] = $path . ':' . ($number + 1) . ' — ' . trim($line);
            }
        }
    }

    // A guard that finds nothing passes its own assertion.
    assert_true($checked >= 5,
        'the ORDER BY clauses were found (' . $checked . ' lines)');

    assert_same([], $offenders,
        'no ORDER BY sorts by a constant: ' . implode(' | ', $offenders));
});
