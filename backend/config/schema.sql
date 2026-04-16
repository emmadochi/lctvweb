-- LCMTV Database Schema
-- MySQL 5.7+ compatible

CREATE DATABASE IF NOT EXISTS lcmtv_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lcmtv_db;

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    role ENUM('user', 'admin', 'super_admin') DEFAULT 'user',
    is_active BOOLEAN DEFAULT TRUE,
    watch_history TEXT, -- JSON array of video IDs and timestamps
    favorites TEXT, -- JSON array of favorite video IDs
    my_channels TEXT, -- JSON array of channel IDs
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Categories table
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    thumbnail_url VARCHAR(500),
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Videos table
CREATE TABLE videos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    youtube_id VARCHAR(20) UNIQUE NOT NULL,
    title VARCHAR(500) NOT NULL,
    description TEXT,
    thumbnail_url VARCHAR(500),
    duration INT, -- in seconds
    category_id INT,
    tags TEXT, -- JSON array of tags
    channel_title VARCHAR(255),
    channel_id VARCHAR(50),
    view_count BIGINT DEFAULT 0,
    like_count BIGINT DEFAULT 0,
    published_at DATETIME,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Livestreams table
CREATE TABLE livestreams (
    id INT PRIMARY KEY AUTO_INCREMENT,
    youtube_id VARCHAR(20) UNIQUE NOT NULL,
    title VARCHAR(500) NOT NULL,
    description TEXT,
    thumbnail_url VARCHAR(500),
    channel_title VARCHAR(255),
    channel_id VARCHAR(50),
    viewer_count INT DEFAULT 0,
    category_id INT,
    is_live BOOLEAN DEFAULT TRUE,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_livestreams_live (is_live),
    INDEX idx_livestreams_category (category_id),
    INDEX idx_livestreams_viewers (viewer_count DESC)
);

-- Video stats table (for tracking views, engagement)
CREATE TABLE video_stats (
    id INT PRIMARY KEY AUTO_INCREMENT,
    video_id INT NOT NULL,
    user_id INT, -- null for anonymous views
    action ENUM('view', 'like', 'share', 'watch_complete') NOT NULL,
    session_id VARCHAR(255), -- for anonymous tracking
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Playlists table (for scheduled content)
CREATE TABLE playlists (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    category_id INT,
    is_scheduled BOOLEAN DEFAULT FALSE,
    schedule_start DATETIME,
    schedule_end DATETIME,
    video_ids TEXT, -- JSON array of video IDs in order
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Analytics tables
CREATE TABLE page_views (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    session_id VARCHAR(255) NOT NULL,
    page_url VARCHAR(500) NOT NULL,
    page_title VARCHAR(255),
    referrer_url VARCHAR(500),
    user_agent TEXT,
    ip_address VARCHAR(45),
    country VARCHAR(2),
    city VARCHAR(100),
    device_type ENUM('desktop', 'mobile', 'tablet', 'unknown') DEFAULT 'unknown',
    browser VARCHAR(50),
    os VARCHAR(50),
    screen_resolution VARCHAR(20),
    time_on_page INT, -- in seconds
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_page_views_session (session_id),
    INDEX idx_page_views_created (created_at),
    INDEX idx_page_views_url (page_url(100))
);

CREATE TABLE video_views (
    id INT PRIMARY KEY AUTO_INCREMENT,
    video_id INT NOT NULL,
    user_id INT,
    session_id VARCHAR(255) NOT NULL,
    youtube_id VARCHAR(20) NOT NULL,
    watch_duration INT DEFAULT 0, -- seconds watched
    total_duration INT DEFAULT 0, -- total video duration
    watch_percentage DECIMAL(5,2) DEFAULT 0, -- percentage watched (0-100)
    quality VARCHAR(10), -- video quality (720p, 1080p, etc.)
    playback_speed DECIMAL(3,1) DEFAULT 1.0,
    referrer_url VARCHAR(500),
    user_agent TEXT,
    ip_address VARCHAR(45),
    device_type ENUM('desktop', 'mobile', 'tablet', 'unknown') DEFAULT 'unknown',
    browser VARCHAR(50),
    os VARCHAR(50),
    country VARCHAR(2),
    city VARCHAR(100),
    completed BOOLEAN DEFAULT FALSE, -- watched to completion
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_video_views_video (video_id),
    INDEX idx_video_views_user (user_id),
    INDEX idx_video_views_session (session_id),
    INDEX idx_video_views_created (created_at),
    INDEX idx_video_views_completed (completed)
);

-- Notifications table
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type ENUM('favorite', 'subscription', 'system', 'interaction') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    related_id INT, -- video_id, user_id, etc. depending on type
    related_type ENUM('video', 'user', 'category', 'system'), -- what the related_id refers to
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notifications_user (user_id),
    INDEX idx_notifications_read (is_read),
    INDEX idx_notifications_created (created_at),
    INDEX idx_notifications_type (type)
);

-- Push subscriptions table
CREATE TABLE push_subscriptions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    endpoint VARCHAR(500) NOT NULL UNIQUE,
    p256dh_key VARCHAR(100) NOT NULL,
    auth_key VARCHAR(50) NOT NULL,
    user_agent TEXT,
    ip_address VARCHAR(45),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_push_subscriptions_user (user_id),
    INDEX idx_push_subscriptions_active (is_active),
    INDEX idx_push_subscriptions_endpoint (endpoint(100))
);

-- Admin logs table
CREATE TABLE admin_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Indexes for performance
CREATE INDEX idx_videos_category ON videos(category_id);
CREATE INDEX idx_videos_youtube_id ON videos(youtube_id);
CREATE INDEX idx_videos_active ON videos(is_active);
CREATE INDEX idx_categories_active ON categories(is_active);
CREATE INDEX idx_categories_slug ON categories(slug);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_active ON users(is_active);
CREATE INDEX idx_video_stats_video ON video_stats(video_id);
CREATE INDEX idx_video_stats_user ON video_stats(user_id);
CREATE INDEX idx_video_stats_created ON video_stats(created_at);

-- Insert default categories
INSERT INTO categories (name, slug, description, sort_order) VALUES
('News', 'news', 'Latest news and current events', 1),
('Sports', 'sports', 'Sports highlights and coverage', 2),
('Music', 'music', 'Music videos and performances', 3),
('Sermons', 'sermons', 'Religious sermons and teachings', 4),
('Kids', 'kids', 'Family-friendly content for children', 5),
('Tech', 'tech', 'Technology and innovation', 6),
('Movies', 'movies', 'Movie trailers and clips', 7);

-- Manual override tracking
CREATE TABLE IF NOT EXISTS video_category_override (
    id INT PRIMARY KEY AUTO_INCREMENT,
    video_id INT NOT NULL,
    original_category_id INT,
    override_category_id INT NOT NULL,
    reason TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
    FOREIGN KEY (original_category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (override_category_id) REFERENCES categories(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_video_override (video_id)
);

-- Donation System Tables
-- Payment Settings: Stores bank info, crypto wallets, and gateway keys
CREATE TABLE IF NOT EXISTS payment_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_group ENUM('gateway', 'bank', 'crypto', 'general') NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    is_encrypted BOOLEAN DEFAULT FALSE, -- For sensitive keys
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Donors table
CREATE TABLE IF NOT EXISTS donors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    phone VARCHAR(20),
    address_street VARCHAR(255),
    address_city VARCHAR(100),
    address_state VARCHAR(100),
    address_zip VARCHAR(20),
    address_country VARCHAR(100),
    total_donated DECIMAL(15, 2) DEFAULT 0,
    donation_count INT DEFAULT 0,
    last_donation_date DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Donation campaigns
CREATE TABLE IF NOT EXISTS donation_campaigns (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    goal_amount DECIMAL(15, 2) NOT NULL,
    current_amount DECIMAL(15, 2) DEFAULT 0,
    currency VARCHAR(10) DEFAULT 'USD',
    start_date DATE,
    end_date DATE,
    campaign_type ENUM('general', 'mission', 'building', 'disaster_relief', 'other') DEFAULT 'general',
    is_active BOOLEAN DEFAULT TRUE,
    is_featured BOOLEAN DEFAULT FALSE,
    image_url VARCHAR(500),
    video_url VARCHAR(500),
    thank_you_message TEXT,
    impact_description TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Donations log
CREATE TABLE IF NOT EXISTS donations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    donor_id INT NOT NULL,
    campaign_id INT,
    amount DECIMAL(15, 2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'USD',
    payment_method VARCHAR(50) NOT NULL, -- stripe, paypal, paystack, crypto, bank_transfer
    payment_provider VARCHAR(50), 
    transaction_id VARCHAR(255) UNIQUE,
    transaction_status ENUM('pending', 'completed', 'failed', 'refunded', 'cancelled') DEFAULT 'pending',
    is_recurring BOOLEAN DEFAULT FALSE,
    recurring_interval ENUM('daily', 'weekly', 'monthly', 'yearly') NULL,
    donation_type VARCHAR(50) DEFAULT 'general',
    donation_purpose TEXT,
    is_anonymous BOOLEAN DEFAULT FALSE,
    tax_deductible BOOLEAN DEFAULT TRUE,
    receipt_issued BOOLEAN DEFAULT FALSE,
    receipt_number VARCHAR(100),
    notes TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    utm_source VARCHAR(100),
    utm_medium VARCHAR(100),
    utm_campaign VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (donor_id) REFERENCES donors(id) ON DELETE CASCADE,
    FOREIGN KEY (campaign_id) REFERENCES donation_campaigns(id) ON DELETE SET NULL
);

-- Recurring donations management
CREATE TABLE IF NOT EXISTS recurring_donations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    donation_id INT NOT NULL, -- Original donation
    user_id INT,
    donor_id INT NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'USD',
    interval_type ENUM('daily', 'weekly', 'monthly', 'yearly') NOT NULL,
    next_payment_date DATE NOT NULL,
    payment_method_id VARCHAR(255), -- Gateway side recurring ID
    status ENUM('active', 'paused', 'cancelled', 'expired') DEFAULT 'active',
    failure_count INT DEFAULT 0,
    last_failure_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (donation_id) REFERENCES donations(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (donor_id) REFERENCES donors(id) ON DELETE CASCADE
);

-- Tax Receipts log
CREATE TABLE IF NOT EXISTS tax_receipts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    donation_id INT NOT NULL,
    receipt_number VARCHAR(100) UNIQUE NOT NULL,
    donor_name VARCHAR(255) NOT NULL,
    donor_email VARCHAR(255) NOT NULL,
    donor_address TEXT,
    donation_date DATE NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'USD',
    tax_year INT NOT NULL,
    organization_name VARCHAR(255) NOT NULL,
    organization_ein VARCHAR(50),
    organization_address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (donation_id) REFERENCES donations(id) ON DELETE CASCADE
);

-- Indexes for donation performance
CREATE INDEX idx_donations_transaction ON donations(transaction_id);
CREATE INDEX idx_donations_status ON donations(transaction_status);
CREATE INDEX idx_donations_created ON donations(created_at);
CREATE INDEX idx_donors_email ON donors(email);

-- Insert admin user (password: admin123 - CHANGE THIS!)
('info@lifechangertouch.org', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'User', 'super_admin');

-- Payment gateways and account settings
CREATE TABLE IF NOT EXISTS payment_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_group ENUM('gateway', 'bank', 'crypto') NOT NULL,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    setting_label VARCHAR(255) NOT NULL,
    is_public BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    is_encrypted BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert initial payment settings (placeholders)
INSERT INTO payment_settings (setting_group, setting_key, setting_value, setting_label, is_public) VALUES
('gateway', 'stripe_publishable_key', 'pk_test_placeholder', 'Stripe Publishable Key', TRUE),
('gateway', 'stripe_secret_key', 'sk_test_placeholder', 'Stripe Secret Key', FALSE),
('gateway', 'stripe_webhook_secret', 'whsec_placeholder', 'Stripe Webhook Secret', FALSE),
('gateway', 'paystack_public_key', 'pk_test_placeholder', 'Paystack Public Key', TRUE),
('gateway', 'paystack_secret_key', 'sk_test_placeholder', 'Paystack Secret Key', FALSE),
('gateway', 'paypal_client_id', 'client_id_placeholder', 'PayPal Client ID', TRUE),
('gateway', 'paypal_secret', 'secret_placeholder', 'PayPal Secret', FALSE),
('bank', 'bank_account_1', 'Account Name: LCMTV\nBank: Example Bank\nAccount: 1234567890', 'Main Bank Account', TRUE),
('crypto', 'btc_wallet', 'bc1qplaceholder', 'Bitcoin (BTC) Wallet', TRUE),
('crypto', 'eth_wallet', '0xplaceholder', 'Ethereum (ETH) Wallet', TRUE);

-- Prayer Requests table
CREATE TABLE IF NOT EXISTS prayer_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NULL,
    category VARCHAR(100) DEFAULT 'General',
    request_text TEXT NOT NULL,
    admin_response TEXT NULL,
    responded_by INT NULL,
    responded_at DATETIME NULL,
    status ENUM('pending', 'answered') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (responded_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Indexes for prayer requests
CREATE INDEX idx_prayer_status ON prayer_requests(status);
CREATE INDEX idx_prayer_user ON prayer_requests(user_id);
CREATE INDEX idx_prayer_created ON prayer_requests(created_at);
