<?php
/**
 * YouTube API Key Configuration Helper
 */

if ($argc < 2) {
    echo "Usage: php configure_youtube_api.php YOUR_API_KEY\n";
    echo "Example: php configure_youtube_api.php AIzaSyD1234567890abcdefghijklmnopqrstuv\n";
    exit(1);
}

$apiKey = $argv[1];

// Validate API key format (should be around 39 characters, starting with AIza)
if (strlen($apiKey) < 30 || !preg_match('/^AIza/', $apiKey)) {
    echo "Warning: API key format doesn't look correct. YouTube API keys typically:\n";
    echo "- Are 39 characters long\n";
    echo "- Start with 'AIza'\n";
    echo "- Contain letters, numbers, underscores, and hyphens\n\n";
    echo "Continue anyway? (y/N): ";
    
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    
    if (trim(strtolower($line)) !== 'y') {
        echo "Configuration cancelled.\n";
        exit(1);
    }
}

$configFile = __DIR__ . '/.env';

if (!file_exists($configFile)) {
    echo "Error: .env file not found at $configFile\n";
    exit(1);
}

$content = file_get_contents($configFile);
$updatedContent = preg_replace(
    '/YOUTUBE_API_KEY=.*$/m',
    "YOUTUBE_API_KEY=$apiKey",
    $content
);

if ($updatedContent !== $content) {
    if (file_put_contents($configFile, $updatedContent)) {
        echo "✓ Successfully updated YouTube API key in .env file\n";
        echo "New API Key: " . substr($apiKey, 0, 10) . "...\n\n";
        
        echo "Testing the new API key...\n";
        require_once 'utils/YouTubeAPI.php';
        
        try {
            $youtube = new YouTubeAPI();
            $testKey = $youtube->getApiKey();
            
            if ($testKey === $apiKey) {
                echo "✓ API key loaded successfully\n";
                
                // Test with a simple API call
                $videoDetails = $youtube->getVideoDetails(['R1KfiyJ0c9U']);
                if (!empty($videoDetails)) {
                    echo "✓ API connection test PASSED\n";
                    echo "Video found: " . $videoDetails[0]['title'] . "\n";
                } else {
                    echo "! API key accepted but no video data returned\n";
                    echo "  This might be due to video privacy settings or quota limits\n";
                }
            } else {
                echo "✗ API key not loaded correctly\n";
            }
        } catch (Exception $e) {
            echo "✗ API test failed: " . $e->getMessage() . "\n";
        }
        
    } else {
        echo "✗ Failed to update .env file\n";
        exit(1);
    }
} else {
    echo "No changes made - API key line not found in .env file\n";
}

echo "\nConfiguration complete!\n";
?>