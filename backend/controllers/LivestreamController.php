<?php
/**
 * Livestream Controller
 * Handles livestream-related API requests
 */

require_once __DIR__ . '/../models/Livestream.php';
require_once __DIR__ . '/../utils/Response.php';

class LivestreamController {

    /**
     * Get all livestreams
     */
    public function index() {
        try {
            $livestreams = Livestream::getAll();
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
            $livestream = Livestream::getFeatured();
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
            $livestream = Livestream::find($id);
            if (!$livestream) {
                Response::notFound('Livestream not found');
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
            $livestreams = Livestream::getByCategory($categoryId);
            Response::success($livestreams);
        } catch (Exception $e) {
            error_log("Livestream getByCategory error: " . $e->getMessage());
            Response::error('Failed to fetch livestreams by category', 500);
        }
    }

    /**
     * Batch delete livestreams
     */
    public function batchDelete() {
        try {
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
            Response::error('Failed to batch delete livestreams', 500);
        }
    }
}
?>
