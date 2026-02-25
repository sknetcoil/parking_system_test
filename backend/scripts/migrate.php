<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;

const MAX_RETRIES = 20;
const RETRY_SLEEP_SECONDS = 2;

function connectWithRetry(): PDO
{
    $lastError = null;

    for ($i = 1; $i <= MAX_RETRIES; $i++) {
        try {
            return Database::getInstance();
        } catch (\Throwable $e) {
            $lastError = $e;
            fwrite(STDERR, "Database not ready (attempt {$i}/" . MAX_RETRIES . ")\n");
            sleep(RETRY_SLEEP_SECONDS);
        }
    }

    throw new RuntimeException('Could not connect to database for migrations', previous: $lastError);
}

function ensureMigrationsTable(PDO $db): void
{
    $db->exec("
        CREATE TABLE IF NOT EXISTS schema_migrations (
            version VARCHAR(255) PRIMARY KEY,
            applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");
}

function getAppliedMigrations(PDO $db): array
{
    $stmt = $db->query("SELECT version FROM schema_migrations");
    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

function applyMigration(PDO $db, string $version, string $sql): void
{
    $db->beginTransaction();

    try {
        $db->exec($sql);

        $insert = $db->prepare("INSERT INTO schema_migrations (version) VALUES (:version)");
        $insert->execute(['version' => $version]);

        $db->commit();
        echo "Applied migration: {$version}\n";
    } catch (\Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

try {
    $db = connectWithRetry();
    $db->query("SELECT pg_advisory_lock(194219421)");
    ensureMigrationsTable($db);

    $applied = array_flip(getAppliedMigrations($db));
    $migrationFiles = glob(__DIR__ . '/../migrations/*.sql') ?: [];
    sort($migrationFiles, SORT_STRING);

    foreach ($migrationFiles as $file) {
        $version = basename($file);
        if (isset($applied[$version])) {
            continue;
        }

        $sql = trim((string)file_get_contents($file));
        if ($sql === '') {
            continue;
        }

        applyMigration($db, $version, $sql);
    }

    echo "Migrations up to date.\n";
    $db->query("SELECT pg_advisory_unlock(194219421)");
} catch (\Throwable $e) {
    fwrite(STDERR, "Migration failed: {$e->getMessage()}\n");
    exit(1);
}
