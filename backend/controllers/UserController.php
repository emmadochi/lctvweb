<?php
/**
 * User Controller
 * Handles user authentication and profile management
 */

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Notification.php';
require_once __DIR__ . '/../utils/Mailer.php';

class UserController {

    public function register($data) {
        try {
            // Validate input
            if (empty($data['email']) || empty($data['password'])) {
                Response::validationError(['email' => 'Email is required', 'password' => 'Password is required']);
            }

            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                Response::validationError(['email' => 'Invalid email format']);
            }

            if (strlen($data['password']) < 6) {
                Response::validationError(['password' => 'Password must be at least 6 characters']);
            }

            // Check if user already exists
            if (User::findByEmail($data['email'])) {
                Response::validationError(['email' => 'Email already registered']);
            }

            // Create user
            $userData = [
                'email' => $data['email'],
                'password' => password_hash($data['password'], PASSWORD_DEFAULT),
                'first_name' => $data['first_name'] ?? '',
                'last_name' => $data['last_name'] ?? '',
                'role' => 'user'
            ];

            $userId = User::create($userData);

            if ($userId) {
                // Generate token
                $user = User::getById($userId);
                $token = Auth::generateToken($user);

                // Send welcome email (best-effort, non-blocking for API)
                try {
                    $appUrl  = getenv('APP_URL') ?: 'http://localhost/LCMTVWebNew/frontend';
                    $subject = 'Welcome to LCMTV';
                    $name    = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'there';
                    $body    = '<p>Dear ' . htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ',</p>'
                             . '<p>Thank you for registering with LCMTV. You can now log in and start watching content.</p>'
                             . '<p><a href="' . htmlspecialchars($appUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
                             . ' style="display:inline-block;padding:10px 18px;background:#6a0dad;color:#ffffff;'
                             . 'text-decoration:none;border-radius:4px;">Go to LCMTV</a></p>'
                             . '<p>Blessings,<br>LCMTV Team</p>';
                    Mailer::send($user['email'], $subject, $body);
                } catch (\Throwable $e) {
                    error_log('UserController register welcome email failed: ' . $e->getMessage());
                }

                Response::success([
                    'user' => [
                        'id' => $user['id'],
                        'email' => $user['email'],
                        'first_name' => $user['first_name'],
                        'last_name' => $user['last_name']
                    ],
                    'token' => $token
                ], 'User registered successfully', 201);
            } else {
                Response::error('Failed to create user', 500);
            }

        } catch (Exception $e) {
            error_log("UserController register error: " . $e->getMessage());
            Response::error('Registration failed', 500);
        }
    }

    public function login($data) {
        try {
            // Validate input
            if (empty($data['email']) || empty($data['password'])) {
                Response::validationError(['email' => 'Email is required', 'password' => 'Password is required']);
            }

            // Find user
            $user = User::findByEmail($data['email']);

            if (!$user || !password_verify($data['password'], $user['password_hash'])) {
                Response::validationError(['general' => 'Invalid email or password']);
            }

            if (!$user['is_active']) {
                Response::validationError(['general' => 'Account is disabled']);
            }

            // Generate token
            $token = Auth::generateToken($user);

            // Send login notification email (best-effort)
            try {
                $appUrl  = getenv('APP_URL') ?: 'http://localhost/LCMTVWebNew/frontend';
                $subject = 'New login to your LCMTV account';
                $ip      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $agent   = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown device';
                $body    = '<p>Hello ' . htmlspecialchars($user['first_name'] ?? 'there', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ',</p>'
                         . '<p>Your LCMTV account was just used to sign in.</p>'
                         . '<ul>'
                         . '<li><strong>Time:</strong> ' . date('Y-m-d H:i:s') . '</li>'
                         . '<li><strong>IP address:</strong> ' . htmlspecialchars($ip, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>'
                         . '<li><strong>Device:</strong> ' . htmlspecialchars($agent, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>'
                         . '</ul>'
                         . '<p>If this was you, you can safely ignore this email. '
                         . 'If you did not sign in, please reset your password immediately.</p>'
                         . '<p><a href="' . htmlspecialchars($appUrl . '#/reset-password', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
                         . ' style="display:inline-block;padding:10px 18px;background:#e74c3c;color:#ffffff;'
                         . 'text-decoration:none;border-radius:4px;">Reset Password</a></p>'
                         . '<p>LCMTV Security Team</p>';
                Mailer::send($user['email'], $subject, $body);
            } catch (\Throwable $e) {
                error_log('UserController login notification email failed: ' . $e->getMessage());
            }

            Response::success([
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name']
                ],
                'token' => $token
            ], 'Login successful');

        } catch (Exception $e) {
            error_log("UserController login error: " . $e->getMessage());
            Response::error('Login failed', 500);
        }
    }

    /**
     * Request a password reset link
     */
    public function requestPasswordReset($data) {
        try {
            $email = isset($data['email']) ? trim($data['email']) : '';

            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Response::validationError(['email' => 'A valid email address is required']);
            }

            $user = User::findByEmail($email);

            // Always return a generic success message to avoid leaking which emails exist
            $genericMessage = 'If an account exists for that email, a reset link has been sent.';

            if (!$user) {
                Response::success(null, $genericMessage);
            }

            $conn = getDBConnection();

            // Ensure password_resets table exists
            $createSql = "CREATE TABLE IF NOT EXISTS password_resets (
                id INT PRIMARY KEY AUTO_INCREMENT,
                email VARCHAR(255) NOT NULL,
                token VARCHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_password_resets_email (email),
                INDEX idx_password_resets_token (token)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            $conn->query($createSql);

            // Remove any existing tokens for this email
            $stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
            if ($stmt) {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $stmt->close();
            }

            // Generate a new token
            $token     = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour

            $stmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
            if (!$stmt) {
                error_log('Failed to prepare password_resets insert: ' . $conn->error);
                Response::error('Unable to process request at this time', 500);
            }
            $stmt->bind_param("sss", $email, $token, $expiresAt);
            $stmt->execute();
            $stmt->close();

            // Build reset link
            $appUrl = getenv('APP_URL') ?: 'http://localhost/LCMTVWebNew/frontend';
            // Angular app uses hash-based routing (#/...)
            $resetUrl = rtrim($appUrl, '/') . '#/reset-password?token=' . urlencode($token);

            // Send reset email
            try {
                $subject = 'Reset your LCMTV password';
                $name    = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'there';
                $body    = '<p>Dear ' . htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ',</p>'
                         . '<p>We received a request to reset your LCMTV password.</p>'
                         . '<p>You can reset your password by clicking the button below:</p>'
                         . '<p><a href="' . htmlspecialchars($resetUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
                         . ' style="display:inline-block;padding:10px 18px;background:#6a0dad;color:#ffffff;'
                         . 'text-decoration:none;border-radius:4px;">Reset Password</a></p>'
                         . '<p>This link will expire in 1 hour. If you did not request a password reset, '
                         . 'you can safely ignore this email.</p>'
                         . '<p>Blessings,<br>LCMTV Team</p>';

                Mailer::send($email, $subject, $body);
            } catch (\Throwable $e) {
                error_log('Password reset email failed: ' . $e->getMessage());
            }

            Response::success(null, $genericMessage);
        } catch (Exception $e) {
            error_log("UserController requestPasswordReset error: " . $e->getMessage());
            Response::error('Failed to process password reset request', 500);
        }
    }

    /**
     * Reset password using a reset token
     */
    public function resetPassword($data) {
        try {
            $token    = isset($data['token']) ? trim($data['token']) : '';
            $password = $data['password'] ?? '';

            if (empty($token)) {
                Response::validationError(['token' => 'Reset token is required']);
            }

            if (empty($password) || strlen($password) < 6) {
                Response::validationError(['password' => 'Password must be at least 6 characters']);
            }

            $conn = getDBConnection();

            // Ensure table exists (in case resetPassword is called before requestPasswordReset)
            $createSql = "CREATE TABLE IF NOT EXISTS password_resets (
                id INT PRIMARY KEY AUTO_INCREMENT,
                email VARCHAR(255) NOT NULL,
                token VARCHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_password_resets_email (email),
                INDEX idx_password_resets_token (token)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            $conn->query($createSql);

            // Look up token
            $stmt = $conn->prepare("SELECT email, expires_at FROM password_resets WHERE token = ? LIMIT 1");
            if (!$stmt) {
                error_log('Failed to prepare password_resets select: ' . $conn->error);
                Response::error('Invalid or expired token', 400);
            }
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $result = $stmt->get_result();
            $row    = $result->fetch_assoc();
            $stmt->close();

            if (!$row) {
                Response::validationError(['token' => 'Invalid or expired token']);
            }

            if (strtotime($row['expires_at']) < time()) {
                // Clean up expired token
                $deleteStmt = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
                if ($deleteStmt) {
                    $deleteStmt->bind_param("s", $token);
                    $deleteStmt->execute();
                    $deleteStmt->close();
                }
                Response::validationError(['token' => 'This reset link has expired. Please request a new one.']);
            }

            $email = $row['email'];
            $user  = User::findByEmail($email);

            if (!$user) {
                Response::error('User account not found', 404);
            }

            // Update password
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt    = $conn->prepare("UPDATE users SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE email = ?");
            if (!$stmt) {
                error_log('Failed to prepare user password update: ' . $conn->error);
                Response::error('Unable to reset password at this time', 500);
            }
            $stmt->bind_param("ss", $newHash, $email);
            $stmt->execute();
            $stmt->close();

            // Delete used token
            $deleteStmt = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
            if ($deleteStmt) {
                $deleteStmt->bind_param("s", $token);
                $deleteStmt->execute();
                $deleteStmt->close();
            }

            // Send confirmation email (best-effort)
            try {
                $subject = 'Your LCMTV password was changed';
                $name    = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'there';
                $body    = '<p>Dear ' . htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ',</p>'
                         . '<p>This is a confirmation that the password for your LCMTV account has just been changed.</p>'
                         . '<p>If you did not make this change, please contact support immediately.</p>'
                         . '<p>LCMTV Security Team</p>';
                Mailer::send($email, $subject, $body);
            } catch (\Throwable $e) {
                error_log('Password reset confirmation email failed: ' . $e->getMessage());
            }

            Response::success(null, 'Your password has been reset successfully. You can now sign in with your new password.');
        } catch (Exception $e) {
            error_log("UserController resetPassword error: " . $e->getMessage());
            Response::error('Failed to reset password', 500);
        }
    }

    public function profile() {
        try {
            Auth::requireAuth();
            $user = Auth::getUserFromToken();

            if (!$user) {
                Response::unauthorized();
            }

            $userData = User::getById($user['user_id']);
            if (!$userData) {
                Response::notFound('User not found');
            }

            // Remove sensitive data
            unset($userData['password_hash']);

            Response::success($userData);
        } catch (Exception $e) {
            error_log("UserController profile error: " . $e->getMessage());
            Response::error('Failed to load profile', 500);
        }
    }

    public function updateProfile() {
        try {
            Auth::requireAuth();
            $user = Auth::getUserFromToken();

            if (!$user) {
                Response::unauthorized();
            }

            $data = json_decode(file_get_contents('php://input'), true);

            // Validate input
            $updateData = [];
            if (isset($data['first_name'])) {
                $updateData['first_name'] = trim($data['first_name']);
            }
            if (isset($data['last_name'])) {
                $updateData['last_name'] = trim($data['last_name']);
            }

            if (empty($updateData)) {
                Response::badRequest('No valid fields to update');
            }

            if (User::updateProfile($user['user_id'], $updateData)) {
                // Return updated user data
                $updatedUser = User::getById($user['user_id']);
                if ($updatedUser) {
                    unset($updatedUser['password_hash']);
                    Response::success($updatedUser, 'Profile updated successfully');
                } else {
                    Response::error('Profile updated but failed to retrieve data', 500);
                }
            } else {
                Response::error('Failed to update profile', 500);
            }
        } catch (Exception $e) {
            error_log("UserController updateProfile error: " . $e->getMessage());
            Response::error('Failed to update profile', 500);
        }
    }

    public function updatePassword() {
        try {
            Auth::requireAuth();
            $user = Auth::getUserFromToken();

            if (!$user) {
                Response::unauthorized();
            }

            $data = json_decode(file_get_contents('php://input'), true);

            // Validate input
            if (empty($data['current_password'])) {
                Response::validationError(['current_password' => 'Current password is required']);
            }

            if (empty($data['new_password'])) {
                Response::validationError(['new_password' => 'New password is required']);
            }

            if (strlen($data['new_password']) < 6) {
                Response::validationError(['new_password' => 'New password must be at least 6 characters']);
            }

            // Update password
            if (User::updatePassword($user['user_id'], $data['current_password'], $data['new_password'])) {
                Response::success(null, 'Password updated successfully');
            } else {
                Response::error('Current password is incorrect', 400);
            }
        } catch (Exception $e) {
            error_log("UserController updatePassword error: " . $e->getMessage());
            Response::error('Failed to update password', 500);
        }
    }

    public function history() {
        try {
            Auth::requireAuth();
            $user = Auth::getUserFromToken();

            if (!$user) {
                Response::unauthorized();
            }

            $limit = (int)($_GET['limit'] ?? 50);
            $history = User::getWatchHistory($user['user_id'], $limit);

            Response::success($history);
        } catch (Exception $e) {
            error_log("UserController history error: " . $e->getMessage());
            Response::error('Failed to load watch history', 500);
        }
    }

    /**
     * Add an entry to the authenticated user's watch history
     */
    public function addHistory() {
        try {
            Auth::requireAuth();
            $user = Auth::getUserFromToken();

            if (!$user) {
                Response::unauthorized();
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $videoId = (int)($data['video_id'] ?? 0);

            if (!$videoId) {
                Response::badRequest('Video ID is required');
            }

            if (User::updateWatchHistory($user['user_id'], $videoId)) {
                Response::success(['message' => 'Watch history updated']);
            } else {
                Response::error('Failed to update watch history', 500);
            }
        } catch (Exception $e) {
            error_log("UserController addHistory error: " . $e->getMessage());
            Response::error('Failed to update watch history', 500);
        }
    }

    /**
     * Clear the authenticated user's watch history
     */
    public function clearHistory() {
        try {
            Auth::requireAuth();
            $user = Auth::getUserFromToken();

            if (!$user) {
                Response::unauthorized();
            }

            if (User::clearWatchHistory($user['user_id'])) {
                Response::success(['message' => 'Watch history cleared']);
            } else {
                Response::error('Failed to clear watch history', 500);
            }
        } catch (Exception $e) {
            error_log("UserController clearHistory error: " . $e->getMessage());
            Response::error('Failed to clear watch history', 500);
        }
    }

    /**
     * Remove a specific video from the authenticated user's watch history
     */
    public function removeHistoryItem($videoId) {
        try {
            Auth::requireAuth();
            $user = Auth::getUserFromToken();

            if (!$user) {
                Response::unauthorized();
            }

            $videoId = (int)$videoId;
            if (!$videoId) {
                Response::badRequest('Video ID is required');
            }

            if (User::removeFromWatchHistory($user['user_id'], $videoId)) {
                Response::success(['message' => 'History item removed']);
            } else {
                Response::error('Failed to remove history item', 500);
            }
        } catch (Exception $e) {
            error_log("UserController removeHistoryItem error: " . $e->getMessage());
            Response::error('Failed to remove history item', 500);
        }
    }

    public function getFavorites() {
        try {
            Auth::requireAuth();
            $user = Auth::getUserFromToken();

            if (!$user) {
                Response::unauthorized();
            }

            $favorites = User::getFavorites($user['user_id']);
            Response::success($favorites);
        } catch (Exception $e) {
            error_log("UserController getFavorites error: " . $e->getMessage());
            Response::error('Failed to load favorites', 500);
        }
    }

    public function addToFavorites() {
        try {
            Auth::requireAuth();
            $user = Auth::getUserFromToken();

            if (!$user) {
                Response::unauthorized();
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $videoId = (int)($data['video_id'] ?? 0);

            if (!$videoId) {
                Response::badRequest('Video ID is required');
            }

            if (User::addToFavorites($user['user_id'], $videoId)) {
                // Create notifications for users who also favorited this video
                try {
                    Notification::notifyVideoFavorited($videoId, $user['user_id']);
                } catch (Exception $e) {
                    error_log("Failed to create favorite notifications: " . $e->getMessage());
                    // Don't fail the main operation if notifications fail
                }

                Response::success(['message' => 'Added to favorites']);
            } else {
                Response::error('Failed to add to favorites', 500);
            }
        } catch (Exception $e) {
            error_log("UserController addToFavorites error: " . $e->getMessage());
            Response::error('Failed to add to favorites', 500);
        }
    }

    public function removeFromFavorites() {
        try {
            Auth::requireAuth();
            $user = Auth::getUserFromToken();

            if (!$user) {
                Response::unauthorized();
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $videoId = (int)($data['video_id'] ?? 0);

            if (!$videoId) {
                Response::badRequest('Video ID is required');
            }

            if (User::removeFromFavorites($user['user_id'], $videoId)) {
                Response::success(['message' => 'Removed from favorites']);
            } else {
                Response::error('Failed to remove from favorites', 500);
            }
        } catch (Exception $e) {
            error_log("UserController removeFromFavorites error: " . $e->getMessage());
            Response::error('Failed to remove from favorites', 500);
        }
    }

    public function getMyChannels() {
        try {
            Auth::requireAuth();
            $user = Auth::getUserFromToken();

            if (!$user) {
                Response::unauthorized();
            }

            $channels = User::getMyChannels($user['user_id']);
            Response::success($channels);
        } catch (Exception $e) {
            error_log("UserController getMyChannels error: " . $e->getMessage());
            Response::error('Failed to load my channels', 500);
        }
    }

    public function addToMyChannels() {
        try {
            Auth::requireAuth();
            $user = Auth::getUserFromToken();

            if (!$user) {
                Response::unauthorized();
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $categoryId = (int)($data['category_id'] ?? 0);

            if (!$categoryId) {
                Response::badRequest('Category ID is required');
            }

            if (User::addToMyChannels($user['user_id'], $categoryId)) {
                Response::success(['message' => 'Added to my channels']);
            } else {
                Response::error('Failed to add to my channels', 500);
            }
        } catch (Exception $e) {
            error_log("UserController addToMyChannels error: " . $e->getMessage());
            Response::error('Failed to add to my channels', 500);
        }
    }

    public function removeFromMyChannels() {
        try {
            Auth::requireAuth();
            $user = Auth::getUserFromToken();

            if (!$user) {
                Response::unauthorized();
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $categoryId = (int)($data['category_id'] ?? 0);

            if (!$categoryId) {
                Response::badRequest('Category ID is required');
            }

            if (User::removeFromMyChannels($user['user_id'], $categoryId)) {
                Response::success(['message' => 'Removed from my channels']);
            } else {
                Response::error('Failed to remove from my channels', 500);
            }
        } catch (Exception $e) {
            error_log("UserController removeFromMyChannels error: " . $e->getMessage());
            Response::error('Failed to remove from my channels', 500);
        }
    }
}
?>
