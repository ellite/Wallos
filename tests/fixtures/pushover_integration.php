<?php

/** The production cron resolves its database beside its source tree. */
function pushover_cron_copy_directory(string $source, string $destination): void
{
    mkdir($destination, 0700, true);
    foreach (new DirectoryIterator($source) as $entry) {
        if ($entry->isDot()) {
            continue;
        }
        $target = $destination . '/' . $entry->getFilename();
        if ($entry->isDir()) {
            pushover_cron_copy_directory($entry->getPathname(), $target);
        } else {
            copy($entry->getPathname(), $target);
        }
    }
}

function pushover_cron_remove_directory(string $directory): void
{
    foreach (new DirectoryIterator($directory) as $entry) {
        if ($entry->isDot()) {
            continue;
        }
        if ($entry->isDir()) {
            pushover_cron_remove_directory($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($directory);
}

function pushover_integration_run(string $scratch, string $script, array $extraEnvironment = [], bool $endpoint = false): array
{
    $capture = $scratch . '/captured-' . uniqid('', true) . '.jsonl';
    $environment = array_merge(getenv(), [
        'TZ' => 'UTC', 'WALLOS_SERVER_URL' => '', 'WALLOS_TEST_PUSHOVER_CAPTURE' => $capture,
    ], $extraEnvironment);
    $process = proc_open([
        PHP_BINARY,
        '-d', 'disable_functions=curl_init,curl_setopt_array,curl_exec,curl_getinfo,curl_close,curl_errno' . ($endpoint ? ',file_get_contents' : ''),
        '-d', 'auto_prepend_file=' . WALLOS_ROOT . '/tests/fixtures/' . ($endpoint ? 'pushover_endpoint.php' : 'pushover_curl.php'),
        '-d', 'display_errors=stderr',
        $scratch . '/' . $script,
    ], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes,
        dirname($scratch . '/' . $script), $environment);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not launch isolated Pushover process');
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    $deliveries = is_file($capture)
        ? array_map(fn($line) => json_decode($line, true), file($capture, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))
        : [];
    return compact('exit', 'stdout', 'stderr', 'deliveries');
}
