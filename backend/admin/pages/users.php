<?php
// Prevent direct access - this file should be included from admin/index.php
if (!defined('ADMIN_ACCESS') && !isset($_SESSION['admin_logged_in'])) {
    header('Location: ../index.php');
    exit();
}

// Determine current admin role (only super_admin can create admins)
$currentAdminRole = $_SESSION['admin_role'] ?? 'admin';
$canManageAdmins = ($currentAdminRole === 'super_admin');

// Handle create admin form submission
$createAdminError = '';
$createAdminSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_admin') {
    if (!$canManageAdmins) {
        $createAdminError = 'You do not have permission to create admin users.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $role = $_POST['role'] ?? 'admin';
        $allowedRoles = ['admin', 'super_admin'];

        // Basic validation
        if (empty($email) || empty($password)) {
            $createAdminError = 'Email and password are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $createAdminError = 'Please enter a valid email address.';
        } elseif (strlen($password) < 6) {
            $createAdminError = 'Password must be at least 6 characters long.';
        } elseif (!in_array($role, $allowedRoles, true)) {
            $createAdminError = 'Invalid role selected.';
        } else {
            // Check if user already exists
            if (User::findByEmail($email)) {
                $createAdminError = 'A user with this email already exists.';
            } else {
                // Create admin user
                $userData = [
                    'email' => $email,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'role' => $role
                ];

                $newUserId = User::create($userData);

                if ($newUserId) {
                    $createAdminSuccess = 'Admin user created successfully.';
                } else {
                    $createAdminError = 'Failed to create admin user. Please try again.';
                }
            }
        }
    }
}

// Get users for management
$conn = getDBConnection();
$result = $conn->query("
    SELECT id, email, first_name, last_name, role, is_active, created_at,
           (SELECT COUNT(*) FROM video_stats WHERE user_id = users.id) as total_views
    FROM users
    ORDER BY created_at DESC
");

$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

$totalUsers = count($users);
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Users Management</h1>
            <p class="text-gray-600">Manage user accounts and permissions</p>
        </div>
        <?php if ($canManageAdmins): ?>
            <button
                type="button"
                onclick="openCreateAdminModal()"
                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-orange-600 hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500"
            >
                <i class="fas fa-user-shield mr-2"></i>
                Add Admin
            </button>
        <?php endif; ?>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center">
                <div class="p-2 bg-blue-100 rounded-lg">
                    <i class="fas fa-users text-blue-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Total Users</p>
                    <p class="text-2xl font-semibold text-gray-900"><?php echo $totalUsers; ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center">
                <div class="p-2 bg-green-100 rounded-lg">
                    <i class="fas fa-user-shield text-green-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Active Admins</p>
                    <p class="text-2xl font-semibold text-gray-900">
                        <?php echo count(array_filter($users, function($u) { return $u['role'] === 'admin' || $u['role'] === 'super_admin'; })); ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center">
                <div class="p-2 bg-purple-100 rounded-lg">
                    <i class="fas fa-eye text-purple-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Total User Views</p>
                    <p class="text-2xl font-semibold text-gray-900">
                        <?php echo number_format(array_sum(array_column($users, 'total_views'))); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">All Users</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Views</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joined</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                            <i class="fas fa-users text-4xl mb-4"></i>
                            <p class="text-lg">No users found</p>
                            <p class="text-sm">Users will appear here after they register</p>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($users as $user): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10">
                                    <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center">
                                        <span class="text-sm font-medium text-gray-700">
                                            <?php echo strtoupper(substr($user['first_name'] ?: $user['email'], 0, 1)); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars(($user['first_name'] || $user['last_name']) ?
                                            trim($user['first_name'] . ' ' . $user['last_name']) :
                                            'No name'); ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?php echo htmlspecialchars($user['email']); ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php
                                echo $user['role'] === 'super_admin' ? 'bg-red-100 text-red-800' :
                                     ($user['role'] === 'admin' ? 'bg-yellow-100 text-yellow-800' :
                                      'bg-blue-100 text-blue-800');
                            ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php
                                echo $user['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                            ?>">
                                <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo number_format($user['total_views']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex space-x-2">
                                <button
                                    onclick="viewUserDetails(<?php echo $user['id']; ?>)"
                                    class="text-blue-600 hover:text-blue-900"
                                    title="View Details"
                                >
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button
                                    onclick="openRoleModal(<?php echo $user['id']; ?>, '<?php echo $user['role']; ?>', '<?php echo addslashes($user['first_name'] . ' ' . $user['last_name']); ?>')"
                                    class="text-orange-600 hover:text-orange-900"
                                    title="Change Role"
                                >
                                    <i class="fas fa-user-tag"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create Admin Modal -->
    <?php if ($canManageAdmins): ?>
    <div
        id="createAdminModal"
        class="fixed inset-0 z-50 overflow-y-auto <?php echo (!empty($createAdminError) || !empty($createAdminSuccess)) ? '' : 'hidden'; ?>"
        aria-labelledby="createAdminTitle"
        role="dialog"
        aria-modal="true"
    >
        <div class="flex items-center justify-center min-h-screen px-4 text-center">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeCreateAdminModal()"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-medium text-gray-900" id="createAdminTitle">Add Admin User</h2>
                        <p class="text-sm text-gray-600">Create additional admin accounts with access to this dashboard.</p>
                    </div>
                    <button
                        type="button"
                        class="text-gray-400 hover:text-gray-600"
                        onclick="closeCreateAdminModal()"
                    >
                        <span class="sr-only">Close</span>
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="px-6 py-4">
                    <?php if (!empty($createAdminError)): ?>
                        <div class="mb-4 rounded-md bg-red-50 p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-circle text-red-400"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-red-700"><?php echo htmlspecialchars($createAdminError); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($createAdminSuccess)): ?>
                        <div class="mb-4 rounded-md bg-green-50 p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-check-circle text-green-400"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-green-700"><?php echo htmlspecialchars($createAdminSuccess); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="createAdminForm" class="space-y-4">
                        <input type="hidden" name="action" value="create_admin">

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="admin_first_name" class="block text-sm font-medium text-gray-700">First Name</label>
                                <input
                                    type="text"
                                    id="admin_first_name"
                                    name="first_name"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500 sm:text-sm"
                                    placeholder="John"
                                >
                            </div>

                            <div>
                                <label for="admin_last_name" class="block text-sm font-medium text-gray-700">Last Name</label>
                                <input
                                    type="text"
                                    id="admin_last_name"
                                    name="last_name"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500 sm:text-sm"
                                    placeholder="Doe"
                                >
                            </div>
                        </div>

                        <div>
                            <label for="admin_email" class="block text-sm font-medium text-gray-700">Email</label>
                            <input
                                type="email"
                                id="admin_email"
                                name="email"
                                required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500 sm:text-sm"
                                placeholder="admin@example.com"
                            >
                        </div>

                        <div>
                            <label for="admin_password" class="block text-sm font-medium text-gray-700">Password</label>
                            <input
                                type="password"
                                id="admin_password"
                                name="password"
                                required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500 sm:text-sm"
                                placeholder="Minimum 6 characters"
                            >
                        </div>

                        <div>
                            <label for="admin_role" class="block text-sm font-medium text-gray-700">Role</label>
                            <select
                                id="admin_role"
                                name="role"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500 sm:text-sm"
                            >
                                <option value="admin" selected>Admin</option>
                                <option value="super_admin">Super Admin</option>
                            </select>
                        </div>
                    </form>
                </div>

                <div class="px-6 py-4 bg-gray-50 flex justify-end space-x-3">
                    <button
                        type="button"
                        class="inline-flex justify-center rounded-md border border-gray-300 px-4 py-2 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500"
                        onclick="closeCreateAdminModal()"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        form="createAdminForm"
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-orange-600 hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500"
                    >
                        <i class="fas fa-user-shield mr-2"></i>
                        Create Admin
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
 
    <!-- Change Role Modal -->
    <div
        id="roleModal"
        class="fixed inset-0 z-50 overflow-y-auto hidden"
        aria-labelledby="roleModalTitle"
        role="dialog"
        aria-modal="true"
    >
        <div class="flex items-center justify-center min-h-screen px-4 text-center">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeRoleModal()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-medium text-gray-900" id="roleModalTitle">Change User Role</h2>
                        <p class="text-sm text-gray-600">Update the leadership status for <span id="modalUserName" class="font-bold"></span></p>
                    </div>
                    <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closeRoleModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="px-6 py-4">
                    <form id="roleForm" class="space-y-4">
                        <input type="hidden" id="modalUserId" name="user_id">
                        <div>
                            <label for="new_role" class="block text-sm font-medium text-gray-700">Select Leadership Role</label>
                            <select
                                id="new_role"
                                name="role"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500 sm:text-sm"
                            >
                                <option value="user">Registered User</option>
                                <option value="leader">Leader</option>
                                <option value="pastor">Pastor</option>
                                <option value="director">Director</option>
                                <?php if ($canManageAdmins): ?>
                                <option value="admin">Administrator</option>
                                <option value="super_admin">Super Admin</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="bg-blue-50 p-4 rounded-md">
                            <div class="flex">
                                <i class="fas fa-info-circle text-blue-400 mt-1"></i>
                                <p class="ml-3 text-sm text-blue-700">
                                    Higher roles inherit access to all content tailored for lower-ranking roles. The user will receive an email notification about their promotion.
                                </p>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="px-6 py-4 bg-gray-50 flex justify-end space-x-3">
                    <button type="button" class="inline-flex justify-center rounded-md border border-gray-300 px-4 py-2 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none" onclick="closeRoleModal()">
                        Cancel
                    </button>
                    <button
                        type="button"
                        onclick="submitRoleUpdate()"
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-orange-600 hover:bg-orange-700 focus:outline-none"
                    >
                        Update Role
                    </button>
                </div>
            </div>
        </div>
    </div>
 
</div>

<script>
function openCreateAdminModal() {
    var modal = document.getElementById('createAdminModal');
    if (modal) {
        modal.classList.remove('hidden');
    }
}

function closeCreateAdminModal() {
    var modal = document.getElementById('createAdminModal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

function viewUserDetails(userId) {
    // TODO: Implement user details modal
    alert('User details view - Coming soon!');
}
 
function openRoleModal(userId, currentRole, userName) {
    document.getElementById('modalUserId').value = userId;
    document.getElementById('new_role').value = currentRole;
    document.getElementById('modalUserName').innerText = userName;
    document.getElementById('roleModal').classList.remove('hidden');
}
 
function closeRoleModal() {
    document.getElementById('roleModal').classList.add('hidden');
}
 
async function submitRoleUpdate() {
    const userId = document.getElementById('modalUserId').value;
    const role = document.getElementById('new_role').value;
    const submitBtn = event.target;
    
    // Disable button during update
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Updating...';
 
    try {
        const response = await fetch('/LCMTVWebNew/backend/api/index.php/admin/users/role', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ user_id: userId, role: role })
        });
 
        const result = await response.json();
 
        if (result.success) {
            alert('User role updated successfully!');
            location.reload();
        } else {
            alert('Failed to update role: ' + (result.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error updating role:', error);
        alert('An error occurred. Please check the console.');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
        closeRoleModal();
    }
}
</script>
