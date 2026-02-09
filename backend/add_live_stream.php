<?php
/**
 * Add Live Stream Script
 * Adds a well-arranged live stream to the database
 */

require_once 'config/database.php';
require_once 'models/Livestream.php';

echo "Adding Live Stream to Database\n";
echo "=============================\n\n";

try {
    $conn = getDBConnection();
    echo "✓ Connected to database\n";

    // First, ensure the livestreams table exists
    $createTableSql = "CREATE TABLE IF NOT EXISTS livestreams (
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

    if ($conn->query($createTableSql) === TRUE) {
        echo "✓ Livestreams table verified/created\n";
    } else {
        echo "✗ Error creating livestreams table: " . $conn->error . "\n";
        exit(1);
    }

    // Check if categories table exists and get sample categories
    $categories = [];
    $categoryCheck = "SHOW TABLES LIKE 'categories'";
    $result = $conn->query($categoryCheck);
    
    if ($result && $result->num_rows > 0) {
        $catQuery = "SELECT id, name FROM categories LIMIT 5";
        $catResult = $conn->query($catQuery);
        while ($row = $catResult->fetch_assoc()) {
            $categories[] = $row;
        }
    }

    // If no categories found, create some sample ones
    if (empty($categories)) {
        echo "Creating sample categories...\n";
        $createCatTable = "CREATE TABLE IF NOT EXISTS categories (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        $conn->query($createCatTable);
        
        $sampleCategories = [
            ['name' => 'News', 'description' => 'Latest news and current events'],
            ['name' => 'Sports', 'description' => 'Sports events and highlights'],
            ['name' => 'Music', 'description' => 'Music performances and concerts'],
            ['name' => 'Gaming', 'description' => 'Gaming streams and content'],
            ['name' => 'Entertainment', 'description' => 'Entertainment and lifestyle content']
        ];
        
        $stmt = $conn->prepare("INSERT IGNORE INTO categories (name, description) VALUES (?, ?)");
        foreach ($sampleCategories as $cat) {
            $stmt->bind_param("ss", $cat['name'], $cat['description']);
            $stmt->execute();
        }
        
        // Reload categories
        $catQuery = "SELECT id, name FROM categories LIMIT 5";
        $catResult = $conn->query($catQuery);
        while ($row = $catResult->fetch_assoc()) {
            $categories[] = $row;
        }
    }

    echo "Available categories:\n";
    foreach ($categories as $cat) {
        echo "  - ID: {$cat['id']}, Name: {$cat['name']}\n";
    }

    // Sample live stream data - well arranged and realistic
    $liveStreams = [
        [
            'youtube_id' => '5qap5aO4i9A', // Popular lofi hip hop live stream
            'title' => 'Lofi Hip Hop Radio - Beats to Relax/Study To 📚',
            'description' => 'Lofi hip hop beats to relax, study, sleep or work to. Chill beats with no lyrics that help you focus and stay productive.',
            'thumbnail_url' => 'https://img.youtube.com/vi/5qap5aO4i9A/maxresdefault.jpg',
            'channel_title' => 'Lofi Girl',
            'channel_id' => 'UCSJ4gkVC6NrvII8umztf0Ow',
            'viewer_count' => 35420,
            'category_id' => array_search('Music', array_column($categories, 'name')) !== false ? 
                           $categories[array_search('Music', array_column($categories, 'name'))]['id'] : $categories[0]['id'],
            'is_live' => true
        ],
        [
            'youtube_id' => 'dQw4w9WgXcQ', // Classic test video
            'title' => 'LCMTV News Live Broadcast',
            'description' => 'Breaking news and live coverage from around the world. Stay informed with 24/7 news updates.',
            'thumbnail_url' => 'https://img.youtube.com/vi/dQw4w9WgXcQ/maxresdefault.jpg',
            'channel_title' => 'LCMTV News',
            'channel_id' => 'UC4QobU6STFB0P71PMvOGN5A',
            'viewer_count' => 12850,
            'category_id' => array_search('News', array_column($categories, 'name')) !== false ? 
                           $categories[array_search('News', array_column($categories, 'name'))]['id'] : $categories[0]['id'],
            'is_live' => true
        ],
        [
            'youtube_id' => '9bZkp7q19f0', // Another popular stream
            'title' => 'Gaming Live: Fortnite Tournament Finals',
            'description' => 'Watch the epic Fortnite tournament finals live! Top players competing for the championship.',
            'thumbnail_url' => 'https://img.youtube.com/vi/9bZkp7q19f0/maxresdefault.jpg',
            'channel_title' => 'LCMTV Gaming',
            'channel_id' => 'UCXGgrKt94gR6lmN4aN3mYTg',
            'viewer_count' => 28750,
            'category_id' => array_search('Gaming', array_column($categories, 'name')) !== false ? 
                           $categories[array_search('Gaming', array_column($categories, 'name'))]['id'] : $categories[0]['id'],
            'is_live' => true
        ]
    ];

    // Add each live stream
    echo "\nAdding live streams to database...\n";
    
    foreach ($liveStreams as $index => $stream) {
        // Check if stream already exists
        $existing = Livestream::findByYoutubeId($stream['youtube_id']);
        
        if ($existing) {
            echo "Stream already exists: {$stream['title']}\n";
            continue;
        }
        
        // Insert the stream
        $result = Livestream::create($stream);
        
        if ($result) {
            echo "✓ Added: {$stream['title']} (Category: " . 
                 ($categories[array_search($stream['category_id'], array_column($categories, 'id'))]['name'] ?? 'Unknown') . ")\n";
        } else {
            echo "✗ Failed to add: {$stream['title']}\n";
        }
    }

    // Verify the data was added
    echo "\nVerifying live streams in database:\n";
    $allStreams = Livestream::getAll();
    
    if (!empty($allStreams)) {
        foreach ($allStreams as $stream) {
            $categoryName = 'Unknown';
            foreach ($categories as $cat) {
                if ($cat['id'] == $stream['category_id']) {
                    $categoryName = $cat['name'];
                    break;
                }
            }
            
            echo "ID: {$stream['id']} | Title: {$stream['title']} | Category: {$categoryName} | Viewers: {$stream['viewer_count']}\n";
        }
    } else {
        echo "No live streams found in database.\n";
    }

    $conn->close();
    echo "\n✓ Live stream addition complete!\n";

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>