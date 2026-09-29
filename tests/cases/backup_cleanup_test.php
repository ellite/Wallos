<?php
/*
  The backup archive is removed even when the download is not finished.

  endpoints/db/backup.php builds a zip of the database and every uploaded file
  in the system temp directory, streams it to the admin who asked, and deletes
  it on the line after readfile(). A download the browser abandons - a closed
  tab, a lost connection, a proxy timing out - ends the script inside
  readfile(), so that line never runs and a complete copy of the installation
  stays behind until something else clears it.

  The archive is not web-accessible, which is what the comment at the top of
  that file is about. This is the smaller version of the same thing: a file
  that should have lived for the length of one request and instead lives until
  something else clears the temp directory.

  These cases pin the shape rather than the behaviour. Proving the removal for
  a download that is abandoned would need an HTTP stream cut in the middle,
  which this harness has no way to do.
*/

wallos_test('the archive is removed on every path out of the request', function () {
    $source = file_get_contents(WALLOS_ROOT . '/endpoints/db/backup.php');

    assert_contains('register_shutdown_function', $source,
        'the removal is registered rather than written after readfile()');

    // The point of the change: nothing relies on reaching the line after the
    // stream any more.
    assert_true(
        strpos($source, 'register_shutdown_function') < strpos($source, 'readfile($zipname);'),
        'and it is registered before the stream starts, not after it'
    );
    assert_not_contains("readfile(\$zipname);\nunlink(\$zipname);", $source,
        'the unlink that only ran on a completed download is gone');
});

wallos_test('a failure before the stream still removes the archive itself', function () {
    // The branches that give up before streaming keep their own unlink and
    // their own message; the shutdown function is a second answer for the path
    // that has none, not a replacement for the ones that do.
    $source = file_get_contents(WALLOS_ROOT . '/endpoints/db/backup.php');

    assert_same(2, substr_count($source, '@unlink($zipname);
    die(json_encode(['),
        'both give-up branches still clean up and report');
});
