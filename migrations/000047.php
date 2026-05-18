<?php
// This migration adds default_auto_renew and default_notifications columns to the settings table.
// These control the default state of the auto-renew and notifications toggles when adding a new subscription.

$columnQuery = $db->query("SELECT * FROM pragma_table_info('settings') where name='default_auto_renew'");
$column = $columnQuery->fetchArray(SQLITE3_ASSOC);
if (!$column) {
    $db->exec('ALTER TABLE settings ADD COLUMN default_auto_renew BOOLEAN DEFAULT 1');
    echo "Column 'default_auto_renew' added to table 'settings'.\n";
} else {
    echo "Column 'default_auto_renew' already exists in table 'settings'.\n";
}

$columnQuery = $db->query("SELECT * FROM pragma_table_info('settings') where name='default_notifications'");
$column = $columnQuery->fetchArray(SQLITE3_ASSOC);
if (!$column) {
    $db->exec('ALTER TABLE settings ADD COLUMN default_notifications BOOLEAN DEFAULT 1');
    echo "Column 'default_notifications' added to table 'settings'.\n";
} else {
    echo "Column 'default_notifications' already exists in table 'settings'.\n";
}
