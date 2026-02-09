<?php
/**
 * User Model
 * Handles user-related database operations
 */

require_once __DIR__ . '/../config/database.php';

class User {
    private $conn;

    public function __construct() {
        $this->conn = getDBConnection();
    }

    /**
     * Find user by email
     */
    public static function findByEmail($email) {
        $conn = getDBConnection();

        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();

        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    /**
     * Get user by ID
     */
    public static function getById($id) {
        $conn = getDBConnection();

        $stmt = $conn->prepare("SELECT id, email, first_name, last_name, role, is_active, watch_history, favorites, my_channels, created_at, updated_at FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    /**
     * Create new user
     */
    public static function create($data) {
        $conn = getDBConnection();

        $stmt = $conn->prepare("INSERT INTO users (email, password_hash, first_name, last_name, role)
                               VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss",
            $data['email'],
            $data['password'],
            $data['first_name'],
            $data['last_name'],
            $data['role']
        );

        if ($stmt->execute()) {
            return $conn->insert_id;
        }

        return false;
    }

    /**
     * Update user profile
     */
    public static function updateProfile($id, $data) {
        $conn = getDBConnection();

        $stmt = $conn->prepare("UPDATE users SET
                               first_name = ?, last_name = ?, updated_at = CURRENT_TIMESTAMP
                               WHERE id = ?");
        $stmt->bind_param("ssi",
            $data['first_name'],
            $data['last_name'],
            $id
        );

        return $stmt->execute();
    }

    /**
     * Update user password
     */
    public static function updatePassword($id, $currentPassword, $newPassword) {
        $conn = getDBConnection();

        // First verify current password
        $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if (!$user) {
            return false;
        }

        // Verify current password
        if (!password_verify($currentPassword, $user['password_hash'])) {
            return false; // Current password is incorrect
        }

        // Hash new password
        $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

        // Update password
        $stmt = $conn->prepare("UPDATE users SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->bind_param("si", $newPasswordHash, $id);

        return $stmt->execute();
    }

    /**
     * Add to favorites
     */
    public static function addToFavorites($userId, $videoId) {
        $conn = getDBConnection();

        $user = self::getById($userId);
        $favorites = json_decode($user['favorites'] ?: '[]', true);

        if (!in_array($videoId, $favorites)) {
            $favorites[] = $videoId;

            $stmt = $conn->prepare("UPDATE users SET favorites = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $favoritesJson = json_encode($favorites);
            $stmt->bind_param("si", $favoritesJson, $userId);

            return $stmt->execute();
        }

        return true; // Already in favorites
    }

    /**
     * Remove from favorites
     */
    public static function removeFromFavorites($userId, $videoId) {
        $conn = getDBConnection();

        $user = self::getById($userId);
        $favorites = json_decode($user['favorites'] ?: '[]', true);

        $favorites = array_filter($favorites, function($id) use ($videoId) {
            return $id != $videoId;
        });

        $stmt = $conn->prepare("UPDATE users SET favorites = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $favoritesJson = json_encode(array_values($favorites));
        $stmt->bind_param("si", $favoritesJson, $userId);

        return $stmt->execute();
    }

    /**
     * Get user favorites with video details
     */
    public static function getFavorites($userId) {
        $conn = getDBConnection();

        $user = self::getById($userId);
        $favorites = json_decode($user['favorites'] ?: '[]', true);

        if (empty($favorites)) {
            return [];
        }

        // Get videos by IDs
        $placeholders = str_repeat('?,', count($favorites) - 1) . '?';
        $sql = "SELECT v.*, c.name as category_name, c.slug as category_slug
                FROM videos v
                LEFT JOIN categories c ON v.category_id = c.id
                WHERE v.id IN ($placeholders) AND v.is_active = 1";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(str_repeat('i', count($favorites)), ...$favorites);
        $stmt->execute();

        $result = $stmt->get_result();
        $videos = [];

        while ($row = $result->fetch_assoc()) {
            $videos[] = [
                'id' => $row['id'],
                'youtube_id' => $row['youtube_id'],
                'title' => $row['title'],
                'description' => $row['description'],
                'thumbnail_url' => $row['thumbnail_url'],
                'duration' => $row['duration'],
                'category' => [
                    'id' => $row['category_id'],
                    'name' => $row['category_name'],
                    'slug' => $row['category_slug']
                ]
            ];
        }

        return $videos;
    }

    /**
     * Update user watch history
     */
    public static function updateWatchHistory($userId, $videoId) {
        $conn = getDBConnection();

        // Get current watch history
        $user = self::getById($userId);
        $history = json_decode($user['watch_history'] ?: '[]', true);

        // Remove if already exists (to move to front)
        $history = array_filter($history, function($item) use ($videoId) {
            return $item['video_id'] != $videoId;
        });

        // Add to beginning
        array_unshift($history, [
            'video_id' => $videoId,
            'watched_at' => date('c')
        ]);

        // Keep only last 100 items
        $history = array_slice($history, 0, 100);

        $stmt = $conn->prepare("UPDATE users SET watch_history = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $historyJson = json_encode($history);
        $stmt->bind_param("si", $historyJson, $userId);

        return $stmt->execute();
    }

    /**
     * Clear user watch history
     */
    public static function clearWatchHistory($userId) {
        $conn = getDBConnection();

        $emptyHistory = json_encode([]);
        $stmt = $conn->prepare("UPDATE users SET watch_history = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->bind_param("si", $emptyHistory, $userId);

        return $stmt->execute();
    }

    /**
     * Remove a single video from watch history
     */
    public static function removeFromWatchHistory($userId, $videoId) {
        $conn = getDBConnection();

        $user = self::getById($userId);
        $history = json_decode($user['watch_history'] ?: '[]', true);

        if (empty($history)) {
            return true;
        }

        $history = array_filter($history, function($item) use ($videoId) {
            return $item['video_id'] != $videoId;
        });

        $stmt = $conn->prepare("UPDATE users SET watch_history = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $historyJson = json_encode(array_values($history));
        $stmt->bind_param("si", $historyJson, $userId);

        return $stmt->execute();
    }

    /**
     * Get user watch history with video details
     */
    public static function getWatchHistory($userId, $limit = 50) {
        $conn = getDBConnection();

        $user = self::getById($userId);
        $history = json_decode($user['watch_history'] ?: '[]', true);

        if (empty($history)) {
            return [];
        }

        // Get video IDs from history
        $videoIds = array_column(array_slice($history, 0, $limit), 'video_id');

        if (empty($videoIds)) {
            return [];
        }

        // Get videos by IDs in the order they appear in history
        $placeholders = str_repeat('?,', count($videoIds) - 1) . '?';
        $sql = "SELECT v.*, c.name as category_name, c.slug as category_slug
                FROM videos v
                LEFT JOIN categories c ON v.category_id = c.id
                WHERE v.id IN ($placeholders) AND v.is_active = 1
                ORDER BY FIELD(v.id, " . implode(',', $videoIds) . ")";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(str_repeat('i', count($videoIds)), ...$videoIds);
        $stmt->execute();

        $result = $stmt->get_result();

        $videos = [];
        while ($row = $result->fetch_assoc()) {
            $videos[] = [
                'id' => $row['id'],
                'youtube_id' => $row['youtube_id'],
                'title' => $row['title'],
                'description' => $row['description'],
                'thumbnail_url' => $row['thumbnail_url'],
                'duration' => $row['duration'],
                'category' => [
                    'id' => $row['category_id'],
                    'name' => $row['category_name'],
                    'slug' => $row['category_slug']
                ]
            ];
        }

        return $videos;
    }

    /**
     * Add to my channels
     */
    public static function addToMyChannels($userId, $categoryId) {
        $conn = getDBConnection();

        $user = self::getById($userId);
        $channels = json_decode($user['my_channels'] ?: '[]', true);

        if (!in_array($categoryId, $channels)) {
            $channels[] = $categoryId;

            $stmt = $conn->prepare("UPDATE users SET my_channels = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $channelsJson = json_encode($channels);
            $stmt->bind_param("si", $channelsJson, $userId);

            return $stmt->execute();
        }

        return true; // Already in channels
    }

    /**
     * Remove from my channels
     */
    public static function removeFromMyChannels($userId, $categoryId) {
        $conn = getDBConnection();

        $user = self::getById($userId);
        $channels = json_decode($user['my_channels'] ?: '[]', true);

        $channels = array_filter($channels, function($id) use ($categoryId) {
            return $id != $categoryId;
        });

        $stmt = $conn->prepare("UPDATE users SET my_channels = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $channelsJson = json_encode(array_values($channels));
        $stmt->bind_param("si", $channelsJson, $userId);

        return $stmt->execute();
    }

    /**
     * Get user my channels with category details
     */
    public static function getMyChannels($userId) {
        $conn = getDBConnection();

        $user = self::getById($userId);
        $channels = json_decode($user['my_channels'] ?: '[]', true);

        if (empty($channels)) {
            return [];
        }

        // Get categories by IDs
        $placeholders = str_repeat('?,', count($channels) - 1) . '?';
        $sql = "SELECT * FROM categories WHERE id IN ($placeholders) AND is_active = 1";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(str_repeat('i', count($channels)), ...$channels);
        $stmt->execute();

        $result = $stmt->get_result();

        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }

        return $categories;
    }
}