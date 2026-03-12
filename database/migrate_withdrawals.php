<?php
/**
 * Migration: Add fund_withdrawals table
 * Run once: php database/migrate_withdrawals.php
 */
define('OMAWD_ACCESS', true);
require_once dirname(__DIR__) . '/config/database.php';

echo "Running withdrawals migration...\n";

try {
    // Check if fund_withdrawals table exists
    $check = $pdo->query("SHOW TABLES LIKE 'fund_withdrawals'")->rowCount();
    if ($check == 0) {
        $pdo->exec("
            CREATE TABLE fund_withdrawals (
                id INT AUTO_INCREMENT PRIMARY KEY,
                fund_id INT NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                withdrawal_date DATE NOT NULL,
                purpose VARCHAR(255) NOT NULL,
                notes TEXT DEFAULT NULL,
                recorded_by INT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (fund_id) REFERENCES funds(id) ON DELETE CASCADE,
                FOREIGN KEY (recorded_by) REFERENCES accounts(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "✓ Created fund_withdrawals table\n";
    } else {
        echo "- fund_withdrawals table already exists\n";
    }

    echo "\nMigration complete!\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
