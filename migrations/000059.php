<?php

// Adds Web Push as a notification channel: standard browser push (Push API +
// Service Worker), no third-party account needed by the user - unlike every
// other push-style channel Wallos already has (Pushover, Gotify, ntfy),
// which all require registering somewhere else first.
//
// push_notifications follows the same shape every other channel's settings
// table has: one row per user, an "enabled" toggle. push_subscriptions is
// one-to-many instead, because a single account can have Wallos open on a
// phone and a laptop at once, and each browser/device gets its own
// subscription (endpoint + keys) from the Push API - there is no single
// "the subscription" for an account the way there is a single ntfy topic.
//
// The instance-wide VAPID keypair identifies this Wallos installation to the
// push services (Chrome's, Firefox's, ...) it talks to; it is not configured
// by an admin the way SMTP is, so it is generated once, lazily, by
// includes/webpush_helper.php the first time it is needed, and stored on the
// admin row alongside the other instance-wide settings that already live
// there (smtp_*, server_url).

$db->exec("CREATE TABLE IF NOT EXISTS push_notifications (
    enabled BOOLEAN DEFAULT 0,
    user_id INTEGER,
    FOREIGN KEY (user_id) REFERENCES user(id)
)");

$db->exec("CREATE TABLE IF NOT EXISTS push_subscriptions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    endpoint TEXT NOT NULL,
    p256dh TEXT NOT NULL,
    auth TEXT NOT NULL,
    user_agent TEXT DEFAULT '',
    created_at TEXT DEFAULT '',
    FOREIGN KEY (user_id) REFERENCES user(id)
)");

$db->exec('CREATE UNIQUE INDEX IF NOT EXISTS push_subscriptions_user_endpoint
           ON push_subscriptions(user_id, endpoint)');

$vapidPublicKeyColumn = $db->query("SELECT * FROM pragma_table_info('admin') WHERE name='vapid_public_key'");
if ($vapidPublicKeyColumn->fetchArray(SQLITE3_ASSOC) === false) {
    $db->exec("ALTER TABLE admin ADD COLUMN vapid_public_key TEXT DEFAULT ''");
}

$vapidPrivateKeyColumn = $db->query("SELECT * FROM pragma_table_info('admin') WHERE name='vapid_private_key'");
if ($vapidPrivateKeyColumn->fetchArray(SQLITE3_ASSOC) === false) {
    $db->exec("ALTER TABLE admin ADD COLUMN vapid_private_key TEXT DEFAULT ''");
}

?>
