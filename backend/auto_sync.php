<?php
/**
 * Automatic Synchronization Runner
 * Run this script via cron to automatically sync channels and playlists
 * 
 * Example cron entry (run every hour):
 * 0 * * * * /usr/bin/php /path/to/backend/auto_sync.php
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output
ini_set('log_errors', 1);

// Change to script directory
chdir(__DIR__);

require_once 'config/database.php';
require_once 'services/ChannelSyncService.php';
require_once 'services/PlaylistSyncService.php';

echo "========================================\n";
echo "Auto Sync Started: " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n\n";

try {
    // Run channel synchronization
    echo "Running Channel Synchronization...\n";
    echo "----------------------------------------\n";
    $channelService = new ChannelSyncService();
    $channelResults = $channelService->runAllSync();
    
    foreach ($channelResults as $result) {
        if ($result['status'] === 'success') {
            echo "✓ {$result['channel']}: {$result['imported']} imported\n";
        } else {
            echo "✗ {$result['channel']}: {$result['status']}\n";
        }
    }
    
    echo "\n";
    
    // Run playlist synchronization
    echo "Running Playlist Synchronization...\n";
    echo "----------------------------------------\n";
    $playlistService = new PlaylistSyncService();
    $playlistResults = $playlistService->runAllSync();
    
    foreach ($playlistResults as $result) {
        if ($result['status'] === 'success') {
            echo "✓ {$result['playlist']}: {$result['imported']} imported\n";
        } else {
            echo "✗ {$result['playlist']}: {$result['status']}\n";
        }
    }
    
    echo "\n";
    echo "========================================\n";
    echo "Auto Sync Completed: " . date('Y-m-d H:i:s') . "\n";
    echo "========================================\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
   exit(1);
}
?>
