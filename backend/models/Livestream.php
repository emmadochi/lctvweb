<?php
/**
 * Livestream Model
 * Handles livestream data operations
 */

require_once __DIR__ . '/../config/database.php';

class Livestream {
    private static $table = 'livestreams';

    /**
     * Get all active livestreams
     */
    public static function getAll($userRole = 'general') {
        $conn = getDBConnection();
        $allowedRoles = Auth::getAllowedRoles($userRole);
        $placeholders = str_repeat('?,', count($allowedRoles) - 1) . '?';

        $sql = "SELECT * FROM " . self::$table . " 
                WHERE is_live = 1 
                AND (target_role IN ($placeholders) OR target_role IS NULL)
                ORDER BY viewer_count DESC, started_at DESC";
        
        $stmt = $conn->prepare($sql);
        $types = str_repeat('s', count($allowedRoles));
        $stmt->bind_param($types, ...$allowedRoles);
        $stmt->execute();
        $result = $stmt->get_result();

        $livestreams = [];
        while ($row = $result->fetch_assoc()) {
            $livestreams[] = self::formatLivestreamData($row);
        }

        return $livestreams;
    }

    /**
     * Get exclusive leadership livestreams
     */
    public static function getExclusive($userRole = 'general', $limit = 10) {
        $conn = getDBConnection();
        $allowedRoles = Auth::getAllowedRoles($userRole);
        
        $exclusiveRoles = array_filter($allowedRoles, function($role) {
            return $role !== 'general';
        });

        if (empty($exclusiveRoles)) {
            return [];
        }

        $placeholders = str_repeat('?,', count($exclusiveRoles) - 1) . '?';

        $sql = "SELECT * FROM " . self::$table . " 
                WHERE is_live = 1 
                AND target_role IN ($placeholders)
                ORDER BY viewer_count DESC, started_at DESC
                LIMIT ?";
        
        $stmt = $conn->prepare($sql);
        $params = array_merge(array_values($exclusiveRoles), [$limit]);
        $types = str_repeat('s', count($exclusiveRoles)) . 'i';
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $livestreams = [];
        while ($row = $result->fetch_assoc()) {
            $livestreams[] = self::formatLivestreamData($row);
        }

        return $livestreams;
    }

    /**
     * Get featured livestream (highest viewer count)
     */
    public static function getFeatured($userRole = 'general') {
        $conn = getDBConnection();
        $allowedRoles = Auth::getAllowedRoles($userRole);
        $placeholders = str_repeat('?,', count($allowedRoles) - 1) . '?';

        $sql = "SELECT * FROM " . self::$table . " 
                WHERE is_live = 1 
                AND (target_role IN ($placeholders) OR target_role IS NULL)
                ORDER BY viewer_count DESC LIMIT 1";
        
        $stmt = $conn->prepare($sql);
        $types = str_repeat('s', count($allowedRoles));
        $stmt->bind_param($types, ...$allowedRoles);
        $stmt->execute();
        $result = $stmt->get_result();

        $row = $result->fetch_assoc();
        return $row ? self::formatLivestreamData($row) : null;
    }

    /**
     * Find livestream by ID
     */
    public static function find($id) {
        $conn = getDBConnection();

        $sql = "SELECT * FROM " . self::$table . " WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        $row = $result->fetch_assoc();
        return $row ? self::formatLivestreamData($row) : null;
    }

    /**
     * Find livestream by YouTube ID
     */
    public static function findByYoutubeId($youtubeId) {
        $conn = getDBConnection();

        $sql = "SELECT * FROM " . self::$table . " WHERE youtube_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $youtubeId);
        $stmt->execute();
        $result = $stmt->get_result();

        $row = $result->fetch_assoc();
        return $row ? self::formatLivestreamData($row) : null;
    }

    /**
     * Get livestreams by category
     */
    public static function getByCategory($categoryId, $userRole = 'general') {
        $conn = getDBConnection();
        $allowedRoles = Auth::getAllowedRoles($userRole);
        $placeholders = str_repeat('?,', count($allowedRoles) - 1) . '?';

        $sql = "SELECT * FROM " . self::$table . " 
                WHERE category_id = ? 
                AND (target_role IN ($placeholders) OR target_role IS NULL)
                AND is_live = 1 
                ORDER BY viewer_count DESC";
        
        $stmt = $conn->prepare($sql);
        $params = array_merge([$categoryId], $allowedRoles);
        $types = "i" . str_repeat('s', count($allowedRoles));
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $livestreams = [];
        while ($row = $result->fetch_assoc()) {
            $livestreams[] = self::formatLivestreamData($row);
        }

        return $livestreams;
    }

    /**
     * Format livestream data
     */
    public static function formatLivestreamData($row) {
        $isLive = (bool)$row['is_live'];
        $viewerCount = (int)$row['viewer_count'];
        
        // If live, get real-time count from active sessions
        if ($isLive) {
            $realTimeCount = self::getActiveViewerCount((int)$row['id']);
            if ($realTimeCount > 0) {
                $viewerCount = $realTimeCount;
            }
        }

        return [
            'id' => (int)$row['id'],
            'youtube_id' => $row['youtube_id'],
            'title' => $row['title'],
            'description' => $row['description'],
            'thumbnail_url' => $row['thumbnail_url'],
            'channel_title' => $row['channel_title'],
            'channel_id' => $row['channel_id'],
            'viewer_count' => $viewerCount,
            'is_live' => $isLive,
            'hls_url' => $row['hls_url'],
            'category_id' => $row['category_id'] ? (int)$row['category_id'] : null,
            'started_at' => $row['started_at'],
            'target_role' => $row['target_role'] ?? 'general'
        ];
    }

    /**
     * Update/Upsert viewer heartbeat
     */
    public static function updateHeartbeat($livestreamId, $sessionId) {
        $conn = getDBConnection();
        
        // Use UPSERT (INSERT ... ON DUPLICATE KEY UPDATE)
        $sql = "INSERT INTO livestream_viewers (livestream_id, session_id, last_heartbeat) 
                VALUES (?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE last_heartbeat = NOW()";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $livestreamId, $sessionId);
        
        // Also perform a quick cleanup of old sessions (older than 2 minutes)
        self::cleanupViewers();
        
        return $stmt->execute();
    }

    /**
     * Get active viewer count (sessions active in the last 60 seconds)
     */
    public static function getActiveViewerCount($livestreamId) {
        $conn = getDBConnection();
        
        $sql = "SELECT COUNT(*) as count FROM livestream_viewers 
                WHERE livestream_id = ? 
                AND last_heartbeat > DATE_SUB(NOW(), INTERVAL 60 SECOND)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $livestreamId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        return (int)($result['count'] ?? 0);
    }

    /**
     * Cleanup old inactive viewer sessions
     */
    public static function cleanupViewers() {
        $conn = getDBConnection();
        // Delete sessions older than 5 minutes to keep table clean
        $conn->query("DELETE FROM livestream_viewers WHERE last_heartbeat < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
    }


    /**
     * Create a new livestream
     */
    public static function create($data) {
        $conn = getDBConnection();

        $sql = "INSERT INTO " . self::$table . "
                (youtube_id, title, description, thumbnail_url, channel_title, channel_id, viewer_count, is_live, category_id, started_at, target_role)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $youtube_id = $data['youtube_id'] ?? '';
        $title = $data['title'] ?? '';
        $description = $data['description'] ?? '';
        $thumbnail_url = $data['thumbnail_url'] ?? '';
        $channel_title = $data['channel_title'] ?? '';
        $channel_id = $data['channel_id'] ?? '';
        $viewer_count = $data['viewer_count'] ?? 0;
        $is_live = $data['is_live'] ?? 1;
        $category_id = $data['category_id'] ?? null;
        $started_at = $data['started_at'] ?? date('Y-m-d H:i:s');
        $target_role = $data['target_role'] ?? 'general';
        
        $stmt->bind_param(
            "ssssssiiiss",
            $youtube_id,
            $title,
            $description,
            $thumbnail_url,
            $channel_title,
            $channel_id,
            $viewer_count,
            $is_live,
            $category_id,
            $started_at,
            $target_role
        );

        if ($stmt->execute()) {
            return $conn->insert_id;
        }

        return false;
    }

    /**
     * Update livestream
     */
    public static function update($id, $data) {
        $conn = getDBConnection();

        $sql = "UPDATE " . self::$table . " SET
                title = ?, description = ?, thumbnail_url = ?, viewer_count = ?, is_live = ?, category_id = ?, target_role = ?
                WHERE id = ?";

        $stmt = $conn->prepare($sql);

        // Extract values to variables to avoid pass-by-reference issues
        $title = $data['title'] ?? '';
        $description = $data['description'] ?? '';
        $thumbnail_url = $data['thumbnail_url'] ?? '';
        $viewer_count = $data['viewer_count'] ?? 0;
        $is_live = $data['is_live'] ? 1 : 0;
        $category_id = isset($data['category_id']) && $data['category_id'] !== '' ? (int)$data['category_id'] : null;
        $target_role = $data['target_role'] ?? 'general';

        $stmt->bind_param(
            "sssiiisi",
            $title,
            $description,
            $thumbnail_url,
            $viewer_count,
            $is_live,
            $category_id,
            $target_role,
            $id
        );

        return $stmt->execute();
    }

    /**
     * Batch delete livestreams
     */
    public static function batchDelete($ids) {
        if (empty($ids)) {
            return false;
        }

        $conn = getDBConnection();

        // Create placeholders for the IN clause
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $types = str_repeat('i', count($ids));

        $stmt = $conn->prepare("DELETE FROM " . self::$table . " WHERE id IN ($placeholders)");
        $stmt->bind_param($types, ...$ids);

        return $stmt->execute();
    }

    /**
     * Count total livestreams
     */
    public static function count() {
        $conn = getDBConnection();

        $result = $conn->query("SELECT COUNT(*) as count FROM " . self::$table);
        $row = $result->fetch_assoc();
        return $row['count'];
    }

    /**
     * Delete livestream
     */
    public static function delete($id) {
        $conn = getDBConnection();

        $sql = "DELETE FROM " . self::$table . " WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);

        return $stmt->execute();
    }
}
?>
