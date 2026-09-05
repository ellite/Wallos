<?php
/*
  One answer shape for getLogoFromUrl(), and a caller that reads it.

  Four files carry their own copy of this helper. The two subscription copies
  answered ['success' => bool, 'filename'|'message'] and were checked at the
  call site. The two payment copies answered a bare filename on success, an
  array on some failures and an empty string on another — and no caller looked.
  The array was bound as the icon column, the insert failed, and the endpoint
  answered with text the page cannot parse, so the user saw "Unknown error,
  please try again" with nothing in the log (#1185).

  A structural guard rather than a behavioural one: the helper needs a network
  and an image library to run, and the defect was never in the fetching. It was
  in the shape of the answer and in nobody reading it. That is visible in the
  source, and it is what a fifth copy would get wrong too.
*/

/**
 * The files that carry a copy of the helper.
 *
 * @return string[]
 */
function logo_fetch_files()
{
    return [
        'endpoints/payments/add.php',
        'endpoints/subscription/add.php',
        'api/payment_methods/set_payment_methods.php',
        'api/subscriptions/set_subscriptions.php',
    ];
}

/**
 * The body of getLogoFromUrl() alone.
 *
 * Scoped deliberately: these files also hold resizeAndUploadLogo(), which
 * returns a bare filename and is right to — it is not the function whose
 * answer nobody could read. A file-wide search flagged all four copies,
 * including the two that were already correct.
 *
 * @param string $source
 * @return string
 */
function logo_fetch_helper_body($source)
{
    $start = strpos($source, 'function getLogoFromUrl(');

    if ($start === false) {
        return '';
    }

    // Up to the next brace in the first column, which closes the function:
    // everything inside it is indented.
    $end = strpos($source, "\n}", $start);

    return $end === false ? substr($source, $start) : substr($source, $start, $end - $start);
}

wallos_test('every copy of the logo fetch answers in the same shape', function () {
    $checked = 0;

    foreach (logo_fetch_files() as $path) {
        $source = file_get_contents(WALLOS_ROOT . '/' . $path);
        $body = logo_fetch_helper_body($source);

        assert_true($body !== '', $path . ' carries a copy of the helper');
        $checked++;

        // Success says so, and names the file it saved under.
        assert_true(preg_match('/["\']success["\']\s*=>\s*true/', $body) === 1,
            $path . ' answers success as ["success" => true, "filename" => ...]');

        // The two shapes that cannot be told apart by the caller.
        assert_true(preg_match('/return\s+\$fileName\s*;/', $body) !== 1,
            $path . ' does not return a bare filename, which a caller cannot '
            . 'distinguish from a failure array');
        assert_true(preg_match('/return\s+["\']{2}\s*;/', $body) !== 1,
            $path . ' does not return an empty string, which a caller reads as '
            . 'a logo it successfully did not fetch');
    }

    // A guard that finds nothing passes every assertion above it.
    assert_same(4, $checked, 'all four copies were found and checked');
});

wallos_test('every caller reads the answer before using it', function () {
    $callSites = 0;

    foreach (logo_fetch_files() as $path) {
        $source = file_get_contents(WALLOS_ROOT . '/' . $path);
        $lines = preg_split('/\R/', $source);

        foreach ($lines as $number => $line) {
            // The definition is not a call site.
            if (strpos($line, 'function getLogoFromUrl(') !== false) {
                continue;
            }

            if (preg_match('/(\$[A-Za-z_][A-Za-z0-9_]*)\s*=\s*getLogoFromUrl\s*\(/', $line, $match) !== 1) {
                continue;
            }

            $callSites++;
            $variable = $match[1];

            // The check has to come before the value is used, and the value is
            // used within a few lines everywhere this appears.
            $window = implode("\n", array_slice($lines, $number, 14));
            $quoted = preg_quote($variable, '/');

            assert_true(preg_match('/' . $quoted . '\s*\[\s*["\']success["\']\s*\]/', $window) === 1,
                $path . ' line ' . ($number + 1) . ': ' . $variable
                . " is checked for ['success'] before it is used");
        }
    }

    assert_same(6, $callSites, 'all six call sites were found and checked');
});
