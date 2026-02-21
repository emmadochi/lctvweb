<?php
/**
 * Push Subscription Model
 * Handles push notification subscriptions
 */

require_once __DIR__ . '/../config/database.php';

// Load Composer autoloader for Web Push library (if installed)
// Make sure to run "composer install" in the backend directory.
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class PushSubscription {
    private $conn;

    public function __construct() {
        $this->conn = getDBConnection();
    }

    /**
     * Create or update a push subscription
     */
    public static function upsert($data) {
        $conn = getDBConnection();

        $stmt = $conn->prepare("INSERT INTO push_subscriptions
                               (user_id, endpoint, p256dh_key, auth_key, user_agent, ip_address)
                               VALUES (?, ?, ?, ?, ?, ?)
                               ON DUPLICATE KEY UPDATE
                               p256dh_key = VALUES(p256dh_key),
                               auth_key = VALUES(auth_key),
                               user_agent = VALUES(user_agent),
                               ip_address = VALUES(ip_address),
                               updated_at = CURRENT_TIMESTAMP,
                               is_active = TRUE");

        $stmt->bind_param("isssss",
            $data['user_id'],
            $data['endpoint'],
            $data['p256dh_key'],
            $data['auth_key'],
            $data['user_agent'] ?? null,
            $data['ip_address'] ?? null
        );

        if ($stmt->execute()) {
            return $conn->insert_id ?: true; // Return true for updates
        }

        return false;
    }

    /**
     * Deactivate a subscription by endpoint
     */
    public static function deactivateByEndpoint($endpoint, $userId = null) {
        $conn = getDBConnection();

        $sql = "UPDATE push_subscriptions SET is_active = FALSE WHERE endpoint = ?";
        $params = [$endpoint];
        $types = "s";

        if ($userId) {
            $sql .= " AND user_id = ?";
            $params[] = $userId;
            $types .= "i";
        }

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);

        return $stmt->execute();
    }

    /**
     * Get active subscriptions for a user
     */
    public static function getUserSubscriptions($userId) {
        $conn = getDBConnection();

        $stmt = $conn->prepare("SELECT * FROM push_subscriptions
                               WHERE user_id = ? AND is_active = TRUE
                               ORDER BY created_at DESC");
        $stmt->bind_param("i", $userId);
        $stmt->execute();

        $result = $stmt->get_result();
        $subscriptions = [];

        while ($row = $result->fetch_assoc()) {
            $subscriptions[] = $row;
        }

        return $subscriptions;
    }

    /**
     * Get all active subscriptions (for sending notifications)
     */
    public static function getAllActiveSubscriptions() {
        $conn = getDBConnection();

        $stmt = $conn->prepare("SELECT ps.*, u.email, u.first_name, u.last_name
                               FROM push_subscriptions ps
                               JOIN users u ON ps.user_id = u.id
                               WHERE ps.is_active = TRUE AND u.is_active = TRUE
                               ORDER BY ps.created_at DESC");
        $stmt->execute();

        $result = $stmt->get_result();
        $subscriptions = [];

        while ($row = $result->fetch_assoc()) {
            $subscriptions[] = $row;
        }

        return $subscriptions;
    }

    /**
     * Clean up old inactive subscriptions
     */
    public static function cleanupInactiveSubscriptions($daysOld = 30) {
        $conn = getDBConnection();

        $stmt = $conn->prepare("DELETE FROM push_subscriptions
                               WHERE is_active = FALSE
                               AND updated_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->bind_param("i", $daysOld);

        return $stmt->execute();
    }

    /**
     * Get configured WebPush instance using VAPID keys from environment.
     */
    private static function getWebPush(): ?WebPush {
        if (!class_exists(WebPush::class)) {
            error_log('WebPush library not installed. Run "composer require minishlink/web-push" in backend directory.');
            return null;
        }

        $publicKey  = getenv('VAPID_PUBLIC_KEY');
        $privateKey = getenv('VAPID_PRIVATE_KEY');
        $subject    = getenv('VAPID_SUBJECT') ?: 'mailto:admin@lcmtv.com';

        if (!$publicKey || !$privateKey) {
            error_log('VAPID keys are not configured in environment.');
            return null;
        }

        $auth = [
            'VAPID' => [
                'subject' => $subject,
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ];

        return new WebPush($auth);
    }

    /**
     * Send push notification to a subscription using Web Push (VAPID).
     */
    public static function sendPushNotification($subscription, $payload) {
        $webPush = self::getWebPush();
        if ($webPush === null) {
            return false;
        }

        try {
            $sub = Subscription::create([
                'endpoint' => $subscription['endpoint'],
                'publicKey' => $subscription['p256dh_key'],
                'authToken' => $subscription['auth_key'],
            ]);

            $report = $webPush->sendOneNotification($sub, json_encode([
                'title' => $payload['title'] ?? 'LCMTV Notification',
                'body' => $payload['body'] ?? 'You have a new notification',
                'icon' => $payload['icon'] ?? '/icon-192x192.png',
                'badge' => $payload['badge'] ?? '/icon-192x192.png',
                'data' => $payload['data'] ?? [],
                'requireInteraction' => true,
                'silent' => false,
            ]));

            if ($report->isSuccess()) {
                return true;
            }

            $endpoint = $subscription['endpoint'];

            // If subscription is gone/expired, deactivate it
            if ($report->getResponse() && $report->getResponse()->getStatusCode() === 410) {
                self::deactivateByEndpoint($endpoint);
            }

            error_log('Push notification failed: ' . $report->getReason());
            return false;
        } catch (\Exception $e) {
            error_log('Push notification exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification to all user subscriptions
     */
    public static function sendToUser($userId, $title, $body, $data = []) {
        $subscriptions = self::getUserSubscriptions($userId);
        $successCount = 0;

        $payload = [
            'title' => $title,
            'body' => $body,
            'icon' => '/icon-192x192.png',
            'badge' => '/icon-192x192.png',
            'data' => $data,
        ];

        foreach ($subscriptions as $subscription) {
            if (self::sendPushNotification($subscription, $payload)) {
                $successCount++;
            }
        }

        return $successCount;
    }

    /**
     * Send notification to multiple users
     */
    public static function sendToUsers($userIds, $title, $body, $data = []) {
        $successCount = 0;

        foreach ($userIds as $userId) {
            $count = self::sendToUser($userId, $title, $body, $data);
            $successCount += $count;
        }

        return $successCount;
    }
}