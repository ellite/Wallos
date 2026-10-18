<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint_admin.php';

function addFolderToZip($dir, $zipArchive, $zipdir = '')
{
    if (is_dir($dir)) {
        if ($dh = opendir($dir)) {
            //Add the directory
            if (!empty($zipdir))
                $zipArchive->addEmptyDir($zipdir);
            while (($file = readdir($dh)) !== false) {
                // Skip '.' and '..'
                if ($file == "." || $file == "..") {
                    continue;
                }
                //If it's a folder, run the function again!
                if (is_dir($dir . $file)) {
                    $newdir = $dir . $file . '/';
                    addFolderToZip($newdir, $zipArchive, $zipdir . $file . '/');
                } else {
                    //Add the files
                    $zipArchive->addFile($dir . $file, $zipdir . $file);
                }
            }
        }
    } else {
        die(json_encode([
            "success" => false,
            "message" => "Directory does not exist: $dir"
        ]));
    }
}

// Build the archive OUTSIDE the web root. Previously it was written to
// ../../.tmp/ with a uniqid()-based name and served statically by nginx, which
// let anyone who could guess the (timestamp-derived, low-entropy) filename
// download the full database unauthenticated. The backup is now streamed
// directly to the authenticated admin below and never persists in a
// web-accessible location.
$zipname = tempnam(sys_get_temp_dir(), 'wallos_backup_');
if ($zipname === false) {
    die(json_encode([
        "success" => false,
        "message" => translate('cannot_open_zip', $i18n)
    ]));
}

$zip = new ZipArchive();
if ($zip->open($zipname, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    @unlink($zipname);
    die(json_encode([
        "success" => false,
        "message" => translate('cannot_open_zip', $i18n)
    ]));
}

addFolderToZip('../../db/', $zip);
addFolderToZip('../../images/uploads/', $zip);

if ($zip->close() === false) {
    @unlink($zipname);
    die(json_encode([
        "success" => false,
        "message" => "Failed to finalize the zip file"
    ]));
}

// Discard any buffered output (e.g. a stray newline from an included file)
// so it cannot corrupt the binary archive that follows.
while (ob_get_level() > 0) {
    ob_end_clean();
}

// ZipArchive wrote through its own handle after tempnam() created the file at
// 0 bytes, so clear the stat cache before reading its size for Content-Length.
clearstatcache(true, $zipname);

$downloadName = 'Wallos-Backup-' . date('Ymd-His') . '.zip';
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($zipname));
header('Cache-Control: no-store');

// Removed on the way out rather than after readfile(), because a download the
// browser abandons - a closed tab, a lost connection, a proxy timing out - ends
// this script inside readfile(). The unlink below it then never runs, and a
// complete copy of the database and every uploaded file stays in the system
// temp directory until something else clears it. The shutdown function runs on
// that path too.
//
// Registered here rather than at tempnam() so the failure branches above keep
// their own unlink and their own message.
register_shutdown_function(function () use ($zipname) {
    if (is_file($zipname)) {
        @unlink($zipname);
    }
});

// An abandoned download does not need the rest of the file read into memory
// first; ignore_user_abort stays off, so PHP stops at the next write.
readfile($zipname);
exit;