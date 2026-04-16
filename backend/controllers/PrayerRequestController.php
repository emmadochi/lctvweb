<?php
/**
 * PrayerRequestController
 * Handles API requests for prayer points.
 */

require_once __DIR__ . '/../models/PrayerRequest.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Auth.php';
require_once __DIR__ . '/../utils/Mailer.php';
require_once __DIR__ . '/../utils/NotificationService.php';

class PrayerRequestController {
    
    /**
     * Submit a new prayer request
     * POST /prayer-requests
     */
    public function submit() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['full_name']) || !isset($data['email']) || !isset($data['request_text'])) {
            return Response::error('Missing required fields: full_name, email, request_text', 400);
        }

        // Optional: Check if user is logged in to link user_id
        $user = Auth::getCurrentUser();
        if ($user) {
            $data['user_id'] = $user['id'];
        }

        $id = PrayerRequest::create($data);
        
        if ($id) {
            return Response::success(['id' => $id], 'Prayer request submitted successfully');
        } else {
            return Response::error('Failed to submit prayer request');
        }
    }

    /**
     * Get all prayer requests (Admin only)
     * GET /admin/prayer-requests
     */
    public function getAll() {
        Auth::requireAdmin();
        
        $filters = [];
        if (isset($_GET['status'])) $filters['status'] = $_GET['status'];
        if (isset($_GET['user_id'])) $filters['user_id'] = $_GET['user_id'];

        $requests = PrayerRequest::getAll($filters);
        return Response::success($requests);
    }

    /**
     * Respond to a prayer request (Admin only)
     * POST /admin/prayer-requests/:id/respond
     */
    public function respond($id) {
        $admin = Auth::requireAdmin();
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['response'])) {
            return Response::error('Response text is required', 400);
        }

        $request = PrayerRequest::getById($id);
        if (!$request) {
            return Response::error('Prayer request not found', 404);
        }

        $success = PrayerRequest::respond($id, $admin['id'], $data['response']);
        
        if ($success) {
            // Send email and push notification via centralized service
            NotificationService::sendPrayerResponse($request, $data['response']);
            
            return Response::success(null, 'Response submitted and user notified');
        } else {
            return Response::error('Failed to submit response');
        }
    }

    /**
     * Delete a prayer request (Admin only)
     * DELETE /admin/prayer-requests/:id
     */
    public function delete($id) {
        Auth::requireAdmin();
        
        if (PrayerRequest::delete($id)) {
            return Response::success(null, 'Prayer request deleted');
        } else {
            return Response::error('Failed to delete prayer request');
        }
    }
}
?>
