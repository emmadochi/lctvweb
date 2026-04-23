<?php

// Override DB_HOST before requiring database.php
putenv('DB_HOST=127.0.0.1');

require_once __DIR__ . '/config/database.php';

try {
    $conn = getDBConnection();
    $sql = "ALTER TABLE video_purchases ADD COLUMN transaction_id VARCHAR(255) UNIQUE AFTER currency";
    if ($conn->query($sql) === TRUE) {
        echo "Table altered successfully\n";
    } else {
        echo "Error altering table: " . $conn->error . "\n";
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
?>
