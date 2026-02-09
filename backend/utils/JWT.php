<?php
/**
 * JWT Utility Class
 * Wrapper for Auth JWT functions
 */

require_once __DIR__ . '/Auth.php';

class JWT {
    /**
     * Encode JWT token
     */
    public static function encode($payload) {
        return Auth::generateToken($payload);
    }

    /**
     * Decode JWT token
     */
    public static function decode($token) {
        return Auth::verifyToken($token);
    }

    /**
     * Get user from JWT token
     */
    public static function getUserFromToken($token) {
        return Auth::getUserFromToken($token);
    }
}
?>