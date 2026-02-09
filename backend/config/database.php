<?php
/**
 * Database Configuration
 * LCMTV Web Platform
 */

require_once __DIR__ . '/../utils/EnvLoader.php';

// Database credentials
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'lcmtv_db');
define('DB_PORT', getenv('DB_PORT') ?: 3306);

// Create connection
function getDBConnection() {
    static $conn = null;

    if ($conn === null) {
        try {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

            if ($conn->connect_error) {
                throw new Exception("Connection failed: " . $conn->connect_error);
            }

            $conn->set_charset("utf8mb4");

        } catch (Exception $e) {
            error_log("Database connection error: " . $e->getMessage());
            // Don't use die() - throw exception so it can be caught and returned as JSON
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }

    return $conn;
}

// Close connection
function closeDBConnection($conn = null) {
    if ($conn !== null) {
        $conn->close();
    } else {
        $conn = getDBConnection();
        if ($conn) {
            $conn->close();
        }
    }
}
?>
