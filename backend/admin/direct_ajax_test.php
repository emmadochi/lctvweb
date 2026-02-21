<?php
/**
 * Direct test of the AJAX endpoint without session requirements
 * This simulates what the AJAX call does but bypasses authentication for testing
 */

// Simulate admin session for testing
session_start();
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_email'] = 'test@example.com';
$_SESSION['admin_id'] = 1;

// Set test parameters
$_GET['channel_id'] = 'UC_x5XG1OV2P6uZZ5FSM9Ttw'; // YouTube's official channel
$_GET['channel_sync_id'] = 1;

echo "<h2>Direct AJAX Endpoint Test</h2>\n";
echo "<p>Testing with channel ID: " . $_GET['channel_id'] . "</p>\n";

// Include the AJAX endpoint code directly
try {
    // Session handling
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Set JSON header
    header('Content-Type: application/json');

    // Authentication check (should pass with our simulated session)
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        throw new Exception('Authentication required');
    }

    // Input validation
    $channelId = trim($_GET['channel_id'] ?? '');
    $channelSyncId = (int)($_GET['channel_sync_id'] ?? 0);

    if (empty($channelId)) {
        throw new Exception('Channel ID is required');
    }

    // Start output buffering
    ob_start();

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
    echo "<p>Fetching playlists...</p>\n";
    $playlists = $youtubeAPI->getChannelPlaylistsWithVideos($channelId, 50, 5);
    echo "<p>Found " . count($playlists) . " playlists</p>\n";
    
    // Fetch existing mappings
    $mappings = [];
    if ($channelSyncId > 0) {
        echo "<p>Fetching mappings for channel sync ID: $channelSyncId</p>\n";
        $mappings = $channelSyncService->getPlaylistMappings($channelSyncId);
        echo "<p>Found " . count($mappings) . " mappings</p>\n";
    }
    
    // Clear output buffer and send response
    ob_end_clean();
    
    $response = [
        'success' => true,
        'playlists' => $playlists,
        'mappings' => $mappings
    ];
    
    echo "<h3>Success! Response:</h3>\n";
    echo "<pre>" . json_encode($response, JSON_PRETTY_PRINT) . "</pre>\n";
    
} catch (Exception $e) {
    ob_end_clean();
    echo "<h3>Error:</h3>\n";
    echo "<p><strong>Message:</strong> " . $e->getMessage() . "</p>\n";
    echo "<p><strong>File:</strong> " . $e->getFile() . "</p>\n";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>\n";
    
    // Also show the JSON response that would be sent
    http_response_code(500);
    $errorResponse = [
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => $e->getCode()
    ];
    echo "<h4>JSON Error Response:</h4>\n";
    echo "<pre>" . json_encode($errorResponse, JSON_PRETTY_PRINT) . "</pre>\n";
} catch (Error $e) {
    ob_end_clean();
    echo "<h3>Fatal Error:</h3>\n";
    echo "<p><strong>Message:</strong> " . $e->getMessage() . "</p>\n";
    echo "<p><strong>File:</strong> " . $e->getFile() . "</p>\n";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>\n";
    
    http_response_code(500);
    $errorResponse = [
        'success' => false,
        'message' => 'Internal server error occurred'
    ];
    echo "<pre>" . json_encode($errorResponse, JSON_PRETTY_PRINT) . "</pre>\n";
}
?>