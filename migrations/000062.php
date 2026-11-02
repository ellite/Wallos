<?php
// This migration adds an upcoming_payments_days column to the settings table.
// 0 keeps the count-based Upcoming Payments list; 7, 14, 30, 60 or 90 shows every payment due within that many days.

$columnQuery = $db->query("SELECT * FROM pragma_table_info('settings') WHERE name='upcoming_payments_days'");
$column = $columnQuery->fetchArray(SQLITE3_ASSOC);
if (!$column) {
    $db->exec('ALTER TABLE settings ADD COLUMN upcoming_payments_days INTEGER DEFAULT 0');
    echo "Column 'upcoming_payments_days' added to table 'settings'.\n";
} else {
    echo "Column 'upcoming_payments_days' already exists in table 'settings'.\n";
}
