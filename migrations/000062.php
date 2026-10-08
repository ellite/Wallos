<?php

// Adds an optional message_thread_id to Telegram notifications so messages can
// be sent to a specific topic inside a group chat.

$col = $db->query("SELECT * FROM pragma_table_info('telegram_notifications') WHERE name='message_thread_id'");
if ($col->fetchArray(SQLITE3_ASSOC) === false) {
    $db->exec("ALTER TABLE telegram_notifications ADD COLUMN message_thread_id TEXT DEFAULT ''");
}

?>
