<?php
/**
 * VideoView Model
 * Handles video view analytics
 */

require_once __DIR__ . '/../config/database.php';

class VideoView {
    private static $table = 'video_views';

    /**
     * Record or update a video view
     */
    public static function record($data) {
        $conn = getDBConnection();

        // Check if we already have a view record for this session/video
        $existing = self::getExistingView($data['video_id'], $data['session_id']);

        if ($existing) {
            // Update existing record
            $sql = "UPDATE " . self::$table . " SET
                    watch_duration = GREATEST(watch_duration, ?),
                    watch_percentage = GREATEST(watch_percentage, ?),
                    quality = COALESCE(?, quality),
                    playback_speed = COALESCE(?, playback_speed),
                    completed = ?,
                    updated_at = NOW()
                    WHERE id = ?";

            $stmt = $conn->prepare($sql);
            $completed = $data['watch_percentage'] >= 95 ? 1 : 0;
            $stmt->bind_param(
                "idssii",
                $data['watch_duration'],
                $data['watch_percentage'],
                $data['quality'],
                $data['playback_speed'],
                $completed,
                $existing['id']
            );

            return $stmt->execute();
        } else {
            // Insert new record
            $sql = "INSERT INTO " . self::$table . "
                    (video_id, user_id, session_id, youtube_id, watch_duration, total_duration,
                     watch_percentage, quality, playback_speed, referrer_url, user_agent,
                     ip_address, device_type, browser, os, country, city, completed)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);
            $completed = $data['watch_percentage'] >= 95 ? 1 : 0;

            // Prepare all parameters, handling nulls properly
            $params = [
                $data['video_id'],        // 0: int
                $data['user_id'],         // 1: int (can be null)
                $data['session_id'],      // 2: string
                $data['youtube_id'],      // 3: string
                $data['watch_duration'],  // 4: int
                $data['total_duration'],  // 5: int
                $data['watch_percentage'], // 6: double
                $data['quality'] ?? null, // 7: string (can be null)
                $data['playback_speed'] ?? 1.0, // 8: double
                $data['referrer_url'] ?? '', // 9: string
                $data['user_agent'] ?? '', // 10: string
                $data['ip_address'] ?? '', // 11: string
                $data['device_type'] ?? 'unknown', // 12: string
                $data['browser'] ?? 'unknown', // 13: string
                $data['os'] ?? 'unknown', // 14: string
                $data['country'] ?? null, // 15: string (can be null)
                $data['city'] ?? null, // 16: string (can be null)
                $completed // 17: int
            ];

            // Create type string: i=int, d=double/float, s=string
            // 18 parameters: video_id(i), user_id(i), session_id(s), youtube_id(s), watch_duration(i),
            // total_duration(i), watch_percentage(d), quality(s), playback_speed(d), referrer_url(s),
            // user_agent(s), ip_address(s), device_type(s), browser(s), os(s), country(s), city(s), completed(i)
            $types = "iissiidsdssssssssi";

            $stmt->bind_param($types, ...$params);

            return $stmt->execute();
        }
    }

    /**
     * Get existing view record
     */
    private static function getExistingView($videoId, $sessionId) {
        $conn = getDBConnection();

        $sql = "SELECT id, watch_duration, watch_percentage FROM " . self::$table . "
                WHERE video_id = ? AND session_id = ? AND DATE(created_at) = CURDATE()
                ORDER BY created_at DESC LIMIT 1";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $videoId, $sessionId);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_assoc();
    }

    /**
     * Get video view statistics
     */
    public static function getStats($period = '30 days') {
        $conn = getDBConnection();

        // Total video views
        $sql = "SELECT COUNT(*) as total_views FROM " . self::$table . "
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL $period)";
        $result = $conn->query($sql);
        $totalViews = ($result && $row = $result->fetch_assoc()) ? $row['total_views'] : 0;

        // Unique video viewers
        $sql = "SELECT COUNT(DISTINCT session_id) as unique_viewers FROM " . self::$table . "
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL $period)";
        $result = $conn->query($sql);
        $uniqueViewers = ($result && $row = $result->fetch_assoc()) ? $row['unique_viewers'] : 0;

        // Most viewed videos
        $sql = "SELECT v.title, vv.youtube_id, COUNT(*) as views, AVG(vv.watch_percentage) as avg_completion
                FROM " . self::$table . " vv
                JOIN videos v ON vv.video_id = v.id
                WHERE vv.created_at >= DATE_SUB(NOW(), INTERVAL $period)
                GROUP BY vv.video_id, v.title, vv.youtube_id
                ORDER BY views DESC
                LIMIT 10";
        $result = $conn->query($sql);
        $topVideos = [];
        while ($row = $result->fetch_assoc()) {
            $topVideos[] = $row;
        }

        // Watch time statistics
        $sql = "SELECT
                    SUM(watch_duration) as total_watch_time,
                    AVG(watch_duration) as avg_watch_time,
                    AVG(watch_percentage) as avg_completion_rate,
                    COUNT(CASE WHEN completed = 1 THEN 1 END) as completed_views
                FROM " . self::$table . "
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL $period)";
        $result = $conn->query($sql);
        $watchStats = ($result && $row = $result->fetch_assoc()) ? $row : [
            'total_watch_time' => 0,
            'avg_watch_time' => 0,
            'avg_completion_rate' => 0,
            'completed_views' => 0
        ];

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

        return [
            'total_views' => $totalViews,
            'unique_viewers' => $uniqueViewers,
            'top_videos' => $topVideos,
            'watch_stats' => $watchStats,
            'devices' => $devices
        ];
    }

    /**
     * Get video views over time
     */
    public static function getViewsOverTime($days = 30) {
        $conn = getDBConnection();

        $sql = "SELECT DATE(created_at) as date, COUNT(*) as views, COUNT(DISTINCT session_id) as unique_viewers
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
     * Get video engagement metrics
     */
    public static function getEngagementMetrics($videoId = null, $period = '30 days') {
        $conn = getDBConnection();

        $whereClause = "WHERE created_at >= DATE_SUB(NOW(), INTERVAL $period)";
        if ($videoId) {
            $whereClause .= " AND video_id = " . (int)$videoId;
        }

        $sql = "SELECT
                    COUNT(*) as total_views,
                    AVG(watch_duration) as avg_watch_time,
                    AVG(watch_percentage) as avg_completion,
                    MIN(watch_percentage) as min_completion,
                    MAX(watch_percentage) as max_completion,
                    COUNT(CASE WHEN watch_percentage >= 25 THEN 1 END) as views_25_percent,
                    COUNT(CASE WHEN watch_percentage >= 50 THEN 1 END) as views_50_percent,
                    COUNT(CASE WHEN watch_percentage >= 75 THEN 1 END) as views_75_percent,
                    COUNT(CASE WHEN completed = 1 THEN 1 END) as completed_views
                FROM " . self::$table . " " . $whereClause;

        $result = $conn->query($sql);
        return $result->fetch_assoc();
    }

    /**
     * Get real-time video views
     */
    public static function getRealtimeViews($minutes = 5) {
        $conn = getDBConnection();

        $sql = "SELECT v.title, vv.youtube_id, vv.watch_duration, vv.created_at
                FROM " . self::$table . " vv
                JOIN videos v ON vv.video_id = v.id
                WHERE vv.created_at >= DATE_SUB(NOW(), INTERVAL $minutes MINUTE)
                ORDER BY vv.created_at DESC
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
