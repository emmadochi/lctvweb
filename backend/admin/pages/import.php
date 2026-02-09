<?php
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../../utils/ContentIngestion.php';

    $ingestion = new ContentIngestion();

    try {
        $importType = $_POST['import_type'];
        $categoryId = (int)$_POST['category_id'];

        switch ($importType) {
            case 'url':
                $videoUrl = trim($_POST['video_url']);

                if (empty($videoUrl)) {
                    throw new Exception('Video URL is required');
                }

                // Validate URL format
                if (!filter_var($videoUrl, FILTER_VALIDATE_URL)) {
                    throw new Exception('Invalid URL format');
                }

                $count = $ingestion->importByUrl($videoUrl, $categoryId);
                $message = $count > 0 ? "Successfully imported video from URL" : "Failed to import video from URL";
                $messageType = $count > 0 ? 'success' : 'error';
                break;

            case 'keyword':
                $keyword = trim($_POST['keyword']);
                $limit = (int)($_POST['limit'] ?? 20);

                if (empty($keyword)) {
                    throw new Exception('Keyword is required');
                }

                $count = $ingestion->importByKeyword($keyword, $categoryId, $limit);
                $message = "Successfully imported $count videos for keyword: '$keyword'";
                $messageType = 'success';
                break;

            case 'playlist':
                $playlistId = trim($_POST['playlist_id']);
                $limit = (int)($_POST['limit'] ?? 50);

                if (empty($playlistId)) {
                    throw new Exception('Playlist ID is required');
                }

                $count = $ingestion->importFromPlaylist($playlistId, $categoryId, $limit);
                $message = "Successfully imported $count videos from playlist";
                $messageType = 'success';
                break;

            case 'channel':
                $channelId = trim($_POST['channel_id']);
                $limit = (int)($_POST['limit'] ?? 20);

                if (empty($channelId)) {
                    throw new Exception('Channel ID is required');
                }

                $count = $ingestion->importFromChannel($channelId, $categoryId, $limit);
                $message = "Successfully imported $count videos from channel";
                $messageType = 'success';
                break;

            case 'trending':
                $limit = (int)($_POST['limit'] ?? 10);
                $region = trim($_POST['region'] ?? 'US');

                $count = $ingestion->importTrending($categoryId, $limit, $region);
                $message = "Successfully imported $count trending videos from $region";
                $messageType = 'success';
                break;

            case 'initial':
                $count = $ingestion->runInitialImport();
                $message = "Initial import complete! Imported $count videos across all categories.";
                $messageType = 'success';
                break;

            case 'livestream_url':
                $livestreamUrl = trim($_POST['livestream_url']);

                if (empty($livestreamUrl)) {
                    throw new Exception('Livestream URL is required');
                }

                if (!filter_var($livestreamUrl, FILTER_VALIDATE_URL)) {
                    throw new Exception('Invalid URL format');
                }

                $count = $ingestion->importLivestreamByUrl($livestreamUrl, $categoryId);
                $message = $count > 0 ? "Successfully imported livestream from URL" : "Failed to import livestream from URL";
                $messageType = $count > 0 ? 'success' : 'error';
                break;

            case 'livestream_channel':
                $channelId = trim($_POST['channel_id']);
                $limit = (int)($_POST['limit'] ?? 5);

                if (empty($channelId)) {
                    throw new Exception('Channel ID is required');
                }

                $count = $ingestion->importLiveFromChannel($channelId, $categoryId, $limit);
                $message = "Successfully imported $count live streams from channel";
                $messageType = $count > 0 ? 'success' : 'info';
                break;

            default:
                throw new Exception('Invalid import type');
        }

    } catch (Exception $e) {
        $message = 'Import failed: ' . $e->getMessage();
        $messageType = 'error';
    }
}
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Import Content</h1>
        <p class="text-gray-600">Import videos from YouTube to populate your TV platform</p>
    </div>

    <!-- Messages -->
    <?php if ($message): ?>
    <div class="rounded-md p-4 <?php echo $messageType === 'success' ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'; ?>">
        <div class="flex">
            <div class="flex-shrink-0">
                <?php if ($messageType === 'success'): ?>
                <i class="fas fa-check-circle text-green-400"></i>
                <?php else: ?>
                <i class="fas fa-exclamation-circle text-red-400"></i>
                <?php endif; ?>
            </div>
            <div class="ml-3">
                <p class="text-sm font-medium <?php echo $messageType === 'success' ? 'text-green-800' : 'text-red-800'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Import Forms -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Import Livestream by URL -->
        <div class="bg-gradient-to-br from-red-50 to-pink-50 rounded-lg shadow-sm p-6 border border-red-200">
            <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                <i class="fas fa-broadcast-tower text-red-500 mr-2"></i>
                Import Livestream by URL
            </h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="import_type" value="livestream_url">

                <div>
                    <label for="livestream_url" class="block text-sm font-medium text-gray-700">YouTube Livestream URL</label>
                    <input
                        type="url"
                        id="livestream_url"
                        name="livestream_url"
                        required
                        placeholder="https://www.youtube.com/watch?v=LIVESTREAM_ID"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-red-500 focus:border-red-500"
                    >
                    <p class="mt-1 text-sm text-gray-500">Paste a YouTube livestream URL (must be currently live)</p>
                </div>

                <div>
                    <label for="livestream_category" class="block text-sm font-medium text-gray-700">Category</label>
                    <select
                        id="livestream_category"
                        name="category_id"
                        required
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-red-500 focus:border-red-500"
                    >
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button
                    type="submit"
                    class="w-full bg-red-500 text-white py-2 px-4 rounded-md hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors"
                >
                    <i class="fas fa-play-circle mr-2"></i> Import Live Stream
                </button>
            </form>
        </div>

        <!-- Import Live from Channel -->
        <div class="bg-gradient-to-br from-red-50 to-pink-50 rounded-lg shadow-sm p-6 border border-red-200">
            <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                <i class="fas fa-user-circle text-red-500 mr-2"></i>
                Import Live from Channel
            </h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="import_type" value="livestream_channel">

                <div>
                    <label for="livestream_channel_id" class="block text-sm font-medium text-gray-700">Channel ID or Handle</label>
                    <input
                        type="text"
                        id="livestream_channel_id"
                        name="channel_id"
                        required
                        placeholder="e.g., UC4QobU6STFB0P71PMvOGN5A or @channelname"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-red-500 focus:border-red-500"
                    >
                    <p class="mt-1 text-sm text-gray-500">Find channel ID in YouTube URL or use @handle</p>
                </div>

                <div>
                    <label for="livestream_channel_category" class="block text-sm font-medium text-gray-700">Category</label>
                    <select
                        id="livestream_channel_category"
                        name="category_id"
                        required
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-red-500 focus:border-red-500"
                    >
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="livestream_limit" class="block text-sm font-medium text-gray-700">Max Live Streams</label>
                    <input
                        type="number"
                        id="livestream_limit"
                        name="limit"
                        min="1"
                        max="10"
                        value="5"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-red-500 focus:border-red-500"
                    >
                </div>

                <button
                    type="submit"
                    class="w-full bg-red-500 text-white py-2 px-4 rounded-md hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors"
                >
                    <i class="fas fa-search mr-2"></i> Find Live Streams
                </button>
            </form>
        </div>
    </div>

    <!-- Regular Video Imports -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-8">
        <!-- Import by URL -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Import by YouTube URL</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="import_type" value="url">

                <div>
                    <label for="video_url" class="block text-sm font-medium text-gray-700">YouTube Video URL</label>
                    <input
                        type="url"
                        id="video_url"
                        name="video_url"
                        required
                        placeholder="https://www.youtube.com/watch?v=dQw4w9WgXcQ"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-orange-500 focus:border-orange-500"
                    >
                    <p class="mt-1 text-sm text-gray-500">Paste any YouTube video URL (watch, embed, shorts, etc.)</p>
                </div>

                <div>
                    <label for="url_category" class="block text-sm font-medium text-gray-700">Category</label>
                    <select
                        id="url_category"
                        name="category_id"
                        required
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-orange-500 focus:border-orange-500"
                    >
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button
                    type="submit"
                    class="w-full bg-red-500 text-white py-2 px-4 rounded-md hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors"
                >
                    <i class="fas fa-link mr-2"></i> Import Single Video
                </button>
            </form>
        </div>

        <!-- Keyword Search -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Import by Keyword</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="import_type" value="keyword">

                <div>
                    <label for="keyword" class="block text-sm font-medium text-gray-700">Search Keyword</label>
                    <input
                        type="text"
                        id="keyword"
                        name="keyword"
                        required
                        placeholder="e.g., breaking news, music videos"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-orange-500 focus:border-orange-500"
                    >
                </div>

                <div>
                    <label for="keyword_category" class="block text-sm font-medium text-gray-700">Category</label>
                    <select
                        id="keyword_category"
                        name="category_id"
                        required
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-orange-500 focus:border-orange-500"
                    >
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="keyword_limit" class="block text-sm font-medium text-gray-700">Limit</label>
                    <input
                        type="number"
                        id="keyword_limit"
                        name="limit"
                        min="1"
                        max="50"
                        value="20"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-orange-500 focus:border-orange-500"
                    >
                </div>

                <button
                    type="submit"
                    class="w-full bg-orange-500 text-white py-2 px-4 rounded-md hover:bg-orange-600 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 transition-colors"
                >
                    <i class="fas fa-search mr-2"></i> Import by Keyword
                </button>
            </form>
        </div>

        <!-- Playlist Import -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Import from Playlist</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="import_type" value="playlist">

                <div>
                    <label for="playlist_id" class="block text-sm font-medium text-gray-700">Playlist ID</label>
                    <input
                        type="text"
                        id="playlist_id"
                        name="playlist_id"
                        required
                        placeholder="e.g., PLrAXtmRdnEQy2i3XjK9aZVjLkjlF2j6"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-orange-500 focus:border-orange-500"
                    >
                    <p class="mt-1 text-sm text-gray-500">Find playlist ID in YouTube URL</p>
                </div>

                <div>
                    <label for="playlist_category" class="block text-sm font-medium text-gray-700">Category</label>
                    <select
                        id="playlist_category"
                        name="category_id"
                        required
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-orange-500 focus:border-orange-500"
                    >
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="playlist_limit" class="block text-sm font-medium text-gray-700">Limit</label>
                    <input
                        type="number"
                        id="playlist_limit"
                        name="limit"
                        min="1"
                        max="50"
                        value="50"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-orange-500 focus:border-orange-500"
                    >
                </div>

                <button
                    type="submit"
                    class="w-full bg-blue-500 text-white py-2 px-4 rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
                >
                    <i class="fas fa-list mr-2"></i> Import from Playlist
                </button>
            </form>
        </div>

        <!-- Channel Import -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Import from Channel</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="import_type" value="channel">

                <div>
                    <label for="channel_id" class="block text-sm font-medium text-gray-700">Channel ID</label>
                    <input
                        type="text"
                        id="channel_id"
                        name="channel_id"
                        required
                        placeholder="e.g., UC4QobU6STFB0P71PMvOGN5A"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-orange-500 focus:border-orange-500"
                    >
                    <p class="mt-1 text-sm text-gray-500">Find channel ID in YouTube URL or use handle</p>
                </div>

                <div>
                    <label for="channel_category" class="block text-sm font-medium text-gray-700">Category</label>
                    <select
                        id="channel_category"
                        name="category_id"
                        required
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-orange-500 focus:border-orange-500"
                    >
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="channel_limit" class="block text-sm font-medium text-gray-700">Limit</label>
                    <input
                        type="number"
                        id="channel_limit"
                        name="limit"
                        min="1"
                        max="50"
                        value="20"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-orange-500 focus:border-orange-500"
                    >
                </div>

                <button
                    type="submit"
                    class="w-full bg-green-500 text-white py-2 px-4 rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors"
                >
                    <i class="fas fa-user mr-2"></i> Import from Channel
                </button>
            </form>
        </div>

        <!-- Trending & Initial Import -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Quick Imports</h3>
            <div class="space-y-4">
                <!-- Trending Videos -->
                <form method="POST" class="space-y-3">
                    <input type="hidden" name="import_type" value="trending">
                    <h4 class="font-medium text-gray-900">Trending Videos</h4>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="trending_limit" class="block text-sm text-gray-600">Limit</label>
                            <input
                                type="number"
                                id="trending_limit"
                                name="limit"
                                min="1"
                                max="50"
                                value="10"
                                class="block w-full px-2 py-1 border border-gray-300 rounded text-sm"
                            >
                        </div>
                        <div>
                            <label for="trending_region" class="block text-sm text-gray-600">Region</label>
                            <select
                                id="trending_region"
                                name="region"
                                class="block w-full px-2 py-1 border border-gray-300 rounded text-sm"
                            >
                                <option value="US">US</option>
                                <option value="GB">UK</option>
                                <option value="CA">Canada</option>
                                <option value="AU">Australia</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label for="trending_category" class="block text-sm text-gray-600">Category</label>
                        <select
                            id="trending_category"
                            name="category_id"
                            required
                            class="block w-full px-2 py-1 border border-gray-300 rounded text-sm"
                        >
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button
                        type="submit"
                        class="w-full bg-purple-500 text-white py-2 px-3 rounded text-sm hover:bg-purple-600 transition-colors"
                    >
                        <i class="fas fa-fire mr-1"></i> Import Trending
                    </button>
                </form>

                <hr>

                <!-- Initial Import -->
                <form method="POST">
                    <input type="hidden" name="import_type" value="initial">
                    <h4 class="font-medium text-gray-900 mb-2">Initial Setup</h4>
                    <p class="text-sm text-gray-600 mb-3">Import a curated set of videos across all categories</p>
                    <button
                        type="submit"
                        class="w-full bg-orange-500 text-white py-2 px-3 rounded text-sm hover:bg-orange-600 transition-colors"
                    >
                        <i class="fas fa-magic mr-1"></i> Run Initial Import
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Command Line Instructions -->
    <div class="bg-gray-50 rounded-lg p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Command Line Import</h3>
        <p class="text-gray-600 mb-4">You can also import content using the command line tool:</p>

        <div class="bg-gray-800 text-green-400 p-4 rounded font-mono text-sm overflow-x-auto">
            <div class="space-y-2">
                <div># Import single video by URL</div>
                <div>php import_content.php url "https://www.youtube.com/watch?v=dQw4w9WgXcQ" 1</div>
                <div></div>
                <div># Import by keyword</div>
                <div>php import_content.php keyword "breaking news" 1 20</div>
                <div></div>
                <div># Import from playlist</div>
                <div>php import_content.php playlist "PLAYLIST_ID" 2 50</div>
                <div></div>
                <div># Import from channel</div>
                <div>php import_content.php channel "CHANNEL_ID" 3 15</div>
                <div></div>
                <div># Import trending videos</div>
                <div>php import_content.php trending 1 10 US</div>
                <div></div>
                <div># Import single livestream by URL</div>
                <div>php import_content.php livestream_url "https://www.youtube.com/watch?v=LIVESTREAM_ID" 1</div>
                <div></div>
                <div># Import live streams from channel</div>
                <div>php import_content.php livestream_channel "CHANNEL_ID" 1 5</div>
                <div></div>
                <div># Run initial import</div>
                <div>php import_content.php initial</div>
            </div>
        </div>
    </div>
</div>
