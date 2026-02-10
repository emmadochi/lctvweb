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
                // Analytics should never break the frontend UX
                return Response::success(['message' => 'Invalid JSON data']);
            }

            // Handle both single event and batch events (analytics service may batch page views)
            $events = [];
            if (isset($data[0]) && is_array($data[0])) {
                $events = $data;
            } else {
                $events = [$data];
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

            $recordedCount = 0;
            $failedCount = 0;
            $skippedInvalid = 0;

            foreach ($events as $event) {
                // Validate required fields (skip invalid events rather than failing the app)
                if (empty($event['session_id']) || empty($event['page_url'])) {
                    $skippedInvalid++;
                    error_log("AnalyticsController::trackPageView - Skipping invalid event (missing session_id/page_url): " . json_encode($event));
                    continue;
                }

                // Prepare page view data
                $pageViewData = [
                    'user_id' => $userId,
                    'session_id' => $event['session_id'],
                    'page_url' => $event['page_url'],
                    'page_title' => $event['page_title'] ?? '',
                    'referrer' => $event['referrer_url'] ?? $event['referrer'] ?? '',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    'ip_address' => self::getClientIP(),
                    'country' => $event['country'] ?? null,
                    'city' => $event['city'] ?? null,
                    'device_type' => $deviceInfo['device_type'],
                    'browser' => $deviceInfo['browser'],
                    'os' => $deviceInfo['os'],
                    'screen_resolution' => $event['screen_resolution'] ?? null,
                    'time_on_page' => isset($event['time_on_page']) ? (int)$event['time_on_page'] : (isset($event['time_spent']) ? (int)$event['time_spent'] : 0)
                ];

                $success = Analytics::trackPageView($pageViewData);
                if ($success) {
                    $recordedCount++;
                } else {
                    $failedCount++;
                    error_log("AnalyticsController::trackPageView - Failed to record page view: " . json_encode($pageViewData));
                }
            }

            // Always return success - analytics should never break the frontend UX
            return Response::success([
                'message' => 'Page view events processed',
                'recorded' => $recordedCount,
                'failed' => $failedCount,
                'skipped_invalid' => $skippedInvalid
            ]);

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
                // Analytics should never break the frontend UX
                return Response::success(['message' => 'Invalid JSON data']);
            }

            // Handle both single event and batch events (frontend may batch video analytics)
            $events = [];
            if (isset($data[0]) && is_array($data[0])) {
                $events = $data;
            } else {
                $events = [$data];
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

            $recordedCount = 0;
            $failedCount = 0;
            $skippedInvalid = 0;

            foreach ($events as $event) {
                // Required fields: session_id + video_id. youtube_id is optional for progress/complete events.
                if (empty($event['video_id']) || empty($event['session_id'])) {
                    $skippedInvalid++;
                    error_log("AnalyticsController::trackVideoView - Skipping invalid event (missing video_id/session_id): " . json_encode($event));
                    continue;
                }

                $videoViewData = [
                    'video_id' => (int)$event['video_id'],
                    'user_id' => $userId,
                    'session_id' => $event['session_id'],
                    'youtube_id' => $event['youtube_id'] ?? '',
                    'watch_duration' => isset($event['watch_duration']) ? (int)$event['watch_duration'] : (isset($event['total_watch_time']) ? (int)$event['total_watch_time'] : 0),
                    'total_duration' => isset($event['total_duration']) ? (int)$event['total_duration'] : 0,
                    'quality' => $event['quality'] ?? null,
                    'playback_speed' => isset($event['playback_speed']) ? (float)$event['playback_speed'] : 1.0,
                    'referrer' => $event['referrer_url'] ?? $event['referrer'] ?? '',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    'ip_address' => self::getClientIP(),
                    'device_type' => $deviceInfo['device_type'],
                    'browser' => $deviceInfo['browser'],
                    'os' => $deviceInfo['os'],
                    'country' => $event['country'] ?? null,
                    'city' => $event['city'] ?? null
                ];

                $success = Analytics::trackVideoView($videoViewData);
                if ($success) {
                    $recordedCount++;
                } else {
                    $failedCount++;
                    error_log("AnalyticsController::trackVideoView - Failed to record video view: " . json_encode($videoViewData));
                }
            }

            return Response::success([
                'message' => 'Video view events processed',
                'recorded' => $recordedCount,
                'failed' => $failedCount,
                'skipped_invalid' => $skippedInvalid
            ]);

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
                // Analytics should never break the frontend UX
                return Response::success(['message' => 'Invalid JSON data']);
            }

            // Handle both single event and batch events
            $events = [];
            if (isset($data[0]) && is_array($data[0])) {
                // Batch of events
                $events = $data;
            } else {
                // Single event
                $events = [$data];
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

            $recordedCount = 0;
            $failedCount = 0;
            $skippedInvalid = 0;

            // Process each event
            foreach ($events as $event) {
                // Try to normalize common field naming differences
                if (empty($event['event_type']) && !empty($event['type'])) {
                    $event['event_type'] = $event['type'];
                }
                if (empty($event['session_id']) && !empty($event['sessionId'])) {
                    $event['session_id'] = $event['sessionId'];
                }

                if (empty($event['session_id']) || empty($event['event_type'])) {
                    $skippedInvalid++;
                    error_log("AnalyticsController::trackEngagement - Skipping invalid event (missing session_id/event_type): " . json_encode($event));
                    continue;
                }

                // If frontend sent a flat payload (no event_data), treat remaining keys as event_data
                $eventData = $event['event_data'] ?? null;
                if ($eventData === null || !is_array($eventData)) {
                    $eventData = $event;
                    $stripKeys = [
                        'user_id', 'session_id', 'event_type', 'event_data',
                        'page_url', 'referrer', 'referrer_url',
                        'user_agent', 'timestamp',
                        'country', 'city', 'latitude', 'longitude'
                    ];
                    foreach ($stripKeys as $k) {
                        unset($eventData[$k]);
                    }
                }

                // Prepare engagement data
                $engagementData = [
                    'user_id' => $userId,
                    'session_id' => $event['session_id'],
                    'event_type' => $event['event_type'],
                    'event_data' => $eventData,
                    'page_url' => $event['page_url'] ?? '',
                    'referrer' => $event['referrer_url'] ?? $event['referrer'] ?? '',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    'ip_address' => self::getClientIP(),
                    'device_type' => $deviceInfo['device_type'],
                    'browser' => $deviceInfo['browser'],
                    'os' => $deviceInfo['os'],
                    'country' => $event['country'] ?? null,
                    'city' => $event['city'] ?? null
                ];

                // Record engagement event
                $success = Analytics::trackEngagement($engagementData);

                if ($success) {
                    $recordedCount++;
                } else {
                    $failedCount++;
                    error_log("AnalyticsController::trackEngagement - Failed to record event: " . json_encode($engagementData));
                }
            }

            // Return success with summary
            return Response::success([
                'message' => 'Engagement events processed',
                'recorded' => $recordedCount,
                'failed' => $failedCount,
                'skipped_invalid' => $skippedInvalid
            ]);

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
