<?php
/**
 * Database schema verification for playlist mapping
 */

require_once __DIR__ . '/config/database.php';

try {
    $conn = getDBConnection();
    
    echo "<h2>Database Schema Verification</h2>\n";
    
    // Check required tables
    $requiredTables = [
        'channel_playlist_mapping',
        'video_playlists', 
        'video_category_override',
        'channel_sync'
    ];
    
    foreach ($requiredTables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            echo "✓ Table <strong>$table</strong> exists<br>\n";
            
            // Show table structure
            $structure = $conn->query("DESCRIBE $table");
            if ($structure) {
                echo "<div style='margin-left: 20px; font-size: 0.9em;'>";
                while ($row = $structure->fetch_assoc()) {
                    echo $row['Field'] . " (" . $row['Type'] . ")<br>\n";
                }
                echo "</div>";
            }
        } else {
            echo "✗ Table <strong>$table</strong> missing<br>\n";
        }
        echo "<br>\n";
    }
    
    // Check if we have any channel sync data
    $result = $conn->query("SELECT COUNT(*) as count FROM channel_sync");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "Channel sync records: " . $row['count'] . "<br>\n";
    }
    
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "<br>\n";
}
?>