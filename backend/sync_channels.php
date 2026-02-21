<?php
/**
 * Automatic Channel Synchronization Script
 * Run this via cron job for automatic YouTube channel monitoring
 * 
 * Cron setup examples:
 * 
 * Hourly synchronization:
 * 0 * * * * /usr/bin/php /path/to/your/project/backend/sync_channels.php
 * 
 * Daily synchronization at 2 AM:
 * 0 2 * * * /usr/bin/php /path/to/your/project/backend/sync_channels.php
 */

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Include required files
require_once __DIR__ . '/services/ChannelSyncService.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/utils/YouTubeAPI.php';

try {
    echo "=== LCMTV Channel Synchronization Started ===\n";
    echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";
    
    // Initialize the sync service
    $syncService = new ChannelSyncService();
    
    // Run synchronization for all active channels
    $results = $syncService->runAllSync();
    
    // Output results
    echo "\n=== Synchronization Results ===\n";
    $totalImported = 0;
    $totalSkipped = 0;
    $failedChannels = 0;
    
    foreach ($results as $result) {
        echo "Channel: {$result['channel']}\n";
        echo "Status: {$result['status']}\n";
        
        if (isset($result['imported'])) {
            echo "Imported: {$result['imported']}\n";
            $totalImported += $result['imported'];
        }
        
        if (isset($result['skipped'])) {
            echo "Skipped: {$result['skipped']}\n";
            $totalSkipped += $result['skipped'];
        }
        
        if (isset($result['error'])) {
            echo "Error: {$result['error']}\n";
            $failedChannels++;
        }
        
        echo "---\n";
    }
    
    echo "\n=== Summary ===\n";
    echo "Total channels processed: " . count($results) . "\n";
    echo "Successfully synchronized: " . (count($results) - $failedChannels) . "\n";
    echo "Failed channels: $failedChannels\n";
    echo "Total videos imported: $totalImported\n";
    echo "Total videos skipped: $totalSkipped\n";
    echo "Completion time: " . date('Y-m-d H:i:s') . "\n";
    
    echo "\n=== LCMTV Channel Synchronization Completed ===\n";
    
    // Exit with success code
    exit(0);
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    error_log("Channel sync cron error: " . $e->getMessage());
    
    // Exit with error code
    exit(1);
}
?>