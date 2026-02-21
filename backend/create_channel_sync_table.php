    <?php
/**
 * Create Channel Synchronization Table
 * Sets up database structure for automatic YouTube channel monitoring
 */

require_once 'config/database.php';

try {
    $conn = getDBConnection();
    
    // Create channel_sync table
    $sql = "CREATE TABLE IF NOT EXISTS channel_sync (
        id INT PRIMARY KEY AUTO_INCREMENT,
        channel_id VARCHAR(50) NOT NULL,
        channel_name VARCHAR(255),
        category_id INT NOT NULL,
        last_sync TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        sync_frequency ENUM('hourly', 'daily', 'weekly') DEFAULT 'daily',
        is_active BOOLEAN DEFAULT TRUE,
        auto_import BOOLEAN DEFAULT TRUE,
        require_approval BOOLEAN DEFAULT FALSE,
        max_videos_per_sync INT DEFAULT 10,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
        INDEX idx_channel_id (channel_id),
        INDEX idx_active (is_active),
        INDEX idx_last_sync (last_sync)
    )";
    
    if ($conn->query($sql)) {
        echo "✓ Channel sync table created successfully\n";
    } else {
        throw new Exception("Failed to create channel_sync table: " . $conn->error);
    }
    
    // Create sync_log table for tracking synchronization history
    $sql = "CREATE TABLE IF NOT EXISTS sync_log (
        id INT PRIMARY KEY AUTO_INCREMENT,
        channel_sync_id INT NOT NULL,
        status ENUM('success', 'partial', 'failed') NOT NULL,
        videos_found INT DEFAULT 0,
        videos_imported INT DEFAULT 0,
        videos_skipped INT DEFAULT 0,
        error_message TEXT,
        started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        completed_at TIMESTAMP NULL,
        FOREIGN KEY (channel_sync_id) REFERENCES channel_sync(id) ON DELETE CASCADE,
        INDEX idx_channel_sync (channel_sync_id),
        INDEX idx_status (status),
        INDEX idx_started_at (started_at)
    )";
    
    if ($conn->query($sql)) {
        echo "✓ Sync log table created successfully\n";
    } else {
        throw new Exception("Failed to create sync_log table: " . $conn->error);
    }
    
    // Add sample channel sync configuration (optional)
    $sampleChannels = [
        ['UC4QobU6STFB0P71PMvOGN5A', 'Sample Church Channel', 4, 'daily', true, false, 5]
    ];
    
    foreach ($sampleChannels as $channel) {
        $stmt = $conn->prepare("INSERT IGNORE INTO channel_sync 
            (channel_id, channel_name, category_id, sync_frequency, auto_import, require_approval, max_videos_per_sync) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssissii", ...$channel);
        $stmt->execute();
    }
    
    echo "✓ Database setup completed successfully\n";
    echo "You can now configure automatic channel synchronization through the admin panel.\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>