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
        // Support both JSON and Multipart (for file uploads)
        $data = [];
        $contentType = $_SERVER["CONTENT_TYPE"] ?? '';
        
        if (strpos($contentType, 'application/json') !== false) {
            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);
        } else {
            $data = $_POST;
        }
        
        if (!$data || !isset($data['full_name']) || !isset($data['email']) || !isset($data['city']) || !isset($data['country']) || !isset($data['request_text'])) {
            return Response::error('Missing required fields: full_name, email, city, country, request_text', 400);
        }

        // Handle file upload if present
        $attachmentUrl = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            // Ensure the path is relative to the backend root correctly
            $uploadDir = __DIR__ . '/../uploads/prayer_requests/';
            
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    error_log("PrayerRequestController::submit - Failed to create directory: " . $uploadDir);
                }
            }

            $fileTmpPath = $_FILES['attachment']['tmp_name'];
            $fileName = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", $_FILES['attachment']['name']);
            $destPath = $uploadDir . $fileName;

            if (move_uploaded_file($fileTmpPath, $destPath)) {
                $attachmentUrl = 'uploads/prayer_requests/' . $fileName;
            } else {
                error_log("PrayerRequestController::submit - Failed to move uploaded file to: " . $destPath);
            }
        } elseif (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
            error_log("PrayerRequestController::submit - File upload error code: " . $_FILES['attachment']['error']);
            return Response::error('File upload failed. Error code: ' . $_FILES['attachment']['error'], 400);
        }

        $data['attachment_url'] = $attachmentUrl;

        // Optional: Check if user is logged in to link user_id
        $user = Auth::getCurrentUser();
        if ($user) {
            $data['user_id'] = $user['user_id'];
        }

        $id = PrayerRequest::create($data);
        
        if ($id) {
            return Response::success(['id' => $id, 'attachment_url' => $attachmentUrl], 'Prayer request submitted successfully');
        } else {
            return Response::error('Failed to submit prayer request');
        }
    }

    /**
     * Get prayer requests for current user
     * GET /prayer-requests/my
     */
    public function userRequests() {
        Auth::requireAuth();
        $user = Auth::getUserFromToken();
        
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        
        $requests = PrayerRequest::getByUser($user['user_id'], $limit, $offset);
        return Response::success($requests);
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
