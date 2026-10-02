<?php

// JSON layout for dashboard widget order + visibility (homepage Edit mode).
// Legacy per-widget boolean columns from 000060 remain as a read fallback.

$layoutColumn = $db->query("SELECT * FROM pragma_table_info('settings') WHERE name='dashboard_widget_layout'");
if ($layoutColumn->fetchArray(SQLITE3_ASSOC) === false) {
    $db->exec("ALTER TABLE settings ADD COLUMN dashboard_widget_layout TEXT DEFAULT NULL");
}

?>
