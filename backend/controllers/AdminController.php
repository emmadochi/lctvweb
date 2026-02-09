<?php
/**
 * Admin Controller
 * Handles admin authentication and administrative functions
 */

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../utils/Auth.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/JWT.php';

class AdminController {
    /**
     * Admin login
     */
    public static function login() {
        try {
            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);

            // Validate JSON parsing
            if (json_last_error() !== JSON_ERROR_NONE) {
                return Response::error('Invalid JSON data', 400);
            }

            // Validate required fields
            if (empty($data['email']) || empty($data['password'])) {
                return Response::error('Email and password are required', 400);
            }

            $email = trim($data['email']);
            $password = $data['password'];

            // Get user by email
            $user = User::getByEmail($email);

            if (!$user) {
                return Response::error('Invalid email or password', 401);
            }

            // Check if user has admin role
            if (!in_array($user['role'], ['admin', 'super_admin'])) {
                return Response::error('Access denied. Admin privileges required.', 403);
            }

            // Verify password (for now, use simple check - in production use proper hashing)
            // For demo purposes, allow default admin credentials
            if (($email === 'admin@lcmtv.com' && $password === 'admin123') ||
                password_verify($password, $user['password_hash'])) {

                // Generate JWT token
                $tokenPayload = [
                    'user_id' => $user['id'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'exp' => time() + (24 * 60 * 60) // 24 hours
                ];

                $token = JWT::encode($tokenPayload);

                // Return success response
                return Response::success([
                    'token' => $token,
                    'user' => [
                        'id' => $user['id'],
                        'email' => $user['email'],
                        'name' => $user['name'],
                        'role' => $user['role']
                    ],
                    'message' => 'Admin login successful'
                ]);

            } else {
                return Response::error('Invalid email or password', 401);
            }

        } catch (Exception $e) {
            error_log("AdminController::login - Error: " . $e->getMessage());
            return Response::error('Login failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get admin dashboard data
     */
    public static function getDashboard() {
        try {
            // Require admin authentication
            Auth::requireAuth();
            $user = Auth::getUserFromToken();

            if (!$user || !in_array($user['role'], ['admin', 'super_admin'])) {
                return Response::forbidden('Admin access required');
            }

            // Get basic stats
            $stats = [
                'total_users' => User::count(),
                'total_videos' => 0, // Will be implemented
                'total_views' => 0, // Will be implemented
                'recent_activity' => [] // Will be implemented
            ];

            return Response::success($stats);

        } catch (Exception $e) {
            error_log("AdminController::getDashboard - Error: " . $e->getMessage());
            return Response::error('Error fetching dashboard data: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create default admin user if it doesn't exist
     */
    public static function createDefaultAdmin() {
        try {
            // Check if admin already exists
            $existingAdmin = User::getByEmail('admin@lcmtv.com');

            if (!$existingAdmin) {
                // Create default admin user
                $adminData = [
                    'name' => 'LCMTV Admin',
                    'email' => 'admin@lcmtv.com',
                    'password' => password_hash('admin123', PASSWORD_DEFAULT),
                    'role' => 'super_admin',
                    'status' => 'active',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];

                $userId = User::create($adminData);

                if ($userId) {
                    return Response::success(['message' => 'Default admin user created', 'user_id' => $userId]);
                } else {
                    return Response::error('Failed to create default admin user', 500);
                }
            } else {
                return Response::success(['message' => 'Admin user already exists']);
            }

        } catch (Exception $e) {
            error_log("AdminController::createDefaultAdmin - Error: " . $e->getMessage());
            return Response::error('Error creating admin user: ' . $e->getMessage(), 500);
        }
    }
}
?>