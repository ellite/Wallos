<?php

$databaseFile = __DIR__ . '/../db/wallos.db';
$db = new SQLite3($databaseFile);
$db->busyTimeout(5000);

if (!$db) {
    die('Connection to the database failed.');
}

require_once __DIR__ . '/../includes/i18n/languages.php';
require_once __DIR__ . '/../includes/i18n/getlang.php';
require_once __DIR__ . '/../includes/i18n/' . $lang . '.php';

?>