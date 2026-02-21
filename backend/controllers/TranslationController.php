<?php
/**
 * Translation Controller
 * Handles translation API requests
 */

require_once __DIR__ . '/../services/GoogleTranslateService.php';
require_once __DIR__ . '/../utils/Response.php';

class TranslationController {
    private $translateService;
    
    public function __construct() {
        $this->translateService = new GoogleTranslateService();
    }
    
    /**
     * Translate single text
     */
    public function translate() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                Response::error('Invalid JSON input', 400);
                return;
            }
            
            $text = $input['text'] ?? '';
            $targetLanguage = $input['target'] ?? 'en';
            $sourceLanguage = $input['source'] ?? 'en';
            
            if (empty($text)) {
                Response::error('Text is required', 400);
                return;
            }
            
            if (empty($targetLanguage)) {
                Response::error('Target language is required', 400);
                return;
            }
            
            $translatedText = $this->translateService->translate($text, $targetLanguage, $sourceLanguage);
            
            Response::success([
                'original_text' => $text,
                'translated_text' => $translatedText,
                'source_language' => $sourceLanguage,
                'target_language' => $targetLanguage,
                'service' => 'google_translate'
            ]);
            
        } catch (Exception $e) {
            error_log("Translation error: " . $e->getMessage());
            Response::error('Translation failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Translate multiple texts
     */
    public function translateBatch() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                Response::error('Invalid JSON input', 400);
                return;
            }
            
            $texts = $input['texts'] ?? [];
            $targetLanguage = $input['target'] ?? 'en';
            $sourceLanguage = $input['source'] ?? 'en';
            
            if (empty($texts)) {
                Response::error('Texts array is required', 400);
                return;
            }
            
            if (empty($targetLanguage)) {
                Response::error('Target language is required', 400);
                return;
            }
            
            $translatedTexts = $this->translateService->translateBatch($texts, $targetLanguage, $sourceLanguage);
            
            Response::success([
                'original_texts' => $texts,
                'translated_texts' => $translatedTexts,
                'source_language' => $sourceLanguage,
                'target_language' => $targetLanguage,
                'service' => 'google_translate',
                'count' => count($texts)
            ]);
            
        } catch (Exception $e) {
            error_log("Batch translation error: " . $e->getMessage());
            Response::error('Batch translation failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Detect language of text
     */
    public function detectLanguage() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                Response::error('Invalid JSON input', 400);
                return;
            }
            
            $text = $input['text'] ?? '';
            
            if (empty($text)) {
                Response::error('Text is required', 400);
                return;
            }
            
            $detectedLanguage = $this->translateService->detectLanguage($text);
            
            Response::success([
                'text' => $text,
                'detected_language' => $detectedLanguage,
                'service' => 'google_translate'
            ]);
            
        } catch (Exception $e) {
            error_log("Language detection error: " . $e->getMessage());
            Response::error('Language detection failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get supported languages
     */
    public function getLanguages() {
        try {
            $targetLanguage = $_GET['target'] ?? 'en';
            
            $languages = $this->translateService->getSupportedLanguages($targetLanguage);
            
            Response::success([
                'languages' => $languages,
                'target_language' => $targetLanguage,
                'service' => 'google_translate',
                'count' => count($languages)
            ]);
            
        } catch (Exception $e) {
            error_log("Get languages error: " . $e->getMessage());
            Response::error('Failed to get supported languages: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get service status
     */
    public function getStatus() {
        try {
            $isConfigured = $this->translateService->isConfigured();
            $cacheStats = $this->translateService->getCacheStats();
            
            Response::success([
                'service' => 'google_translate',
                'configured' => $isConfigured,
                'cache_stats' => $cacheStats,
                'api_endpoint' => '/api/translate'
            ]);
            
        } catch (Exception $e) {
            Response::error('Failed to get service status: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Clear translation cache
     */
    public function clearCache() {
        try {
            $this->translateService->clearCache();
            
            Response::success([
                'message' => 'Translation cache cleared successfully',
                'service' => 'google_translate'
            ]);
            
        } catch (Exception $e) {
            error_log("Clear cache error: " . $e->getMessage());
            Response::error('Failed to clear cache: ' . $e->getMessage(), 500);
        }
    }
}
?>