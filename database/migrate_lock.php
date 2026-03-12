<?php
/**
 * Migration: Add is_locked columns to attendance_sessions and funds
 * Run once: php database/migrate_lock.php
 */
define('OMAWD_ACCESS', true);
require_once dirname(__DIR__) . '/config/database.php';

echo "Running lock records migration...\n";

try {
    // Add is_locked to attendance_sessions
    $check = $pdo->query("SHOW COLUMNS FROM attendance_sessions LIKE 'is_locked'")->rowCount();
    if ($check == 0) {
        $pdo->exec("ALTER TABLE attendance_sessions ADD COLUMN is_locked TINYINT(1) NOT NULL DEFAULT 0");
        echo "✓ Added is_locked to attendance_sessions\n";
    } else {
        echo "- attendance_sessions.is_locked already exists\n";
    }

    // Add is_locked to funds
    $check = $pdo->query("SHOW COLUMNS FROM funds LIKE 'is_locked'")->rowCount();
    if ($check == 0) {
        $pdo->exec("ALTER TABLE funds ADD COLUMN is_locked TINYINT(1) NOT NULL DEFAULT 0");
        echo "✓ Added is_locked to funds\n";
    } else {
        echo "- funds.is_locked already exists\n";
    }

    echo "\nMigration complete!\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
