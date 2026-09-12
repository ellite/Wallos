<?php
/*
  A precached asset that changes without a STATIC_CACHE bump never reaches
  anyone who already has the service worker installed.

  The static handler matches with { ignoreSearch: true }, so the "?<version>"
  query the pages append to every script and stylesheet is discarded before the
  lookup. The cached copy wins forever; the only thing that evicts it is a new
  cache name, because activate deletes every bucket not in validCaches.

  That failure is silent and total: the file on disk is correct, the server
  serves it correctly, and the browser still runs last release's copy. It cost
  a shipped calendar feature once already.

  So: change anything in staticAssets and this test fails until STATIC_CACHE is
  bumped and the fingerprint below is updated. Both, together, in the same
  commit.
*/

const SERVICE_WORKER_ASSET_FINGERPRINT = 'c8ee88115fb5c405';

/**
 * @return string[] The asset paths the service worker precaches.
 */
function service_worker_static_assets()
{
    $source = file_get_contents(WALLOS_ROOT . '/service-worker.js');

    if (preg_match('/const staticAssets = \[(.*?)\];/s', $source, $block) !== 1) {
        return [];
    }

    preg_match_all("/'([^']+)'/", $block[1], $matches);

    return $matches[1];
}

wallos_test('the precached assets have not changed without a cache bump', function () {
    $assets = service_worker_static_assets();

    assert_true(count($assets) > 50,
        'the staticAssets list was found and parsed (' . count($assets) . ' entries)');

    $missing = [];
    $parts = [];
    foreach ($assets as $asset) {
        $path = WALLOS_ROOT . '/' . $asset;
        if (!is_file($path)) {
            $missing[] = $asset;
            $parts[] = $asset . ':missing';
            continue;
        }
        $parts[] = $asset . ':' . hash_file('sha256', $path);
    }

    assert_same([], $missing,
        'every precached asset exists: ' . implode(', ', $missing));

    $fingerprint = substr(hash('sha256', implode('', $parts)), 0, 16);

    assert_same(SERVICE_WORKER_ASSET_FINGERPRINT, $fingerprint,
        'a precached asset changed. Bump STATIC_CACHE in service-worker.js, then set '
        . 'SERVICE_WORKER_ASSET_FINGERPRINT in this file to ' . $fingerprint
        . '. Without the bump the change never reaches an installed client');
});

wallos_test('a bumped static cache actually evicts the previous one', function () {
    $source = file_get_contents(WALLOS_ROOT . '/service-worker.js');

    // The eviction depends on activate deleting every cache not named here, so
    // a bump is only effective while STATIC_CACHE is part of that list.
    assert_contains('const validCaches = [STATIC_CACHE, PAGES_CACHE, LOGOS_CACHE]', $source,
        'activate cleans up by comparing against the current cache names');
    assert_contains('caches.delete(key)', $source,
        'caches outside that list are deleted');

    // If this ever stops being true the "?<version>" buster would work on its
    // own and the fingerprint guard above could be dropped.
    assert_contains('ignoreSearch: true', $source,
        'static assets still match regardless of the version query string');
});
