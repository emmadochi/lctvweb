<?php
// Get stats for dashboard
$stats = [
    'total_videos' => $totalVideos,
    'total_categories' => $totalCategories,
    'total_views' => 0, // We'll calculate this
    'recent_imports' => 0
];

// Calculate total views
foreach ($recentVideos as $video) {
    $stats['total_views'] += $video['view_count'];
}
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
        <p class="text-gray-600">Overview of your LCMTV content</p>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center">
                <div class="p-2 bg-blue-100 rounded-lg">
                    <i class="fas fa-video text-blue-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Total Videos</p>
                    <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($stats['total_videos']); ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center">
                <div class="p-2 bg-green-100 rounded-lg">
                    <i class="fas fa-folder text-green-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Categories</p>
                    <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['total_categories']; ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center">
                <div class="p-2 bg-purple-100 rounded-lg">
                    <i class="fas fa-eye text-purple-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Total Views</p>
                    <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($stats['total_views']); ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center">
                <div class="p-2 bg-orange-100 rounded-lg">
                    <i class="fas fa-chart-line text-orange-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Avg per Category</p>
                    <p class="text-2xl font-semibold text-gray-900">
                        <?php echo $stats['total_categories'] > 0 ? round($stats['total_videos'] / $stats['total_categories'], 1) : 0; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts and Recent Activity -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Category Distribution -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Videos by Category</h3>
            <div class="space-y-3">
                <?php foreach ($categories as $category): ?>
                <?php
                    $categoryVideos = Video::getByCategory($category['id']);
                    $percentage = $stats['total_videos'] > 0 ? round((count($categoryVideos) / $stats['total_videos']) * 100, 1) : 0;
                ?>
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-600"><?php echo htmlspecialchars($category['name']); ?></span>
                    <div class="flex items-center space-x-2">
                        <div class="w-24 bg-gray-200 rounded-full h-2">
                            <div class="bg-orange-500 h-2 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                        </div>
                        <span class="text-sm text-gray-500 w-12"><?php echo count($categoryVideos); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Recent Videos -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Recent Videos</h3>
            <div class="space-y-4">
                <?php foreach (array_slice($recentVideos, 0, 5) as $video): ?>
                <div class="flex items-center space-x-3">
                    <img
                        src="<?php echo htmlspecialchars($video['thumbnail_url']); ?>"
                        alt="<?php echo htmlspecialchars($video['title']); ?>"
                        class="w-12 h-12 rounded object-cover"
                    >
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 truncate">
                            <?php echo htmlspecialchars($video['title']); ?>
                        </p>
                        <p class="text-sm text-gray-500">
                            <?php echo htmlspecialchars($video['channel_title']); ?> •
                            <?php echo number_format($video['view_count']); ?> views
                        </p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white rounded-lg shadow-sm p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Quick Actions</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <a
                href="?page=import"
                class="flex items-center justify-center px-4 py-3 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors"
            >
                <i class="fas fa-download mr-2"></i>
                Import Content
            </a>
            <a
                href="?page=videos"
                class="flex items-center justify-center px-4 py-3 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors"
            >
                <i class="fas fa-video mr-2"></i>
                Manage Videos
            </a>
            <a
                href="?page=categories"
                class="flex items-center justify-center px-4 py-3 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors"
            >
                <i class="fas fa-folder mr-2"></i>
                Manage Categories
            </a>
        </div>
    </div>
</div>
