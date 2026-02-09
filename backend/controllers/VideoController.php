<?php
/**
 * Video Controller
 * Handles video-related API endpoints
 */

require_once __DIR__ . '/../models/Video.php';
require_once __DIR__ . '/../utils/Auth.php';

class VideoController {

    public function index() {
        try {
            $videos = Video::getFeaturedVideos(50);
            Response::success($videos);
        } catch (Exception $e) {
            error_log("VideoController index error: " . $e->getMessage());
            Response::error('Failed to load videos', 500);
        }
    }

    public function show($id) {
        try {
            $video = Video::getById($id);

            if (!$video) {
                Response::notFound('Video not found');
            }

            // Increment view count
            Video::incrementViews($id);

            Response::success($video);
        } catch (Exception $e) {
            error_log("VideoController show error: " . $e->getMessage());
            Response::error('Failed to load video', 500);
        }
    }

    public function getByCategory($categoryId) {
        try {
            $videos = Video::getByCategory($categoryId, 50);
            Response::success($videos);
        } catch (Exception $e) {
            error_log("VideoController getByCategory error: " . $e->getMessage());
            Response::error('Failed to load category videos', 500);
        }
    }

    public function search($query) {
        try {
            if (empty($query) || strlen($query) < 2) {
                Response::badRequest('Search query must be at least 2 characters');
            }

            $videos = Video::search($query);
            Response::success($videos);
        } catch (Exception $e) {
            error_log("VideoController search error: " . $e->getMessage());
            Response::error('Search failed', 500);
        }
    }

    public function like($id) {
        try {
            // Get user info if authenticated
            $userId = null;
            $sessionId = null;

            if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
                $auth = new Auth();
                $user = $auth->getCurrentUser();
                if ($user) {
                    $userId = $user['id'];
                }
            }

            // Get session ID for anonymous users
            $sessionId = $_COOKIE['lcmtv_session'] ?? session_id();

            // Get client info
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';

            $result = Video::toggleLike($id, $userId, $sessionId, $userAgent, $ipAddress);

            Response::success($result);
        } catch (Exception $e) {
            error_log("VideoController like error: " . $e->getMessage());
            Response::error('Failed to toggle like', 500);
        }
    }

    public function checkLikeStatus($id) {
        try {
            // Get user info if authenticated
            $userId = null;
            $sessionId = null;

            if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
                $auth = new Auth();
                $user = $auth->getCurrentUser();
                if ($user) {
                    $userId = $user['id'];
                }
            }

            // Get session ID for anonymous users
            $sessionId = $_COOKIE['lcmtv_session'] ?? session_id();

            $hasLiked = Video::hasUserLiked($id, $userId, $sessionId);

            Response::success(['liked' => $hasLiked]);
        } catch (Exception $e) {
            error_log("VideoController checkLikeStatus error: " . $e->getMessage());
            Response::error('Failed to check like status', 500);
        }
    }

    public function incrementView($id) {
        try {
            $success = Video::incrementViews($id);
            if ($success) {
                Response::success(['message' => 'View count incremented']);
            } else {
                Response::error('Failed to increment view count', 500);
            }
        } catch (Exception $e) {
            error_log("VideoController incrementView error: " . $e->getMessage());
            Response::error('Failed to increment view count', 500);
        }
    }
}
?>
