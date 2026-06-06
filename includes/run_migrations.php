<?php
// Expects $db to be set by the caller.

$migrationsDir = __DIR__ . '/../migrations/';

$completedMigrations = [];

$migrationTableExists = $db
    ->query("SELECT name FROM sqlite_master WHERE type='table' AND name='migrations'")
    ->fetchArray(SQLITE3_ASSOC) !== false;

if ($migrationTableExists) {
    $migrationQuery = $db->query('SELECT migration FROM migrations');
    while ($row = $migrationQuery->fetchArray(SQLITE3_ASSOC)) {
        $completedMigrations[] = str_replace('../../', '', $row['migration']);
    }
}

$allMigrations = array_map(
    fn($path) => 'migrations/' . basename($path),
    glob($migrationsDir . '*.php') ?: []
);

$requiredMigrations = array_diff($allMigrations, $completedMigrations);

if (count($requiredMigrations) === 0) {
    echo "No migrations to run.\n";
}

foreach ($requiredMigrations as $migration) {
    require_once $migrationsDir . basename($migration);

    $stmt = $db->prepare('INSERT INTO migrations (migration) VALUES (:migration)');
    $stmt->bindValue(':migration', $migration, SQLITE3_TEXT);
    $stmt->execute();

    echo sprintf("Migration %s completed successfully.\n", $migration);
}
