<?php
/**
 * LCMTV Database Setup Script
 * Run this script to initialize the database
 */

require_once 'config/database.php';

echo "LCMTV Database Setup\n";
echo "====================\n\n";

// First, try to connect without specifying the database to create it
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, '', DB_PORT);

if ($conn->connect_error) {
    die("Failed to connect to database server: " . $conn->connect_error . "\n");
}

echo "✓ Connected to database server\n";

// Create database if it doesn't exist
$createDbSql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($conn->query($createDbSql) === TRUE) {
    echo "✓ Database '" . DB_NAME . "' created or already exists\n";
} else {
    die("✗ Failed to create database: " . $conn->error . "\n");
}

// Now connect to the specific database
$conn->select_db(DB_NAME);

// Read and execute schema
$schemaFile = __DIR__ . '/config/schema.sql';
if (!file_exists($schemaFile)) {
    die("Schema file not found: $schemaFile\n");
}

$schema = file_get_contents($schemaFile);
$statements = array_filter(array_map('trim', explode(';', $schema)));

$successCount = 0;
$errorCount = 0;

foreach ($statements as $statement) {
    if (empty($statement)) continue;

    if ($conn->query($statement) === TRUE) {
        $successCount++;
    } else {
        echo "✗ Error executing statement: " . $conn->error . "\n";
        echo "Statement: " . substr($statement, 0, 100) . "...\n\n";
        $errorCount++;
    }
}

echo "\nSetup Complete!\n";
echo "===============\n";
echo "✓ Successful statements: $successCount\n";

if ($errorCount > 0) {
    echo "✗ Failed statements: $errorCount\n";
    echo "\nPlease check the errors above and try again.\n";
} else {
    echo "✓ All statements executed successfully!\n\n";

    // Create demo user if it doesn't exist
    echo "Creating demo user...\n";
    require_once 'models/User.php';

    $demoEmail = 'demo@lcmtv.com';
    $demoPassword = 'demo123';

    if (!User::findByEmail($demoEmail)) {
        $demoUserData = [
            'email' => $demoEmail,
            'password' => password_hash($demoPassword, PASSWORD_DEFAULT),
            'first_name' => 'Demo',
            'last_name' => 'User',
            'role' => 'user'
        ];

        $userId = User::create($demoUserData);
        if ($userId) {
            echo "✓ Demo user created: $demoEmail / $demoPassword\n";
        } else {
            echo "✗ Failed to create demo user\n";
        }
    } else {
        echo "✓ Demo user already exists\n";
    }

    echo "\nDatabase setup complete. You can now:\n";
    echo "1. Start the backend API server\n";
    echo "2. Access the admin panel at /admin\n";
    echo "3. Demo login: demo@lcmtv.com / demo123\n";
    echo "4. Default admin login: admin@lcmtv.com / admin123\n";
}

closeDBConnection($conn);
?>
