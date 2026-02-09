<?php
/**
 * Video Model
 * Handles video-related database operations
 */

require_once __DIR__ . '/../config/database.php';

class Video {
    private $conn;

    public function __construct() {
        $this->conn = getDBConnection();
    }

    /**
     * Get featured videos for homepage
     */
    public static function getFeaturedVideos($limit = 12) {
        $conn = getDBConnection();

        $stmt = $conn->prepare("SELECT v.*, c.name as category_name, c.slug as category_slug
                               FROM videos v
                               LEFT JOIN categories c ON v.category_id = c.id
                               WHERE v.is_active = 1 AND c.is_active = 1
                               ORDER BY v.view_count DESC, v.created_at DESC
                               LIMIT ?");
        $stmt->bind_param("i", $limit);
        $stmt->execute();

        $result = $stmt->get_result();

        $videos = [];
        while ($row = $result->fetch_assoc()) {
            $videos[] = self::formatVideoData($row);
        }

        return $videos;
    }

    /**
     * Get videos by category
     */
    public static function getByCategory($categoryId, $limit = null) {
        $conn = getDBConnection();

        $sql = "SELECT v.*, c.name as category_name, c.slug as category_slug
                FROM videos v
                LEFT JOIN categories c ON v.category_id = c.id
                WHERE v.category_id = ? AND v.is_active = 1 AND c.is_active = 1
                ORDER BY v.created_at DESC";

        if ($limit) {
            $sql .= " LIMIT ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $categoryId, $limit);
        } else {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $categoryId);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $videos = [];
        while ($row = $result->fetch_assoc()) {
            $videos[] = self::formatVideoData($row);
        }

        return $videos;
    }

    /**
     * Get video by ID
     */
    public static function getById($id) {
        $conn = getDBConnection();

        $stmt = $conn->prepare("SELECT v.*, c.name as category_name, c.slug as category_slug
                               FROM videos v
                               LEFT JOIN categories c ON v.category_id = c.id
                               WHERE v.id = ? AND v.is_active = 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        $result = $stmt->get_result();
        $video = $result->fetch_assoc();

        return $video ? self::formatVideoData($video) : null;
    }

    /**
     * Get video by YouTube ID
     */
    public static function getByYouTubeId($youtubeId) {
        $conn = getDBConnection();

        $stmt = $conn->prepare("SELECT v.*, c.name as category_name, c.slug as category_slug
                               FROM videos v
                               LEFT JOIN categories c ON v.category_id = c.id
                               WHERE v.youtube_id = ? AND v.is_active = 1");
        $stmt->bind_param("s", $youtubeId);
        $stmt->execute();

        $result = $stmt->get_result();
        $video = $result->fetch_assoc();

        return $video ? self::formatVideoData($video) : null;
    }

    /**
     * Search videos
     */
    public static function search($query, $limit = 20) {
        $conn = getDBConnection();

        $searchTerm = "%{$query}%";

        $stmt = $conn->prepare("SELECT v.*, c.name as category_name, c.slug as category_slug
                               FROM videos v
                               LEFT JOIN categories c ON v.category_id = c.id
                               WHERE v.is_active = 1 AND c.is_active = 1
                               AND (v.title LIKE ? OR v.description LIKE ? OR v.tags LIKE ?)
                               ORDER BY v.view_count DESC
                               LIMIT ?");
        $stmt->bind_param("sssi", $searchTerm, $searchTerm, $searchTerm, $limit);
        $stmt->execute();

        $result = $stmt->get_result();

        $videos = [];
        while ($row = $result->fetch_assoc()) {
            $videos[] = self::formatVideoData($row);
        }

        return $videos;
    }

    /**
     * Count total videos
     */
    public static function count() {
        $conn = getDBConnection();

        $result = $conn->query("SELECT COUNT(*) as count FROM videos WHERE is_active = 1");
        $row = $result->fetch_assoc();
        return $row['count'];
    }

    /**
     * Create new video
     */
    public static function create($data) {
        $conn = getDBConnection();

        $stmt = $conn->prepare("INSERT INTO videos
                               (youtube_id, title, description, thumbnail_url, duration, category_id, tags, channel_title, channel_id, published_at)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssissss",
            $data['youtube_id'],
            $data['title'],
            $data['description'],
            $data['thumbnail_url'],
            $data['duration'],
            $data['category_id'],
            $data['tags'],
            $data['channel_title'],
            $data['channel_id'],
            $data['published_at']
        );

        if ($stmt->execute()) {
            return $conn->insert_id;
        }

        return false;
    }

    /**
     * Update video
     */
    public static function update($id, $data) {
        $conn = getDBConnection();

        $stmt = $conn->prepare("UPDATE videos SET
                               title = ?, description = ?, thumbnail_url = ?, duration = ?,
                               category_id = ?, tags = ?, channel_title = ?, channel_id = ?, published_at = ?
                               WHERE id = ?");
        $stmt->bind_param("sssississi",
            $data['title'],
            $data['description'],
            $data['thumbnail_url'],
            $data['duration'],
            $data['category_id'],
            $data['tags'],
            $data['channel_title'],
            $data['channel_id'],
            $data['published_at'],
            $id
        );

        return $stmt->execute();
    }

    /**
     * Increment view count
     */
    public static function incrementViews($id) {
        $conn = getDBConnection();

        $stmt = $conn->prepare("UPDATE videos SET view_count = view_count + 1 WHERE id = ?");
        $stmt->bind_param("i", $id);

        return $stmt->execute();
    }

    /**
     * Increment like count
     */
    public static function incrementLikes($id) {
        $conn = getDBConnection();

        $stmt = $conn->prepare("UPDATE videos SET like_count = like_count + 1 WHERE id = ?");
        $stmt->bind_param("i", $id);

        return $stmt->execute();
    }

    /**
     * Decrement like count
     */
    public static function decrementLikes($id) {
        $conn = getDBConnection();

        $stmt = $conn->prepare("UPDATE videos SET like_count = GREATEST(like_count - 1, 0) WHERE id = ?");
        $stmt->bind_param("i", $id);

        return $stmt->execute();
    }

    /**
     * Check if user has liked a video
     */
    public static function hasUserLiked($videoId, $userId = null, $sessionId = null) {
        $conn = getDBConnection();

        $query = "SELECT id FROM video_stats WHERE video_id = ? AND action = 'like'";
        $params = [$videoId];
        $types = "i";

        if ($userId) {
            $query .= " AND user_id = ?";
            $params[] = $userId;
            $types .= "i";
        } elseif ($sessionId) {
            $query .= " AND session_id = ?";
            $params[] = $sessionId;
            $types .= "s";
        } else {
            return false; // Need either user_id or session_id
        }

        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        $result = $stmt->get_result();
        return $result->num_rows > 0;
    }

    /**
     * Add or remove like from video
     */
    public static function toggleLike($videoId, $userId = null, $sessionId = null, $userAgent = '', $ipAddress = '') {
        $conn = getDBConnection();

        // Check if user/session already liked this video
        $hasLiked = self::hasUserLiked($videoId, $userId, $sessionId);

        if ($hasLiked) {
            // Unlike: remove from video_stats and decrement count
            $query = "DELETE FROM video_stats WHERE video_id = ? AND action = 'like'";
            $params = [$videoId];
            $types = "i";

            if ($userId) {
                $query .= " AND user_id = ?";
                $params[] = $userId;
                $types .= "i";
            } elseif ($sessionId) {
                $query .= " AND session_id = ?";
                $params[] = $sessionId;
                $types .= "s";
            }

            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();

            // Decrement like count
            self::decrementLikes($videoId);

            return ['action' => 'unliked', 'liked' => false];
        } else {
            // Like: add to video_stats and increment count
            $stmt = $conn->prepare("INSERT INTO video_stats (video_id, user_id, session_id, action, user_agent, ip_address) VALUES (?, ?, ?, 'like', ?, ?)");
            $stmt->bind_param("iisss", $videoId, $userId, $sessionId, $userAgent, $ipAddress);
            $stmt->execute();

            // Increment like count
            self::incrementLikes($videoId);

            return ['action' => 'liked', 'liked' => true];
        }
    }

    /**
     * Delete video (soft delete)
     */
    public static function delete($id) {
        $conn = getDBConnection();

        $stmt = $conn->prepare("UPDATE videos SET is_active = 0 WHERE id = ?");
        $stmt->bind_param("i", $id);

        return $stmt->execute();
    }

    /**
     * Format video data for API response
     */
    private static function formatVideoData($video) {
        return [
            'id' => $video['id'],
            'youtube_id' => $video['youtube_id'],
            'title' => $video['title'],
            'description' => $video['description'],
            'thumbnail_url' => $video['thumbnail_url'],
            'duration' => $video['duration'],
            'category' => [
                'id' => $video['category_id'],
                'name' => $video['category_name'],
                'slug' => $video['category_slug']
            ],
            'tags' => json_decode($video['tags'] ?: '[]', true),
            'channel_title' => $video['channel_title'],
            'channel_id' => $video['channel_id'],
            'view_count' => (int)$video['view_count'],
            'like_count' => (int)$video['like_count'],
            'published_at' => $video['published_at'],
            'created_at' => $video['created_at']
        ];
    }
}
?>
