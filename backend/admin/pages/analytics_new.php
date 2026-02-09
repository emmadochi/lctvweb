<?php
$message = '';
$messageType = '';

$period = $_GET['period'] ?? '30';
// Get the base URL for API calls
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$apiUrl = $protocol . '://' . $host . '/lcmtvweb/backend/api/analytics/dashboard?period=' . urlencode($period);

// Get analytics data from API
$analytics = [];
try {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => 'Authorization: Bearer ' . ($_SESSION['admin_token'] ?? ''),
            'timeout' => 15, // Increased timeout for comprehensive data
        ]
    ]);

    $response = file_get_contents($apiUrl, false, $context);
    if ($response) {
        $data = json_decode($response, true);
        // Check if the API returned success
        if (isset($data['success']) && $data['success'] && isset($data['data'])) {
            $analytics = $data['data'];
        } elseif (isset($data['success']) && !$data['success']) {
            throw new Exception($data['message'] ?? 'API returned an error');
        } else {
            // Fallback for old API format
            $analytics = $data;
        }
    } else {
        throw new Exception('No response from analytics API');
    }
} catch (Exception $e) {
    $message = 'Failed to load analytics data: ' . $e->getMessage();
    $messageType = 'error';
    // Provide fallback empty data structure
    $analytics = [
        'overview' => [
            'total_users' => 0,
            'total_sessions' => 0,
            'page_views' => 0,
            'video_views' => 0,
            'avg_session_duration' => 0,
            'top_content' => []
        ],
        'demographics' => [
            'devices' => [],
            'browsers' => [],
            'operating_systems' => [],
            'geographic' => []
        ],
        'content_performance' => [
            'video_performance' => [],
            'engagement' => [],
            'categories' => []
        ],
        'engagement' => [
            'social_interactions' => [],
            'hourly_activity' => [],
            'daily_activity' => []
        ],
        'realtime' => [
            'active_users' => 0,
            'video_views_24h' => 0,
            'page_views_24h' => 0,
            'top_pages' => []
        ]
    ];
}
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Advanced Analytics Dashboard</h1>
            <p class="text-gray-600">Comprehensive insights into user behavior and content performance</p>
        </div>

        <!-- Period Selector -->
        <div class="flex items-center space-x-2">
            <label for="period" class="text-sm font-medium text-gray-700">Period:</label>
            <select
                id="period"
                onchange="changePeriod(this.value)"
                class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-orange-500 focus:border-orange-500"
            >
                <option value="7" <?php echo $period === '7' ? 'selected' : ''; ?>>Last 7 days</option>
                <option value="30" <?php echo $period === '30' ? 'selected' : ''; ?>>Last 30 days</option>
                <option value="90" <?php echo $period === '90' ? 'selected' : ''; ?>>Last 90 days</option>
                <option value="365" <?php echo $period === '365' ? 'selected' : ''; ?>>Last year</option>
            </select>
        </div>
    </div>

    <!-- Messages -->
    <?php if ($message): ?>
    <div class="rounded-md p-4 <?php echo $messageType === 'success' ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'; ?>">
        <div class="flex">
            <div class="flex-shrink-0">
                <?php if ($messageType === 'success'): ?>
                <i class="fas fa-check-circle text-green-400"></i>
                <?php else: ?>
                <i class="fas fa-exclamation-circle text-red-400"></i>
                <?php endif; ?>
            </div>
            <div class="ml-3">
                <p class="text-sm font-medium <?php echo $messageType === 'success' ? 'text-green-800' : 'text-red-800'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Real-time Metrics Banner -->
    <div class="bg-gradient-to-r from-green-500 to-blue-600 rounded-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold mb-1">Live Metrics</h3>
                <p class="text-green-100 text-sm">Real-time activity in the last 24 hours</p>
            </div>
            <div class="flex space-x-6">
                <div class="text-center">
                    <div class="text-2xl font-bold"><?php echo number_format($analytics['realtime']['active_users'] ?? 0); ?></div>
                    <div class="text-sm opacity-90">Active Users</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold"><?php echo number_format($analytics['realtime']['page_views_24h'] ?? 0); ?></div>
                    <div class="text-sm opacity-90">Page Views</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold"><?php echo number_format($analytics['realtime']['video_views_24h'] ?? 0); ?></div>
                    <div class="text-sm opacity-90">Video Views</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Overview Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Total Users -->
        <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-blue-500">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-users text-white text-sm"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <div class="text-sm font-medium text-gray-500">Total Users</div>
                    <div class="text-2xl font-bold text-gray-900"><?php echo number_format($analytics['overview']['total_users'] ?? 0); ?></div>
                    <div class="text-xs text-gray-500 mt-1">Last <?php echo $analytics['period_days'] ?? 30; ?> days</div>
                </div>
            </div>
        </div>

        <!-- Total Sessions -->
        <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-green-500">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-chart-line text-white text-sm"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <div class="text-sm font-medium text-gray-500">Total Sessions</div>
                    <div class="text-2xl font-bold text-gray-900"><?php echo number_format($analytics['overview']['total_sessions'] ?? 0); ?></div>
                    <div class="text-xs text-gray-500 mt-1">User visits</div>
                </div>
            </div>
        </div>

        <!-- Page Views -->
        <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-purple-500">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-eye text-white text-sm"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <div class="text-sm font-medium text-gray-500">Page Views</div>
                    <div class="text-2xl font-bold text-gray-900"><?php echo number_format($analytics['overview']['page_views'] ?? 0); ?></div>
                    <div class="text-xs text-gray-500 mt-1">Total interactions</div>
                </div>
            </div>
        </div>

        <!-- Video Views -->
        <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-orange-500">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-orange-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-play-circle text-white text-sm"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <div class="text-sm font-medium text-gray-500">Video Views</div>
                    <div class="text-2xl font-bold text-gray-900"><?php echo number_format($analytics['overview']['video_views'] ?? 0); ?></div>
                    <div class="text-xs text-gray-500 mt-1">Content consumption</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Additional Metrics Row -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Average Session Duration -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-indigo-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-clock text-white text-sm"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <div class="text-sm font-medium text-gray-500">Avg Session Duration</div>
                    <div class="text-xl font-bold text-gray-900">
                        <?php
                        $duration = $analytics['overview']['avg_session_duration'] ?? 0;
                        echo gmdate('i:s', round($duration));
                        ?>
                    </div>
                    <div class="text-xs text-gray-500 mt-1">Time spent on site</div>
                </div>
            </div>
        </div>

        <!-- Social Interactions -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-pink-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-heart text-white text-sm"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <div class="text-sm font-medium text-gray-500">Social Interactions</div>
                    <div class="text-xl font-bold text-gray-900">
                        <?php
                        $social = $analytics['engagement']['social_interactions'] ?? [];
                        $total = 0;
                        foreach ($social as $interaction) {
                            $total += $interaction['count'] ?? 0;
                        }
                        echo number_format($total);
                        ?>
                    </div>
                    <div class="text-xs text-gray-500 mt-1">Comments, reactions, shares</div>
                </div>
            </div>
        </div>

        <!-- Top Content Performance -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-teal-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-trophy text-white text-sm"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <div class="text-sm font-medium text-gray-500">Top Content</div>
                    <div class="text-xl font-bold text-gray-900">
                        <?php
                        $topContent = $analytics['overview']['top_content'] ?? [];
                        echo count($topContent) > 0 ? htmlspecialchars(substr($topContent[0]['video_title'] ?? 'None', 0, 20)) . '...' : 'No data';
                        ?>
                    </div>
                    <div class="text-xs text-gray-500 mt-1">Most viewed content</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Analytics Sections -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- User Demographics -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">User Demographics</h3>

            <!-- Device Types -->
            <div class="mb-4">
                <h4 class="text-sm font-medium text-gray-700 mb-2">Device Types</h4>
                <div class="space-y-2">
                    <?php
                    $devices = $analytics['demographics']['devices'] ?? [];
                    if (empty($devices)) {
                        echo '<p class="text-sm text-gray-500">No device data available</p>';
                    } else {
                        foreach (array_slice($devices, 0, 5) as $device) {
                            $percentage = $analytics['overview']['total_sessions'] > 0 ?
                                round(($device['count'] / $analytics['overview']['total_sessions']) * 100, 1) : 0;
                    ?>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600"><?php echo htmlspecialchars(ucfirst($device['device_type'])); ?></span>
                        <div class="flex items-center space-x-2">
                            <div class="w-20 bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                            <span class="text-sm font-medium text-gray-900"><?php echo $percentage; ?>%</span>
                        </div>
                    </div>
                    <?php }} ?>
                </div>
            </div>

            <!-- Browsers -->
            <div class="mb-4">
                <h4 class="text-sm font-medium text-gray-700 mb-2">Top Browsers</h4>
                <div class="space-y-2">
                    <?php
                    $browsers = $analytics['demographics']['browsers'] ?? [];
                    if (empty($browsers)) {
                        echo '<p class="text-sm text-gray-500">No browser data available</p>';
                    } else {
                        foreach (array_slice($browsers, 0, 3) as $browser) {
                            $percentage = $analytics['overview']['total_sessions'] > 0 ?
                                round(($browser['count'] / $analytics['overview']['total_sessions']) * 100, 1) : 0;
                    ?>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600"><?php echo htmlspecialchars($browser['browser']); ?></span>
                        <span class="text-sm font-medium text-gray-900"><?php echo $percentage; ?>%</span>
                    </div>
                    <?php }} ?>
                </div>
            </div>

            <!-- Geographic Data -->
            <div>
                <h4 class="text-sm font-medium text-gray-700 mb-2">Top Locations</h4>
                <div class="space-y-2">
                    <?php
                    $geographic = $analytics['demographics']['geographic'] ?? [];
                    if (empty($geographic)) {
                        echo '<p class="text-sm text-gray-500">No geographic data available</p>';
                    } else {
                        foreach (array_slice($geographic, 0, 3) as $location) {
                    ?>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">
                            <?php echo htmlspecialchars($location['city'] . ', ' . $location['country']); ?>
                        </span>
                        <span class="text-sm font-medium text-gray-900"><?php echo number_format($location['count']); ?></span>
                    </div>
                    <?php }} ?>
                </div>
            </div>
        </div>

        <!-- Content Performance -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Content Performance</h3>

            <!-- Top Performing Videos -->
            <div class="mb-4">
                <h4 class="text-sm font-medium text-gray-700 mb-2">Top Videos</h4>
                <div class="space-y-3">
                    <?php
                    $videos = $analytics['content_performance']['video_performance'] ?? [];
                    if (empty($videos)) {
                        echo '<p class="text-sm text-gray-500">No video performance data available</p>';
                    } else {
                        foreach (array_slice($videos, 0, 3) as $video) {
                    ?>
                    <div class="border-b border-gray-100 pb-2 last:border-b-0">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-900 truncate">
                                    <?php echo htmlspecialchars($video['video_title']); ?>
                                </p>
                                <div class="flex items-center space-x-4 mt-1">
                                    <span class="text-xs text-gray-500">
                                        <?php echo number_format($video['total_views']); ?> views
                                    </span>
                                    <span class="text-xs text-gray-500">
                                        <?php echo round($video['avg_completion_rate'], 1); ?>% completion
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php }} ?>
                </div>
            </div>

            <!-- Popular Categories -->
            <div>
                <h4 class="text-sm font-medium text-gray-700 mb-2">Popular Categories</h4>
                <div class="space-y-2">
                    <?php
                    $categories = $analytics['content_performance']['categories'] ?? [];
                    if (empty($categories)) {
                        echo '<p class="text-sm text-gray-500">No category data available</p>';
                    } else {
                        foreach (array_slice($categories, 0, 3) as $category) {
                    ?>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600"><?php echo htmlspecialchars($category['category_name']); ?></span>
                        <span class="text-sm font-medium text-gray-900"><?php echo number_format($category['video_views']); ?> views</span>
                    </div>
                    <?php }} ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Engagement Analytics -->
    <div class="bg-white rounded-lg shadow-sm p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Engagement Analytics</h3>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Social Interactions -->
            <div>
                <h4 class="text-sm font-medium text-gray-700 mb-3">Social Interactions</h4>
                <div class="space-y-2">
                    <?php
                    $social = $analytics['engagement']['social_interactions'] ?? [];
                    if (empty($social)) {
                        echo '<p class="text-sm text-gray-500">No social data available</p>';
                    } else {
                        foreach ($social as $interaction) {
                    ?>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600 capitalize"><?php echo htmlspecialchars($interaction['type']); ?></span>
                        <span class="text-sm font-medium text-gray-900"><?php echo number_format($interaction['count']); ?></span>
                    </div>
                    <?php }} ?>
                </div>
            </div>

            <!-- Hourly Activity -->
            <div>
                <h4 class="text-sm font-medium text-gray-700 mb-3">Peak Hours</h4>
                <div class="space-y-2">
                    <?php
                    $hourly = $analytics['engagement']['hourly_activity'] ?? [];
                    if (empty($hourly)) {
                        echo '<p class="text-sm text-gray-500">No hourly data available</p>';
                    } else {
                        // Find peak hours
                        usort($hourly, function($a, $b) {
                            return $b['activity_count'] - $a['activity_count'];
                        });
                        foreach (array_slice($hourly, 0, 3) as $hour) {
                            $hourFormatted = sprintf('%02d:00', $hour['hour']);
                    ?>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600"><?php echo $hourFormatted; ?></span>
                        <span class="text-sm font-medium text-gray-900"><?php echo number_format($hour['activity_count']); ?></span>
                    </div>
                    <?php }} ?>
                </div>
            </div>

            <!-- Daily Activity -->
            <div>
                <h4 class="text-sm font-medium text-gray-700 mb-3">Busiest Days</h4>
                <div class="space-y-2">
                    <?php
                    $daily = $analytics['engagement']['daily_activity'] ?? [];
                    if (empty($daily)) {
                        echo '<p class="text-sm text-gray-500">No daily data available</p>';
                    } else {
                        // Sort by activity count
                        usort($daily, function($a, $b) {
                            return $b['activity_count'] - $a['activity_count'];
                        });
                        foreach (array_slice($daily, 0, 3) as $day) {
                    ?>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600"><?php echo htmlspecialchars($day['day_name']); ?></span>
                        <span class="text-sm font-medium text-gray-900"><?php echo number_format($day['activity_count']); ?></span>
                    </div>
                    <?php }} ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Content Table -->
    <div class="bg-white rounded-lg shadow-sm p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Top Performing Content</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Content</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Views</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unique Viewers</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Watch Time</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Completion Rate</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php
                    $videos = $analytics['content_performance']['video_performance'] ?? [];
                    if (empty($videos)) {
                        echo '<tr><td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">No video performance data available</td></tr>';
                    } else {
                        foreach (array_slice($videos, 0, 10) as $video) {
                    ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900 truncate max-w-xs">
                                <?php echo htmlspecialchars($video['video_title']); ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo number_format($video['total_views']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo number_format($video['unique_viewers']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo gmdate('i:s', round($video['avg_watch_duration'])); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                <?php echo $video['avg_completion_rate'] >= 80 ? 'bg-green-100 text-green-800' :
                                        ($video['avg_completion_rate'] >= 50 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                <?php echo round($video['avg_completion_rate'], 1); ?>%
                            </span>
                        </td>
                    </tr>
                    <?php }} ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Auto-refresh analytics data every 60 seconds
setInterval(function() {
    if (!document.hidden) {
        location.reload();
    }
}, 60000);

// Change period function
function changePeriod(period) {
    const url = new URL(window.location);
    url.searchParams.set('period', period);
    window.location.href = url.toString();
}
</script>