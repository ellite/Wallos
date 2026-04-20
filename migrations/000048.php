<?php

/*
* Backfill migration for instances that ran an older 000043/000044 pair.
* Ensures SSRF allowlist column exists and uploaded_avatars table is present.
*/

// Ensure admin allowlist column exists
$adminQuery = $db->query("PRAGMA table_info(admin)");
$allowlistColumnExists = false;

while ($adminQuery && ($row = $adminQuery->fetchArray(SQLITE3_ASSOC))) {
    if ($row['name'] === 'local_webhook_notifications_allowlist') {
        $allowlistColumnExists = true;
        break;
    }
}

if (!$allowlistColumnExists) {
    $db->exec("ALTER TABLE admin ADD COLUMN local_webhook_notifications_allowlist TEXT DEFAULT ''");
}

// Ensure uploaded_avatars table exists
$avatarsTableExists = $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='uploaded_avatars'");
if (!$avatarsTableExists) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS uploaded_avatars (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            path TEXT NOT NULL
        )
    ");

    $userCount = (int) $db->querySingle("SELECT COUNT(*) FROM user");

    if ($userCount === 1) {
        $userId = (int) $db->querySingle("SELECT id FROM user LIMIT 1");
        $avatarDir = '../../images/uploads/logos/avatars';

        if (is_dir($avatarDir)) {
            $files = scandir($avatarDir);
            $stmt = $db->prepare("INSERT INTO uploaded_avatars (user_id, path) VALUES (:user_id, :path)");

            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..' && is_file($avatarDir . '/' . $file)) {
                    $relativePath = 'images/uploads/logos/avatars/' . $file;
                    $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
                    $stmt->bindValue(':path', $relativePath, SQLITE3_TEXT);
                    $stmt->execute();
                }
            }
        }
    } elseif ($userCount > 1) {
        $users = $db->query("SELECT id, avatar FROM user");
        $stmt = $db->prepare("INSERT INTO uploaded_avatars (user_id, path) VALUES (:user_id, :path)");

        while ($users && ($row = $users->fetchArray(SQLITE3_ASSOC))) {
            $userId = (int) $row['id'];
            $avatarPath = $row['avatar'] ?? '';

            if (strpos($avatarPath, 'images/uploads/logos/avatars/') === 0) {
                $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
                $stmt->bindValue(':path', $avatarPath, SQLITE3_TEXT);
                $stmt->execute();
            }
        }
    }
}

?>
