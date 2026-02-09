<?php
/**
 * API Response Utility Class
 * Handles standardized JSON responses
 */

class Response {
    /**
     * Send success response
     */
    public static function success($data = null, $message = 'Success', $statusCode = 200) {
        http_response_code($statusCode);
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('c')
        ]);
        exit();
    }

    /**
     * Send error response
     */
    public static function error($message = 'An error occurred', $statusCode = 400) {
        http_response_code($statusCode);
        echo json_encode([
            'success' => false,
            'message' => $message,
            'timestamp' => date('c')
        ]);
        exit();
    }

    /**
     * Send not found response
     */
    public static function notFound($message = 'Resource not found') {
        self::error($message, 404);
    }

    /**
     * Send unauthorized response
     */
    public static function unauthorized($message = 'Unauthorized access') {
        self::error($message, 401);
    }

    /**
     * Send forbidden response
     */
    public static function forbidden($message = 'Access forbidden') {
        self::error($message, 403);
    }

    /**
     * Send method not allowed response
     */
    public static function methodNotAllowed($message = 'Method not allowed') {
        self::error($message, 405);
    }

    /**
     * Send bad request response
     */
    public static function badRequest($message = 'Bad request') {
        self::error($message, 400);
    }

    /**
     * Send validation error response
     */
    public static function validationError($errors = [], $message = 'Validation failed') {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'timestamp' => date('c')
        ]);
        exit();
    }
}
?>
