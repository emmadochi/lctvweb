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
            $sort = $_GET['sort'] ?? 'featured';
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            
            // Get current user data
            $user = Auth::getCurrentUser();
            $userRole = $user['role'] ?? 'general';
            $userId = $user['user_id'] ?? $user['id'] ?? null;
            
            if ($sort === 'recent') {
                // Get videos ordered by creation date (most recent first)
                $videos = Video::getRecentVideos($limit, $userRole, $userId);
            } else {
                // Default to featured videos
                $videos = Video::getFeaturedVideos($limit, $userRole, $userId);
            }

            Response::success($videos);
        } catch (Exception $e) {
            error_log("VideoController index error: " . $e->getMessage());
            Response::error('Failed to load videos', 500);
        }
    }

    public function show($id) {
        try {
            $user = Auth::getCurrentUser();
            $userRole = $user['role'] ?? 'general';
            $userId = $user['user_id'] ?? $user['id'] ?? null;
            
            $video = Video::getById($id, $userId);

            if (!$video) {
                Response::notFound('Video not found');
            }

            // Check if user is allowed to see this video
            $allowedRoles = Video::getAllowedRoles($userRole);
            $targetRole = $video['target_role'] ?? 'general';
            
            if (!in_array($targetRole, $allowedRoles) && !in_array($userRole, ['admin', 'super_admin'])) {
                Response::forbidden('You do not have permission to view this video');
                return;
            }

            // Increment view count
            Video::incrementViews($id);

            // Add purchase status
            $video['has_access'] = true;
            if ($video['is_premium']) {
                $userId = $user['user_id'] ?? $user['id'] ?? null;
                $video['has_access'] = Video::hasUserPurchased($id, $userId);
            }

            Response::success($video);
        } catch (Exception $e) {
            error_log("VideoController show error: " . $e->getMessage());
            Response::error('Failed to load video', 500);
        }
    }

    public function getByCategory($categoryId) {
        try {
            $user = Auth::getCurrentUser();
            $userRole = $user['role'] ?? 'general';
            $userId = $user['user_id'] ?? $user['id'] ?? null;
            
            $videos = Video::getByCategory($categoryId, 50, $userRole, $userId);
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

            $user = Auth::getCurrentUser();
            $userRole = $user['role'] ?? 'general';
            $userId = $user['user_id'] ?? $user['id'] ?? null;
            
            $videos = Video::search($query, 20, $userRole, $userId);
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
                $user = Auth::getCurrentUser();
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
                $user = Auth::getCurrentUser();
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

    public function exclusive() {
        try {
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            
            // Get current user role
            // Get current user context
            $user = Auth::getCurrentUser();
            $userRole = $user['role'] ?? 'general';
            $userId = $user['user_id'] ?? $user['id'] ?? null;
            
            // General users shouldn't even know this exists or will get empty list
            $videos = Video::getExclusiveContent($limit, $userRole, $userId);

            Response::success($videos);
        } catch (Exception $e) {
            error_log("VideoController exclusive error: " . $e->getMessage());
            Response::error('Failed to load exclusive videos', 500);
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
