<?php
/**
 * Add Livestreams Table Script
 * Creates the livestreams table if it doesn't exist
 */

require_once 'config/database.php';

echo "Adding Livestreams Table\n";
echo "=======================\n\n";

try {
    $conn = getDBConnection();
    echo "✓ Connected to database\n";

    // Create livestreams table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS livestreams (
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
    )";

    if ($conn->query($sql) === TRUE) {
        echo "✓ Livestreams table created or already exists\n";

        // Check if table has any data, if not, add some sample data
        $checkSql = "SELECT COUNT(*) as count FROM livestreams";
        $result = $conn->query($checkSql);
        $row = $result->fetch_assoc();

        if ($row['count'] == 0) {
            echo "✓ Adding sample livestream data...\n";

            $sampleStreams = [
                [
                    'youtube_id' => 'dQw4w9WgXcQ',
                    'title' => 'Sample Live Stream 1',
                    'description' => 'This is a sample live stream for testing',
                    'thumbnail_url' => 'https://img.youtube.com/vi/dQw4w9WgXcQ/maxresdefault.jpg',
                    'channel_title' => 'LCMTV Live',
                    'channel_id' => 'lcmtvlive',
                    'viewer_count' => 1250,
                    'category_id' => 1,
                    'is_live' => true
                ],
                [
                    'youtube_id' => '9bZkp7q19f0',
                    'title' => 'Sample Live Stream 2',
                    'description' => 'Another sample live stream for testing',
                    'thumbnail_url' => 'https://img.youtube.com/vi/9bZkp7q19f0/maxresdefault.jpg',
                    'channel_title' => 'LCMTV Live 2',
                    'channel_id' => 'lcmtvlive2',
                    'viewer_count' => 850,
                    'category_id' => 2,
                    'is_live' => true
                ]
            ];

            $stmt = $conn->prepare("INSERT INTO livestreams
                (youtube_id, title, description, thumbnail_url, channel_title, channel_id, viewer_count, category_id, is_live)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

            foreach ($sampleStreams as $stream) {
                $stmt->bind_param(
                    "ssssssiii",
                    $stream['youtube_id'],
                    $stream['title'],
                    $stream['description'],
                    $stream['thumbnail_url'],
                    $stream['channel_title'],
                    $stream['channel_id'],
                    $stream['viewer_count'],
                    $stream['category_id'],
                    $stream['is_live']
                );
                $stmt->execute();
            }

            echo "✓ Sample livestream data added\n";
        } else {
            echo "✓ Livestreams table already has data\n";
        }

    } else {
        echo "✗ Error creating livestreams table: " . $conn->error . "\n";
    }

    $conn->close();
    echo "\n✓ Setup complete!\n";

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>
