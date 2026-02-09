<?php
/**
 * Analytics Model
 * Handles analytics data collection and reporting
 */

require_once __DIR__ . '/../config/database.php';

class Analytics {
    private static $pageViewsTable = 'page_views';
    private static $videoViewsTable = 'video_views';
    private static $userSessionsTable = 'user_sessions';
    private static $contentEngagementTable = 'content_engagement';
    private static $geographicDataTable = 'geographic_data';

    /**
     * Track page view
     */
    public static function trackPageView($data) {
        $conn = getDBConnection();

            $sql = "INSERT INTO " . self::$pageViewsTable . "
                (user_id, session_id, page_url, page_title, referrer_url, user_agent, ip_address, country, city, device_type, browser, os, screen_resolution, time_on_page, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        $userId = $data['user_id'] ?? null;
        $sessionId = $data['session_id'] ?? session_id();
        $pageUrl = $data['page_url'] ?? '';
        $pageTitle = $data['page_title'] ?? '';
        $referrer = $data['referrer'] ?? $data['referrer_url'] ?? '';
        $userAgent = $data['user_agent'] ?? '';
        $ipAddress = $data['ip_address'] ?? '';
        $country = $data['country'] ?? '';
        $city = $data['city'] ?? '';
        $deviceType = self::getDeviceType($userAgent);
        $browser = self::getBrowser($userAgent);
        $os = self::getOS($userAgent);
        $screenResolution = $data['screen_resolution'] ?? '';
        $timeSpent = $data['time_spent'] ?? $data['time_on_page'] ?? 0;
        $createdAt = date('Y-m-d H:i:s');

        $stmt->bind_param("sssssssssssssis", $userId, $sessionId, $pageUrl, $pageTitle, $referrer,
                         $userAgent, $ipAddress, $country, $city, $deviceType, $browser, $os,
                         $screenResolution, $timeSpent, $createdAt);

        return $stmt->execute();
    }

    /**
     * Track video view
     */
    public static function trackVideoView($data) {
        $conn = getDBConnection();

            $sql = "INSERT INTO " . self::$videoViewsTable . "
                (user_id, session_id, video_id, youtube_id, watch_duration, total_duration, watch_percentage, quality, playback_speed, referrer_url, user_agent, ip_address, device_type, browser, os, country, city, completed, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        $userId = $data['user_id'] ?? null;
        $sessionId = $data['session_id'] ?? session_id();
        $videoId = $data['video_id'];
        $youtubeId = $data['youtube_id'] ?? '';
        $watchDuration = $data['watch_duration'] ?? 0;
        $totalDuration = $data['total_duration'] ?? 0;
        $watchPercentage = $totalDuration > 0 ? ($watchDuration / $totalDuration) * 100 : 0;
        $quality = $data['quality'] ?? '';
        $playbackSpeed = $data['playback_speed'] ?? 1.0;
        $referrer = $data['referrer'] ?? '';
        $userAgent = $data['user_agent'] ?? '';
        $ipAddress = $data['ip_address'] ?? '';
        $deviceType = $data['device_type'] ?? '';
        $browser = $data['browser'] ?? '';
        $os = $data['os'] ?? '';
        $country = $data['country'] ?? '';
        $city = $data['city'] ?? '';
        $completed = $watchPercentage >= 90 ? 1 : 0;
        $createdAt = date('Y-m-d H:i:s');

        $stmt->bind_param("iisiddsdsssssssssis", $userId, $sessionId, $videoId, $youtubeId,
                         $watchDuration, $totalDuration, $watchPercentage, $quality, $playbackSpeed,
                         $referrer, $userAgent, $ipAddress, $deviceType, $browser, $os, $country, $city, $completed, $createdAt);

        return $stmt->execute();
    }

    /**
     * Track user session
     */
    public static function trackUserSession($data) {
        $conn = getDBConnection();

        $sql = "INSERT INTO " . self::$userSessionsTable . "
                (user_id, session_id, start_time, end_time, duration, pages_viewed, videos_watched, device_type, browser, os, ip_address, country, city, referrer, utm_source, utm_medium, utm_campaign)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                end_time = VALUES(end_time),
                duration = VALUES(duration),
                pages_viewed = VALUES(pages_viewed),
                videos_watched = VALUES(videos_watched)";

        $stmt = $conn->prepare($sql);

        $userId = $data['user_id'] ?? null;
        $sessionId = $data['session_id'] ?? session_id();
        $startTime = $data['start_time'] ?? date('Y-m-d H:i:s');
        $endTime = $data['end_time'] ?? date('Y-m-d H:i:s');
        $duration = $data['duration'] ?? 0;
        $pagesViewed = $data['pages_viewed'] ?? 0;
        $videosWatched = $data['videos_watched'] ?? 0;
        $deviceType = $data['device_type'] ?? '';
        $browser = $data['browser'] ?? '';
        $os = $data['os'] ?? '';
        $ipAddress = $data['ip_address'] ?? '';
        $country = $data['country'] ?? '';
        $city = $data['city'] ?? '';
        $referrer = $data['referrer'] ?? '';
        $utmSource = $data['utm_source'] ?? '';
        $utmMedium = $data['utm_medium'] ?? '';
        $utmCampaign = $data['utm_campaign'] ?? '';

        $stmt->bind_param("ssssiiiissssssssss", $userId, $sessionId, $startTime, $endTime, $duration,
                         $pagesViewed, $videosWatched, $deviceType, $browser, $os, $ipAddress,
                         $country, $city, $referrer, $utmSource, $utmMedium, $utmCampaign);

        return $stmt->execute();
    }

    /**
     * Track content engagement
     */
    public static function trackContentEngagement($data) {
        $conn = getDBConnection();

        $sql = "INSERT INTO " . self::$contentEngagementTable . "
                (user_id, session_id, content_type, content_id, action_type, action_value, device_type, referrer, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        $userId = $data['user_id'] ?? null;
        $sessionId = $data['session_id'] ?? session_id();
        $contentType = $data['content_type'] ?? ''; // video, livestream, comment, reaction
        $contentId = $data['content_id'] ?? '';
        $actionType = $data['action_type'] ?? ''; // view, like, comment, share, favorite
        $actionValue = $data['action_value'] ?? '';
        $deviceType = $data['device_type'] ?? '';
        $referrer = $data['referrer'] ?? '';
        $createdAt = date('Y-m-d H:i:s');

        $stmt->bind_param("sssssssss", $userId, $sessionId, $contentType, $contentId,
                         $actionType, $actionValue, $deviceType, $referrer, $createdAt);

        return $stmt->execute();
    }

    /**
     * Get dashboard overview stats
     */
    public static function getDashboardOverview($period = '30') {
        $conn = getDBConnection();

        $dateFilter = "DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL $period DAY)";

        // Total users
        $usersQuery = "SELECT COUNT(DISTINCT user_id) as total_users FROM " . self::$pageViewsTable . " WHERE $dateFilter AND user_id IS NOT NULL";
        $usersResult = $conn->query($usersQuery);
        $usersData = $usersResult->fetch_assoc();

        // Total sessions
        $sessionsQuery = "SELECT COUNT(DISTINCT session_id) as total_sessions FROM " . self::$pageViewsTable . " WHERE $dateFilter";
        $sessionsResult = $conn->query($sessionsQuery);
        $sessionsData = $sessionsResult->fetch_assoc();

        // Page views
        $pageViewsQuery = "SELECT COUNT(*) as page_views FROM " . self::$pageViewsTable . " WHERE $dateFilter";
        $pageViewsResult = $conn->query($pageViewsQuery);
        $pageViewsData = $pageViewsResult->fetch_assoc();

        // Video views
        $videoViewsQuery = "SELECT COUNT(*) as video_views FROM " . self::$videoViewsTable . " WHERE $dateFilter";
        $videoViewsResult = $conn->query($videoViewsQuery);
        $videoViewsData = $videoViewsResult->fetch_assoc();

        // Average session duration
        $avgSessionQuery = "SELECT AVG(duration) as avg_session_duration FROM " . self::$userSessionsTable . " WHERE $dateFilter AND duration > 0";
        $avgSessionResult = $conn->query($avgSessionQuery);
        $avgSessionData = $avgSessionResult->fetch_assoc();

        // Top content
        $topContentQuery = "SELECT video_id, video_title, COUNT(*) as views
                           FROM " . self::$videoViewsTable . "
                           WHERE $dateFilter
                           GROUP BY video_id, video_title
                           ORDER BY views DESC
                           LIMIT 5";
        $topContentResult = $conn->query($topContentQuery);
        $topContent = [];
        while ($row = $topContentResult->fetch_assoc()) {
            $topContent[] = $row;
        }

        return [
            'total_users' => (int)$usersData['total_users'],
            'total_sessions' => (int)$sessionsData['total_sessions'],
            'page_views' => (int)$pageViewsData['page_views'],
            'video_views' => (int)$videoViewsData['video_views'],
            'avg_session_duration' => round((float)$avgSessionData['avg_session_duration'], 2),
            'top_content' => $topContent,
            'period_days' => (int)$period
        ];
    }

    /**
     * Get user demographics
     */
    public static function getUserDemographics($period = '30') {
        $conn = getDBConnection();

        $dateFilter = "pv.created_at >= DATE_SUB(NOW(), INTERVAL $period DAY)";

        // Device types
        $deviceQuery = "SELECT device_type, COUNT(*) as count
                       FROM " . self::$pageViewsTable . " pv
                       WHERE $dateFilter AND device_type != ''
                       GROUP BY device_type
                       ORDER BY count DESC";
        $deviceResult = $conn->query($deviceQuery);
        $devices = [];
        while ($row = $deviceResult->fetch_assoc()) {
            $devices[] = $row;
        }

        // Browsers
        $browserQuery = "SELECT browser, COUNT(*) as count
                        FROM " . self::$pageViewsTable . " pv
                        WHERE $dateFilter AND browser != ''
                        GROUP BY browser
                        ORDER BY count DESC
                        LIMIT 10";
        $browserResult = $conn->query($browserQuery);
        $browsers = [];
        while ($row = $browserResult->fetch_assoc()) {
            $browsers[] = $row;
        }

        // Operating systems
        $osQuery = "SELECT os, COUNT(*) as count
                   FROM " . self::$pageViewsTable . " pv
                   WHERE $dateFilter AND os != ''
                   GROUP BY os
                   ORDER BY count DESC
                   LIMIT 10";
        $osResult = $conn->query($osQuery);
        $operatingSystems = [];
        while ($row = $osResult->fetch_assoc()) {
            $operatingSystems[] = $row;
        }

        // Geographic data
        $geoQuery = "SELECT country, city, COUNT(*) as count
                    FROM " . self::$pageViewsTable . " pv
                    WHERE $dateFilter AND country != ''
                    GROUP BY country, city
                    ORDER BY count DESC
                    LIMIT 20";
        $geoResult = $conn->query($geoQuery);
        $geographic = [];
        while ($row = $geoResult->fetch_assoc()) {
            $geographic[] = $row;
        }

        return [
            'devices' => $devices,
            'browsers' => $browsers,
            'operating_systems' => $operatingSystems,
            'geographic' => $geographic,
            'period_days' => (int)$period
        ];
    }

    /**
     * Get content performance metrics
     */
    public static function getContentPerformance($period = '30') {
        $conn = getDBConnection();

        $dateFilter = "DATE(vv.created_at) >= DATE_SUB(CURDATE(), INTERVAL $period DAY)";

        // Video performance
        $videoPerfQuery = "SELECT
                            vv.video_id,
                            vv.video_title,
                            COUNT(*) as total_views,
                            AVG(vv.watch_duration) as avg_watch_duration,
                            AVG(vv.completion_rate) as avg_completion_rate,
                            SUM(vv.watch_duration) as total_watch_time,
                            COUNT(DISTINCT vv.user_id) as unique_viewers
                          FROM " . self::$videoViewsTable . " vv
                          WHERE $dateFilter
                          GROUP BY vv.video_id, vv.video_title
                          ORDER BY total_views DESC
                          LIMIT 20";

        $videoPerfResult = $conn->query($videoPerfQuery);
        $videoPerformance = [];
        while ($row = $videoPerfResult->fetch_assoc()) {
            $videoPerformance[] = [
                'video_id' => $row['video_id'],
                'video_title' => $row['video_title'],
                'total_views' => (int)$row['total_views'],
                'avg_watch_duration' => round((float)$row['avg_watch_duration'], 2),
                'avg_completion_rate' => round((float)$row['avg_completion_rate'], 2),
                'total_watch_time' => (int)$row['total_watch_time'],
                'unique_viewers' => (int)$row['unique_viewers']
            ];
        }

        // Content engagement
        $engagementQuery = "SELECT
                            content_type,
                            action_type,
                            COUNT(*) as count
                           FROM " . self::$contentEngagementTable . " ce
                           WHERE DATE(ce.created_at) >= DATE_SUB(CURDATE(), INTERVAL $period DAY)
                           GROUP BY content_type, action_type
                           ORDER BY count DESC";

        $engagementResult = $conn->query($engagementQuery);
        $engagement = [];
        while ($row = $engagementResult->fetch_assoc()) {
            $engagement[] = $row;
        }

        // Popular categories
        $categoryQuery = "SELECT
                           c.name as category_name,
                           COUNT(vv.id) as video_views
                          FROM " . self::$videoViewsTable . " vv
                          JOIN videos v ON vv.video_id = v.id
                          LEFT JOIN categories c ON v.category_id = c.id
                          WHERE $dateFilter
                          GROUP BY c.id, c.name
                          ORDER BY video_views DESC
                          LIMIT 10";

        $categoryResult = $conn->query($categoryQuery);
        $categories = [];
        while ($row = $categoryResult->fetch_assoc()) {
            $categories[] = $row;
        }

        return [
            'video_performance' => $videoPerformance,
            'engagement' => $engagement,
            'categories' => $categories,
            'period_days' => (int)$period
        ];
    }

    /**
     * Get engagement analytics
     */
    public static function getEngagementAnalytics($period = '30') {
        $conn = getDBConnection();

        $dateFilter = "DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL $period DAY)";

        // Social interactions
        $socialQuery = "SELECT
                        'comments' as type, COUNT(*) as count FROM comments WHERE $dateFilter
                        UNION ALL
                        SELECT 'reactions' as type, COUNT(*) as count FROM reactions WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL $period DAY)
                        UNION ALL
                        SELECT 'favorites' as type, COUNT(*) as count FROM user_favorites WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL $period DAY)";

        $socialResult = $conn->query($socialQuery);
        $socialInteractions = [];
        while ($row = $socialResult->fetch_assoc()) {
            $socialInteractions[] = $row;
        }

        // Time-based analytics
        $timeQuery = "SELECT
                      HOUR(created_at) as hour,
                      COUNT(*) as activity_count
                     FROM " . self::$pageViewsTable . "
                     WHERE $dateFilter
                     GROUP BY HOUR(created_at)
                     ORDER BY hour";

        $timeResult = $conn->query($timeQuery);
        $hourlyActivity = [];
        while ($row = $timeResult->fetch_assoc()) {
            $hourlyActivity[] = $row;
        }

        // Day of week analytics
        $dowQuery = "SELECT
                    DAYNAME(created_at) as day_name,
                    DAYOFWEEK(created_at) as day_of_week,
                    COUNT(*) as activity_count
                   FROM " . self::$pageViewsTable . "
                   WHERE $dateFilter
                   GROUP BY DAYOFWEEK(created_at), DAYNAME(created_at)
                   ORDER BY day_of_week";

        $dowResult = $conn->query($dowQuery);
        $dailyActivity = [];
        while ($row = $dowResult->fetch_assoc()) {
            $dailyActivity[] = $row;
        }

        return [
            'social_interactions' => $socialInteractions,
            'hourly_activity' => $hourlyActivity,
            'daily_activity' => $dailyActivity,
            'period_days' => (int)$period
        ];
    }

    /**
     * Get real-time metrics
     */
    public static function getRealtimeMetrics() {
        $conn = getDBConnection();

        // Last 24 hours
        $last24h = "created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";

        // Active users (last 5 minutes)
        $activeUsersQuery = "SELECT COUNT(DISTINCT user_id) as active_users
                           FROM " . self::$pageViewsTable . "
                           WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                           AND user_id IS NOT NULL";

        $activeUsersResult = $conn->query($activeUsersQuery);
        $activeUsers = $activeUsersResult->fetch_assoc();

        // Current video views (last 24h)
        $videoViewsQuery = "SELECT COUNT(*) as video_views_24h
                          FROM " . self::$videoViewsTable . "
                          WHERE $last24h";

        $videoViewsResult = $conn->query($videoViewsQuery);
        $videoViews = $videoViewsResult->fetch_assoc();

        // Page views (last 24h)
        $pageViewsQuery = "SELECT COUNT(*) as page_views_24h
                         FROM " . self::$pageViewsTable . "
                         WHERE $last24h";

        $pageViewsResult = $conn->query($pageViewsQuery);
        $pageViews = $pageViewsResult->fetch_assoc();

        // Top pages right now
        $topPagesQuery = "SELECT page_title, COUNT(*) as views
                        FROM " . self::$pageViewsTable . "
                        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                        GROUP BY page_title
                        ORDER BY views DESC
                        LIMIT 5";

        $topPagesResult = $conn->query($topPagesQuery);
        $topPages = [];
        while ($row = $topPagesResult->fetch_assoc()) {
            $topPages[] = $row;
        }

        return [
            'active_users' => (int)$activeUsers['active_users'],
            'video_views_24h' => (int)$videoViews['video_views_24h'],
            'page_views_24h' => (int)$pageViews['page_views_24h'],
            'top_pages' => $topPages,
            'timestamp' => date('c')
        ];
    }

    // Utility functions

    /**
     * Detect device type from user agent
     */
    private static function getDeviceType($userAgent) {
        $userAgent = strtolower($userAgent);

        if (strpos($userAgent, 'mobile') !== false ||
            strpos($userAgent, 'android') !== false ||
            strpos($userAgent, 'iphone') !== false ||
            strpos($userAgent, 'ipad') !== false ||
            strpos($userAgent, 'ipod') !== false) {
            return 'mobile';
        }

        if (strpos($userAgent, 'tablet') !== false ||
            strpos($userAgent, 'ipad') !== false) {
            return 'tablet';
        }

        return 'desktop';
    }

    /**
     * Detect browser from user agent
     */
    private static function getBrowser($userAgent) {
        $userAgent = strtolower($userAgent);

        if (strpos($userAgent, 'chrome') !== false && strpos($userAgent, 'edg') === false) {
            return 'Chrome';
        }
        if (strpos($userAgent, 'firefox') !== false) {
            return 'Firefox';
        }
        if (strpos($userAgent, 'safari') !== false && strpos($userAgent, 'chrome') === false) {
            return 'Safari';
        }
        if (strpos($userAgent, 'edg') !== false) {
            return 'Edge';
        }
        if (strpos($userAgent, 'opera') !== false) {
            return 'Opera';
        }

        return 'Other';
    }

    /**
     * Detect OS from user agent
     */
    private static function getOS($userAgent) {
        $userAgent = strtolower($userAgent);

        if (strpos($userAgent, 'windows') !== false) {
            return 'Windows';
        }
        if (strpos($userAgent, 'macintosh') !== false || strpos($userAgent, 'mac os') !== false) {
            return 'macOS';
        }
        if (strpos($userAgent, 'linux') !== false) {
            return 'Linux';
        }
        if (strpos($userAgent, 'android') !== false) {
            return 'Android';
        }
        if (strpos($userAgent, 'ios') !== false || strpos($userAgent, 'iphone') !== false || strpos($userAgent, 'ipad') !== false) {
            return 'iOS';
        }

        return 'Other';
    }

    /**
     * Clean up old analytics data (retention policy)
     */
    public static function cleanupOldData($daysToKeep = 365) {
        $conn = getDBConnection();

        $tables = [
            self::$pageViewsTable,
            self::$videoViewsTable,
            self::$userSessionsTable,
            self::$contentEngagementTable,
            self::$geographicDataTable
        ];

        $deletedRows = 0;

        foreach ($tables as $table) {
            $sql = "DELETE FROM $table WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $daysToKeep);
            $stmt->execute();
            $deletedRows += $stmt->affected_rows;
        }

        return $deletedRows;
    }
}
?>