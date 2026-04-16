<?php
/**
 * Prayer Requests Management Page
 */
if (!defined('ADMIN_ACCESS') && !isset($_SESSION['admin_logged_in'])) {
    header('Location: ../index.php');
    exit();
}

require_once __DIR__ . '/../../models/PrayerRequest.php';

// Get filters
$status = $_GET['status'] ?? null;
$requests = PrayerRequest::getAll(['status' => $status]);
$stats = PrayerRequest::getStats();
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Prayer Requests</h1>
            <p class="text-gray-600">Review and respond to prayer requests from the mobile app</p>
        </div>
        <div class="flex space-x-2">
            <select onchange="window.location.href='?page=prayer-requests&status=' + this.value" class="rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                <option value="">All Statuses</option>
                <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="answered" <?php echo $status == 'answered' ? 'selected' : ''; ?>>Answered</option>
            </select>
        </div>
    </div>

    <!-- Stats Overview -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-lg shadow-sm border-l-4 border-gray-400">
            <p class="text-sm font-medium text-gray-600">Total Requests</p>
            <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['total']); ?></p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-sm border-l-4 border-yellow-400">
            <p class="text-sm font-medium text-gray-600">Pending Review</p>
            <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['pending']); ?></p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-sm border-l-4 border-green-400">
            <p class="text-sm font-medium text-gray-600">Answered</p>
            <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['answered']); ?></p>
        </div>
    </div>

    <!-- Requests Table -->
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Request snippet</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Submitted</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($requests)): ?>
                        <tr><td colspan="6" class="px-6 py-10 text-center text-gray-500">No prayer requests found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($requests as $r): ?>
                            <tr id="request-row-<?php echo $r['id']; ?>">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($r['full_name']); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($r['email']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-medium bg-purple-100 text-purple-700 rounded-full">
                                        <?php echo htmlspecialchars($r['category'] ?: 'General'); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900 truncate max-w-xs">
                                        <?php echo htmlspecialchars(substr($r['request_text'], 0, 100)); ?><?php echo strlen($r['request_text']) > 100 ? '...' : ''; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap" id="status-badge-<?php echo $r['id']; ?>">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $r['status'] === 'answered' ? 'bg-green-100 text-green-800' : 
                                                 ($r['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800'); ?>">
                                        <?php echo ucfirst($r['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M j, Y H:i', strtotime($r['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                    <button onclick="viewRequest(<?php echo htmlspecialchars(json_encode($r)); ?>)" class="text-blue-600 hover:text-blue-900" title="View & Respond">
                                        <i class="fas fa-reply"></i>
                                    </button>
                                    <button onclick="deleteRequest(<?php echo $r['id']; ?>)" class="text-red-600 hover:text-red-900" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Response Modal -->
<div id="responseModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-3/4 max-w-2xl shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center pb-4 border-b">
            <h3 class="text-xl font-bold text-gray-900">Respond to Prayer Request</h3>
            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="mt-4 space-y-4">
            <div class="bg-gray-50 p-4 rounded-md">
                <div class="flex justify-between text-xs text-gray-500 mb-2">
                    <span id="modal-user-info"></span>
                    <span id="modal-date"></span>
                </div>
                <div id="modal-request-text" class="text-gray-800 italic whitespace-pre-wrap"></div>
            </div>

            <div id="existing-response-container" class="hidden bg-green-50 p-4 rounded-md border border-green-100">
                <h4 class="text-xs font-bold text-green-800 uppercase mb-2">Previous Response</h4>
                <div id="modal-existing-response" class="text-green-900 whitespace-pre-wrap"></div>
            </div>

            <form id="responseForm" onsubmit="submitResponse(event)">
                <input type="hidden" id="request-id-input">
                <label class="block text-sm font-medium text-gray-700 mb-2">Your Response / Prayer</label>
                <textarea id="response-text" rows="6" class="w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500" placeholder="Type your response here... The user will be notified via email." required></textarea>
                
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancel</button>
                    <button type="submit" id="submit-btn" class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 flex items-center">
                        <span id="btn-text">Send Response</span>
                        <i id="btn-spinner" class="fas fa-spinner fa-spin ml-2 hidden"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let currentRequest = null;

function viewRequest(request) {
    currentRequest = request;
    document.getElementById('request-id-input').value = request.id;
    document.getElementById('modal-user-info').textContent = request.full_name + ' (' + request.email + ')';
    document.getElementById('modal-date').textContent = 'Submitted on ' + new Date(request.created_at).toLocaleString();
    document.getElementById('modal-request-text').textContent = request.request_text;
    
    const existingResponse = document.getElementById('existing-response-container');
    if (request.admin_response) {
        existingResponse.classList.remove('hidden');
        document.getElementById('modal-existing-response').textContent = request.admin_response;
        document.getElementById('response-text').value = request.admin_response;
        document.getElementById('btn-text').textContent = 'Update Response';
    } else {
        existingResponse.classList.add('hidden');
        document.getElementById('response-text').value = '';
        document.getElementById('btn-text').textContent = 'Send Response';
    }

    document.getElementById('responseModal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('responseModal').classList.add('hidden');
}

async function submitResponse(e) {
    e.preventDefault();
    const id = document.getElementById('request-id-input').value;
    const response = document.getElementById('response-text').value;
    const btn = document.getElementById('submit-btn');
    const spinner = document.getElementById('btn-spinner');

    btn.disabled = true;
    spinner.classList.remove('hidden');

    try {
        const res = await fetch(`../api/index.php/admin/prayer-requests/${id}/respond`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ response })
        });

        const result = await res.json();
        if (result.success) {
            alert('Response sent successfully!');
            location.reload(); // Refresh to update status and data
        } else {
            alert('Error: ' + result.message);
        }
    } catch (err) {
        alert('Failed to send response. Please check your connection.');
        console.error(err);
    } finally {
        btn.disabled = false;
        spinner.classList.add('hidden');
    }
}

async function deleteRequest(id) {
    if (!confirm('Are you sure you want to delete this prayer request? This cannot be undone.')) return;

    try {
        const res = await fetch(`../api/index.php/admin/prayer-requests/${id}`, {
            method: 'DELETE'
        });

        const result = await res.json();
        if (result.success) {
            document.getElementById(`request-row-${id}`).remove();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (err) {
        alert('Failed to delete request.');
    }
}
</script>
