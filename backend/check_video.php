<?php
// Check if YouTube video exists
$videoId = 'R1KfiyJ0c9U';
$url = "https://www.youtube.com/watch?v=$videoId";

echo "Checking video: $videoId\n";
echo "URL: $url\n\n";

// Try to get headers
$context = stream_context_create([
    'http' => [
        'method' => 'HEAD',
        'timeout' => 10,
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ]
]);

$headers = @get_headers($url, 1, $context);

if ($headers) {
    echo "HTTP Status: " . $headers[0] . "\n";
    if (isset($headers['Location'])) {
        echo "Redirect Location: " . (is_array($headers['Location']) ? implode(', ', $headers['Location']) : $headers['Location']) . "\n";
    }
    
    // Check if it's a 404 or redirect to unavailable page
    $statusCode = explode(' ', $headers[0])[1];
    if ($statusCode == '404') {
        echo "✗ Video not found (404)\n";
    } elseif (strpos($headers[0], '301') !== false || strpos($headers[0], '302') !== false) {
        echo "! Video redirected (may be unavailable)\n";
    } else {
        echo "✓ Video appears to exist\n";
    }
} else {
    echo "✗ Could not retrieve headers\n";
}

echo "\nTrying oembed API...\n";
$oembedUrl = "https://www.youtube.com/oembed?url=" . urlencode($url) . "&format=json";
$oembedResponse = @file_get_contents($oembedUrl);

if ($oembedResponse) {
    $data = json_decode($oembedResponse, true);
    if ($data) {
        echo "✓ oEmbed success\n";
        echo "Title: " . $data['title'] . "\n";
        echo "Author: " . $data['author_name'] . "\n";
    }
} else {
    echo "✗ oEmbed failed - video likely doesn't exist or is private\n";
}

echo "\nTest completed.\n";
?>