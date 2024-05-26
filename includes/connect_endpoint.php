<?php

$databaseFile = '../../db/wallos.db';
$db = new SQLite3($databaseFile);
$db->busyTimeout(5000);

if (!$db) {
    die('Connection to the database failed.');
}

require_once 'i18n/languages.php';
require_once 'i18n/getlang.php';
require_once 'i18n/' . $lang . '.php';

session_start();

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $userId = $_SESSION['userId'];
} else {
    $userId = 0;
}

?>