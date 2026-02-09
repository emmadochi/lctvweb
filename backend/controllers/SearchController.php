<?php
/**
 * Search Controller
 * Handles enhanced search and AI recommendation endpoints
 */

require_once __DIR__ . '/../models/Search.php';
require_once __DIR__ . '/../utils/Auth.php';
require_once __DIR__ . '/../utils/Response.php';

class SearchController {
    /**
     * Enhanced search endpoint
     */
    public static function search() {
        try {
            $query = $_GET['q'] ?? '';
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

            // Parse filters
            $filters = self::parseSearchFilters();

            // Get current user if authenticated
            $userId = null;
            try {
                $user = Auth::getUserFromToken();
                $userId = $user ? $user['id'] : null;
            } catch (Exception $e) {
                // User not authenticated - that's okay for search
            }

            // Perform enhanced search
            $results = Search::enhancedSearch($query, $userId, $filters, $limit, $offset);

            return Response::success($results);

        } catch (Exception $e) {
            error_log("SearchController::search - Error: " . $e->getMessage());
            return Response::error('Search failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get search suggestions
     */
    public static function suggestions() {
        try {
            $query = $_GET['q'] ?? '';
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

            // Get current user if authenticated
            $userId = null;
            try {
                $user = Auth::getUserFromToken();
                $userId = $user ? $user['id'] : null;
            } catch (Exception $e) {
                // User not authenticated - that's okay for suggestions
            }

            $suggestions = Search::getSearchSuggestions($query, $userId, $limit);

            return Response::success([
                'suggestions' => $suggestions,
                'query' => $query
            ]);

        } catch (Exception $e) {
            error_log("SearchController::suggestions - Error: " . $e->getMessage());
            return Response::error('Failed to get suggestions: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get AI-powered recommendations
     */
    public static function recommendations() {
        try {
            // Require authentication for personalized recommendations
            Auth::requireAuth();
            $user = Auth::getUserFromToken();

            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $excludeWatched = isset($_GET['exclude_watched']) ? (bool)$_GET['exclude_watched'] : true;

            $recommendations = Search::getRecommendations($user['id'], $limit, $excludeWatched);

            return Response::success($recommendations);

        } catch (Exception $e) {
            error_log("SearchController::recommendations - Error: " . $e->getMessage());
            return Response::error('Failed to get recommendations: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get popular search terms
     */
    public static function popularSearches() {
        try {
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $days = isset($_GET['days']) ? (int)$_GET['days'] : 30;

            $searches = Search::getPopularSearches($limit, $days);

            return Response::success([
                'popular_searches' => $searches,
                'period_days' => $days
            ]);

        } catch (Exception $e) {
            error_log("SearchController::popularSearches - Error: " . $e->getMessage());
            return Response::error('Failed to get popular searches: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create smart playlist
     */
    public static function createSmartPlaylist() {
        try {
            Auth::requireAuth();
            $user = Auth::getUserFromToken();

            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return Response::error('Invalid JSON data', 400);
            }

            $playlistType = $data['playlist_type'] ?? '';
            $limit = $data['limit'] ?? 20;

            if (empty($playlistType)) {
                return Response::error('Playlist type is required', 400);
            }

            $videos = Search::createSmartPlaylist($user['id'], $playlistType, $limit);

            // Generate playlist title and description
            $playlistInfo = self::getPlaylistInfo($playlistType);

            return Response::success([
                'playlist' => [
                    'type' => $playlistType,
                    'title' => $playlistInfo['title'],
                    'description' => $playlistInfo['description'],
                    'videos' => $videos,
                    'video_count' => count($videos),
                    'generated_at' => date('c')
                ]
            ]);

        } catch (Exception $e) {
            error_log("SearchController::createSmartPlaylist - Error: " . $e->getMessage());
            return Response::error('Failed to create smart playlist: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get smart playlists for user
     */
    public static function getSmartPlaylists() {
        try {
            Auth::requireAuth();
            $user = Auth::getUserFromToken();

            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

            // For now, generate playlists on-demand
            // In production, you might want to cache these
            $playlists = [
                [
                    'type' => 'continue_watching',
                    'title' => 'Continue Watching',
                    'description' => 'Pick up where you left off',
                    'icon' => 'play-circle'
                ],
                [
                    'type' => 'trending',
                    'title' => 'Trending Now',
                    'description' => 'Popular content this week',
                    'icon' => 'fire'
                ],
                [
                    'type' => 'recently_watched',
                    'title' => 'Recently Watched',
                    'description' => 'Your recent viewing history',
                    'icon' => 'history'
                ],
                [
                    'type' => 'favorites_genre',
                    'title' => 'More Like Your Favorites',
                    'description' => 'Based on content you love',
                    'icon' => 'heart'
                ],
                [
                    'type' => 'similar_content',
                    'title' => 'Similar Content',
                    'description' => 'Based on your last watched video',
                    'icon' => 'shuffle'
                ]
            ];

            // Limit results
            $playlists = array_slice($playlists, 0, $limit);

            return Response::success([
                'playlists' => $playlists,
                'total' => count($playlists)
            ]);

        } catch (Exception $e) {
            error_log("SearchController::getSmartPlaylists - Error: " . $e->getMessage());
            return Response::error('Failed to get smart playlists: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Advanced search with multiple filters
     */
    public static function advancedSearch() {
        try {
            $query = $_GET['q'] ?? '';
            $filters = self::parseAdvancedFilters();

            // Get current user if authenticated
            $userId = null;
            try {
                $user = Auth::getUserFromToken();
                $userId = $user ? $user['id'] : null;
            } catch (Exception $e) {
                // User not authenticated - that's okay for search
            }

            $results = Search::enhancedSearch($query, $userId, $filters, 50, 0);

            // Add filter metadata
            $results['applied_filters'] = $filters;
            $results['available_filters'] = self::getAvailableFilters();

            return Response::success($results);

        } catch (Exception $e) {
            error_log("SearchController::advancedSearch - Error: " . $e->getMessage());
            return Response::error('Advanced search failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Parse search filters from GET parameters
     */
    private static function parseSearchFilters() {
        $filters = [];

        // Basic filters
        if (!empty($_GET['category_id'])) {
            $filters['category_id'] = (int)$_GET['category_id'];
        }

        if (!empty($_GET['speaker'])) {
            $filters['speaker'] = trim($_GET['speaker']);
        }

        if (!empty($_GET['date_from'])) {
            $filters['date_from'] = $_GET['date_from'];
        }

        if (!empty($_GET['date_to'])) {
            $filters['date_to'] = $_GET['date_to'];
        }

        if (!empty($_GET['duration_min'])) {
            $filters['duration_min'] = (int)$_GET['duration_min'];
        }

        if (!empty($_GET['duration_max'])) {
            $filters['duration_max'] = (int)$_GET['duration_max'];
        }

        if (!empty($_GET['content_type'])) {
            $filters['content_type'] = $_GET['content_type'];
        }

        if (!empty($_GET['language'])) {
            $filters['language'] = $_GET['language'];
        }

        // Parse tags array
        if (!empty($_GET['tags']) && is_array($_GET['tags'])) {
            $filters['tags'] = array_map('trim', $_GET['tags']);
        }

        return $filters;
    }

    /**
     * Parse advanced search filters
     */
    private static function parseAdvancedFilters() {
        $filters = self::parseSearchFilters();

        // Add advanced filters
        if (!empty($_GET['quality'])) {
            $filters['quality'] = $_GET['quality'];
        }

        if (!empty($_GET['sort_by'])) {
            $filters['sort_by'] = $_GET['sort_by'];
        }

        if (!empty($_GET['sort_order'])) {
            $filters['sort_order'] = $_GET['sort_order'];
        }

        return $filters;
    }

    /**
     * Get available filter options
     */
    private static function getAvailableFilters() {
        $conn = getDBConnection();

        $filters = [];

        // Categories
        $categories = [];
        $catResult = $conn->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name");
        while ($row = $catResult->fetch_assoc()) {
            $categories[] = $row;
        }
        $filters['categories'] = $categories;

        // Speakers
        $speakers = [];
        $speakerResult = $conn->query("SELECT DISTINCT speaker FROM videos WHERE speaker IS NOT NULL AND speaker != '' ORDER BY speaker");
        while ($row = $speakerResult->fetch_assoc()) {
            $speakers[] = $row['speaker'];
        }
        $filters['speakers'] = $speakers;

        // Languages
        $languages = [];
        $langResult = $conn->query("SELECT DISTINCT language FROM videos WHERE language IS NOT NULL ORDER BY language");
        while ($row = $langResult->fetch_assoc()) {
            $languages[] = $row['language'];
        }
        $filters['languages'] = $languages;

        // Content types
        $filters['content_types'] = [
            ['id' => 'video', 'name' => 'Videos'],
            ['id' => 'livestream', 'name' => 'Live Streams']
        ];

        // Duration ranges
        $filters['duration_ranges'] = [
            ['id' => 'short', 'name' => 'Under 5 minutes', 'min' => 0, 'max' => 300],
            ['id' => 'medium', 'name' => '5-30 minutes', 'min' => 300, 'max' => 1800],
            ['id' => 'long', 'name' => '30+ minutes', 'min' => 1800, 'max' => null]
        ];

        return $filters;
    }

    /**
     * Get playlist info by type
     */
    private static function getPlaylistInfo($type) {
        $playlists = [
            'continue_watching' => [
                'title' => 'Continue Watching',
                'description' => 'Pick up where you left off'
            ],
            'favorites_genre' => [
                'title' => 'More Like Your Favorites',
                'description' => 'Based on content you love'
            ],
            'recently_watched' => [
                'title' => 'Recently Watched',
                'description' => 'Your recent viewing history'
            ],
            'trending' => [
                'title' => 'Trending Now',
                'description' => 'Popular content this week'
            ],
            'similar_content' => [
                'title' => 'Similar Content',
                'description' => 'Based on your last watched video'
            ]
        ];

        return $playlists[$type] ?? [
            'title' => 'Smart Playlist',
            'description' => 'AI-generated playlist'
        ];
    }
}
?>