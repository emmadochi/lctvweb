<?php
/**
 * Content Import Command Line Tool
 * Import YouTube content into LCMTV database
 *
 * Usage:
 * php import_content.php url "https://www.youtube.com/watch?v=VIDEO_ID" 1
 * php import_content.php keyword "search term" 1 20
 * php import_content.php playlist "PLAYLIST_ID" 1 50
 * php import_content.php channel "CHANNEL_ID" 1 20
 * php import_content.php trending 1 10
 * php import_content.php livestream_url "https://www.youtube.com/watch?v=LIVESTREAM_ID" 1
 * php import_content.php livestream_channel "CHANNEL_ID" 1 5
 * php import_content.php initial
 */

require_once 'utils/EnvLoader.php';
require_once 'utils/ContentIngestion.php';

echo "LCMTV Content Import Tool\n";
echo "=========================\n\n";

if ($argc < 2) {
    showUsage();
    exit(1);
}

$command = $argv[1];
$ingestion = new ContentIngestion();

switch ($command) {
    case 'url':
        if ($argc < 4) {
            echo "Error: Missing parameters for URL import\n";
            echo "Usage: php import_content.php url \"VIDEO_URL\" category_id\n";
            exit(1);
        }

        $videoUrl = $argv[2];
        $categoryId = (int)$argv[3];

        echo "Importing video from URL: $videoUrl\n";
        echo "Category ID: $categoryId\n\n";

        $count = $ingestion->importByUrl($videoUrl, $categoryId);
        echo "\nImport complete: $count video imported\n";
        break;

    case 'keyword':
        if ($argc < 5) {
            echo "Error: Missing parameters for keyword import\n";
            echo "Usage: php import_content.php keyword \"search term\" category_id [limit]\n";
            exit(1);
        }

        $keyword = $argv[2];
        $categoryId = (int)$argv[3];
        $limit = isset($argv[4]) ? (int)$argv[4] : 20;

        echo "Importing videos by keyword: '$keyword'\n";
        echo "Category ID: $categoryId\n";
        echo "Limit: $limit\n\n";

        $count = $ingestion->importByKeyword($keyword, $categoryId, $limit);
        echo "\nImport complete: $count videos imported\n";
        break;

    case 'playlist':
        if ($argc < 5) {
            echo "Error: Missing parameters for playlist import\n";
            echo "Usage: php import_content.php playlist \"PLAYLIST_ID\" category_id [limit]\n";
            exit(1);
        }

        $playlistId = $argv[2];
        $categoryId = (int)$argv[3];
        $limit = isset($argv[4]) ? (int)$argv[4] : 50;

        echo "Importing videos from playlist: $playlistId\n";
        echo "Category ID: $categoryId\n";
        echo "Limit: $limit\n\n";

        $count = $ingestion->importFromPlaylist($playlistId, $categoryId, $limit);
        echo "\nImport complete: $count videos imported\n";
        break;

    case 'channel':
        if ($argc < 5) {
            echo "Error: Missing parameters for channel import\n";
            echo "Usage: php import_content.php channel \"CHANNEL_ID\" category_id [limit]\n";
            exit(1);
        }

        $channelId = $argv[2];
        $categoryId = (int)$argv[3];
        $limit = isset($argv[4]) ? (int)$argv[4] : 20;

        echo "Importing videos from channel: $channelId\n";
        echo "Category ID: $categoryId\n";
        echo "Limit: $limit\n\n";

        $count = $ingestion->importFromChannel($channelId, $categoryId, $limit);
        echo "\nImport complete: $count videos imported\n";
        break;

    case 'trending':
        if ($argc < 4) {
            echo "Error: Missing parameters for trending import\n";
            echo "Usage: php import_content.php trending category_id [limit] [region]\n";
            exit(1);
        }

        $categoryId = (int)$argv[2];
        $limit = isset($argv[3]) ? (int)$argv[3] : 10;
        $region = isset($argv[4]) ? $argv[4] : 'US';

        echo "Importing trending videos\n";
        echo "Category ID: $categoryId\n";
        echo "Limit: $limit\n";
        echo "Region: $region\n\n";

        $count = $ingestion->importTrending($categoryId, $limit, $region);
        echo "\nImport complete: $count videos imported\n";
        break;

    case 'initial':
        echo "Running initial content import...\n";
        echo "This will import a curated set of videos across all categories.\n\n";

        $count = $ingestion->runInitialImport();
        echo "\n🎉 Initial import complete! Total videos: $count\n";
        break;

    case 'livestream_url':
        if ($argc < 4) {
            echo "Error: Missing parameters for livestream URL import\n";
            echo "Usage: php import_content.php livestream_url \"LIVESTREAM_URL\" category_id\n";
            exit(1);
        }

        $livestreamUrl = $argv[2];
        $categoryId = (int)$argv[3];

        echo "Importing livestream from URL: $livestreamUrl\n";
        echo "Category ID: $categoryId\n\n";

        $count = $ingestion->importLivestreamByUrl($livestreamUrl, $categoryId);
        echo "\nImport complete: $count livestream imported\n";
        break;

    case 'livestream_channel':
        if ($argc < 5) {
            echo "Error: Missing parameters for livestream channel import\n";
            echo "Usage: php import_content.php livestream_channel \"CHANNEL_ID\" category_id [limit]\n";
            exit(1);
        }

        $channelId = $argv[2];
        $categoryId = (int)$argv[3];
        $limit = isset($argv[4]) ? (int)$argv[4] : 5;

        echo "Importing live streams from channel: $channelId\n";
        echo "Category ID: $categoryId\n";
        echo "Limit: $limit\n\n";

        $count = $ingestion->importLiveFromChannel($channelId, $categoryId, $limit);
        echo "\nImport complete: $count live streams imported\n";
        break;

    case 'stats':
        showDatabaseStats();
        break;

    case 'help':
    default:
        showUsage();
        break;
}

function showUsage() {
    echo "Usage:\n";
    echo "  php import_content.php url \"VIDEO_URL\" category_id\n";
    echo "  php import_content.php keyword \"search term\" category_id [limit]\n";
    echo "  php import_content.php playlist \"PLAYLIST_ID\" category_id [limit]\n";
    echo "  php import_content.php channel \"CHANNEL_ID\" category_id [limit]\n";
    echo "  php import_content.php trending category_id [limit] [region]\n";
    echo "  php import_content.php livestream_url \"LIVESTREAM_URL\" category_id\n";
    echo "  php import_content.php livestream_channel \"CHANNEL_ID\" category_id [limit]\n";
    echo "  php import_content.php initial\n";
    echo "  php import_content.php stats\n";
    echo "  php import_content.php help\n\n";

    echo "Examples:\n";
    echo "  php import_content.php url \"https://www.youtube.com/watch?v=dQw4w9WgXcQ\" 1\n";
    echo "  php import_content.php keyword \"breaking news\" 1 20\n";
    echo "  php import_content.php playlist \"PLrAXtmRdnEQy2i3XjK9aZVjLkjlF2j6\" 2 50\n";
    echo "  php import_content.php channel \"UC4QobU6STFB0P71PMvOGN5A\" 3 15\n";
    echo "  php import_content.php trending 1 10 US\n";
    echo "  php import_content.php livestream_url \"https://www.youtube.com/watch?v=LIVESTREAM_ID\" 1\n";
    echo "  php import_content.php livestream_channel \"UC4QobU6STFB0P71PMvOGN5A\" 1 5\n";
    echo "  php import_content.php initial\n\n";

    echo "Categories:\n";
    echo "  1: News\n";
    echo "  2: Sports\n";
    echo "  3: Music\n";
    echo "  4: Sermons\n";
    echo "  5: Kids\n";
    echo "  6: Tech\n";
    echo "  7: Movies\n";
}

function showDatabaseStats() {
    require_once 'config/database.php';
    require_once 'models/Category.php';
    require_once 'models/Video.php';

    echo "Database Statistics:\n";
    echo "===================\n";

    try {
        $categories = Category::getActiveCategories();
        $totalVideos = Video::count();

        echo "Categories: " . count($categories) . "\n";
        echo "Total Videos: $totalVideos\n\n";

        echo "Videos by Category:\n";
        foreach ($categories as $category) {
            $videos = Video::getByCategory($category['id']);
            echo "  {$category['name']}: " . count($videos) . " videos\n";
        }

    } catch (Exception $e) {
        echo "Error getting stats: " . $e->getMessage() . "\n";
    }
}
?>
