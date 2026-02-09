<?php
/**
 * Notification Controller
 * Handles notification-related API requests
 */

require_once __DIR__ . '/../models/Notification.php';
require_once __DIR__ . '/../utils/Auth.php';
require_once __DIR__ . '/../utils/Response.php';

class NotificationController {

    /**
     * Get user's notifications
     */
    public function getNotifications() {
        $userId = Auth::getCurrentUserId();
        if (!$userId) {
            Response::unauthorized('Authentication required');
            return;
        }

        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $onlyUnread = isset($_GET['unread']) && $_GET['unread'] === 'true';

        $notifications = Notification::getUserNotifications($userId, $limit, $onlyUnread);

        Response::success([
            'notifications' => $notifications,
            'unread_count' => Notification::getUnreadCount($userId)
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead() {
        $userId = Auth::getCurrentUserId();
        if (!$userId) {
            Response::unauthorized('Authentication required');
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $notificationId = $data['notification_id'] ?? null;

        if (!$notificationId) {
            Response::badRequest('Notification ID required');
            return;
        }

        $success = Notification::markAsRead($notificationId, $userId);

        if ($success) {
            Response::success([
                'message' => 'Notification marked as read',
                'unread_count' => Notification::getUnreadCount($userId)
            ]);
        } else {
            Response::error('Failed to mark notification as read', 500);
        }
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead() {
        $userId = Auth::getCurrentUserId();
        if (!$userId) {
            Response::unauthorized('Authentication required');
            return;
        }

        $success = Notification::markAllAsRead($userId);

        if ($success) {
            Response::success([
                'message' => 'All notifications marked as read',
                'unread_count' => 0
            ]);
        } else {
            Response::error('Failed to mark all notifications as read', 500);
        }
    }

    /**
     * Delete notification
     */
    public function deleteNotification() {
        $userId = Auth::getCurrentUserId();
        if (!$userId) {
            Response::unauthorized('Authentication required');
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $notificationId = $data['notification_id'] ?? null;

        if (!$notificationId) {
            Response::badRequest('Notification ID required');
            return;
        }

        $success = Notification::delete($notificationId, $userId);

        if ($success) {
            Response::success([
                'message' => 'Notification deleted',
                'unread_count' => Notification::getUnreadCount($userId)
            ]);
        } else {
            Response::error('Failed to delete notification', 500);
        }
    }

    /**
     * Get unread count
     */
    public function getUnreadCount() {
        $userId = Auth::getCurrentUserId();
        if (!$userId) {
            Response::unauthorized('Authentication required');
            return;
        }

        $count = Notification::getUnreadCount($userId);
        Response::success(['unread_count' => $count]);
    }

    /**
     * Create a system notification (admin function)
     */
    public function createSystemNotification() {
        $userId = Auth::getCurrentUserId();
        if (!$userId) {
            Response::unauthorized('Authentication required');
            return;
        }

        // Check if user is admin
        $user = Auth::getUserFromToken();
        if (!$user || !in_array($user['role'], ['admin', 'super_admin'])) {
            Response::forbidden('Admin access required');
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['user_id']) || !isset($data['title']) || !isset($data['message'])) {
            Response::badRequest('user_id, title, and message are required');
            return;
        }

        $notificationData = [
            'user_id' => $data['user_id'],
            'type' => 'system',
            'title' => $data['title'],
            'message' => $data['message'],
            'related_id' => $data['related_id'] ?? null,
            'related_type' => $data['related_type'] ?? 'system'
        ];

        $notificationId = Notification::create($notificationData);

        if ($notificationId) {
            Response::success([
                'message' => 'System notification created',
                'notification_id' => $notificationId
            ]);
        } else {
            Response::error('Failed to create system notification', 500);
        }
    }

    /**
     * Create a notification for video favorited (called internally)
     */
    public function notifyVideoFavorited() {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['video_id']) || !isset($data['user_id'])) {
            Response::badRequest('video_id and user_id are required');
            return;
        }

        $count = Notification::notifyVideoFavorited($data['video_id'], $data['user_id']);

        Response::success([
            'message' => 'Video favorite notifications sent',
            'notifications_sent' => $count
        ]);
    }

    /**
     * Create notifications for new video in category
     */
    public function notifyNewVideoInCategory() {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['video_id']) || !isset($data['category_id'])) {
            Response::badRequest('video_id and category_id are required');
            return;
        }

        $count = Notification::notifyNewVideoInCategory($data['video_id'], $data['category_id']);

        Response::success([
            'message' => 'Category subscription notifications sent',
            'notifications_sent' => $count
        ]);
    }
}