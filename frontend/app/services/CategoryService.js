/**
 * Category Service
 * Handles category-related API calls and data management
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .factory('CategoryService', ['$http', '$q', 'API_BASE', function($http, $q, API_BASE) {

            var service = {};

            // Cache for categories
            var categoriesCache = null;
            var cacheExpiry = 10 * 60 * 1000; // 10 minutes

            function unwrap(res) {
                var body = res && res.data;
                if (body && body.success === true && body.data !== undefined) {
                    return body.data;
                }
                return body;
            }

            /**
             * Get all active categories
             */
            service.getCategories = function() {
                if (categoriesCache && categoriesCache.timestamp &&
                    (Date.now() - categoriesCache.timestamp) < cacheExpiry) {
                    return $q.resolve(categoriesCache.data);
                }

                return $http.get(API_BASE + '/categories')
                    .then(function(response) {
                        var data = unwrap(response);
                        var list = Array.isArray(data) ? data : [];
                        categoriesCache = { data: list, timestamp: Date.now() };
                        return list;
                    }).catch(function(error) {
                        console.error('Error fetching categories:', error);
                        return [];
                    });
            };

            /**
             * Get category by slug
             */
            service.getCategoryBySlug = function(slug) {
                return service.getCategories().then(function(categories) {
                    return categories.find(function(category) {
                        return category.slug === slug;
                    });
                });
            };

            /**
             * Get category statistics
             */
            service.getCategoryStats = function(categorySlug) {
                return $http.get(API_BASE + '/categories/' + categorySlug + '/stats')
                    .then(function(response) {
                        var data = unwrap(response);
                        return data || {};
                    }).catch(function(error) {
                        console.error('Error fetching category stats:', error);
                        return {
                            video_count: 0,
                            total_views: 0,
                            last_updated: null
                        };
                    });
            };

            /**
             * Get featured categories for homepage
             */
            service.getFeaturedCategories = function() {
                return service.getCategories().then(function(categories) {
                    // Return first 6 categories or customize based on priority
                    return categories.slice(0, 6);
                });
            };

            /**
             * Clear cache
             */
            service.clearCache = function() {
                categoriesCache = null;
            };

            /**
             * Get category icons mapping
             * Maps category slugs to Font Awesome icons
             */
            service.getCategoryIcon = function(slug) {
                var iconMap = {
                    'sermons': 'fa-microphone',
                    'worship': 'fa-music',
                    'youth': 'fa-users',
                    'bible-study': 'fa-book',
                    'special-events': 'fa-calendar',
                    'testimonials': 'fa-heart',
                    'news': 'fa-newspaper-o',
                    'sports': 'fa-futbol-o',
                    'music': 'fa-headphones',
                    'kids': 'fa-child',
                    'tech': 'fa-laptop',
                    'movies': 'fa-film'
                };

                return iconMap[slug] || 'fa-play-circle';
            };

            /**
             * Get category color mapping
             * Maps category slugs to color themes
             */
            service.getCategoryColor = function(slug) {
                var colorMap = {
                    'sermons': '#3498db',
                    'worship': '#e74c3c',
                    'youth': '#2ecc71',
                    'bible-study': '#9b59b6',
                    'special-events': '#f39c12',
                    'testimonials': '#e67e22',
                    'news': '#34495e',
                    'sports': '#27ae60',
                    'music': '#8e44ad',
                    'kids': '#f1c40f',
                    'tech': '#16a085',
                    'movies': '#d35400'
                };

                return colorMap[slug] || '#95a5a6';
            };

            return service;
        }]);
})();