<?php
/**
 * Google Cloud Translation Service
 * Professional translation service integration
 */

require_once __DIR__ . '/../utils/EnvLoader.php';

class GoogleTranslateService {
    private $apiKey;
    private $apiUrl;
    private $cache;
    
    public function __construct() {
        $this->apiKey = getenv('GOOGLE_TRANSLATE_API_KEY');
        $this->apiUrl = 'https://translation.googleapis.com/language/translate/v2';
        $this->cache = [];
    }
    
    /**
     * Check if service is configured
     */
    public function isConfigured() {
        return !empty($this->apiKey);
    }
    
    /**
     * Translate text using Google Translate API
     */
    public function translate($text, $targetLanguage, $sourceLanguage = 'en') {
        // Validate inputs
        if (empty($text) || empty($targetLanguage)) {
            return $text;
        }
        
        // Don't translate if source and target are the same
        if ($sourceLanguage === $targetLanguage) {
            return $text;
        }
        
        // Check cache first
        $cacheKey = md5($text . $sourceLanguage . $targetLanguage);
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }
        
        try {
            // Prepare request data
            $data = [
                'q' => $text,
                'source' => $sourceLanguage,
                'target' => $targetLanguage,
                'format' => 'text'
            ];
            
            // Make API request
            $response = $this->makeApiRequest($data);
            
            if ($response && isset($response['data']['translations'][0]['translatedText'])) {
                $translatedText = $response['data']['translations'][0]['translatedText'];
                
                // Cache the result
                $this->cache[$cacheKey] = $translatedText;
                
                return $translatedText;
            }
            
            // Fallback to original text if translation fails
            error_log("Google Translate API failed for text: " . substr($text, 0, 50) . "...");
            return $text;
            
        } catch (Exception $e) {
            error_log("Translation error: " . $e->getMessage());
            return $text; // Return original text on error
        }
    }
    
    /**
     * Translate multiple texts at once
     */
    public function translateBatch($texts, $targetLanguage, $sourceLanguage = 'en') {
        if (empty($texts) || empty($targetLanguage)) {
            return $texts;
        }
        
        if ($sourceLanguage === $targetLanguage) {
            return $texts;
        }
        
        try {
            // Prepare request data
            $data = [
                'q' => $texts,
                'source' => $sourceLanguage,
                'target' => $targetLanguage,
                'format' => 'text'
            ];
            
            // Make API request
            $response = $this->makeApiRequest($data);
            
            if ($response && isset($response['data']['translations'])) {
                $translations = [];
                foreach ($response['data']['translations'] as $index => $translation) {
                    $translations[] = $translation['translatedText'];
                }
                return $translations;
            }
            
            return $texts; // Fallback to original texts
            
        } catch (Exception $e) {
            error_log("Batch translation error: " . $e->getMessage());
            return $texts;
        }
    }
    
    /**
     * Detect language of text
     */
    public function detectLanguage($text) {
        if (empty($text)) {
            return 'en';
        }
        
        try {
            $url = $this->apiUrl . '/detect?key=' . $this->apiKey;
            $data = ['q' => $text];
            
            $options = [
                'http' => [
                    'header' => "Content-Type: application/json\r\n",
                    'method' => 'POST',
                    'content' => json_encode($data)
                ]
            ];
            
            $context = stream_context_create($options);
            $result = file_get_contents($url, false, $context);
            
            if ($result === false) {
                throw new Exception('API request failed');
            }
            
            $response = json_decode($result, true);
            
            if (isset($response['data']['detections'][0][0]['language'])) {
                return $response['data']['detections'][0][0]['language'];
            }
            
            return 'en'; // Default fallback
            
        } catch (Exception $e) {
            error_log("Language detection error: " . $e->getMessage());
            return 'en';
        }
    }
    
    /**
     * Get supported languages
     */
    public function getSupportedLanguages($targetLanguage = 'en') {
        try {
            $url = $this->apiUrl . '/languages?key=' . $this->apiKey . '&target=' . $targetLanguage;
            
            $result = file_get_contents($url);
            if ($result === false) {
                throw new Exception('API request failed');
            }
            
            $response = json_decode($result, true);
            
            if (isset($response['data']['languages'])) {
                return $response['data']['languages'];
            }
            
            return []; // Return empty array on failure
            
        } catch (Exception $e) {
            error_log("Get languages error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Make API request to Google Translate
     */
    private function makeApiRequest($data) {
        if (!$this->isConfigured()) {
            throw new Exception('Google Translate API key not configured');
        }
        
        $url = $this->apiUrl . '?key=' . $this->apiKey;
        
        $options = [
            'http' => [
                'header' => "Content-Type: application/json\r\n",
                'method' => 'POST',
                'content' => json_encode($data),
                'timeout' => 10
            ]
        ];
        
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        
        if ($result === false) {
            throw new Exception('Google Translate API request failed');
        }
        
        $response = json_decode($result, true);
        
        if (isset($response['error'])) {
            throw new Exception('Google Translate API error: ' . $response['error']['message']);
        }
        
        return $response;
    }
    
    /**
     * Clear translation cache
     */
    public function clearCache() {
        $this->cache = [];
    }
    
    /**
     * Get cache statistics
     */
    public function getCacheStats() {
        return [
            'cache_size' => count($this->cache),
            'is_configured' => $this->isConfigured()
        ];
    }
}
?>