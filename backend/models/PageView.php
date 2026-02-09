<?php
/**
 * PageView Model
 * Handles page view analytics
 */

require_once __DIR__ . '/../config/database.php';

class PageView {
    private static $table = 'page_views';

    /**
     * Record a page view
     */
    public static function record($data) {
        try {
            $conn = getDBConnection();

            // Validate required fields
            if (empty($data['session_id']) || empty($data['page_url'])) {
                error_log("PageView::record - Missing required fields: session_id or page_url");
                return false;
            }

            // Check if table exists
            $tableCheck = $conn->query("SHOW TABLES LIKE '" . self::$table . "'");
            if ($tableCheck->num_rows === 0) {
                error_log("PageView::record - Table '" . self::$table . "' does not exist. Run migrate_analytics.php");
                return false;
            }

            $sql = "INSERT INTO " . self::$table . "
                    (user_id, session_id, page_url, page_title, referrer_url, user_agent, ip_address,
                     country, city, device_type, browser, os, screen_resolution, time_on_page)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);
            
            if (!$stmt) {
                error_log("PageView::record - Prepare failed: " . $conn->error);
                return false;
            }

            // Ensure all values are properly set
            $userId = $data['user_id'] ?? null;
            $sessionId = $data['session_id'];
            $pageUrl = $data['page_url'];
            $pageTitle = $data['page_title'] ?? null;
            $referrerUrl = $data['referrer_url'] ?? null;
            $userAgent = $data['user_agent'] ?? null;
            $ipAddress = $data['ip_address'] ?? null;
            $country = $data['country'] ?? null;
            $city = $data['city'] ?? null;
            $deviceType = $data['device_type'] ?? null;
            $browser = $data['browser'] ?? null;
            $os = $data['os'] ?? null;
            $screenResolution = $data['screen_resolution'] ?? null;
            $timeOnPage = $data['time_on_page'] ?? 0;

            $stmt->bind_param(
                "issssssssssssi",
                $userId,
                $sessionId,
                $pageUrl,
                $pageTitle,
                $referrerUrl,
                $userAgent,
                $ipAddress,
                $country,
                $city,
                $deviceType,
                $browser,
                $os,
                $screenResolution,
                $timeOnPage
            );

            $result = $stmt->execute();
            
            if (!$result) {
                error_log("PageView::record - Execute failed: " . $stmt->error);
            }
            
            $stmt->close();
            return $result;

        } catch (Exception $e) {
            error_log("PageView::record - Exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get page view statistics
     */
    public static function getStats($period = '30 days') {
        $conn = getDBConnection();

        // Total page views
        $sql = "SELECT COUNT(*) as total_views FROM " . self::$table . "
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL $period)";
        $result = $conn->query($sql);
        $totalViews = ($result && $row = $result->fetch_assoc()) ? $row['total_views'] : 0;

        // Unique visitors
        $sql = "SELECT COUNT(DISTINCT session_id) as unique_visitors FROM " . self::$table . "
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL $period)";
        $result = $conn->query($sql);
        $uniqueVisitors = ($result && $row = $result->fetch_assoc()) ? $row['unique_visitors'] : 0;

        // Top pages
        $sql = "SELECT page_url, page_title, COUNT(*) as views
                FROM " . self::$table . "
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL $period)
                GROUP BY page_url, page_title
                ORDER BY views DESC
                LIMIT 10";
        $result = $conn->query($sql);
        $topPages = [];
        while ($row = $result->fetch_assoc()) {
            $topPages[] = $row;
        }

        // Device breakdown
        $sql = "SELECT device_type, COUNT(*) as count
                FROM " . self::$table . "
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL $period)
                GROUP BY device_type
                ORDER BY count DESC";
        $result = $conn->query($sql);
        $devices = [];
        while ($row = $result->fetch_assoc()) {
            $devices[] = $row;
        }

        // Hourly activity for today
        $sql = "SELECT HOUR(created_at) as hour, COUNT(*) as views
                FROM " . self::$table . "
                WHERE DATE(created_at) = CURDATE()
                GROUP BY HOUR(created_at)
                ORDER BY hour";
        $result = $conn->query($sql);
        $hourlyActivity = [];
        while ($row = $result->fetch_assoc()) {
            $hourlyActivity[] = $row;
        }

        return [
            'total_views' => $totalViews,
            'unique_visitors' => $uniqueVisitors,
            'top_pages' => $topPages,
            'devices' => $devices,
            'hourly_activity' => $hourlyActivity
        ];
    }

    /**
     * Get page views over time (daily)
     */
    public static function getViewsOverTime($days = 30) {
        $conn = getDBConnection();

        $sql = "SELECT DATE(created_at) as date, COUNT(*) as views, COUNT(DISTINCT session_id) as unique_visitors
                FROM " . self::$table . "
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)
                GROUP BY DATE(created_at)
                ORDER BY date";

        $result = $conn->query($sql);
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }

        return $data;
    }

    /**
     * Get real-time page views (last N minutes)
     */
    public static function getRealtimeViews($minutes = 5) {
        $conn = getDBConnection();

        $sql = "SELECT page_url, page_title, created_at
                FROM " . self::$table . "
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL $minutes MINUTE)
                ORDER BY created_at DESC
                LIMIT 20";

        $result = $conn->query($sql);
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }

        return $data;
    }
}
?>
