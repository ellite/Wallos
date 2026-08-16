<?php
/*
  Test runner.

      php tests/run.php              run every case
      php tests/run.php currency     run cases whose file or name matches "currency"

  Exits non-zero when a case fails, so it can be used in CI.
*/

require_once __DIR__ . '/bootstrap.php';

$filter = $argv[1] ?? null;

if (is_dir(WALLOS_TEST_TMP)) {
    foreach (glob(WALLOS_TEST_TMP . '/case-*.db') as $leftover) {
        @unlink($leftover);
    }
} else {
    mkdir(WALLOS_TEST_TMP, 0700, true);
}

$files = glob(__DIR__ . '/cases/*_test.php');
sort($files);

foreach ($files as $file) {
    if ($filter !== null && stripos(basename($file), $filter) === false) {
        // Keep the file when one of its case names matches instead.
        $contents = file_get_contents($file);
        if (stripos($contents, $filter) === false) {
            continue;
        }
    }

    require_once $file;
}

$started = microtime(true);
$passed = 0;
$failedTests = [];

foreach ($GLOBALS['wallos_tests'] as $test) {
    if ($filter !== null
        && stripos($test['name'], $filter) === false
        && stripos(basename((new ReflectionFunction($test['body']))->getFileName()), $filter) === false) {
        continue;
    }

    $GLOBALS['wallos_test_current'] = $test['name'];
    $before = count($GLOBALS['wallos_test_failures']);

    try {
        $test['body']();
    } catch (Throwable $error) {
        wallos_test_fail('threw ' . get_class($error) . ': ' . $error->getMessage()
            . ' @ ' . basename($error->getFile()) . ':' . $error->getLine());
    }

    $newFailures = array_slice($GLOBALS['wallos_test_failures'], $before);

    if ($newFailures === []) {
        $passed++;
        echo "\033[32m  ok\033[0m  " . $test['name'] . "\n";

        continue;
    }

    $failedTests[] = $test['name'];
    echo "\033[31mFAIL\033[0m  " . $test['name'] . "\n";
    foreach ($newFailures as $failure) {
        echo "        " . $failure['message'] . "\n";
    }
}

foreach (glob(WALLOS_TEST_TMP . '/case-*.db') as $leftover) {
    @unlink($leftover);
}

$duration = round((microtime(true) - $started) * 1000);
$failures = count($GLOBALS['wallos_test_failures']);

echo "\n";
echo $failures === 0
    ? sprintf("\033[32m%d tests passed\033[0m (%d assertions, %dms)\n", $passed, $GLOBALS['wallos_test_assertions'], $duration)
    : sprintf("\033[31m%d failing assertion(s)\033[0m in %d test(s), %d passed (%dms)\n",
        $failures, count($failedTests), $passed, $duration);

exit($failures === 0 ? 0 : 1);
