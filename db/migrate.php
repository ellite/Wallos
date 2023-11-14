<?php
/** @var \SQLite3 $db */
require_once 'includes/connect_endpoint_crontabs.php';

$completedMigrations = [];

$migrationTableExists = $db
        ->query('SELECT name FROM sqlite_master WHERE type="table" AND name="migrations"')
        ->fetchArray(SQLITE3_ASSOC) !== false;

if ($migrationTableExists) {
    $migrationQuery = $db->query('SELECT migration FROM migrations');
    while ($row = $migrationQuery->fetchArray(SQLITE3_ASSOC)) {
        $completedMigrations[] = $row['migration'];
    }
}

$allMigrations = glob('migrations/*.php');
$requiredMigrations = array_diff($allMigrations, $completedMigrations);

if (count($requiredMigrations) === 0) {
    echo "No migrations to run.\n";
}

foreach ($requiredMigrations as $migration) {
    require_once $migration;

    $stmtInsert = $db->prepare('INSERT INTO migrations (migration) VALUES (:migration)');
    $stmtInsert->bindParam(':migration', $migration, SQLITE3_TEXT);
    $stmtInsert->execute();

    echo sprintf("Migration %s completed successfully.\n", $migration);
}
