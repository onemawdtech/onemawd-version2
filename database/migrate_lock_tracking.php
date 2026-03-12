<?php
/**
 * Migration: Add locked_by and locked_at columns for lock tracking
 * Run once: php database/migrate_lock_tracking.php
 */
define('OMAWD_ACCESS', true);
require_once dirname(__DIR__) . '/config/database.php';

echo "Running lock tracking migration...\n";

try {
    // Add locked_by to attendance_sessions
    $check = $pdo->query("SHOW COLUMNS FROM attendance_sessions LIKE 'locked_by'")->rowCount();
    if ($check == 0) {
        $pdo->exec("ALTER TABLE attendance_sessions ADD COLUMN locked_by INT DEFAULT NULL AFTER is_locked");
        $pdo->exec("ALTER TABLE attendance_sessions ADD COLUMN locked_at DATETIME DEFAULT NULL AFTER locked_by");
        echo "✓ Added locked_by, locked_at to attendance_sessions\n";
    } else {
        echo "- attendance_sessions lock tracking already exists\n";
    }

    // Add locked_by to funds
    $check = $pdo->query("SHOW COLUMNS FROM funds LIKE 'locked_by'")->rowCount();
    if ($check == 0) {
        $pdo->exec("ALTER TABLE funds ADD COLUMN locked_by INT DEFAULT NULL AFTER is_locked");
        $pdo->exec("ALTER TABLE funds ADD COLUMN locked_at DATETIME DEFAULT NULL AFTER locked_by");
        echo "✓ Added locked_by, locked_at to funds\n";
    } else {
        echo "- funds lock tracking already exists\n";
    }

    // Add auto_lock_days setting to funds (days after which records auto-lock, 0 = disabled)
    $check = $pdo->query("SHOW COLUMNS FROM funds LIKE 'auto_lock_days'")->rowCount();
    if ($check == 0) {
        $pdo->exec("ALTER TABLE funds ADD COLUMN auto_lock_days INT NOT NULL DEFAULT 0 AFTER locked_at");
        echo "✓ Added auto_lock_days to funds\n";
    } else {
        echo "- funds auto_lock_days already exists\n";
    }

    echo "\nMigration complete!\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
