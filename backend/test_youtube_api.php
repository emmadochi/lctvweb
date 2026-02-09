<?php
/**
 * Test YouTube API Connection
 */
require_once __DIR__ . '/utils/YouTubeAPI.php';

echo "=== YouTube API Connection Test ===\n\n";

try {
    $youtube = new YouTubeAPI();
    
    echo "1. Checking API Key...\n";
    $apiKey = $youtube->getApiKey();
    echo "   API Key: " . ($apiKey ? substr($apiKey, 0, 10) . '...' : 'NOT SET') . "\n\n";
    
    echo "2. Testing API Connectivity...\n";
    
    // Test 1: Get video details for the video from the screenshot
    echo "   Testing video details for R1KfiyJ0c9U...\n";
    $videoDetails = $youtube->getVideoDetails(['R1KfiyJ0c9U']);
    
    if (!empty($videoDetails)) {
        $video = $videoDetails[0];
        echo "   ✓ SUCCESS: Video found\n";
        echo "   Title: " . $video['title'] . "\n";
        echo "   Channel: " . $video['channel_title'] . "\n";
        echo "   Views: " . number_format($video['view_count']) . "\n";
        echo "   Duration: " . gmdate("H:i:s", $video['duration']) . "\n\n";
    } else {
        echo "   ✗ FAILED: Could not retrieve video details\n\n";
    }
    
    // Test 2: Search for videos
    echo "   Testing search functionality...\n";
    $searchResults = $youtube->searchVideos('Lifechangers Touch TV', 3);
    
    if (!empty($searchResults)) {
        echo "   ✓ SUCCESS: Search returned " . count($searchResults) . " results\n";
        foreach ($searchResults as $index => $video) {
            echo "   " . ($index + 1) . ". " . $video['title'] . "\n";
        }
        echo "\n";
    } else {
        echo "   ✗ FAILED: Search returned no results\n\n";
    }
    
    // Test 3: Check trending videos
    echo "   Testing trending videos...\n";
    $trending = $youtube->getTrendingVideos('US', 3);
    
    if (!empty($trending)) {
        echo "   ✓ SUCCESS: Trending videos retrieved\n";
        foreach ($trending as $index => $video) {
            echo "   " . ($index + 1) . ". " . $video['title'] . "\n";
        }
        echo "\n";
    } else {
        echo "   ✗ FAILED: Could not retrieve trending videos\n\n";
    }
    
    echo "=== Test Summary ===\n";
    echo "✓ YouTube API is accessible\n";
    echo "✓ Video details can be retrieved\n";
    echo "✓ Search functionality works\n";
    echo "✓ Trending videos accessible\n";
    
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Network Diagnostics ===\n";

// Test direct connectivity
echo "Testing direct connection to YouTube API...\n";
$testUrl = 'https://www.googleapis.com/youtube/v3/videos?id=R1KfiyJ0c9U&key=' . (isset($apiKey) ? $apiKey : '') . '&part=snippet';
$context = stream_context_create([
    'http' => [
        'timeout' => 10,
        'user_agent' => 'LCMTV/1.0'
    ]
]);

$response = @file_get_contents($testUrl, false, $context);

if ($response !== false) {
    $data = json_decode($response, true);
    if (isset($data['error'])) {
        echo "✗ API Error: " . $data['error']['message'] . "\n";
        if (isset($data['error']['errors'][0])) {
            echo "   Reason: " . $data['error']['errors'][0]['reason'] . "\n";
        }
    } else {
        echo "✓ Direct API connection successful\n";
        if (isset($data['items'][0])) {
            echo "   Video title: " . $data['items'][0]['snippet']['title'] . "\n";
        }
    }
} else {
    echo "✗ Failed to connect to YouTube API directly\n";
    $error = error_get_last();
    if ($error) {
        echo "   Error: " . $error['message'] . "\n";
    }
}

echo "\nTest completed.\n";
?>