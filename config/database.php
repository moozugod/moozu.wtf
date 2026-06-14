<?php
/**
 * Database Configuration for Render + Railway
 */

$dbUrl = getenv('MYSQL_PUBLIC_URL') ?: getenv('DATABASE_URL') ?: getenv('MYSQL_URL');

if ($dbUrl) {
    $parts = parse_url($dbUrl);

    define('DB_HOST', $parts['host'] ?? '');
    define('DB_PORT', $parts['port'] ?? '3306');
    define('DB_NAME', isset($parts['path']) ? ltrim($parts['path'], '/') : '');
    define('DB_USER', isset($parts['user']) ? urldecode($parts['user']) : '');
    define('DB_PASS', isset($parts['pass']) ? urldecode($parts['pass']) : '');
} else {
    define('DB_HOST', getenv('MYSQLHOST') ?: '');
    define('DB_PORT', getenv('MYSQLPORT') ?: '3306');
    define('DB_NAME', getenv('MYSQLDATABASE') ?: '');
    define('DB_USER', getenv('MYSQLUSER') ?: '');
    define('DB_PASS', getenv('MYSQLPASSWORD') ?: '');
}

define('JWT_SECRET', getenv('JWT_SECRET') ?: 'ezpzy');
define('SITE_URL', getenv('SITE_URL') ?: 'https://moozu-wtf.onrender.com');

define('ADMIN_USERNAME', getenv('ADMIN_USERNAME') ?: 'moozu');
define('ADMIN_PASSWORD', getenv('ADMIN_PASSWORD') ?: 'Moozu@Admin2024!');

define('MAX_FILE_SIZE', 100 * 1024 * 1024);
define('ALLOWED_EXTENSIONS', ['zip', 'exe', 'rar']);

function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";

        $pdo = new PDO(
            $dsn,
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );

        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection error: " . $e->getMessage());
        return null;
    }
}

function testDatabaseConnection() {
    $pdo = getDBConnection();

    if ($pdo) {
        echo "Database connection: SUCCESS\n";
        return true;
    }

    echo "Database connection: FAILED\n";
    return false;
}

if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    testDatabaseConnection();
}
?>
