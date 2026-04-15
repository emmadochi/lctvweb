<?php
/**
 * Admin Controller
 * Handles admin authentication and administrative functions
 */

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../utils/Auth.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/Mailer.php';

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

            // Verify password
            // Support environment-driven default admin for initial setup/recovery
            $envAdminEmail = getenv('ADMIN_EMAIL') ?: 'info@lifechangertouch.org';
            $envAdminPass  = getenv('ADMIN_PASSWORD');

            if (($envAdminPass && $email === $envAdminEmail && $password === $envAdminPass) ||
                password_verify($password, $user['password_hash'])) {

                // Generate JWT token
                $tokenPayload = [
                    'user_id' => $user['id'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'exp' => time() + (24 * 60 * 60) // 24 hours
                ];

                $token = JWT::encode($tokenPayload);

                // Send admin login notification email (best-effort)
                try {
                    $subject = 'Admin login to LCMTV dashboard';
                    $ip      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                    $agent   = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown device';
                    $body    = '<p>Hello ' . htmlspecialchars($user['name'] ?? $user['email'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ',</p>'
                             . '<p>An admin account just logged in to the LCMTV dashboard.</p>'
                             . '<ul>'
                             . '<li><strong>Time:</strong> ' . date('Y-m-d H:i:s') . '</li>'
                             . '<li><strong>IP address:</strong> ' . htmlspecialchars($ip, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>'
                             . '<li><strong>Device:</strong> ' . htmlspecialchars($agent, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>'
                             . '</ul>'
                             . '<p>If this was not you, please secure your account immediately.</p>'
                             . '<p>LCMTV Security Team</p>';
                    Mailer::send($user['email'], $subject, $body);
                } catch (\Throwable $e) {
                    error_log('AdminController login notification email failed: ' . $e->getMessage());
                }

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
     * Update a user's role (Promotion logic)
     */
    public static function updateUserRole() {
        try {
            // Require admin authentication
            Auth::requireAdmin();

            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);

            if (empty($data['user_id']) || empty($data['role'])) {
                return Response::error('User ID and role are required', 400);
            }

            $userId = (int)$data['user_id'];
            $newRole = trim($data['role']);

            // Validate the role name
            $validRoles = ['user', 'leader', 'pastor', 'director', 'admin', 'super_admin'];
            if (!in_array($newRole, $validRoles)) {
                return Response::error('Invalid role name', 400);
            }

            $conn = getDBConnection();
            $stmt = $conn->prepare("UPDATE users SET role = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->bind_param("si", $newRole, $userId);

            if ($stmt->execute()) {
                // Send notification email to user about their new role
                try {
                    $user = User::getById($userId);
                    if ($user) {
                        $subject = 'LCMTV Role Update: You have been promoted to ' . ucfirst($newRole);
                        $body = "<p>Hello " . htmlspecialchars($user['first_name'] ?: $user['email']) . ",</p>"
                              . "<p>An administrator has updated your role on LCMTV.</p>"
                              . "<p><strong>New Role:</strong> " . ucfirst($newRole) . "</p>"
                              . "<p>You now have access to content tailored for your leadership position.</p>"
                              . "<p>Blessings,<br>LCMTV Team</p>";
                        Mailer::send($user['email'], $subject, $body);
                    }
                } catch (\Throwable $e) {
                    error_log('AdminController notification email failed: ' . $e->getMessage());
                }

                return Response::success(['message' => 'User role updated successfully', 'role' => $newRole]);
            } else {
                return Response::error('Failed to update user role', 500);
            }

        } catch (Exception $e) {
            error_log("AdminController::updateUserRole - Error: " . $e->getMessage());
            return Response::error('Error updating role: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create default admin user if it doesn't exist
     */
    public static function createDefaultAdmin() {
        try {
            $adminEmail = getenv('ADMIN_EMAIL') ?: 'info@lifechangertouch.org';
            $adminPass  = getenv('ADMIN_PASSWORD') ?: 'admin123';

            // Check if admin already exists
            $existingAdmin = User::getByEmail($adminEmail);

            if (!$existingAdmin) {
                // Create default admin user
                $adminData = [
                    'name' => 'LCMTV Admin',
                    'email' => $adminEmail,
                    'password' => password_hash($adminPass, PASSWORD_DEFAULT),
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