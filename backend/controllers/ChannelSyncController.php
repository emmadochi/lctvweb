<?php
/**
 * Channel Sync Controller
 * Handles API endpoints for channel synchronization management
 */

require_once __DIR__ . '/../services/ChannelSyncService.php';
require_once __DIR__ . '/../utils/Auth.php';
require_once __DIR__ . '/../utils/Response.php';

class ChannelSyncController {
    
    /**
     * Get all channel sync configurations
     */
    public static function index() {
        try {
            Auth::requireAdmin();
            
            $service = new ChannelSyncService();
            $conn = getDBConnection();
            
            $sql = "SELECT cs.*, c.name as category_name 
                    FROM channel_sync cs 
                    LEFT JOIN categories c ON cs.category_id = c.id 
                    ORDER BY cs.created_at DESC";
            $result = $conn->query($sql);
            
            $channels = [];
            while ($row = $result->fetch_assoc()) {
                $channels[] = $row;
            }
            
            Response::success($channels);
            
        } catch (Exception $e) {
            error_log("ChannelSyncController::index error: " . $e->getMessage());
            Response::error('Failed to fetch channel sync configurations', 500);
        }
    }
    
    /**
     * Get specific channel sync configuration
     */
    public static function show($id) {
        try {
            Auth::requireAdmin();
            
            $conn = getDBConnection();
            $stmt = $conn->prepare("SELECT cs.*, c.name as category_name 
                                   FROM channel_sync cs 
                                   LEFT JOIN categories c ON cs.category_id = c.id 
                                   WHERE cs.id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $channel = $result->fetch_assoc();
            
            if (!$channel) {
                Response::notFound('Channel sync configuration not found');
                return;
            }
            
            Response::success($channel);
            
        } catch (Exception $e) {
            error_log("ChannelSyncController::show error: " . $e->getMessage());
            Response::error('Failed to fetch channel sync configuration', 500);
        }
    }
    
    /**
     * Create new channel sync configuration
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
            $required = ['channel_id', 'category_id'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    Response::error("Missing required field: $field", 400);
                    return;
                }
            }
            
            $service = new ChannelSyncService();
            
            $channelId = $data['channel_id'];
            $categoryId = (int)$data['category_id'];
            $channelName = $data['channel_name'] ?? null;
            $frequency = $data['sync_frequency'] ?? 'daily';
            $autoImport = $data['auto_import'] ?? true;
            $requireApproval = $data['require_approval'] ?? false;
            $maxVideos = $data['max_videos_per_sync'] ?? 10;
            $targetRole = $data['target_role'] ?? 'general';
            
            $channelSyncId = $service->addChannel(
                $channelId, 
                $categoryId, 
                $channelName, 
                $frequency, 
                $autoImport, 
                $requireApproval, 
                $maxVideos,
                $targetRole
            );
            
            if ($channelSyncId) {
                // Return the created configuration
                $conn = getDBConnection();
                $stmt = $conn->prepare("SELECT cs.*, c.name as category_name 
                                       FROM channel_sync cs 
                                       LEFT JOIN categories c ON cs.category_id = c.id 
                                       WHERE cs.id = ?");
                $stmt->bind_param("i", $channelSyncId);
                $stmt->execute();
                $result = $stmt->get_result();
                $channel = $result->fetch_assoc();
                
                Response::success($channel, 201);
            } else {
                Response::error('Failed to create channel sync configuration', 500);
            }
            
        } catch (Exception $e) {
            error_log("ChannelSyncController::create error: " . $e->getMessage());
            Response::error($e->getMessage(), 400);
        }
    }
    
    /**
     * Update channel sync configuration
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
            $stmt = $conn->prepare("SELECT id FROM channel_sync WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                Response::notFound('Channel sync configuration not found');
                return;
            }
            
            // Build update query dynamically
            $fields = [];
            $params = [];
            $types = "";
            
            if (isset($data['channel_name'])) {
                $fields[] = "channel_name = ?";
                $params[] = $data['channel_name'];
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

            if (isset($data['target_role'])) {
                $fields[] = "target_role = ?";
                $params[] = $data['target_role'];
                $types .= "s";
            }
            
            if (empty($fields)) {
                Response::error('No fields to update', 400);
                return;
            }
            
            $fields[] = "updated_at = NOW()";
            $sql = "UPDATE channel_sync SET " . implode(", ", $fields) . " WHERE id = ?";
            $params[] = $id;
            $types .= "i";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            
            if ($stmt->execute()) {
                // Return updated configuration
                $stmt = $conn->prepare("SELECT cs.*, c.name as category_name 
                                       FROM channel_sync cs 
                                       LEFT JOIN categories c ON cs.category_id = c.id 
                                       WHERE cs.id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                $channel = $result->fetch_assoc();
                
                Response::success($channel);
            } else {
                Response::error('Failed to update channel sync configuration', 500);
            }
            
        } catch (Exception $e) {
            error_log("ChannelSyncController::update error: " . $e->getMessage());
            Response::error('Failed to update channel sync configuration', 500);
        }
    }
    
    /**
     * Delete channel sync configuration
     */
    public static function delete($id) {
        try {
            Auth::requireAdmin();
            
            $conn = getDBConnection();
            
            // Check if configuration exists
            $stmt = $conn->prepare("SELECT id FROM channel_sync WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                Response::notFound('Channel sync configuration not found');
                return;
            }
            
            // Delete configuration (will cascade delete sync logs)
            $stmt = $conn->prepare("DELETE FROM channel_sync WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                Response::success(['message' => 'Channel sync configuration deleted successfully']);
            } else {
                Response::error('Failed to delete channel sync configuration', 500);
            }
            
        } catch (Exception $e) {
            error_log("ChannelSyncController::delete error: " . $e->getMessage());
            Response::error('Failed to delete channel sync configuration', 500);
        }
    }
    
    /**
     * Run synchronization for all channels
     */
    public static function runSync() {
        try {
            Auth::requireAdmin();
            
            $service = new ChannelSyncService();
            $results = $service->runAllSync();
            
            Response::success([
                'message' => 'Channel synchronization completed',
                'results' => $results,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
        } catch (Exception $e) {
            error_log("ChannelSyncController::runSync error: " . $e->getMessage());
            Response::error('Failed to run channel synchronization: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Run synchronization for specific channel
     */
    public static function runChannelSync($id) {
        try {
            Auth::requireAdmin();
            
            $conn = getDBConnection();
            
            // Get channel configuration
            $stmt = $conn->prepare("SELECT * FROM channel_sync WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $channel = $result->fetch_assoc();
            
            if (!$channel) {
                Response::notFound('Channel sync configuration not found');
                return;
            }
            
            if (!$channel['is_active']) {
                Response::error('Channel sync is not active', 400);
                return;
            }
            
            $service = new ChannelSyncService();
            $result = $service->syncChannel($channel);
            
            Response::success([
                'message' => 'Channel synchronization completed',
                'result' => $result,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
        } catch (Exception $e) {
            error_log("ChannelSyncController::runChannelSync error: " . $e->getMessage());
            Response::error('Failed to run channel synchronization: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get synchronization statistics
     */
    public static function getStats() {
        try {
            Auth::requireAdmin();
            
            $service = new ChannelSyncService();
            $stats = $service->getSyncStats();
            
            Response::success($stats);
            
        } catch (Exception $e) {
            error_log("ChannelSyncController::getStats error: " . $e->getMessage());
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
            $sql = "SELECT sl.*, cs.channel_name 
                    FROM sync_log sl
                    JOIN channel_sync cs ON sl.channel_sync_id = cs.id
                    ORDER BY sl.started_at DESC
                    LIMIT 50";
            $result = $conn->query($sql);
            
            $logs = [];
            while ($row = $result->fetch_assoc()) {
                $logs[] = $row;
            }
            
            Response::success($logs);
            
        } catch (Exception $e) {
            error_log("ChannelSyncController::getLogs error: " . $e->getMessage());
            Response::error('Failed to fetch sync logs', 500);
        }
    }

    /**
     * Override video category
     */
    public static function overrideCategory($videoId) {
        try {
            Auth::requireAdmin();
            
            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);
            
            if (json_last_error() !== JSON_ERROR_NONE || !isset($data['category_id'])) {
                Response::error('Invalid override data', 400);
                return;
            }
            
            $service = new ChannelSyncService();
            $categoryId = (int)$data['category_id'];
            $reason = $data['reason'] ?? '';
            $userId = $_SESSION['admin_id'] ?? null;
            
            $success = $service->overrideVideoCategory($videoId, $categoryId, $reason, $userId);
            
            if ($success) {
                Response::success(['message' => 'Video category overridden successfully']);
            } else {
                Response::error('Failed to override video category', 500);
            }
            
        } catch (Exception $e) {
            error_log("ChannelSyncController::overrideCategory error: " . $e->getMessage());
            Response::error('Failed to override video category: ' . $e->getMessage(), 500);
        }
    }
}
?>