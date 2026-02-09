<?php
/**
 * YouTube Data API v3 Integration
 * Handles video search, retrieval, and metadata
 */

require_once __DIR__ . '/../utils/EnvLoader.php';

class YouTubeAPI {
    private $apiKey;
    private $baseUrl = 'https://www.googleapis.com/youtube/v3/';

    public function __construct() {
        $this->loadApiKey();
    }
    
    private function loadApiKey() {
        // Try to load from environment
        $this->apiKey = getenv('YOUTUBE_API_KEY');
        
        if (!$this->apiKey) {
            // Fallback to default key
            $this->apiKey = 'AIzaSyDT1-_m5WdXEAPDd8J-WuPAGrmKgdMeqeY';
            error_log("YouTube API key not configured. Set YOUTUBE_API_KEY environment variable.");
        }
    }
    
    public function getApiKey() {
        // Refresh API key in case it was set after instantiation
        $currentApiKey = getenv('YOUTUBE_API_KEY');
        if ($currentApiKey && $currentApiKey !== $this->apiKey) {
            $this->apiKey = $currentApiKey;
        }
        return $this->apiKey;
    }

    /**
     * Make API request to YouTube
     */
    private function makeRequest($endpoint, $params = []) {
        $params['key'] = $this->getApiKey();
        $url = $this->baseUrl . $endpoint . '?' . http_build_query($params);

        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'LCMTV/1.0'
            ]
        ]);

        $response = file_get_contents($url, false, $context);

        if ($response === false) {
            throw new Exception("YouTube API request failed");
        }

        $data = json_decode($response, true);

        if (isset($data['error'])) {
            throw new Exception("YouTube API error: " . $data['error']['message']);
        }

        return $data;
    }

    /**
     * Search for videos by keyword
     */
    public function searchVideos($query, $maxResults = 10, $order = 'relevance') {
        try {
            $params = [
                'part' => 'snippet',
                'q' => $query,
                'type' => 'video',
                'maxResults' => min($maxResults, 50),
                'order' => $order,
                'safeSearch' => 'moderate',
                'videoEmbeddable' => 'true'
            ];

            $data = $this->makeRequest('search', $params);

            return $this->formatSearchResults($data['items'] ?? []);

        } catch (Exception $e) {
            error_log("YouTube search error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get videos from a playlist
     */
    public function getPlaylistVideos($playlistId, $maxResults = 50) {
        try {
            $params = [
                'part' => 'snippet',
                'playlistId' => $playlistId,
                'maxResults' => min($maxResults, 50)
            ];

            $data = $this->makeRequest('playlistItems', $params);

            return $this->formatPlaylistResults($data['items'] ?? []);

        } catch (Exception $e) {
            error_log("YouTube playlist error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get videos from a channel
     */
    public function getChannelVideos($channelId, $maxResults = 10, $order = 'date') {
        try {
            // First get channel uploads playlist ID
            $channelParams = [
                'part' => 'contentDetails',
                'id' => $channelId
            ];

            $channelData = $this->makeRequest('channels', $channelParams);

            if (empty($channelData['items'])) {
                return [];
            }

            $uploadsPlaylistId = $channelData['items'][0]['contentDetails']['relatedPlaylists']['uploads'];

            // Then get videos from uploads playlist
            return $this->getPlaylistVideos($uploadsPlaylistId, $maxResults);

        } catch (Exception $e) {
            error_log("YouTube channel error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get detailed video information
     */
    public function getVideoDetails($videoIds) {
        try {
            if (is_string($videoIds)) {
                $videoIds = [$videoIds];
            }

            $params = [
                'part' => 'snippet,statistics,contentDetails',
                'id' => implode(',', array_slice($videoIds, 0, 50)) // Max 50 per request
            ];

            $data = $this->makeRequest('videos', $params);

            return $this->formatVideoDetails($data['items'] ?? []);

        } catch (Exception $e) {
            error_log("YouTube video details error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Format search results
     */
    private function formatSearchResults($items) {
        $videos = [];

        foreach ($items as $item) {
            if ($item['id']['kind'] !== 'youtube#video') continue;

            $videos[] = [
                'youtube_id' => $item['id']['videoId'],
                'title' => $item['snippet']['title'],
                'description' => $item['snippet']['description'],
                'thumbnail_url' => $item['snippet']['thumbnails']['medium']['url'] ?? $item['snippet']['thumbnails']['default']['url'],
                'channel_title' => $item['snippet']['channelTitle'],
                'channel_id' => $item['snippet']['channelId'],
                'published_at' => $item['snippet']['publishedAt'],
                'tags' => [], // Will be populated from video details
                'view_count' => 0, // Will be populated from video details
                'like_count' => 0, // Will be populated from video details
                'duration' => '' // Will be populated from video details
            ];
        }

        return $videos;
    }

    /**
     * Format playlist results
     */
    private function formatPlaylistResults($items) {
        $videos = [];

        foreach ($items as $item) {
            $videos[] = [
                'youtube_id' => $item['snippet']['resourceId']['videoId'],
                'title' => $item['snippet']['title'],
                'description' => $item['snippet']['description'],
                'thumbnail_url' => $item['snippet']['thumbnails']['medium']['url'] ?? $item['snippet']['thumbnails']['default']['url'],
                'channel_title' => $item['snippet']['channelTitle'],
                'channel_id' => $item['snippet']['channelId'],
                'published_at' => $item['snippet']['publishedAt'],
                'tags' => [],
                'view_count' => 0,
                'like_count' => 0,
                'duration' => ''
            ];
        }

        return $videos;
    }

    /**
     * Format detailed video information
     */
    private function formatVideoDetails($items) {
        $videos = [];

        foreach ($items as $item) {
            // Parse ISO 8601 duration (PT4M13S = 4 minutes 13 seconds)
            $duration = $this->parseDuration($item['contentDetails']['duration'] ?? 'PT0S');

            $videos[] = [
                'youtube_id' => $item['id'],
                'title' => $item['snippet']['title'],
                'description' => $item['snippet']['description'],
                'thumbnail_url' => $item['snippet']['thumbnails']['medium']['url'] ?? $item['snippet']['thumbnails']['default']['url'],
                'channel_title' => $item['snippet']['channelTitle'],
                'channel_id' => $item['snippet']['channelId'],
                'published_at' => $item['snippet']['publishedAt'],
                'tags' => $item['snippet']['tags'] ?? [],
                'view_count' => (int)($item['statistics']['viewCount'] ?? 0),
                'like_count' => (int)($item['statistics']['likeCount'] ?? 0),
                'duration' => $duration
            ];
        }

        return $videos;
    }

    /**
     * Parse ISO 8601 duration format
     */
    private function parseDuration($duration) {
        $pattern = '/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/';
        preg_match($pattern, $duration, $matches);

        $hours = (int)($matches[1] ?? 0);
        $minutes = (int)($matches[2] ?? 0);
        $seconds = (int)($matches[3] ?? 0);

        return $hours * 3600 + $minutes * 60 + $seconds;
    }

    /**
     * Get trending videos
     */
    public function getTrendingVideos($regionCode = 'US', $maxResults = 10) {
        try {
            $params = [
                'part' => 'snippet,statistics',
                'chart' => 'mostPopular',
                'regionCode' => $regionCode,
                'maxResults' => min($maxResults, 50),
                'videoCategoryId' => '' // All categories
            ];

            $data = $this->makeRequest('videos', $params);

            return $this->formatVideoDetails($data['items'] ?? []);

        } catch (Exception $e) {
            error_log("YouTube trending error: " . $e->getMessage());
            return [];
        }
    }
}
?>
