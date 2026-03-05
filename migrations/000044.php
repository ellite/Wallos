<?php

/* * This migration creates the uploaded_avatars table to isolate custom avatars
 * by user_id, preventing IDOR deletion vulnerabilities. It also migrates existing
 * avatars based on whether the instance is single-tenant or multi-tenant.
 */

// Check if the table already exists to prevent duplicate migration runs
$tableCheck = $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='uploaded_avatars'");

if (!$tableCheck) {
    // Create the uploaded_avatars table
    $db->exec("
        CREATE TABLE IF NOT EXISTS uploaded_avatars (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            path TEXT NOT NULL
        )
    ");

    // Check if solo user or multiple users
    $userCount = $db->querySingle("SELECT COUNT(*) FROM user");

    if ($userCount === 1) {
        // SOLO USER MIGRATION
        $userId = $db->querySingle("SELECT id FROM user LIMIT 1");
        
        $avatarDir = '../../images/uploads/logos/avatars';
        
        if (is_dir($avatarDir)) {
            $files = scandir($avatarDir);
            
            $stmt = $db->prepare("INSERT INTO uploaded_avatars (user_id, path) VALUES (:user_id, :path)");
            
            foreach ($files as $file) {
                // Skip directories and hidden files (like .gitkeep or .htaccess)
                if ($file !== '.' && $file !== '..' && is_file($avatarDir . '/' . $file)) {
                    // Store the path exactly as the app expects it in the database
                    $relativePath = 'images/uploads/logos/avatars/' . $file;
                    
                    $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
                    $stmt->bindValue(':path', $relativePath, SQLITE3_TEXT);
                    $stmt->execute();
                }
            }
        }
    } elseif ($userCount > 1) {
        // MULTI-USER MIGRATION
        $results = $db->query("SELECT id, avatar FROM user");
        
        $stmt = $db->prepare("INSERT INTO uploaded_avatars (user_id, path) VALUES (:user_id, :path)");
        
        while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
            $userId = $row['id'];
            $avatarPath = $row['avatar'];
            
            if (strpos($avatarPath, 'images/uploads/logos/avatars/') === 0) {
                $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
                $stmt->bindValue(':path', $avatarPath, SQLITE3_TEXT);
                $stmt->execute();
            }
        }
    }
}

?>