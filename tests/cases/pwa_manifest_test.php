<?php
/*
  manifest.json used to hardcode theme_color/background_color to white,
  which an installed PWA (Android WebAPK in particular) uses for the status
  bar/splash screen independent of the page's own live theme - dark-theme
  users always got a white header there. manifest.php replaces the static
  file so those colors track the user's actual theme, resolved from the
  same "theme"/"inUseTheme" cookies the pre-auth pages already use (no
  login is required to read this file).
*/

/**
 * Runs manifest.php with the given cookies and returns the decoded output.
 *
 * @param array $cookies
 * @return array
 */
function render_manifest($cookies)
{
    $previousCookies = $_COOKIE;
    $_COOKIE = $cookies;

    ob_start();
    include WALLOS_ROOT . '/manifest.php';
    $output = ob_get_clean();

    $_COOKIE = $previousCookies;

    return json_decode($output, true);
}

wallos_test('the manifest is valid JSON with the expected content type', function () {
    ob_start();
    include WALLOS_ROOT . '/manifest.php';
    $output = ob_get_clean();

    $decoded = json_decode($output, true);
    assert_true($decoded !== null, 'manifest.php produces valid JSON');
    assert_same('Wallos', $decoded['short_name'] ?? null, 'the manifest content is otherwise unchanged');
});

wallos_test('theme_color and background_color track the theme cookie', function () {
    $light = render_manifest(['theme' => 'light']);
    $dark = render_manifest(['theme' => 'dark']);

    assert_same('#FFFFFF', $light['theme_color'], 'light theme is white');
    assert_same('#FFFFFF', $light['background_color'], 'and the background matches it');
    assert_same('#12151C', $dark['theme_color'], 'dark theme is dark, matching the header meta tag value');
    assert_same('#12151C', $dark['background_color'], 'and the background matches it');
});

wallos_test('automatic mode resolves from the inUseTheme cookie, not just "not light"', function () {
    $resolvedDark = render_manifest(['theme' => 'automatic', 'inUseTheme' => 'dark']);
    $resolvedLight = render_manifest(['theme' => 'automatic', 'inUseTheme' => 'light']);

    assert_same('#12151C', $resolvedDark['theme_color'], 'automatic + resolved dark is dark');
    assert_same('#FFFFFF', $resolvedLight['theme_color'], 'automatic + resolved light is light');
});

wallos_test('missing or invalid cookies default to light, same as the pre-auth pages', function () {
    $noCookie = render_manifest([]);
    $garbage = render_manifest(['theme' => '<script>alert(1)</script>']);

    assert_same('#FFFFFF', $noCookie['theme_color'], 'no cookie at all defaults to light');
    assert_same('#FFFFFF', $garbage['theme_color'], 'an invalid theme value is not trusted, falls back to light');
});

wallos_test('every shortcut icon declares its type, and the shortcut/icon set is unchanged', function () {
    $manifest = render_manifest(['theme' => 'light']);

    assert_same(6, count($manifest['shortcuts']), 'all six dashboard shortcuts are still present');
    foreach ($manifest['shortcuts'] as $shortcut) {
        foreach ($shortcut['icons'] as $icon) {
            assert_same('image/png', $icon['type'] ?? null, $shortcut['name'] . ' shortcut icon declares its type');
        }
    }
});

wallos_test('every page linking the manifest points at manifest.php, not the old static file', function () {
    $files = [
        'includes/header.php',
        'login.php',
        'registration.php',
        'passwordreset.php',
        'verifyemail.php',
        'totp.php',
    ];

    foreach ($files as $file) {
        $source = file_get_contents(WALLOS_ROOT . '/' . $file);
        assert_contains('href="manifest.php"', $source, $file . ' links the dynamic manifest');
        assert_not_contains('href="manifest.json"', $source, $file . ' does not still link the removed static file');
    }
});

wallos_test('the service worker does not cache-first the manifest forever', function () {
    $source = file_get_contents(WALLOS_ROOT . '/service-worker.js');

    assert_not_contains("'manifest.json'", $source,
        'the old static manifest is not precached (it no longer exists)');
    assert_not_contains("'manifest.php'", $source,
        'the dynamic, per-user manifest is not cache-first precached either - it must stay network-first so a theme change is reflected');
});

wallos_test('the service worker matches versioned static assets when offline', function () {
    $source = file_get_contents(WALLOS_ROOT . '/service-worker.js');

    // The pages request assets as "styles/styles.css?<version>" but they are
    // precached under the bare path, so the static-asset lookup must ignore
    // the query string or every asset misses the cache offline.
    $staticBranch = substr($source, strpos($source, 'Static assets: cache-first'));
    $staticBranch = substr($staticBranch, 0, strpos($staticBranch, 'PHP pages and everything else'));

    assert_contains('caches.match(request, { ignoreSearch: true })', $staticBranch,
        'the static-asset lookup ignores the "?<version>" query string');
    assert_contains('cache.put(request, clone)', $staticBranch,
        'a cache miss is backfilled, so an asset the install step dropped (e.g. apexcharts) still ends up cached');
});

wallos_test('the service worker installs static assets in batches, not all at once', function () {
    $source = file_get_contents(WALLOS_ROOT . '/service-worker.js');

    assert_contains('BATCH_SIZE', $source,
        'the install step chunks its fetches so large files are not starved of connections');
});
