<?php
/**
 * eClass - Database Configuration
 */
if (!defined('OMAWD_ACCESS')) {
    http_response_code(403);
    die('<h1>403 Forbidden</h1><p>Direct access not allowed.</p>');
}

define('DB_HOST', 'localhost');
define('DB_NAME', 'eclass_db');
define('DB_USER', 'root');
define('DB_PASS', 'rootUser123');
define('DB_CHARSET', 'utf8mb4');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
