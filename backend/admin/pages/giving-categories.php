<?php
// Prevent direct access - this file should be included from admin/index.php
if (!defined('ADMIN_ACCESS') && !isset($_SESSION['admin_logged_in'])) {
    header('Location: ../index.php');
    exit();
}

require_once __DIR__ . '/../../models/PaymentSetting.php';
$categories = PaymentSetting::getByGroup('giving_type');
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Giving Category Management</h1>
            <p class="text-gray-600">Add or edit the donation types shown in the Mobile App</p>
        </div>
        <button
            type="button"
            onclick="openCategoryModal()"
            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-orange-600 hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500"
        >
            <i class="fas fa-plus mr-2"></i>
            Add New Category
        </button>
    </div>

    <!-- Categories Table -->
    <div class="bg-white shadow overflow-hidden sm:rounded-lg border border-gray-200">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category Name</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Display Value</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($categories)): ?>
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center text-gray-500 italic">No giving categories found. Click the button above to add one.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                                <?php echo htmlspecialchars($cat['setting_key']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($cat['setting_value']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $cat['is_active'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                    <?php echo $cat['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button onclick='editCategory(<?php echo json_encode($cat); ?>)' class="text-orange-600 hover:text-orange-900 mr-4">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button onclick="deleteCategory(<?php echo $cat['id']; ?>)" class="text-red-600 hover:text-red-900">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Info Alert -->
    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded shadow-sm">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-info-circle text-blue-400"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm text-blue-700 font-medium">
                    Note: Categories added here will be synchronized with the Mobile App. The "Display Value" is what your donors will see in the dropdown list.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Category Modal -->
<div id="categoryModal" class="fixed inset-0 z-50 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true" onclick="closeCategoryModal()">
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border-0">
            <form id="categoryForm" onsubmit="saveCategory(event)">
                <input type="hidden" name="id" id="cat_id">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-bold text-gray-900 mb-6 flex items-center" id="modalTitle">
                        <i class="fas fa-tag mr-2 text-orange-500"></i> 
                        <span id="titleText">Add Giving Category</span>
                    </h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700">Internal Name (Key)</label>
                            <input type="text" name="key" id="cat_key" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500 sm:text-sm p-2 bg-gray-50" placeholder="e.g., Tithe, Missions">
                            <p class="text-xs text-gray-400 mt-1">Unique identifier, avoid spaces.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700">Display Name</label>
                            <input type="text" name="value" id="cat_value" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500 sm:text-sm p-2 bg-gray-50" placeholder="e.g., General Offering">
                            <p class="text-xs text-gray-400 mt-1">This is what users see in the mobile app.</p>
                        </div>
                        <div class="flex items-center pt-2">
                            <input type="checkbox" name="is_active" id="cat_is_active" checked class="h-4 w-4 text-orange-600 focus:ring-orange-500 border-gray-300 rounded">
                            <label class="ml-2 block text-sm font-medium text-gray-900">Active (Visible in App)</label>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-4 sm:px-6 sm:flex sm:flex-row-reverse gap-3">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-6 py-2 bg-orange-600 text-base font-bold text-white hover:bg-orange-700 focus:outline-none sm:w-auto sm:text-sm transition-colors">
                        Save Category
                    </button>
                    <button type="button" onclick="closeCategoryModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-6 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openCategoryModal() {
    document.getElementById('titleText').innerText = 'Add Giving Category';
    document.getElementById('categoryForm').reset();
    document.getElementById('cat_id').value = '';
    document.getElementById('cat_key').readOnly = false;
    document.getElementById('cat_key').classList.remove('bg-gray-100');
    document.getElementById('categoryModal').classList.remove('hidden');
}

function closeCategoryModal() {
    document.getElementById('categoryModal').classList.add('hidden');
}

function editCategory(cat) {
    document.getElementById('titleText').innerText = 'Edit Giving Category';
    document.getElementById('cat_id').value = cat.id;
    document.getElementById('cat_key').value = cat.setting_key;
    document.getElementById('cat_key').readOnly = true;
    document.getElementById('cat_key').classList.add('bg-gray-100');
    document.getElementById('cat_value').value = cat.setting_value;
    document.getElementById('cat_is_active').checked = cat.is_active == 1;
    document.getElementById('categoryModal').classList.remove('hidden');
}

async function saveCategory(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = {
        key: formData.get('key'),
        value: formData.get('value'),
        group: 'giving_type',
        label: formData.get('value'),
        is_active: formData.get('is_active') === 'on' ? 1 : 0
    };

    console.log('Saving category:', data);

    try {
        const response = await fetch('../api/index.php/admin/donations/settings', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        console.log('Response status:', response.status);
        
        if (!response.ok) {
            const errorText = await response.text();
            console.error('Server error:', errorText);
            alert('Server error (' + response.status + '): ' + errorText);
            return;
        }

        const result = await response.json();
        console.log('Result:', result);

        if (result.success) {
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (err) {
        console.error('Fetch error:', err);
        alert('Failed to save category. Please check your network connection or server logs.');
    }
}

async function deleteCategory(id) {
    if (!confirm('Are you sure you want to delete this category?')) return;
    try {
        const response = await fetch(`../api/index.php/admin/donations/settings?id=${id}`, {
            method: 'DELETE'
        });
        
        if (!response.ok) {
            alert('Server error (' + response.status + ')');
            return;
        }

        const result = await response.json();
        if (result.success) {
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (err) {
        alert('Failed to delete category');
    }
}
</script>
