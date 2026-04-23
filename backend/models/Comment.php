<?php
/**
 * Comment Model
 * Handles comment data operations
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Notification.php';

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
            $name = trim($row['first_name'] . ' ' . $row['last_name']);
            $comments[] = [
                'id' => $row['id'],
                'video_id' => $row['video_id'],
                'livestream_id' => $row['livestream_id'],
                'user_id' => $row['user_id'],
                'user_name' => $name, // Flattened for mobile
                'user_avatar' => null, // Placeholder as users table has no profile_picture column yet
                'content' => $row['content'],
                'parent_id' => $row['parent_id'],
                'is_approved' => $row['is_approved'],
                'created_at' => $row['created_at'],
                'user' => [
                    'id' => $row['user_id'],
                    'name' => $name,
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
     * Get all comments for a livestream
     */
    public static function getByLivestream($livestreamId, $page = 1, $limit = 20) {
        $conn = getDBConnection();

        $offset = ($page - 1) * $limit;

        $sql = "SELECT c.*, u.first_name, u.last_name, u.email
                FROM " . self::$table . " c
                LEFT JOIN users u ON c.user_id = u.id
                WHERE c.livestream_id = ? AND c.is_approved = 1
                ORDER BY c.created_at DESC
                LIMIT ? OFFSET ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $livestreamId, $limit, $offset);
        $stmt->execute();

        $result = $stmt->get_result();
        $comments = [];

        while ($row = $result->fetch_assoc()) {
            $name = trim($row['first_name'] . ' ' . $row['last_name']);
            $comments[] = [
                'id' => $row['id'],
                'video_id' => $row['video_id'],
                'livestream_id' => $row['livestream_id'],
                'user_id' => $row['user_id'],
                'user_name' => $name, // Flattened for mobile
                'user_avatar' => null, // Placeholder
                'content' => $row['content'],
                'parent_id' => $row['parent_id'],
                'is_approved' => $row['is_approved'],
                'created_at' => $row['created_at'],
                'user' => [
                    'id' => $row['user_id'],
                    'name' => $name,
                    'email' => $row['email']
                ]
            ];
        }

        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM " . self::$table . " WHERE livestream_id = ? AND is_approved = 1";
        $countStmt = $conn->prepare($countSql);
        $countStmt->bind_param("i", $livestreamId);
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

        $videoId = !empty($data['video_id']) ? (int)$data['video_id'] : null;
        $livestreamId = !empty($data['livestream_id']) ? (int)$data['livestream_id'] : null;
        $userId = (int)$data['user_id'];
        $content = trim($data['content']);
        $parentId = !empty($data['parent_id']) ? (int)$data['parent_id'] : null;
        $isApproved = isset($data['is_approved']) ? (int)$data['is_approved'] : 1; 
        $createdAt = date('Y-m-d H:i:s');

        // Dynamically build the VALUES array to ensure literal NULLs are passed when variables are null
        // This prevents mysqli bind_param from casting null integer bindings to 0, which breaks foreign keys.
        $valuesStr = sprintf(
            "%s, %s, ?, ?, %s, ?, ?",
            $videoId ? '?' : 'NULL',
            $livestreamId ? '?' : 'NULL',
            $parentId ? '?' : 'NULL'
        );

        $sql = "INSERT INTO " . self::$table . "
                (video_id, livestream_id, user_id, content, parent_id, is_approved, created_at)
                VALUES ($valuesStr)";

        $stmt = $conn->prepare($sql);

        // Build dynamic types and values for bind_param
        $types = "";
        $params = [];
        
        if ($videoId) { $types .= "i"; $params[] = $videoId; }
        if ($livestreamId) { $types .= "i"; $params[] = $livestreamId; }
        
        $types .= "is"; // userId, content
        $params[] = $userId;
        $params[] = $content;

        if ($parentId) { $types .= "i"; $params[] = $parentId; }
        
        $types .= "is"; // isApproved, createdAt
        $params[] = $isApproved;
        $params[] = $createdAt;

        // Use reflection or call_user_func_array to bind dynamic params
        $bindNames = [$types];
        for ($i = 0; $i < count($params); $i++) {
            $bindName = 'bind' . $i;
            $$bindName = $params[$i];
            $bindNames[] = &$$bindName;
        }
        
        call_user_func_array([$stmt, 'bind_param'], $bindNames);

        if ($stmt->execute()) {
            $commentId = $stmt->insert_id;

            // Create notification for video/livestream owner or mentioned users
            self::createCommentNotification($commentId, $videoId, $livestreamId, $userId);

            return $commentId;
        } else {
            error_log("Comment INSERT error: " . $stmt->error);
            error_log("Params: videoId=$videoId, livestreamId=$livestreamId, userId=$userId, parentId=$parentId");
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
    private static function createCommentNotification($commentId, $videoId, $livestreamId, $userId) {
        try {
            $conn = getDBConnection();
            $title = '';
            
            if ($videoId) {
                // Get video information
                $videoSql = "SELECT title FROM videos WHERE id = ?";
                $videoStmt = $conn->prepare($videoSql);
                $videoStmt->bind_param("i", $videoId);
                $videoStmt->execute();
                $result = $videoStmt->get_result()->fetch_assoc();
                if ($result) {
                    $title = $result['title'];
                    
                    // Create notification for all users who favorited this video
                    $favSql = "SELECT user_id FROM user_favorites WHERE video_id = ? AND user_id != ?";
                    $favStmt = $conn->prepare($favSql);
                    $favStmt->bind_param("ii", $videoId, $userId);
                    $favStmt->execute();
                    $favResult = $favStmt->get_result();

                    while ($row = $favResult->fetch_assoc()) {
                        Notification::create([
                            'user_id' => $row['user_id'],
                            'type' => 'comment',
                            'title' => 'New comment on a video',
                            'message' => 'Someone commented on "' . $title . '"',
                            'related_id' => $videoId,
                            'related_type' => 'video'
                        ]);
                    }
                }
            } elseif ($livestreamId) {
                // Get livestream information
                $liveSql = "SELECT title FROM livestreams WHERE id = ?";
                $liveStmt = $conn->prepare($liveSql);
                $liveStmt->bind_param("i", $livestreamId);
                $liveStmt->execute();
                $result = $liveStmt->get_result()->fetch_assoc();
                if ($result) {
                    $title = $result['title'];
                    
                    // For livestreams, maybe notify admin or just log (livestreams might not have favorites yet)
                    // You could add logic here if livestreams have favorites
                }
            }
        } catch (Exception $e) {
            // Log but don't fail the comment creation
            error_log("Failed to create comment notification: " . $e->getMessage());
        }
    }
}
?>