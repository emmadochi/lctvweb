<?php
/**
 * Update database schema for playlist mapping feature
 * Run this script to add the new tables required for playlist-to-category mapping
 */

require_once 'config/database.php';

try {
    $conn = getDBConnection();
    
    echo "=== Updating Database Schema for Playlist Mapping ===\n\n";
    
    // Create channel_playlist_mapping table
    $sql1 = "CREATE TABLE IF NOT EXISTS channel_playlist_mapping (
        id INT PRIMARY KEY AUTO_INCREMENT,
        channel_sync_id INT NOT NULL,
        playlist_id VARCHAR(50) NOT NULL,
        playlist_name VARCHAR(255),
        category_id INT NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        import_limit INT DEFAULT 20,
        require_approval BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (channel_sync_id) REFERENCES channel_sync(id) ON DELETE CASCADE,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
        UNIQUE KEY unique_channel_playlist (channel_sync_id, playlist_id),
        INDEX idx_playlist_id (playlist_id),
        INDEX idx_active (is_active)
    )";
    
    if ($conn->query($sql1)) {
        echo "✓ channel_playlist_mapping table created successfully\n";
    } else {
        throw new Exception("Failed to create channel_playlist_mapping table: " . $conn->error);
    }
    
    // Create video_playlists table
    $sql2 = "CREATE TABLE IF NOT EXISTS video_playlists (
        id INT PRIMARY KEY AUTO_INCREMENT,
        video_id INT NOT NULL,
        playlist_id VARCHAR(50) NOT NULL,
        playlist_name VARCHAR(255),
        channel_sync_id INT,
        position INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
        FOREIGN KEY (channel_sync_id) REFERENCES channel_sync(id) ON DELETE SET NULL,
        INDEX idx_video_playlist (video_id, playlist_id),
        INDEX idx_playlist_videos (playlist_id)
    )";
    
    if ($conn->query($sql2)) {
        echo "✓ video_playlists table created successfully\n";
    } else {
        throw new Exception("Failed to create video_playlists table: " . $conn->error);
    }
    
    // Create video_category_override table
    $sql3 = "CREATE TABLE IF NOT EXISTS video_category_override (
        id INT PRIMARY KEY AUTO_INCREMENT,
        video_id INT NOT NULL,
        original_category_id INT,
        override_category_id INT NOT NULL,
        reason TEXT,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
        FOREIGN KEY (original_category_id) REFERENCES categories(id) ON DELETE SET NULL,
        FOREIGN KEY (override_category_id) REFERENCES categories(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
        UNIQUE KEY unique_video_override (video_id)
    )";
    
    if ($conn->query($sql3)) {
        echo "✓ video_category_override table created successfully\n";
    } else {
        throw new Exception("Failed to create video_category_override table: " . $conn->error);
    }
    
    echo "\n=== Database Update Complete ===\n";
    echo "You can now use the playlist mapping feature in the admin panel.\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>