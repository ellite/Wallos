<?php

session_start();

$userId = 0;
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $userId = $_SESSION['userId'];
}

if (php_sapi_name() !== 'cli') {
    if ($userId !== 1) {
        die("Unauthorized");
    }
}

?>