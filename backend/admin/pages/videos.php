<?php
// Prevent direct access - this file should be included from admin/index.php
if (!defined('ADMIN_ACCESS') && !isset($_SESSION['admin_logged_in'])) {
    header('Location: ../index.php');
    exit();
}

require_once __DIR__ . '/../../services/ChannelSyncService.php';

// Handle actions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $videoId = (int)($_POST['video_id'] ?? 0);

    try {
        switch ($action) {
            case 'delete':
                if (Video::delete($videoId)) {
                    $message = 'Video deleted successfully';
                    $messageType = 'success';
                } else {
                    throw new Exception('Failed to delete video');
                }
                break;

            case 'batch_delete':
                $selectedVideos = $_POST['selected_videos'] ?? [];
                if (empty($selectedVideos)) {
                    throw new Exception('No videos selected for deletion');
                }

                $videoIds = array_map('intval', $selectedVideos);
                if (Video::batchDelete($videoIds)) {
                    $deletedCount = count($videoIds);
                    $message = $deletedCount . ' video' . ($deletedCount > 1 ? 's' : '') . ' deleted successfully';
                    $messageType = 'success';
                } else {
                    throw new Exception('Failed to delete selected videos');
                }
                break;
                
            case 'manual_override':
                $videoId = (int)$_POST['video_id'];
                $newCategoryId = (int)$_POST['category_id'];
                $reason = trim($_POST['reason']);
                $isPremium = isset($_POST['is_premium']) ? 1 : 0;
                $price = (float)($_POST['price'] ?? 0.00);
                $userId = $_SESSION['admin_id'] ?? null;
                
                $channelSyncService = new ChannelSyncService();
                $channelSyncService->overrideVideoCategory($videoId, $newCategoryId, $reason, $userId);
                
                // Update premium status
                Video::update($videoId, [
                    'title' => $_POST['video_title'] ?? '', // We need to be careful here, maybe just update specific fields
                    'is_premium' => $isPremium,
                    'price' => $price,
                    'category_id' => $newCategoryId,
                    // Keep other fields same by fetching them first or supporting partial update
                ]);
                
                // Better: Let's fetch the video first to keep existing data
                $videoData = Video::getById($videoId);
                if ($videoData) {
                    $videoData['category_id'] = $newCategoryId;
                    $videoData['is_premium'] = $isPremium;
                    $videoData['price'] = $price;
                    Video::update($videoId, $videoData);
                }
                
                $message = "Video category overridden successfully!";
                $messageType = 'success';
                break;

            case 'toggle_active':
                // This would require updating the model to support toggling active status
                $message = 'Feature not implemented yet';
                $messageType = 'info';
                break;
        }
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Pagination
$page = (int)($_GET['page_num'] ?? 1);
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get videos with pagination
$conn = getDBConnection();
$stmt = $conn->prepare("
    SELECT v.*, c.name as category_name
    FROM videos v
    LEFT JOIN categories c ON v.category_id = c.id
    WHERE v.is_active = 1
    ORDER BY v.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->bind_param("ii", $perPage, $offset);
$stmt->execute();
$result = $stmt->get_result();

$videos = [];
while ($row = $result->fetch_assoc()) {
    $videos[] = $row;
}

// Get total count for pagination
$totalResult = $conn->query("SELECT COUNT(*) as total FROM videos WHERE is_active = 1");
$totalVideos = $totalResult->fetch_assoc()['total'];
$totalPages = ceil($totalVideos / $perPage);
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Videos Management</h1>
            <p class="text-gray-600">Manage your video content</p>
        </div>
        <a
            href="?page=import"
            class="bg-orange-500 text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition-colors"
        >
            <i class="fas fa-plus mr-2"></i> Import Videos
        </a>
    </div>

    <!-- Messages -->
    <?php if ($message): ?>
    <div class="rounded-md p-4 <?php
        echo $messageType === 'success' ? 'bg-green-50 border border-green-200' :
             ($messageType === 'error' ? 'bg-red-50 border border-red-200' :
              'bg-blue-50 border border-blue-200');
    ?>">
        <div class="flex">
            <div class="flex-shrink-0">
                <?php if ($messageType === 'success'): ?>
                <i class="fas fa-check-circle text-green-400"></i>
                <?php elseif ($messageType === 'error'): ?>
                <i class="fas fa-exclamation-circle text-red-400"></i>
                <?php else: ?>
                <i class="fas fa-info-circle text-blue-400"></i>
                <?php endif; ?>
            </div>
            <div class="ml-3">
                <p class="text-sm font-medium <?php
                    echo $messageType === 'success' ? 'text-green-800' :
                         ($messageType === 'error' ? 'text-red-800' : 'text-blue-800');
                ?>">
                    <?php echo htmlspecialchars($message); ?>
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Videos Table -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-medium text-gray-900">All Videos (<?php echo $totalVideos; ?>)</h3>
                <div class="flex space-x-2">
                    <button type="button" id="selectAllBtn" class="px-3 py-1 text-sm bg-gray-100 text-gray-700 rounded hover:bg-gray-200">
                        Select All
                    </button>
                    <button type="button" id="deselectAllBtn" class="px-3 py-1 text-sm bg-gray-100 text-gray-700 rounded hover:bg-gray-200 hidden">
                        Deselect All
                    </button>
                    <button type="button" id="deleteSelectedBtn" class="px-3 py-1 text-sm bg-red-600 text-white rounded hover:bg-red-700 hidden" disabled>
                        <i class="fas fa-trash mr-1"></i> Delete Selected
                    </button>
                </div>
            </div>
        </div>

        <!-- Bulk Delete Form -->
        <form method="POST" id="bulkDeleteForm">
            <input type="hidden" name="action" id="formAction" value="batch_delete">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <input type="checkbox" id="selectAllCheckbox" class="rounded border-gray-300 text-orange-600 focus:ring-orange-500">
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Video</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pricing</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Target Role</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Views</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($videos)): ?>
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                            <i class="fas fa-video text-4xl mb-4"></i>
                            <p class="text-lg">No videos found</p>
                            <p class="text-sm">Import some videos to get started</p>
                            <a
                                href="?page=import"
                                class="mt-4 inline-block bg-orange-500 text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition-colors"
                            >
                                Import Videos
                            </a>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($videos as $video): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <input type="checkbox" name="selected_videos[]" value="<?php echo $video['id']; ?>" class="video-checkbox rounded border-gray-300 text-orange-600 focus:ring-orange-500">
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-12 w-18">
                                    <img
                                        class="h-12 w-18 rounded object-cover"
                                        src="<?php echo htmlspecialchars($video['thumbnail_url']); ?>"
                                        alt="<?php echo htmlspecialchars($video['title']); ?>"
                                    >
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900 max-w-xs truncate">
                                        <?php echo htmlspecialchars($video['title']); ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?php echo htmlspecialchars($video['youtube_id']); ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                <?php echo htmlspecialchars($video['category_name'] ?? 'Uncategorized'); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php if ($video['is_premium']): ?>
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gold-100 text-gold-800 border border-gold-200" style="background-color: #fef3c7; color: #92400e;">
                                Premium ($<?php echo number_format($video['price'], 2); ?>)
                            </span>
                            <?php else: ?>
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                Free
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php 
                                echo ($video['target_role'] ?? 'general') === 'general' ? 'bg-gray-100 text-gray-800' : 'bg-purple-100 text-purple-800'; 
                            ?>">
                                <?php echo ucfirst($video['target_role'] ?? 'General'); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo number_format($video['view_count']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo date('M j, Y', strtotime($video['created_at'])); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex space-x-2">
                                <a
                                    href="https://www.youtube.com/watch?v=<?php echo $video['youtube_id']; ?>"
                                    target="_blank"
                                    class="text-blue-600 hover:text-blue-900"
                                    title="View on YouTube"
                                >
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                                <button type="button" 
                                        class="text-orange-600 hover:text-orange-900 manual-override-btn" 
                                        title="Video Settings"
                                        data-video-id="<?php echo $video['id']; ?>"
                                        data-video-title="<?php echo htmlspecialchars($video['title']); ?>"
                                        data-category-id="<?php echo $video['category_id']; ?>"
                                        data-is-premium="<?php echo $video['is_premium']; ?>"
                                        data-price="<?php echo $video['price']; ?>">
                                    <i class="fas fa-cog"></i>
                                </button>
                                <button type="button" class="text-red-600 hover:text-red-900 delete-single-btn" title="Delete Video" data-video-id="<?php echo $video['id']; ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        </form>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
            <div class="flex items-center justify-between">
                <div class="text-sm text-gray-700">
                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalVideos); ?> of <?php echo $totalVideos; ?> videos
                </div>
                <div class="flex space-x-1">
                    <?php if ($page > 1): ?>
                    <a
                        href="?page=videos&page_num=<?php echo $page - 1; ?>"
                        class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"
                    >
                        Previous
                    </a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <a
                        href="?page=videos&page_num=<?php echo $i; ?>"
                        class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium <?php echo $i === $page ? 'text-orange-600 bg-orange-50' : 'text-gray-700 hover:bg-gray-50'; ?>"
                    >
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                    <a
                        href="?page=videos&page_num=<?php echo $page + 1; ?>"
                        class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"
                    >
                        Next
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- JavaScript for bulk operations -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            const selectAllBtn = document.getElementById('selectAllBtn');
            const deselectAllBtn = document.getElementById('deselectAllBtn');
            const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
            const videoCheckboxes = document.querySelectorAll('.video-checkbox');
            const bulkDeleteForm = document.getElementById('bulkDeleteForm');

            // Update delete button state based on selected checkboxes
            function updateDeleteButton() {
                const checkedBoxes = document.querySelectorAll('.video-checkbox:checked');
                const hasSelection = checkedBoxes.length > 0;

                deleteSelectedBtn.disabled = !hasSelection;
                deleteSelectedBtn.classList.toggle('hidden', !hasSelection);

                // Update select/deselect all button visibility
                const allChecked = videoCheckboxes.length > 0 && checkedBoxes.length === videoCheckboxes.length;
                const someChecked = checkedBoxes.length > 0 && checkedBoxes.length < videoCheckboxes.length;

                selectAllCheckbox.checked = allChecked;
                selectAllCheckbox.indeterminate = someChecked;
            }

            // Select all videos
            function selectAll() {
                videoCheckboxes.forEach(checkbox => {
                    checkbox.checked = true;
                });
                updateDeleteButton();
            }

            // Deselect all videos
            function deselectAll() {
                videoCheckboxes.forEach(checkbox => {
                    checkbox.checked = false;
                });
                updateDeleteButton();
            }

            // Handle select all checkbox
            selectAllCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    selectAll();
                } else {
                    deselectAll();
                }
            });

            // Handle select all button
            selectAllBtn.addEventListener('click', function() {
                selectAll();
            });

            // Handle deselect all button
            deselectAllBtn.addEventListener('click', function() {
                deselectAll();
            });

            // Handle individual checkbox changes
            videoCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateDeleteButton);
            });

            // Handle bulk delete with confirmation
            deleteSelectedBtn.addEventListener('click', function() {
                const checkedBoxes = document.querySelectorAll('.video-checkbox:checked');
                const count = checkedBoxes.length;

                if (count === 0) {
                    alert('Please select at least one video to delete.');
                    return;
                }

                const message = `Are you sure you want to delete ${count} video${count > 1 ? 's' : ''}? This action cannot be undone.`;

                if (confirm(message)) {
                    document.getElementById('formAction').value = 'batch_delete';
                    bulkDeleteForm.submit();
                }
            });

            // Handle individual delete buttons
            document.querySelectorAll('.delete-single-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const videoId = this.getAttribute('data-video-id');
                    const message = 'Are you sure you want to delete this video? This action cannot be undone.';

                    if (confirm(message)) {
                        // Create a hidden input for the single video ID
                        const videoIdInput = document.createElement('input');
                        videoIdInput.type = 'hidden';
                        videoIdInput.name = 'video_id';
                        videoIdInput.value = videoId;

                        document.getElementById('formAction').value = 'delete';

                        // Add the video ID input to the form
                        bulkDeleteForm.appendChild(videoIdInput);
                        bulkDeleteForm.submit();
                    }
                });
            });
            
            // Handle manual override buttons
            document.querySelectorAll('.manual-override-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const videoId = this.getAttribute('data-video-id');
                    const videoTitle = this.getAttribute('data-video-title');
                    
                    const isPremium = this.getAttribute('data-is-premium') == '1';
                    const price = this.getAttribute('data-price');
                    const categoryId = this.getAttribute('data-category-id');
                    
                    // Set the video ID in the modal
                    document.getElementById('override_video_id').value = videoId;
                    document.getElementById('override_video_title').textContent = videoTitle;
                    document.getElementById('override_category_id').value = categoryId;
                    document.getElementById('override_is_premium').checked = isPremium;
                    document.getElementById('override_price').value = price;
                    
                    // Show the modal
                    document.getElementById('overrideModal').classList.remove('hidden');
                });
            });
            
            // Close modal functions
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
            
            // Modal close button
            document.querySelector('#overrideModal .modal-close')?.addEventListener('click', closeOverrideModal);
            
            // Close on backdrop click
            document.getElementById('overrideModal')?.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeOverrideModal();
                }
            });

            // Initial state
            updateDeleteButton();
        });
    </script>
    
    <!-- Manual Override Modal -->
    <div id="overrideModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Manual Category Override</h3>
                    <button class="modal-close text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="mb-4">
                    <p class="text-sm text-gray-600">Video: <span id="override_video_title" class="font-medium"></span></p>
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
                        <label class="block text-sm font-medium text-gray-700 mb-1">Reason for Override (Optional)</label>
                        <textarea name="reason" id="override_reason" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Optional explanation..."></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-4 border-t pt-4 mt-4">
                        <div class="flex items-center">
                            <input type="checkbox" name="is_premium" id="override_is_premium" class="h-4 w-4 text-orange-600 focus:ring-orange-500 border-gray-300 rounded">
                            <label for="override_is_premium" class="ml-2 block text-sm text-gray-900 font-medium">Premium Video</label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Price (USD)</label>
                            <input type="number" step="0.01" name="price" id="override_price" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </form>
                
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeOverrideModal()" class="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">
                        Cancel
                    </button>
                    <button type="button" onclick="saveOverride()" class="px-4 py-2 text-white bg-blue-600 rounded-md hover:bg-blue-700">
                        Apply Override
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
