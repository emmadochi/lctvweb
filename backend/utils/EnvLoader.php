<?php
/**
 * Environment Variable Loader
 * Loads environment variables from .env file
 */

class EnvLoader {
    /**
     * Load environment variables from .env file
     */
    public static function load($filePath = null) {
        if ($filePath === null) {
            $filePath = __DIR__ . '/../.env';
        }

        if (!file_exists($filePath)) {
            return false;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments and empty lines
            if (strpos(trim($line), '#') === 0 || trim($line) === '') {
                continue;
            }

            // Parse key=value pairs
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                if ((startsWith($value, '"') && endsWith($value, '"')) ||
                    (startsWith($value, "'") && endsWith($value, "'"))) {
                    $value = substr($value, 1, -1);
                }
                
                // Set environment variable if not already set
                if (!getenv($key)) {
                    putenv("$key=$value");
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                }
            }
        }

        return true;
    }
}

// Helper functions
if (!function_exists('startsWith')) {
    function startsWith($haystack, $needle) {
        return substr($haystack, 0, strlen($needle)) === $needle;
    }
}

if (!function_exists('endsWith')) {
    function endsWith($haystack, $needle) {
        return substr($haystack, -strlen($needle)) === $needle;
    }
}

// Auto-load environment variables
EnvLoader::load();
?>