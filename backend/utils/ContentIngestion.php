<?php
/**
 * Content Ingestion Service
 * Imports YouTube content into the database
 */

require_once 'YouTubeAPI.php';
require_once __DIR__ . '/../models/Video.php';
require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../models/Notification.php';

class ContentIngestion {
    private $youtubeAPI;

    public function __construct() {
        $this->youtubeAPI = new YouTubeAPI();
    }

    /**
     * Import a single video by YouTube URL
     */
    public function importByUrl($url, $categoryId) {
        try {
            // Extract video ID from URL
            $videoId = $this->extractVideoIdFromUrl($url);

            if (!$videoId) {
                throw new Exception('Invalid YouTube URL or unable to extract video ID');
            }

            echo "Importing video from URL: $url\n";
            echo "Extracted video ID: $videoId\n";

            // Get video details from YouTube API
            $videos = $this->youtubeAPI->getVideoDetails([$videoId]);

            if (empty($videos)) {
                throw new Exception('Video not found or unavailable');
            }

            $videoData = $videos[0];

            // Import the video
            if ($this->importVideo($videoData, $categoryId)) {
                echo "Successfully imported video: {$videoData['title']}\n";
                return 1;
            } else {
                echo "Failed to import video: {$videoData['title']}\n";
                return 0;
            }

        } catch (Exception $e) {
            echo "Error importing video from URL '$url': " . $e->getMessage() . "\n";
            return 0;
        }
    }

    /**
     * Extract video ID from various YouTube URL formats
     */
    private function extractVideoIdFromUrl($url) {
        // Remove any query parameters and fragments
        $url = parse_url($url, PHP_URL_PATH) . '?' . parse_url($url, PHP_URL_QUERY);

        // Common YouTube URL patterns
        $patterns = [
            // youtube.com/watch?v=VIDEO_ID
            '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/',
            // youtu.be/VIDEO_ID
            '/youtu\.be\/([a-zA-Z0-9_-]{11})/',
            // youtube.com/embed/VIDEO_ID
            '/youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/',
            // youtube.com/v/VIDEO_ID
            '/youtube\.com\/v\/([a-zA-Z0-9_-]{11})/',
            // youtube.com/shorts/VIDEO_ID
            '/youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }

        // If no pattern matches, try to extract from query parameters
        parse_str(parse_url($url, PHP_URL_QUERY), $queryParams);
        if (isset($queryParams['v']) && strlen($queryParams['v']) === 11) {
            return $queryParams['v'];
        }

        return null;
    }

    /**
     * Import videos by keyword search
     */
    public function importByKeyword($keyword, $categoryId, $maxResults = 20) {
        try {
            echo "Searching YouTube for: '$keyword'\n";

            $videos = $this->youtubeAPI->searchVideos($keyword, $maxResults);

            if (empty($videos)) {
                echo "No videos found for keyword: $keyword\n";
                return 0;
            }

            // Get detailed information for all videos
            $videoIds = array_column($videos, 'youtube_id');
            $detailedVideos = $this->youtubeAPI->getVideoDetails($videoIds);

            // Merge search results with detailed info
            $videos = $this->mergeVideoDetails($videos, $detailedVideos);

            $imported = 0;
            foreach ($videos as $videoData) {
                if ($this->importVideo($videoData, $categoryId)) {
                    $imported++;
                }
            }

            echo "Imported $imported videos from keyword search\n";
            return $imported;

        } catch (Exception $e) {
            echo "Error importing by keyword '$keyword': " . $e->getMessage() . "\n";
            return 0;
        }
    }

    /**
     * Import videos from YouTube playlist
     */
    public function importFromPlaylist($playlistId, $categoryId, $maxResults = 50) {
        try {
            echo "Importing playlist: $playlistId\n";

            $videos = $this->youtubeAPI->getPlaylistVideos($playlistId, $maxResults);

            if (empty($videos)) {
                echo "No videos found in playlist: $playlistId\n";
                return 0;
            }

            // Get detailed information
            $videoIds = array_column($videos, 'youtube_id');
            $detailedVideos = $this->youtubeAPI->getVideoDetails($videoIds);
            $videos = $this->mergeVideoDetails($videos, $detailedVideos);

            $imported = 0;
            foreach ($videos as $videoData) {
                if ($this->importVideo($videoData, $categoryId)) {
                    $imported++;
                }
            }

            echo "Imported $imported videos from playlist\n";
            return $imported;

        } catch (Exception $e) {
            echo "Error importing playlist '$playlistId': " . $e->getMessage() . "\n";
            return 0;
        }
    }

    /**
     * Import videos from YouTube channel
     */
    public function importFromChannel($channelId, $categoryId, $maxResults = 20) {
        try {
            echo "Importing channel: $channelId\n";

            $videos = $this->youtubeAPI->getChannelVideos($channelId, $maxResults);

            if (empty($videos)) {
                echo "No videos found in channel: $channelId\n";
                return 0;
            }

            // Get detailed information
            $videoIds = array_column($videos, 'youtube_id');
            $detailedVideos = $this->youtubeAPI->getVideoDetails($videoIds);
            $videos = $this->mergeVideoDetails($videos, $detailedVideos);

            $imported = 0;
            foreach ($videos as $videoData) {
                if ($this->importVideo($videoData, $categoryId)) {
                    $imported++;
                }
            }

            echo "Imported $imported videos from channel\n";
            return $imported;

        } catch (Exception $e) {
            echo "Error importing channel '$channelId': " . $e->getMessage() . "\n";
            return 0;
        }
    }

    /**
     * Import trending videos
     */
    public function importTrending($categoryId, $maxResults = 10, $regionCode = 'US') {
        try {
            echo "Importing trending videos from $regionCode\n";

            $videos = $this->youtubeAPI->getTrendingVideos($regionCode, $maxResults);

            if (empty($videos)) {
                echo "No trending videos found\n";
                return 0;
            }

            $imported = 0;
            foreach ($videos as $videoData) {
                if ($this->importVideo($videoData, $categoryId)) {
                    $imported++;
                }
            }

            echo "Imported $imported trending videos\n";
            return $imported;

        } catch (Exception $e) {
            echo "Error importing trending videos: " . $e->getMessage() . "\n";
            return 0;
        }
    }

    /**
     * Import a single video
     */
    private function importVideo($videoData, $categoryId) {
        try {
            // Check if video already exists
            $existingVideo = Video::getByYouTubeId($videoData['youtube_id']);
            if ($existingVideo) {
                echo "Video already exists: {$videoData['title']}\n";
                return false;
            }

            // Validate category exists
            $category = Category::getById($categoryId);
            if (!$category) {
                echo "Invalid category ID: $categoryId\n";
                return false;
            }

            // Prepare video data for database
            $videoInsertData = [
                'youtube_id' => $videoData['youtube_id'],
                'title' => $this->sanitizeString($videoData['title']),
                'description' => $this->sanitizeString($videoData['description']),
                'thumbnail_url' => $videoData['thumbnail_url'],
                'duration' => $videoData['duration'],
                'category_id' => $categoryId,
                'tags' => json_encode($videoData['tags'] ?: []),
                'channel_title' => $this->sanitizeString($videoData['channel_title']),
                'channel_id' => $videoData['channel_id'],
                'view_count' => $videoData['view_count'],
                'like_count' => $videoData['like_count'],
                'published_at' => date('Y-m-d H:i:s', strtotime($videoData['published_at']))
            ];

            $result = Video::create($videoInsertData);

            if ($result) {
                echo "✓ Imported: {$videoData['title']}\n";

                // Create notifications for users subscribed to this category
                try {
                    Notification::notifyNewVideoInCategory($result, $categoryId);
                } catch (Exception $e) {
                    echo "Warning: Failed to create notifications for new video: " . $e->getMessage() . "\n";
                    // Don't fail the import if notifications fail
                }

                return true;
            } else {
                echo "✗ Failed to import: {$videoData['title']}\n";
                return false;
            }

        } catch (Exception $e) {
            echo "✗ Error importing video: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Merge search results with detailed video information
     */
    private function mergeVideoDetails($searchResults, $detailedResults) {
        $detailedById = [];
        foreach ($detailedResults as $video) {
            $detailedById[$video['youtube_id']] = $video;
        }

        foreach ($searchResults as &$video) {
            $youtubeId = $video['youtube_id'];
            if (isset($detailedById[$youtubeId])) {
                $video = array_merge($video, $detailedById[$youtubeId]);
            }
        }

        return $searchResults;
    }

    /**
     * Sanitize string for database insertion
     */
    private function sanitizeString($string, $maxLength = 500) {
        // Remove null bytes and sanitize
        $string = str_replace("\x00", '', $string);
        $string = trim($string);

        // Truncate if too long
        if (strlen($string) > $maxLength) {
            $string = substr($string, 0, $maxLength - 3) . '...';
        }

        return $string;
    }

    /**
     * Bulk import configuration for initial setup
     */
    public function runInitialImport() {
        echo "Starting initial content import...\n\n";

        $imports = [
            // News category
            ['type' => 'keyword', 'value' => 'breaking news today', 'category' => 1, 'limit' => 15],
            ['type' => 'keyword', 'value' => 'current events', 'category' => 1, 'limit' => 10],

            // Sports category
            ['type' => 'keyword', 'value' => 'sports highlights', 'category' => 2, 'limit' => 15],
            ['type' => 'keyword', 'value' => 'football soccer', 'category' => 2, 'limit' => 10],

            // Music category
            ['type' => 'keyword', 'value' => 'music videos', 'category' => 3, 'limit' => 20],

            // Tech category
            ['type' => 'keyword', 'value' => 'technology news', 'category' => 6, 'limit' => 15],
            ['type' => 'keyword', 'value' => 'gadgets review', 'category' => 6, 'limit' => 10],

            // Movies category
            ['type' => 'keyword', 'value' => 'movie trailers', 'category' => 7, 'limit' => 15],
        ];

        $totalImported = 0;

        foreach ($imports as $import) {
            switch ($import['type']) {
                case 'keyword':
                    $count = $this->importByKeyword($import['value'], $import['category'], $import['limit']);
                    break;
                case 'playlist':
                    $count = $this->importFromPlaylist($import['value'], $import['category'], $import['limit']);
                    break;
                case 'channel':
                    $count = $this->importFromChannel($import['value'], $import['category'], $import['limit']);
                    break;
                case 'trending':
                    $count = $this->importTrending($import['category'], $import['limit']);
                    break;
                default:
                    $count = 0;
            }

            $totalImported += $count;
            echo "\n";
        }

        echo "Initial import complete! Total videos imported: $totalImported\n";
        return $totalImported;
    }

    /**
     * Import a livestream by YouTube URL
     */
    public function importLivestreamByUrl($url, $categoryId) {
        try {
            // Extract video ID from URL
            $videoId = $this->extractVideoIdFromUrl($url);

            if (!$videoId) {
                throw new Exception('Invalid YouTube URL or unable to extract video ID');
            }

            echo "Importing livestream from URL: $url\n";
            echo "Extracted video ID: $videoId\n";

            // Get video details from YouTube API
            $videos = $this->youtubeAPI->getVideoDetails([$videoId]);

            if (empty($videos)) {
                throw new Exception('Livestream not found or unavailable');
            }

            $videoData = $videos[0];

            // Check if this is actually a live stream
            // YouTube API returns liveBroadcastContent in video details
            if (!isset($videoData['liveBroadcastContent']) || $videoData['liveBroadcastContent'] !== 'live') {
                echo "Warning: This video is not currently live. Importing as regular video instead.\n";
                // Import as regular video instead
                return $this->importVideo($videoData, $categoryId) ? 1 : 0;
            }

            // Import the livestream
            if ($this->importLivestream($videoData, $categoryId)) {
                echo "Successfully imported livestream: {$videoData['title']}\n";
                return 1;
            } else {
                echo "Failed to import livestream: {$videoData['title']}\n";
                return 0;
            }

        } catch (Exception $e) {
            echo "Error importing livestream by URL: " . $e->getMessage() . "\n";
            return 0;
        }
    }

    /**
     * Import live streams from a YouTube channel
     */
    public function importLiveFromChannel($channelId, $categoryId, $maxResults = 5) {
        try {
            echo "Searching for live streams on channel: $channelId\n";

            // Get channel's live streams using search API with live broadcast type
            $params = [
                'part' => 'snippet',
                'channelId' => $channelId,
                'eventType' => 'live',
                'type' => 'video',
                'order' => 'date',
                'maxResults' => min($maxResults, 50)
            ];

            $searchData = $this->youtubeAPI->makeRequest('search', $params);

            if (empty($searchData['items'])) {
                echo "No live streams found on this channel.\n";
                return 0;
            }

            $imported = 0;
            foreach ($searchData['items'] as $item) {
                if ($item['id']['kind'] !== 'youtube#video') continue;

                $videoId = $item['id']['videoId'];

                // Get detailed video info to confirm it's live
                $videos = $this->youtubeAPI->getVideoDetails([$videoId]);

                if (!empty($videos)) {
                    $videoData = $videos[0];

                    // Check if it's actually live
                    if (isset($videoData['liveBroadcastContent']) && $videoData['liveBroadcastContent'] === 'live') {
                        if ($this->importLivestream($videoData, $categoryId)) {
                            echo "Imported live stream: {$videoData['title']}\n";
                            $imported++;
                        }
                    }
                }
            }

            echo "Imported $imported live streams from channel.\n";
            return $imported;

        } catch (Exception $e) {
            echo "Error importing live streams from channel: " . $e->getMessage() . "\n";
            return 0;
        }
    }

    /**
     * Import a livestream to the database
     */
    private function importLivestream($videoData, $categoryId) {
        try {
            // Check if livestream already exists
            $existingLivestream = Livestream::findByYoutubeId($videoData['youtube_id']);

            if ($existingLivestream) {
                echo "Livestream already exists: {$videoData['title']}\n";
                return false;
            }

            // Prepare livestream data
            $livestreamData = [
                'youtube_id' => $videoData['youtube_id'],
                'title' => $videoData['title'],
                'description' => $videoData['description'],
                'thumbnail_url' => $videoData['thumbnail_url'],
                'channel_title' => $videoData['channel_title'],
                'channel_id' => $videoData['channel_id'],
                'is_live' => true,
                'viewer_count' => $videoData['view_count'] ?? null,
                'started_at' => date('Y-m-d H:i:s'), // Current time as start time
                'category_id' => $categoryId
            ];

            // Create the livestream
            $livestreamId = Livestream::create($livestreamData);

            if ($livestreamId) {
                echo "Created livestream with ID: $livestreamId\n";
                return true;
            } else {
                echo "Failed to create livestream in database\n";
                return false;
            }

        } catch (Exception $e) {
            echo "Error importing livestream: " . $e->getMessage() . "\n";
            return false;
        }
    }
}
?>
