<?php
/**
 * Database Migration Runner
 * Run: php database/migrate.php
 */
define('OMAWD_ACCESS', true);
require_once __DIR__ . '/../config/database.php';

echo "Running migration: add_receipt_columns.sql\n";

try {
    // Add receipt_image column to fund_payments
    $pdo->exec("ALTER TABLE fund_payments ADD COLUMN receipt_image LONGBLOB DEFAULT NULL AFTER notes");
    echo "✓ Added receipt_image column\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "- receipt_image column already exists\n";
    } else {
        echo "✗ Error: " . $e->getMessage() . "\n";
    }
}

try {
    $pdo->exec("ALTER TABLE fund_payments ADD COLUMN receipt_mime VARCHAR(50) DEFAULT NULL AFTER receipt_image");
    echo "✓ Added receipt_mime column\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "- receipt_mime column already exists\n";
    } else {
        echo "✗ Error: " . $e->getMessage() . "\n";
    }
}

try {
    // Add acknowledgment form columns to billing periods
    $pdo->exec("ALTER TABLE fund_billing_periods ADD COLUMN acknowledgment_form LONGBLOB DEFAULT NULL AFTER status");
    echo "✓ Added acknowledgment_form column\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "- acknowledgment_form column already exists\n";
    } else {
        echo "✗ Error: " . $e->getMessage() . "\n";
    }
}

try {
    $pdo->exec("ALTER TABLE fund_billing_periods ADD COLUMN form_mime VARCHAR(50) DEFAULT NULL AFTER acknowledgment_form");
    echo "✓ Added form_mime column\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "- form_mime column already exists\n";
    } else {
        echo "✗ Error: " . $e->getMessage() . "\n";
    }
}

try {
    $pdo->exec("ALTER TABLE fund_billing_periods ADD COLUMN form_uploaded_at DATETIME DEFAULT NULL AFTER form_mime");
    echo "✓ Added form_uploaded_at column\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "- form_uploaded_at column already exists\n";
    } else {
        echo "✗ Error: " . $e->getMessage() . "\n";
    }
}

try {
    // Create ledger documents table
    $pdo->exec("CREATE TABLE IF NOT EXISTS fund_ledger_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fund_id INT NOT NULL,
        billing_period_id INT DEFAULT NULL,
        document_number VARCHAR(50) NOT NULL UNIQUE,
        qr_code_data VARCHAR(255) NOT NULL,
        generated_by INT DEFAULT NULL,
        generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        period_start DATE NOT NULL,
        period_end DATE NOT NULL,
        FOREIGN KEY (fund_id) REFERENCES funds(id) ON DELETE CASCADE,
        FOREIGN KEY (billing_period_id) REFERENCES fund_billing_periods(id) ON DELETE SET NULL,
        FOREIGN KEY (generated_by) REFERENCES accounts(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✓ Created fund_ledger_documents table\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
        echo "- fund_ledger_documents table already exists\n";
    } else {
        echo "✗ Error: " . $e->getMessage() . "\n";
    }
}

echo "\n✅ Migration complete!\n";
