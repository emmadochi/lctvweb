<?php
/**
 * Channel Synchronization Service
 * Automatically imports videos from configured YouTube channels
 */

require_once __DIR__ . '/../utils/YouTubeAPI.php';
require_once __DIR__ . '/../models/Video.php';
require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../config/database.php';

class ChannelSyncService {
    private $youtubeAPI;
    private $conn;
    
    public function __construct() {
        $this->youtubeAPI = new YouTubeAPI();
        $this->conn = getDBConnection();
    }
    
    /**
     * Run synchronization for all active channels
     */
    public function runAllSync() {
        $channels = $this->getActiveChannels();
        $results = [];
        
        foreach ($channels as $channel) {
            $result = $this->syncChannel($channel);
            $results[] = $result;
        }
        
        return $results;
    }
    
    /**
     * Synchronize a specific channel
     */
    public function syncChannel($channelConfig) {
        $startTime = date('Y-m-d H:i:s');
        $logId = $this->createSyncLog($channelConfig['id']);
        
        try {
            echo "Starting sync for channel: {$channelConfig['channel_name']} (ID: {$channelConfig['channel_id']})\n";
            
            // Get new videos since last sync
            $newVideos = $this->getNewVideos($channelConfig);
            
            if (empty($newVideos)) {
                echo "No new videos found for channel {$channelConfig['channel_name']}\n";
                $this->updateSyncLog($logId, 'success', 0, 0, 0);
                $this->updateLastSync($channelConfig['id']);
                return ['channel' => $channelConfig['channel_name'], 'status' => 'no_new_videos', 'imported' => 0];
            }
            
            echo "Found " . count($newVideos) . " new videos\n";
            
            $imported = 0;
            $skipped = 0;
            
            // Limit videos per sync to prevent overwhelming the system
            $videosToProcess = array_slice($newVideos, 0, $channelConfig['max_videos_per_sync']);
            
            foreach ($videosToProcess as $videoData) {
                if ($this->importVideo($videoData, $channelConfig)) {
                    $imported++;
                } else {
                    $skipped++;
                }
            }
            
            // Update sync log
            $this->updateSyncLog($logId, 'success', count($videosToProcess), $imported, $skipped);
            $this->updateLastSync($channelConfig['id']);
            
            echo "Sync completed for {$channelConfig['channel_name']}: $imported imported, $skipped skipped\n";
            
            return [
                'channel' => $channelConfig['channel_name'],
                'status' => 'success',
                'found' => count($videosToProcess),
                'imported' => $imported,
                'skipped' => $skipped
            ];
            
        } catch (Exception $e) {
            echo "Error syncing channel {$channelConfig['channel_name']}: " . $e->getMessage() . "\n";
            $this->updateSyncLog($logId, 'failed', 0, 0, 0, $e->getMessage());
            return [
                'channel' => $channelConfig['channel_name'],
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get videos published since last sync
     */
    private function getNewVideos($channelConfig) {
        try {
            $lastSync = $channelConfig['last_sync'];
            
            // Search for videos from this channel published after last sync
            $searchResults = $this->youtubeAPI->searchVideos(
                'channel:' . $channelConfig['channel_id'], 
                $channelConfig['max_videos_per_sync'], 
                'date'
            );
            
            if (empty($searchResults)) {
                return [];
            }
            
            // Filter by date - only videos published after last sync
            $filteredVideos = [];
            $lastSyncTimestamp = strtotime($lastSync);
            
            foreach ($searchResults as $video) {
                $publishedTimestamp = strtotime($video['published_at']);
                if ($publishedTimestamp > $lastSyncTimestamp) {
                    $filteredVideos[] = $video;
                }
            }
            
            if (empty($filteredVideos)) {
                return [];
            }
            
            // Get detailed video information
            $videoIds = array_column($filteredVideos, 'youtube_id');
            $searchData = $this->youtubeAPI->getVideoDetails($videoIds);
            
            if (empty($searchData['items'])) {
                return [];
            }
            
            // Get detailed video information
            $videoIds = array_column($searchData['items'], 'id', 'videoId');
            $videoIdsList = array_keys($videoIds);
            
            $detailedVideos = $this->youtubeAPI->getVideoDetails($videoIdsList);
            
            return $detailedVideos;
            
        } catch (Exception $e) {
            error_log("Error getting new videos: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Import a single video
     */
    private function importVideo($videoData, $channelConfig) {
        try {
            // Check if video already exists
            $existingVideo = Video::getByYouTubeId($videoData['youtube_id']);
            if ($existingVideo) {
                echo "Video already exists: {$videoData['title']}\n";
                return false;
            }
            
            // Check if video should be auto-imported or require approval
            $isActive = $channelConfig['auto_import'] && !$channelConfig['require_approval'];
            
            // Prepare video data
            $videoInsertData = [
                'youtube_id' => $videoData['youtube_id'],
                'title' => $this->sanitizeString($videoData['title']),
                'description' => $this->sanitizeString($videoData['description']),
                'thumbnail_url' => $videoData['thumbnail_url'],
                'duration' => $videoData['duration'],
                'category_id' => $channelConfig['category_id'],
                'tags' => json_encode($videoData['tags'] ?: []),
                'channel_title' => $this->sanitizeString($videoData['channel_title']),
                'channel_id' => $videoData['channel_id'],
                'view_count' => $videoData['view_count'],
                'like_count' => $videoData['like_count'],
                'published_at' => date('Y-m-d H:i:s', strtotime($videoData['published_at'])),
                'is_active' => $isActive ? 1 : 0,
                'target_role' => $channelConfig['target_role'] ?? 'general'
            ];
            
            $result = Video::create($videoInsertData);
            
            if ($result) {
                echo "✓ Imported: {$videoData['title']}\n";
                return true;
            } else {
                echo "✗ Failed to import: {$videoData['title']}\n";
                return false;
            }
            
        } catch (Exception $e) {
            echo "✗ Error importing video: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Get all active channel configurations
     */
    private function getActiveChannels() {
        $sql = "SELECT * FROM channel_sync WHERE is_active = 1";
        $result = $this->conn->query($sql);
        
        $channels = [];
        while ($row = $result->fetch_assoc()) {
            $channels[] = $row;
        }
        
        return $channels;
    }
    
    /**
     * Create sync log entry
     */
    private function createSyncLog($channelSyncId) {
        $stmt = $this->conn->prepare("INSERT INTO sync_log (channel_sync_id, status) VALUES (?, 'started')");
        $stmt->bind_param("i", $channelSyncId);
        $stmt->execute();
        return $this->conn->insert_id;
    }
    
    /**
     * Update sync log with results
     */
    private function updateSyncLog($logId, $status, $found = 0, $imported = 0, $skipped = 0, $errorMessage = null) {
        $stmt = $this->conn->prepare("UPDATE sync_log SET 
            status = ?, 
            videos_found = ?, 
            videos_imported = ?, 
            videos_skipped = ?, 
            error_message = ?, 
            completed_at = NOW() 
            WHERE id = ?");
        $stmt->bind_param("siiisi", $status, $found, $imported, $skipped, $errorMessage, $logId);
        $stmt->execute();
    }
    
    /**
     * Update last sync timestamp
     */
    private function updateLastSync($channelSyncId) {
        $stmt = $this->conn->prepare("UPDATE channel_sync SET last_sync = NOW() WHERE id = ?");
        $stmt->bind_param("i", $channelSyncId);
        $stmt->execute();
    }
    
    /**
     * Sanitize string for database insertion
     */
    private function sanitizeString($string, $maxLength = 500) {
        $string = str_replace("\x00", '', $string);
        $string = trim($string);
        
        if (strlen($string) > $maxLength) {
            $string = substr($string, 0, $maxLength - 3) . '...';
        }
        
        return $string;
    }
    
    /**
     * Add new channel to sync
     */
    public function addChannel($channelId, $categoryId, $channelName = null, $frequency = 'daily', $autoImport = true, $requireApproval = false, $maxVideos = 10, $targetRole = 'general') {
        try {
            // Validate channel exists by trying to get its videos
            $testVideos = $this->youtubeAPI->getChannelVideos($channelId, 1);
            if (empty($testVideos)) {
                throw new Exception("Channel not found or has no public videos");
            }
            
            // Use channel title from first video if not provided
            if (!$channelName && !empty($testVideos)) {
                $channelName = $testVideos[0]['channel_title'];
            }
            
            $stmt = $this->conn->prepare("INSERT INTO channel_sync 
                (channel_id, channel_name, category_id, sync_frequency, auto_import, require_approval, max_videos_per_sync, target_role) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssissiis", $channelId, $channelName, $categoryId, $frequency, $autoImport, $requireApproval, $maxVideos, $targetRole);
            
            if ($stmt->execute()) {
                return $this->conn->insert_id;
            } else {
                throw new Exception("Failed to add channel: " . $stmt->error);
            }
            
        } catch (Exception $e) {
            throw new Exception("Error adding channel: " . $e->getMessage());
        }
    }
    
    /**
     * Get sync statistics
     */
    public function getSyncStats() {
        $stats = [];
        
        // Total active channels
        $result = $this->conn->query("SELECT COUNT(*) as total FROM channel_sync WHERE is_active = 1");
        $stats['active_channels'] = $result->fetch_assoc()['total'];
        
        // Recent sync results
        $result = $this->conn->query("SELECT 
            cs.channel_name,
            sl.status,
            sl.videos_found,
            sl.videos_imported,
            sl.started_at
            FROM sync_log sl
            JOIN channel_sync cs ON sl.channel_sync_id = cs.id
            WHERE sl.started_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY sl.started_at DESC
            LIMIT 20");
            
        $stats['recent_syncs'] = [];
        while ($row = $result->fetch_assoc()) {
            $stats['recent_syncs'][] = $row;
        }
        
        return $stats;
    }
    
    /**
     * Manual category override for single videos
     */
    public function overrideVideoCategory($videoId, $newCategoryId, $reason = '', $userId = null) {
        try {
            // Get current category
            $stmt = $this->conn->prepare("SELECT category_id FROM videos WHERE id = ?");
            $stmt->bind_param("i", $videoId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception("Video not found");
            }
            
            $currentCategory = $result->fetch_assoc()['category_id'];
            
            // Insert or update override
            $stmt = $this->conn->prepare("INSERT INTO video_category_override 
                (video_id, original_category_id, override_category_id, reason, created_by) 
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                override_category_id = VALUES(override_category_id),
                reason = VALUES(reason),
                created_by = VALUES(created_by),
                created_at = CURRENT_TIMESTAMP");
            
            $stmt->bind_param("iisis", $videoId, $currentCategory, $newCategoryId, $reason, $userId);
            
            if ($stmt->execute()) {
                // Update video category
                $updateStmt = $this->conn->prepare("UPDATE videos SET category_id = ? WHERE id = ?");
                $updateStmt->bind_param("ii", $newCategoryId, $videoId);
                $updateStmt->execute();
                
                return true;
            } else {
                throw new Exception("Failed to create category override");
            }
            
        } catch (Exception $e) {
            throw new Exception("Error overriding video category: " . $e->getMessage());
        }
    }
    
    /**
     * Get video category override history
     */
    public function getVideoOverrideHistory($videoId) {
        try {
            $stmt = $this->conn->prepare("SELECT vco.*, 
                c1.name as original_category_name,
                c2.name as override_category_name,
                u.first_name, u.last_name
                FROM video_category_override vco
                LEFT JOIN categories c1 ON vco.original_category_id = c1.id
                LEFT JOIN categories c2 ON vco.override_category_id = c2.id
                LEFT JOIN users u ON vco.created_by = u.id
                WHERE vco.video_id = ?
                ORDER BY vco.created_at DESC");
            
            $stmt->bind_param("i", $videoId);
            $stmt->execute();
            
            $result = $stmt->get_result();
            $history = [];
            while ($row = $result->fetch_assoc()) {
                $history[] = $row;
            }
            
            return $history;
            
        } catch (Exception $e) {
            throw new Exception("Error fetching override history: " . $e->getMessage());
        }
    }
}
?>