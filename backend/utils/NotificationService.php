<?php
/**
 * Notification Service
 * Handles platform-wide email notifications and broadcasts
 */

require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/PushSubscription.php';

class NotificationService {

    /**
     * Send welcome email to new user
     */
    public static function sendWelcome($user) {
        $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'there';
        $subject = 'Welcome to LCMTV';
        
        $body = "
            <p>Dear " . htmlspecialchars($name) . ",</p>
            <p>Thank you for joining <strong>LCMTV</strong>! We are excited to have you as part of our community.</p>
            <p>You can now log in to the platform to watch your favorite livestreams, sermons, and exclusive content.</p>
            <p>If you have any questions, feel free to reply to this email or contact support.</p>
            <p>Blessings,<br>The LCMTV Team</p>
        ";

        Mailer::send($user['email'], $subject, $body);
        
        // Push notification
        try {
            PushSubscription::sendToUser($user['id'], $subject, 'Thank you for joining LCMTV! Explore our content now.');
        } catch (\Throwable $e) {}

        return true;
    }

    /**
     * Send login alert for account security
     */
    public static function sendLoginAlert($user) {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $time = date('M j, Y H:i:s T');
        $subject = 'Security Alert: New Login Detected';

        $body = "
            <p>Hello " . htmlspecialchars($user['first_name'] ?: 'there') . ",</p>
            <p>This is a security notification to let you know that a new login was detected on your LCMTV account.</p>
            <div style='background: #fdf2f2; border: 1px solid #fbd5d5; padding: 15px; border-radius: 4px; margin: 20px 0;'>
                <strong>Time:</strong> {$time}<br>
                <strong>IP Address:</strong> {$ipAddress}<br>
                <strong>Device:</strong> " . htmlspecialchars(self::getBrowser($userAgent)) . "
            </div>
            <p>If this was you, you can safely ignore this email. If you did not authorize this login, please change your password immediately.</p>
            <p>Stay secure,<br>LCMTV Security Team</p>
        ";

        Mailer::send($user['email'], $subject, $body);

        // Push notification
        try {
            PushSubscription::sendToUser($user['id'], $subject, 'A new login was detected on your account.');
        } catch (\Throwable $e) {}

        return true;
    }

    /**
     * Broadcast "We are Live now" to appropriate audience
     */
    public static function broadcastLiveEvent($livestream) {
        $targetRole = $livestream['target_role'] ?? 'general';
        $emails = User::getEmailsByRole($targetRole);
        
        if (empty($emails)) return true;

        $subject = '🔴 WE ARE LIVE: ' . $livestream['title'];
        $body = "
            <p>Greetings!</p>
            <p>We are excited to inform you that we have just started a live stream: <strong class='orange'>" . htmlspecialchars($livestream['title']) . "</strong>.</p>
            <p>Join us now to participate in the live session and engage with the community.</p>
            <hr>
            <p><strong>Topic:</strong> " . htmlspecialchars($livestream['description'] ?: 'No description provided') . "</p>
            <p>We look forward to seeing you there!</p>
        ";

        self::batchSend($emails, $subject, $body);

        // Broadcast Push Notification to all users (considering target role)
        try {
            // Note: sendToUsers handles multiple subscriptions
            $userIds = array_map(function($email) {
                // This is slightly inefficient, but for broadcast it is manageable. 
                // In production, one would fetch IDs directly with roles.
                $user = User::findByEmail($email);
                return $user ? $user['id'] : null;
            }, $emails);
            $userIds = array_filter($userIds);

            PushSubscription::sendToUsers($userIds, "🔴 WE ARE LIVE!", $livestream['title'], [
                'type' => 'livestream',
                'id' => $livestream['id']
            ]);
        } catch (\Throwable $e) {
            error_log("Broadcast live event push failed: " . $e->getMessage());
        }

        return true;
    }

    /**
     * Send notification for a prayer request response (Email + Push)
     */
    public static function sendPrayerResponse($request, $responseText) {
        $subject = "Response to your Prayer Request - LCMTV";
        
        $htmlContent = "
            <p>Dear <strong>" . htmlspecialchars($request['full_name']) . "</strong>,</p>
            <p>We have received and reviewed your prayer request regarding: <em>" . htmlspecialchars($request['category'] ?: 'General') . "</em>.</p>
            <div style='background: #f9f9f9; padding: 15px; border-left: 4px solid #6A0DAD; margin: 20px 0;'>
                <strong>Your Request:</strong><br>
                " . nl2br(htmlspecialchars($request['request_text'])) . "
            </div>
            <div style='background: #fff8ee; padding: 15px; border-left: 4px solid #FF8C00; margin: 20px 0;'>
                <strong>Our Response:</strong><br>
                " . nl2br(htmlspecialchars($responseText)) . "
            </div>
            <p>May God's peace be with you and may He answer your prayers according to His will.</p>
            <p>God bless you,<br>The LCMTV Team</p>
        ";

        // 1. Send Email
        Mailer::send($request['email'], $subject, $htmlContent);

        // 2. Send Push Notification if user_id exists
        if (!empty($request['user_id'])) {
            try {
                $pushTitle = "Prayer Request Response";
                $pushBody = "You have a response to your prayer request regarding " . ($request['category'] ?: 'General') . ".";
                PushSubscription::sendToUser($request['user_id'], $pushTitle, $pushBody, [
                    'type' => 'prayer_response',
                    'id' => $request['id']
                ]);
            } catch (\Throwable $e) {
                error_log("Push notification failed for prayer response: " . $e->getMessage());
            }
        }

        return true;
    }

    /**
     * Broadcast "New Video Available" to appropriate audience
     */
    public static function broadcastNewVideo($video) {
        $targetRole = $video['target_role'] ?? 'general';
        $emails = User::getEmailsByRole($targetRole);
        
        if (empty($emails)) return true;

        $subject = '📹 New Video: ' . $video['title'];
        $body = "
            <p>Hello,</p>
            <p>A new video has just been added to the platform: <strong class='purple'>" . htmlspecialchars($video['title']) . "</strong>.</p>
            <p>You can now watch it at your convenience. Stay tuned for more life-changing content.</p>
            <hr>
            <p><strong>About this video:</strong> " . htmlspecialchars(substr($video['description'] ?? '', 0, 150)) . "...</p>
        ";

        $appUrl = getenv('APP_URL');
        if (!$appUrl || strpos($appUrl, 'localhost') !== false) {
            $appUrl = 'https://tv.lifechangerstouch.org';
        }
        $videoUrl = rtrim($appUrl, '/') . '/frontend/#/video/' . ($video['id'] ?? '');

        self::batchSend($emails, $subject, $body, $videoUrl, "Watch " . htmlspecialchars($video['title']));

        // Broadcast Push Notification
        try {
            $userIds = array_map(function($email) {
                $user = User::findByEmail($email);
                return $user ? $user['id'] : null;
            }, $emails);
            $userIds = array_filter($userIds);

            PushSubscription::sendToUsers($userIds, "📹 New Video Available", $video['title'], [
                'type' => 'video',
                'id' => $video['id'] ?? null,
                'image' => $video['thumbnail_url'] ?? null
            ]);
        } catch (\Throwable $e) {
            error_log("Broadcast new video push failed: " . $e->getMessage());
        }

        return true;
    }

    /**
     * Internal helper to handle looping through emails
     */
    private static function batchSend($emails, $subject, $body, $actionUrl = null, $actionText = null) {
        // Extend execution time for large lists
        @set_time_limit(300); 
        
        $successCount = 0;
        foreach ($emails as $email) {
            if (Mailer::send($email, $subject, $body, null, $actionUrl, $actionText)) {
                $successCount++;
            }
            // Small Sleep to avoid hitting local rate limits (optional)
            // usleep(50000); 
        }
        return $successCount > 0;
    }

    /**
     * Simple browser detection
     */
    private static function getBrowser($userAgent) {
        if (strpos($userAgent, 'Chrome') !== false) return 'Google Chrome';
        if (strpos($userAgent, 'Firefox') !== false) return 'Mozilla Firefox';
        if (strpos($userAgent, 'Safari') !== false) return 'Apple Safari';
        if (strpos($userAgent, 'Edge') !== false) return 'Microsoft Edge';
        return 'Mobile Device or Browser';
    }
}
