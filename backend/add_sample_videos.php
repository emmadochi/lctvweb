<?php
/**
 * Add Sample Videos Script
 * Adds sample YouTube videos to the database for testing
 */

require_once 'config/database.php';
require_once 'models/Video.php';

echo "LCMTV Sample Videos Addition\n";
echo "============================\n\n";

try {
    $conn = getDBConnection();

    // Sample videos data
    $sampleVideos = [
        [
            'youtube_id' => 'dQw4w9WgXcQ',
            'title' => 'Rick Astley - Never Gonna Give You Up (Official Music Video)',
            'description' => 'The official music video for "Never Gonna Give You Up" by Rick Astley. This iconic 80s hit has become a cultural phenomenon.',
            'thumbnail_url' => 'https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
            'channel_title' => 'Rick Astley',
            'channel_id' => 'UCuAXFkgsw1L7xaCfnd5JJOw',
            'published_at' => '2009-10-25T06:57:33Z',
            'tags' => ['rick astley', 'never gonna give you up', '80s', 'music video', 'classic'],
            'view_count' => 1500000000,
            'like_count' => 18000000,
            'duration' => 213,
            'category_id' => 3 // Music
        ],
        [
            'youtube_id' => '9bZkp7q19f0',
            'title' => 'PSY - GANGNAM STYLE (강남스타일) M/V',
            'description' => 'PSY - GANGNAM STYLE (강남스타일) M/V',
            'thumbnail_url' => 'https://img.youtube.com/vi/9bZkp7q19f0/hqdefault.jpg',
            'channel_title' => 'officialpsy',
            'channel_id' => 'UC4QobU6STFB0P71PMvOGN5A',
            'published_at' => '2012-07-15T07:46:32Z',
            'tags' => ['psy', 'gangnam style', 'korean', 'music video', 'viral'],
            'view_count' => 5000000000,
            'like_count' => 25000000,
            'duration' => 252,
            'category_id' => 3 // Music
        ],
        [
            'youtube_id' => 'jNQXAC9IVRw',
            'title' => 'Me at the zoo',
            'description' => 'The very first video uploaded to YouTube, showing co-founder Steve Chen at the San Diego Zoo.',
            'thumbnail_url' => 'https://img.youtube.com/vi/jNQXAC9IVRw/hqdefault.jpg',
            'channel_title' => 'jawed',
            'channel_id' => 'UC4QobU6STFB0P71PMvOGN5A',
            'published_at' => '2005-04-23T21:45:11Z',
            'tags' => ['youtube', 'first video', 'history', 'zoo'],
            'view_count' => 300000000,
            'like_count' => 5000000,
            'duration' => 19,
            'category_id' => 6 // Tech
        ],
        [
            'youtube_id' => 'kJQP7kiw5Fk',
            'title' => 'Despacito - Luis Fonsi ft. Daddy Yankee',
            'description' => 'Despacito" by Luis Fonsi ft. Daddy Yankee (Official Video)',
            'thumbnail_url' => 'https://img.youtube.com/vi/kJQP7kiw5Fk/hqdefault.jpg',
            'channel_title' => 'LuisFonsiVEVO',
            'channel_id' => 'UCxoq-PAQeAdk_zyg8YS0JzQ',
            'published_at' => '2017-01-12T05:00:00Z',
            'tags' => ['despacito', 'luis fonsi', 'daddy yankee', 'reggaeton', 'latin music'],
            'view_count' => 8500000000,
            'like_count' => 40000000,
            'duration' => 282,
            'category_id' => 3 // Music
        ],
        [
            'youtube_id' => 'hTWKbfoikeg',
            'title' => 'Nirvana - Smells Like Teen Spirit (Official Music Video)',
            'description' => 'Nirvana\'s official music video for "Smells Like Teen Spirit"',
            'thumbnail_url' => 'https://img.youtube.com/vi/hTWKbfoikeg/hqdefault.jpg',
            'channel_title' => 'NirvanaVEVO',
            'channel_id' => 'UCqhnX4jA0A5paNd1v-zEyw',
            'published_at' => '2009-06-16T21:26:39Z',
            'tags' => ['nirvana', 'smells like teen spirit', 'grunge', '90s rock', 'kurt cobain'],
            'view_count' => 2000000000,
            'like_count' => 15000000,
            'duration' => 301,
            'category_id' => 3 // Music
        ],
        [
            'youtube_id' => 'kffacxfA7G4',
            'title' => 'Baby Shark Dance | @Pinkfong Official',
            'description' => 'Baby Shark Dance! The most viewed video on YouTube!',
            'thumbnail_url' => 'https://img.youtube.com/vi/kffacxfA7G4/hqdefault.jpg',
            'channel_title' => 'Pinkfong Baby Shark - Kids\' Songs & Stories',
            'channel_id' => 'UCcdwLMPsaU2ezNSJU1Zj6MQ',
            'published_at' => '2016-06-17T12:00:00Z',
            'tags' => ['baby shark', 'kids songs', 'children', 'dance', 'educational'],
            'view_count' => 15000000000,
            'like_count' => 30000000,
            'duration' => 125,
            'category_id' => 5 // Kids
        ],
        [
            'youtube_id' => 'JGwWNGJdvx8',
            'title' => 'Ed Sheeran - Shape of You (Official Music Video)',
            'description' => 'Ed Sheeran - Shape of You (Official Music Video)',
            'thumbnail_url' => 'https://img.youtube.com/vi/JGwWNGJdvx8/hqdefault.jpg',
            'channel_title' => 'Ed Sheeran',
            'channel_id' => 'UC0C-w0YjGpqDXGB8IHb662A',
            'published_at' => '2017-01-30T05:00:00Z',
            'tags' => ['ed sheeran', 'shape of you', 'pop', 'music video', '2017'],
            'view_count' => 6000000000,
            'like_count' => 25000000,
            'duration' => 234,
            'category_id' => 3 // Music
        ],
        [
            'youtube_id' => 'lp-EO5I60KA',
            'title' => 'Johann Sebastian Bach - Air on the G String',
            'description' => 'Beautiful classical music by Johann Sebastian Bach',
            'thumbnail_url' => 'https://img.youtube.com/vi/lp-EO5I60KA/hqdefault.jpg',
            'channel_title' => 'Classical Music',
            'channel_id' => 'UC1M9cFCF5nC8Qw0RdR3Rj5Q',
            'published_at' => '2015-03-15T10:00:00Z',
            'tags' => ['bach', 'classical music', 'air on g string', 'orchestral suite'],
            'view_count' => 50000000,
            'like_count' => 800000,
            'duration' => 305,
            'category_id' => 3 // Music
        ],
        [
            'youtube_id' => 'sBws8MSXN7A',
            'title' => 'Avengers: Endgame - Official Trailer',
            'description' => 'Watch the official trailer for Avengers: Endgame',
            'thumbnail_url' => 'https://img.youtube.com/vi/sBws8MSXN7A/hqdefault.jpg',
            'channel_title' => 'Marvel Entertainment',
            'channel_id' => 'UCvC4D8onUfXzvjTOM-dBfEA',
            'published_at' => '2019-03-14T13:00:00Z',
            'tags' => ['avengers', 'endgame', 'marvel', 'mcu', 'superhero'],
            'view_count' => 300000000,
            'like_count' => 5000000,
            'duration' => 195,
            'category_id' => 7 // Movies
        ],
        [
            'youtube_id' => 'V-_O7nl0Ii0',
            'title' => 'Apple iPhone 15 Pro - Titanium',
            'description' => 'Introducing iPhone 15 Pro with titanium design',
            'thumbnail_url' => 'https://img.youtube.com/vi/V-_O7nl0Ii0/hqdefault.jpg',
            'channel_title' => 'Apple',
            'channel_id' => 'UCE_M8A5yxnLfW0KghEeajjw',
            'published_at' => '2023-09-12T18:00:00Z',
            'tags' => ['apple', 'iphone 15 pro', 'titanium', 'smartphone', 'technology'],
            'view_count' => 50000000,
            'like_count' => 1000000,
            'duration' => 180,
            'category_id' => 6 // Tech
        ],
        [
            'youtube_id' => 'kXYiU_JCYtU',
            'title' => 'Neymar Jr. Best Skills & Goals 2023',
            'description' => 'Amazing skills and goals from Neymar Jr.',
            'thumbnail_url' => 'https://img.youtube.com/vi/kXYiU_JCYtU/hqdefault.jpg',
            'channel_title' => 'Futbol Highlights',
            'channel_id' => 'UCqZQJ4600a9wIfMpZWB-5Ew',
            'published_at' => '2023-12-01T15:00:00Z',
            'tags' => ['neymar', 'football', 'soccer', 'skills', 'goals'],
            'view_count' => 25000000,
            'like_count' => 800000,
            'duration' => 420,
            'category_id' => 2 // Sports
        ],
        [
            'youtube_id' => 'fJ9rUzIMcZQ',
            'title' => 'Breaking News: Major Weather Event',
            'description' => 'Stay informed with the latest weather updates',
            'thumbnail_url' => 'https://img.youtube.com/vi/fJ9rUzIMcZQ/hqdefault.jpg',
            'channel_title' => 'News Network',
            'channel_id' => 'UCYfdidRxbB8Qhf0Nx7ioOYw',
            'published_at' => '2024-01-15T08:00:00Z',
            'tags' => ['news', 'weather', 'breaking news', 'updates'],
            'view_count' => 5000000,
            'like_count' => 50000,
            'duration' => 180,
            'category_id' => 1 // News
        ]
    ];

    $inserted = 0;
    $skipped = 0;

    foreach ($sampleVideos as $videoData) {
        // Check if video already exists
        $existing = Video::getByYouTubeId($videoData['youtube_id']);

        if ($existing) {
            echo "Skipping existing video: {$videoData['title']}\n";
            $skipped++;
            continue;
        }

        // Create the video
        $videoId = Video::create([
            'youtube_id' => $videoData['youtube_id'],
            'title' => $videoData['title'],
            'description' => $videoData['description'],
            'thumbnail_url' => $videoData['thumbnail_url'],
            'channel_title' => $videoData['channel_title'],
            'channel_id' => $videoData['channel_id'],
            'published_at' => $videoData['published_at'],
            'tags' => json_encode($videoData['tags']),
            'view_count' => $videoData['view_count'],
            'like_count' => $videoData['like_count'],
            'duration' => $videoData['duration'],
            'category_id' => $videoData['category_id'],
            'is_active' => 1
        ]);

        if ($videoId) {
            echo "✓ Added: {$videoData['title']}\n";
            $inserted++;
        } else {
            echo "✗ Failed to add: {$videoData['title']}\n";
        }
    }

    echo "\n🎉 Sample videos addition complete!\n";
    echo "=================================\n";
    echo "Videos inserted: $inserted\n";
    echo "Videos skipped (already exist): $skipped\n";
    echo "Total videos in database: " . Video::count() . "\n\n";

    echo "Sample videos by category:\n";
    $categories = [
        1 => 'News',
        2 => 'Sports',
        3 => 'Music',
        4 => 'Sermons',
        5 => 'Kids',
        6 => 'Tech',
        7 => 'Movies'
    ];

    foreach ($categories as $id => $name) {
        $videos = Video::getByCategory($id);
        echo "  $name: " . count($videos) . " videos\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>





