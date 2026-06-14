<?php
/**
 * Database Configuration
 * Railway/Render-ready: reads database credentials from environment variables.
 * Do not hard-code real database passwords in this file.
 */

// Database connection settings
// Railway MySQL variables: MYSQLHOST, MYSQLDATABASE, MYSQLUSER, MYSQLPASSWORD, MYSQLPORT
// Generic fallback variables: DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT
if (!defined('DB_HOST')) define('DB_HOST', getenv('MYSQLHOST') ?: getenv('DB_HOST') ?: 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', getenv('MYSQLDATABASE') ?: getenv('DB_NAME') ?: 'railway');
if (!defined('DB_USER')) define('DB_USER', getenv('MYSQLUSER') ?: getenv('DB_USER') ?: 'root');
if (!defined('DB_PASS')) define('DB_PASS', getenv('MYSQLPASSWORD') ?: getenv('DB_PASS') ?: '');
if (!defined('DB_PORT')) define('DB_PORT', getenv('MYSQLPORT') ?: getenv('DB_PORT') ?: '3306');

// Application settings
if (!defined('JWT_SECRET')) define('JWT_SECRET', getenv('JWT_SECRET') ?: 'change-this-jwt-secret-in-render-variables');
if (!defined('SITE_URL')) define('SITE_URL', getenv('SITE_URL') ?: 'https://moozu.wtf');

// Admin credentials
if (!defined('ADMIN_USERNAME')) define('ADMIN_USERNAME', getenv('ADMIN_USERNAME') ?: 'moozu');
if (!defined('ADMIN_PASSWORD')) define('ADMIN_PASSWORD', getenv('ADMIN_PASSWORD') ?: 'Moozu@Admin2024!');

// File upload settings
if (!defined('MAX_FILE_SIZE')) define('MAX_FILE_SIZE', 100 * 1024 * 1024); // 100MB
if (!defined('ALLOWED_EXTENSIONS')) define('ALLOWED_EXTENSIONS', ['zip', 'exe', 'rar']);

/**
 * Database Connection Function
 */
function getDBConnection() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
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

/**
 * Test Database Connection
 */
function testDatabaseConnection() {
    $pdo = getDBConnection();
    if ($pdo) {
        echo "Database connection: SUCCESS\n";
        $pdo = null;
        return true;
    } else {
        echo "Database connection: FAILED\n";
        return false;
    }
}

// Test connection if this file is accessed directly
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    testDatabaseConnection();
}
?>
