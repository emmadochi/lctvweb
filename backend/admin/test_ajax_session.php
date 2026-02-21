<?php
/**
 * Diagnostic test for AJAX session handling
 * Run this after logging into admin panel to verify session is working
 */

session_start();

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Not logged in',
        'session_id' => session_id(),
        'session_data' => $_SESSION
    ]);
    exit();
}

// Test YouTube API
try {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../utils/YouTubeAPI.php';
    require_once __DIR__ . '/../services/ChannelSyncService.php';
    
    $youtubeAPI = new YouTubeAPI();
    $channelSyncService = new ChannelSyncService();
    
    // Test with a known working channel
    $testChannelId = 'UC_x5XG1OV2P6uZZ5FSM9Ttw'; // YouTube's channel
    $playlists = $youtubeAPI->getChannelPlaylists($testChannelId, 3);
    
    echo json_encode([
        'success' => true,
        'message' => 'Session and API working correctly',
        'session_id' => session_id(),
        'session_data' => [
            'admin_logged_in' => $_SESSION['admin_logged_in'],
            'admin_email' => $_SESSION['admin_email'] ?? 'N/A',
            'admin_id' => $_SESSION['admin_id'] ?? 'N/A'
        ],
        'api_test' => [
            'playlists_found' => count($playlists),
            'first_playlist' => !empty($playlists) ? $playlists[0]['title'] : 'None'
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'API test failed: ' . $e->getMessage(),
        'session_id' => session_id(),
        'session_data' => $_SESSION
    ]);
}
?>