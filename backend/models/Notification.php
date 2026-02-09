<?php
/**
 * Notification Model
 * Handles notification-related database operations
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/PushSubscription.php';

class Notification {
    private $conn;

    public function __construct() {
        $this->conn = getDBConnection();
    }

    /**
     * Create a new notification
     */
    public static function create($data) {
        $conn = getDBConnection();

        $stmt = $conn->prepare("INSERT INTO notifications
                               (user_id, type, title, message, related_id, related_type)
                               VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssis",
            $data['user_id'],
            $data['type'],
            $data['title'],
            $data['message'],
            $data['related_id'],
            $data['related_type']
        );

        if ($stmt->execute()) {
            $notificationId = $conn->insert_id;

            // Send push notification in the background (don't block the response)
            try {
                self::sendPushNotification($data, $notificationId);
            } catch (Exception $e) {
                error_log("Failed to send push notification: " . $e->getMessage());
                // Don't fail the notification creation if push fails
            }

            return $notificationId;
        }

        return false;
    }

    /**
     * Get notifications for a user
     */
    public static function getUserNotifications($userId, $limit = 50, $onlyUnread = false) {
        $conn = getDBConnection();

        $sql = "SELECT * FROM notifications WHERE user_id = ?";
        if ($onlyUnread) {
            $sql .= " AND is_read = FALSE";
        }
        $sql .= " ORDER BY created_at DESC LIMIT ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $userId, $limit);
        $stmt->execute();

        $result = $stmt->get_result();
        $notifications = [];

        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }

        return $notifications;
    }

    /**
     * Mark notification as read
     */
    public static function markAsRead($notificationId, $userId) {
        $conn = getDBConnection();

        $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE
                               WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $notificationId, $userId);

        return $stmt->execute();
    }

    /**
     * Mark all notifications as read for a user
     */
    public static function markAllAsRead($userId) {
        $conn = getDBConnection();

        $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ?");
        $stmt->bind_param("i", $userId);

        return $stmt->execute();
    }

    /**
     * Delete notification
     */
    public static function delete($notificationId, $userId) {
        $conn = getDBConnection();

        $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $notificationId, $userId);

        return $stmt->execute();
    }

    /**
     * Get unread count for a user
     */
    public static function getUnreadCount($userId) {
        $conn = getDBConnection();

        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications
                               WHERE user_id = ? AND is_read = FALSE");
        $stmt->bind_param("i", $userId);
        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        return (int)$row['count'];
    }

    /**
     * Create notification for video favorites
     */
    public static function notifyVideoFavorited($videoId, $userId) {
        // Get video details
        $video = self::getVideoDetails($videoId);
        if (!$video) return false;

        $notifications = [];

        // Notify all users who have favorited this video (excluding the one who just favorited)
        $favoritedUsers = self::getUsersWhoFavoritedVideo($videoId);
        foreach ($favoritedUsers as $favUserId) {
            if ($favUserId != $userId) {
                $user = self::getUserDetails($userId);
                $notifications[] = [
                    'user_id' => $favUserId,
                    'type' => 'interaction',
                    'title' => 'Video Favorited',
                    'message' => $user['first_name'] . ' ' . $user['last_name'] . ' also favorited "' . $video['title'] . '"',
                    'related_id' => $videoId,
                    'related_type' => 'video'
                ];
            }
        }

        // Create notifications
        foreach ($notifications as $notification) {
            self::create($notification);
        }

        return count($notifications);
    }

    /**
     * Create notification for new video in subscribed category
     */
    public static function notifyNewVideoInCategory($videoId, $categoryId) {
        $video = self::getVideoDetails($videoId);
        $category = self::getCategoryDetails($categoryId);
        if (!$video || !$category) return false;

        $notifications = [];

        // Notify all users subscribed to this category
        $subscribedUsers = self::getUsersSubscribedToCategory($categoryId);
        foreach ($subscribedUsers as $userId) {
            $notifications[] = [
                'user_id' => $userId,
                'type' => 'subscription',
                'title' => 'New Video in ' . $category['name'],
                'message' => 'New video added: "' . $video['title'] . '" in ' . $category['name'],
                'related_id' => $videoId,
                'related_type' => 'video'
            ];
        }

        // Create notifications
        foreach ($notifications as $notification) {
            self::create($notification);
        }

        return count($notifications);
    }

    /**
     * Helper: Get video details
     */
    private static function getVideoDetails($videoId) {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT title, youtube_id FROM videos WHERE id = ?");
        $stmt->bind_param("i", $videoId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Helper: Get user details
     */
    private static function getUserDetails($userId) {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Helper: Get category details
     */
    private static function getCategoryDetails($categoryId) {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT name FROM categories WHERE id = ?");
        $stmt->bind_param("i", $categoryId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Helper: Get users who favorited a video
     */
    private static function getUsersWhoFavoritedVideo($videoId) {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT id FROM users WHERE JSON_CONTAINS(favorites, ?)");
        $videoIdJson = json_encode($videoId);
        $stmt->bind_param("s", $videoIdJson);
        $stmt->execute();

        $result = $stmt->get_result();
        $userIds = [];
        while ($row = $result->fetch_assoc()) {
            $userIds[] = $row['id'];
        }
        return $userIds;
    }

    /**
     * Helper: Get users subscribed to a category
     */
    private static function getUsersSubscribedToCategory($categoryId) {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT id FROM users WHERE JSON_CONTAINS(my_channels, ?)");
        $categoryIdJson = json_encode($categoryId);
        $stmt->bind_param("s", $categoryIdJson);
        $stmt->execute();

        $result = $stmt->get_result();
        $userIds = [];
        while ($row = $result->fetch_assoc()) {
            $userIds[] = $row['id'];
        }
        return $userIds;
    }

    /**
     * Send push notification for a newly created notification
     */
    private static function sendPushNotification($notificationData, $notificationId) {
        // Send push notification to the user
        $successCount = PushSubscription::sendToUser(
            $notificationData['user_id'],
            $notificationData['title'],
            $notificationData['message'],
            [
                'notificationId' => $notificationId,
                'type' => $notificationData['type'],
                'relatedId' => $notificationData['related_id'],
                'relatedType' => $notificationData['related_type']
            ]
        );

        if ($successCount > 0) {
            error_log("Push notification sent to $successCount devices for notification $notificationId");
        }
    }
}