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
     * Get recent videos ordered by creation date
     */
    public static function getRecentVideos($limit = 12) {
        $conn = getDBConnection();

        $stmt = $conn->prepare("SELECT v.*, c.name as category_name, c.slug as category_slug
                               FROM videos v
                               LEFT JOIN categories c ON v.category_id = c.id
                               WHERE v.is_active = 1 AND c.is_active = 1
                               ORDER BY v.created_at DESC
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
     * Batch delete videos (soft delete)
     */
    public static function batchDelete($ids) {
        if (empty($ids)) {
            return false;
        }

        $conn = getDBConnection();

        // Create placeholders for the IN clause
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $types = str_repeat('i', count($ids));

        $stmt = $conn->prepare("UPDATE videos SET is_active = 0 WHERE id IN ($placeholders)");
        $stmt->bind_param($types, ...$ids);

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

    /**
     * Get video with translations for a specific language
     */
    public static function getWithTranslations($videoId, $languageCode = null) {
        $video = self::find($videoId);

        if (!$video || !$languageCode || $languageCode === 'en') {
            return $video;
        }

        // Get translations for this video
        $conn = getDBConnection();
        $sql = "SELECT ct.field_name, ct.translated_text
                FROM content_translations ct
                JOIN languages l ON ct.language_id = l.id
                WHERE ct.content_type = 'video'
                AND ct.content_id = ?
                AND l.code = ?
                AND ct.field_name IN ('title', 'description')";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $videoId, $languageCode);
        $stmt->execute();

        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            if ($row['field_name'] === 'title') {
                $video['translated_title'] = $row['translated_text'];
            } elseif ($row['field_name'] === 'description') {
                $video['translated_description'] = $row['translated_text'];
            }
        }

        return $video;
    }

    /**
     * Get available subtitles for a video
     */
    public static function getSubtitles($videoId) {
        $conn = getDBConnection();

        $sql = "SELECT l.code as language_code, l.name as language_name,
                       l.native_name, l.flag_emoji as flag,
                       CONCAT('/subtitles/', ?, '_', l.code, '.vtt') as subtitle_url
                FROM languages l
                WHERE l.is_active = 1
                AND EXISTS (
                    SELECT 1 FROM content_translations ct
                    WHERE ct.content_type = 'video'
                    AND ct.content_id = ?
                    AND ct.language_id = l.id
                    AND ct.field_name = 'subtitles'
                )
                ORDER BY l.sort_order";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $videoId, $videoId);
        $stmt->execute();

        $result = $stmt->get_result();
        $subtitles = [];

        while ($row = $result->fetch_assoc()) {
            $subtitles[] = $row;
        }

        return $subtitles;
    }

    /**
     * Set content translation for a video
     */
    public static function setTranslation($videoId, $languageCode, $field, $translatedText) {
        require_once __DIR__ . '/Language.php';

        return Language::setContentTranslation('video', $videoId, $languageCode, $field, $translatedText);
    }

    /**
     * Get videos with language filtering
     */
    public static function getByLanguage($languageCode, $limit = 20, $offset = 0) {
        $conn = getDBConnection();

        // Get videos that have translations in the requested language
        $sql = "SELECT DISTINCT v.*, c.name as category_name, c.slug as category_slug
                FROM videos v
                LEFT JOIN categories c ON v.category_id = c.id
                LEFT JOIN content_translations ct ON ct.content_type = 'video' AND ct.content_id = v.id
                LEFT JOIN languages l ON ct.language_id = l.id
                WHERE v.is_active = 1
                AND (v.original_language = ? OR l.code = ?)
                ORDER BY v.created_at DESC
                LIMIT ? OFFSET ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssii", $languageCode, $languageCode, $limit, $offset);
        $stmt->execute();

        $result = $stmt->get_result();
        $videos = [];

        while ($row = $result->fetch_assoc()) {
            $videos[] = self::formatVideoData($row);
        }

        return $videos;
    }

    /**
     * Get content statistics by language
     */
    public static function getLanguageStats() {
        $conn = getDBConnection();

        $stats = [];

        // Videos by original language
        $sql = "SELECT original_language, COUNT(*) as count
                FROM videos
                WHERE is_active = 1 AND original_language IS NOT NULL
                GROUP BY original_language
                ORDER BY count DESC";

        $result = $conn->query($sql);
        $stats['by_original_language'] = [];

        while ($row = $result->fetch_assoc()) {
            $stats['by_original_language'][] = $row;
        }

        // Translated content
        $sql = "SELECT l.code, l.name, COUNT(ct.id) as translations_count
                FROM languages l
                LEFT JOIN content_translations ct ON l.id = ct.language_id AND ct.content_type = 'video'
                WHERE l.is_active = 1
                GROUP BY l.id, l.code, l.name
                ORDER BY l.sort_order";

        $result = $conn->query($sql);
        $stats['translations_by_language'] = [];

        while ($row = $result->fetch_assoc()) {
            $stats['translations_by_language'][] = $row;
        }

        // Videos with subtitles
        $stats['videos_with_subtitles'] = 0;
        $result = $conn->query("SELECT COUNT(DISTINCT content_id) as count FROM content_translations WHERE content_type = 'video' AND field_name = 'subtitles'");
        if ($result) {
            $stats['videos_with_subtitles'] = $result->fetch_assoc()['count'];
        }

        return $stats;
    }

    /**
     * Update video language metadata
     */
    public static function updateLanguageMetadata($videoId, $originalLanguage, $hasSubtitles = null, $subtitleLanguages = null) {
        $conn = getDBConnection();

        $sql = "UPDATE videos SET original_language = ?";
        $params = [$originalLanguage];
        $types = "s";

        if ($hasSubtitles !== null) {
            $sql .= ", has_subtitles = ?";
            $params[] = $hasSubtitles;
            $types .= "i";
        }

        if ($subtitleLanguages !== null) {
            $jsonLanguages = json_encode($subtitleLanguages);
            $sql .= ", subtitle_languages = ?";
            $params[] = $jsonLanguages;
            $types .= "s";
        }

        $sql .= " WHERE id = ?";
        $params[] = $videoId;
        $types .= "i";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);

        return $stmt->execute();
    }
}
?>
