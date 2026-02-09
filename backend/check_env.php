<?php
require_once 'utils/EnvLoader.php';

echo "Environment Variables:\n";
echo "=====================\n";
echo "YOUTUBE_API_KEY: " . getenv('YOUTUBE_API_KEY') . "\n";
echo "DB_HOST: " . getenv('DB_HOST') . "\n";
echo "APP_ENV: " . getenv('APP_ENV') . "\n";

echo "\nAll ENV vars:\n";
foreach ($_ENV as $key => $value) {
    if (strpos($key, 'YOUTUBE') !== false || strpos($key, 'API') !== false) {
        echo "$key: $value\n";
    }
}
?>