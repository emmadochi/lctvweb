<?php
require_once 'utils/YouTubeAPI.php';

echo "=== Testing Channel ID Formats ===\n\n";

$api = new YouTubeAPI();

// Test different formats
$testIds = [
    '@ComeLetsWorship1',
    'ComeLetsWorship1',
    'UC' . substr(md5('ComeLetsWorship1'), 0, 22) // This is just a guess
];

foreach ($testIds as $channelId) {
    echo "Testing: channel:$channelId\n";
    try {
        $videos = $api->searchVideos("channel:$channelId", 3, 'date');
        echo "Found: " . count($videos) . " videos\n";
        if (!empty($videos)) {
            foreach ($videos as $video) {
                echo "  - {$video['title']}\n";
            }
            echo "✓ SUCCESS with format: channel:$channelId\n";
            break;
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}
?>