<?php
/**
 * User Controller
 * Handles user authentication and profile management
 */

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Notification.php';

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
