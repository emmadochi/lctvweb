<?php
/**
 * LCMTV Admin Dashboard
 * Content management interface
 */

session_start();

// Check authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../models/Video.php';
require_once __DIR__ . '/../models/User.php';

// Get stats for dashboard
$totalCategories = Category::count();
$totalVideos = Video::count();
$categories = Category::getActiveCategories();
$recentVideos = Video::getFeaturedVideos(10);

$page = $_GET['page'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LCMTV Admin - Content Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <!-- Header -->
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center">
                    <div class="w-8 h-8 bg-orange-500 rounded flex items-center justify-center mr-3">
                        <span class="text-white font-bold">TV</span>
                    </div>
                    <h1 class="text-xl font-bold text-gray-900">LCMTV Admin</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-600">Welcome, <?php echo htmlspecialchars($_SESSION['admin_email']); ?></span>
                    <a href="logout.php" class="text-sm text-red-600 hover:text-red-800">Logout</a>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex">
            <!-- Sidebar -->
            <nav class="w-64 mr-8">
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <ul class="space-y-2">
                        <li>
                            <a href="?page=dashboard" class="flex items-center px-4 py-2 text-sm font-medium rounded-md <?php echo $page === 'dashboard' ? 'bg-orange-100 text-orange-700' : 'text-gray-600 hover:bg-gray-50'; ?>">
                                <i class="fas fa-tachometer-alt mr-3"></i> Dashboard
                            </a>
                        </li>
                        <li>
                            <a href="?page=videos" class="flex items-center px-4 py-2 text-sm font-medium rounded-md <?php echo $page === 'videos' ? 'bg-orange-100 text-orange-700' : 'text-gray-600 hover:bg-gray-50'; ?>">
                                <i class="fas fa-video mr-3"></i> Videos
                            </a>
                        </li>
                        <li>
                            <a href="?page=categories" class="flex items-center px-4 py-2 text-sm font-medium rounded-md <?php echo $page === 'categories' ? 'bg-orange-100 text-orange-700' : 'text-gray-600 hover:bg-gray-50'; ?>">
                                <i class="fas fa-folder mr-3"></i> Categories
                            </a>
                        </li>
                        <li>
                            <a href="?page=import" class="flex items-center px-4 py-2 text-sm font-medium rounded-md <?php echo $page === 'import' ? 'bg-orange-100 text-orange-700' : 'text-gray-600 hover:bg-gray-50'; ?>">
                                <i class="fas fa-download mr-3"></i> Import Content
                            </a>
                        </li>
                        <li>
                            <a href="?page=analytics" class="flex items-center px-4 py-2 text-sm font-medium rounded-md <?php echo $page === 'analytics' ? 'bg-orange-100 text-orange-700' : 'text-gray-600 hover:bg-gray-50'; ?>">
                                <i class="fas fa-chart-bar mr-3"></i> Analytics
                            </a>
                        </li>
                        <li>
                            <a href="?page=ai-analytics" class="flex items-center px-4 py-2 text-sm font-medium rounded-md <?php echo $page === 'ai-analytics' ? 'bg-purple-100 text-purple-700' : 'text-gray-600 hover:bg-gray-50'; ?>">
                                <i class="fas fa-robot mr-3"></i> AI Analytics
                            </a>
                        </li>
                        <li>
                            <a href="?page=users" class="flex items-center px-4 py-2 text-sm font-medium rounded-md <?php echo $page === 'users' ? 'bg-orange-100 text-orange-700' : 'text-gray-600 hover:bg-gray-50'; ?>">
                                <i class="fas fa-users mr-3"></i> Users
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="flex-1">
                <?php
                switch ($page) {
                    case 'dashboard':
                        include 'pages/dashboard.php';
                        break;
                    case 'videos':
                        include 'pages/videos.php';
                        break;
                    case 'categories':
                        include 'pages/categories.php';
                        break;
                    case 'import':
                        include 'pages/import.php';
                        break;
                    case 'analytics':
                        include 'pages/analytics.php';
                        break;
                    case 'ai-analytics':
                        include 'pages/ai-analytics.php';
                        break;
                    case 'users':
                        include 'pages/users.php';
                        break;
                    default:
                        include 'pages/dashboard.php';
                }
                ?>
            </main>
        </div>
    </div>

    <script>
        // Auto-refresh dashboard stats every 30 seconds
        <?php if ($page === 'dashboard'): ?>
        setInterval(function() {
            location.reload();
        }, 30000);
        <?php endif; ?>
    </script>
</body>
</html>
