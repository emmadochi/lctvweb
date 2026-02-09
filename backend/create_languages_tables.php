<?php
/**
 * Create Languages Tables
 * Sets up multi-language support system
 */

require_once 'config/database.php';

echo "Creating Languages Tables\n";
echo "=========================\n\n";

try {
    $conn = getDBConnection();
    echo "✓ Connected to database\n";

    // Create languages table
    $languagesTableSql = "CREATE TABLE IF NOT EXISTS languages (
        id INT PRIMARY KEY AUTO_INCREMENT,
        code VARCHAR(10) NOT NULL UNIQUE,
        name VARCHAR(100) NOT NULL,
        native_name VARCHAR(100),
        flag_emoji VARCHAR(10),
        is_rtl BOOLEAN DEFAULT FALSE,
        is_active BOOLEAN DEFAULT TRUE,
        sort_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_languages_code (code),
        INDEX idx_languages_active (is_active),
        INDEX idx_languages_sort (sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($languagesTableSql) === TRUE) {
        echo "✓ Languages table created\n";
    } else {
        echo "✗ Error creating languages table: " . $conn->error . "\n";
        exit(1);
    }

    // Create translations table
    $translationsTableSql = "CREATE TABLE IF NOT EXISTS translations (
        id INT PRIMARY KEY AUTO_INCREMENT,
        language_id INT NOT NULL,
        translation_key VARCHAR(255) NOT NULL,
        translation_text TEXT NOT NULL,
        category VARCHAR(50) DEFAULT 'general',
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (language_id) REFERENCES languages(id) ON DELETE CASCADE,
        UNIQUE KEY unique_translation (language_id, translation_key),
        INDEX idx_translations_language (language_id),
        INDEX idx_translations_key (translation_key),
        INDEX idx_translations_category (category),
        INDEX idx_translations_active (is_active),
        FULLTEXT idx_translations_text (translation_text)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($translationsTableSql) === TRUE) {
        echo "✓ Translations table created\n";
    } else {
        echo "✗ Error creating translations table: " . $conn->error . "\n";
        exit(1);
    }

    // Create content_translations table for video/content translations
    $contentTranslationsTableSql = "CREATE TABLE IF NOT EXISTS content_translations (
        id INT PRIMARY KEY AUTO_INCREMENT,
        content_type ENUM('video', 'category', 'page', 'menu') NOT NULL,
        content_id INT NOT NULL,
        language_id INT NOT NULL,
        field_name VARCHAR(100) NOT NULL, -- title, description, etc.
        translated_text TEXT NOT NULL,
        is_machine_translated BOOLEAN DEFAULT FALSE,
        needs_review BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (language_id) REFERENCES languages(id) ON DELETE CASCADE,
        UNIQUE KEY unique_content_translation (content_type, content_id, language_id, field_name),
        INDEX idx_content_translations_content (content_type, content_id),
        INDEX idx_content_translations_language (language_id),
        INDEX idx_content_translations_field (field_name),
        INDEX idx_content_translations_review (needs_review),
        FULLTEXT idx_content_translations_text (translated_text)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($contentTranslationsTableSql) === TRUE) {
        echo "✓ Content translations table created\n";
    } else {
        echo "✗ Error creating content translations table: " . $conn->error . "\n";
        exit(1);
    }

    // Add language support to existing tables
    $alterVideosSql = "ALTER TABLE videos
        ADD COLUMN IF NOT EXISTS original_language VARCHAR(10) DEFAULT 'en',
        ADD COLUMN IF NOT EXISTS has_subtitles BOOLEAN DEFAULT FALSE,
        ADD COLUMN IF NOT EXISTS subtitle_languages JSON,
        ADD INDEX IF NOT EXISTS idx_videos_language (original_language),
        ADD INDEX IF NOT EXISTS idx_videos_subtitles (has_subtitles)";

    if ($conn->query($alterVideosSql) === TRUE) {
        echo "✓ Videos table updated with language support\n";
    } else {
        if (strpos($conn->error, 'Duplicate column') === false &&
            strpos($conn->error, 'Duplicate key') === false) {
            echo "✗ Error updating videos table: " . $conn->error . "\n";
        } else {
            echo "✓ Videos table language columns already exist\n";
        }
    }

    // Insert supported languages
    echo "\nInserting supported languages...\n";

    $languages = [
        ['en', 'English', 'English', '🇺🇸', false, 1, 1],
        ['es', 'Spanish', 'Español', '🇪🇸', false, 1, 2],
        ['fr', 'French', 'Français', '🇫🇷', false, 1, 3],
        ['pt', 'Portuguese', 'Português', '🇵🇹', false, 1, 4],
        ['de', 'German', 'Deutsch', '🇩🇪', false, 1, 5],
        ['zh', 'Chinese', '中文', '🇨🇳', false, 1, 6],
        ['ar', 'Arabic', 'العربية', '🇸🇦', true, 1, 7],
        ['hi', 'Hindi', 'हिन्दी', '🇮🇳', false, 1, 8],
        ['ru', 'Russian', 'Русский', '🇷🇺', false, 1, 9],
        ['ko', 'Korean', '한국어', '🇰🇷', false, 1, 10]
    ];

    $langStmt = $conn->prepare("INSERT IGNORE INTO languages
        (code, name, native_name, flag_emoji, is_rtl, is_active, sort_order)
        VALUES (?, ?, ?, ?, ?, ?, ?)");

    $langCount = 0;
    foreach ($languages as $lang) {
        $langStmt->bind_param("ssssiii", $lang[0], $lang[1], $lang[2], $lang[3], $lang[4], $lang[5], $lang[6]);
        if ($langStmt->execute()) {
            $langCount++;
        }
    }
    echo "✓ Added $langCount supported languages\n";

    // Insert basic translations for English (as reference)
    echo "\nInserting basic English translations...\n";

    // Get English language ID
    $result = $conn->query("SELECT id FROM languages WHERE code = 'en'");
    $englishId = $result->fetch_assoc()['id'];

    $englishTranslations = [
        // Navigation
        ['NAV_HOME', 'Home', 'navigation'],
        ['NAV_SEARCH', 'Search', 'navigation'],
        ['NAV_FAVORITES', 'Favorites', 'navigation'],
        ['NAV_PROFILE', 'Profile', 'navigation'],
        ['NAV_DONATE', 'Donate', 'navigation'],
        ['NAV_ABOUT', 'About', 'navigation'],
        ['NAV_CONTACT', 'Contact', 'navigation'],

        // Search
        ['SEARCH_PLACEHOLDER', 'Search for videos, sermons, worship...', 'search'],
        ['SEARCH_BUTTON', 'Search', 'search'],
        ['SEARCH_RESULTS', 'results', 'search'],
        ['SEARCH_NO_RESULTS', 'No results found', 'search'],
        ['SEARCH_LOADING', 'Searching...', 'search'],
        ['SEARCH_ADVANCED', 'Advanced Search', 'search'],
        ['SEARCH_FILTERS', 'Filters', 'search'],

        // Categories
        ['CATEGORY_SERMONS', 'Sermons', 'categories'],
        ['CATEGORY_WORSHIP', 'Worship', 'categories'],
        ['CATEGORY_CHILDREN', 'Children', 'categories'],
        ['CATEGORY_YOUTH', 'Youth', 'categories'],
        ['CATEGORY_PRAYER', 'Prayer', 'categories'],
        ['CATEGORY_BIBLE_STUDY', 'Bible Study', 'categories'],
        ['CATEGORY_SPECIAL_EVENTS', 'Special Events', 'categories'],
        ['CATEGORY_MUSIC', 'Music', 'categories'],

        // Actions
        ['ACTION_SAVE', 'Save', 'actions'],
        ['ACTION_CANCEL', 'Cancel', 'actions'],
        ['ACTION_DELETE', 'Delete', 'actions'],
        ['ACTION_EDIT', 'Edit', 'actions'],
        ['ACTION_VIEW', 'View', 'actions'],
        ['ACTION_SHARE', 'Share', 'actions'],
        ['ACTION_DOWNLOAD', 'Download', 'actions'],
        ['ACTION_PLAY', 'Play', 'actions'],
        ['ACTION_PAUSE', 'Pause', 'actions'],
        ['ACTION_LIKE', 'Like', 'actions'],
        ['ACTION_COMMENT', 'Comment', 'actions'],
        ['ACTION_FAVORITE', 'Favorite', 'actions'],

        // Messages
        ['MSG_LOADING', 'Loading...', 'messages'],
        ['MSG_ERROR', 'An error occurred', 'messages'],
        ['MSG_SUCCESS', 'Success!', 'messages'],
        ['MSG_CONFIRM_DELETE', 'Are you sure you want to delete this?', 'messages'],
        ['MSG_NETWORK_ERROR', 'Network error. Please try again.', 'messages']
    ];

    $transStmt = $conn->prepare("INSERT IGNORE INTO translations
        (language_id, translation_key, translation_text, category)
        VALUES (?, ?, ?, ?)");

    $transCount = 0;
    foreach ($englishTranslations as $trans) {
        $transStmt->bind_param("isss", $englishId, $trans[0], $trans[1], $trans[2]);
        if ($transStmt->execute()) {
            $transCount++;
        }
    }
    echo "✓ Added $transCount English translations\n";

    $conn->close();
    echo "\n🎉 Languages tables created successfully!\n";
    echo "\nNext steps:\n";
    echo "1. Run this script: php create_languages_tables.php\n";
    echo "2. Create LanguageController.php for API endpoints\n";
    echo "3. Update frontend to load translations dynamically\n";
    echo "4. Add content translation features\n";
    echo "5. Create admin translation management interface\n";

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>