<?php

// Adds pushover_token to the admin row: one Pushover application for the whole
// instance, the way the SMTP transport is already one mail server for the
// whole instance.
//
// A Pushover application token names the application a message is sent from,
// not the person it reaches. The user key is what names the person, and it
// stays on each user's own row. Before this, every member of a household had
// to register an application of their own at pushover.net and paste its token
// into Wallos, so notifications arrived from as many applications as there are
// people in the house.
//
// Empty means what it has always meant: no instance application, and a user's
// own token is the only one there is.

$column = $db->query("SELECT * FROM pragma_table_info('admin') WHERE name='pushover_token'");

if ($column->fetchArray(SQLITE3_ASSOC) === false) {
    $db->exec("ALTER TABLE admin ADD COLUMN pushover_token TEXT DEFAULT ''");
}

$db->exec("UPDATE admin SET pushover_token = '' WHERE pushover_token IS NULL");

?>
