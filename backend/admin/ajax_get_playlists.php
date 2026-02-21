<?php
/**
 * AJAX endpoint for fetching YouTube channel playlists
 * Used by the admin interface for playlist mapping configuration
 */

// Session handling - use existing session from admin panel
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON header early
header('Content-Type: application/json');

// Authentication check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'message' => 'Authentication required. Please login to admin panel.'
    ]);
    exit();
}

// Input validation
$channelId = trim($_GET['channel_id'] ?? '');
$channelSyncId = (int)($_GET['channel_sync_id'] ?? 0);

if (empty($channelId)) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Channel ID is required'
    ]);
    exit();
}

// Start output buffering
ob_start();

try {
    // Load dependencies
    $requiredFiles = [
        __DIR__ . '/../config/database.php',
        __DIR__ . '/../utils/YouTubeAPI.php',
        __DIR__ . '/../services/ChannelSyncService.php'
    ];
    
    foreach ($requiredFiles as $file) {
        if (!file_exists($file)) {
            throw new Exception("Required file not found: " . basename($file));
        }
        require_once $file;
    }
    
    // Initialize services
    $youtubeAPI = new YouTubeAPI();
    $channelSyncService = new ChannelSyncService();
    
    // Clear any output from class loading
    if (ob_get_length()) {
        ob_clean();
    }
    
    // Fetch playlists
    $playlists = $youtubeAPI->getChannelPlaylistsWithVideos($channelId, 50, 5);
    
    // Fetch existing mappings
    $mappings = [];
    if ($channelSyncId > 0) {
        $mappings = $channelSyncService->getPlaylistMappings($channelSyncId);
    }
    
    // Clear output buffer and send response
    ob_end_clean();
    
    echo json_encode([
        'success' => true,
        'playlists' => $playlists,
        'mappings' => $mappings
    ]);
    
} catch (Exception $e) {
    // Clear output buffer
    ob_end_clean();
    
    // Log error
    error_log("AJAX Playlist Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    // Send error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => $e->getCode()
    ]);
} catch (Error $e) {
    // Handle PHP fatal errors
    ob_end_clean();
    
    error_log("AJAX Playlist Fatal Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error occurred'
    ]);
}

