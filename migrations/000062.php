<?php

// Sign in with ChatGPT (includes/chatgpt_auth.php).
//
// ai_chatgpt_auth holds one connection per user: the client OpenAI issued at
// registration, the verified account and the OAuth tokens. The whole record is
// encrypted with WALLOS_SECRET_KEY, because backups copy the db folder as is.
//
// admin.chatgpt_host_id identifies this installation to OpenAI. It is not a
// secret and stays the same across sign-ins and users.

$db->exec("CREATE TABLE IF NOT EXISTS ai_chatgpt_auth (
    user_id INTEGER PRIMARY KEY,
    record TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE
)");

$query = $db->query("PRAGMA table_info(admin)");
$columnExists = false;

while ($row = $query->fetchArray(SQLITE3_ASSOC)) {
    if ($row['name'] === 'chatgpt_host_id') {
        $columnExists = true;
        break;
    }
}

if (!$columnExists) {
    $db->exec("ALTER TABLE admin ADD COLUMN chatgpt_host_id TEXT");
}

?>
