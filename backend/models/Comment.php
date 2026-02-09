<?php
/**
 * Comment Model
 * Handles comment data operations
 */

require_once __DIR__ . '/../config/database.php';

class Comment {
    private static $table = 'comments';

    /**
     * Get all comments for a video
     */
    public static function getByVideo($videoId, $page = 1, $limit = 20) {
        $conn = getDBConnection();

        $offset = ($page - 1) * $limit;

        $sql = "SELECT c.*, u.first_name, u.last_name, u.email
                FROM " . self::$table . " c
                LEFT JOIN users u ON c.user_id = u.id
                WHERE c.video_id = ? AND c.is_approved = 1
                ORDER BY c.created_at DESC
                LIMIT ? OFFSET ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $videoId, $limit, $offset);
        $stmt->execute();

        $result = $stmt->get_result();
        $comments = [];

        while ($row = $result->fetch_assoc()) {
            $comments[] = [
                'id' => $row['id'],
                'video_id' => $row['video_id'],
                'user_id' => $row['user_id'],
                'content' => $row['content'],
                'parent_id' => $row['parent_id'],
                'is_approved' => $row['is_approved'],
                'created_at' => $row['created_at'],
                'user' => [
                    'id' => $row['user_id'],
                    'name' => trim($row['first_name'] . ' ' . $row['last_name']),
                    'email' => $row['email']
                ]
            ];
        }

        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM " . self::$table . " WHERE video_id = ? AND is_approved = 1";
        $countStmt = $conn->prepare($countSql);
        $countStmt->bind_param("i", $videoId);
        $countStmt->execute();
        $countResult = $countStmt->get_result()->fetch_assoc();

        return [
            'comments' => $comments,
            'total' => $countResult['total'],
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($countResult['total'] / $limit)
        ];
    }

    /**
     * Get all comments for moderation (admin)
     */
    public static function getAllForModeration($page = 1, $limit = 50) {
        $conn = getDBConnection();

        $offset = ($page - 1) * $limit;

        $sql = "SELECT c.*, u.first_name, u.last_name, u.email, v.title as video_title
                FROM " . self::$table . " c
                LEFT JOIN users u ON c.user_id = u.id
                LEFT JOIN videos v ON c.video_id = v.id
                ORDER BY c.created_at DESC
                LIMIT ? OFFSET ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $limit, $offset);
        $stmt->execute();

        $result = $stmt->get_result();
        $comments = [];

        while ($row = $result->fetch_assoc()) {
            $comments[] = [
                'id' => $row['id'],
                'video_id' => $row['video_id'],
                'user_id' => $row['user_id'],
                'content' => $row['content'],
                'parent_id' => $row['parent_id'],
                'is_approved' => $row['is_approved'],
                'created_at' => $row['created_at'],
                'user' => [
                    'id' => $row['user_id'],
                    'name' => trim($row['first_name'] . ' ' . $row['last_name']),
                    'email' => $row['email']
                ],
                'video' => [
                    'id' => $row['video_id'],
                    'title' => $row['video_title']
                ]
            ];
        }

        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM " . self::$table;
        $countResult = $conn->query($countSql)->fetch_assoc();

        return [
            'comments' => $comments,
            'total' => $countResult['total'],
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($countResult['total'] / $limit)
        ];
    }

    /**
     * Create a new comment
     */
    public static function create($data) {
        $conn = getDBConnection();

        $sql = "INSERT INTO " . self::$table . "
                (video_id, user_id, content, parent_id, is_approved, created_at)
                VALUES (?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        $videoId = $data['video_id'];
        $userId = $data['user_id'];
        $content = trim($data['content']);
        $parentId = $data['parent_id'] ?? null;
        $isApproved = $data['is_approved'] ?? 1; // Auto-approve for now
        $createdAt = date('Y-m-d H:i:s');

        $stmt->bind_param("iisiss", $videoId, $userId, $content, $parentId, $isApproved, $createdAt);

        if ($stmt->execute()) {
            $commentId = $stmt->insert_id;

            // Create notification for video owner or mentioned users
            self::createCommentNotification($commentId, $videoId, $userId);

            return $commentId;
        }

        return false;
    }

    /**
     * Update comment
     */
    public static function update($id, $data) {
        $conn = getDBConnection();

        $sql = "UPDATE " . self::$table . " SET content = ?, is_approved = ? WHERE id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sii", $data['content'], $data['is_approved'], $id);

        return $stmt->execute();
    }

    /**
     * Delete comment
     */
    public static function delete($id) {
        $conn = getDBConnection();

        $sql = "DELETE FROM " . self::$table . " WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);

        return $stmt->execute();
    }

    /**
     * Approve comment (moderation)
     */
    public static function approve($id) {
        $conn = getDBConnection();

        $sql = "UPDATE " . self::$table . " SET is_approved = 1 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);

        return $stmt->execute();
    }

    /**
     * Reject comment (moderation)
     */
    public static function reject($id) {
        $conn = getDBConnection();

        $sql = "UPDATE " . self::$table . " SET is_approved = 0 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);

        return $stmt->execute();
    }

    /**
     * Get comment by ID
     */
    public static function find($id) {
        $conn = getDBConnection();

        $sql = "SELECT c.*, u.first_name, u.last_name, u.email
                FROM " . self::$table . " c
                LEFT JOIN users u ON c.user_id = u.id
                WHERE c.id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            return [
                'id' => $row['id'],
                'video_id' => $row['video_id'],
                'user_id' => $row['user_id'],
                'content' => $row['content'],
                'parent_id' => $row['parent_id'],
                'is_approved' => $row['is_approved'],
                'created_at' => $row['created_at'],
                'user' => [
                    'id' => $row['user_id'],
                    'name' => trim($row['first_name'] . ' ' . $row['last_name']),
                    'email' => $row['email']
                ]
            ];
        }

        return null;
    }

    /**
     * Get replies for a comment
     */
    public static function getReplies($parentId) {
        $conn = getDBConnection();

        $sql = "SELECT c.*, u.first_name, u.last_name, u.email
                FROM " . self::$table . " c
                LEFT JOIN users u ON c.user_id = u.id
                WHERE c.parent_id = ? AND c.is_approved = 1
                ORDER BY c.created_at ASC";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $parentId);
        $stmt->execute();

        $result = $stmt->get_result();
        $replies = [];

        while ($row = $result->fetch_assoc()) {
            $replies[] = [
                'id' => $row['id'],
                'video_id' => $row['video_id'],
                'user_id' => $row['user_id'],
                'content' => $row['content'],
                'parent_id' => $row['parent_id'],
                'is_approved' => $row['is_approved'],
                'created_at' => $row['created_at'],
                'user' => [
                    'id' => $row['user_id'],
                    'name' => trim($row['first_name'] . ' ' . $row['last_name']),
                    'email' => $row['email']
                ]
            ];
        }

        return $replies;
    }

    /**
     * Get comment count for a video
     */
    public static function getCount($videoId) {
        $conn = getDBConnection();

        $sql = "SELECT COUNT(*) as count FROM " . self::$table . " WHERE video_id = ? AND is_approved = 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $videoId);
        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        return $row['count'];
    }

    /**
     * Create notification when comment is posted
     */
    private static function createCommentNotification($commentId, $videoId, $userId) {
        try {
            // Get video information
            $conn = getDBConnection();
            $videoSql = "SELECT title FROM videos WHERE id = ?";
            $videoStmt = $conn->prepare($videoSql);
            $videoStmt->bind_param("i", $videoId);
            $videoStmt->execute();
            $videoResult = $videoStmt->get_result()->fetch_assoc();

            if ($videoResult) {
                // Create notification for all users who favorited this video (except the commenter)
                $favSql = "SELECT user_id FROM user_favorites WHERE video_id = ? AND user_id != ?";
                $favStmt = $conn->prepare($favSql);
                $favStmt->bind_param("ii", $videoId, $userId);
                $favStmt->execute();
                $favResult = $favStmt->get_result();

                while ($row = $favResult->fetch_assoc()) {
                    Notification::create([
                        'user_id' => $row['user_id'],
                        'type' => 'comment',
                        'title' => 'New comment on your favorite video',
                        'message' => 'Someone commented on "' . $videoResult['title'] . '"',
                        'data' => json_encode([
                            'video_id' => $videoId,
                            'comment_id' => $commentId,
                            'video_title' => $videoResult['title']
                        ])
                    ]);
                }
            }
        } catch (Exception $e) {
            // Log but don't fail the comment creation
            error_log("Failed to create comment notification: " . $e->getMessage());
        }
    }
}
?>