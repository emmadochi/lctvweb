<?php
/**
 * Force import videos from channel regardless of last sync date
 */
require_once 'services/ChannelSyncService.php';
require_once 'config/database.php';

echo "=== FORCE VIDEO IMPORT ===\n\n";

try {
    $syncService = new ChannelSyncService();
    $conn = getDBConnection();
    
    // Get active channels
    $result = $conn->query("SELECT * FROM channel_sync WHERE is_active = 1");
    $channels = [];
    while ($row = $result->fetch_assoc()) {
        $channels[] = $row;
    }
    
    if (empty($channels)) {
        echo "No active channels found!\n";
        exit(1);
    }
    
    echo "Found " . count($channels) . " active channels:\n";
    foreach ($channels as $channel) {
        echo "- {$channel['channel_name']} ({$channel['channel_id']})\n";
    }
    echo "\n";
    
    // Force import from each channel
    foreach ($channels as $channel) {
        echo "Importing videos from: {$channel['channel_name']}\n";
        echo str_repeat("-", 50) . "\n";
        
        // Get videos without date filtering
        $videos = getChannelVideosWithoutDateFilter($channel['channel_id'], $channel['max_videos_per_sync']);
        
        if (empty($videos)) {
            echo "No videos found for this channel\n\n";
            continue;
        }
        
        echo "Found " . count($videos) . " videos:\n";
        $imported = 0;
        $skipped = 0;
        
        foreach ($videos as $video) {
            // Check if video already exists
            $stmt = $conn->prepare("SELECT id FROM videos WHERE youtube_id = ?");
            $stmt->bind_param("s", $video['youtube_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                echo "SKIP: {$video['title']} (already exists)\n";
                $skipped++;
                continue;
            }
            
            // Import the video
            $isActive = $channel['auto_import'] && !$channel['require_approval'];
            
            $videoData = [
                'youtube_id' => $video['youtube_id'],
                'title' => substr($video['title'], 0, 255),
                'description' => substr($video['description'] ?? '', 0, 500),
                'thumbnail_url' => $video['thumbnail_url'],
                'duration' => $video['duration'] ?? 0,
                'category_id' => $channel['category_id'],
                'tags' => json_encode($video['tags'] ?? []),
                'channel_title' => substr($video['channel_title'] ?? '', 0, 100),
                'channel_id' => $video['channel_id'],
                'view_count' => $video['view_count'] ?? 0,
                'like_count' => $video['like_count'] ?? 0,
                'published_at' => date('Y-m-d H:i:s', strtotime($video['published_at'])),
                'is_active' => $isActive ? 1 : 0
            ];
            
            $stmt = $conn->prepare("INSERT INTO videos 
                (youtube_id, title, description, thumbnail_url, duration, category_id, tags, 
                 channel_title, channel_id, view_count, like_count, published_at, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->bind_param("sssssisssiiis",
                $videoData['youtube_id'],
                $videoData['title'],
                $videoData['description'],
                $videoData['thumbnail_url'],
                $videoData['duration'],
                $videoData['category_id'],
                $videoData['tags'],
                $videoData['channel_title'],
                $videoData['channel_id'],
                $videoData['view_count'],
                $videoData['like_count'],
                $videoData['published_at'],
                $videoData['is_active']
            );
            
            if ($stmt->execute()) {
                echo "✓ IMPORTED: {$video['title']}\n";
                $imported++;
            } else {
                echo "✗ FAILED: {$video['title']} - " . $stmt->error . "\n";
            }
        }
        
        echo "\nImport Summary for {$channel['channel_name']}:\n";
        echo "Imported: $imported | Skipped: $skipped\n\n";
        
        // Update last sync time
        $stmt = $conn->prepare("UPDATE channel_sync SET last_sync = NOW() WHERE id = ?");
        $stmt->bind_param("i", $channel['id']);
        $stmt->execute();
    }
    
    echo "=== IMPORT COMPLETE ===\n";
    echo "Check your videos at: http://localhost/LCMTVWebNew/frontend/\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

function getChannelVideosWithoutDateFilter($channelId, $maxVideos = 10) {
    require_once 'utils/YouTubeAPI.php';
    
    $youtubeAPI = new YouTubeAPI();
    
    try {
        // Search for videos from this channel
        $searchResults = $youtubeAPI->searchVideos(
            'channel:' . $channelId, 
            $maxVideos, 
            'date'
        );
        
        if (empty($searchResults)) {
            return [];
        }
        
        // Get detailed video information
        $videoIds = array_column($searchResults, 'youtube_id');
        $detailedVideos = $youtubeAPI->getVideoDetails($videoIds);
        
        return $detailedVideos;
        
    } catch (Exception $e) {
        echo "YouTube API Error: " . $e->getMessage() . "\n";
        return [];
    }
}
?>