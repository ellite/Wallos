<?php
// This migration adds default_auto_renew and default_notifications columns to the settings table.
// They set the initial state of the auto renewal and notification toggles when adding a subscription.
// The defaults match the current form: auto renewal on, notifications off.

$columnQuery = $db->query("SELECT * FROM pragma_table_info('settings') WHERE name='default_auto_renew'");
$column = $columnQuery->fetchArray(SQLITE3_ASSOC);
if (!$column) {
    $db->exec('ALTER TABLE settings ADD COLUMN default_auto_renew BOOLEAN DEFAULT 1');
    echo "Column 'default_auto_renew' added to table 'settings'.\n";
} else {
    echo "Column 'default_auto_renew' already exists in table 'settings'.\n";
}

$columnQuery = $db->query("SELECT * FROM pragma_table_info('settings') WHERE name='default_notifications'");
$column = $columnQuery->fetchArray(SQLITE3_ASSOC);
if (!$column) {
    $db->exec('ALTER TABLE settings ADD COLUMN default_notifications BOOLEAN DEFAULT 0');
    echo "Column 'default_notifications' added to table 'settings'.\n";
} else {
    echo "Column 'default_notifications' already exists in table 'settings'.\n";
}
