<?php

// JSON layout for dashboard widget order + visibility (homepage Edit mode).
// Legacy per-widget boolean columns from 000071 remain as a read fallback.
//
// Earlier builds stored this column from migrations/000063.php, and the
// previous numbering of this branch stored it from migrations/000070.php.
// Both of those filenames now belong to upstream, so this file is 000072.
// The column is added only when it is missing, which keeps a layout those
// builds already saved.

$layoutColumn = $db->query("SELECT * FROM pragma_table_info('settings') WHERE name='dashboard_widget_layout'");
if ($layoutColumn->fetchArray(SQLITE3_ASSOC) === false) {
    $db->exec("ALTER TABLE settings ADD COLUMN dashboard_widget_layout TEXT DEFAULT NULL");
}

?>
