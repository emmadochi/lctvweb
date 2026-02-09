<?php
$message = '';
$messageType = '';

$period = $_GET['period'] ?? '30 days';
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
            'timeout' => 10, // 10 second timeout
        ]
    ]);

    $response = file_get_contents($apiUrl, false, $context);
    if ($response) {
        $analytics = json_decode($response, true);
        // Check if the API returned an error
        if (isset($analytics['success']) && !$analytics['success']) {
            throw new Exception($analytics['message'] ?? 'API returned an error');
        }
    } else {
        throw new Exception('No response from analytics API');
    }
} catch (Exception $e) {
    $message = 'Failed to load analytics data: ' . $e->getMessage();
    $messageType = 'error';
    // Provide fallback empty data
    $analytics = ['data' => null];
}
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Analytics Dashboard</h1>
            <p class="text-gray-600">Track user behavior and content performance</p>
        </div>

        <!-- Period Selector -->
        <div class="flex items-center space-x-2">
            <label for="period" class="text-sm font-medium text-gray-700">Period:</label>
            <select
                id="period"
                onchange="changePeriod(this.value)"
                class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-orange-500 focus:border-orange-500"
            >
                <option value="7 days" <?php echo $period === '7 days' ? 'selected' : ''; ?>>Last 7 days</option>
                <option value="30 days" <?php echo $period === '30 days' ? 'selected' : ''; ?>>Last 30 days</option>
                <option value="90 days" <?php echo $period === '90 days' ? 'selected' : ''; ?>>Last 90 days</option>
                <option value="1 year" <?php echo $period === '1 year' ? 'selected' : ''; ?>>Last year</option>
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

    <!-- Overview Stats -->
    <?php if (!empty($analytics['data'])): ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Page Views -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center">
                <div class="p-2 bg-blue-100 rounded-lg">
                    <i class="fas fa-eye text-blue-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Page Views</p>
                    <p class="text-2xl font-semibold text-gray-900">
                        <?php echo number_format($analytics['data']['page_views']['total_views'] ?? 0); ?>
                    </p>
                    <p class="text-sm text-gray-500">
                        <?php echo number_format($analytics['data']['page_views']['unique_visitors'] ?? 0); ?> unique
                    </p>
                </div>
            </div>
        </div>

        <!-- Video Views -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center">
                <div class="p-2 bg-red-100 rounded-lg">
                    <i class="fas fa-video text-red-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Video Views</p>
                    <p class="text-2xl font-semibold text-gray-900">
                        <?php echo number_format($analytics['data']['video_views']['total_views'] ?? 0); ?>
                    </p>
                    <p class="text-sm text-gray-500">
                        <?php echo number_format($analytics['data']['video_views']['unique_viewers'] ?? 0); ?> unique
                    </p>
                </div>
            </div>
        </div>

        <!-- Watch Time -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center">
                <div class="p-2 bg-green-100 rounded-lg">
                    <i class="fas fa-clock text-green-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Avg Watch Time</p>
                    <p class="text-2xl font-semibold text-gray-900">
                        <?php
                        $avgWatchTime = $analytics['data']['video_views']['watch_stats']['avg_watch_time'] ?? 0;
                        echo gmdate('i:s', round($avgWatchTime));
                        ?>
                    </p>
                    <p class="text-sm text-gray-500">
                        <?php echo round($analytics['data']['video_views']['watch_stats']['avg_completion_rate'] ?? 0, 1); ?>% completion
                    </p>
                </div>
            </div>
        </div>

        <!-- Top Category -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center">
                <div class="p-2 bg-purple-100 rounded-lg">
                    <i class="fas fa-trophy text-purple-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Top Category</p>
                    <p class="text-2xl font-semibold text-gray-900">
                        <?php
                        $topPages = $analytics['data']['page_views']['top_pages'] ?? [];
                        $categoriesPage = array_filter($topPages, function($page) {
                            return strpos($page['page_url'], '/categories') !== false;
                        });
                        echo !empty($categoriesPage) ? 'Categories' : 'Home';
                        ?>
                    </p>
                    <p class="text-sm text-gray-500">Most visited</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Traffic Over Time -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Traffic Overview</h3>
            <div class="h-64 flex items-center justify-center text-gray-500">
                <div class="text-center">
                    <i class="fas fa-chart-line text-4xl mb-2"></i>
                    <p>Traffic chart visualization</p>
                    <p class="text-sm">Chart.js integration coming soon</p>
                </div>
            </div>
            <div class="mt-4 text-sm text-gray-600">
                <p><strong>Page Views:</strong> <?php echo number_format($analytics['data']['page_views']['total_views'] ?? 0); ?></p>
                <p><strong>Video Views:</strong> <?php echo number_format($analytics['data']['video_views']['total_views'] ?? 0); ?></p>
            </div>
        </div>

        <!-- Top Content -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Top Content</h3>
            <div class="space-y-3">
                <?php
                $topVideos = $analytics['data']['video_views']['top_videos'] ?? [];
                if (!empty($topVideos)):
                    foreach (array_slice($topVideos, 0, 5) as $video):
                ?>
                <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-b-0">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 truncate">
                            <?php echo htmlspecialchars($video['title']); ?>
                        </p>
                        <p class="text-sm text-gray-500">
                            <?php echo number_format($video['views']); ?> views
                        </p>
                    </div>
                    <div class="text-right ml-4">
                        <span class="text-sm font-medium text-green-600">
                            <?php echo round($video['avg_completion'], 1); ?>%
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                <p class="text-gray-500 text-center py-8">No video data available yet</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Device & Browser Stats -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Device Breakdown -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Device Types</h3>
            <div class="space-y-3">
                <?php
                $devices = $analytics['data']['page_views']['devices'] ?? [];
                foreach ($devices as $device):
                ?>
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-600 capitalize">
                        <?php echo htmlspecialchars($device['device_type']); ?>
                    </span>
                    <div class="flex items-center space-x-2">
                        <div class="w-24 bg-gray-200 rounded-full h-2">
                            <div class="bg-orange-500 h-2 rounded-full" style="width: <?php echo ($device['count'] / max(array_column($devices, 'count'))) * 100; ?>%"></div>
                        </div>
                        <span class="text-sm text-gray-500 w-12"><?php echo $device['count']; ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Top Pages -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Popular Pages</h3>
            <div class="space-y-3">
                <?php
                $topPages = $analytics['data']['page_views']['top_pages'] ?? [];
                foreach (array_slice($topPages, 0, 5) as $page):
                ?>
                <div class="flex items-center justify-between py-2">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 truncate">
                            <?php echo htmlspecialchars($page['page_title'] ?: $page['page_url']); ?>
                        </p>
                        <p class="text-sm text-gray-500">
                            <?php echo htmlspecialchars($page['page_url']); ?>
                        </p>
                    </div>
                    <span class="text-sm font-medium text-blue-600 ml-4">
                        <?php echo number_format($page['views']); ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Real-time Activity -->
    <div class="bg-white rounded-lg shadow-sm p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Real-time Activity (Last 5 minutes)</h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Recent Page Views -->
            <div>
                <h4 class="font-medium text-gray-900 mb-3">Recent Page Views</h4>
                <div class="space-y-2 max-h-48 overflow-y-auto">
                    <?php
                    $realtimePages = $analytics['data']['realtime_page_views'] ?? [];
                    if (!empty($realtimePages)):
                        foreach ($realtimePages as $page):
                    ?>
                    <div class="flex items-center justify-between text-sm py-1 border-b border-gray-100 last:border-b-0">
                        <span class="truncate flex-1"><?php echo htmlspecialchars($page['page_title'] ?: $page['page_url']); ?></span>
                        <span class="text-gray-500 ml-2"><?php echo date('H:i:s', strtotime($page['created_at'])); ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <p class="text-gray-500 text-sm">No recent page views</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Video Views -->
            <div>
                <h4 class="font-medium text-gray-900 mb-3">Recent Video Views</h4>
                <div class="space-y-2 max-h-48 overflow-y-auto">
                    <?php
                    $realtimeVideos = $analytics['data']['realtime_video_views'] ?? [];
                    if (!empty($realtimeVideos)):
                        foreach ($realtimeVideos as $video):
                    ?>
                    <div class="flex items-center justify-between text-sm py-1 border-b border-gray-100 last:border-b-0">
                        <span class="truncate flex-1"><?php echo htmlspecialchars($video['title']); ?></span>
                        <span class="text-gray-500 ml-2"><?php echo gmdate('i:s', $video['watch_duration']); ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <p class="text-gray-500 text-sm">No recent video views</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- No Data State -->
    <div class="bg-white rounded-lg shadow-sm p-12 text-center">
        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-chart-bar text-gray-400 text-2xl"></i>
        </div>
        <h3 class="text-lg font-medium text-gray-900 mb-2">No Analytics Data Yet</h3>
        <p class="text-gray-600 mb-6">
            Analytics data will appear here once users start interacting with your platform.
            Make sure the analytics tracking is properly configured.
        </p>
        <div class="text-left max-w-md mx-auto">
            <h4 class="font-medium text-gray-900 mb-2">To enable tracking:</h4>
            <ul class="text-sm text-gray-600 space-y-1">
                <li>• Ensure users are visiting your frontend</li>
                <li>• Check that the analytics API endpoints are accessible</li>
                <li>• Verify database tables are created</li>
                <li>• Monitor browser console for tracking errors</li>
            </ul>
        </div>
    </div>
    <?php endif; ?>
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
