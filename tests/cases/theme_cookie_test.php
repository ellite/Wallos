<?php
/*
  The theme cookies, on their way into an inline script.

  `theme` and `colorTheme` are set by the browser and read straight back out
  into a <script> block. Written as

      window.colorTheme = "<?= $colorTheme ?>";

  a cookie value containing a double quote closes the string and the rest is
  script — reflected XSS against anyone who can put a cookie on the origin.

  5.5.0 fixed this by validating both cookies against a fixed list and encoding
  what is emitted, in login.php, totp.php and includes/header.php.
  registration.php was missed, and it is the one page of the four that renders
  before any account exists.

  This finds the pages by looking for the emission rather than from a list, so
  a fifth one added later is covered without anybody editing this file.
*/

/**
 * Every page that writes a theme value into an inline script.
 *
 * @return array<string, string> path => the emitting line
 */
function theme_cookie_emitters()
{
    $found = [];

    $candidates = glob(WALLOS_ROOT . '/*.php');
    $candidates[] = WALLOS_ROOT . '/includes/header.php';

    foreach ($candidates as $file) {
        foreach (preg_split('/\R/', file_get_contents($file)) as $line) {
            if (preg_match('/window\.(color_?theme|theme)\s*=/i', $line) === 1) {
                $found[str_replace(WALLOS_ROOT . '/', '', $file)] = $line;
            }
        }
    }

    return $found;
}

wallos_test('no page writes a theme cookie into a script unencoded', function () {
    $emitters = theme_cookie_emitters();

    // A guard that finds nothing passes every assertion after it.
    assert_true(count($emitters) >= 4,
        'the emitting pages were found (' . implode(', ', array_keys($emitters)) . ')');

    foreach ($emitters as $path => $line) {
        assert_true(preg_match('/window\.(color_?theme|theme)\s*=\s*"/i', $line) !== 1,
            $path . ' does not interpolate a theme value into a quoted string: '
            . trim($line));
        assert_contains('json_encode(', $line,
            $path . ' encodes the value it emits');
    }
});

wallos_test('the value emitted was validated against a fixed list first', function () {
    // The encoding stops the injection on its own. This stops the value
    // reaching the stylesheet ids and the theme-colour meta tag as something
    // that is not one of the themes, and it is what makes the encoded output
    // predictable rather than merely safe.
    $sanitizers = [
        "\$_COOKIE['theme']" => 'sanitize_theme_mode',
        "\$_COOKIE['colorTheme']" => 'sanitize_color_theme',
        "\$_COOKIE['inUseTheme']" => 'sanitize_resolved_theme',
    ];

    $checked = 0;

    foreach (array_keys(theme_cookie_emitters()) as $path) {
        $source = file_get_contents(WALLOS_ROOT . '/' . $path);

        foreach ($sanitizers as $cookie => $sanitizer) {
            if (strpos($source, $cookie) === false) {
                continue;
            }

            $checked++;
            assert_contains($sanitizer . '(' . $cookie . ')', $source,
                $path . ' validates ' . $cookie . ' before using it');
        }
    }

    assert_true($checked >= 6,
        'the cookie reads were found rather than the search quietly matching '
        . 'nothing (' . $checked . ' found)');
});
