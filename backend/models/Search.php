<?php
/**
 * Enhanced Search Model
 * Provides advanced search capabilities with AI-powered recommendations
 */

require_once __DIR__ . '/../config/database.php';

class Search {
    private static $searchHistoryTable = 'search_history';

    /**
     * Perform enhanced search across videos, livestreams, and content
     */
    public static function enhancedSearch($query, $userId = null, $filters = [], $limit = 20, $offset = 0) {
        $conn = getDBConnection();

        // Build search conditions
        $searchConditions = self::buildSearchConditions($query, $filters);
        $whereClause = $searchConditions['where'];
        $params = $searchConditions['params'];

        // Main search query with relevance scoring
        $sql = "SELECT
                    v.id,
                    v.title,
                    v.description,
                    v.youtube_id,
                    v.thumbnail_url,
                    v.published_at,
                    v.duration,
                    v.view_count,
                    v.category_id,
                    c.name as category_name,
                    v.speaker,
                    v.tags,
                    v.created_at,
                    " . self::buildRelevanceScore($query) . " as relevance_score,
                    MATCH(v.title, v.description, v.tags, v.speaker) AGAINST (? IN NATURAL LANGUAGE MODE) as fulltext_score
                FROM videos v
                LEFT JOIN categories c ON v.category_id = c.id
                WHERE v.is_active = 1 {$whereClause}
                ORDER BY relevance_score DESC, fulltext_score DESC, v.view_count DESC
                LIMIT ? OFFSET ?";

        $stmt = $conn->prepare($sql);

        // Bind all parameters
        $paramTypes = str_repeat('s', count($params)) . 'ii';
        $bindParams = array_merge($params, [$query, $limit, $offset]);
        $stmt->bind_param($paramTypes, ...$bindParams);

        $stmt->execute();
        $result = $stmt->get_result();

        $videos = [];
        while ($row = $result->fetch_assoc()) {
            $videos[] = $row;
        }

        // Get total count for pagination
        $countSql = "SELECT COUNT(*) as total FROM videos v WHERE v.is_active = 1 {$whereClause}";
        $countStmt = $conn->prepare($countSql);
        $countStmt->bind_param(str_repeat('s', count($params)), ...$params);
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        $totalCount = $countResult->fetch_assoc()['total'];

        // Log search for analytics
        if ($userId && !empty($query)) {
            self::logSearchQuery($query, $userId, count($videos), $filters);
        }

        return [
            'results' => $videos,
            'total' => (int)$totalCount,
            'query' => $query,
            'filters' => $filters,
            'pagination' => [
                'limit' => $limit,
                'offset' => $offset,
                'total_pages' => ceil($totalCount / $limit),
                'current_page' => floor($offset / $limit) + 1
            ]
        ];
    }

    /**
     * Build search conditions based on filters
     */
    private static function buildSearchConditions($query, $filters) {
        $conditions = [];
        $params = [];

        // Full-text search condition
        if (!empty($query)) {
            $conditions[] = "MATCH(v.title, v.description, v.tags, v.speaker) AGAINST (? IN NATURAL LANGUAGE MODE)";
            $params[] = $query;
        }

        // Category filter
        if (!empty($filters['category_id'])) {
            $conditions[] = "v.category_id = ?";
            $params[] = $filters['category_id'];
        }

        // Date range filter
        if (!empty($filters['date_from'])) {
            $conditions[] = "v.published_at >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $conditions[] = "v.published_at <= ?";
            $params[] = $filters['date_to'];
        }

        // Duration filter
        if (!empty($filters['duration_min'])) {
            $conditions[] = "v.duration >= ?";
            $params[] = $filters['duration_min'];
        }
        if (!empty($filters['duration_max'])) {
            $conditions[] = "v.duration <= ?";
            $params[] = $filters['duration_max'];
        }

        // Speaker filter
        if (!empty($filters['speaker'])) {
            $conditions[] = "v.speaker LIKE ?";
            $params[] = '%' . $filters['speaker'] . '%';
        }

        // Content type filter (video, livestream, etc.)
        if (!empty($filters['content_type'])) {
            if ($filters['content_type'] === 'livestream') {
                $conditions[] = "v.is_livestream = 1";
            } elseif ($filters['content_type'] === 'video') {
                $conditions[] = "v.is_livestream = 0";
            }
        }

        // Language filter
        if (!empty($filters['language'])) {
            $conditions[] = "v.language = ?";
            $params[] = $filters['language'];
        }

        // Tags filter
        if (!empty($filters['tags'])) {
            $tagConditions = [];
            foreach ($filters['tags'] as $tag) {
                $tagConditions[] = "FIND_IN_SET(?, v.tags) > 0";
                $params[] = $tag;
            }
            $conditions[] = "(" . implode(" OR ", $tagConditions) . ")";
        }

        $whereClause = !empty($conditions) ? " AND " . implode(" AND ", $conditions) : "";

        return [
            'where' => $whereClause,
            'params' => $params
        ];
    }

    /**
     * Build relevance scoring formula
     */
    private static function buildRelevanceScore($query) {
        $query = strtolower($query);

        return "
        (
            /* Exact title match gets highest score */
            CASE WHEN LOWER(v.title) LIKE CONCAT('%', ?, '%') THEN 100 ELSE 0 END +

            /* Title starts with query */
            CASE WHEN LOWER(v.title) LIKE CONCAT(?, '%') THEN 50 ELSE 0 END +

            /* Speaker match */
            CASE WHEN LOWER(v.speaker) LIKE CONCAT('%', ?, '%') THEN 30 ELSE 0 END +

            /* Tags match */
            CASE WHEN LOWER(v.tags) LIKE CONCAT('%', ?, '%') THEN 20 ELSE 0 END +

            /* Recent content bonus */
            CASE WHEN v.published_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 15 ELSE 0 END +

            /* Popular content bonus */
            CASE WHEN v.view_count > 1000 THEN 10 ELSE 0 END +

            /* Description match */
            CASE WHEN LOWER(v.description) LIKE CONCAT('%', ?, '%') THEN 5 ELSE 0 END
        )";
    }

    /**
     * Get search suggestions based on popular searches and content
     */
    public static function getSearchSuggestions($query, $userId = null, $limit = 10) {
        try {
            $conn = getDBConnection();

            if (empty($query)) {
            // For empty queries, return some default suggestions based on available videos
            // Instead of using search_history table which might not exist
            $sql = "SELECT DISTINCT title as suggestion, 'popular' as type, COUNT(*) as relevance
                    FROM videos
                    WHERE is_active = 1
                    GROUP BY title
                    ORDER BY relevance DESC
                    LIMIT ?";
        } else {
            // Return fuzzy matches and suggestions with video IDs - more permissive search
            $sql = "SELECT DISTINCT
                        CASE
                            WHEN title LIKE CONCAT('%', ?, '%') THEN title
                            WHEN channel_title LIKE CONCAT('%', ?, '%') THEN CONCAT('by ', channel_title)
                            WHEN tags LIKE CONCAT('%', ?, '%') THEN tags
                        END as suggestion,
                        'content' as type,
                        COUNT(*) as relevance,
                        -- Get the most viewed video ID that matches this suggestion pattern
                        (SELECT id FROM videos v2
                         WHERE v2.is_active = 1
                         AND ((v.title LIKE CONCAT('%', ?, '%') AND v2.title = v.title)
                              OR (v.channel_title LIKE CONCAT('%', ?, '%') AND v2.channel_title = v.channel_title)
                              OR (v.tags LIKE CONCAT('%', ?, '%') AND v2.tags LIKE CONCAT('%', v.tags, '%')))
                         ORDER BY v2.view_count DESC LIMIT 1) as video_id
                    FROM videos v
                    WHERE (title LIKE CONCAT('%', ?, '%') OR channel_title LIKE CONCAT('%', ?, '%') OR tags LIKE CONCAT('%', ?, '%'))
                    AND is_active = 1
                    GROUP BY suggestion
                    ORDER BY relevance DESC, suggestion
                    LIMIT ?";
        }

        $stmt = $conn->prepare($sql);

        if (empty($query)) {
            $stmt->bind_param("i", $limit);
        } else {
            $fuzzyQuery = $query . '%';
                    $stmt->bind_param("sssssssi", $fuzzyQuery, $fuzzyQuery, $fuzzyQuery, $fuzzyQuery, $fuzzyQuery, $fuzzyQuery, $fuzzyQuery, $limit);
        }

        $stmt->execute();
        $result = $stmt->get_result();

            $suggestions = [];
            while ($row = $result->fetch_assoc()) {
                $suggestions[] = $row;
            }

            return $suggestions;
        } catch (Exception $e) {
            error_log("Search::getSearchSuggestions Error: " . $e->getMessage());
            error_log("Query: '$query', Limit: $limit");
            throw $e;
        }
    }

    /**
     * Log search query for analytics
     */
    private static function logSearchQuery($query, $userId, $resultCount, $filters) {
        $conn = getDBConnection();

        $sql = "INSERT INTO " . self::$searchHistoryTable . "
                (user_id, query, result_count, filters, ip_address, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        $filtersJson = json_encode($filters);
        $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $createdAt = date('Y-m-d H:i:s');

        $stmt->bind_param("isissss", $userId, $query, $resultCount, $filtersJson, $ipAddress, $userAgent, $createdAt);
        $stmt->execute();
    }

    /**
     * Get popular search terms
     */
    public static function getPopularSearches($limit = 20, $days = 30) {
        $conn = getDBConnection();

        $sql = "SELECT query, COUNT(*) as search_count, AVG(result_count) as avg_results
                FROM " . self::$searchHistoryTable . "
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY query
                ORDER BY search_count DESC
                LIMIT ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $days, $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        $searches = [];
        while ($row = $result->fetch_assoc()) {
            $searches[] = $row;
        }

        return $searches;
    }

    /**
     * Get AI-powered recommendations for a user
     */
    public static function getRecommendations($userId, $limit = 10, $excludeWatched = true) {
        $conn = getDBConnection();

        // Get user's viewing history and preferences
        $userHistory = self::getUserViewingHistory($userId);
        $userPreferences = self::analyzeUserPreferences($userId);

        // Build recommendation query based on user behavior
        $recommendations = [];

        // 1. Content similar to highly-rated videos
        if (!empty($userHistory['liked_categories'])) {
            $categoryRecs = self::getCategoryRecommendations($userHistory['liked_categories'], $userId, $limit / 3);
            $recommendations = array_merge($recommendations, $categoryRecs);
        }

        // 2. Popular content in user's preferred categories
        if (!empty($userPreferences['top_categories'])) {
            $popularRecs = self::getPopularRecommendations($userPreferences['top_categories'], $userId, $limit / 3);
            $recommendations = array_merge($recommendations, $popularRecs);
        }

        // 3. Trending content
        $trendingRecs = self::getTrendingRecommendations($userId, $limit / 3);
        $recommendations = array_merge($recommendations, $trendingRecs);

        // Remove duplicates and limit results
        $recommendations = self::deduplicateRecommendations($recommendations);
        $recommendations = array_slice($recommendations, 0, $limit);

        // Add recommendation reasons
        foreach ($recommendations as &$rec) {
            $rec['reason'] = self::generateRecommendationReason($rec, $userPreferences);
        }

        return [
            'recommendations' => $recommendations,
            'user_preferences' => $userPreferences,
            'generated_at' => date('c')
        ];
    }

    /**
     * Get user's viewing history analysis
     */
    private static function getUserViewingHistory($userId) {
        $conn = getDBConnection();

        // Get recent viewing history
        $sql = "SELECT v.category_id, v.speaker, v.tags, vv.completion_rate, vv.created_at
                FROM video_views vv
                JOIN videos v ON vv.video_id = v.id
                WHERE vv.user_id = ? AND vv.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                ORDER BY vv.created_at DESC
                LIMIT 100";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        $history = [];
        while ($row = $result->fetch_assoc()) {
            $history[] = $row;
        }

        // Analyze preferences
        $categories = [];
        $speakers = [];
        $tags = [];
        $completionRates = [];

        foreach ($history as $item) {
            if ($item['category_id']) {
                $categories[] = $item['category_id'];
            }
            if ($item['speaker']) {
                $speakers[] = $item['speaker'];
            }
            if ($item['tags']) {
                $tags = array_merge($tags, explode(',', $item['tags']));
            }
            if ($item['completion_rate']) {
                $completionRates[] = $item['completion_rate'];
            }
        }

        return [
            'total_views' => count($history),
            'liked_categories' => array_count_values($categories),
            'preferred_speakers' => array_count_values($speakers),
            'interested_tags' => array_count_values($tags),
            'avg_completion_rate' => !empty($completionRates) ? array_sum($completionRates) / count($completionRates) : 0
        ];
    }

    /**
     * Analyze user preferences based on behavior
     */
    private static function analyzeUserPreferences($userId) {
        $conn = getDBConnection();

        // Get user's favorites and ratings
        $sql = "SELECT v.category_id, v.speaker, v.tags, COUNT(*) as interactions
                FROM user_favorites uf
                JOIN videos v ON uf.video_id = v.id
                WHERE uf.user_id = ? AND uf.created_at >= DATE_SUB(NOW(), INTERVAL 180 DAY)
                GROUP BY v.category_id, v.speaker, v.tags";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        $preferences = [];
        while ($row = $result->fetch_assoc()) {
            $preferences[] = $row;
        }

        // Extract top preferences
        $topCategories = [];
        $topSpeakers = [];
        $topTags = [];

        foreach ($preferences as $pref) {
            if ($pref['category_id']) {
                $topCategories[$pref['category_id']] = ($topCategories[$pref['category_id']] ?? 0) + $pref['interactions'];
            }
            if ($pref['speaker']) {
                $topSpeakers[$pref['speaker']] = ($topSpeakers[$pref['speaker']] ?? 0) + $pref['interactions'];
            }
            if ($pref['tags']) {
                $tags = explode(',', $pref['tags']);
                foreach ($tags as $tag) {
                    $topTags[$tag] = ($topTags[$tag] ?? 0) + $pref['interactions'];
                }
            }
        }

        arsort($topCategories);
        arsort($topSpeakers);
        arsort($topTags);

        return [
            'top_categories' => array_keys(array_slice($topCategories, 0, 5)),
            'top_speakers' => array_keys(array_slice($topSpeakers, 0, 5)),
            'top_tags' => array_keys(array_slice($topTags, 0, 3))
        ];
    }

    /**
     * Get category-based recommendations
     */
    private static function getCategoryRecommendations($categoryWeights, $userId, $limit) {
        $conn = getDBConnection();

        $categoryIds = array_keys($categoryWeights);
        $placeholders = str_repeat('?,', count($categoryIds) - 1) . '?';

        $sql = "SELECT v.*, c.name as category_name,
                       (CASE " . implode(' ', array_map(function($catId) use ($categoryWeights) {
                           return "WHEN v.category_id = ? THEN " . ($categoryWeights[$catId] ?? 1);
                       }, $categoryIds)) . " END) as preference_score
                FROM videos v
                LEFT JOIN categories c ON v.category_id = c.id
                WHERE v.is_active = 1
                AND v.category_id IN ($placeholders)
                AND v.id NOT IN (
                    SELECT video_id FROM video_views WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                )
                ORDER BY preference_score DESC, v.view_count DESC, v.published_at DESC
                LIMIT ?";

        $stmt = $conn->prepare($sql);

        $params = array_merge($categoryIds, [$userId, $limit]);
        $types = str_repeat('i', count($params));
        $stmt->bind_param($types, ...$params);

        $stmt->execute();
        $result = $stmt->get_result();

        $recommendations = [];
        while ($row = $result->fetch_assoc()) {
            $recommendations[] = $row;
        }

        return $recommendations;
    }

    /**
     * Get popular recommendations in user's preferred categories
     */
    private static function getPopularRecommendations($categoryIds, $userId, $limit) {
        $conn = getDBConnection();

        $placeholders = str_repeat('?,', count($categoryIds) - 1) . '?';

        $sql = "SELECT v.*, c.name as category_name,
                       v.view_count as popularity_score
                FROM videos v
                LEFT JOIN categories c ON v.category_id = c.id
                WHERE v.is_active = 1
                AND v.category_id IN ($placeholders)
                AND v.published_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                AND v.id NOT IN (
                    SELECT video_id FROM video_views WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                )
                ORDER BY v.view_count DESC, v.published_at DESC
                LIMIT ?";

        $stmt = $conn->prepare($sql);

        $params = array_merge($categoryIds, [$userId, $limit]);
        $types = str_repeat('i', count($params));
        $stmt->bind_param($types, ...$params);

        $stmt->execute();
        $result = $stmt->get_result();

        $recommendations = [];
        while ($row = $result->fetch_assoc()) {
            $recommendations[] = $row;
        }

        return $recommendations;
    }

    /**
     * Get trending content recommendations
     */
    private static function getTrendingRecommendations($userId, $limit) {
        $conn = getDBConnection();

        $sql = "SELECT v.*, c.name as category_name,
                       (v.view_count / GREATEST(DATEDIFF(NOW(), v.published_at), 1)) as trending_score
                FROM videos v
                LEFT JOIN categories c ON v.category_id = c.id
                WHERE v.is_active = 1
                AND v.published_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND v.id NOT IN (
                    SELECT video_id FROM video_views WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                )
                ORDER BY trending_score DESC
                LIMIT ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $userId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        $recommendations = [];
        while ($row = $result->fetch_assoc()) {
            $recommendations[] = $row;
        }

        return $recommendations;
    }

    /**
     * Remove duplicate recommendations
     */
    private static function deduplicateRecommendations($recommendations) {
        $seen = [];
        $unique = [];

        foreach ($recommendations as $rec) {
            if (!isset($seen[$rec['id']])) {
                $seen[$rec['id']] = true;
                $unique[] = $rec;
            }
        }

        return $unique;
    }

    /**
     * Generate recommendation reason text
     */
    private static function generateRecommendationReason($video, $userPreferences) {
        $reasons = [];

        // Category match
        if ($video['category_id'] && in_array($video['category_id'], $userPreferences['top_categories'] ?? [])) {
            $reasons[] = "Based on your interest in this category";
        }

        // Speaker match
        if ($video['speaker'] && in_array($video['speaker'], $userPreferences['top_speakers'] ?? [])) {
            $reasons[] = "From a speaker you enjoy";
        }

        // Recent and popular
        if (strtotime($video['published_at']) > strtotime('-7 days')) {
            $reasons[] = "Recently published";
        }

        if (($video['view_count'] ?? 0) > 1000) {
            $reasons[] = "Popular content";
        }

        // Trending
        if (isset($video['trending_score']) && $video['trending_score'] > 10) {
            $reasons[] = "Trending now";
        }

        return !empty($reasons) ? $reasons[0] : "Recommended for you";
    }

    /**
     * Create smart playlists based on user preferences
     */
    public static function createSmartPlaylist($userId, $playlistType, $limit = 20) {
        $conn = getDBConnection();

        switch ($playlistType) {
            case 'continue_watching':
                return self::getContinueWatchingPlaylist($userId, $limit);

            case 'favorites_genre':
                return self::getFavoritesGenrePlaylist($userId, $limit);

            case 'recently_watched':
                return self::getRecentlyWatchedPlaylist($userId, $limit);

            case 'trending':
                return self::getTrendingPlaylist($limit);

            case 'similar_content':
                return self::getSimilarContentPlaylist($userId, $limit);

            default:
                return [];
        }
    }

    /**
     * Get continue watching playlist
     */
    private static function getContinueWatchingPlaylist($userId, $limit) {
        $conn = getDBConnection();

        $sql = "SELECT v.*, vv.watch_duration, vv.total_duration,
                       (vv.watch_duration / vv.total_duration * 100) as progress_percentage
                FROM video_views vv
                JOIN videos v ON vv.video_id = v.id
                WHERE vv.user_id = ?
                AND vv.completion_rate < 90
                AND vv.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                ORDER BY vv.created_at DESC
                LIMIT ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $userId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        $videos = [];
        while ($row = $result->fetch_assoc()) {
            $videos[] = $row;
        }

        return $videos;
    }

    /**
     * Get playlist based on favorite genres
     */
    private static function getFavoritesGenrePlaylist($userId, $limit) {
        $conn = getDBConnection();

        // Get user's most favorited category
        $sql = "SELECT v.category_id, COUNT(*) as favorites_count
                FROM user_favorites uf
                JOIN videos v ON uf.video_id = v.id
                WHERE uf.user_id = ?
                GROUP BY v.category_id
                ORDER BY favorites_count DESC
                LIMIT 1";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $category = $result->fetch_assoc();

        if (!$category) return [];

        // Get videos from that category
        $sql2 = "SELECT v.*, c.name as category_name
                 FROM videos v
                 LEFT JOIN categories c ON v.category_id = c.id
                 WHERE v.category_id = ?
                 AND v.is_active = 1
                 ORDER BY v.view_count DESC, v.published_at DESC
                 LIMIT ?";

        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param("ii", $category['category_id'], $limit);
        $stmt2->execute();
        $result2 = $stmt2->get_result();

        $videos = [];
        while ($row = $result2->fetch_assoc()) {
            $videos[] = $row;
        }

        return $videos;
    }

    /**
     * Get recently watched playlist
     */
    private static function getRecentlyWatchedPlaylist($userId, $limit) {
        $conn = getDBConnection();

        $sql = "SELECT DISTINCT v.*
                FROM video_views vv
                JOIN videos v ON vv.video_id = v.id
                WHERE vv.user_id = ?
                ORDER BY vv.created_at DESC
                LIMIT ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $userId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        $videos = [];
        while ($row = $result->fetch_assoc()) {
            $videos[] = $row;
        }

        return $videos;
    }

    /**
     * Get trending playlist
     */
    private static function getTrendingPlaylist($limit) {
        $conn = getDBConnection();

        $sql = "SELECT v.*, c.name as category_name,
                       (v.view_count / GREATEST(DATEDIFF(NOW(), v.published_at), 1)) as trending_score
                FROM videos v
                LEFT JOIN categories c ON v.category_id = c.id
                WHERE v.is_active = 1
                AND v.published_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                ORDER BY trending_score DESC
                LIMIT ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        $videos = [];
        while ($row = $result->fetch_assoc()) {
            $videos[] = $row;
        }

        return $videos;
    }

    /**
     * Get similar content playlist based on user's last watched video
     */
    private static function getSimilarContentPlaylist($userId, $limit) {
        $conn = getDBConnection();

        // Get user's last watched video
        $sql = "SELECT v.category_id, v.speaker, v.tags
                FROM video_views vv
                JOIN videos v ON vv.video_id = v.id
                WHERE vv.user_id = ?
                ORDER BY vv.created_at DESC
                LIMIT 1";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $lastVideo = $result->fetch_assoc();

        if (!$lastVideo) return [];

        // Find similar videos
        $sql2 = "SELECT v.*, c.name as category_name
                 FROM videos v
                 LEFT JOIN categories c ON v.category_id = c.id
                 WHERE v.is_active = 1
                 AND v.id != (SELECT vv.video_id FROM video_views vv WHERE vv.user_id = ? ORDER BY vv.created_at DESC LIMIT 1)
                 AND (
                     v.category_id = ? OR
                     v.speaker = ? OR
                     v.tags LIKE CONCAT('%', ?, '%')
                 )
                 ORDER BY v.view_count DESC, v.published_at DESC
                 LIMIT ?";

        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param("iissi", $userId, $lastVideo['category_id'], $lastVideo['speaker'], $lastVideo['tags'], $limit);
        $stmt2->execute();
        $result2 = $stmt2->get_result();

        $videos = [];
        while ($row = $result2->fetch_assoc()) {
            $videos[] = $row;
        }

        return $videos;
    }
}
?>