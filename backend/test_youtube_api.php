<?php
require_once 'utils/YouTubeAPI.php';

try {
    $yt = new YouTubeAPI();
    echo "Testing YouTube API connection...\n";
    
    $videos = $yt->getChannelVideos('UC4QobU6STFB0P71PMvOGN5A', 5);
    echo 'Videos found: ' . count($videos) . "\n";
    
    foreach($videos as $video) {
        echo 'Title: ' . $video['title'] . ' | ID: ' . $video['youtube_id'] . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>