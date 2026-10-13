<?php

// Adds ntfy_server and ntfy_headers to the admin row: one ntfy server for the
// whole instance, the way the SMTP transport is already one mail server for
// the whole instance.
//
// What is personal about ntfy is the topic, which stays on each user's own
// row. The server address is not: a household that self-hosts ntfy runs one
// server, and before this every member had to know its address and type it in.
// The headers are the credential that server may require, and they belong with
// the server they authenticate to - never with a server a user chose, which is
// what includes/instance_config.php's resolver is careful about.
//
// Empty means what it has always meant: no instance server, and a user's own
// address is the only one there is.

foreach (['ntfy_server', 'ntfy_headers'] as $column) {
    $exists = $db->query("SELECT * FROM pragma_table_info('admin') WHERE name='" . $column . "'");

    if ($exists->fetchArray(SQLITE3_ASSOC) === false) {
        $db->exec("ALTER TABLE admin ADD COLUMN " . $column . " TEXT DEFAULT ''");
    }

    $db->exec("UPDATE admin SET " . $column . " = '' WHERE " . $column . " IS NULL");
}

?>
