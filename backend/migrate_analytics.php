<?php
/**
 * Analytics Tables Migration
 * Adds page_views and video_views tables to existing database
 */

echo "LCMTV Analytics Migration\n";
echo "=========================\n\n";

require_once 'config/database.php';

$conn = getDBConnection();

try {
    // Check if tables already exist
    $tablesExist = [];
    $tables = ['page_views', 'video_views'];

    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        $tablesExist[$table] = $result->num_rows > 0;
    }

    // Create page_views table if it doesn't exist
    if (!$tablesExist['page_views']) {
        echo "Creating page_views table...\n";
        $sql = "
            CREATE TABLE page_views (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT,
                session_id VARCHAR(255) NOT NULL,
                page_url VARCHAR(500) NOT NULL,
                page_title VARCHAR(255),
                referrer_url VARCHAR(500),
                user_agent TEXT,
                ip_address VARCHAR(45),
                country VARCHAR(2),
                city VARCHAR(100),
                device_type ENUM('desktop', 'mobile', 'tablet', 'unknown') DEFAULT 'unknown',
                browser VARCHAR(50),
                os VARCHAR(50),
                screen_resolution VARCHAR(20),
                time_on_page INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_page_views_session (session_id),
                INDEX idx_page_views_created (created_at),
                INDEX idx_page_views_url (page_url(100))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";

        if ($conn->query($sql)) {
            echo "✓ page_views table created successfully\n";
        } else {
            throw new Exception("Failed to create page_views table: " . $conn->error);
        }
    } else {
        echo "✓ page_views table already exists\n";
    }

    // Create video_views table if it doesn't exist
    if (!$tablesExist['video_views']) {
        echo "Creating video_views table...\n";
        $sql = "
            CREATE TABLE video_views (
                id INT PRIMARY KEY AUTO_INCREMENT,
                video_id INT NOT NULL,
                user_id INT,
                session_id VARCHAR(255) NOT NULL,
                youtube_id VARCHAR(20) NOT NULL,
                watch_duration INT DEFAULT 0,
                total_duration INT DEFAULT 0,
                watch_percentage DECIMAL(5,2) DEFAULT 0,
                quality VARCHAR(10),
                playback_speed DECIMAL(3,1) DEFAULT 1.0,
                referrer_url VARCHAR(500),
                user_agent TEXT,
                ip_address VARCHAR(45),
                device_type ENUM('desktop', 'mobile', 'tablet', 'unknown') DEFAULT 'unknown',
                browser VARCHAR(50),
                os VARCHAR(50),
                country VARCHAR(2),
                city VARCHAR(100),
                completed BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_video_views_video (video_id),
                INDEX idx_video_views_user (user_id),
                INDEX idx_video_views_session (session_id),
                INDEX idx_video_views_created (created_at),
                INDEX idx_video_views_completed (completed)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";

        if ($conn->query($sql)) {
            echo "✓ video_views table created successfully\n";
        } else {
            throw new Exception("Failed to create video_views table: " . $conn->error);
        }
    } else {
        echo "✓ video_views table already exists\n";
    }

    echo "\n✓ Analytics migration completed successfully!\n";
    echo "You can now use the analytics features.\n";

} catch (Exception $e) {
    echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
