<?php
/**
 * Create Search Tables
 * Sets up enhanced search capabilities and AI recommendations
 */

require_once 'config/database.php';

echo "Creating Search Tables\n";
echo "======================\n\n";

try {
    $conn = getDBConnection();
    echo "✓ Connected to database\n";

    // Create search_history table
    $searchHistoryTableSql = "CREATE TABLE IF NOT EXISTS search_history (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NULL,
        query VARCHAR(255) NOT NULL,
        result_count INT DEFAULT 0,
        filters JSON,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_search_history_user (user_id),
        INDEX idx_search_history_query (query),
        INDEX idx_search_history_created (created_at),
        FULLTEXT idx_search_history_fulltext (query)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($searchHistoryTableSql) === TRUE) {
        echo "✓ Search history table created\n";
    } else {
        echo "✗ Error creating search history table: " . $conn->error . "\n";
        exit(1);
    }

    // Create recommendations table for storing user recommendations
    $recommendationsTableSql = "CREATE TABLE IF NOT EXISTS user_recommendations (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        video_id INT NOT NULL,
        recommendation_type ENUM('category_based', 'popular', 'trending', 'similar', 'ai_generated') DEFAULT 'category_based',
        relevance_score DECIMAL(3,2) DEFAULT 0.00,
        reason VARCHAR(255),
        is_viewed BOOLEAN DEFAULT FALSE,
        is_clicked BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
        INDEX idx_user_recommendations_user (user_id),
        INDEX idx_user_recommendations_video (video_id),
        INDEX idx_user_recommendations_type (recommendation_type),
        INDEX idx_user_recommendations_created (created_at),
        INDEX idx_user_recommendations_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($recommendationsTableSql) === TRUE) {
        echo "✓ User recommendations table created\n";
    } else {
        echo "✗ Error creating user recommendations table: " . $conn->error . "\n";
        exit(1);
    }

    // Create smart_playlists table
    $smartPlaylistsTableSql = "CREATE TABLE IF NOT EXISTS smart_playlists (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        playlist_type ENUM('continue_watching', 'favorites_genre', 'recently_watched', 'trending', 'similar_content', 'seasonal', 'custom') NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        video_ids JSON, -- Array of video IDs
        criteria JSON, -- Generation criteria
        is_active BOOLEAN DEFAULT TRUE,
        last_generated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        expires_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_smart_playlists_user (user_id),
        INDEX idx_smart_playlists_type (playlist_type),
        INDEX idx_smart_playlists_active (is_active),
        INDEX idx_smart_playlists_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($smartPlaylistsTableSql) === TRUE) {
        echo "✓ Smart playlists table created\n";
    } else {
        echo "✗ Error creating smart playlists table: " . $conn->error . "\n";
        exit(1);
    }

    // Update videos table to support full-text search
    // First check what columns exist and add missing ones
    $columnsResult = $conn->query("DESCRIBE videos");
    $existingColumns = [];
    while ($row = $columnsResult->fetch_assoc()) {
        $existingColumns[] = $row['Field'];
    }

    $alterCommands = [];

    // Add missing columns
    if (!in_array('language', $existingColumns)) {
        $alterCommands[] = "ADD COLUMN language VARCHAR(10) DEFAULT 'en'";
    }
    if (!in_array('search_relevance', $existingColumns)) {
        $alterCommands[] = "ADD COLUMN search_relevance DECIMAL(3,2) DEFAULT 0.00";
    }

    // Add indexes for existing columns
    $indexCommands = [];
    if (!in_array('language', $existingColumns)) {
        $indexCommands[] = "ADD INDEX idx_videos_language (language)";
    }
    if (!in_array('search_relevance', $existingColumns)) {
        $indexCommands[] = "ADD INDEX idx_videos_relevance (search_relevance)";
    }

    // Execute alter commands
    $success = true;
    if (!empty($alterCommands)) {
        $alterSql = "ALTER TABLE videos " . implode(", ", $alterCommands);
        if ($conn->query($alterSql) !== TRUE) {
            echo "✗ Error adding columns to videos table: " . $conn->error . "\n";
            $success = false;
        }
    }

    if (!empty($indexCommands) && $success) {
        $indexSql = "ALTER TABLE videos " . implode(", ", $indexCommands);
        if ($conn->query($indexSql) !== TRUE) {
            echo "✗ Error adding indexes to videos table: " . $conn->error . "\n";
            $success = false;
        }
    }

    // Try to add fulltext index (only if columns exist)
    if (in_array('title', $existingColumns) && in_array('description', $existingColumns) &&
        in_array('tags', $existingColumns) && in_array('speaker', $existingColumns)) {
        $fulltextSql = "ALTER TABLE videos ADD FULLTEXT INDEX idx_videos_fulltext (title, description, tags, speaker)";
        if ($conn->query($fulltextSql) !== TRUE) {
            if (strpos($conn->error, 'Duplicate key') === false) {
                echo "✗ Error adding fulltext index: " . $conn->error . "\n";
                $success = false;
            }
        }
    } else {
        echo "! Fulltext index not created - some required columns missing\n";
    }

    if ($success) {
        echo "✓ Videos table updated with search enhancements\n";
    }

    // Add sample search history data
    echo "\nAdding sample search data...\n";

    // Sample search history
    $sampleSearches = [
        [1, 'sermon faith', 12, '{"category_id": 1}', '192.168.1.1', 'Chrome/91.0'],
        [1, 'prayer meeting', 8, '{"date_from": "2024-01-01"}', '192.168.1.1', 'Chrome/91.0'],
        [null, 'worship songs', 25, '{}', '192.168.1.2', 'Safari/14.0'],
        [2, 'pastor john', 15, '{"speaker": "john"}', '192.168.1.3', 'Firefox/89.0'],
        [1, 'youth ministry', 6, '{"category_id": 3}', '192.168.1.1', 'Chrome/91.0'],
    ];

    $searchStmt = $conn->prepare("INSERT IGNORE INTO search_history
        (user_id, query, result_count, filters, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?, ?)");

    $searchCount = 0;
    foreach ($sampleSearches as $search) {
        $searchStmt->bind_param("isisss", $search[0], $search[1], $search[2], $search[3], $search[4], $search[5]);
        if ($searchStmt->execute()) {
            $searchCount++;
        }
    }
    echo "✓ Added $searchCount sample search records\n";

    // Add sample smart playlists
    $samplePlaylists = [
        [1, 'continue_watching', 'Continue Watching', 'Videos you started watching', '[1,2,3]', '{"max_age_days": 30}', 1],
        [1, 'favorites_genre', 'More Sermons', 'Based on your favorite sermons', '[4,5,6]', '{"category_id": 1}', 1],
        [2, 'trending', 'Trending Now', 'Popular content this week', '[7,8,9]', '{"period_days": 7}', 1],
    ];

    $playlistStmt = $conn->prepare("INSERT IGNORE INTO smart_playlists
        (user_id, playlist_type, title, description, video_ids, criteria, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?)");

    $playlistCount = 0;
    foreach ($samplePlaylists as $playlist) {
        $playlistStmt->bind_param("issssss", $playlist[0], $playlist[1], $playlist[2], $playlist[3], $playlist[4], $playlist[5], $playlist[6]);
        if ($playlistStmt->execute()) {
            $playlistCount++;
        }
    }
    echo "✓ Added $playlistCount sample smart playlists\n";

    $conn->close();
    echo "\n🎉 Search tables created successfully!\n";
    echo "\nNext steps:\n";
    echo "1. Update API routes in backend/api/index.php\n";
    echo "2. Create SearchController.php\n";
    echo "3. Update frontend search interface\n";
    echo "4. Implement AI recommendations service\n";

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>