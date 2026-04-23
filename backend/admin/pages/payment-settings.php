<?php
// Prevent direct access - this file should be included from admin/index.php
if (!defined('ADMIN_ACCESS') && !isset($_SESSION['admin_logged_in'])) {
    header('Location: ../index.php');
    exit();
}

// Fetch all settings via the model directly for the initial load
require_once __DIR__ . '/../../models/PaymentSetting.php';

$allGateways = PaymentSetting::getByGroup('gateway');
$genericGateways = [];
$paystack = [
    'public_key' => '',
    'secret_key' => '',
    'is_active' => 1
];

foreach ($allGateways as $g) {
    if ($g['setting_key'] === 'paystack_public_key') {
        $paystack['public_key'] = $g['setting_value'];
        $paystack['is_active'] = $g['is_active'];
    } elseif ($g['setting_key'] === 'paystack_secret_key') {
        $paystack['secret_key'] = $g['setting_value'];
    } else {
        $genericGateways[] = $g;
    }
}

$settings = [
    'gateway' => $genericGateways,
    'bank' => PaymentSetting::getByGroup('bank'),
    'crypto' => PaymentSetting::getByGroup('crypto'),
    'general' => PaymentSetting::getByGroup('general')
];
?>

<div class="space-y-6" ng-init="activeTab = 'gateway'">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Payment Settings</h1>
            <p class="text-gray-600">Manage donation gateways, bank accounts, and crypto wallets</p>
        </div>
        <button
            type="button"
            onclick="openSettingModal()"
            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-orange-600 hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500"
        >
            <i class="fas fa-plus mr-2"></i>
            Add New Setting
        </button>
    </div>

    <!-- Paystack Configuration -->
    <div class="bg-white shadow sm:rounded-lg mb-8 border border-orange-200">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 border-b border-gray-200 pb-3 flex items-center justify-between">
                <div><i class="fas fa-layer-group text-orange-500 mr-2"></i> Dedicated Paystack Setup</div>
                <div class="flex items-center text-sm font-normal text-gray-500">
                    <input type="checkbox" id="ps_active" <?php echo $paystack['is_active'] ? 'checked' : ''; ?> class="h-4 w-4 text-orange-600 focus:ring-orange-500 border-gray-300 rounded mr-2">
                    <label for="ps_active">Active Checkout</label>
                </div>
            </h3>
            <div class="mt-5 grid grid-cols-1 gap-6 sm:grid-cols-2">
                <div>
                    <label for="ps_public" class="block text-sm font-medium text-gray-700">Public Key</label>
                    <input type="text" id="ps_public" value="<?php echo htmlspecialchars($paystack['public_key']); ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500 sm:text-sm" placeholder="pk_live_... or pk_test_...">
                    <p class="mt-1 text-xs text-gray-500">Used on the frontend to initialize the payment modal securely.</p>
                </div>
                <div>
                    <label for="ps_secret" class="block text-sm font-medium text-gray-700">Secret Key</label>
                    <input type="password" id="ps_secret" value="<?php echo htmlspecialchars($paystack['secret_key']); ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500 sm:text-sm" placeholder="sk_live_... or sk_test_...">
                    <p class="mt-1 text-xs text-gray-500">Used safely on the backend server to automatically verify transactions.</p>
                </div>
            </div>
            <div class="mt-5 flex justify-end">
                <button type="button" onclick="savePaystack()" class="inline-flex items-center justify-center px-4 py-2 border border-transparent font-medium rounded-md shadow-sm text-white bg-orange-600 hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 sm:text-sm">
                    <i class="fas fa-check mr-2"></i> Save Paystack Keys
                </button>
            </div>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <div class="border-b border-gray-200">
        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
            <button onclick="switchTab('gateway')" id="tab-gateway" class="border-orange-500 text-orange-600 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm tab-btn">
                <i class="fas fa-credit-card mr-2"></i> Gateways
            </button>
            <button onclick="switchTab('bank')" id="tab-bank" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm tab-btn">
                <i class="fas fa-university mr-2"></i> Bank Accounts
            </button>
            <button onclick="switchTab('crypto')" id="tab-crypto" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm tab-btn">
                <i class="fab fa-bitcoin mr-2"></i> Crypto Wallets
            </button>
        </nav>
    </div>

    <!-- Settings Content -->
    <div id="settings-container">
        <!-- Gateway Section -->
        <div id="section-gateway" class="settings-section">
            <div class="bg-white shadow overflow-hidden sm:rounded-md">
                <ul class="divide-y divide-gray-200">
                    <?php if (empty($settings['gateway'])): ?>
                        <li class="px-6 py-12 text-center text-gray-500">No gateway settings found.</li>
                    <?php else: ?>
                        <?php foreach ($settings['gateway'] as $s): ?>
                            <li class="px-6 py-4 flex items-center justify-between hover:bg-gray-50">
                                <div>
                                    <h4 class="text-sm font-bold text-gray-900"><?php echo htmlspecialchars($s['setting_key']); ?></h4>
                                    <p class="text-xs text-gray-500"><?php echo $s['is_encrypted'] ? 'Encrypted Key' : htmlspecialchars($s['setting_value']); ?></p>
                                </div>
                                <div class="flex items-center space-x-4">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $s['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo $s['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                    <div class="flex space-x-2">
                                        <button onclick='editSetting(<?php echo json_encode($s); ?>)' class="text-indigo-600 hover:text-indigo-900"><i class="fas fa-edit"></i></button>
                                        <button onclick="deleteSetting(<?php echo $s['id']; ?>)" class="text-red-600 hover:text-red-900"><i class="fas fa-trash"></i></button>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- Bank Section -->
        <div id="section-bank" class="settings-section hidden">
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <?php if (empty($settings['bank'])): ?>
                    <div class="col-span-full py-12 text-center text-gray-500">No bank details added yet.</div>
                <?php else: ?>
                    <?php foreach ($settings['bank'] as $s): ?>
                        <div class="bg-white overflow-hidden shadow rounded-lg border border-gray-200">
                            <div class="px-4 py-5 sm:p-6">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900 whitespace-pre-line"><?php echo htmlspecialchars($s['setting_key']); ?></h3>
                                    <div class="flex space-x-2">
                                        <button onclick='editSetting(<?php echo json_encode($s); ?>)' class="text-gray-400 hover:text-gray-600"><i class="fas fa-edit"></i></button>
                                        <button onclick="deleteSetting(<?php echo $s['id']; ?>)" class="text-gray-400 hover:text-red-600"><i class="fas fa-trash"></i></button>
                                    </div>
                                </div>
                                <div class="text-sm text-gray-500 whitespace-pre-line">
                                    <?php echo htmlspecialchars($s['setting_value']); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Crypto Section -->
        <div id="section-crypto" class="settings-section hidden">
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <?php if (empty($settings['crypto'])): ?>
                    <div class="col-span-full py-12 text-center text-gray-500">No crypto wallets added yet.</div>
                <?php else: ?>
                    <?php foreach ($settings['crypto'] as $s): ?>
                        <div class="bg-white overflow-hidden shadow rounded-lg p-6 border-l-4 border-orange-500">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-gray-500 uppercase whitespace-pre-line"><?php echo htmlspecialchars($s['setting_key']); ?></span>
                                <div class="flex space-x-2">
                                    <button onclick='editSetting(<?php echo json_encode($s); ?>)' class="text-gray-400 hover:text-gray-600"><i class="fas fa-edit"></i></button>
                                    <button onclick="deleteSetting(<?php echo $s['id']; ?>)" class="text-gray-400 hover:text-red-600"><i class="fas fa-trash"></i></button>
                                </div>
                            </div>
                            <div class="font-mono text-xs break-all bg-gray-50 p-3 rounded mt-2">
                                <?php echo htmlspecialchars($s['setting_value']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div id="settingModal" class="fixed inset-0 z-50 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true" onclick="closeSettingModal()">
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form id="settingForm" onsubmit="saveSetting(event)">
                <input type="hidden" name="id" id="setting_id">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4" id="modalTitle">Add Payment Setting</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Group</label>
                            <select name="group" id="setting_group" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500 sm:text-sm">
                                <option value="gateway">Payment Gateway</option>
                                <option value="bank">Bank Details</option>
                                <option value="crypto">Crypto Wallet</option>
                                <option value="general">General</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Key (Identifier)</label>
                            <textarea name="key" id="setting_key" rows="4" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500 sm:text-sm" placeholder="e.g., Bank details, wallet info..."></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Value (Acc No. / Address)</label>
                            <input type="text" name="value" id="setting_value" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500 sm:text-sm" placeholder="e.g., Account Number, Crypto Address">
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" name="is_active" id="setting_is_active" checked class="h-4 w-4 text-orange-600 focus:ring-orange-500 border-gray-300 rounded">
                            <label class="ml-2 block text-sm text-gray-900">Active</label>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-orange-600 text-base font-medium text-white hover:bg-orange-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">Save</button>
                    <button type="button" onclick="closeSettingModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
async function savePaystack() {
    const pub = document.getElementById('ps_public').value;
    const sec = document.getElementById('ps_secret').value;
    const isActive = document.getElementById('ps_active').checked ? 1 : 0;

    if (!pub || !sec) {
        alert('Please provide both the Public Key and Secret Key for Paystack');
        return;
    }

    try {
        const pubResp = await fetch('../api/index.php/admin/donations/settings', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ key: 'paystack_public_key', value: pub, group: 'gateway', is_active: isActive })
        });
        const secResp = await fetch('../api/index.php/admin/donations/settings', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ key: 'paystack_secret_key', value: sec, group: 'gateway', is_active: isActive, is_encrypted: 1 })
        });
        
        const pubResult = await pubResp.json();
        const secResult = await secResp.json();
        
        if (pubResult.success && secResult.success) {
            alert('Paystack settings have been saved successfully! Your checkout is ready.');
            location.reload();
        } else {
            alert('Error saving Paystack settings, check your logs');
        }
    } catch (e) {
        alert('Failed to save request to server: ' + e);
    }
}

function switchTab(tab) {
    // Hide all sections
    document.querySelectorAll('.settings-section').forEach(el => el.classList.add('hidden'));
    // Show active section
    document.getElementById('section-' + tab).classList.remove('hidden');
    // Update tab styles
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('border-orange-500', 'text-orange-600');
        btn.classList.add('border-transparent', 'text-gray-500');
    });
    document.getElementById('tab-' + tab).classList.add('border-orange-500', 'text-orange-600');
    document.getElementById('tab-' + tab).classList.remove('border-transparent', 'text-gray-500');
}

function openSettingModal() {
    document.getElementById('modalTitle').innerText = 'Add Payment Setting';
    document.getElementById('settingForm').reset();
    document.getElementById('setting_id').value = '';
    document.getElementById('settingModal').classList.remove('hidden');
}

function closeSettingModal() {
    document.getElementById('settingModal').classList.add('hidden');
}

function editSetting(setting) {
    document.getElementById('modalTitle').innerText = 'Edit Payment Setting';
    document.getElementById('setting_id').value = setting.id;
    document.getElementById('setting_key').value = setting.setting_key;
    document.getElementById('setting_value').value = setting.setting_value;
    document.getElementById('setting_group').value = setting.setting_group;
    document.getElementById('setting_is_active').checked = setting.is_active == 1;
    document.getElementById('settingModal').classList.remove('hidden');
}

async function saveSetting(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = {
        key: formData.get('key'),
        value: formData.get('value'),
        group: formData.get('group'),
        is_active: formData.get('is_active') === 'on' ? 1 : 0
    };

    try {
        const response = await fetch('../api/index.php/admin/donations/settings', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await response.json();
        if (result.success) {
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (err) {
        alert('Failed to save setting');
    }
}

async function deleteSetting(id) {
    if (!confirm('Are you sure you want to delete this setting?')) return;
    try {
        const response = await fetch(`../api/index.php/admin/donations/settings?id=${id}`, {
            method: 'DELETE'
        });
        const result = await response.json();
        if (result.success) {
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (err) {
        alert('Failed to delete setting');
    }
}
</script>
