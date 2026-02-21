<?php
/**
 * Comprehensive test for the AJAX playlist functionality
 * Run this after logging into admin panel
 */

session_start();

// Check authentication first
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required. Please login to admin panel first.',
        'test_results' => [
            'session_status' => 'NOT_LOGGED_IN',
            'session_id' => session_id()
        ]
    ]);
    exit();
}

// Test results array
$results = [
    'session_status' => 'LOGGED_IN',
    'session_id' => session_id(),
    'admin_email' => $_SESSION['admin_email'] ?? 'N/A',
    'admin_id' => $_SESSION['admin_id'] ?? 'N/A'
];

try {
    // Test 1: Database connection and required tables
    require_once __DIR__ . '/../config/database.php';
    $conn = getDBConnection();
    $results['database_connection'] = 'SUCCESS';
    
    $requiredTables = ['channel_playlist_mapping', 'video_playlists', 'video_category_override', 'channel_sync'];
    $tableStatus = [];
    
    foreach ($requiredTables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        $tableStatus[$table] = $result && $result->num_rows > 0 ? 'EXISTS' : 'MISSING';
    }
    $results['tables'] = $tableStatus;
    
    // Test 2: YouTube API
    require_once __DIR__ . '/../utils/YouTubeAPI.php';
    $youtubeAPI = new YouTubeAPI();
    $results['youtube_api'] = 'INSTANTIATED';
    
    // Test with a known working channel
    $testChannelId = 'UC_x5XG1OV2P6uZZ5FSM9Ttw'; // YouTube's channel
    $playlists = $youtubeAPI->getChannelPlaylists($testChannelId, 3);
    $results['api_test'] = [
        'playlists_found' => count($playlists),
        'first_playlist' => !empty($playlists) ? $playlists[0]['title'] : 'None'
    ];
    
    // Test 3: ChannelSyncService
    require_once __DIR__ . '/../services/ChannelSyncService.php';
    $channelSyncService = new ChannelSyncService();
    $results['channel_sync_service'] = 'INSTANTIATED';
    
    // Test getChannelPlaylistsForMapping
    $mappingPlaylists = $channelSyncService->getChannelPlaylistsForMapping($testChannelId);
    $results['mapping_test'] = [
        'playlists_with_videos' => count($mappingPlaylists),
        'first_playlist_videos' => !empty($mappingPlaylists) ? count($mappingPlaylists[0]['videos'] ?? []) : 0
    ];
    
    // Test 4: AJAX endpoint simulation
    $testChannelId = 'UC_x5XG1OV2P6uZZ5FSM9Ttw';
    $testChannelSyncId = 1; // Test with a dummy ID
    
    $ajaxPlaylists = $youtubeAPI->getChannelPlaylistsWithVideos($testChannelId, 5, 3);
    $ajaxMappings = $channelSyncService->getPlaylistMappings($testChannelSyncId);
    
    $results['ajax_simulation'] = [
        'playlists' => count($ajaxPlaylists),
        'mappings' => count($ajaxMappings),
        'first_playlist_name' => !empty($ajaxPlaylists) ? $ajaxPlaylists[0]['title'] : 'None'
    ];
    
    // Overall success
    $allTablesExist = array_reduce($tableStatus, function($carry, $status) {
        return $carry && $status === 'EXISTS';
    }, true);
    
    $success = $allTablesExist && 
               count($playlists) > 0 && 
               isset($playlists[0]['title']);
    
    echo json_encode([
        'success' => $success,
        'message' => $success ? 'All tests passed!' : 'Some tests failed - check results below',
        'test_results' => $results
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Test failed: ' . $e->getMessage(),
        'test_results' => array_merge($results, [
            'error' => $e->getMessage(),
            'error_file' => $e->getFile(),
            'error_line' => $e->getLine()
        ])
    ]);
}
?>