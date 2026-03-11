<?php

/* * This migration adds a column to the admin table to store a comma-separated 
* allowlist of hostnames and IPs that can be used in webhook notifications. 
* This prevents SSRF attacks on internal services.
*/

// Check if the column already exists to prevent errors on multiple runs
$query = $db->query("PRAGMA table_info(admin)");
$columnExists = false;

while ($row = $query->fetchArray(SQLITE3_ASSOC)) {
    if ($row['name'] === 'local_webhook_notifications_allowlist') {
        $columnExists = true;
        break;
    }
}

if (!$columnExists) {
    // Add the column with an empty string as the default
    $db->exec("ALTER TABLE admin ADD COLUMN local_webhook_notifications_allowlist TEXT DEFAULT ''");
}

?>