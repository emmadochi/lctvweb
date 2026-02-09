<?php
/**
 * Create Social Features Tables
 * Adds comments and reactions tables for community features
 */

require_once 'config/database.php';

echo "Creating Social Features Tables\n";
echo "=================================\n\n";

try {
    $conn = getDBConnection();
    echo "✓ Connected to database\n";

    // Create comments table
    $commentsTableSql = "CREATE TABLE IF NOT EXISTS comments (
        id INT PRIMARY KEY AUTO_INCREMENT,
        video_id INT NOT NULL,
        user_id INT NOT NULL,
        content TEXT NOT NULL,
        parent_id INT NULL,
        is_approved BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE,
        INDEX idx_comments_video (video_id),
        INDEX idx_comments_user (user_id),
        INDEX idx_comments_parent (parent_id),
        INDEX idx_comments_approved (is_approved),
        INDEX idx_comments_created (created_at DESC)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($commentsTableSql) === TRUE) {
        echo "✓ Comments table created\n";
    } else {
        echo "✗ Error creating comments table: " . $conn->error . "\n";
        exit(1);
    }

    // Create reactions table
    $reactionsTableSql = "CREATE TABLE IF NOT EXISTS reactions (
        id INT PRIMARY KEY AUTO_INCREMENT,
        video_id INT NOT NULL,
        user_id INT NOT NULL,
        reaction_type ENUM('like', 'love', 'pray', 'amen', 'praise', 'thankful', 'insightful') NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_user_video_reaction (video_id, user_id),
        INDEX idx_reactions_video (video_id),
        INDEX idx_reactions_user (user_id),
        INDEX idx_reactions_type (reaction_type),
        INDEX idx_reactions_created (created_at DESC)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($reactionsTableSql) === TRUE) {
        echo "✓ Reactions table created\n";
    } else {
        echo "✗ Error creating reactions table: " . $conn->error . "\n";
        exit(1);
    }

    // Create social activity table for tracking user interactions
    $socialActivityTableSql = "CREATE TABLE IF NOT EXISTS social_activity (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        activity_type ENUM('comment', 'reaction', 'share', 'favorite') NOT NULL,
        video_id INT NULL,
        target_user_id INT NULL,
        metadata JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
        FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_social_activity_user (user_id),
        INDEX idx_social_activity_video (video_id),
        INDEX idx_social_activity_type (activity_type),
        INDEX idx_social_activity_created (created_at DESC)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($socialActivityTableSql) === TRUE) {
        echo "✓ Social activity table created\n";
    } else {
        echo "✗ Error creating social activity table: " . $conn->error . "\n";
        exit(1);
    }

    // Add comment_count and reaction_count columns to videos table
    $alterVideosSql = "ALTER TABLE videos
        ADD COLUMN comment_count INT DEFAULT 0,
        ADD COLUMN reaction_count INT DEFAULT 0,
        ADD INDEX idx_videos_comment_count (comment_count DESC),
        ADD INDEX idx_videos_reaction_count (reaction_count DESC)";

    if ($conn->query($alterVideosSql) === TRUE) {
        echo "✓ Videos table updated with social counters\n";
    } else {
        // Check if columns already exist
        if (strpos($conn->error, 'Duplicate column') === false) {
            echo "✗ Error updating videos table: " . $conn->error . "\n";
        } else {
            echo "✓ Videos table social counters already exist\n";
        }
    }

    // Create some sample data for testing
    echo "\nAdding sample social data...\n";

    // Add sample comments
    $sampleComments = [
        [1, 1, "This sermon really touched my heart. Thank you for sharing!", null, 1],
        [1, 1, "Great message about faith and perseverance.", null, 1],
        [2, 1, "Amazing worship session! The music was beautiful.", null, 1],
        [2, 1, "Love this new song. It's become my favorite.", null, 1],
    ];

    $commentStmt = $conn->prepare("INSERT IGNORE INTO comments (video_id, user_id, content, parent_id, is_approved) VALUES (?, ?, ?, ?, ?)");
    $commentCount = 0;

    foreach ($sampleComments as $comment) {
        $commentStmt->bind_param("iisii", $comment[0], $comment[1], $comment[2], $comment[3], $comment[4]);
        if ($commentStmt->execute()) {
            $commentCount++;
        }
    }

    echo "✓ Added $commentCount sample comments\n";

    // Add sample reactions
    $sampleReactions = [
        [1, 1, 'amen'],
        [1, 1, 'praise'],
        [2, 1, 'love'],
        [2, 1, 'pray'],
        [3, 1, 'thankful'],
    ];

    $reactionStmt = $conn->prepare("INSERT IGNORE INTO reactions (video_id, user_id, reaction_type) VALUES (?, ?, ?)");
    $reactionCount = 0;

    foreach ($sampleReactions as $reaction) {
        $reactionStmt->bind_param("iis", $reaction[0], $reaction[1], $reaction[2]);
        if ($reactionStmt->execute()) {
            $reactionCount++;
        }
    }

    echo "✓ Added $reactionCount sample reactions\n";

    // Update video counters
    $updateCountersSql = "
        UPDATE videos v
        LEFT JOIN (
            SELECT video_id, COUNT(*) as comment_count
            FROM comments
            WHERE is_approved = 1
            GROUP BY video_id
        ) c ON v.id = c.video_id
        LEFT JOIN (
            SELECT video_id, COUNT(*) as reaction_count
            FROM reactions
            GROUP BY video_id
        ) r ON v.id = r.video_id
        SET v.comment_count = COALESCE(c.comment_count, 0),
            v.reaction_count = COALESCE(r.reaction_count, 0)
        WHERE v.id IN (SELECT DISTINCT video_id FROM comments UNION SELECT DISTINCT video_id FROM reactions)
    ";

    if ($conn->query($updateCountersSql) === TRUE) {
        echo "✓ Video counters updated\n";
    }

    $conn->close();
    echo "\n🎉 Social features tables created successfully!\n";
    echo "\nNext steps:\n";
    echo "1. Run this script: php create_social_tables.php\n";
    echo "2. Update API routes in backend/api/index.php\n";
    echo "3. Create frontend social components\n";

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>