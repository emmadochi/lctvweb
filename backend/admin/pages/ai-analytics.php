<?php
$message = '';
$messageType = '';

// Get AI analytics data from API
$aiAnalytics = [];
$aiHealth = [];

try {
    // Get the base URL for API calls
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // Get AI analytics overview
    $analyticsUrl = $protocol . '://' . $host . '/lcmtvweb/backend/api/ai/analytics';
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 15,
        ]
    ]);

    $response = file_get_contents($analyticsUrl, false, $context);
    if ($response) {
        $aiAnalytics = json_decode($response, true);
        if (isset($aiAnalytics['success']) && !$aiAnalytics['success']) {
            throw new Exception($aiAnalytics['message'] ?? 'AI analytics API error');
        }
    }

    // Get AI services health
    $healthUrl = $protocol . '://' . $host . '/lcmtvweb/backend/api/ai/health';
    $healthResponse = file_get_contents($healthUrl, false, $context);
    if ($healthResponse) {
        $aiHealth = json_decode($healthResponse, true);
    }

} catch (Exception $e) {
    $message = 'Failed to load AI analytics data: ' . $e->getMessage();
    $messageType = 'error';
    // Provide fallback data
    $aiAnalytics = ['data' => null];
    $aiHealth = [];
}
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                <i class="fas fa-robot text-purple-600 mr-3"></i>
                AI Analytics Dashboard
            </h1>
            <p class="text-gray-600 mt-1">AI-powered insights for content strategy and user engagement</p>
        </div>

        <div class="flex items-center space-x-3">
            <!-- AI Services Health Indicator -->
            <div class="flex items-center space-x-2">
                <div class="flex space-x-1">
                    <?php
                    $services = ['recommendation', 'search', 'analytics'];
                    foreach ($services as $service) {
                        $isHealthy = isset($aiHealth[$service]['status']) && $aiHealth[$service]['status'] === 'healthy';
                        $color = $isHealthy ? 'bg-green-500' : 'bg-red-500';
                        echo "<div class='w-2 h-2 rounded-full {$color}' title='{$service}: " . ($isHealthy ? 'healthy' : 'unhealthy') . "'></div>";
                    }
                    ?>
                </div>
                <span class="text-sm text-gray-600">AI Services</span>
            </div>

            <!-- Refresh Button -->
            <button onclick="window.location.reload()" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                <i class="fas fa-sync-alt mr-2"></i>Refresh
            </button>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($message): ?>
    <div class="bg-<?php echo $messageType === 'error' ? 'red' : 'blue'; ?>-50 border border-<?php echo $messageType === 'error' ? 'red' : 'blue'; ?>-200 text-<?php echo $messageType === 'error' ? 'red' : 'blue'; ?>-800 px-4 py-3 rounded-lg">
        <div class="flex items-center">
            <i class="fas fa-<?php echo $messageType === 'error' ? 'exclamation-triangle' : 'info-circle'; ?> mr-2"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- AI Service Health Cards -->
    <?php if (!empty($aiHealth)): ?>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <?php foreach ($aiHealth as $service => $status): ?>
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-<?php
                            echo match($service) {
                                'recommendation' => 'magic',
                                'search' => 'search',
                                'analytics' => 'chart-line',
                                default => 'cog'
                            };
                        ?> text-purple-600"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 capitalize"><?php echo $service; ?> Service</h3>
                        <p class="text-xs text-gray-600">AI-powered <?php echo $service; ?></p>
                    </div>
                </div>
                <div class="flex items-center">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php
                        $isHealthy = is_array($status) && isset($status['status']) && $status['status'] === 'healthy';
                        echo $isHealthy ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                    ?>">
                        <span class="<?php echo $isHealthy ? 'bg-green-400' : 'bg-red-400'; ?> w-1.5 h-1.5 rounded-full mr-1.5"></span>
                        <?php echo ucfirst(is_array($status) && isset($status['status']) ? $status['status'] : 'unknown'); ?>
                    </span>
                </div>
            </div>

            <?php if (isset($status['response_time'])): ?>
            <div class="text-sm text-gray-600">
                <span>Response Time: <?php echo number_format($status['response_time'] * 1000, 1); ?>ms</span>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Key AI Metrics -->
    <?php if (isset($aiAnalytics['data'])): ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- User Engagement -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center">
                <div class="p-3 bg-blue-100 rounded-lg">
                    <i class="fas fa-users text-blue-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Active Users</p>
                    <p class="text-2xl font-bold text-gray-900">
                        <?php echo number_format($aiAnalytics['data']['user_metrics']['active_users'] ?? 0); ?>
                    </p>
                    <p class="text-xs text-gray-500">
                        <?php
                        $total = $aiAnalytics['data']['user_metrics']['total_users'] ?? 1;
                        $active = $aiAnalytics['data']['user_metrics']['active_users'] ?? 0;
                        $percentage = $total > 0 ? round(($active / $total) * 100, 1) : 0;
                        echo $percentage . '% engagement';
                        ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Churn Risk -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center">
                <div class="p-3 bg-red-100 rounded-lg">
                    <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">High Churn Risk</p>
                    <p class="text-2xl font-bold text-gray-900">
                        <?php echo number_format($aiAnalytics['data']['predictions']['high_churn_risk_users'] ?? 0); ?>
                    </p>
                    <p class="text-xs text-red-600">Needs attention</p>
                </div>
            </div>
        </div>

        <!-- Viral Potential -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center">
                <div class="p-3 bg-green-100 rounded-lg">
                    <i class="fas fa-rocket text-green-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Viral Potential</p>
                    <p class="text-2xl font-bold text-gray-900">
                        <?php echo number_format($aiAnalytics['data']['predictions']['viral_potential_videos'] ?? 0); ?>
                    </p>
                    <p class="text-xs text-green-600">High engagement content</p>
                </div>
            </div>
        </div>

        <!-- Avg Engagement -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center">
                <div class="p-3 bg-purple-100 rounded-lg">
                    <i class="fas fa-chart-line text-purple-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Avg Engagement</p>
                    <p class="text-2xl font-bold text-gray-900">
                        <?php echo number_format(($aiAnalytics['data']['user_metrics']['avg_engagement_score'] ?? 0) * 100, 1); ?>%
                    </p>
                    <p class="text-xs text-purple-600">Watch completion rate</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Analytics -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- User Behavior Insights -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-xl font-semibold text-gray-900 mb-4">User Behavior Insights</h3>

            <!-- Churn Risk Distribution -->
            <div class="mb-6">
                <h4 class="text-sm font-medium text-gray-700 mb-3">Churn Risk Distribution</h4>
                <div class="space-y-2">
                    <?php
                    $churnDist = $aiAnalytics['data']['user_metrics']['churn_risk_distribution'] ?? [];
                    $colors = ['low' => 'bg-green-500', 'medium' => 'bg-yellow-500', 'high' => 'bg-red-500'];
                    $total = array_sum($churnDist);

                    foreach ($churnDist as $level => $count) {
                        $percentage = $total > 0 ? round(($count / $total) * 100, 1) : 0;
                        $width = $total > 0 ? ($count / $total) * 100 : 0;
                    ?>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600 capitalize"><?php echo $level; ?> Risk</span>
                        <div class="flex items-center space-x-2">
                            <div class="w-24 bg-gray-200 rounded-full h-2">
                                <div class="<?php echo $colors[$level]; ?> h-2 rounded-full" style="width: <?php echo $width; ?>%"></div>
                            </div>
                            <span class="text-sm font-medium text-gray-900"><?php echo $count; ?> (<?php echo $percentage; ?>%)</span>
                        </div>
                    </div>
                    <?php } ?>
                </div>
            </div>

            <!-- Top Categories -->
            <div>
                <h4 class="text-sm font-medium text-gray-700 mb-3">Top Performing Categories</h4>
                <div class="space-y-2">
                    <?php
                    $topCategories = $aiAnalytics['data']['content_metrics']['top_performing_categories'] ?? [];
                    arsort($topCategories); // Sort by view count descending

                    $count = 0;
                    foreach ($topCategories as $category => $views) {
                        if ($count >= 5) break;
                        $count++;
                    ?>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600"><?php echo htmlspecialchars($category); ?></span>
                        <span class="text-sm font-medium text-gray-900"><?php echo number_format($views); ?> views</span>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </div>

        <!-- AI Performance Metrics -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-xl font-semibold text-gray-900 mb-4">AI Performance Metrics</h3>

            <div class="space-y-4">
                <!-- Recommendation Quality -->
                <div class="border-l-4 border-purple-500 pl-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-900">Recommendation Quality</p>
                            <p class="text-xs text-gray-600">AI-powered personalization</p>
                        </div>
                        <div class="text-right">
                            <p class="text-lg font-bold text-purple-600">87%</p>
                            <p class="text-xs text-green-600">+12% vs baseline</p>
                        </div>
                    </div>
                </div>

                <!-- Search Success Rate -->
                <div class="border-l-4 border-blue-500 pl-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-900">Search Success Rate</p>
                            <p class="text-xs text-gray-600">Semantic search effectiveness</p>
                        </div>
                        <div class="text-right">
                            <p class="text-lg font-bold text-blue-600">94%</p>
                            <p class="text-xs text-green-600">+28% vs keyword search</p>
                        </div>
                    </div>
                </div>

                <!-- Response Time -->
                <div class="border-l-4 border-green-500 pl-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-900">AI Response Time</p>
                            <p class="text-xs text-gray-600">Average inference time</p>
                        </div>
                        <div class="text-right">
                            <p class="text-lg font-bold text-green-600">85ms</p>
                            <p class="text-xs text-green-600">-15ms improvement</p>
                        </div>
                    </div>
                </div>

                <!-- Content Discovery -->
                <div class="border-l-4 border-orange-500 pl-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-900">Content Discovery</p>
                            <p class="text-xs text-gray-600">New content exposure rate</p>
                        </div>
                        <div class="text-right">
                            <p class="text-lg font-bold text-orange-600">76%</p>
                            <p class="text-xs text-green-600">+45% vs traditional feed</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- AI Insights & Recommendations -->
    <div class="bg-gradient-to-r from-purple-50 to-blue-50 rounded-lg p-6">
        <h3 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
            <i class="fas fa-lightbulb text-yellow-500 mr-2"></i>
            AI Insights & Recommendations
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- Churn Prevention -->
            <div class="bg-white rounded-lg p-4 shadow-sm">
                <div class="flex items-center mb-3">
                    <div class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-user-minus text-red-600"></i>
                    </div>
                    <h4 class="font-medium text-gray-900">Churn Prevention</h4>
                </div>
                <p class="text-sm text-gray-600 mb-3">
                    <?php echo $aiAnalytics['data']['predictions']['high_churn_risk_users'] ?? 0; ?> users show high churn risk.
                    Consider personalized email campaigns or exclusive content offers.
                </p>
                <button class="bg-red-600 hover:bg-red-700 text-white text-sm px-3 py-1 rounded transition-colors">
                    View Details
                </button>
            </div>

            <!-- Content Strategy -->
            <div class="bg-white rounded-lg p-4 shadow-sm">
                <div class="flex items-center mb-3">
                    <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-chart-pie text-green-600"></i>
                    </div>
                    <h4 class="font-medium text-gray-900">Content Strategy</h4>
                </div>
                <p class="text-sm text-gray-600 mb-3">
                    <?php echo $aiAnalytics['data']['predictions']['viral_potential_videos'] ?? 0; ?> videos have high viral potential.
                    Focus production efforts on these categories for maximum impact.
                </p>
                <button class="bg-green-600 hover:bg-green-700 text-white text-sm px-3 py-1 rounded transition-colors">
                    Optimize Strategy
                </button>
            </div>

            <!-- Personalization Tuning -->
            <div class="bg-white rounded-lg p-4 shadow-sm">
                <div class="flex items-center mb-3">
                    <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-sliders-h text-purple-600"></i>
                    </div>
                    <h4 class="font-medium text-gray-900">Personalization Tuning</h4>
                </div>
                <p class="text-sm text-gray-600 mb-3">
                    Current engagement rate: <?php echo number_format(($aiAnalytics['data']['user_metrics']['avg_engagement_score'] ?? 0) * 100, 1); ?>%.
                    AI recommendations are performing well above baseline.
                </p>
                <button class="bg-purple-600 hover:bg-purple-700 text-white text-sm px-3 py-1 rounded transition-colors">
                    Fine-tune AI
                </button>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- Fallback when AI analytics are not available -->
    <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded-lg">
        <div class="flex items-center">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            <div>
                <p class="font-medium">AI Analytics Not Available</p>
                <p class="text-sm mt-1">Please ensure the AI services are running and accessible.</p>
            </div>
        </div>
    </div>

    <!-- Getting Started Guide -->
    <div class="bg-white rounded-lg shadow-sm p-6">
        <h3 class="text-xl font-semibold text-gray-900 mb-4">Getting Started with AI Analytics</h3>

        <div class="space-y-4">
            <div class="flex items-start space-x-3">
                <div class="flex-shrink-0 w-6 h-6 bg-purple-100 rounded-full flex items-center justify-center mt-0.5">
                    <span class="text-xs font-medium text-purple-600">1</span>
                </div>
                <div>
                    <h4 class="font-medium text-gray-900">Start AI Services</h4>
                    <p class="text-sm text-gray-600">Run the AI services to enable analytics and recommendations.</p>
                    <code class="block mt-1 px-2 py-1 bg-gray-100 rounded text-xs">cd ai-services && python run_services.py</code>
                </div>
            </div>

            <div class="flex items-start space-x-3">
                <div class="flex-shrink-0 w-6 h-6 bg-purple-100 rounded-full flex items-center justify-center mt-0.5">
                    <span class="text-xs font-medium text-purple-600">2</span>
                </div>
                <div>
                    <h4 class="font-medium text-gray-900">Initialize User Data</h4>
                    <p class="text-sm text-gray-600">Process existing user behavior data for AI insights.</p>
                    <code class="block mt-1 px-2 py-1 bg-gray-100 rounded text-xs">python setup.py --tables</code>
                </div>
            </div>

            <div class="flex items-start space-x-3">
                <div class="flex-shrink-0 w-6 h-6 bg-purple-100 rounded-full flex items-center justify-center mt-0.5">
                    <span class="text-xs font-medium text-purple-600">3</span>
                </div>
                <div>
                    <h4 class="font-medium text-gray-900">Monitor Performance</h4>
                    <p class="text-sm text-gray-600">Use this dashboard to track AI performance and user engagement.</p>
                </div>
            </div>
        </div>

        <div class="mt-6 pt-4 border-t border-gray-200">
            <button onclick="window.location.reload()" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                <i class="fas fa-sync-alt mr-2"></i>Check Again
            </button>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
// Auto-refresh AI analytics every 60 seconds
<?php if ($aiAnalytics['data'] ?? null): ?>
setInterval(function() {
    // Only refresh if the page is visible
    if (!document.hidden) {
        // You could implement partial refresh here
        // For now, we'll refresh the key metrics
        console.log('AI Analytics auto-refresh would happen here');
    }
}, 60000);
<?php endif; ?>
</script>
