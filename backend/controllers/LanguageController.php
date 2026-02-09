<?php
/**
 * Language Controller
 * Handles language management and translation APIs
 */

require_once __DIR__ . '/../models/Language.php';
require_once __DIR__ . '/../utils/Auth.php';
require_once __DIR__ . '/../utils/Response.php';

class LanguageController {
    /**
     * Get all supported languages
     */
    public static function getLanguages() {
        try {
            $languages = Language::getAll();

            // Format for frontend
            $formatted = array_map(function($lang) {
                return [
                    'code' => $lang['code'],
                    'name' => $lang['name'],
                    'nativeName' => $lang['native_name'],
                    'flag' => $lang['flag_emoji'],
                    'rtl' => (bool)$lang['is_rtl'],
                    'active' => (bool)$lang['is_active']
                ];
            }, $languages);

            return Response::success([
                'languages' => $formatted,
                'total' => count($formatted)
            ]);

        } catch (Exception $e) {
            error_log("LanguageController::getLanguages - Error: " . $e->getMessage());
            return Response::error('Failed to get languages: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get translations for a specific language
     */
    public static function getTranslations() {
        try {
            $languageCode = $_GET['lang'] ?? 'en';

            $translations = Language::getTranslations($languageCode);

            return Response::success([
                'language' => $languageCode,
                'translations' => $translations,
                'count' => count($translations)
            ]);

        } catch (Exception $e) {
            error_log("LanguageController::getTranslations - Error: " . $e->getMessage());
            return Response::error('Failed to get translations: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Detect user's preferred language
     */
    public static function detectLanguage() {
        try {
            $detected = Language::detectFromHeaders();

            return Response::success([
                'detected_language' => $detected,
                'supported' => Language::findByCode($detected) !== null
            ]);

        } catch (Exception $e) {
            error_log("LanguageController::detectLanguage - Error: " . $e->getMessage());
            return Response::error('Failed to detect language: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get language statistics (admin only)
     */
    public static function getStats() {
        try {
            // Require admin authentication
            Auth::requireAuth();
            $user = Auth::getUserFromToken();

            if (!$user || $user['role'] !== 'admin') {
                return Response::forbidden('Admin access required');
            }

            $stats = Language::getStats();

            return Response::success($stats);

        } catch (Exception $e) {
            error_log("LanguageController::getStats - Error: " . $e->getMessage());
            return Response::error('Failed to get language stats: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update or create translation (admin only)
     */
    public static function setTranslation() {
        try {
            // Require admin authentication
            Auth::requireAuth();
            $user = Auth::getUserFromToken();

            if (!$user || $user['role'] !== 'admin') {
                return Response::forbidden('Admin access required');
            }

            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return Response::error('Invalid JSON data', 400);
            }

            $languageCode = $data['language_code'] ?? '';
            $key = $data['key'] ?? '';
            $text = $data['text'] ?? '';
            $category = $data['category'] ?? 'general';

            if (empty($languageCode) || empty($key) || !isset($text)) {
                return Response::error('Missing required fields: language_code, key, text', 400);
            }

            $success = Language::setTranslation($languageCode, $key, $text, $category);

            if ($success) {
                return Response::success([
                    'message' => 'Translation updated successfully',
                    'language_code' => $languageCode,
                    'key' => $key,
                    'text' => $text
                ]);
            } else {
                return Response::error('Failed to update translation', 500);
            }

        } catch (Exception $e) {
            error_log("LanguageController::setTranslation - Error: " . $e->getMessage());
            return Response::error('Failed to update translation: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get missing translations for a language (admin only)
     */
    public static function getMissingTranslations() {
        try {
            // Require admin authentication
            Auth::requireAuth();
            $user = Auth::getUserFromToken();

            if (!$user || $user['role'] !== 'admin') {
                return Response::forbidden('Admin access required');
            }

            $languageCode = $_GET['lang'] ?? '';

            if (empty($languageCode)) {
                return Response::error('Language code is required', 400);
            }

            $missing = Language::getMissingTranslations($languageCode);

            return Response::success([
                'language_code' => $languageCode,
                'missing_translations' => $missing,
                'count' => count($missing)
            ]);

        } catch (Exception $e) {
            error_log("LanguageController::getMissingTranslations - Error: " . $e->getMessage());
            return Response::error('Failed to get missing translations: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Import translations from JSON (admin only)
     */
    public static function importTranslations() {
        try {
            // Require admin authentication
            Auth::requireAuth();
            $user = Auth::getUserFromToken();

            if (!$user || $user['role'] !== 'admin') {
                return Response::forbidden('Admin access required');
            }

            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return Response::error('Invalid JSON data', 400);
            }

            $languageCode = $data['language_code'] ?? '';
            $translations = $data['translations'] ?? [];

            if (empty($languageCode) || empty($translations)) {
                return Response::error('Missing required fields: language_code, translations', 400);
            }

            $result = Language::importTranslations($languageCode, $translations);

            return Response::success([
                'message' => 'Translations imported successfully',
                'language_code' => $languageCode,
                'imported' => $result['success'],
                'errors' => $result['errors']
            ]);

        } catch (Exception $e) {
            error_log("LanguageController::importTranslations - Error: " . $e->getMessage());
            return Response::error('Failed to import translations: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get translation categories
     */
    public static function getCategories() {
        try {
            $categories = Language::getCategories();

            return Response::success([
                'categories' => $categories,
                'count' => count($categories)
            ]);

        } catch (Exception $e) {
            error_log("LanguageController::getCategories - Error: " . $e->getMessage());
            return Response::error('Failed to get categories: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Set content translation
     */
    public static function setContentTranslation() {
        try {
            // Require admin authentication
            Auth::requireAuth();
            $user = Auth::getUserFromToken();

            if (!$user || $user['role'] !== 'admin') {
                return Response::forbidden('Admin access required');
            }

            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return Response::error('Invalid JSON data', 400);
            }

            $contentType = $data['content_type'] ?? '';
            $contentId = $data['content_id'] ?? '';
            $languageCode = $data['language_code'] ?? '';
            $fieldName = $data['field_name'] ?? '';
            $translatedText = $data['translated_text'] ?? '';

            if (empty($contentType) || empty($contentId) || empty($languageCode) || empty($fieldName)) {
                return Response::error('Missing required fields', 400);
            }

            $conn = getDBConnection();

            // Get language ID
            $langResult = $conn->query("SELECT id FROM languages WHERE code = '$languageCode'");
            $language = $langResult->fetch_assoc();

            if (!$language) {
                return Response::error('Language not found', 404);
            }

            // Check if translation exists
            $checkSql = "SELECT id FROM content_translations
                        WHERE content_type = ? AND content_id = ? AND language_id = ? AND field_name = ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bind_param("siis", $contentType, $contentId, $language['id'], $fieldName);
            $checkStmt->execute();

            if ($checkStmt->get_result()->num_rows > 0) {
                // Update existing
                $sql = "UPDATE content_translations
                       SET translated_text = ?, updated_at = NOW()
                       WHERE content_type = ? AND content_id = ? AND language_id = ? AND field_name = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssiis", $translatedText, $contentType, $contentId, $language['id'], $fieldName);
            } else {
                // Create new
                $sql = "INSERT INTO content_translations
                       (content_type, content_id, language_id, field_name, translated_text)
                       VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("siiss", $contentType, $contentId, $language['id'], $fieldName, $translatedText);
            }

            if ($stmt->execute()) {
                return Response::success([
                    'message' => 'Content translation saved successfully',
                    'content_type' => $contentType,
                    'content_id' => $contentId,
                    'language_code' => $languageCode,
                    'field_name' => $fieldName
                ]);
            } else {
                return Response::error('Failed to save content translation', 500);
            }

        } catch (Exception $e) {
            error_log("LanguageController::setContentTranslation - Error: " . $e->getMessage());
            return Response::error('Failed to save content translation: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get content translations
     */
    public static function getContentTranslations() {
        try {
            $contentType = $_GET['content_type'] ?? '';
            $contentId = $_GET['content_id'] ?? '';
            $languageCode = $_GET['lang'] ?? '';

            if (empty($contentType) || empty($contentId)) {
                return Response::error('Missing required parameters: content_type, content_id', 400);
            }

            $conn = getDBConnection();

            $whereClause = "ct.content_type = ? AND ct.content_id = ?";
            $params = [$contentType, $contentId];
            $types = "si";

            if (!empty($languageCode)) {
                $whereClause .= " AND l.code = ?";
                $params[] = $languageCode;
                $types .= "s";
            }

            $sql = "SELECT ct.*, l.code as language_code, l.name as language_name
                   FROM content_translations ct
                   JOIN languages l ON ct.language_id = l.id
                   WHERE {$whereClause} AND l.is_active = 1
                   ORDER BY l.sort_order";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();

            $result = $stmt->get_result();
            $translations = [];

            while ($row = $result->fetch_assoc()) {
                $translations[] = $row;
            }

            return Response::success([
                'content_type' => $contentType,
                'content_id' => $contentId,
                'translations' => $translations,
                'count' => count($translations)
            ]);

        } catch (Exception $e) {
            error_log("LanguageController::getContentTranslations - Error: " . $e->getMessage());
            return Response::error('Failed to get content translations: ' . $e->getMessage(), 500);
        }
    }
}
?>