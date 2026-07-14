<?php
// This migration repairs databases that missed the themed logo columns due to
// a historical migration-number collision on some long-lived branches.

$logoTextColorColumn = $db->query("SELECT * FROM pragma_table_info('subscriptions') WHERE name='logo_text_color'");
if ($logoTextColorColumn->fetchArray(SQLITE3_ASSOC) === false) {
    $db->exec("ALTER TABLE subscriptions ADD COLUMN logo_text_color TEXT DEFAULT NULL");
}

$logoVariantColumn = $db->query("SELECT * FROM pragma_table_info('subscriptions') WHERE name='logo_variant'");
if ($logoVariantColumn->fetchArray(SQLITE3_ASSOC) === false) {
    $db->exec("ALTER TABLE subscriptions ADD COLUMN logo_variant TEXT DEFAULT NULL");
}

?>
