<?php
/**
 * Home Controller
 * Handles API home endpoint and general info
 */

require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../models/Video.php';

class HomeController {

    public function index() {
        try {
            // Get featured categories and recent videos
            $categories = Category::getActiveCategories(8);
            $featuredVideos = Video::getFeaturedVideos(12);

            Response::success([
                'message' => 'Welcome to LCMTV API',
                'version' => '1.0.0',
                'categories' => $categories,
                'featured_videos' => $featuredVideos,
                'stats' => [
                    'total_categories' => Category::count(),
                    'total_videos' => Video::count()
                ]
            ]);

        } catch (Exception $e) {
            error_log("HomeController error: " . $e->getMessage());
            Response::error('Failed to load home data', 500);
        }
    }
}
?>
