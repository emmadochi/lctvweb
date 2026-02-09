<?php
/**
 * Reaction Model
 * Handles reaction (like, love, pray, amen, etc.) data operations
 */

require_once __DIR__ . '/../config/database.php';

class Reaction {
    private static $table = 'reactions';

    // Available reaction types
    const TYPES = [
        'like' => '👍',
        'love' => '❤️',
        'pray' => '🙏',
        'amen' => '✨',
        'praise' => '🙌',
        'thankful' => '🙏',
        'insightful' => '💡'
    ];

    /**
     * Get reactions for a video
     */
    public static function getByVideo($videoId) {
        $conn = getDBConnection();

        $sql = "SELECT reaction_type, COUNT(*) as count
                FROM " . self::$table . "
                WHERE video_id = ?
                GROUP BY reaction_type
                ORDER BY count DESC";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $videoId);
        $stmt->execute();

        $result = $stmt->get_result();
        $reactions = [];

        while ($row = $result->fetch_assoc()) {
            $reactions[$row['reaction_type']] = [
                'type' => $row['reaction_type'],
                'emoji' => self::TYPES[$row['reaction_type']] ?? '👍',
                'count' => (int)$row['count']
            ];
        }

        return $reactions;
    }

    /**
     * Get user's reaction for a video
     */
    public static function getUserReaction($videoId, $userId) {
        $conn = getDBConnection();

        $sql = "SELECT reaction_type, created_at
                FROM " . self::$table . "
                WHERE video_id = ? AND user_id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $videoId, $userId);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            return [
                'type' => $row['reaction_type'],
                'emoji' => self::TYPES[$row['reaction_type']] ?? '👍',
                'created_at' => $row['created_at']
            ];
        }

        return null;
    }

    /**
     * Add or update reaction
     */
    public static function react($videoId, $userId, $reactionType) {
        // Validate reaction type
        if (!array_key_exists($reactionType, self::TYPES)) {
            throw new Exception('Invalid reaction type');
        }

        $conn = getDBConnection();

        // Check if user already reacted to this video
        $existing = self::getUserReaction($videoId, $userId);

        if ($existing) {
            if ($existing['type'] === $reactionType) {
                // Same reaction - remove it (toggle off)
                return self::removeReaction($videoId, $userId);
            } else {
                // Different reaction - update it
                return self::updateReaction($videoId, $userId, $reactionType);
            }
        } else {
            // New reaction - add it
            return self::addReaction($videoId, $userId, $reactionType);
        }
    }

    /**
     * Add new reaction
     */
    private static function addReaction($videoId, $userId, $reactionType) {
        $conn = getDBConnection();

        $sql = "INSERT INTO " . self::$table . "
                (video_id, user_id, reaction_type, created_at)
                VALUES (?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $createdAt = date('Y-m-d H:i:s');
        $stmt->bind_param("iiss", $videoId, $userId, $reactionType, $createdAt);

        if ($stmt->execute()) {
            // Create notification for video owner/fans
            self::createReactionNotification($videoId, $userId, $reactionType);

            return [
                'action' => 'added',
                'reaction_type' => $reactionType,
                'emoji' => self::TYPES[$reactionType]
            ];
        }

        return false;
    }

    /**
     * Update existing reaction
     */
    private static function updateReaction($videoId, $userId, $reactionType) {
        $conn = getDBConnection();

        $sql = "UPDATE " . self::$table . "
                SET reaction_type = ?, created_at = ?
                WHERE video_id = ? AND user_id = ?";

        $stmt = $conn->prepare($sql);
        $updatedAt = date('Y-m-d H:i:s');
        $stmt->bind_param("ssii", $reactionType, $updatedAt, $videoId, $userId);

        if ($stmt->execute()) {
            return [
                'action' => 'updated',
                'reaction_type' => $reactionType,
                'emoji' => self::TYPES[$reactionType]
            ];
        }

        return false;
    }

    /**
     * Remove reaction
     */
    private static function removeReaction($videoId, $userId) {
        $conn = getDBConnection();

        $sql = "DELETE FROM " . self::$table . " WHERE video_id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $videoId, $userId);

        if ($stmt->execute()) {
            return [
                'action' => 'removed'
            ];
        }

        return false;
    }

    /**
     * Get reaction statistics for a video
     */
    public static function getStats($videoId) {
        $reactions = self::getByVideo($videoId);

        $total = 0;
        $topReaction = null;
        $reactionCounts = [];

        foreach ($reactions as $reaction) {
            $total += $reaction['count'];
            $reactionCounts[$reaction['type']] = $reaction['count'];

            if (!$topReaction || $reaction['count'] > $topReaction['count']) {
                $topReaction = $reaction;
            }
        }

        return [
            'total' => $total,
            'reactions' => $reactions,
            'top_reaction' => $topReaction,
            'counts' => $reactionCounts
        ];
    }

    /**
     * Get reaction summary for multiple videos
     */
    public static function getBulkStats($videoIds) {
        if (empty($videoIds)) {
            return [];
        }

        $conn = getDBConnection();

        $placeholders = str_repeat('?,', count($videoIds) - 1) . '?';
        $sql = "SELECT video_id, reaction_type, COUNT(*) as count
                FROM " . self::$table . "
                WHERE video_id IN ($placeholders)
                GROUP BY video_id, reaction_type
                ORDER BY video_id, count DESC";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(str_repeat('i', count($videoIds)), ...$videoIds);
        $stmt->execute();

        $result = $stmt->get_result();
        $stats = [];

        while ($row = $result->fetch_assoc()) {
            $videoId = $row['video_id'];

            if (!isset($stats[$videoId])) {
                $stats[$videoId] = [
                    'total' => 0,
                    'reactions' => [],
                    'top_reaction' => null
                ];
            }

            $reaction = [
                'type' => $row['reaction_type'],
                'emoji' => self::TYPES[$row['reaction_type']] ?? '👍',
                'count' => (int)$row['count']
            ];

            $stats[$videoId]['reactions'][$row['reaction_type']] = $reaction;
            $stats[$videoId]['total'] += $reaction['count'];

            if (!$stats[$videoId]['top_reaction'] ||
                $reaction['count'] > $stats[$videoId]['top_reaction']['count']) {
                $stats[$videoId]['top_reaction'] = $reaction;
            }
        }

        return $stats;
    }

    /**
     * Create notification when reaction is added
     */
    private static function createReactionNotification($videoId, $userId, $reactionType) {
        try {
            // Get video information
            $conn = getDBConnection();
            $videoSql = "SELECT title FROM videos WHERE id = ?";
            $videoStmt = $conn->prepare($videoSql);
            $videoStmt->bind_param("i", $videoId);
            $videoStmt->execute();
            $videoResult = $videoStmt->get_result()->fetch_assoc();

            if ($videoResult) {
                // Create notification for all users who favorited this video (except the reactor)
                $favSql = "SELECT user_id FROM user_favorites WHERE video_id = ? AND user_id != ?";
                $favStmt = $conn->prepare($favSql);
                $favStmt->bind_param("ii", $videoId, $userId);
                $favStmt->execute();
                $favResult = $favStmt->get_result();

                $emoji = self::TYPES[$reactionType] ?? '👍';

                while ($row = $favResult->fetch_assoc()) {
                    Notification::create([
                        'user_id' => $row['user_id'],
                        'type' => 'reaction',
                        'title' => 'Someone reacted to your favorite video',
                        'message' => 'Someone reacted with ' . $emoji . ' to "' . $videoResult['title'] . '"',
                        'data' => json_encode([
                            'video_id' => $videoId,
                            'reaction_type' => $reactionType,
                            'reaction_emoji' => $emoji,
                            'video_title' => $videoResult['title']
                        ])
                    ]);
                }
            }
        } catch (Exception $e) {
            // Log but don't fail the reaction creation
            error_log("Failed to create reaction notification: " . $e->getMessage());
        }
    }

    /**
     * Clean up old reactions (optional maintenance)
     */
    public static function cleanupOldReactions($daysOld = 365) {
        $conn = getDBConnection();

        $sql = "DELETE FROM " . self::$table . "
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $daysOld);

        return $stmt->execute();
    }
}
?>