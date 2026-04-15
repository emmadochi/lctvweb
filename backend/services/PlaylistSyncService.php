<?php
/**
 * Playlist Synchronization Service
 * Automatically imports videos from configured YouTube playlists
 */

require_once __DIR__ . '/../utils/YouTubeAPI.php';
require_once __DIR__ . '/../models/Video.php';
require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../config/database.php';

class PlaylistSyncService {
  private $youtubeAPI;
  private $conn;
    
  public function __construct() {
        $this->youtubeAPI = new YouTubeAPI();
        $this->conn = getDBConnection();
    }
    
    /**
     * Run synchronization for all active playlists
     */
  public function runAllSync() {
        $playlists = $this->getActivePlaylists();
        $results = [];
        
        foreach ($playlists as $playlist) {
            $result = $this->syncPlaylist($playlist);
            $results[] = $result;
        }
        
        return $results;
    }
    
    /**
     * Synchronize a specific playlist
     */
  public function syncPlaylist($playlistConfig) {
        $startTime = date('Y-m-d H:i:s');
        $logId = $this->createSyncLog($playlistConfig['id']);
        
        try {
            echo "Starting sync for playlist: {$playlistConfig['playlist_name']} (ID: {$playlistConfig['playlist_id']})\n";
            
            // Get new videos since last sync
            $newVideos = $this->getNewVideos($playlistConfig);
            
            if (empty($newVideos)) {
                echo "No new videos found for playlist {$playlistConfig['playlist_name']}\n";
                $this->updateSyncLog($logId, 'success', 0, 0, 0);
                $this->updateLastSync($playlistConfig['id']);
                return ['playlist' => $playlistConfig['playlist_name'], 'status' => 'no_new_videos', 'imported' => 0];
            }
            
            echo "Found " . count($newVideos) . " new videos\n";
            
            $imported = 0;
            $skipped = 0;
            
            // Limit videos per sync to prevent overwhelming the system
            $videosToProcess = array_slice($newVideos, 0, $playlistConfig['max_videos_per_sync']);
            
            foreach ($videosToProcess as $videoData) {
                if ($this->importVideo($videoData, $playlistConfig)) {
                    $imported++;
                } else {
                    $skipped++;
                }
            }
            
            // Update sync log
            $this->updateSyncLog($logId, 'success', count($videosToProcess), $imported, $skipped);
            $this->updateLastSync($playlistConfig['id']);
            
            echo "Sync completed for {$playlistConfig['playlist_name']}: $imported imported, $skipped skipped\n";
            
            return [
                'playlist' => $playlistConfig['playlist_name'],
                'status' => 'success',
                'found' => count($videosToProcess),
                'imported' => $imported,
                'skipped' => $skipped
            ];
            
        } catch (Exception $e) {
            echo "Error syncing playlist {$playlistConfig['playlist_name']}: " . $e->getMessage() . "\n";
            $this->updateSyncLog($logId, 'failed', 0, 0, 0, $e->getMessage());
            return [
                'playlist' => $playlistConfig['playlist_name'],
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get videos published since last sync
     */
  private function getNewVideos($playlistConfig) {
        try {
            // Get all videos from playlist
            $allVideos = $this->youtubeAPI->getPlaylistVideos($playlistConfig['playlist_id'], 50);
            
            if (empty($allVideos)) {
                return [];
            }
            
            // Get detailed video information
            $videoIds = array_column($allVideos, 'youtube_id');
            $detailedVideos = $this->youtubeAPI->getVideoDetails($videoIds);
            
            if (empty($detailedVideos)) {
                return [];
            }
            
            // Filter by last sync date if available
            if (!empty($playlistConfig['last_video_date'])) {
                $filteredVideos = [];
                $lastSyncTimestamp = strtotime($playlistConfig['last_video_date']);
                
                foreach ($detailedVideos as $video) {
                    $publishedTimestamp = strtotime($video['published_at']);
                    if ($publishedTimestamp > $lastSyncTimestamp) {
                        $filteredVideos[] = $video;
                    }
                }
                
                return $filteredVideos;
            }
            
            // If no last sync date, return all videos (first sync)
            return $detailedVideos;
            
        } catch (Exception $e) {
            error_log("Error getting new videos: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Import a single video
     */
  private function importVideo($videoData, $playlistConfig) {
        try {
            // Check if video already exists
            $existingVideo = Video::getByYouTubeId($videoData['youtube_id']);
            if ($existingVideo) {
                echo "Video already exists: {$videoData['title']}\n";
                return false;
            }
            
            // Check if video should be auto-imported or require approval
            $isActive = $playlistConfig['auto_import'] && !$playlistConfig['require_approval'];
            
            // Prepare video data
            $videoInsertData = [
                'youtube_id' => $videoData['youtube_id'],
                'title' => $this->sanitizeString($videoData['title']),
                'description' => $this->sanitizeString($videoData['description']),
                'thumbnail_url' => $videoData['thumbnail_url'],
                'duration' => $videoData['duration'],
                'category_id' => $playlistConfig['category_id'],
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
     * Get all active playlist configurations
     */
  private function getActivePlaylists() {
        $sql = "SELECT * FROM playlist_sync WHERE is_active = 1";
        $result = $this->conn->query($sql);
        
        $playlists = [];
        while ($row = $result->fetch_assoc()) {
            $playlists[] = $row;
        }
        
        return $playlists;
    }
    
    /**
     * Create sync log entry
     */
  private function createSyncLog($playlistSyncId) {
        $stmt = $this->conn->prepare("INSERT INTO playlist_sync_log (playlist_sync_id, status) VALUES (?, 'started')");
        $stmt->bind_param("i", $playlistSyncId);
        $stmt->execute();
        return $this->conn->insert_id;
    }
    
    /**
     * Update sync log with results
     */
  private function updateSyncLog($logId, $status, $found = 0, $imported = 0, $skipped = 0, $errorMessage = null) {
        $stmt = $this->conn->prepare("UPDATE playlist_sync_log SET 
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
  private function updateLastSync($playlistSyncId) {
        $stmt = $this->conn->prepare("UPDATE playlist_sync SET last_sync = NOW() WHERE id = ?");
        $stmt->bind_param("i", $playlistSyncId);
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
     * Add new playlist to sync
     */
  public function addPlaylist($playlistId, $categoryId, $playlistName = null, $frequency = 'daily', $autoImport = true, $requireApproval = false, $maxVideos = 10) {
        try {
            // Validate playlist exists by trying to get its videos
            $testVideos = $this->youtubeAPI->getPlaylistVideos($playlistId, 1);
            if (empty($testVideos)) {
                throw new Exception("Playlist not found or has no public videos");
            }
            
            // Use playlist name from first video if not provided
            if (!$playlistName && !empty($testVideos)) {
                $playlistName = $testVideos[0]['title'] . " (Playlist)";
            }
            
            $stmt = $this->conn->prepare("INSERT INTO playlist_sync 
                (playlist_id, playlist_name, channel_id, channel_name, category_id, sync_frequency, auto_import, require_approval, max_videos_per_sync) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $channelId = $testVideos[0]['channel_id'] ?? '';
            $channelName = $testVideos[0]['channel_title'] ?? '';
            
            $stmt->bind_param("sssssissi", $playlistId, $playlistName, $channelId, $channelName, $categoryId, $frequency, $autoImport, $requireApproval, $maxVideos);
            
            if ($stmt->execute()) {
                return $this->conn->insert_id;
            } else {
                throw new Exception("Failed to add playlist: " . $stmt->error);
            }
            
        } catch (Exception $e) {
            throw new Exception("Error adding playlist: " . $e->getMessage());
        }
    }
    
    /**
     * Get sync statistics
     */
  public function getSyncStats() {
        $stats = [];
        
        // Total active playlists
        $result = $this->conn->query("SELECT COUNT(*) as total FROM playlist_sync WHERE is_active = 1");
        $stats['active_playlists'] = $result->fetch_assoc()['total'];
        
        // Recent sync results
        $result = $this->conn->query("SELECT 
            ps.playlist_name,
            psl.status,
           psl.videos_found,
           psl.videos_imported,
           psl.started_at
            FROM playlist_sync_log psl
            JOIN playlist_sync ps ON psl.playlist_sync_id = ps.id
            WHERE psl.started_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY psl.started_at DESC
            LIMIT 20");
            
        $stats['recent_syncs'] = [];
        while ($row = $result->fetch_assoc()) {
            $stats['recent_syncs'][] = $row;
        }
        
        return $stats;
    }
}
?>
