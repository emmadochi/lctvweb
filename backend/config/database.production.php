<?php
/**
 * Production Database Configuration Template
 * Copy this to database.php and update with your production credentials
 */

// Production Database Configuration for Namecheap Hosting
define('DB_HOST', 'localhost');        // Usually localhost for shared hosting
define('DB_NAME', 'yourusername_lcmtv'); // Replace with your database name
define('DB_USER', 'yourusername_dbuser'); // Replace with your database username
define('DB_PASS', 'your_secure_password'); // Replace with your database password
define('DB_PORT', '3306');            // Default MySQL port

// Optional: Database connection options
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATION', 'utf8mb4_unicode_ci');

// Get database connection
function getDBConnection() {
    static $conn = null;

    if ($conn === null) {
        try {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

            if ($conn->connect_error) {
                error_log("Database connection failed: " . $conn->connect_error);
                throw new Exception("Database connection failed");
            }

            // Set charset
            $conn->set_charset(DB_CHARSET);

        } catch (Exception $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw $e;
        }
    }

    return $conn;
}

// Close database connection
function closeDBConnection($conn = null) {
    if ($conn === null) {
        // Close default connection if no specific connection provided
        static $defaultConn = null;
        if ($defaultConn !== null) {
            $defaultConn->close();
            $defaultConn = null;
        }
    } else {
        $conn->close();
    }
}
?>