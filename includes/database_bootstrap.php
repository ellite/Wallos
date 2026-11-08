<?php

/**
 * Helpers for detecting / recreating an incomplete Wallos SQLite database.
 * An empty wallos.db can appear when PHP opens SQLite before createdatabase runs.
 */

if (!function_exists('wallos_database_needs_create')) {
    /**
     * @return bool True when the DB file is missing or is readable but has no core schema.
     */
    function wallos_database_needs_create($databaseFile)
    {
        if (!file_exists($databaseFile)) {
            return true;
        }

        // Only a successful probe that finds no `user` table counts as incomplete.
        // Any open/query failure (locked DB, permissions, ...) must NOT trigger a
        // recreate, because that deletes the existing database files.
        try {
            $probe = new SQLite3($databaseFile, SQLITE3_OPEN_READONLY);
            $probe->busyTimeout(1000);
            $result = $probe->query("SELECT name FROM sqlite_master WHERE type='table' AND name='user'");
            if ($result === false) {
                $probe->close();
                return false;
            }
            $hasUser = $result->fetchArray(SQLITE3_ASSOC);
            $probe->close();

            return !$hasUser;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('wallos_remove_database_files')) {
    function wallos_remove_database_files($databaseFile)
    {
        foreach ([$databaseFile, $databaseFile . '-wal', $databaseFile . '-shm', $databaseFile . '-journal'] as $path) {
            if (file_exists($path)) {
                @unlink($path);
            }
        }
    }
}
