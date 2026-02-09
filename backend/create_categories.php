<?php
/**
 * Create Basic Categories Script
 * Creates the essential categories for the Church TV application
 */

require_once 'config/database.php';
require_once 'models/Category.php';

echo "Creating Basic Categories for Church TV\n";
echo "=======================================\n\n";

try {
    $categories = [
        [
            'name' => 'News',
            'slug' => 'news',
            'description' => 'Latest news and current events',
            'thumbnail_url' => null,
            'sort_order' => 1
        ],
        [
            'name' => 'Sports',
            'slug' => 'sports',
            'description' => 'Sports highlights and updates',
            'thumbnail_url' => null,
            'sort_order' => 2
        ],
        [
            'name' => 'Music',
            'slug' => 'music',
            'description' => 'Music videos and worship songs',
            'thumbnail_url' => null,
            'sort_order' => 3
        ],
        [
            'name' => 'Sermons',
            'slug' => 'sermons',
            'description' => 'Church sermons and teachings',
            'thumbnail_url' => null,
            'sort_order' => 4
        ],
        [
            'name' => 'Youth Ministry',
            'slug' => 'youth',
            'description' => 'Content for youth ministry and young people',
            'thumbnail_url' => null,
            'sort_order' => 5
        ],
        [
            'name' => 'Technology',
            'slug' => 'tech',
            'description' => 'Technology news and reviews',
            'thumbnail_url' => null,
            'sort_order' => 6
        ],
        [
            'name' => 'Movies',
            'slug' => 'movies',
            'description' => 'Movie trailers and entertainment',
            'thumbnail_url' => null,
            'sort_order' => 7
        ],
        [
            'name' => 'Bible Study',
            'slug' => 'bible-study',
            'description' => 'Bible study materials and teachings',
            'thumbnail_url' => null,
            'sort_order' => 8
        ],
        [
            'name' => 'Special Events',
            'slug' => 'special-events',
            'description' => 'Special church events and celebrations',
            'thumbnail_url' => null,
            'sort_order' => 9
        ],
        [
            'name' => 'Testimonials',
            'slug' => 'testimonials',
            'description' => 'Personal testimonies and stories',
            'thumbnail_url' => null,
            'sort_order' => 10
        ]
    ];

    $created = 0;
    $skipped = 0;

    foreach ($categories as $categoryData) {
        // Check if category already exists
        $existing = Category::getBySlug($categoryData['slug']);

        if ($existing) {
            echo "Skipping existing category: {$categoryData['name']}\n";
            $skipped++;
            continue;
        }

        // Create the category
        $categoryId = Category::create($categoryData);

        if ($categoryId) {
            echo "✓ Created category: {$categoryData['name']}\n";
            $created++;
        } else {
            echo "✗ Failed to create category: {$categoryData['name']}\n";
        }
    }

    echo "\n🎉 Categories creation complete!\n";
    echo "===============================\n";
    echo "Categories created: $created\n";
    echo "Categories skipped (already exist): $skipped\n";
    echo "Total categories in database: " . Category::count() . "\n\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>