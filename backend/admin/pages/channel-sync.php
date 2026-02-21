<?php
// Prevent direct access - this file should be included from admin/index.php
if (!defined('ADMIN_ACCESS') && !isset($_SESSION['admin_logged_in'])) {
    header('Location: ../index.php');
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/YouTubeAPI.php';
require_once __DIR__ . '/../../models/Category.php';

$message = '';
$messageType = '';
$syncStats = [];
$channels = [];
$categories = [];

// Get database connection
$conn = getDBConnection();

// Get categories for dropdown
$categories = Category::getActiveCategories();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'add_channel':
                $channelId = trim($_POST['channel_id']);
                $categoryId = (int)$_POST['category_id'];
                $autoImport = isset($_POST['auto_import']);
                $importLimit = (int)($_POST['import_limit'] ?? 10);
                
                if (empty($channelId)) {
                    throw new Exception('Channel ID is required');
                }
                
                if ($categoryId <= 0) {
                    throw new Exception('Please select a category');
                }
                
                // Check if channel already exists
                $stmt = $conn->prepare("SELECT id FROM channel_sync WHERE channel_id = ?");
                $stmt->bind_param("s", $channelId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    throw new Exception('Channel already exists in synchronization list');
                }
                
                // Add channel to sync table
                $stmt = $conn->prepare("INSERT INTO channel_sync (channel_id, category_id, auto_import, max_videos_per_sync, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->bind_param("siis", $channelId, $categoryId, $autoImport, $importLimit);
                
                if ($stmt->execute()) {
                    $message = 'Channel added successfully for synchronization';
                    $messageType = 'success';
                } else {
                    throw new Exception('Failed to add channel');
                }
                break;
                
            case 'delete_channel':
                $channelId = (int)$_POST['channel_id'];
                
                $stmt = $conn->prepare("DELETE FROM channel_sync WHERE id = ?");
                $stmt->bind_param("i", $channelId);
                
                if ($stmt->execute()) {
                    $message = 'Channel removed from synchronization';
                    $messageType = 'success';
                } else {
                    throw new Exception('Failed to remove channel');
                }
                break;
                
            case 'sync_now':
                $channelId = (int)$_POST['channel_id'];
                
                // Get channel details
                $stmt = $conn->prepare("SELECT * FROM channel_sync WHERE id = ?");
                $stmt->bind_param("i", $channelId);
                $stmt->execute();
                $result = $stmt->get_result();
                $channel = $result->fetch_assoc();
                
                if (!$channel) {
                    throw new Exception('Channel not found');
                }
                
                // Use YouTube API to get channel videos
                $youtube = new YouTubeAPI();
                $videos = $youtube->getChannelVideos($channel['channel_id'], $channel['max_videos_per_sync']);
                
                $importedCount = 0;
                $failedCount = 0;
                
                foreach ($videos as $video) {
                    // Check if video already exists
                    $stmt = $conn->prepare("SELECT id FROM videos WHERE youtube_id = ?");
                    $stmt->bind_param("s", $video['youtube_id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        continue; // Skip existing videos
                    }
                    
                    // Insert new video
                    $stmt = $conn->prepare("INSERT INTO videos (youtube_id, title, description, thumbnail_url, duration, category_id, created_at, is_active) VALUES (?, ?, ?, ?, ?, ?, NOW(), 1)");
                    $stmt->bind_param("sssssi", $video['youtube_id'], $video['title'], $video['description'], $video['thumbnail_url'], $video['duration'], $channel['category_id']);
                    
                    if ($stmt->execute()) {
                        $importedCount++;
                    } else {
                        $failedCount++;
                    }
                }
                
                $message = "Synchronization complete! Imported: $importedCount, Failed: $failedCount";
                $messageType = $importedCount > 0 ? 'success' : 'info';
                break;
        }
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Get current sync statistics
$syncStats = [
    'total_channels' => 0,
    'active_channels' => 0,
    'total_videos_imported' => 0,
    'last_sync' => 'Never'
];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM channel_sync");
$stmt->execute();
$result = $stmt->get_result();
$syncStats['total_channels'] = $result->fetch_assoc()['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as active FROM channel_sync WHERE auto_import = 1");
$stmt->execute();
$result = $stmt->get_result();
$syncStats['active_channels'] = $result->fetch_assoc()['active'];

// Get channels list
$stmt = $conn->prepare("SELECT cs.*, c.name as category_name FROM channel_sync cs LEFT JOIN categories c ON cs.category_id = c.id ORDER BY cs.created_at DESC");
$stmt->execute();
$result = $stmt->get_result();
$channels = $result->fetch_all(MYSQLI_ASSOC);
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Channel Synchronization</h1>
        <p class="text-gray-600">Automatically sync videos from YouTube channels to your platform</p>
    </div>

    <!-- Messages -->
    <?php if ($message): ?>
        <div class="rounded-lg p-4 <?php echo $messageType === 'success' ? 'bg-green-50 text-green-800' : ($messageType === 'error' ? 'bg-red-50 text-red-800' : 'bg-blue-50 text-blue-800'); ?>">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : ($messageType === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'); ?>"></i>
                </div>
                <div class="ml-3">
                    <p><?php echo htmlspecialchars($message); ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center">
                <div class="p-2 bg-blue-100 rounded-lg">
                    <i class="fas fa-tv text-blue-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Total Channels</p>
                    <p class="text-2xl font-semibold text-gray-900"><?php echo $syncStats['total_channels']; ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center">
                <div class="p-2 bg-green-100 rounded-lg">
                    <i class="fas fa-sync-alt text-green-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Auto Sync</p>
                    <p class="text-2xl font-semibold text-gray-900"><?php echo $syncStats['active_channels']; ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center">
                <div class="p-2 bg-purple-100 rounded-lg">
                    <i class="fas fa-video text-purple-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Videos Imported</p>
                    <p class="text-2xl font-semibold text-gray-900"><?php echo $syncStats['total_videos_imported']; ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center">
                <div class="p-2 bg-orange-100 rounded-lg">
                    <i class="fas fa-clock text-orange-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Last Sync</p>
                    <p class="text-lg font-semibold text-gray-900"><?php echo $syncStats['last_sync']; ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Channel Form -->
    <div class="bg-white rounded-lg shadow-sm p-6">
        <h2 class="text-lg font-medium text-gray-900 mb-4">Add Channel for Synchronization</h2>
        <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input type="hidden" name="action" value="add_channel">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">YouTube Channel ID</label>
                <input type="text" name="channel_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="UC_x5XG1OV2P6uZZ5FSM9Ttw">
                <p class="mt-1 text-xs text-gray-500">Find your channel ID in YouTube Studio</p>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                <select name="category_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Import Limit</label>
                <input type="number" name="import_limit" value="10" min="1" max="50" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                <p class="mt-1 text-xs text-gray-500">Maximum videos to import per sync</p>
            </div>
            
            <div class="flex items-center">
                <input type="checkbox" name="auto_import" id="auto_import" class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                <label for="auto_import" class="ml-2 block text-sm text-gray-700">Enable Automatic Import</label>
            </div>
            
            <div class="md:col-span-2">
                <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors">
                    <i class="fas fa-plus mr-2"></i>Add Channel
                </button>
            </div>
        </form>
    </div>

    <!-- Channels List -->
    <div class="bg-white rounded-lg shadow-sm">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-medium text-gray-900">Managed Channels</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Channel</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Auto Import</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Import Limit</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($channels)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                <i class="fas fa-tv text-2xl mb-2"></i>
                                <p>No channels configured for synchronization</p>
                                <p class="text-sm">Add your first channel above to get started</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($channels as $channel): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                                                <i class="fas fa-tv text-blue-600"></i>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($channel['channel_id']); ?></div>
                                            <div class="text-sm text-gray-500">Added <?php echo date('M j, Y', strtotime($channel['created_at'])); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($channel['category_name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $channel['auto_import'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                        <?php echo $channel['auto_import'] ? 'Enabled' : 'Disabled'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo $channel['max_videos_per_sync']; ?> videos
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <form method="POST" class="inline-block mr-2">
                                        <input type="hidden" name="action" value="sync_now">
                                        <input type="hidden" name="channel_id" value="<?php echo $channel['id']; ?>">
                                        <button type="submit" class="text-blue-600 hover:text-blue-900">
                                            <i class="fas fa-sync-alt mr-1"></i>Sync Now
                                        </button>
                                    </form>
                                    <form method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to remove this channel?')">
                                        <input type="hidden" name="action" value="delete_channel">
                                        <input type="hidden" name="channel_id" value="<?php echo $channel['id']; ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-900">
                                            <i class="fas fa-trash mr-1"></i>Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Setup Instructions -->
    <div class="bg-blue-50 rounded-lg p-6">
        <h3 class="text-lg font-medium text-blue-900 mb-2">How to Find Your YouTube Channel ID</h3>
        <ol class="list-decimal list-inside space-y-2 text-blue-800">
            <li>Go to <a href="https://studio.youtube.com/" target="_blank" class="underline">YouTube Studio</a></li>
            <li>Click on your profile picture in the top right</li>
            <li>Select "Settings" from the dropdown menu</li>
            <li>In the left sidebar, click "Channel" then "Advanced settings"</li>
            <li>Your Channel ID will be displayed under "Channel ID"</li>
        </ol>
        <p class="mt-3 text-blue-800">
            <i class="fas fa-lightbulb mr-1"></i>Tip: You can also find your channel ID by visiting your channel page and looking at the URL
        </p>
    </div>
</div>