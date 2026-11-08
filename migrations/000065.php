<?php

// Adds telegram_bot_token to the admin row: one Telegram bot for the whole
// instance, the way the SMTP transport is already one mail server for the
// whole instance.
//
// A Telegram bot token is not personal. It names a bot, and a bot can message
// anybody who has started a chat with it, so one bot serves every account on
// an installation; what is personal is the chat id, which stays on each user's
// own row. Before this, everybody in a household had to create a bot of their
// own through BotFather and paste its token into Wallos.
//
// Empty means what it has always meant: no instance bot, and a user's own
// token is the only one there is.

$column = $db->query("SELECT * FROM pragma_table_info('admin') WHERE name='telegram_bot_token'");

if ($column->fetchArray(SQLITE3_ASSOC) === false) {
    $db->exec("ALTER TABLE admin ADD COLUMN telegram_bot_token TEXT DEFAULT ''");
}

$db->exec("UPDATE admin SET telegram_bot_token = '' WHERE telegram_bot_token IS NULL");

?>
