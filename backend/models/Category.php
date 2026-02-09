<?php
/**
 * Category Model
 * Handles category-related database operations
 */

require_once __DIR__ . '/../config/database.php';

class Category {
    private $conn;

    public function __construct() {
        $this->conn = getDBConnection();
    }

    /**
     * Get all active categories
     */
    public static function getActiveCategories($limit = null) {
        $conn = getDBConnection();

        $sql = "SELECT id, name, slug, description, thumbnail_url, sort_order
                FROM categories
                WHERE is_active = 1
                ORDER BY sort_order ASC, name ASC";

        if ($limit) {
            $sql .= " LIMIT ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $limit);
        } else {
            $stmt = $conn->prepare($sql);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }

        return $categories;
    }

    /**
     * Get category by ID
     */
    public static function getById($id) {
        $conn = getDBConnection();

        $stmt = $conn->prepare("SELECT * FROM categories WHERE id = ? AND is_active = 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    /**
     * Get category by slug
     */
    public static function getBySlug($slug) {
        $conn = getDBConnection();

        $stmt = $conn->prepare("SELECT * FROM categories WHERE slug = ? AND is_active = 1");
        $stmt->bind_param("s", $slug);
        $stmt->execute();

        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    /**
     * Count total categories
     */
    public static function count() {
        $conn = getDBConnection();

        $result = $conn->query("SELECT COUNT(*) as count FROM categories WHERE is_active = 1");
        $row = $result->fetch_assoc();
        return $row['count'];
    }

    /**
     * Create new category
     */
    public static function create($data) {
        $conn = getDBConnection();

        $stmt = $conn->prepare("INSERT INTO categories (name, slug, description, thumbnail_url, sort_order)
                               VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss",
            $data['name'],
            $data['slug'],
            $data['description'],
            $data['thumbnail_url'],
            $data['sort_order']
        );

        if ($stmt->execute()) {
            return $conn->insert_id;
        }

        return false;
    }

    /**
     * Update category
     */
    public static function update($id, $data) {
        $conn = getDBConnection();

        $stmt = $conn->prepare("UPDATE categories SET
                               name = ?, slug = ?, description = ?, thumbnail_url = ?, sort_order = ?
                               WHERE id = ?");
        $stmt->bind_param("sssssi",
            $data['name'],
            $data['slug'],
            $data['description'],
            $data['thumbnail_url'],
            $data['sort_order'],
            $id
        );

        return $stmt->execute();
    }

    /**
     * Delete category (soft delete)
     */
    public static function delete($id) {
        $conn = getDBConnection();

        $stmt = $conn->prepare("UPDATE categories SET is_active = 0 WHERE id = ?");
        $stmt->bind_param("i", $id);

        return $stmt->execute();
    }
}
?>
