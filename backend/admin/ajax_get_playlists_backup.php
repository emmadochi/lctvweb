<?php
/**
 * AJAX endpoint for fetching YouTube channel playlists
 * Used by the admin interface for playlist mapping configuration
 */

// Ensure session is started (it should be from the main admin page)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    error_log("AJAX Request - Started new session: " . session_id());
} else {
    error_log("AJAX Request - Using existing session: " . session_id());
}

// Debug: Log session data
error_log("AJAX Request - Session data: " . print_r($_SESSION, true));
error_log("AJAX Request - Cookies: " . print_r($_COOKIE, true));

// Set JSON header
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    error_log("AJAX Request - Authentication failed for session: " . session_id());
    error_log("AJAX Request - Session admin_logged_in value: " . ($_SESSION['admin_logged_in'] ?? 'NOT SET'));
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

error_log("AJAX Request - Authentication passed for session: " . session_id());

// Buffer output to prevent any HTML from being sent
ob_start();

try {
    // Include required files
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../utils/YouTubeAPI.php';
    require_once __DIR__ . '/../services/ChannelSyncService.php';
    
    $channelId = $_GET['channel_id'] ?? '';
    $channelSyncId = (int)($_GET['channel_sync_id'] ?? 0);
    
    if (empty($channelId)) {
        throw new Exception('Channel ID is required');
    }
    
    $youtubeAPI = new YouTubeAPI();
    $channelSyncService = new ChannelSyncService();
    
    // Get playlists
    $playlists = $youtubeAPI->getChannelPlaylistsWithVideos($channelId, 50, 5);
    
    // Get existing mappings for this channel
    $mappings = [];
    if ($channelSyncId > 0) {
        $mappings = $channelSyncService->getPlaylistMappings($channelSyncId);
    }
    
    // Clear any output buffer content
    ob_end_clean();
    
    // Send successful response
    echo json_encode([
        'success' => true,
        'playlists' => $playlists,
        'mappings' => $mappings
    ]);
    
} catch (Exception $e) {
    // Clear output buffer
    ob_end_clean();
    
    error_log("AJAX Playlist Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>