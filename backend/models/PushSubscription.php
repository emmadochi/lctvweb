<?php
/**
 * Push Subscription Model
 * Handles push notification subscriptions
 */

require_once __DIR__ . '/../config/database.php';

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
     * Send push notification to a subscription
     */
    public static function sendPushNotification($subscription, $payload) {
        $endpoint = $subscription['endpoint'];

        // For development/demo purposes, we'll send a basic notification
        // In production, you'd use proper VAPID encryption with a library like web-push-php

        $postData = json_encode([
            'title' => $payload['title'] ?? 'LCMTV Notification',
            'body' => $payload['body'] ?? 'You have a new notification',
            'icon' => $payload['icon'] ?? '/icon-192x192.png',
            'badge' => $payload['badge'] ?? '/icon-192x192.png',
            'data' => $payload['data'] ?? [],
            'requireInteraction' => true,
            'silent' => false
        ]);

        // For demo purposes, we'll use curl to send to push services
        // In production, you should use proper encrypted payloads
        return self::sendViaCurl($endpoint, $postData);
    }

    /**
     * Send push notification via curl (fallback method)
     */
    private static function sendViaCurl($endpoint, $payload) {
        $ch = curl_init();

        $headers = [
            'Content-Type: application/json',
            'TTL: 86400', // 24 hours
        ];

        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        // For encrypted payloads, we would need proper VAPID implementation
        // For now, sending basic payload (this works with some push services for testing)

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            error_log("Push notification failed: $error");
            return false;
        }

        // 201 = Created (success), 410 = Gone (subscription expired)
        if ($httpCode === 201 || $httpCode === 200) {
            return true;
        } elseif ($httpCode === 410) {
            // Subscription expired, deactivate it
            self::deactivateByEndpoint($endpoint);
            return false;
        }

        error_log("Push notification failed with HTTP $httpCode");
        return false;
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