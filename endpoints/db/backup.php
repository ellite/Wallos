<?php
require_once '../../includes/connect_endpoint.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die(json_encode([
        "success" => false,
        "message" => translate('session_expired', $i18n)
    ]));
}

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

$zip = new ZipArchive();
$filename = "backup_" . uniqid() . ".zip";
$zipname = "../../.tmp/" . $filename;

if ($zip->open($zipname, ZipArchive::CREATE) !== TRUE) {
    die(json_encode([
        "success" => false,
        "message" => translate('cannot_open_zip', $i18n)
    ]));
}

addFolderToZip('../../db/', $zip);
addFolderToZip('../../images/uploads/', $zip);

$numberOfFilesAdded = $zip->numFiles;

if ($zip->close() === false) {
    die(json_encode([
        "success" => false,
        "message" => "Failed to finalize the zip file"
    ]));
} else {
    flush();
    die(json_encode([
        "success" => true,
        "message" => "Zip file created successfully",
        "numFiles" => $numberOfFilesAdded,
        "file" => $filename
    ]));
}


?>