<?php
/**
 * Livestream Controller
 * Handles livestream-related API requests
 */

require_once __DIR__ . '/../models/Livestream.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Auth.php';

class LivestreamController {

    /**
     * Get all livestreams
     */
    public function index() {
        try {
            $user = Auth::getCurrentUser();
            $userRole = $user['role'] ?? 'general';
            
            $livestreams = Livestream::getAll($userRole);
            Response::success($livestreams);
        } catch (Exception $e) {
            error_log("Livestream index error: " . $e->getMessage());
            Response::error('Failed to fetch livestreams', 500);
        }
    }

    /**
     * Get featured livestream
     */
    public function featured() {
        try {
            $user = Auth::getCurrentUser();
            $userRole = $user['role'] ?? 'general';
            
            $livestream = Livestream::getFeatured($userRole);
            Response::success($livestream);
        } catch (Exception $e) {
            error_log("Livestream featured error: " . $e->getMessage());
            Response::success(null); // Return null for featured if error
        }
    }

    /**
     * Get single livestream by ID
     */
    public function show($id) {
        try {
            $user = Auth::getCurrentUser();
            $userRole = $user['role'] ?? 'general';
            
            $livestream = Livestream::find($id);
            if (!$livestream) {
                Response::notFound('Livestream not found');
                return;
            }

            // Check if user is allowed to see this livestream
            $allowedRoles = Auth::getAllowedRoles($userRole);
            $targetRole = $livestream['target_role'] ?? 'general';
            
            if (!in_array($targetRole, $allowedRoles) && !in_array($userRole, ['admin', 'super_admin'])) {
                Response::forbidden('You do not have permission to view this livestream');
                return;
            }

            Response::success($livestream);
        } catch (Exception $e) {
            error_log("Livestream show error: " . $e->getMessage());
            Response::error('Failed to fetch livestream', 500);
        }
    }

    /**
     * Get livestreams by category
     */
    public function getByCategory($categoryId) {
        try {
            $user = Auth::getCurrentUser();
            $userRole = $user['role'] ?? 'general';
            
            $livestreams = Livestream::getByCategory($categoryId, $userRole);
            Response::success($livestreams);
        } catch (Exception $e) {
            error_log("Livestream getByCategory error: " . $e->getMessage());
            Response::error('Failed to fetch livestreams by category', 500);
        }
    }

    /**
     * Delete a single livestream
     */
    public function delete($id) {
        try {
            // Secure endpoint - Admins only
            Auth::requireRole(['admin', 'super_admin']);

            if (Livestream::delete($id)) {
                Response::success(['id' => $id, 'message' => 'Livestream deleted successfully']);
            } else {
                Response::error('Failed to delete livestream', 500);
            }
        } catch (Exception $e) {
            error_log("Livestream delete error: " . $e->getMessage());
            Response::error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Update a livestream
     */
    public function update($id) {
        try {
            // Secure endpoint - Admins only
            Auth::requireRole(['admin', 'super_admin']);

            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                Response::badRequest('Invalid JSON data');
                return;
            }

            // Verify livestream exists
            $livestream = Livestream::find($id);
            if (!$livestream) {
                Response::notFound('Livestream not found');
                return;
            }

            // Merge existing data with new data if some fields are missing
            $updateData = array_merge($livestream, $data);

            if (Livestream::update($id, $updateData)) {
                Response::success(['id' => $id, 'message' => 'Livestream updated successfully']);
            } else {
                Response::error('Failed to update livestream', 500);
            }
        } catch (Exception $e) {
            error_log("Livestream update error: " . $e->getMessage());
            Response::error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Batch delete livestreams
     */
    public function batchDelete() {
        try {
            // Secure endpoint - Admins only
            Auth::requireRole(['admin', 'super_admin']);

            $data = json_decode(file_get_contents('php://input'), true);
            $ids = $data['ids'] ?? [];

            if (empty($ids)) {
                Response::badRequest('No livestream IDs provided');
                return;
            }

            if (Livestream::batchDelete($ids)) {
                Response::success(['deleted' => count($ids)]);
            } else {
                Response::error('Failed to delete livestreams', 500);
            }
        } catch (Exception $e) {
            error_log("Livestream batchDelete error: " . $e->getMessage());
            Response::error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Get exclusive leadership livestreams
     */
    public function exclusive() {
        try {
            $user = Auth::getCurrentUser();
            $userRole = $user['role'] ?? 'general';
            
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $livestreams = Livestream::getExclusive($userRole, $limit);
            Response::success($livestreams);
        } catch (Exception $e) {
            error_log("Livestream exclusive error: " . $e->getMessage());
            Response::error('Failed to fetch exclusive livestreams', 500);
        }
    }

    /**
     * Track livestream heartbeat for real-time viewer count
     */
    public function heartbeat($id) {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $sessionId = $data['session_id'] ?? null;

            if (!$sessionId) {
                Response::badRequest('Session ID required');
                return;
            }

            if (Livestream::updateHeartbeat($id, $sessionId)) {
                $count = Livestream::getActiveViewerCount($id);
                Response::success(['active_viewers' => $count]);
            } else {
                Response::error('Failed to update heartbeat', 500);
            }
        } catch (Exception $e) {
            error_log("Livestream heartbeat error: " . $e->getMessage());
            Response::error('Failed to process heartbeat', 500);
        }
    }

    /**
     * Track livestream view for analytics
     */
    public function trackView($livestreamId) {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $userId = $data['user_id'] ?? null;
            $timestamp = $data['timestamp'] ?? date('Y-m-d H:i:s');

            // Verify livestream exists
            $livestream = Livestream::find($livestreamId);
            if (!$livestream) {
                Response::notFound('Livestream not found');
                return;
            }

            // Update viewer count on the livestream record (manual boost)
            // This is use as a fallback or for non-realtime views
            $currentData = $livestream;
            $currentData['viewer_count'] = ($currentData['viewer_count'] ?? 0) + 1;
            Livestream::update($livestreamId, $currentData);

            error_log("Livestream view tracked: ID {$livestreamId}, User: " . ($userId ?? 'anonymous') . ", Time: {$timestamp}");

            Response::success(['tracked' => true, 'livestream_id' => $livestreamId]);
        } catch (Exception $e) {
            error_log("Livestream trackView error: " . $e->getMessage());
            Response::error('Failed to track livestream view', 500);
        }
    }
}
?>
