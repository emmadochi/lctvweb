<?php
/**
 * Authentication Utility Class
 * Handles JWT tokens and user authentication
 */

class Auth {
    private static $secretKey = 'lcmtv_secret_key_2024'; // TODO: Move to environment variable

    /**
     * Generate JWT token
     */
    public static function generateToken($userData) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode([
            'user_id' => $userData['id'],
            'email' => $userData['email'],
            'role' => $userData['role'] ?? 'user',
            'iat' => time(),
            'exp' => time() + (24 * 60 * 60) // 24 hours
        ]);

        $headerEncoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $payloadEncoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

        $signature = hash_hmac('sha256', $headerEncoded . "." . $payloadEncoded, self::$secretKey, true);
        $signatureEncoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        return $headerEncoded . "." . $payloadEncoded . "." . $signatureEncoded;
    }

    /**
     * Verify JWT token
     */
    public static function verifyToken($token) {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return false;
        }

        $header = $parts[0];
        $payload = $parts[1];
        $signature = $parts[2];

        $expectedSignature = hash_hmac('sha256', $header . "." . $payload, self::$secretKey, true);
        $expectedSignatureEncoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($expectedSignature));

        if ($signature !== $expectedSignatureEncoded) {
            return false;
        }

        $payloadDecoded = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $payload)), true);

        if ($payloadDecoded['exp'] < time()) {
            return false;
        }

        return $payloadDecoded;
    }

    /**
     * Get user from token
     */
    public static function getUserFromToken() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';

        if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return null;
        }

        $token = $matches[1];
        return self::verifyToken($token);
    }

    /**
     * Check if user is authenticated
     */
    public static function isAuthenticated() {
        return self::getUserFromToken() !== null;
    }

    /**
     * Get the current authenticated user's ID, or null if not authenticated
     */
    public static function getCurrentUserId() {
        $user = self::getUserFromToken();
        if (!$user || !isset($user['user_id'])) {
            return null;
        }
        return (int)$user['user_id'];
    }

    /**
     * Check if user has admin role
     */
    public static function isAdmin() {
        $user = self::getUserFromToken();
        return $user && ($user['role'] === 'admin' || $user['role'] === 'super_admin');
    }

    /**
     * Require authentication
     */
    public static function requireAuth() {
        if (!self::isAuthenticated()) {
            Response::unauthorized();
        }
    }

    /**
     * Require admin role
     */
    public static function requireAdmin() {
        self::requireAuth();

        if (!self::isAdmin()) {
            Response::forbidden('Admin access required');
        }
    }
}
?>
