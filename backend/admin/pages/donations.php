<?php
// Prevent direct access
if (!defined('ADMIN_ACCESS') && !isset($_SESSION['admin_logged_in'])) {
    header('Location: ../index.php');
    exit();
}

require_once __DIR__ . '/../../models/Donation.php';

// Get period from query or default to 30 days
$period = $_GET['period'] ?? '30';
$stats = Donation::getStats($period);

// Get pagination for donation log
$page_num = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$limit = 20;
$offset = ($page_num - 1) * $limit;

$conn = getDBConnection();
$sql = "SELECT d.*, dr.first_name, dr.last_name, dr.email as donor_email
        FROM donations d
        LEFT JOIN donors dr ON d.donor_id = dr.id
        ORDER BY d.created_at DESC
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$donations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$totalDonationsCount = $conn->query("SELECT COUNT(*) FROM donations")->fetch_row()[0];
$totalPages = ceil($totalDonationsCount / $limit);
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Donation Management</h1>
            <p class="text-gray-600">Monitor contributions and manage donor records</p>
        </div>
        <div class="flex space-x-2">
            <select onchange="window.location.href='?page=donations&period=' + this.value" class="rounded-md border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500 sm:text-sm">
                <option value="7" <?php echo $period == '7' ? 'selected' : ''; ?>>Last 7 Days</option>
                <option value="30" <?php echo $period == '30' ? 'selected' : ''; ?>>Last 30 Days</option>
                <option value="90" <?php echo $period == '90' ? 'selected' : ''; ?>>Last 90 Days</option>
                <option value="365" <?php echo $period == '365' ? 'selected' : ''; ?>>Last Year</option>
            </select>
        </div>
    </div>

    <!-- Stats Overview -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-white p-6 rounded-lg shadow-sm">
            <p class="text-sm font-medium text-gray-600">Total Amount (<?php echo $period; ?>d)</p>
            <p class="text-2xl font-bold text-gray-900">$<?php echo number_format($stats['overview']['total_amount'] ?? 0, 2); ?></p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-sm">
            <p class="text-sm font-medium text-gray-600">Total Donations</p>
            <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['overview']['total_donations'] ?? 0); ?></p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-sm">
            <p class="text-sm font-medium text-gray-600">Unique Donors</p>
            <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['overview']['unique_donors'] ?? 0); ?></p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-sm">
            <p class="text-sm font-medium text-gray-600">Avg. Donation</p>
            <p class="text-2xl font-bold text-gray-900">$<?php echo number_format($stats['overview']['avg_donation'] ?? 0, 2); ?></p>
        </div>
    </div>

    <!-- Donation Log -->
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <h3 class="text-lg font-medium text-gray-900">Transaction History</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Donor</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Method</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($donations)): ?>
                        <tr><td colspan="6" class="px-6 py-10 text-center text-gray-500">No donations found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($donations as $d): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($d['first_name'] . ' ' . $d['last_name']); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($d['donor_email']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-bold">
                                    <?php echo htmlspecialchars($d['currency']); ?> <?php echo number_format($d['amount'], 2); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <span class="capitalize"><?php echo str_replace('_', ' ', $d['payment_method']); ?></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $d['transaction_status'] === 'completed' ? 'bg-green-100 text-green-800' : 
                                                 ($d['transaction_status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                        <?php echo ucfirst($d['transaction_status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M j, Y H:i', strtotime($d['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button class="text-orange-600 hover:text-orange-900" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
            <div class="flex-1 flex justify-between sm:hidden">
                <a href="?page=donations&p=<?php echo max(1, $page_num - 1); ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Previous</a>
                <a href="?page=donations&p=<?php echo min($totalPages, $page_num + 1); ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Next</a>
            </div>
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-gray-700">
                        Showing page <span class="font-medium"><?php echo $page_num; ?></span> of <span class="font-medium"><?php echo $totalPages; ?></span>
                    </p>
                </div>
                <div>
                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="?page=donations&p=<?php echo $i; ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium <?php echo $i === $page_num ? 'text-orange-600 bg-orange-50 z-10' : 'text-gray-500 hover:bg-gray-50'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </nav>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
