<?php
/**
 * OneMAWD - Database Configuration
 * Reads credentials from .env file
 */
if (!defined('OMAWD_ACCESS')) {
    http_response_code(403);
    die('<h1>403 Forbidden</h1><p>Direct access not allowed.</p>');
}

// Parse .env file
$_envFile = dirname(__DIR__) . '/.env';
if (file_exists($_envFile)) {
    foreach (file($_envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $_line) {
        if (str_starts_with(trim($_line), '#')) continue;
        if (strpos($_line, '=') === false) continue;
        [$_key, $_val] = explode('=', $_line, 2);
        $_ENV[trim($_key)] = trim($_val);
    }
}

define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_PORT', $_ENV['DB_PORT'] ?? '3306');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'eclass_db');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_CHARSET', 'utf8mb4');
define('DB_SSL', ($_ENV['DB_SSL'] ?? 'false') === 'true');

try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    if (DB_SSL) {
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        $options[PDO::MYSQL_ATTR_SSL_CA] = '';
    }

    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
