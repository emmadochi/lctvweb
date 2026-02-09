<?php
/**
 * Detailed YouTube API Test for Specific Video
 */
require_once __DIR__ . '/utils/YouTubeAPI.php';

echo "=== Detailed YouTube API Test ===\n\n";

$youtube = new YouTubeAPI();
$apiKey = $youtube->getApiKey();

echo "API Key: " . substr($apiKey, 0, 15) . "...\n\n";

// Test the specific video from the screenshot
$videoId = 'R1KfiyJ0c9U';
echo "Testing video ID: $videoId\n";

// Also test with a known working video
$workingVideoId = 'dQw4w9WgXcQ'; // Rick Astley - Never Gonna Give You Up
echo "Also testing known video ID: $workingVideoId\n\n";

try {
    echo "1. Testing direct API call...\n";
    $testUrl = "https://www.googleapis.com/youtube/v3/videos?id={$videoId}&key={$apiKey}&part=snippet,statistics,contentDetails";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 15,
            'user_agent' => 'LCMTV/1.0',
            'header' => 'Accept: application/json'
        ]
    ]);
    
    $response = file_get_contents($testUrl, false, $context);
    
    if ($response !== false) {
        $data = json_decode($response, true);
        echo "   Response received\n";
        
        if (isset($data['error'])) {
            echo "   ✗ API Error: " . $data['error']['message'] . "\n";
            if (isset($data['error']['errors'][0])) {
                echo "     Reason: " . $data['error']['errors'][0]['reason'] . "\n";
                echo "     Domain: " . $data['error']['errors'][0]['domain'] . "\n";
            }
        } else {
            echo "   ✓ Success! Response structure:\n";
            echo "     Items count: " . count($data['items']) . "\n";
            if (!empty($data['items'])) {
                $item = $data['items'][0];
                echo "     Title: " . $item['snippet']['title'] . "\n";
                echo "     Channel: " . $item['snippet']['channelTitle'] . "\n";
                echo "     Published: " . $item['snippet']['publishedAt'] . "\n";
                echo "     View count: " . ($item['statistics']['viewCount'] ?? 'N/A') . "\n";
            }
        }
    } else {
        $error = error_get_last();
        echo "   ✗ Failed to get response\n";
        echo "     Error: " . ($error['message'] ?? 'Unknown') . "\n";
    }
    
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

echo "\n2. Testing through YouTubeAPI class...\n";
try {
    $videoDetails = $youtube->getVideoDetails([$videoId]);
    if (!empty($videoDetails)) {
        echo "   ✓ SUCCESS: Video details retrieved\n";
        $video = $videoDetails[0];
        echo "   Title: " . $video['title'] . "\n";
        echo "   Channel: " . $video['channel_title'] . "\n";
        echo "   Views: " . number_format($video['view_count']) . "\n";
        echo "   Duration: " . gmdate("H:i:s", $video['duration']) . "\n";
    } else {
        echo "   ✗ FAILED: No video details returned\n";
    }
} catch (Exception $e) {
    echo "   ✗ Exception: " . $e->getMessage() . "\n";
}

echo "\n4. Testing known working video...\n";
try {
    $workingVideoDetails = $youtube->getVideoDetails([$workingVideoId]);
    if (!empty($workingVideoDetails)) {
        echo "   ✓ SUCCESS: Known video details retrieved\n";
        $video = $workingVideoDetails[0];
        echo "   Title: " . $video['title'] . "\n";
        echo "   Channel: " . $video['channel_title'] . "\n";
        echo "   Views: " . number_format($video['view_count']) . "\n";
    } else {
        echo "   ✗ FAILED: Known video not found\n";
    }
} catch (Exception $e) {
    echo "   ✗ Exception: " . $e->getMessage() . "\n";
}

echo "\n=== Test Completed ===\n";
?>