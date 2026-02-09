<?php
/**
 * Create Analytics Tables
 * Sets up comprehensive analytics tracking for LCMTV platform
 */

require_once 'config/database.php';

echo "Creating Analytics Tables\n";
echo "=========================\n\n";

try {
    $conn = getDBConnection();
    echo "✓ Connected to database\n";

    // Create page_views table
    $pageViewsTableSql = "CREATE TABLE IF NOT EXISTS page_views (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NULL,
        session_id VARCHAR(255) NOT NULL,
        page_url VARCHAR(500) NOT NULL,
        page_title VARCHAR(255),
        referrer VARCHAR(500),
        user_agent TEXT,
        ip_address VARCHAR(45),
        country VARCHAR(100),
        city VARCHAR(100),
        device_type ENUM('desktop', 'mobile', 'tablet') DEFAULT 'desktop',
        browser VARCHAR(50),
        os VARCHAR(50),
        screen_resolution VARCHAR(20),
        viewport_size VARCHAR(20),
        time_spent INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_page_views_user (user_id),
        INDEX idx_page_views_session (session_id),
        INDEX idx_page_views_created (created_at),
        INDEX idx_page_views_device (device_type),
        INDEX idx_page_views_country (country),
        INDEX idx_page_views_url (page_url(100))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($pageViewsTableSql) === TRUE) {
        echo "✓ Page views table created\n";
    } else {
        echo "✗ Error creating page views table: " . $conn->error . "\n";
        exit(1);
    }

    // Create video_views table
    $videoViewsTableSql = "CREATE TABLE IF NOT EXISTS video_views (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NULL,
        session_id VARCHAR(255) NOT NULL,
        video_id INT NOT NULL,
        video_title VARCHAR(255),
        watch_duration INT DEFAULT 0,
        total_duration INT DEFAULT 0,
        completion_rate DECIMAL(5,2) DEFAULT 0.00,
        quality VARCHAR(10),
        playback_speed DECIMAL(3,1) DEFAULT 1.0,
        referrer VARCHAR(500),
        device_type ENUM('desktop', 'mobile', 'tablet') DEFAULT 'desktop',
        ip_address VARCHAR(45),
        country VARCHAR(100),
        city VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
        INDEX idx_video_views_user (user_id),
        INDEX idx_video_views_session (session_id),
        INDEX idx_video_views_video (video_id),
        INDEX idx_video_views_created (created_at),
        INDEX idx_video_views_device (device_type),
        INDEX idx_video_views_completion (completion_rate)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($videoViewsTableSql) === TRUE) {
        echo "✓ Video views table created\n";
    } else {
        echo "✗ Error creating video views table: " . $conn->error . "\n";
        exit(1);
    }

    // Create user_sessions table
    $userSessionsTableSql = "CREATE TABLE IF NOT EXISTS user_sessions (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NULL,
        session_id VARCHAR(255) NOT NULL UNIQUE,
        start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        end_time TIMESTAMP NULL,
        duration INT DEFAULT 0,
        pages_viewed INT DEFAULT 0,
        videos_watched INT DEFAULT 0,
        device_type ENUM('desktop', 'mobile', 'tablet') DEFAULT 'desktop',
        browser VARCHAR(50),
        os VARCHAR(50),
        ip_address VARCHAR(45),
        country VARCHAR(100),
        city VARCHAR(100),
        referrer VARCHAR(500),
        utm_source VARCHAR(100),
        utm_medium VARCHAR(100),
        utm_campaign VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_user_sessions_user (user_id),
        INDEX idx_user_sessions_session (session_id),
        INDEX idx_user_sessions_created (created_at),
        INDEX idx_user_sessions_device (device_type),
        INDEX idx_user_sessions_duration (duration)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($userSessionsTableSql) === TRUE) {
        echo "✓ User sessions table created\n";
    } else {
        echo "✗ Error creating user sessions table: " . $conn->error . "\n";
        exit(1);
    }

    // Create content_engagement table
    $contentEngagementTableSql = "CREATE TABLE IF NOT EXISTS content_engagement (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NULL,
        session_id VARCHAR(255) NOT NULL,
        content_type ENUM('video', 'livestream', 'comment', 'reaction', 'category') NOT NULL,
        content_id VARCHAR(100) NOT NULL,
        action_type ENUM('view', 'like', 'comment', 'share', 'favorite', 'watch_later', 'download') NOT NULL,
        action_value VARCHAR(255),
        device_type ENUM('desktop', 'mobile', 'tablet') DEFAULT 'desktop',
        referrer VARCHAR(500),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_content_engagement_user (user_id),
        INDEX idx_content_engagement_session (session_id),
        INDEX idx_content_engagement_content (content_type, content_id),
        INDEX idx_content_engagement_action (action_type),
        INDEX idx_content_engagement_created (created_at),
        INDEX idx_content_engagement_device (device_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($contentEngagementTableSql) === TRUE) {
        echo "✓ Content engagement table created\n";
    } else {
        echo "✗ Error creating content engagement table: " . $conn->error . "\n";
        exit(1);
    }

    // Create geographic_data table for IP geolocation cache
    $geographicDataTableSql = "CREATE TABLE IF NOT EXISTS geographic_data (
        id INT PRIMARY KEY AUTO_INCREMENT,
        ip_address VARCHAR(45) NOT NULL UNIQUE,
        country VARCHAR(100),
        country_code VARCHAR(2),
        city VARCHAR(100),
        region VARCHAR(100),
        latitude DECIMAL(10,8),
        longitude DECIMAL(11,8),
        timezone VARCHAR(50),
        isp VARCHAR(100),
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_geographic_ip (ip_address),
        INDEX idx_geographic_country (country),
        INDEX idx_geographic_city (city),
        INDEX idx_geographic_updated (last_updated)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($geographicDataTableSql) === TRUE) {
        echo "✓ Geographic data table created\n";
    } else {
        echo "✗ Error creating geographic data table: " . $conn->error . "\n";
        exit(1);
    }

    // Add analytics columns to existing tables if they don't exist
    $alterVideosSql = "ALTER TABLE videos
        ADD COLUMN IF NOT EXISTS view_count INT DEFAULT 0,
        ADD COLUMN IF NOT EXISTS avg_watch_duration INT DEFAULT 0,
        ADD COLUMN IF NOT EXISTS completion_rate DECIMAL(5,2) DEFAULT 0.00,
        ADD INDEX IF NOT EXISTS idx_videos_view_count (view_count DESC),
        ADD INDEX IF NOT EXISTS idx_videos_completion_rate (completion_rate DESC)";

    if ($conn->query($alterVideosSql) === TRUE) {
        echo "✓ Videos table updated with analytics columns\n";
    } else {
        // Check if columns already exist
        if (strpos($conn->error, 'Duplicate column') === false &&
            strpos($conn->error, 'Duplicate key') === false) {
            echo "✗ Error updating videos table: " . $conn->error . "\n";
        } else {
            echo "✓ Videos table analytics columns already exist\n";
        }
    }

    // Add analytics columns to users table
    $alterUsersSql = "ALTER TABLE users
        ADD COLUMN IF NOT EXISTS total_sessions INT DEFAULT 0,
        ADD COLUMN IF NOT EXISTS total_watch_time INT DEFAULT 0,
        ADD COLUMN IF NOT EXISTS favorite_categories TEXT,
        ADD COLUMN IF NOT EXISTS last_activity TIMESTAMP NULL,
        ADD INDEX IF NOT EXISTS idx_users_activity (last_activity)";

    if ($conn->query($alterUsersSql) === TRUE) {
        echo "✓ Users table updated with analytics columns\n";
    } else {
        // Check if columns already exist
        if (strpos($conn->error, 'Duplicate column') === false &&
            strpos($conn->error, 'Duplicate key') === false) {
            echo "✗ Error updating users table: " . $conn->error . "\n";
        } else {
            echo "✓ Users table analytics columns already exist\n";
        }
    }

    // Add sample analytics data for testing
    echo "\nAdding sample analytics data...\n";

    // Sample page views
    $samplePageViews = [
        [1, 'session_001', '/', 'LCMTV - Church TV', 'google.com', 'Chrome/91.0', '192.168.1.1', 'United States', 'New York', 'desktop', 'Chrome', 'Windows', '1920x1080', 120],
        [1, 'session_002', '/video/1', 'Sermon Title', '/', 'Chrome/91.0', '192.168.1.1', 'United States', 'Los Angeles', 'mobile', 'Chrome', 'Android', '375x667', 300],
        [null, 'session_003', '/category/sermons', 'Sermons', 'facebook.com', 'Safari/14.0', '192.168.1.2', 'Canada', 'Toronto', 'desktop', 'Safari', 'macOS', '1440x900', 45],
    ];

    $pageViewStmt = $conn->prepare("INSERT IGNORE INTO page_views
        (user_id, session_id, page_url, page_title, referrer_url, user_agent, ip_address, country, city, device_type, browser, os, screen_resolution, time_on_page)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $pageViewCount = 0;
    foreach ($samplePageViews as $view) {
        $pageViewStmt->bind_param("issssssssssssi", $view[0], $view[1], $view[2], $view[3], $view[4], $view[5], $view[6], $view[7], $view[8], $view[9], $view[10], $view[11], $view[12], $view[13]);
        if ($pageViewStmt->execute()) {
            $pageViewCount++;
        }
    }
    echo "✓ Added $pageViewCount sample page views\n";

    // Sample video views
    $sampleVideoViews = [
        [1, 'session_001', 1, 'abc123def456', 180, 300, 60.0, '720p', 1.0, '/', 'Chrome/91.0', '192.168.1.1', 'desktop', 'Chrome', 'Windows', 'US', 'New York', 0],
        [1, 'session_002', 1, 'abc123def456', 250, 300, 83.3, '480p', 1.0, '/video/1', 'Chrome/91.0', '192.168.1.1', 'mobile', 'Chrome', 'Android', 'US', 'Los Angeles', 0],
        [null, 'session_003', 2, 'xyz789ghi012', 120, 180, 66.7, '1080p', 1.25, '/category/prayers', 'Safari/14.0', '192.168.1.2', 'desktop', 'Safari', 'macOS', 'CA', 'Toronto', 0],
    ];

    $videoViewStmt = $conn->prepare("INSERT IGNORE INTO video_views
        (user_id, session_id, video_id, youtube_id, watch_duration, total_duration, watch_percentage, quality, playback_speed, referrer_url, user_agent, ip_address, device_type, browser, os, country, city, completed)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $videoViewCount = 0;
    foreach ($sampleVideoViews as $view) {
        $videoViewStmt->bind_param("iisisiddsdsssssssi", $view[0], $view[1], $view[2], $view[3], $view[4], $view[5], $view[6], $view[7], $view[8], $view[9], $view[10], $view[11], $view[12], $view[13], $view[14], $view[15], $view[16], $view[17]);
        if ($videoViewStmt->execute()) {
            $videoViewCount++;
        }
    }
    echo "✓ Added $videoViewCount sample video views\n";

    // Sample user sessions
    $sampleSessions = [
        [1, 'session_001', '2024-01-15 10:00:00', '2024-01-15 10:05:00', 300, 3, 2, 'desktop', 'Chrome', 'Windows', '192.168.1.1', 'United States', 'New York', 'google.com', 'google', 'organic', 'brand'],
        [1, 'session_002', '2024-01-15 14:30:00', '2024-01-15 14:45:00', 900, 5, 3, 'mobile', 'Chrome', 'Android', '192.168.1.1', 'United States', 'Los Angeles', 'facebook.com', 'facebook', 'social', 'church'],
    ];

    $sessionStmt = $conn->prepare("INSERT IGNORE INTO user_sessions
        (user_id, session_id, start_time, end_time, duration, pages_viewed, videos_watched, device_type, browser, os, ip_address, country, city, referrer, utm_source, utm_medium, utm_campaign)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $sessionCount = 0;
    foreach ($sampleSessions as $session) {
        $sessionStmt->bind_param("isssiiiisssssssss", $session[0], $session[1], $session[2], $session[3], $session[4], $session[5], $session[6], $session[7], $session[8], $session[9], $session[10], $session[11], $session[12], $session[13], $session[14], $session[15], $session[16]);
        if ($sessionStmt->execute()) {
            $sessionCount++;
        }
    }
    echo "✓ Added $sessionCount sample user sessions\n";

    // Sample content engagement
    $sampleEngagement = [
        [1, 'session_001', 'video', '1', 'like', '', 'desktop', '/'],
        [1, 'session_001', 'video', '1', 'comment', 'Great message!', 'desktop', '/video/1'],
        [1, 'session_002', 'video', '2', 'favorite', '', 'mobile', '/category/sermons'],
        [null, 'session_003', 'category', 'sermons', 'view', '', 'desktop', '/'],
    ];

    $engagementStmt = $conn->prepare("INSERT IGNORE INTO content_engagement
        (user_id, session_id, content_type, content_id, action_type, action_value, device_type, referrer)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

    $engagementCount = 0;
    foreach ($sampleEngagement as $engagement) {
        $engagementStmt->bind_param("ssssssss", $engagement[0], $engagement[1], $engagement[2], $engagement[3], $engagement[4], $engagement[5], $engagement[6], $engagement[7]);
        if ($engagementStmt->execute()) {
            $engagementCount++;
        }
    }
    echo "✓ Added $engagementCount sample engagement records\n";

    $conn->close();
    echo "\n🎉 Analytics tables created successfully!\n";
    echo "\nNext steps:\n";
    echo "1. Run this script: php create_analytics_tables.php\n";
    echo "2. Update API routes in backend/api/index.php\n";
    echo "3. Create frontend analytics components\n";
    echo "4. Add client-side tracking to main.js\n";

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>