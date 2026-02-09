<?php
/**
 * API Test Script
 * Tests basic API functionality
 */

echo "LCMTV API Test\n";
echo "==============\n\n";

$baseUrl = 'http://localhost/lcmtvweb/backend/api';

// Test 1: Home endpoint
echo "Testing Home API endpoint...\n";
$homeResponse = file_get_contents($baseUrl . '/');
if ($homeResponse) {
    $homeData = json_decode($homeResponse, true);
    if ($homeData && isset($homeData['success']) && $homeData['success']) {
        echo "✓ Home API working\n";
        echo "  - Categories: " . count($homeData['data']['categories']) . "\n";
        echo "  - Videos: " . count($homeData['data']['featured_videos']) . "\n";
    } else {
        echo "✗ Home API returned invalid response\n";
    }
} else {
    echo "✗ Home API not accessible\n";
}

// Test 2: Categories endpoint
echo "\nTesting Categories API endpoint...\n";
$categoriesResponse = file_get_contents($baseUrl . '/categories');
if ($categoriesResponse) {
    $categoriesData = json_decode($categoriesResponse, true);
    if ($categoriesData && isset($categoriesData['success']) && $categoriesData['success']) {
        echo "✓ Categories API working\n";
        echo "  - Found " . count($categoriesData['data']) . " categories\n";
    } else {
        echo "✗ Categories API returned invalid response\n";
    }
} else {
    echo "✗ Categories API not accessible\n";
}

// Test 3: Videos endpoint
echo "\nTesting Videos API endpoint...\n";
$videosResponse = file_get_contents($baseUrl . '/videos');
if ($videosResponse) {
    $videosData = json_decode($videosResponse, true);
    if ($videosData && isset($videosData['success']) && $videosData['success']) {
        echo "✓ Videos API working\n";
        echo "  - Found " . count($videosData['data']) . " videos\n";
    } else {
        echo "✗ Videos API returned invalid response\n";
    }
} else {
    echo "✗ Videos API not accessible\n";
}

echo "\nAPI Test Complete!\n";

if (function_exists('curl_version')) {
    echo "\nNote: For full API testing with POST requests, use a proper REST client like Postman.\n";
}
?>
