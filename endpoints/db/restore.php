<?php
require_once '../../includes/connect_endpoint.php';
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die(json_encode([
        "success" => false,
        "message" => translate('session_expired', $i18n)
    ]));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['file'])) {
        $file = $_FILES['file'];
        $fileTmpName = $file['tmp_name'];
        $fileError = $file['error'];

        if ($fileError === 0) {
            // Handle the uploaded file here
            // The uploaded file will be stored as restore.zip
            $fileDestination = '../../.tmp/restore.zip';
            move_uploaded_file($fileTmpName, $fileDestination);

            // Unzip the uploaded file
            $zip = new ZipArchive();
            if ($zip->open($fileDestination) === true) {
                $zip->extractTo('../../.tmp/restore/');
                $zip->close();
            }

            // Check if wallos.db file exists in the restore folder
            if (file_exists('../../.tmp/restore/wallos.db')) {
                // Replace the wallos.db file in the db directory with the wallos.db file in the restore directory
                if (file_exists('../../db/wallos.db')) {
                    unlink('../../db/wallos.db');
                }
                rename('../../.tmp/restore/wallos.db', '../../db/wallos.db');

                // Check if restore/logos/ directory exists
                if (file_exists('../../.tmp/restore/logos/')) {
                    // Delete the files and folders in the uploaded logos directory
                    $dir = '../../images/uploads/logos/';

                    // Create recursive directory iterator
                    $di = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);

                    // Create recursive iterator iterator in Child First Order
                    $ri = new RecursiveIteratorIterator($di, RecursiveIteratorIterator::CHILD_FIRST);

                    // For each item in the recursive iterator
                    foreach ( $ri as $file ) {
                        // If the item is a directory
                        if ( $file->isDir() ) {
                            // Remove the directory
                            rmdir($file->getPathname());
                        } else {
                            // If the item is a file
                            // Remove the file
                            unlink($file->getPathname());
                        }
                    }

                    // Copy the contents of restore/logos/ directory to the ../../images/uploads/logos/ directory
                    $dir = new RecursiveDirectoryIterator('../../.tmp/restore/logos/');
                    $ite = new RecursiveIteratorIterator($dir);
                    $allowedExtensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

                    foreach ($ite as $filePath) {
                        if (in_array(pathinfo($filePath, PATHINFO_EXTENSION), $allowedExtensions)) {
                            $destination = str_replace('../../.tmp/restore/', '../../images/uploads/', $filePath);
                            $destinationDir = pathinfo($destination, PATHINFO_DIRNAME);

                            if (!is_dir($destinationDir)) {
                                mkdir($destinationDir, 0755, true);
                            }

                            copy($filePath, $destination);
                        }
                    }
                }
                
                echo json_encode([
                    "success" => true,
                    "message" => "File uploaded and wallos.db exists"
                ]);
            } else {
                die(json_encode([
                    "success" => false,
                    "message" => "wallos.db does not exist in the backup file"
                ]));
            }


        } else {
            echo json_encode([
                "success" => false,
                "message" => "Failed to upload file"
            ]);
        }
    } else {
        echo json_encode([
            "success" => false,
            "message" => "No file uploaded"
        ]);
    }
} else {
    echo json_encode([
        "success" => false,
        "message" => "Invalid request method"
    ]);
}
?>