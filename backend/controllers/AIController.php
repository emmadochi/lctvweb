<?php
/**
 * AI Controller
 * Handles integration with Python AI services
 */

require_once __DIR__ . '/../config/database.php';

class AIController {
    private $aiServices;

    public function __construct() {
        $this->aiServices = [
            'recommendations' => 'http://localhost:8000/api/v1',
            'search' => 'http://localhost:8001/api/v1',
            'analytics' => 'http://localhost:8002/api/v1'
        ];
    }

    /**
     * Get personalized recommendations for a user
     */
    public function getRecommendations($userId, $contextVideoId = null, $limit = 10) {
        try {
            $data = [
                'user_id' => (int)$userId,
                'limit' => (int)$limit,
                'include_explanations' => true
            ];

            if ($contextVideoId) {
                $data['context_video_id'] = (int)$contextVideoId;
            }

            $response = $this->callAIService('recommendations', 'POST', '/recommendations', $data);

            if ($response && isset($response['recommendations'])) {
                return $this->enrichRecommendationsWithVideoData($response['recommendations']);
            }

            return [];

        } catch (Exception $e) {
            error_log("AI recommendations error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Perform semantic search
     */
    public function search($query, $userId = null, $limit = 20) {
        try {
            $data = [
                'query' => trim($query),
                'limit' => (int)$limit,
                'include_metadata' => true
            ];

            if ($userId) {
                $data['user_id'] = (int)$userId;
            }

            $response = $this->callAIService('search', 'POST', '/search', $data);

            if ($response && isset($response['results'])) {
                return $response['results'];
            }

            return [];

        } catch (Exception $e) {
            error_log("AI search error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get user insights and analytics
     */
    public function getUserInsights($userId) {
        try {
            $response = $this->callAIService('recommendations', 'POST', '/user/insights', [
                'user_id' => (int)$userId
            ]);

            return $response ?: ['insights' => 'No insights available'];

        } catch (Exception $e) {
            error_log("AI user insights error: " . $e->getMessage());
            return ['insights' => 'Unable to load insights'];
        }
    }

    /**
     * Find similar videos
     */
    public function findSimilarVideos($videoId, $limit = 10) {
        try {
            $response = $this->callAIService('search', 'POST', '/search/similar', [
                'video_id' => (int)$videoId,
                'limit' => (int)$limit
            ]);

            if ($response && isset($response['similar_videos'])) {
                return $this->enrichRecommendationsWithVideoData($response['similar_videos']);
            }

            return [];

        } catch (Exception $e) {
            error_log("AI similar videos error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get AI service health status
     */
    public function getServiceHealth() {
        $health = [];

        foreach ($this->aiServices as $service => $baseUrl) {
            try {
                $response = $this->callAIService($service, 'GET', '/health');
                $health[$service] = [
                    'status' => 'healthy',
                    'response_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
                    'details' => $response
                ];
            } catch (Exception $e) {
                $health[$service] = [
                    'status' => 'unhealthy',
                    'error' => $e->getMessage()
                ];
            }
        }

        return $health;
    }

    /**
     * Get AI analytics overview
     */
    public function getAnalyticsOverview() {
        try {
            $response = $this->callAIService('analytics', 'GET', '/analytics/overview');
            return $response ?: ['error' => 'Analytics service unavailable'];
        } catch (Exception $e) {
            error_log("AI analytics error: " . $e->getMessage());
            return ['error' => 'Unable to load analytics'];
        }
    }

    /**
     * Call AI service via HTTP
     */
    private function callAIService($service, $method, $endpoint, $data = null) {
        if (!isset($this->aiServices[$service])) {
            throw new Exception("Unknown AI service: $service");
        }

        $url = $this->aiServices[$service] . $endpoint;
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 30 second timeout
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: LCMTV-PHP/1.0'
        ]);

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("CURL error: $error");
        }

        if ($httpCode !== 200) {
            throw new Exception("HTTP $httpCode: $response");
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response: " . json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * Enrich AI results with full video data from database
     */
    private function enrichRecommendationsWithVideoData($recommendations) {
        if (!$recommendations) {
            return [];
        }

        $videoIds = array_column($recommendations, 'video_id');
        $placeholders = str_repeat('?,', count($videoIds) - 1) . '?';

        $conn = getDBConnection();
        $stmt = $conn->prepare("
            SELECT
                v.id,
                v.youtube_id,
                v.title,
                v.description,
                v.thumbnail_url,
                v.duration,
                v.view_count,
                v.like_count,
                v.channel_title,
                v.published_at,
                c.name as category_name
            FROM videos v
            LEFT JOIN categories c ON v.category_id = c.id
            WHERE v.id IN ($placeholders) AND v.is_active = 1
        ");

        if (!$stmt) {
            error_log("Failed to prepare video enrichment query: " . $conn->error);
            return $recommendations;
        }

        $stmt->bind_param(str_repeat('i', count($videoIds)), ...$videoIds);
        $stmt->execute();
        $result = $stmt->get_result();

        $videos = [];
        while ($row = $result->fetch_assoc()) {
            $videos[$row['id']] = $row;
        }

        $stmt->close();

        // Merge AI results with video data
        foreach ($recommendations as &$rec) {
            $videoId = $rec['video_id'];
            if (isset($videos[$videoId])) {
                $rec = array_merge($rec, $videos[$videoId]);
            }
        }

        return $recommendations;
    }

    /**
     * Track AI recommendation interactions for A/B testing
     */
    public function trackRecommendationInteraction($userId, $videoId, $action, $context = []) {
        try {
            $conn = getDBConnection();
            $stmt = $conn->prepare("
                INSERT INTO recommendation_experiments
                (user_id, experiment_name, variant_shown, recommendation_shown, user_action, confidence_score)
                VALUES (?, 'ai_recommendations', 'personalized', ?, ?, ?)
            ");

            $confidence = isset($context['score']) ? $context['score'] : 0.5;
            $recommendationData = json_encode([
                'video_id' => $videoId,
                'context' => $context,
                'timestamp' => date('c')
            ]);

            $stmt->bind_param("issd", $userId, $recommendationData, $action, $confidence);
            $stmt->execute();
            $stmt->close();

            return true;

        } catch (Exception $e) {
            error_log("Failed to track recommendation interaction: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get AI model performance metrics
     */
    public function getModelMetrics() {
        try {
            $conn = getDBConnection();
            $stmt = $conn->prepare("
                SELECT
                    model_name,
                    model_version,
                    metric_name,
                    metric_value,
                    dataset_size,
                    training_time_seconds,
                    evaluated_at
                FROM model_metrics
                ORDER BY evaluated_at DESC
                LIMIT 50
            ");

            $stmt->execute();
            $result = $stmt->get_result();

            $metrics = [];
            while ($row = $result->fetch_assoc()) {
                $metrics[] = $row;
            }

            $stmt->close();
            return $metrics;

        } catch (Exception $e) {
            error_log("Failed to get model metrics: " . $e->getMessage());
            return [];
        }
    }
}
?>
