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
    $livestreamId = (int)($_POST['livestream_id'] ?? 0);

    try {
        switch ($action) {
            case 'delete':
                if (Livestream::delete($livestreamId)) {
                    $message = 'Livestream deleted successfully';
                    $messageType = 'success';
                } else {
                    throw new Exception('Failed to delete livestream');
                }
                break;

            case 'toggle_live':
                $currentStatus = Livestream::find($livestreamId);
                if (!$currentStatus) {
                    throw new Exception('Livestream not found');
                }

                $newStatus = !$currentStatus['is_live'];
                if (Livestream::update($livestreamId, ['is_live' => $newStatus])) {
                    $statusText = $newStatus ? 'started' : 'stopped';
                    $message = "Livestream $statusText successfully";
                    $messageType = 'success';
                } else {
                    throw new Exception('Failed to update livestream status');
                }
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

// Get livestreams with pagination
$conn = getDBConnection();
$stmt = $conn->prepare("
    SELECT l.*, c.name as category_name
    FROM livestreams l
    LEFT JOIN categories c ON l.category_id = c.id
    ORDER BY l.is_live DESC, l.started_at DESC
    LIMIT ? OFFSET ?
");
$stmt->bind_param("ii", $perPage, $offset);
$stmt->execute();
$result = $stmt->get_result();

$livestreams = [];
while ($row = $result->fetch_assoc()) {
    $livestreams[] = $row;
}

// Get total count for pagination
$totalResult = $conn->query("SELECT COUNT(*) as total FROM livestreams");
$totalLivestreams = $totalResult->fetch_assoc()['total'];
$totalPages = ceil($totalLivestreams / $perPage);
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Livestreams Management</h1>
            <p class="text-gray-600">Manage your livestream content</p>
        </div>
        <a
            href="?page=import-livestream"
            class="bg-orange-500 text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition-colors"
        >
            <i class="fas fa-plus mr-2"></i> Import Livestream
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

    <!-- Livestreams Table -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-medium text-gray-900">All Livestreams (<?php echo $totalLivestreams; ?>)</h3>
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
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Livestream</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Channel</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Viewers</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Started</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($livestreams)): ?>
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-video-camera text-4xl mb-4"></i>
                                <p class="text-lg">No livestreams found</p>
                                <p class="text-sm">Import some livestreams to get started</p>
                                <a
                                    href="?page=import-livestream"
                                    class="mt-4 inline-block bg-orange-500 text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition-colors"
                                >
                                    Import Livestreams
                                </a>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($livestreams as $livestream): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <input type="checkbox" name="selected_livestreams[]" value="<?php echo $livestream['id']; ?>" class="livestream-checkbox rounded border-gray-300 text-orange-600 focus:ring-orange-500">
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-12 w-18">
                                        <img
                                            class="h-12 w-18 rounded object-cover"
                                            src="<?php echo htmlspecialchars($livestream['thumbnail_url']); ?>"
                                            alt="<?php echo htmlspecialchars($livestream['title']); ?>"
                                        >
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900 max-w-xs truncate">
                                            <?php echo htmlspecialchars($livestream['title']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?php echo htmlspecialchars($livestream['youtube_id']); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $livestream['is_live'] ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800'; ?>">
                                    <span class="w-2 h-2 rounded-full mr-1.5 <?php echo $livestream['is_live'] ? 'bg-red-400 animate-pulse' : 'bg-gray-400'; ?>"></span>
                                    <?php echo $livestream['is_live'] ? 'Live' : 'Offline'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                    <?php echo htmlspecialchars($livestream['category_name'] ?? 'Uncategorized'); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo htmlspecialchars($livestream['channel_title']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo number_format($livestream['viewer_count']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo $livestream['started_at'] ? date('M j, Y H:i', strtotime($livestream['started_at'])) : 'Not started'; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-2">
                                    <a
                                        href="https://www.youtube.com/watch?v=<?php echo $livestream['youtube_id']; ?>"
                                        target="_blank"
                                        class="text-blue-600 hover:text-blue-900"
                                        title="View on YouTube"
                                    >
                                        <i class="fas fa-external-link-alt"></i>
                                    </a>
                                    <form method="POST" class="inline" onsubmit="return confirm('<?php echo $livestream['is_live'] ? 'Stop' : 'Start'; ?> this livestream?')">
                                        <input type="hidden" name="action" value="toggle_live">
                                        <input type="hidden" name="livestream_id" value="<?php echo $livestream['id']; ?>">
                                        <button type="submit" class="text-<?php echo $livestream['is_live'] ? 'orange' : 'green'; ?>-600 hover:text-<?php echo $livestream['is_live'] ? 'orange' : 'green'; ?>-900" title="<?php echo $livestream['is_live'] ? 'Stop' : 'Start'; ?> Livestream">
                                            <i class="fas fa-<?php echo $livestream['is_live'] ? 'stop' : 'play'; ?>"></i>
                                        </button>
                                    </form>
                                    <button type="button" class="text-red-600 hover:text-red-900 delete-single-btn" title="Delete Livestream" data-livestream-id="<?php echo $livestream['id']; ?>">
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
                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalLivestreams); ?> of <?php echo $totalLivestreams; ?> livestreams
                </div>
                <div class="flex space-x-1">
                    <?php if ($page > 1): ?>
                    <a
                        href="?page=livestreams&page_num=<?php echo $page - 1; ?>"
                        class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"
                    >
                        Previous
                    </a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <a
                        href="?page=livestreams&page_num=<?php echo $i; ?>"
                        class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium <?php echo $i === $page ? 'text-orange-600 bg-orange-50' : 'text-gray-700 hover:bg-gray-50'; ?>"
                    >
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                    <a
                        href="?page=livestreams&page_num=<?php echo $page + 1; ?>"
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
</div>

<!-- JavaScript for bulk operations -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const selectAllCheckbox = document.getElementById('selectAllCheckbox');
        const selectAllBtn = document.getElementById('selectAllBtn');
        const deselectAllBtn = document.getElementById('deselectAllBtn');
        const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
        const livestreamCheckboxes = document.querySelectorAll('.livestream-checkbox');
        const bulkDeleteForm = document.getElementById('bulkDeleteForm');

        // Update delete button state based on selected checkboxes
        function updateDeleteButton() {
            const checkedBoxes = document.querySelectorAll('.livestream-checkbox:checked');
            const hasSelection = checkedBoxes.length > 0;

            deleteSelectedBtn.disabled = !hasSelection;
            deleteSelectedBtn.classList.toggle('hidden', !hasSelection);

            // Update select/deselect all button visibility
            const allChecked = livestreamCheckboxes.length > 0 && checkedBoxes.length === livestreamCheckboxes.length;
            const someChecked = checkedBoxes.length > 0 && checkedBoxes.length < livestreamCheckboxes.length;

            selectAllCheckbox.checked = allChecked;
            selectAllCheckbox.indeterminate = someChecked;
        }

        // Select all livestreams
        function selectAll() {
            livestreamCheckboxes.forEach(checkbox => {
                checkbox.checked = true;
            });
            updateDeleteButton();
        }

        // Deselect all livestreams
        function deselectAll() {
            livestreamCheckboxes.forEach(checkbox => {
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
        livestreamCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateDeleteButton);
        });

        // Handle bulk delete with confirmation
        deleteSelectedBtn.addEventListener('click', function() {
            const checkedBoxes = document.querySelectorAll('.livestream-checkbox:checked');
            const count = checkedBoxes.length;

            if (count === 0) {
                alert('Please select at least one livestream to delete.');
                return;
            }

            const message = `Are you sure you want to delete ${count} livestream${count > 1 ? 's' : ''}? This action cannot be undone.`;

            if (confirm(message)) {
                document.getElementById('formAction').value = 'batch_delete';
                bulkDeleteForm.submit();
            }
        });

        // Handle individual delete buttons
        document.querySelectorAll('.delete-single-btn').forEach(button => {
            button.addEventListener('click', function() {
                const livestreamId = this.getAttribute('data-livestream-id');
                const message = 'Are you sure you want to delete this livestream? This action cannot be undone.';

                if (confirm(message)) {
                    // Create a hidden input for the single livestream ID
                    const livestreamIdInput = document.createElement('input');
                    livestreamIdInput.type = 'hidden';
                    livestreamIdInput.name = 'livestream_id';
                    livestreamIdInput.value = livestreamId;

                    document.getElementById('formAction').value = 'delete';

                    // Add the livestream ID input to the form
                    bulkDeleteForm.appendChild(livestreamIdInput);
                    bulkDeleteForm.submit();
                }
            });
        });

        // Initial state
        updateDeleteButton();
    });
</script>