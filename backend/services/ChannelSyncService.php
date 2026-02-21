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
                'is_active' => $isActive ? 1 : 0
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
    public function addChannel($channelId, $categoryId, $channelName = null, $frequency = 'daily', $autoImport = true, $requireApproval = false, $maxVideos = 10) {
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
                (channel_id, channel_name, category_id, sync_frequency, auto_import, require_approval, max_videos_per_sync) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssissii", $channelId, $channelName, $categoryId, $frequency, $autoImport, $requireApproval, $maxVideos);
            
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
     * Get channel playlists for mapping configuration
     */
    public function getChannelPlaylistsForMapping($channelId) {
        try {
            return $this->youtubeAPI->getChannelPlaylistsWithVideos($channelId, 50, 5);
        } catch (Exception $e) {
            throw new Exception("Error fetching channel playlists: " . $e->getMessage());
        }
    }
    
    /**
     * Add playlist mapping for a channel
     */
    public function addPlaylistMapping($channelSyncId, $playlistId, $playlistName, $categoryId, $importLimit = 20, $requireApproval = false) {
        try {
            $stmt = $this->conn->prepare("INSERT INTO channel_playlist_mapping 
                (channel_sync_id, playlist_id, playlist_name, category_id, import_limit, require_approval) 
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                playlist_name = VALUES(playlist_name), 
                category_id = VALUES(category_id), 
                import_limit = VALUES(import_limit), 
                require_approval = VALUES(require_approval),
                is_active = TRUE,
                updated_at = CURRENT_TIMESTAMP");
            
            $stmt->bind_param("issiii", $channelSyncId, $playlistId, $playlistName, $categoryId, $importLimit, $requireApproval);
            
            if ($stmt->execute()) {
                return $this->conn->insert_id;
            } else {
                throw new Exception("Failed to add playlist mapping: " . $stmt->error);
            }
            
        } catch (Exception $e) {
            throw new Exception("Error adding playlist mapping: " . $e->getMessage());
        }
    }
    
    /**
     * Remove playlist mapping
     */
    public function removePlaylistMapping($mappingId) {
        try {
            $stmt = $this->conn->prepare("UPDATE channel_playlist_mapping SET is_active = FALSE WHERE id = ?");
            $stmt->bind_param("i", $mappingId);
            return $stmt->execute();
        } catch (Exception $e) {
            throw new Exception("Error removing playlist mapping: " . $e->getMessage());
        }
    }
    
    /**
     * Get playlist mappings for a channel
     */
    public function getPlaylistMappings($channelSyncId) {
        try {
            $stmt = $this->conn->prepare("SELECT cpm.*, c.name as category_name 
                FROM channel_playlist_mapping cpm 
                JOIN categories c ON cpm.category_id = c.id 
                WHERE cpm.channel_sync_id = ? AND cpm.is_active = TRUE 
                ORDER BY cpm.playlist_name");
            $stmt->bind_param("i", $channelSyncId);
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $mappings = [];
            while ($row = $result->fetch_assoc()) {
                $mappings[] = $row;
            }
            
            return $mappings;
            
        } catch (Exception $e) {
            throw new Exception("Error fetching playlist mappings: " . $e->getMessage());
        }
    }
    
    /**
     * Sync channel with playlist mapping support
     */
    public function syncChannelWithPlaylists($channelConfig) {
        $startTime = date('Y-m-d H:i:s');
        $logId = $this->createSyncLog($channelConfig['id']);
        
        try {
            echo "Starting playlist-aware sync for channel: {$channelConfig['channel_name']} (ID: {$channelConfig['channel_id']})\n";
            
            // Get playlist mappings for this channel
            $playlistMappings = $this->getPlaylistMappings($channelConfig['id']);
            
            if (empty($playlistMappings)) {
                // Fallback to default category mapping if no playlists configured
                echo "No playlist mappings found, using default category mapping\n";
                $fallbackResult = $this->syncChannel($channelConfig);
                
                // Convert syncChannel result format to match syncChannelWithPlaylists format
                return [
                    'channel_id' => $channelConfig['channel_id'],
                    'channel_name' => $channelConfig['channel_name'],
                    'status' => $fallbackResult['status'],
                    'videos_found' => $fallbackResult['found'] ?? 0,
                    'videos_imported' => $fallbackResult['imported'] ?? 0,
                    'videos_skipped' => $fallbackResult['skipped'] ?? 0,
                    'started_at' => $startTime,
                    'completed_at' => date('Y-m-d H:i:s')
                ];
            }
            
            $totalVideosFound = 0;
            $totalVideosImported = 0;
            $totalVideosSkipped = 0;
            
            // Process each mapped playlist
            foreach ($playlistMappings as $mapping) {
                echo "Processing playlist: {$mapping['playlist_name']} -> Category: {$mapping['category_name']}\n";
                
                $playlistVideos = $this->youtubeAPI->getPlaylistVideos($mapping['playlist_id'], $mapping['import_limit']);
                $totalVideosFound += count($playlistVideos);
                
                foreach ($playlistVideos as $video) {
                    $importResult = $this->importVideoWithCategory($video, $mapping['category_id'], $channelConfig['id'], $mapping['playlist_id'], $mapping['require_approval']);
                    
                    if ($importResult['success']) {
                        $totalVideosImported++;
                        echo "✓ Imported: {$video['title']}\n";
                    } else {
                        $totalVideosSkipped++;
                        echo "✗ Skipped: {$video['title']} - {$importResult['reason']}\n";
                    }
                }
            }
            
            // Update sync log
            $this->updateSyncLog($logId, 'success', $totalVideosFound, $totalVideosImported, $totalVideosSkipped);
            
            $result = [
                'channel_id' => $channelConfig['channel_id'],
                'channel_name' => $channelConfig['channel_name'],
                'status' => 'success',
                'videos_found' => $totalVideosFound,
                'videos_imported' => $totalVideosImported,
                'videos_skipped' => $totalVideosSkipped,
                'started_at' => $startTime,
                'completed_at' => date('Y-m-d H:i:s')
            ];
            
            echo "Playlist sync completed: Found {$totalVideosFound}, Imported {$totalVideosImported}, Skipped {$totalVideosSkipped}\n";
            return $result;
            
        } catch (Exception $e) {
            $this->updateSyncLog($logId, 'failed', 0, 0, 0, $e->getMessage());
            throw new Exception("Playlist sync failed: " . $e->getMessage());
        }
    }
    
    /**
     * Import video with specific category and playlist tracking
     */
    private function importVideoWithCategory($video, $categoryId, $channelSyncId, $playlistId, $requireApproval = false) {
        try {
            // Check if video already exists
            $stmt = $this->conn->prepare("SELECT id, category_id FROM videos WHERE youtube_id = ?");
            $stmt->bind_param("s", $video['youtube_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $existingVideo = $result->fetch_assoc();
                
                // Check for category override
                $overrideCheck = $this->conn->prepare("SELECT override_category_id FROM video_category_override WHERE video_id = ?");
                $overrideCheck->bind_param("i", $existingVideo['id']);
                $overrideCheck->execute();
                $overrideResult = $overrideCheck->get_result();
                
                if ($overrideResult->num_rows > 0) {
                    // Video has manual override, don't change category
                    return ['success' => false, 'reason' => 'Manual category override exists'];
                }
                
                // Update category if different and no override exists
                if ($existingVideo['category_id'] != $categoryId) {
                    $updateStmt = $this->conn->prepare("UPDATE videos SET category_id = ? WHERE id = ?");
                    $updateStmt->bind_param("ii", $categoryId, $existingVideo['id']);
                    $updateStmt->execute();
                    echo "  Updated category for existing video: {$video['title']}\n";
                }
                
                return ['success' => true, 'reason' => 'Existing video updated'];
            }
            
            // Insert new video
            $status = $requireApproval ? 0 : 1; // 0 = pending approval, 1 = active
            $stmt = $this->conn->prepare("INSERT INTO videos 
                (youtube_id, title, description, thumbnail_url, duration, category_id, channel_title, channel_id, published_at, is_active, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            
            $stmt->bind_param("ssssisssii", 
                $video['youtube_id'], 
                $video['title'], 
                $video['description'], 
                $video['thumbnail_url'], 
                $video['duration'], 
                $categoryId, 
                $video['channel_title'], 
                $video['channel_id'], 
                $video['published_at'], 
                $status
            );
            
            if ($stmt->execute()) {
                $videoId = $this->conn->insert_id;
                
                // Track playlist association
                $this->trackVideoPlaylist($videoId, $playlistId, $video['title'], $channelSyncId);
                
                return ['success' => true, 'video_id' => $videoId];
            } else {
                return ['success' => false, 'reason' => 'Database insert failed: ' . $stmt->error];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'reason' => 'Import error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Track video playlist association
     */
    private function trackVideoPlaylist($videoId, $playlistId, $playlistName, $channelSyncId) {
        try {
            $stmt = $this->conn->prepare("INSERT IGNORE INTO video_playlists 
                (video_id, playlist_id, playlist_name, channel_sync_id) 
                VALUES (?, ?, ?, ?)");
            $stmt->bind_param("issi", $videoId, $playlistId, $playlistName, $channelSyncId);
            $stmt->execute();
        } catch (Exception $e) {
            error_log("Error tracking video playlist: " . $e->getMessage());
        }
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