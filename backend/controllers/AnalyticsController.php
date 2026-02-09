<?php
/**
 * Analytics Controller
 * Handles analytics data collection and reporting
 */

require_once __DIR__ . '/../models/Analytics.php';
require_once __DIR__ . '/../utils/Auth.php';
require_once __DIR__ . '/../utils/Response.php';

class AnalyticsController {
    /**
     * Track page view
     */
    public static function trackPageView() {
        try {
            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);

            // Validate JSON parsing
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("AnalyticsController::trackPageView - JSON decode error: " . json_last_error_msg());
                return Response::error('Invalid JSON data', 400);
            }

            // Validate required fields
            if (empty($data['session_id']) || empty($data['page_url'])) {
                error_log("AnalyticsController::trackPageView - Missing required fields");
                return Response::error('Missing required fields: session_id, page_url', 400);
            }

            // Get user info if authenticated (may return null if not authenticated)
            $userId = null;
            try {
                $userId = Auth::getCurrentUserId();
            } catch (Exception $e) {
                // User not authenticated - that's okay for analytics
                error_log("AnalyticsController::trackPageView - Auth check: " . $e->getMessage());
            }

            // Detect device info
            $deviceInfo = self::detectDeviceInfo();

            // Prepare page view data
            $pageViewData = [
                'user_id' => $userId,
                'session_id' => $data['session_id'],
                'page_url' => $data['page_url'],
                'page_title' => $data['page_title'] ?? '',
                'referrer' => $data['referrer_url'] ?? $data['referrer'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'ip_address' => self::getClientIP(),
                'country' => $data['country'] ?? null,
                'city' => $data['city'] ?? null,
                'device_type' => $deviceInfo['device_type'],
                'browser' => $deviceInfo['browser'],
                'os' => $deviceInfo['os'],
                'screen_resolution' => $data['screen_resolution'] ?? null,
                'time_on_page' => isset($data['time_on_page']) ? (int)$data['time_on_page'] : 0
            ];

            // Record page view
            $success = Analytics::trackPageView($pageViewData);

            if ($success) {
                return Response::success(['message' => 'Page view recorded']);
            } else {
                // Log error but return success to prevent breaking the frontend
                error_log("AnalyticsController::trackPageView - Failed to record page view");
                // Return success anyway - analytics failures shouldn't break the app
                return Response::success(['message' => 'Page view recording attempted']);
            }

        } catch (Exception $e) {
            error_log("AnalyticsController::trackPageView - Exception: " . $e->getMessage());
            error_log("AnalyticsController::trackPageView - Stack trace: " . $e->getTraceAsString());
            // Return success to prevent breaking the frontend
            return Response::success(['message' => 'Page view recording attempted']);
        }
    }

    /**
     * Track video view
     */
    public static function trackVideoView() {
        try {
            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);

            // Validate JSON parsing
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("AnalyticsController::trackVideoView - JSON decode error: " . json_last_error_msg());
                return Response::error('Invalid JSON data', 400);
            }

            // Validate required fields
            if (empty($data['video_id']) || empty($data['session_id']) || empty($data['youtube_id'])) {
                error_log("AnalyticsController::trackVideoView - Missing required fields");
                return Response::error('Missing required fields: video_id, session_id, youtube_id', 400);
            }

            // Get user info if authenticated (may return null if not authenticated)
            $userId = null;
            try {
                $userId = Auth::getCurrentUserId();
            } catch (Exception $e) {
                // User not authenticated - that's okay for analytics
                error_log("AnalyticsController::trackVideoView - Auth check: " . $e->getMessage());
            }

            // Detect device info
            $deviceInfo = self::detectDeviceInfo();

            // Prepare video view data
            $videoViewData = [
                'video_id' => (int)$data['video_id'],
                'user_id' => $userId,
                'session_id' => $data['session_id'],
                'youtube_id' => $data['youtube_id'],
                'watch_duration' => isset($data['watch_duration']) ? (int)$data['watch_duration'] : 0,
                'total_duration' => isset($data['total_duration']) ? (int)$data['total_duration'] : 0,
                'quality' => $data['quality'] ?? null,
                'playback_speed' => isset($data['playback_speed']) ? (float)$data['playback_speed'] : 1.0,
                'referrer' => $data['referrer_url'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'ip_address' => self::getClientIP(),
                'device_type' => $deviceInfo['device_type'],
                'browser' => $deviceInfo['browser'],
                'os' => $deviceInfo['os'],
                'country' => $data['country'] ?? null,
                'city' => $data['city'] ?? null
            ];

            // Record video view
            $success = Analytics::trackVideoView($videoViewData);

            if ($success) {
                return Response::success(['message' => 'Video view recorded']);
            } else {
                // Log error but return success to prevent breaking the frontend
                error_log("AnalyticsController::trackVideoView - Failed to record video view");
                // Return success anyway - analytics failures shouldn't break the app
                return Response::success(['message' => 'Video view recording attempted']);
            }

        } catch (Exception $e) {
            error_log("AnalyticsController::trackVideoView - Exception: " . $e->getMessage());
            error_log("AnalyticsController::trackVideoView - Stack trace: " . $e->getTraceAsString());
            // Return success to prevent breaking the frontend
            return Response::success(['message' => 'Video view recording attempted']);
        }
    }

    /**
     * Get analytics dashboard data
     */
    public static function getDashboard() {
        try {
            // Require admin authentication for analytics dashboard
            Auth::requireAuth();
            $user = Auth::getUserFromToken();

            if (!$user || $user['role'] !== 'admin') {
                return Response::forbidden('Admin access required for analytics dashboard');
            }

            $period = isset($_GET['period']) ? (int)$_GET['period'] : 30;

            // Get comprehensive dashboard overview
            $overview = Analytics::getDashboardOverview($period);

            // Get user demographics
            $demographics = Analytics::getUserDemographics($period);

            // Get content performance metrics
            $contentPerformance = Analytics::getContentPerformance($period);

            // Get engagement analytics
            $engagement = Analytics::getEngagementAnalytics($period);

            // Get real-time metrics
            $realtime = Analytics::getRealtimeMetrics();

            $analytics = [
                'overview' => $overview,
                'demographics' => $demographics,
                'content_performance' => $contentPerformance,
                'engagement' => $engagement,
                'realtime' => $realtime,
                'period_days' => $period
            ];

            return Response::success($analytics);

        } catch (Exception $e) {
            error_log("AnalyticsController::getDashboard - Error: " . $e->getMessage());
            return Response::error('Error fetching analytics dashboard: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Track engagement events (social interactions, user engagement, etc.)
     */
    public static function trackEngagement() {
        try {
            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);

            // Validate JSON parsing
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("AnalyticsController::trackEngagement - JSON decode error: " . json_last_error_msg());
                return Response::error('Invalid JSON data', 400);
            }

            // Validate required fields
            if (empty($data['session_id']) || empty($data['event_type'])) {
                error_log("AnalyticsController::trackEngagement - Missing required fields");
                return Response::error('Missing required fields: session_id, event_type', 400);
            }

            // Get user info if authenticated (may return null if not authenticated)
            $userId = null;
            try {
                $userId = Auth::getCurrentUserId();
            } catch (Exception $e) {
                // User not authenticated - that's okay for analytics
                error_log("AnalyticsController::trackEngagement - Auth check: " . $e->getMessage());
            }

            // Detect device info
            $deviceInfo = self::detectDeviceInfo();

            // Prepare engagement data
            $engagementData = [
                'user_id' => $userId,
                'session_id' => $data['session_id'],
                'event_type' => $data['event_type'],
                'event_data' => $data['event_data'] ?? [],
                'page_url' => $data['page_url'] ?? '',
                'referrer' => $data['referrer_url'] ?? $data['referrer'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'ip_address' => self::getClientIP(),
                'device_type' => $deviceInfo['device_type'],
                'browser' => $deviceInfo['browser'],
                'os' => $deviceInfo['os'],
                'country' => $data['country'] ?? null,
                'city' => $data['city'] ?? null
            ];

            // Record engagement event
            $success = Analytics::trackEngagement($engagementData);

            if ($success) {
                return Response::success(['message' => 'Engagement event recorded']);
            } else {
                // Log error but return success to prevent breaking the frontend
                error_log("AnalyticsController::trackEngagement - Failed to record engagement event");
                // Return success anyway - analytics failures shouldn't break the app
                return Response::success(['message' => 'Engagement event recording attempted']);
            }

        } catch (Exception $e) {
            error_log("AnalyticsController::trackEngagement - Exception: " . $e->getMessage());
            error_log("AnalyticsController::trackEngagement - Stack trace: " . $e->getTraceAsString());
            // Return success to prevent breaking the frontend
            return Response::success(['message' => 'Engagement event recording attempted']);
        }
    }

    /**
     * Get video engagement metrics (legacy endpoint)
     */
    public static function getVideoEngagement() {
        try {
            // Require admin authentication
            Auth::requireAuth();
            $user = Auth::getUserFromToken();

            if (!$user || $user['role'] !== 'admin') {
                return Response::forbidden('Admin access required');
            }

            $videoId = isset($_GET['video_id']) ? (int)$_GET['video_id'] : null;
            $period = isset($_GET['period']) ? (int)$_GET['period'] : 30;

            // Get content performance data which includes engagement metrics
            $contentPerformance = Analytics::getContentPerformance($period);

            if ($videoId) {
                // Filter for specific video
                $videoEngagement = array_filter($contentPerformance['video_performance'], function($video) use ($videoId) {
                    return $video['video_id'] === $videoId;
                });

                $engagement = [
                    'video_performance' => $videoEngagement ? array_values($videoEngagement)[0] : null,
                    'engagement' => $contentPerformance['engagement'],
                    'period_days' => $period
                ];
            } else {
                $engagement = $contentPerformance;
            }

            return Response::success($engagement);

        } catch (Exception $e) {
            error_log("AnalyticsController::getVideoEngagement - Error: " . $e->getMessage());
            return Response::error('Error fetching engagement metrics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Detect device and browser information
     */
    private static function detectDeviceInfo() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Device type detection
        if (preg_match('/Mobile|Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i', $userAgent)) {
            if (preg_match('/iPad/i', $userAgent)) {
                $deviceType = 'tablet';
            } else {
                $deviceType = 'mobile';
            }
        } else {
            $deviceType = 'desktop';
        }

        // Browser detection
        $browser = 'unknown';
        if (preg_match('/Chrome/i', $userAgent)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Firefox/i', $userAgent)) {
            $browser = 'Firefox';
        } elseif (preg_match('/Safari/i', $userAgent)) {
            $browser = 'Safari';
        } elseif (preg_match('/Edge/i', $userAgent)) {
            $browser = 'Edge';
        } elseif (preg_match('/Opera/i', $userAgent)) {
            $browser = 'Opera';
        } elseif (preg_match('/MSIE|Trident/i', $userAgent)) {
            $browser = 'Internet Explorer';
        }

        // OS detection
        $os = 'unknown';
        if (preg_match('/Windows/i', $userAgent)) {
            $os = 'Windows';
        } elseif (preg_match('/Mac/i', $userAgent)) {
            $os = 'macOS';
        } elseif (preg_match('/Linux/i', $userAgent)) {
            $os = 'Linux';
        } elseif (preg_match('/Android/i', $userAgent)) {
            $os = 'Android';
        } elseif (preg_match('/iOS|iPhone|iPad/i', $userAgent)) {
            $os = 'iOS';
        }

        return [
            'device_type' => $deviceType,
            'browser' => $browser,
            'os' => $os
        ];
    }

    /**
     * Get client IP address
     */
    private static function getClientIP() {
        $ipHeaders = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ipHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];

                // Handle comma-separated IPs (from X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }

                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}
?>
