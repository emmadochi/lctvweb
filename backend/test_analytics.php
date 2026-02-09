<?php
/**
 * Test Analytics Data
 * Adds sample analytics data for testing
 */

require_once 'config/database.php';
require_once 'models/PageView.php';
require_once 'models/VideoView.php';

echo "Adding test analytics data...\n\n";

// Sample page view data
$pageViews = [
    [
        'user_id' => null,
        'session_id' => 'test_session_1',
        'page_url' => '/',
        'page_title' => 'LCMTV - Modern TV Platform',
        'referrer_url' => '',
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'ip_address' => '127.0.0.1',
        'country' => 'US',
        'city' => 'New York',
        'device_type' => 'desktop',
        'browser' => 'Chrome',
        'os' => 'Windows',
        'screen_resolution' => '1920x1080',
        'time_on_page' => 120
    ],
    [
        'user_id' => null,
        'session_id' => 'test_session_1',
        'page_url' => '/categories',
        'page_title' => 'Categories - LCMTV',
        'referrer_url' => '/',
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'ip_address' => '127.0.0.1',
        'country' => 'US',
        'city' => 'New York',
        'device_type' => 'desktop',
        'browser' => 'Chrome',
        'os' => 'Windows',
        'screen_resolution' => '1920x1080',
        'time_on_page' => 85
    ],
    [
        'user_id' => null,
        'session_id' => 'test_session_2',
        'page_url' => '/',
        'page_title' => 'LCMTV - Modern TV Platform',
        'referrer_url' => 'https://google.com',
        'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) AppleWebKit/605.1.15',
        'ip_address' => '127.0.0.1',
        'country' => 'US',
        'city' => 'Los Angeles',
        'device_type' => 'mobile',
        'browser' => 'Safari',
        'os' => 'iOS',
        'screen_resolution' => '375x667',
        'time_on_page' => 95
    ]
];

// Sample video view data
$videoViews = [
    [
        'video_id' => 1,
        'user_id' => null,
        'session_id' => 'test_session_1',
        'youtube_id' => 'dQw4w9WgXcQ',
        'watch_duration' => 180,
        'total_duration' => 180,
        'watch_percentage' => 100.0,
        'quality' => '720p',
        'playback_speed' => 1.0,
        'referrer_url' => '/',
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'ip_address' => '127.0.0.1',
        'device_type' => 'desktop',
        'browser' => 'Chrome',
        'os' => 'Windows',
        'country' => 'US',
        'city' => 'New York'
    ],
    [
        'video_id' => 2,
        'user_id' => null,
        'session_id' => 'test_session_2',
        'youtube_id' => '9bZkp7q19f0',
        'watch_duration' => 120,
        'total_duration' => 253,
        'watch_percentage' => 47.4,
        'quality' => '480p',
        'playback_speed' => 1.0,
        'referrer_url' => '/',
        'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) AppleWebKit/605.1.15',
        'ip_address' => '127.0.0.1',
        'device_type' => 'mobile',
        'browser' => 'Safari',
        'os' => 'iOS',
        'country' => 'US',
        'city' => 'Los Angeles'
    ]
];

try {
    // Insert page views
    echo "Inserting page view data...\n";
    foreach ($pageViews as $pageView) {
        if (PageView::record($pageView)) {
            echo "✓ Page view recorded: {$pageView['page_url']}\n";
        } else {
            echo "✗ Failed to record page view: {$pageView['page_url']}\n";
        }
    }

    // Insert video views
    echo "\nInserting video view data...\n";
    foreach ($videoViews as $videoView) {
        if (VideoView::record($videoView)) {
            echo "✓ Video view recorded: {$videoView['youtube_id']}\n";
        } else {
            echo "✗ Failed to record video view: {$videoView['youtube_id']}\n";
        }
    }

    echo "\n✓ Test analytics data added successfully!\n";
    echo "You can now view the analytics dashboard at: http://localhost/lcmtvweb/backend/admin/\n";

} catch (Exception $e) {
    echo "\n✗ Error adding test data: " . $e->getMessage() . "\n";
}
?>
