<?php
/**
 * Push Notification Controller
 * Handles push notification subscriptions and sending
 */

require_once __DIR__ . '/../models/PushSubscription.php';
require_once __DIR__ . '/../models/Notification.php';
require_once __DIR__ . '/../utils/Auth.php';
require_once __DIR__ . '/../utils/Response.php';

class PushController {

    /**
     * Subscribe to push notifications
     */
    public function subscribe() {
        $userId = Auth::getCurrentUserId();
        if (!$userId) {
            Response::unauthorized('Authentication required');
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data || !isset($data['endpoint']) || !isset($data['keys'])) {
            Response::badRequest('Invalid subscription data');
            return;
        }

        $subscriptionData = [
            'user_id' => $userId,
            'endpoint' => $data['endpoint'],
            'p256dh_key' => $data['keys']['p256dh'] ?? '',
            'auth_key' => $data['keys']['auth'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ];

        $result = PushSubscription::upsert($subscriptionData);

        if ($result) {
            Response::success([
                'message' => 'Successfully subscribed to push notifications',
                'subscription_id' => $result
            ]);
        } else {
            Response::error('Failed to save subscription', 500);
        }
    }

    /**
     * Unsubscribe from push notifications
     */
    public function unsubscribe() {
        $userId = Auth::getCurrentUserId();
        if (!$userId) {
            Response::unauthorized('Authentication required');
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $endpoint = $data['endpoint'] ?? null;

        if (!$endpoint) {
            Response::badRequest('Endpoint required');
            return;
        }

        $result = PushSubscription::deactivateByEndpoint($endpoint, $userId);

        if ($result) {
            Response::success(['message' => 'Successfully unsubscribed from push notifications']);
        } else {
            Response::error('Failed to unsubscribe', 500);
        }
    }

    /**
     * Get user's push subscriptions
     */
    public function getSubscriptions() {
        $userId = Auth::getCurrentUserId();
        if (!$userId) {
            Response::unauthorized('Authentication required');
            return;
        }

        $subscriptions = PushSubscription::getUserSubscriptions($userId);

        Response::success([
            'subscriptions' => $subscriptions,
            'count' => count($subscriptions)
        ]);
    }

    /**
     * Send test push notification
     */
    public function sendTest() {
        $userId = Auth::getCurrentUserId();
        if (!$userId) {
            Response::unauthorized('Authentication required');
            return;
        }

        $successCount = PushSubscription::sendToUser(
            $userId,
            'LCMTV Test Notification',
            'This is a test push notification to verify your subscription is working!',
            [
                'type' => 'test',
                'test' => true
            ]
        );

        if ($successCount > 0) {
            Response::success([
                'message' => 'Test notification sent successfully',
                'devices_notified' => $successCount
            ]);
        } else {
            Response::error('No active subscriptions found or failed to send notification', 400);
        }
    }

    /**
     * Get VAPID public key
     */
    public function getVapidPublicKey() {
        // For demo purposes, using a valid base64url-encoded VAPID public key
        // In production, generate proper VAPID keys
        // For testing, using a minimal valid VAPID key
        $publicKey = 'BLr9o3-m8aWp24xN0g_yC4u1e4d5F6B7C8D9E0F1G2H3I4J5K6L7M8N9O0P1Q2R3S4T5U6V7W8X9Y0Z1A2B3C4D5E6F7G8H9I0';

        error_log("VAPID endpoint called, returning key: " . substr($publicKey, 0, 20) . "...");

        Response::success([
            'publicKey' => $publicKey
        ]);
    }

    /**
     * Send push notification for a regular notification (internal use)
     */
    public function sendForNotification() {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data || !isset($data['notification_id'])) {
            Response::badRequest('Notification ID required');
            return;
        }

        $notificationId = $data['notification_id'];

        // Get notification details
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT * FROM notifications WHERE id = ?");
        $stmt->bind_param("i", $notificationId);
        $stmt->execute();
        $notification = $stmt->get_result()->fetch_assoc();

        if (!$notification) {
            Response::notFound('Notification not found');
            return;
        }

        // Send push notification
        $successCount = PushSubscription::sendToUser(
            $notification['user_id'],
            $notification['title'],
            $notification['message'],
            [
                'notificationId' => $notification['id'],
                'type' => $notification['type'],
                'relatedId' => $notification['related_id'],
                'relatedType' => $notification['related_type']
            ]
        );

        Response::success([
            'message' => 'Push notification sent',
            'devices_notified' => $successCount
        ]);
    }

    /**
     * Admin function: Send push notification to all users
     */
    public function sendToAll() {
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

        if (!$data || !isset($data['title']) || !isset($data['body'])) {
            Response::badRequest('Title and body required');
            return;
        }

        $subscriptions = PushSubscription::getAllActiveSubscriptions();
        $successCount = 0;

        $payload = [
            'title' => $data['title'],
            'body' => $data['body'],
            'icon' => '/icon-192x192.png',
            'badge' => '/icon-192x192.png',
            'data' => $data['data'] ?? []
        ];

        foreach ($subscriptions as $subscription) {
            if (PushSubscription::sendPushNotification($subscription, $payload)) {
                $successCount++;
            }
        }

        Response::success([
            'message' => 'Push notifications sent to all users',
            'total_subscriptions' => count($subscriptions),
            'successful_sends' => $successCount
        ]);
    }

    /**
     * Clean up old inactive subscriptions (admin function)
     */
    public function cleanup() {
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

        $daysOld = isset($_GET['days']) ? (int)$_GET['days'] : 30;

        $result = PushSubscription::cleanupInactiveSubscriptions($daysOld);

        if ($result) {
            Response::success(['message' => 'Cleanup completed successfully']);
        } else {
            Response::error('Failed to cleanup subscriptions', 500);
        }
    }
}