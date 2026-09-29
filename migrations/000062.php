<?php

// Adds gotify_server to the admin row: one Gotify server for the whole
// instance, the way the SMTP transport is already one mail server for the
// whole instance.
//
// Only the address is shared. A Gotify application token is personal - it
// names the application a message arrives under, and each member of a
// household keeps their own, so their notifications stay theirs. What nobody
// should have to look up twice is where the server is.
//
// Empty means what it has always meant: no instance server, and a user's own
// address is the only one there is.

$column = $db->query("SELECT * FROM pragma_table_info('admin') WHERE name='gotify_server'");

if ($column->fetchArray(SQLITE3_ASSOC) === false) {
    $db->exec("ALTER TABLE admin ADD COLUMN gotify_server TEXT DEFAULT ''");
}

$db->exec("UPDATE admin SET gotify_server = '' WHERE gotify_server IS NULL");

?>
