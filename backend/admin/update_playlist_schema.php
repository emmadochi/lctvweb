<?php
/**
 * Database schema update for playlist mapping functionality
 * Creates required tables if they don't exist
 */

require_once __DIR__ . '/config/database.php';

try {
    $conn = getDBConnection();
    
    echo "<h2>Playlist Mapping Database Schema Update</h2>\n";
    
    // Create channel_playlist_mapping table
    $sql1 = "CREATE TABLE IF NOT EXISTS channel_playlist_mapping (
        id INT AUTO_INCREMENT PRIMARY KEY,
        channel_sync_id INT NOT NULL,
        playlist_id VARCHAR(100) NOT NULL,
        playlist_name VARCHAR(255) NOT NULL,
        category_id INT NOT NULL,
        import_limit INT DEFAULT 10,
        require_approval BOOLEAN DEFAULT FALSE,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (channel_sync_id) REFERENCES channel_sync(id) ON DELETE CASCADE,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
        UNIQUE KEY unique_channel_playlist (channel_sync_id, playlist_id)
    )";
    
    if ($conn->query($sql1)) {
        echo "✓ channel_playlist_mapping table created/verified<br>\n";
    } else {
        echo "✗ Error creating channel_playlist_mapping: " . $conn->error . "<br>\n";
    }
    
    // Create video_playlists table
    $sql2 = "CREATE TABLE IF NOT EXISTS video_playlists (
        id INT AUTO_INCREMENT PRIMARY KEY,
        video_id INT NOT NULL,
        playlist_id VARCHAR(100) NOT NULL,
        playlist_name VARCHAR(255) NOT NULL,
        channel_sync_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
        FOREIGN KEY (channel_sync_id) REFERENCES channel_sync(id) ON DELETE CASCADE,
        UNIQUE KEY unique_video_playlist (video_id, playlist_id)
    )";
    
    if ($conn->query($sql2)) {
        echo "✓ video_playlists table created/verified<br>\n";
    } else {
        echo "✗ Error creating video_playlists: " . $conn->error . "<br>\n";
    }
    
    // Create video_category_override table
    $sql3 = "CREATE TABLE IF NOT EXISTS video_category_override (
        id INT AUTO_INCREMENT PRIMARY KEY,
        video_id INT NOT NULL,
        original_category_id INT NOT NULL,
        new_category_id INT NOT NULL,
        reason TEXT,
        user_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
        FOREIGN KEY (original_category_id) REFERENCES categories(id) ON DELETE CASCADE,
        FOREIGN KEY (new_category_id) REFERENCES categories(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    )";
    
    if ($conn->query($sql3)) {
        echo "✓ video_category_override table created/verified<br>\n";
    } else {
        echo "✗ Error creating video_category_override: " . $conn->error . "<br>\n";
    }
    
    // Verify all tables exist
    echo "<h3>Verification:</h3>\n";
    $requiredTables = ['channel_playlist_mapping', 'video_playlists', 'video_category_override'];
    
    foreach ($requiredTables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            echo "✓ Table <strong>$table</strong> exists<br>\n";
        } else {
            echo "✗ Table <strong>$table</strong> missing<br>\n";
        }
    }
    
    echo "<br><strong>Schema update completed!</strong><br>\n";
    echo "You can now test the playlist mapping functionality.<br>\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>\n";
    echo "File: " . $e->getFile() . "<br>\n";
    echo "Line: " . $e->getLine() . "<br>\n";
}
?>