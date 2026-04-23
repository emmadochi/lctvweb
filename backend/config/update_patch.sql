-- LCMTV Database Update Script
-- Use this script to patch an existing database to match the latest backend requirements.
-- Run this in phpMyAdmin or your cPanel MySQL terminal.

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Update Users Table
ALTER TABLE users MODIFY COLUMN password_hash VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS google_id VARCHAR(255) NULL AFTER role;
ALTER TABLE users ADD COLUMN IF NOT EXISTS login_provider VARCHAR(50) DEFAULT 'local' AFTER google_id;
ALTER TABLE users MODIFY COLUMN role ENUM('user', 'leader', 'pastor', 'director', 'admin', 'super_admin') DEFAULT 'user';
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_users_google (google_id);

-- 2. Update Videos Table
ALTER TABLE videos ADD COLUMN IF NOT EXISTS target_role VARCHAR(50) DEFAULT 'general' AFTER published_at;
ALTER TABLE videos ADD COLUMN IF NOT EXISTS original_language VARCHAR(10) DEFAULT 'en' AFTER price;
ALTER TABLE videos ADD COLUMN IF NOT EXISTS has_subtitles BOOLEAN DEFAULT FALSE AFTER original_language;
ALTER TABLE videos ADD COLUMN IF NOT EXISTS subtitle_languages TEXT AFTER has_subtitles;
ALTER TABLE videos ADD INDEX IF NOT EXISTS idx_videos_role (target_role);

-- 3. Update Livestreams Table
ALTER TABLE livestreams ADD COLUMN IF NOT EXISTS hls_url VARCHAR(500) NULL AFTER channel_id;
ALTER TABLE livestreams ADD COLUMN IF NOT EXISTS target_role VARCHAR(50) DEFAULT 'general' AFTER is_live;
ALTER TABLE livestreams ADD INDEX IF NOT EXISTS idx_livestreams_role (target_role);

-- 4. Create Livestream Viewers Table
CREATE TABLE IF NOT EXISTS livestream_viewers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    livestream_id INT NOT NULL,
    session_id VARCHAR(255) NOT NULL,
    last_heartbeat TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_session (livestream_id, session_id),
    FOREIGN KEY (livestream_id) REFERENCES livestreams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Create Languages Table
CREATE TABLE IF NOT EXISTS languages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(10) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    native_name VARCHAR(100),
    flag_emoji VARCHAR(10),
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Create Content Translations Table
CREATE TABLE IF NOT EXISTS content_translations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    language_id INT NOT NULL,
    content_type ENUM('video', 'category', 'livestream', 'page') NOT NULL,
    content_id INT NOT NULL,
    field_name VARCHAR(100) NOT NULL,
    translated_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (language_id) REFERENCES languages(id) ON DELETE CASCADE,
    UNIQUE KEY unique_translation (language_id, content_type, content_id, field_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
