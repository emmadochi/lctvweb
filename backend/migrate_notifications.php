<?php
/**
 * Notifications Migration Script
 * Adds notifications table to existing database
 */

require_once 'config/database.php';

echo "LCMTV Notifications Migration\n";
echo "==============================\n\n";

try {
    $conn = getDBConnection();

    // Check if notifications table already exists
    $result = $conn->query("SHOW TABLES LIKE 'notifications'");
    if ($result->num_rows > 0) {
        echo "✓ Notifications table already exists\n";
        echo "\nMigration complete. No changes needed.\n";
        exit(0);
    }

    echo "Creating notifications table...\n";

    $sql = "CREATE TABLE notifications (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        type ENUM('favorite', 'subscription', 'system', 'interaction') NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        related_id INT,
        related_type ENUM('video', 'user', 'category', 'system'),
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_notifications_user (user_id),
        INDEX idx_notifications_read (is_read),
        INDEX idx_notifications_created (created_at),
        INDEX idx_notifications_type (type)
    )";

    if ($conn->query($sql) === TRUE) {
        echo "✓ Notifications table created successfully\n";
        echo "\nMigration complete!\n";
        echo "=================\n";
        echo "✓ Notifications table added to database\n";
        echo "✓ Indexes created for optimal performance\n";
        echo "✓ Foreign key constraints added\n";
    } else {
        throw new Exception("Failed to create notifications table: " . $conn->error);
    }

} catch (Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

closeDBConnection($conn);
?>