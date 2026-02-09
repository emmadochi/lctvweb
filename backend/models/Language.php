<?php
/**
 * Language Model
 * Manages supported languages and translations
 */

require_once __DIR__ . '/../config/database.php';

class Language {
    private static $languagesTable = 'languages';
    private static $translationsTable = 'translations';

    /**
     * Get all supported languages
     */
    public static function getAll() {
        $conn = getDBConnection();

        $sql = "SELECT * FROM " . self::$languagesTable . " WHERE is_active = 1 ORDER BY sort_order, name";
        $result = $conn->query($sql);

        $languages = [];
        while ($row = $result->fetch_assoc()) {
            $languages[] = $row;
        }

        return $languages;
    }

    /**
     * Get language by code
     */
    public static function findByCode($code) {
        $conn = getDBConnection();

        $sql = "SELECT * FROM " . self::$languagesTable . " WHERE code = ? AND is_active = 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $code);
        $stmt->execute();

        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    /**
     * Get translations for a language
     */
    public static function getTranslations($languageCode) {
        $conn = getDBConnection();

        $sql = "SELECT t.*, l.name as language_name
                FROM " . self::$translationsTable . " t
                JOIN " . self::$languagesTable . " l ON t.language_id = l.id
                WHERE l.code = ? AND t.is_active = 1
                ORDER BY t.translation_key";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $languageCode);
        $stmt->execute();

        $result = $stmt->get_result();
        $translations = [];

        while ($row = $result->fetch_assoc()) {
            $translations[$row['translation_key']] = $row['translation_text'];
        }

        return $translations;
    }

    /**
     * Update or create translation
     */
    public static function setTranslation($languageCode, $key, $text, $category = 'general') {
        $conn = getDBConnection();

        // Get language ID
        $language = self::findByCode($languageCode);
        if (!$language) {
            return false;
        }

        // Check if translation exists
        $sql = "SELECT id FROM " . self::$translationsTable . "
                WHERE language_id = ? AND translation_key = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $language['id'], $key);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Update existing
            $sql = "UPDATE " . self::$translationsTable . "
                    SET translation_text = ?, category = ?, updated_at = NOW()
                    WHERE language_id = ? AND translation_key = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssis", $text, $category, $language['id'], $key);
        } else {
            // Create new
            $sql = "INSERT INTO " . self::$translationsTable . "
                    (language_id, translation_key, translation_text, category, is_active, created_at)
                    VALUES (?, ?, ?, ?, 1, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isss", $language['id'], $key, $text, $category);
        }

        return $stmt->execute();
    }

    /**
     * Get translation categories
     */
    public static function getCategories() {
        $conn = getDBConnection();

        $sql = "SELECT DISTINCT category FROM " . self::$translationsTable . "
                WHERE is_active = 1 ORDER BY category";
        $result = $conn->query($sql);

        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row['category'];
        }

        return $categories;
    }

    /**
     * Get missing translations for a language
     */
    public static function getMissingTranslations($languageCode) {
        $conn = getDBConnection();

        // Get all translation keys from default language (English)
        $defaultLang = self::findByCode('en');
        if (!$defaultLang) return [];

        $sql = "SELECT t1.translation_key, t1.translation_text as default_text, t1.category
                FROM " . self::$translationsTable . " t1
                LEFT JOIN " . self::$translationsTable . " t2 ON t1.translation_key = t2.translation_key
                AND t2.language_id = (SELECT id FROM " . self::$languagesTable . " WHERE code = ?)
                WHERE t1.language_id = ? AND t2.id IS NULL";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $languageCode, $defaultLang['id']);
        $stmt->execute();

        $result = $stmt->get_result();
        $missing = [];

        while ($row = $result->fetch_assoc()) {
            $missing[] = $row;
        }

        return $missing;
    }

    /**
     * Detect user's preferred language from HTTP headers
     */
    public static function detectFromHeaders() {
        $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';

        if (empty($acceptLanguage)) {
            return 'en';
        }

        // Parse Accept-Language header
        $languages = explode(',', $acceptLanguage);

        foreach ($languages as $lang) {
            $lang = trim(explode(';', $lang)[0]); // Remove quality value
            $code = explode('-', $lang)[0]; // Get primary language code

            // Check if we support this language
            if (self::findByCode($code)) {
                return $code;
            }
        }

        return 'en'; // Default fallback
    }

    /**
     * Export translations for frontend
     */
    public static function exportTranslations($languageCode) {
        $language = self::findByCode($languageCode);
        if (!$language) {
            return null;
        }

        return [
            'language' => $language,
            'translations' => self::getTranslations($languageCode)
        ];
    }

    /**
     * Import translations from array
     */
    public static function importTranslations($languageCode, $translations) {
        $success = 0;
        $errors = 0;

        foreach ($translations as $key => $text) {
            if (self::setTranslation($languageCode, $key, $text)) {
                $success++;
            } else {
                $errors++;
            }
        }

        return ['success' => $success, 'errors' => $errors];
    }

    /**
     * Get language statistics
     */
    public static function getStats() {
        $conn = getDBConnection();

        $stats = [];

        // Total languages
        $result = $conn->query("SELECT COUNT(*) as total FROM " . self::$languagesTable);
        $stats['total_languages'] = $result->fetch_assoc()['total'];

        // Active languages
        $result = $conn->query("SELECT COUNT(*) as active FROM " . self::$languagesTable . " WHERE is_active = 1");
        $stats['active_languages'] = $result->fetch_assoc()['active'];

        // Total translations
        $result = $conn->query("SELECT COUNT(*) as total FROM " . self::$translationsTable);
        $stats['total_translations'] = $result->fetch_assoc()['total'];

        // Translations per language
        $sql = "SELECT l.code, l.name, COUNT(t.id) as translation_count
                FROM " . self::$languagesTable . " l
                LEFT JOIN " . self::$translationsTable . " t ON l.id = t.language_id
                GROUP BY l.id, l.code, l.name
                ORDER BY l.sort_order";

        $result = $conn->query($sql);
        $stats['per_language'] = [];

        while ($row = $result->fetch_assoc()) {
            $stats['per_language'][] = $row;
        }

        return $stats;
    }
}
?>