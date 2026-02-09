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
    public static function getAll() {
        $conn = getDBConnection();

        $sql = "SELECT * FROM " . self::$table . " WHERE is_live = 1 ORDER BY viewer_count DESC, started_at DESC";
        $result = $conn->query($sql);

        $livestreams = [];
        while ($row = $result->fetch_assoc()) {
            $livestreams[] = $row;
        }

        return $livestreams;
    }

    /**
     * Get featured livestream (highest viewer count)
     */
    public static function getFeatured() {
        $conn = getDBConnection();

        $sql = "SELECT * FROM " . self::$table . " WHERE is_live = 1 ORDER BY viewer_count DESC LIMIT 1";
        $result = $conn->query($sql);

        return $result->fetch_assoc();
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

        return $result->fetch_assoc();
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

        return $result->fetch_assoc();
    }

    /**
     * Get livestreams by category
     */
    public static function getByCategory($categoryId) {
        $conn = getDBConnection();

        $sql = "SELECT * FROM " . self::$table . " WHERE category_id = ? AND is_live = 1 ORDER BY viewer_count DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $categoryId);
        $stmt->execute();
        $result = $stmt->get_result();

        $livestreams = [];
        while ($row = $result->fetch_assoc()) {
            $livestreams[] = $row;
        }

        return $livestreams;
    }

    /**
     * Create a new livestream
     */
    public static function create($data) {
        $conn = getDBConnection();

        $sql = "INSERT INTO " . self::$table . "
                (youtube_id, title, description, thumbnail_url, channel_title, channel_id, viewer_count, is_live, category_id, started_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

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
        
        $stmt->bind_param(
            "ssssssiiis",
            $youtube_id,
            $title,
            $description,
            $thumbnail_url,
            $channel_title,
            $channel_id,
            $viewer_count,
            $is_live,
            $category_id,
            $started_at
        );

        return $stmt->execute();
    }

    /**
     * Update livestream
     */
    public static function update($id, $data) {
        $conn = getDBConnection();

        $sql = "UPDATE " . self::$table . " SET
                title = ?, description = ?, thumbnail_url = ?, viewer_count = ?, is_live = ?
                WHERE id = ?";

        $stmt = $conn->prepare($sql);

        // Extract values to variables to avoid pass-by-reference issues
        $title = $data['title'] ?? '';
        $description = $data['description'] ?? '';
        $thumbnail_url = $data['thumbnail_url'] ?? '';
        $viewer_count = $data['viewer_count'] ?? 0;
        $is_live = $data['is_live'] ?? 1;

        $stmt->bind_param(
            "sssiii",
            $title,
            $description,
            $thumbnail_url,
            $viewer_count,
            $is_live,
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
