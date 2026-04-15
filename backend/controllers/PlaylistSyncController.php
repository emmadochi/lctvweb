<?php
/**
 * Playlist Sync Controller
 * Handles API endpoints for playlist synchronization management
 */

require_once __DIR__ . '/../services/PlaylistSyncService.php';
require_once __DIR__ . '/../utils/Auth.php';
require_once __DIR__ . '/../utils/Response.php';

class PlaylistSyncController {
    
    /**
     * Get all playlist sync configurations
     */
  public static function index() {
        try {
            Auth::requireAdmin();
            
            $service = new PlaylistSyncService();
            $conn = getDBConnection();
            
            $sql = "SELECT ps.*, c.name as category_name 
                    FROM playlist_sync ps 
                    LEFT JOIN categories c ON ps.category_id = c.id 
                    ORDER BY ps.created_at DESC";
            $result = $conn->query($sql);
            
            $playlists = [];
            while ($row = $result->fetch_assoc()) {
                $playlists[] = $row;
            }
            
            Response::success($playlists);
            
        } catch (Exception $e) {
            error_log("PlaylistSyncController::index error: " . $e->getMessage());
            Response::error('Failed to fetch playlist sync configurations', 500);
        }
    }
    
    /**
     * Get specific playlist sync configuration
     */
  public static function show($id) {
        try {
            Auth::requireAdmin();
            
            $conn = getDBConnection();
            $stmt = $conn->prepare("SELECT ps.*, c.name as category_name 
                                   FROM playlist_sync ps 
                                   LEFT JOIN categories c ON ps.category_id = c.id 
                                   WHERE ps.id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $playlist = $result->fetch_assoc();
            
            if (!$playlist) {
                Response::notFound('Playlist sync configuration not found');
                return;
            }
            
            Response::success($playlist);
            
        } catch (Exception $e) {
            error_log("PlaylistSyncController::show error: " . $e->getMessage());
            Response::error('Failed to fetch playlist sync configuration', 500);
        }
    }
    
    /**
     * Create new playlist sync configuration
     */
  public static function create() {
        try {
            Auth::requireAdmin();
            
            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Response::error('Invalid JSON data', 400);
                return;
            }
            
            // Validate required fields
            $required = ['playlist_id', 'category_id'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    Response::error("Missing required field: $field", 400);
                    return;
                }
            }
            
            $service = new PlaylistSyncService();
            
            $playlistId = $data['playlist_id'];
            $categoryId = (int)$data['category_id'];
            $playlistName = $data['playlist_name'] ?? null;
            $frequency = $data['sync_frequency'] ?? 'daily';
            $autoImport = $data['auto_import'] ?? true;
            $requireApproval = $data['require_approval'] ?? false;
            $maxVideos = $data['max_videos_per_sync'] ?? 10;
            
            $playlistSyncId = $service->addPlaylist(
                $playlistId, 
                $categoryId, 
                $playlistName, 
                $frequency, 
                $autoImport, 
                $requireApproval, 
                $maxVideos
            );
            
            if ($playlistSyncId) {
                // Return the created configuration
                $conn = getDBConnection();
                $stmt = $conn->prepare("SELECT ps.*, c.name as category_name 
                                       FROM playlist_sync ps 
                                       LEFT JOIN categories c ON ps.category_id = c.id 
                                       WHERE ps.id = ?");
                $stmt->bind_param("i", $playlistSyncId);
                $stmt->execute();
                $result = $stmt->get_result();
                $playlist = $result->fetch_assoc();
                
                Response::success($playlist, 201);
            } else {
                Response::error('Failed to create playlist sync configuration', 500);
            }
            
        } catch (Exception $e) {
            error_log("PlaylistSyncController::create error: " . $e->getMessage());
            Response::error($e->getMessage(), 400);
        }
    }
    
    /**
     * Update playlist sync configuration
     */
  public static function update($id) {
        try {
            Auth::requireAdmin();
            
            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Response::error('Invalid JSON data', 400);
                return;
            }
            
            $conn = getDBConnection();
            
            // Check if configuration exists
            $stmt = $conn->prepare("SELECT id FROM playlist_sync WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                Response::notFound('Playlist sync configuration not found');
                return;
            }
            
            // Build update query dynamically
            $fields = [];
            $params = [];
            $types = "";
            
            if (isset($data['playlist_name'])) {
                $fields[] = "playlist_name = ?";
                $params[] = $data['playlist_name'];
                $types .= "s";
            }
            
            if (isset($data['category_id'])) {
                $fields[] = "category_id = ?";
                $params[] = (int)$data['category_id'];
                $types .= "i";
            }
            
            if (isset($data['sync_frequency'])) {
                $fields[] = "sync_frequency = ?";
                $params[] = $data['sync_frequency'];
                $types .= "s";
            }
            
            if (isset($data['is_active'])) {
                $fields[] = "is_active = ?";
                $params[] = (int)$data['is_active'];
                $types .= "i";
            }
            
            if (isset($data['auto_import'])) {
                $fields[] = "auto_import = ?";
                $params[] = (int)$data['auto_import'];
                $types .= "i";
            }
            
            if (isset($data['require_approval'])) {
                $fields[] = "require_approval = ?";
                $params[] = (int)$data['require_approval'];
                $types .= "i";
            }
            
            if (isset($data['max_videos_per_sync'])) {
                $fields[] = "max_videos_per_sync = ?";
                $params[] = (int)$data['max_videos_per_sync'];
                $types .= "i";
            }
            
            if (empty($fields)) {
                Response::error('No fields to update', 400);
                return;
            }
            
            $fields[] = "updated_at = NOW()";
            $sql = "UPDATE playlist_sync SET " . implode(", ", $fields) . " WHERE id = ?";
            $params[] = $id;
            $types .= "i";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            
            if ($stmt->execute()) {
                // Return updated configuration
                $stmt = $conn->prepare("SELECT ps.*, c.name as category_name 
                                       FROM playlist_sync ps 
                                       LEFT JOIN categories c ON ps.category_id = c.id 
                                       WHERE ps.id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                $playlist = $result->fetch_assoc();
                
                Response::success($playlist);
            } else {
                Response::error('Failed to update playlist sync configuration', 500);
            }
            
        } catch (Exception $e) {
            error_log("PlaylistSyncController::update error: " . $e->getMessage());
            Response::error('Failed to update playlist sync configuration', 500);
        }
    }
    
    /**
     * Delete playlist sync configuration
     */
  public static function delete($id) {
        try {
            Auth::requireAdmin();
            
            $conn = getDBConnection();
            
            // Check if configuration exists
            $stmt = $conn->prepare("SELECT id FROM playlist_sync WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                Response::notFound('Playlist sync configuration not found');
                return;
            }
            
            // Delete configuration (will cascade delete sync logs)
            $stmt = $conn->prepare("DELETE FROM playlist_sync WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                Response::success(['message' => 'Playlist sync configuration deleted successfully']);
            } else {
                Response::error('Failed to delete playlist sync configuration', 500);
            }
            
        } catch (Exception $e) {
            error_log("PlaylistSyncController::delete error: " . $e->getMessage());
            Response::error('Failed to delete playlist sync configuration', 500);
        }
    }
    
    /**
     * Run synchronization for all playlists
     */
  public static function runSync() {
        try {
            Auth::requireAdmin();
            
            $service = new PlaylistSyncService();
            $results = $service->runAllSync();
            
            Response::success([
                'message' => 'Playlist synchronization completed',
                'results' => $results,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
        } catch (Exception $e) {
            error_log("PlaylistSyncController::runSync error: " . $e->getMessage());
            Response::error('Failed to run playlist synchronization: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Run synchronization for specific playlist
     */
  public static function runPlaylistSync($id) {
        try {
            Auth::requireAdmin();
            
            $conn = getDBConnection();
            
            // Get playlist configuration
            $stmt = $conn->prepare("SELECT * FROM playlist_sync WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $playlist = $result->fetch_assoc();
            
            if (!$playlist) {
                Response::notFound('Playlist sync configuration not found');
                return;
            }
            
            if (!$playlist['is_active']) {
                Response::error('Playlist sync is not active', 400);
                return;
            }
            
            $service = new PlaylistSyncService();
            $result = $service->syncPlaylist($playlist);
            
            Response::success([
                'message' => 'Playlist synchronization completed',
                'result' => $result,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
        } catch (Exception $e) {
            error_log("PlaylistSyncController::runPlaylistSync error: " . $e->getMessage());
            Response::error('Failed to run playlist synchronization: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get synchronization statistics
     */
  public static function getStats() {
        try {
            Auth::requireAdmin();
            
            $service = new PlaylistSyncService();
            $stats = $service->getSyncStats();
            
            Response::success($stats);
            
        } catch (Exception $e) {
            error_log("PlaylistSyncController::getStats error: " . $e->getMessage());
            Response::error('Failed to fetch synchronization statistics', 500);
        }
    }
    
    /**
     * Get recent sync logs
     */
  public static function getLogs() {
        try {
            Auth::requireAdmin();
            
            $conn = getDBConnection();
            $sql = "SELECT psl.*, ps.playlist_name 
                    FROM playlist_sync_log psl
                    JOIN playlist_sync ps ON psl.playlist_sync_id = ps.id
                    ORDER BY psl.started_at DESC
                    LIMIT 50";
            $result = $conn->query($sql);
            
            $logs = [];
            while ($row = $result->fetch_assoc()) {
                $logs[] = $row;
            }
            
            Response::success($logs);
            
        } catch (Exception $e) {
            error_log("PlaylistSyncController::getLogs error: " . $e->getMessage());
            Response::error('Failed to fetch sync logs', 500);
        }
    }
}
?>
