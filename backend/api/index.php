<?php
/**
 * LCMTV API Entry Point
 * Main API router and initialization
 */

// Set error handling BEFORE any output
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Set headers immediately to ensure JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include configuration
require_once __DIR__ . '/../utils/EnvLoader.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Auth.php';
require_once __DIR__ . '/../controllers/AnalyticsController.php'; // Added for direct access to static methods
require_once __DIR__ . '/../controllers/SearchController.php'; // Added for search functionality
require_once __DIR__ . '/../controllers/DonationController.php'; // Added for donation processing
require_once __DIR__ . '/../controllers/LanguageController.php'; // Added for language management
require_once __DIR__ . '/../controllers/AdminController.php'; // Added for admin functions

// Enable error logging but don't display errors (they break JSON responses)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
enable_php_error_log('api_errors.log'); // Custom function to log PHP errors
error_reporting(E_ALL);

// Basic routing
$request = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Remove query parameters and get path
$path = parse_url($request, PHP_URL_PATH);
$originalPath = $path;
// Normalize path so it works regardless of project folder name.
// Supports URLs like:
// - /lcmtvweb/backend/api/...
// - /LCMTVWebNew/backend/api/...
// - /backend/api/...
// - /api/... (when proxied)
$backendApiSegmentPos = strpos($path, '/backend/api');
if ($backendApiSegmentPos !== false) {
    $path = substr($path, $backendApiSegmentPos + strlen('/backend/api'));
} else {
    // Fallback: if called through a proxy that maps /api -> backend/api
    $apiSegmentPos = strpos($path, '/api');
    if ($apiSegmentPos !== false) {
        $path = substr($path, $apiSegmentPos + strlen('/api'));
    }
}

// Debug logging for push routes
if (strpos($originalPath, 'push') !== false || strpos($path, 'push') !== false) {
    error_log("Push route detected - Original: $originalPath, Parsed: $path, Method: $method");
}

// Route to appropriate controller
try {
    switch ($path) {
        case '/':
        case '':
            require_once '../controllers/HomeController.php';
            $controller = new HomeController();
            $controller->index();
            break;

        case (preg_match('/^\/videos/', $path) ? true : false):
            require_once '../controllers/VideoController.php';
            $controller = new VideoController();
            handleVideoRoutes($controller, $method, $path);
            break;

        case '/categories':
            require_once '../controllers/CategoryController.php';
            $controller = new CategoryController();
            handleCategoryRoutes($controller, $method, $path);
            break;

        // Category videos by slug: /categories/{slug}/videos
        case (preg_match('/^\/categories\/([^\/]+)\/videos$/', $path, $matches) ? true : false):
            require_once '../models/Category.php';
            require_once '../models/Video.php';
            $slug = $matches[1];
            $category = Category::getBySlug($slug);
            if (!$category) {
                Response::notFound('Category not found');
            }

            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $limit = max(1, min(100, $limit));

            // Simple paging (fetch up to 100 and slice). For large datasets, implement SQL LIMIT/OFFSET.
            $videos = Video::getByCategory((int)$category['id'], 100);
            $total = count($videos);
            $offset = ($page - 1) * $limit;
            $paged = array_slice($videos, $offset, $limit);
            $totalPages = $limit > 0 ? (int)ceil($total / $limit) : 1;

            Response::success([
                'videos' => $paged,
                'total' => $total,
                'page' => $page,
                'total_pages' => $totalPages,
                'category' => [
                    'id' => (int)$category['id'],
                    'name' => $category['name'],
                    'slug' => $category['slug']
                ]
            ]);
            break;

        // Category stats: /categories/{slug}/stats
        case (preg_match('/^\/categories\/([^\/]+)\/stats$/', $path, $matches) ? true : false):
            require_once '../models/Category.php';
            $slug = $matches[1];
            $category = Category::getBySlug($slug);
            if (!$category) {
                Response::notFound('Category not found');
            }
            $conn = getDBConnection();
            $stmt = $conn->prepare("SELECT COUNT(*) as video_count, COALESCE(SUM(view_count),0) as total_views, MAX(updated_at) as last_updated
                                    FROM videos
                                    WHERE is_active = 1 AND category_id = ?");
            $categoryId = (int)$category['id'];
            $stmt->bind_param("i", $categoryId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            Response::success([
                'video_count' => (int)($row['video_count'] ?? 0),
                'total_views' => (int)($row['total_views'] ?? 0),
                'last_updated' => $row['last_updated'] ?? null
            ]);
            break;

        // Search endpoint: /search?q=...
        case '/search':
            require_once '../models/Video.php';
            $query = isset($_GET['q']) ? trim($_GET['q']) : '';
            if (strlen($query) < 2) {
                Response::badRequest('Search query must be at least 2 characters');
            }
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 24;
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $limit = max(1, min(100, $limit));

            $videos = Video::search($query, 100);
            $total = count($videos);
            $offset = ($page - 1) * $limit;
            $paged = array_slice($videos, $offset, $limit);
            $totalPages = $limit > 0 ? (int)ceil($total / $limit) : 1;

            Response::success([
                'videos' => $paged,
                'total' => $total,
                'page' => $page,
                'total_pages' => $totalPages,
                'query' => $query
            ]);
            break;

        case '/users':
            require_once '../controllers/UserController.php';
            $controller = new UserController();
            handleUserRoutes($controller, $method);
            break;

        case '/livestreams':
            require_once '../controllers/LivestreamController.php';
            $controller = new LivestreamController();
            handleLivestreamRoutes($controller, $method);
            break;

        case '/comments':
            require_once '../controllers/CommentController.php';
            $controller = new CommentController();
            handleCommentRoutes($controller, $method, $path);
            break;

        case '/reactions':
            require_once '../controllers/ReactionController.php';
            $controller = new ReactionController();
            handleReactionRoutes($controller, $method, $path);
            break;

        case (preg_match('/^\/users\/profile$/', $path) ? true : false):
            require_once '../controllers/UserController.php';
            $controller = new UserController();
            if ($method === 'GET') {
                $controller->profile();
            } elseif ($method === 'PUT') {
                $controller->updateProfile();
            } else {
                Response::methodNotAllowed();
            }
            break;

        case (preg_match('/^\/users\/password$/', $path) ? true : false):
            require_once '../controllers/UserController.php';
            $controller = new UserController();
            if ($method === 'PUT') {
                $controller->updatePassword();
            } else {
                Response::methodNotAllowed();
            }
            break;

        case (preg_match('/^\/users\/history\/(\d+)$/', $path, $matches) ? true : false):
            require_once '../controllers/UserController.php';
            $controller = new UserController();
            if ($method === 'DELETE') {
                $controller->removeHistoryItem($matches[1]);
            } else {
                Response::methodNotAllowed();
            }
            break;

        case (preg_match('/^\/users\/history$/', $path) ? true : false):
            require_once '../controllers/UserController.php';
            $controller = new UserController();
            if ($method === 'GET') {
                $controller->history();
            } elseif ($method === 'POST') {
                $controller->addHistory();
            } elseif ($method === 'DELETE') {
                $controller->clearHistory();
            } else {
                Response::methodNotAllowed();
            }
            break;

        case (preg_match('/^\/users\/favorites$/', $path) ? true : false):
            require_once '../controllers/UserController.php';
            $controller = new UserController();
            if ($method === 'GET') {
                $controller->getFavorites();
            } elseif ($method === 'POST') {
                $controller->addToFavorites();
            } elseif ($method === 'DELETE') {
                $controller->removeFromFavorites();
            }
            break;

        case (preg_match('/^\/users\/channels$/', $path) ? true : false):
            require_once '../controllers/UserController.php';
            $controller = new UserController();
            if ($method === 'GET') {
                $controller->getMyChannels();
            } elseif ($method === 'POST') {
                $controller->addToMyChannels();
            } elseif ($method === 'DELETE') {
                $controller->removeFromMyChannels();
            }
            break;

        case (preg_match('/^\/livestreams\/featured$/', $path) ? true : false):
            require_once '../controllers/LivestreamController.php';
            $controller = new LivestreamController();
            if ($method === 'GET') {
                $controller->featured();
            } else {
                Response::methodNotAllowed();
            }
            break;

        case '/analytics/page-view':
            require_once '../controllers/AnalyticsController.php';
            if ($method === 'POST') {
                AnalyticsController::trackPageView();
            } else {
                Response::methodNotAllowed();
            }
            break;

        case '/analytics/video-view':
            require_once '../controllers/AnalyticsController.php';
            if ($method === 'POST') {
                AnalyticsController::trackVideoView();
            } else {
                Response::methodNotAllowed();
            }
            break;

        case '/analytics/dashboard':
            require_once '../controllers/AnalyticsController.php';
            if ($method === 'GET') {
                AnalyticsController::getDashboard();
            } else {
                Response::methodNotAllowed();
            }
            break;

        case '/analytics/engagement':
            require_once '../controllers/AnalyticsController.php';
            if ($method === 'POST') {
                AnalyticsController::trackEngagement();
            } else {
                Response::methodNotAllowed();
            }
            break;

        // Search endpoints
        case '/search':
            require_once '../controllers/SearchController.php';
            if ($method === 'GET') {
                SearchController::search();
            } else {
                Response::methodNotAllowed();
            }
            break;

        case '/search/suggestions':
            require_once '../controllers/SearchController.php';
            if ($method === 'GET') {
                SearchController::suggestions();
            } else {
                Response::methodNotAllowed();
            }
            break;

        case '/search/advanced':
            require_once '../controllers/SearchController.php';
            if ($method === 'GET') {
                SearchController::advancedSearch();
            } else {
                Response::methodNotAllowed();
            }
            break;

        case '/search/popular':
            require_once '../controllers/SearchController.php';
            if ($method === 'GET') {
                SearchController::popularSearches();
            } else {
                Response::methodNotAllowed();
            }
            break;

        case '/recommendations':
            require_once '../controllers/SearchController.php';
            if ($method === 'GET') {
                SearchController::recommendations();
            } else {
                Response::methodNotAllowed();
            }
            break;

        case '/playlists/smart':
            require_once '../controllers/SearchController.php';
            if ($method === 'GET') {
                SearchController::getSmartPlaylists();
            } elseif ($method === 'POST') {
                SearchController::createSmartPlaylist();
            } else {
                Response::methodNotAllowed();
            }
            break;

        // Donation endpoints
        case '/donations':
            require_once '../controllers/DonationController.php';
            if ($method === 'POST') {
                DonationController::processDonation();
            } elseif ($method === 'GET') {
                DonationController::getUserDonations();
            } else {
                Response::methodNotAllowed();
            }
            break;

        case '/donations/campaigns':
            require_once '../controllers/DonationController.php';
            if ($method === 'GET') {
                DonationController::getCampaigns();
            } elseif ($method === 'POST') {
                DonationController::createCampaign();
            } else {
                Response::methodNotAllowed();
            }
            break;

        case '/donations/stats':
            require_once '../controllers/DonationController.php';
            if ($method === 'GET') {
                DonationController::getStats();
            } else {
                Response::methodNotAllowed();
            }
            break;

        case '/donations/receipt':
            require_once '../controllers/DonationController.php';
            if ($method === 'POST') {
                DonationController::generateReceipt();
            } else {
                Response::methodNotAllowed();
            }
            break;

        case '/donations/recurring/process':
            require_once '../controllers/DonationController.php';
            if ($method === 'POST') {
                DonationController::processRecurring();
            } else {
                Response::methodNotAllowed();
            }
            break;

        case '/donations/webhook':
            require_once '../controllers/DonationController.php';
            if ($method === 'POST') {
                DonationController::handleWebhook();
            } else {
                Response::methodNotAllowed();
            }
            break;

        // Language endpoints
        case '/languages':
            require_once '../controllers/LanguageController.php';
            if ($method === 'GET') {
                LanguageController::getLanguages();
            } else {
                Response::methodNotAllowed();
            }
            break;

        case '/languages/translations':
            require_once '../controllers/LanguageController.php';
            if ($method === 'GET') {
                LanguageController::getTranslations();
            } elseif ($method === 'POST') {
                LanguageController::setTranslation();
            } else {
                Response::methodNotAllowed();
            }
            break;

        case '/languages/detect':
            require_once '../controllers/LanguageController.php';
            if ($method === 'GET') {
                LanguageController::detectLanguage();
            } else {
                Response::methodNotAllowed();
            }
            break;

        case '/languages/stats':
            require_once '../controllers/LanguageController.php';
            if ($method === 'GET') {
                LanguageController::getStats();
            } else {
                Response::methodNotAllowed();
            }
            break;

        case '/languages/missing':
            require_once '../controllers/LanguageController.php';
            if ($method === 'GET') {
                LanguageController::getMissingTranslations();
            } else {
                Response::methodNotAllowed();
            }
            break;

        case '/languages/import':
            require_once '../controllers/LanguageController.php';
            if ($method === 'POST') {
                LanguageController::importTranslations();
            } else {
                Response::methodNotAllowed();
            }
            break;

        case '/languages/categories':
            require_once '../controllers/LanguageController.php';
            if ($method === 'GET') {
                LanguageController::getCategories();
            } else {
                Response::methodNotAllowed();
            }
            break;

        case '/content/translations':
            require_once '../controllers/LanguageController.php';
            if ($method === 'GET') {
                LanguageController::getContentTranslations();
            } elseif ($method === 'POST') {
                LanguageController::setContentTranslation();
            } else {
                Response::methodNotAllowed();
            }
            break;

        case (preg_match('/^\/analytics\/engagement$/', $path) ? true : false):
            require_once '../controllers/AnalyticsController.php';
            if ($method === 'GET') {
                AnalyticsController::getVideoEngagement();
            } else {
                Response::methodNotAllowed();
            }
            break;

        case '/ai/recommendations':
            require_once '../controllers/AIController.php';
            $controller = new AIController();
            handleAIRoutes($controller, $method, $path);
            break;

        case '/ai/search':
            require_once '../controllers/AIController.php';
            $controller = new AIController();
            handleAISearchRoutes($controller, $method);
            break;

        case '/ai/health':
            require_once '../controllers/AIController.php';
            $controller = new AIController();
            if ($method === 'GET') {
                $health = $controller->getServiceHealth();
                Response::success($health);
            } else {
                Response::methodNotAllowed();
            }
            break;

        case '/ai/analytics':
            require_once '../controllers/AIController.php';
            $controller = new AIController();
            if ($method === 'GET') {
                $analytics = $controller->getAnalyticsOverview();
                Response::success($analytics);
            } else {
                Response::methodNotAllowed();
            }
            break;

        case '/test-push':
            error_log("Test push endpoint called with path: $path");
            Response::success(['message' => 'Push endpoint working', 'path' => $path]);
            break;

        case '/notifications':
            require_once '../controllers/NotificationController.php';
            $controller = new NotificationController();
            if ($method === 'GET') {
                $controller->getNotifications();
            } elseif ($method === 'POST') {
                // Admin only - create system notification
                $controller->createSystemNotification();
            } else {
                Response::methodNotAllowed();
            }
            break;

        case '/notifications/unread-count':
            require_once '../controllers/NotificationController.php';
            $controller = new NotificationController();
            if ($method === 'GET') {
                $controller->getUnreadCount();
            } else {
                Response::methodNotAllowed();
            }
            break;

        case (preg_match('/^\/notifications\/mark-read$/', $path) ? true : false):
            require_once '../controllers/NotificationController.php';
            $controller = new NotificationController();
            if ($method === 'POST') {
                $controller->markAsRead();
            } else {
                Response::methodNotAllowed();
            }
            break;

        case (preg_match('/^\/notifications\/mark-all-read$/', $path) ? true : false):
            require_once '../controllers/NotificationController.php';
            $controller = new NotificationController();
            if ($method === 'POST') {
                $controller->markAllAsRead();
            } else {
                Response::methodNotAllowed();
            }
            break;

        case (preg_match('/^\/notifications\/delete$/', $path) ? true : false):
            require_once '../controllers/NotificationController.php';
            $controller = new NotificationController();
            if ($method === 'DELETE') {
                $controller->deleteNotification();
            } else {
                Response::methodNotAllowed();
            }
            break;

        case '/notifications/video-favorited':
            require_once '../controllers/NotificationController.php';
            $controller = new NotificationController();
            if ($method === 'POST') {
                $controller->notifyVideoFavorited();
            } else {
                Response::methodNotAllowed();
            }
            break;

        case '/notifications/new-video-category':
            require_once '../controllers/NotificationController.php';
            $controller = new NotificationController();
            if ($method === 'POST') {
                $controller->notifyNewVideoInCategory();
            } else {
                Response::methodNotAllowed();
            }
            break;

        case '/push/subscribe':
            require_once '../controllers/PushController.php';
            $controller = new PushController();
            if ($method === 'POST') {
                $controller->subscribe();
            } else {
                Response::methodNotAllowed();
            }
            break;

        case '/push/unsubscribe':
            require_once '../controllers/PushController.php';
            $controller = new PushController();
            if ($method === 'POST') {
                $controller->unsubscribe();
            } else {
                Response::methodNotAllowed();
            }
            break;

        case '/push/subscriptions':
            require_once '../controllers/PushController.php';
            $controller = new PushController();
            if ($method === 'GET') {
                $controller->getSubscriptions();
            } else {
                Response::methodNotAllowed();
            }
            break;

        case '/push/test':
            require_once '../controllers/PushController.php';
            $controller = new PushController();
            if ($method === 'POST') {
                $controller->sendTest();
            } else {
                Response::methodNotAllowed();
            }
            break;

        case '/push/vapid-public-key':
            error_log("VAPID endpoint matched with path: $path");
            require_once '../controllers/PushController.php';
            $controller = new PushController();
            if ($method === 'GET') {
                $controller->getVapidPublicKey();
            } else {
                Response::methodNotAllowed();
            }
            break;

        case '/push/send-notification':
            require_once '../controllers/PushController.php';
            $controller = new PushController();
            if ($method === 'POST') {
                $controller->sendForNotification();
            } else {
                Response::methodNotAllowed();
            }
            break;

        case '/push/send-to-all':
            require_once '../controllers/PushController.php';
            $controller = new PushController();
            if ($method === 'POST') {
                $controller->sendToAll();
            } else {
                Response::methodNotAllowed();
            }
            break;

        case '/admin/login':
            require_once '../controllers/AdminController.php';
            if ($method === 'POST') {
                AdminController::login();
            } else {
                Response::methodNotAllowed();
            }
            break;

        case '/admin/dashboard':
            require_once '../controllers/AdminController.php';
            if ($method === 'GET') {
                AdminController::getDashboard();
            } else {
                Response::methodNotAllowed();
            }
            break;

        case '/admin/create-default-admin':
            require_once '../controllers/AdminController.php';
            if ($method === 'POST') {
                AdminController::createDefaultAdmin();
            } else {
                Response::methodNotAllowed();
            }
            break;

        case '/debug/database':
            if ($method === 'GET') {
                try {
                    $conn = getDBConnection();

                    // Check videos count
                    $result = $conn->query("SELECT COUNT(*) as count FROM videos");
                    $videosCount = $result->fetch_assoc()['count'];

                    // Check if search_history table exists
                    $result = $conn->query("SHOW TABLES LIKE 'search_history'");
                    $hasSearchHistory = $result->num_rows > 0;

                    // Get some sample videos if any exist
                    $sampleVideos = [];
                    if ($videosCount > 0) {
                        $result = $conn->query("SELECT id, title, channel_title, tags FROM videos LIMIT 5");
                        while ($row = $result->fetch_assoc()) {
                            $sampleVideos[] = $row;
                        }
                    }

                    Response::success([
                        'videos_count' => (int)$videosCount,
                        'has_search_history_table' => $hasSearchHistory,
                        'sample_videos' => $sampleVideos
                    ]);
                } catch (Exception $e) {
                    Response::error('Database check failed: ' . $e->getMessage(), 500);
                }
            } else {
                Response::methodNotAllowed();
            }
            break;

        case '/debug/add-test-videos':
            if ($method === 'POST') {
                try {
                    $conn = getDBConnection();

                    // Add some test videos that will match common search queries
                    $testVideos = [
                        [
                            'youtube_id' => 'dQw4w9WgXcQ',
                            'title' => 'Rick Astley - Never Gonna Give You Up',
                            'description' => 'Classic Rick Astley song that became a meme',
                            'channel_title' => 'Rick Astley',
                            'tags' => '["music", "80s", "pop", "rick astley", "meme"]',
                            'category_id' => 3, // Music category
                            'duration' => 212,
                            'view_count' => 1000000
                        ],
                        [
                            'youtube_id' => '9bZkp7q19f0',
                            'title' => 'PSY - Gangnam Style (강남스타일)',
                            'description' => 'World famous K-pop music video by PSY',
                            'channel_title' => 'officialpsy',
                            'tags' => '["music", "kpop", "psy", "gangnam", "korean", "dance"]',
                            'category_id' => 3,
                            'duration' => 252,
                            'view_count' => 4000000000
                        ],
                        [
                            'youtube_id' => 'hTWKbfoikeg',
                            'title' => 'Nirvana - Smells Like Teen Spirit (Official Music Video)',
                            'description' => 'Grunge rock classic by Nirvana',
                            'channel_title' => 'Nirvana',
                            'tags' => '["music", "rock", "grunge", "nirvana", "90s", "alternative"]',
                            'category_id' => 3,
                            'duration' => 301,
                            'view_count' => 150000000
                        ],
                        [
                            'youtube_id' => 'kJQP7kiw5Fk',
                            'title' => 'Luis Fonsi - Despacito ft. Daddy Yankee',
                            'description' => 'Latin pop hit that became a global phenomenon',
                            'channel_title' => 'Luis Fonsi',
                            'tags' => '["music", "latin", "pop", "despacito", "reggaeton", "spanish"]',
                            'category_id' => 3,
                            'duration' => 229,
                            'view_count' => 7000000000
                        ],
                        [
                            'youtube_id' => 'JGwWNGJdvx8',
                            'title' => 'Ed Sheeran - Shape of You (Official Music Video)',
                            'description' => 'Pop music hit by Ed Sheeran',
                            'channel_title' => 'Ed Sheeran',
                            'tags' => '["music", "pop", "ed sheeran", "shape of you", "british", "guitar"]',
                            'category_id' => 3,
                            'duration' => 234,
                            'view_count' => 5000000000
                        ]
                    ];

                    $inserted = 0;
                    foreach ($testVideos as $video) {
                        $stmt = $conn->prepare("INSERT IGNORE INTO videos
                            (youtube_id, title, description, channel_title, tags, category_id, duration, view_count, is_active)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
                        $stmt->bind_param("sssssiis",
                            $video['youtube_id'],
                            $video['title'],
                            $video['description'],
                            $video['channel_title'],
                            $video['tags'],
                            $video['category_id'],
                            $video['duration'],
                            $video['view_count']
                        );

                        if ($stmt->execute()) {
                            $inserted++;
                        }
                        $stmt->close();
                    }

                    Response::success([
                        'message' => "Added $inserted test videos",
                        'videos' => $testVideos
                    ]);
                } catch (Exception $e) {
                    Response::error('Failed to add test videos: ' . $e->getMessage(), 500);
                }
            } else {
                Response::methodNotAllowed();
            }
            break;

        case '/push/cleanup':
            require_once '../controllers/PushController.php';
            $controller = new PushController();
            if ($method === 'POST') {
                $controller->cleanup();
            } else {
                Response::methodNotAllowed();
            }
            break;

        default:
            // Debug for unmatched routes
            if (strpos($path, 'push') !== false) {
                error_log("Push route not matched: $path (original: $originalPath)");
            }
            Response::notFound('Endpoint not found');
    }

} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    error_log("API Error Stack: " . $e->getTraceAsString());
    // Ensure we return JSON even for unexpected errors
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    Response::error('Internal server error: ' . (ini_get('display_errors') ? $e->getMessage() : 'Please check server logs'), 500);
}

// Helper function to enable detailed PHP error logging to a custom file
function enable_php_error_log($filename) {
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../../logs/' . $filename);
    error_log("API error logging enabled to $filename");
}

// Helper functions for route handling
function handleVideoRoutes($controller, $method, $path) {

    // Handle video like routes
    if (preg_match('/^\/videos\/(\d+)\/like$/', $path, $matches)) {
        $videoId = $matches[1];
        if ($method === 'POST') {
            $controller->like($videoId);
        } elseif ($method === 'GET') {
            $controller->checkLikeStatus($videoId);
        } else {
            Response::methodNotAllowed();
        }
        return;
    }

    // Handle video view increment routes
    if (preg_match('/^\/videos\/(\d+)\/view$/', $path, $matches)) {
        $videoId = $matches[1];
        if ($method === 'POST') {
            $controller->incrementView($videoId);
        } else {
            Response::methodNotAllowed();
        }
        return;
    }

    // Handle video details route: /videos/{id}
    if ($method === 'GET' && preg_match('/^\/videos\/(\d+)$/', $path, $matches)) {
        $controller->show($matches[1]);
        return;
    }

    // Trending route: /videos/trending
    if ($method === 'GET' && preg_match('/^\/videos\/trending$/', $path)) {
        // For now, treat \"trending\" as featured (most viewed)
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 12;
        $limit = max(1, min(50, $limit));
        $videos = Video::getFeaturedVideos($limit);
        Response::success($videos);
        return;
    }

    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                $controller->show($_GET['id']);
            } elseif (isset($_GET['category'])) {
                $controller->getByCategory($_GET['category']);
            } elseif (isset($_GET['search'])) { // backward compatible
                $controller->search($_GET['search']);
            } else {
                $controller->index();
            }
            break;
        default:
            Response::methodNotAllowed();
    }
}

function handleCategoryRoutes($controller, $method, $path) {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                $controller->show($_GET['id']);
            } else {
                $controller->index();
            }
            break;
        default:
            Response::methodNotAllowed();
    }
}

function handleUserRoutes($controller, $method) {
    switch ($method) {
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            if (isset($data['action'])) {
                switch ($data['action']) {
                    case 'login':
                        $controller->login($data);
                        break;
                    case 'register':
                        $controller->register($data);
                        break;
                    default:
                        Response::badRequest('Invalid action');
                }
            } else {
                Response::badRequest('Missing action parameter');
            }
            break;
        default:
            Response::methodNotAllowed();
    }
}

function handleLivestreamRoutes($controller, $method) {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                $controller->show($_GET['id']);
            } elseif (isset($_GET['category'])) {
                $controller->getByCategory($_GET['category']);
            } else {
                $controller->index();
            }
            break;
        default:
            Response::methodNotAllowed();
    }
}

function handleAIRoutes($controller, $method, $path) {
    if ($method === 'GET') {
        // Get recommendations
        $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
        $contextVideoId = isset($_GET['context_video_id']) ? (int)$_GET['context_video_id'] : null;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

        if (!$userId) {
            Response::badRequest('user_id parameter required');
            return;
        }

        $recommendations = $controller->getRecommendations($userId, $contextVideoId, $limit);
        Response::success($recommendations);

    } elseif ($method === 'POST') {
        // Track recommendation interactions
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data) {
            Response::badRequest('Invalid JSON data');
            return;
        }

        $userId = $data['user_id'] ?? null;
        $videoId = $data['video_id'] ?? null;
        $action = $data['action'] ?? null;
        $context = $data['context'] ?? [];

        if (!$userId || !$videoId || !$action) {
            Response::badRequest('Missing required parameters: user_id, video_id, action');
            return;
        }

        $success = $controller->trackRecommendationInteraction($userId, $videoId, $action, $context);

        if ($success) {
            Response::success(['tracked' => true]);
        } else {
            Response::error('Failed to track interaction', 500);
        }

    } else {
        Response::methodNotAllowed();
    }
}

function handleAISearchRoutes($controller, $method) {
    if ($method === 'GET') {
        // Basic search (fallback to existing search)
        $query = isset($_GET['q']) ? trim($_GET['q']) : '';
        $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

        if (!$query) {
            Response::badRequest('Query parameter required');
            return;
        }

        $results = $controller->search($query, $userId, $limit);
        Response::success($results);

    } elseif ($method === 'POST') {
        // Advanced AI search
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data || !isset($data['query'])) {
            Response::badRequest('Query parameter required in JSON payload');
            return;
        }

        $query = trim($data['query']);
        $userId = $data['user_id'] ?? null;
        $limit = $data['limit'] ?? 20;

        $results = $controller->search($query, $userId, $limit);
        Response::success($results);

    } else {
            Response::methodNotAllowed();
    }
}

function handleCommentRoutes($controller, $method, $path) {
    // Extract video ID from path: /comments/video/{id}
    if (preg_match('/^\/comments\/video\/(\d+)$/', $path, $matches)) {
        $videoId = $matches[1];

        switch ($method) {
            case 'GET':
                $controller->getByVideo($videoId);
                break;
            case 'POST':
                $controller->create();
                break;
            default:
                Response::methodNotAllowed();
        }
        return;
    }

    // Extract comment ID from path: /comments/{id}
    if (preg_match('/^\/comments\/(\d+)$/', $path, $matches)) {
        $commentId = $matches[1];

        switch ($method) {
            case 'GET':
                $controller->show($commentId);
                break;
            case 'PUT':
                $controller->update($commentId);
                break;
            case 'DELETE':
                $controller->delete($commentId);
                break;
            default:
                Response::methodNotAllowed();
        }
        return;
    }

    // Admin moderation routes
    if (preg_match('/^\/comments\/moderation$/', $path)) {
        if ($method === 'GET') {
            $controller->getForModeration();
        } else {
            Response::methodNotAllowed();
        }
        return;
    }

    // Admin moderation by ID
    if (preg_match('/^\/comments\/(\d+)\/(approve|reject)$/', $path, $matches)) {
        $commentId = $matches[1];
        $action = $matches[2];

        if ($method === 'POST') {
            if ($action === 'approve') {
                $controller->approve($commentId);
            } elseif ($action === 'reject') {
                $controller->reject($commentId);
            }
        } else {
            Response::methodNotAllowed();
        }
        return;
    }

    // Default comments endpoint
    switch ($method) {
        case 'GET':
            // Could return recent comments or require video ID
            Response::badRequest('Video ID required for comments');
            break;
        default:
            Response::methodNotAllowed();
    }
}

function handleReactionRoutes($controller, $method, $path) {
    // Extract video ID from path: /reactions/video/{id}
    if (preg_match('/^\/reactions\/video\/(\d+)$/', $path, $matches)) {
        $videoId = $matches[1];

        switch ($method) {
            case 'GET':
                $controller->getByVideo($videoId);
                break;
            case 'POST':
                $controller->react($videoId);
                break;
            case 'DELETE':
                $controller->removeReaction($videoId);
                break;
            default:
                Response::methodNotAllowed();
        }
        return;
    }

    // Get user's reaction for a video: /reactions/video/{id}/user
    if (preg_match('/^\/reactions\/video\/(\d+)\/user$/', $path, $matches)) {
        $videoId = $matches[1];

        if ($method === 'GET') {
            $controller->getUserReaction($videoId);
        } else {
            Response::methodNotAllowed();
        }
        return;
    }

    // Bulk stats endpoint
    if (preg_match('/^\/reactions\/bulk$/', $path)) {
        if ($method === 'POST') {
            $controller->getBulkStats();
        } else {
            Response::methodNotAllowed();
        }
        return;
    }

    // Reaction types endpoint
    if (preg_match('/^\/reactions\/types$/', $path)) {
        if ($method === 'GET') {
            $controller->getReactionTypes();
        } else {
            Response::methodNotAllowed();
        }
        return;
    }

    // Leaderboard endpoint
    if (preg_match('/^\/reactions\/leaderboard$/', $path)) {
        if ($method === 'GET') {
            $controller->getLeaderboard();
        } else {
            Response::methodNotAllowed();
        }
        return;
    }

    // Default reactions endpoint
    Response::badRequest('Invalid reaction endpoint');
}
?>
