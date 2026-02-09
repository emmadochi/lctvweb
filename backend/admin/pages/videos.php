<?php
// Prevent direct access - this file should be included from admin/index.php
if (!defined('ADMIN_ACCESS') && !isset($_SESSION['admin_logged_in'])) {
    header('Location: ../index.php');
    exit();
}

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
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Channel</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Views</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($videos)): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-gray-500">
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
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo htmlspecialchars($video['channel_title']); ?>
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

            // Initial state
            updateDeleteButton();
        });
    </script>
</div>
