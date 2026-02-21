<?php
// Prevent direct access - this file should be included from admin/index.php
if (!defined('ADMIN_ACCESS') && !isset($_SESSION['admin_logged_in'])) {
    header('Location: ../index.php');
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/YouTubeAPI.php';
require_once __DIR__ . '/../../models/Category.php';
require_once __DIR__ . '/../../services/ChannelSyncService.php';

$message = '';
$messageType = '';
$syncStats = [];
$channels = [];
$categories = [];

// Get database connection
$conn = getDBConnection();

// Initialize services
$channelSyncService = new ChannelSyncService();
$youtubeAPI = new YouTubeAPI();

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
                
                // Add channel with default settings
                $channelSyncId = $channelSyncService->addChannel($channelId, $categoryId, null, 'daily', $autoImport, false, $importLimit);
                
                $message = "Channel added successfully! ID: $channelSyncId";
                $messageType = 'success';
                break;
                
            case 'configure_playlists':
                $channelSyncId = (int)$_POST['channel_sync_id'];
                $playlistMappings = $_POST['playlist_mappings'] ?? [];
                
                // Clear existing mappings
                $stmt = $conn->prepare("UPDATE channel_playlist_mapping SET is_active = FALSE WHERE channel_sync_id = ?");
                $stmt->bind_param("i", $channelSyncId);
                $stmt->execute();
                
                // Add new mappings
                foreach ($playlistMappings as $mapping) {
                    $playlistId = $mapping['playlist_id'];
                    $categoryId = (int)$mapping['category_id'];
                    $importLimit = (int)($mapping['import_limit'] ?? 10);
                    $requireApproval = isset($mapping['require_approval']);
                    $playlistName = $mapping['playlist_name'] ?? '';
                    
                    if ($categoryId > 0) {
                        $channelSyncService->addPlaylistMapping($channelSyncId, $playlistId, $playlistName, $categoryId, $importLimit, $requireApproval);
                    }
                }
                
                $message = "Playlist mappings configured successfully!";
                $messageType = 'success';
                break;
                
            case 'manual_override':
                $videoId = (int)$_POST['video_id'];
                $newCategoryId = (int)$_POST['category_id'];
                $reason = trim($_POST['reason']);
                $userId = $_SESSION['admin_id'] ?? null;
                
                $channelSyncService->overrideVideoCategory($videoId, $newCategoryId, $reason, $userId);
                
                $message = "Video category overridden successfully!";
                $messageType = 'success';
                break;
                
            case 'sync_channel':
                $channelSyncId = (int)$_POST['channel_sync_id'];
                
                // Get channel config
                $stmt = $conn->prepare("SELECT * FROM channel_sync WHERE id = ? AND is_active = TRUE");
                $stmt->bind_param("i", $channelSyncId);
                $stmt->execute();
                $channelConfig = $stmt->get_result()->fetch_assoc();
                
                if (!$channelConfig) {
                    throw new Exception('Channel not found or inactive');
                }
                
                // Run playlist-aware sync
                $result = $channelSyncService->syncChannelWithPlaylists($channelConfig);
                
                // Ensure all required keys exist with default values
                $videosFound = $result['videos_found'] ?? 0;
                $videosImported = $result['videos_imported'] ?? 0;
                $videosSkipped = $result['videos_skipped'] ?? 0;
                
                $message = "Synchronization complete! Found: {$videosFound}, Imported: {$videosImported}, Skipped: {$videosSkipped}";
                $messageType = $videosImported > 0 ? 'success' : 'info';
                break;
                
            case 'remove_channel':
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
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Get channel list with sync status
$stmt = $conn->prepare("SELECT cs.*, c.name as category_name, 
    (SELECT COUNT(*) FROM channel_playlist_mapping WHERE channel_sync_id = cs.id AND is_active = TRUE) as playlist_count
    FROM channel_sync cs 
    LEFT JOIN categories c ON cs.category_id = c.id 
    ORDER BY cs.created_at DESC");
$stmt->execute();
$channels = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get sync statistics
$syncStats = $channelSyncService->getSyncStats();
?>

<div class="container mx-auto px-4 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Channel Synchronization</h1>
        <p class="text-gray-600 mt-2">Manage YouTube channel synchronization with playlist mapping</p>
    </div>

    <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-100 text-green-800' : ($messageType === 'error' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800'); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Stats Overview -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center">
                <div class="p-2 bg-blue-100 rounded-lg">
                    <i class="fas fa-tv text-blue-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Active Channels</p>
                    <p class="text-lg font-semibold text-gray-900"><?php echo $syncStats['active_channels']; ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center">
                <div class="p-2 bg-green-100 rounded-lg">
                    <i class="fas fa-list text-green-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Mapped Playlists</p>
                    <p class="text-lg font-semibold text-gray-900">
                        <?php 
                        $totalPlaylists = array_sum(array_column($channels, 'playlist_count'));
                        echo $totalPlaylists;
                        ?>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center">
                <div class="p-2 bg-purple-100 rounded-lg">
                    <i class="fas fa-sync text-purple-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Recent Syncs</p>
                    <p class="text-lg font-semibold text-gray-900"><?php echo count($syncStats['recent_syncs']); ?></p>
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
                    <p class="text-lg font-semibold text-gray-900"><?php echo $syncStats['last_sync'] ?? 'Never'; ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Channel Management -->
    <div class="bg-white rounded-lg shadow-sm mb-8">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-900">Channel Management</h2>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Channel</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Default Category</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mapped Playlists</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Sync</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($channels as $channel): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($channel['channel_name']); ?></div>
                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($channel['channel_id']); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo htmlspecialchars($channel['category_name']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo $channel['playlist_count']; ?> playlists mapped
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo $channel['last_sync'] ? date('M j, Y g:i A', strtotime($channel['last_sync'])) : 'Never'; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <button onclick="configurePlaylists(<?php echo $channel['id']; ?>, '<?php echo htmlspecialchars($channel['channel_id']); ?>')" 
                                    class="text-blue-600 hover:text-blue-900 mr-3">
                                <i class="fas fa-cog"></i> Configure Playlists
                            </button>
                            <button onclick="syncChannel(<?php echo $channel['id']; ?>)" 
                                    class="text-green-600 hover:text-green-900 mr-3">
                                <i class="fas fa-sync"></i> Sync Now
                            </button>
                            <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to remove this channel?')">
                                <input type="hidden" name="action" value="remove_channel">
                                <input type="hidden" name="channel_id" value="<?php echo $channel['id']; ?>">
                                <button type="submit" class="text-red-600 hover:text-red-900">
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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
                <label class="block text-sm font-medium text-gray-700 mb-1">Default Category</label>
                <select name="category_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Select a category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="mt-1 text-xs text-gray-500">Used when no playlist mapping is configured</p>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Import Limit</label>
                <input type="number" name="import_limit" value="10" min="1" max="100" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                <p class="mt-1 text-xs text-gray-500">Maximum videos per sync</p>
            </div>
            
            <div class="flex items-center">
                <input type="checkbox" name="auto_import" id="auto_import" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" checked>
                <label for="auto_import" class="ml-2 block text-sm text-gray-900">Auto-import videos</label>
            </div>
            
            <div class="md:col-span-2">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    <i class="fas fa-plus mr-2"></i>Add Channel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Playlist Configuration Modal -->
<div id="playlistModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Configure Playlist Mapping</h3>
                <button onclick="closePlaylistModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div id="playlistConfigContent" class="max-h-96 overflow-y-auto">
                <!-- Content will be loaded via AJAX -->
            </div>
            
            <div class="mt-6 flex justify-end space-x-3">
                <button onclick="closePlaylistModal()" class="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">
                    Cancel
                </button>
                <button onclick="savePlaylistMapping()" class="px-4 py-2 text-white bg-blue-600 rounded-md hover:bg-blue-700">
                    Save Configuration
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Manual Override Modal -->
<div id="overrideModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Manual Category Override</h3>
                <button onclick="closeOverrideModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="overrideForm" method="POST">
                <input type="hidden" name="action" value="manual_override">
                <input type="hidden" name="video_id" id="override_video_id">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">New Category</label>
                    <select name="category_id" id="override_category_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Select a category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reason for Override</label>
                    <textarea name="reason" id="override_reason" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Explain why this category change is needed..."></textarea>
                </div>
            </form>
            
            <div class="mt-6 flex justify-end space-x-3">
                <button onclick="closeOverrideModal()" class="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">
                    Cancel
                </button>
                <button onclick="saveOverride()" class="px-4 py-2 text-white bg-blue-600 rounded-md hover:bg-blue-700">
                    Apply Override
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentChannelSyncId = null;

function configurePlaylists(channelSyncId, channelId) {
    currentChannelSyncId = channelSyncId;
    
    // Show loading state
    document.getElementById('playlistConfigContent').innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-2xl text-blue-600"></i><p class="mt-2">Loading playlists...</p></div>';
    document.getElementById('playlistModal').classList.remove('hidden');
    
    // Fetch playlist data
    const ajaxUrl = '../ajax_get_playlists.php';
    fetch(`${ajaxUrl}?channel_id=${encodeURIComponent(channelId)}&channel_sync_id=${channelSyncId}`, {
        credentials: 'same-origin'
    })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                displayPlaylistMappingForm(data.playlists, data.mappings);
            } else {
                document.getElementById('playlistConfigContent').innerHTML = `<div class="text-center py-8 text-red-600"><i class="fas fa-exclamation-circle text-2xl"></i><p class="mt-2">Error: ${data.message}</p></div>`;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('playlistConfigContent').innerHTML = `<div class="text-center py-8 text-red-600"><i class="fas fa-exclamation-circle text-2xl"></i><p class="mt-2">Error loading playlists: ${error.message}</p><p class="text-sm mt-2">Please check your YouTube API key and channel ID</p></div>`;
        });
}

function displayPlaylistMappingForm(playlists, existingMappings) {
    let html = '<form id="playlistMappingForm">';
    html += '<input type="hidden" name="action" value="configure_playlists">';
    html += '<input type="hidden" name="channel_sync_id" value="' + currentChannelSyncId + '">';
    
    if (playlists.length === 0) {
        html += '<div class="text-center py-8 text-gray-500"><i class="fas fa-info-circle text-2xl"></i><p class="mt-2">No playlists found for this channel</p></div>';
    } else {
        playlists.forEach((playlist, index) => {
            const existingMapping = existingMappings.find(m => m.playlist_id === playlist.playlist_id);
            const isChecked = existingMapping ? 'checked' : '';
            const categoryId = existingMapping ? existingMapping.category_id : '';
            const importLimit = existingMapping ? existingMapping.import_limit : '10';
            const requireApproval = existingMapping ? existingMapping.require_approval : false;
            
            html += `
            <div class="border border-gray-200 rounded-lg p-4 mb-4">
                <div class="flex items-start">
                    <input type="checkbox" name="playlist_mappings[${index}][enabled]" value="1" ${isChecked} 
                           class="mt-1 h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded playlist-checkbox"
                           data-index="${index}">
                    <div class="ml-3 flex-1">
                        <h4 class="text-sm font-medium text-gray-900">${playlist.title}</h4>
                        <p class="text-xs text-gray-500 mt-1">${playlist.video_count} videos</p>
                        <input type="hidden" name="playlist_mappings[${index}][playlist_id]" value="${playlist.playlist_id}">
                        <input type="hidden" name="playlist_mappings[${index}][playlist_name]" value="${playlist.title.replace(/"/g, '&quot;')}">
                        
                        <div class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-3 mapping-controls" style="display: ${isChecked ? 'grid' : 'none'};">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Category</label>
                                <select name="playlist_mappings[${index}][category_id]" class="w-full text-xs px-2 py-1 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500">
                                    <option value="">Select category</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" ${categoryId == <?php echo $category['id']; ?> ? 'selected' : ''}>${'<?php echo htmlspecialchars($category['name']); ?>'}</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Import Limit</label>
                                <input type="number" name="playlist_mappings[${index}][import_limit]" value="${importLimit}" min="1" max="100" 
                                       class="w-full text-xs px-2 py-1 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500">
                            </div>
                            <div class="flex items-end">
                                <label class="flex items-center text-xs">
                                    <input type="checkbox" name="playlist_mappings[${index}][require_approval]" ${requireApproval ? 'checked' : ''} 
                                           class="h-3 w-3 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <span class="ml-2">Require Approval</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            `;
        });
    }
    
    html += '</form>';
    document.getElementById('playlistConfigContent').innerHTML = html;
    
    // Add event listeners for checkbox toggling
    document.querySelectorAll('.playlist-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const index = this.dataset.index;
            const controls = this.closest('.flex').querySelector('.mapping-controls');
            controls.style.display = this.checked ? 'grid' : 'none';
        });
    });
}

function savePlaylistMapping() {
    const form = document.getElementById('playlistMappingForm');
    if (!form) return;
    
    const formData = new FormData(form);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closePlaylistModal();
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error saving playlist mapping');
    });
}

function syncChannel(channelSyncId) {
    if (confirm('Are you sure you want to sync this channel now?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="sync_channel">
            <input type="hidden" name="channel_sync_id" value="${channelSyncId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function closePlaylistModal() {
    document.getElementById('playlistModal').classList.add('hidden');
}

function closeOverrideModal() {
    document.getElementById('overrideModal').classList.add('hidden');
}

function saveOverride() {
    const form = document.getElementById('overrideForm');
    const formData = new FormData(form);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeOverrideModal();
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error saving override');
    });
}

// Add event listener for manual override buttons (if video management is on the same page)
document.addEventListener('click', function(e) {
    if (e.target.closest('.manual-override-btn')) {
        const videoId = e.target.closest('.manual-override-btn').dataset.videoId;
        document.getElementById('override_video_id').value = videoId;
        document.getElementById('overrideModal').classList.remove('hidden');
    }
});
</script>