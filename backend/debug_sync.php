<?php
require_once 'config/database.php';
require_once 'utils/YouTubeAPI.php';

echo "=== Debug Channel Sync Issue ===\n\n";

// Test the specific channel ID
$channelId = 'UC4QobU6STFB0P71PMvOGN5A'; // Legacy format
echo "Testing channel ID: $channelId\n";

try {
    $yt = new YouTubeAPI();
    
    // Test getting channel videos
    echo "\n1. Testing getChannelVideos...\n";
    $videos = $yt->getChannelVideos($channelId, 10);
    echo "Videos returned: " . count($videos) . "\n";
    
    foreach($videos as $index => $video) {
        echo ($index + 1) . ". Title: " . $video['title'] . "\n";
        echo "   ID: " . $video['youtube_id'] . "\n";
        echo "   Duration: " . $video['duration'] . "\n";
        echo "   ---\n";
    }
    
    // Test the database insertion
    echo "\n2. Testing database insertion...\n";
    $conn = getDBConnection();
    
    if (!empty($videos)) {
        $video = $videos[0];
        $categoryId = 1; // Test with category 1
        
        echo "Attempting to insert video: " . $video['title'] . "\n";
        
        // Check if video already exists
        $stmt = $conn->prepare("SELECT id FROM videos WHERE youtube_id = ?");
        $stmt->bind_param("s", $video['youtube_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo "Video already exists in database\n";
        } else {
            echo "Video does not exist, attempting insertion...\n";
            
            // Insert new video with proper parameter binding
            $stmt = $conn->prepare("INSERT INTO videos (youtube_id, title, description, thumbnail_url, duration, category_id, created_at, is_active) VALUES (?, ?, ?, ?, ?, ?, NOW(), 1)");
            
            // Debug the parameters
            echo "Parameters being bound:\n";
            echo "youtube_id: " . $video['youtube_id'] . "\n";
            echo "title: " . substr($video['title'], 0, 50) . "...\n";
            echo "description: " . substr($video['description'], 0, 50) . "...\n";
            echo "thumbnail_url: " . $video['thumbnail_url'] . "\n";
            echo "duration: " . $video['duration'] . "\n";
            echo "category_id: " . $categoryId . "\n";
            
            $stmt->bind_param("sssssi", $video['youtube_id'], $video['title'], $video['description'], $video['thumbnail_url'], $video['duration'], $categoryId);
            
            if ($stmt->execute()) {
                echo "✓ Video inserted successfully!\n";
                $insertId = $conn->insert_id;
                echo "Inserted video ID: $insertId\n";
                
                // Verify the insertion
                $verifyStmt = $conn->prepare("SELECT id, title, is_active FROM videos WHERE id = ?");
                $verifyStmt->bind_param("i", $insertId);
                $verifyStmt->execute();
                $verifyResult = $verifyStmt->get_result();
                $verifyRow = $verifyResult->fetch_assoc();
                
                echo "Verification - ID: " . $verifyRow['id'] . ", Title: " . $verifyRow['title'] . ", Active: " . $verifyRow['is_active'] . "\n";
            } else {
                echo "✗ Failed to insert video\n";
                echo "Error: " . $stmt->error . "\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== Debug Complete ===\n";
?>