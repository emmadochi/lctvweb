<?php
/**
 * Category Controller
 * Handles category-related API endpoints
 */

require_once __DIR__ . '/../models/Category.php';

class CategoryController {

    public function index() {
        try {
            $categories = Category::getActiveCategories();
            Response::success($categories);
        } catch (Exception $e) {
            error_log("CategoryController index error: " . $e->getMessage());
            Response::error('Failed to load categories', 500);
        }
    }

    public function show($id) {
        try {
            $category = Category::getById($id);

            if (!$category) {
                Response::notFound('Category not found');
            }

            // Get videos for this category
            $videos = Video::getByCategory($id, 20);

            Response::success([
                'category' => $category,
                'videos' => $videos
            ]);
        } catch (Exception $e) {
            error_log("CategoryController show error: " . $e->getMessage());
            Response::error('Failed to load category', 500);
        }
    }
}
?>
