<?php
require_once '../../includes/connect_endpoint.php';

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
            $fileDestination = '../../.tmp/restore.zip';
            move_uploaded_file($fileTmpName, $fileDestination);

            $zip = new ZipArchive();
            if ($zip->open($fileDestination) === true) {
                $zip->extractTo('../../.tmp/restore/');
                $zip->close();
            } else {
                die(json_encode([
                    "success" => false,
                    "message" => "Failed to extract the uploaded file"
                ]));
            }

            if (file_exists('../../.tmp/restore/wallos.db')) {
                if (file_exists('../../db/wallos.db')) {
                    unlink('../../db/wallos.db');
                }
                rename('../../.tmp/restore/wallos.db', '../../db/wallos.db');

                if (file_exists('../../.tmp/restore/logos/')) {
                    $dir = '../../images/uploads/logos/';
                    $di = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);
                    $ri = new RecursiveIteratorIterator($di, RecursiveIteratorIterator::CHILD_FIRST);

                    foreach ($ri as $file) {
                        if ($file->isDir()) {
                            rmdir($file->getPathname());
                        } else {
                            unlink($file->getPathname());
                        }
                    }

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

                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator('../../.tmp', RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );

                foreach ($files as $fileinfo) {
                    $removeFunction = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                    $removeFunction($fileinfo->getRealPath());
                }

                echo json_encode([
                    "success" => true,
                    "message" => translate("success", $i18n)
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