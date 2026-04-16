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

        // Detect if this is an FCM token (mobile)
        $isFcm = isset($data['endpoint']) && (strpos($data['endpoint'], 'fcm:') === 0 || strlen($data['endpoint']) > 100);
        
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
            return $conn->insert_id ?: true;
        }

        return false;
    }

    /**
     * Get all active push subscriptions for a user
     */
    public static function getUserSubscriptions($userId) {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT * FROM push_subscriptions WHERE user_id = ? AND is_active = TRUE");
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
     * Get OAuth2 Access Token for FCM V1
     */
    private static function getFcmAccessToken() {
        $jsonPath = __DIR__ . '/../config/firebase-service-account.json';
        if (!file_exists($jsonPath)) {
            error_log("FCM V1: Service account JSON not found at $jsonPath");
            return null;
        }

        $config = json_decode(file_get_contents($jsonPath), true);
        $now = time();
        $payload = [
            'iss' => $config['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => $config['token_uri'],
            'iat' => $now,
            'exp' => $now + 3600
        ];

        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        
        $base64UrlHeader = self::base64UrlEncode(json_encode($header));
        $base64UrlPayload = self::base64UrlEncode(json_encode($payload));
        
        $signatureInput = $base64UrlHeader . "." . $base64UrlPayload;
        $signature = '';
        openssl_sign($signatureInput, $signature, $config['private_key'], 'SHA256');
        $base64UrlSignature = self::base64UrlEncode($signature);
        
        $jwt = $signatureInput . "." . $base64UrlSignature;

        // Exchange JWT for Access Token
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $config['token_uri']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ]));
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        return $data['access_token'] ?? null;
    }

    private static function base64UrlEncode($data) {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    /**
     * Get WebPush instance initialized with VAPID keys
     */
    private static function getWebPush() {
        $publicKey = getenv('VAPID_PUBLIC_KEY');
        $privateKey = getenv('VAPID_PRIVATE_KEY');
        $adminEmail = getenv('ADMIN_EMAIL') ?: 'info@lifechangertouch.org';

        if (!$publicKey || !$privateKey) {
            error_log("Push Error: VAPID keys not configured in environment");
            return null;
        }

        // The vendor folder might be missing on some environments, check before using
        if (!class_exists('Minishlink\WebPush\WebPush')) {
            error_log("Push Error: Minishlink WebPush library not found. Run composer install.");
            return null;
        }

        $auth = [
            'VAPID' => [
                'subject' => 'mailto:' . $adminEmail,
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ];

        try {
            return new WebPush($auth);
        } catch (\Exception $e) {
            error_log("Push Error: Failed to initialize WebPush: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Deactivate a subscription by its endpoint
     */
    public static function deactivateByEndpoint($endpoint, $userId = null) {
        $conn = getDBConnection();
        if ($userId) {
            $stmt = $conn->prepare("UPDATE push_subscriptions SET is_active = FALSE WHERE endpoint = ? AND user_id = ?");
            $stmt->bind_param("si", $endpoint, $userId);
        } else {
            $stmt = $conn->prepare("UPDATE push_subscriptions SET is_active = FALSE WHERE endpoint = ?");
            $stmt->bind_param("s", $endpoint);
        }
        return $stmt->execute();
    }

    /**
     * Get all active subscriptions for all users
     */
    public static function getAllActiveSubscriptions() {
        $conn = getDBConnection();
        $result = $conn->query("SELECT * FROM push_subscriptions WHERE is_active = TRUE");
        
        $subscriptions = [];
        while ($row = $result->fetch_assoc()) {
            $subscriptions[] = $row;
        }
        return $subscriptions;
    }

    /**
     * Clean up inactive subscriptions older than X days
     */
    public static function cleanupInactiveSubscriptions($daysOld = 30) {
        $conn = getDBConnection();
        $stmt = $conn->prepare("DELETE FROM push_subscriptions WHERE is_active = FALSE AND updated_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->bind_param("i", $daysOld);
        return $stmt->execute();
    }

    /**
     * Send notification via FCM V1
     */
    public static function sendFcmV1Notification($token, $payload) {
        $accessToken = self::getFcmAccessToken();
        if (!$accessToken) return false;

        $jsonPath = __DIR__ . '/../config/firebase-service-account.json';
        $config = json_decode(file_get_contents($jsonPath), true);
        $projectId = $config['project_id'];

        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
        
        $message = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $payload['title'],
                    'body' => $payload['body']
                ],
                'data' => array_map('strval', $payload['data'] ?? []),
                'android' => [
                    'priority' => 'high',
                    'notification' => [
                        'color' => '#FF8C00'
                    ]
                ]
            ]
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) return true;
        
        if ($httpCode === 404 || $httpCode === 410) {
            self::deactivateByEndpoint($token);
        }

        error_log("FCM V1 Send Error ($httpCode): " . $response);
        return false;
    }

    /**
     * Send push notification to a subscription using Web Push (VAPID) or FCM.
     */
    public static function sendPushNotification($subscription, $payload) {
        $token = $subscription['endpoint'];
        
        // Check if it's an FCM token (usually no slashes and long, or starts with FCM: which we might use)
        if (strpos($token, 'fcm:') === 0 || strlen($token) > 100) {
            $actualToken = str_replace('fcm:', '', $token);
            return self::sendFcmV1Notification($actualToken, $payload);
        }

        // Default to Web Push
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
            ]));

            if ($report->isSuccess()) return true;

            if ($report->getResponse() && $report->getResponse()->getStatusCode() === 410) {
                self::deactivateByEndpoint($subscription['endpoint']);
            }

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