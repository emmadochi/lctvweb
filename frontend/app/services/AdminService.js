/**
 * Admin Service
 * Handles admin authentication and administrative functions
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .factory('AdminService', ['$http', '$q', 'API_BASE', function($http, $q, API_BASE) {

            var service = {};

            // Admin authentication state
            var adminToken = null;
            var currentAdmin = null;

            // Initialize from localStorage
            init();

            function init() {
                var storedToken = localStorage.getItem('admin_token');
                var storedAdmin = localStorage.getItem('admin_user');

                if (storedToken && storedAdmin) {
                    adminToken = storedToken;
                    currentAdmin = JSON.parse(storedAdmin);

                    // Set default Authorization header for all requests
                    $http.defaults.headers.common['Authorization'] = 'Bearer ' + adminToken;
                }
            }

            /**
             * Admin login
             */
            service.login = function(email, password) {
                return $http.post(API_BASE + '/admin/login', {
                    email: email,
                    password: password
                }).then(function(response) {
                    if (response.data.token && response.data.user) {
                        adminToken = response.data.token;
                        currentAdmin = response.data.user;

                        // Store in localStorage
                        localStorage.setItem('admin_token', adminToken);
                        localStorage.setItem('admin_user', JSON.stringify(currentAdmin));

                        // Set Authorization header
                        $http.defaults.headers.common['Authorization'] = 'Bearer ' + adminToken;

                        return response.data;
                    } else {
                        throw new Error('Invalid login response');
                    }
                });
            };

            /**
             * Admin logout
             */
            service.logout = function() {
                return $http.post(API_BASE + '/admin/logout')
                    .finally(function() {
                        // Clear local data regardless of API response
                        adminToken = null;
                        currentAdmin = null;

                        localStorage.removeItem('admin_token');
                        localStorage.removeItem('admin_user');

                        // Remove Authorization header
                        delete $http.defaults.headers.common['Authorization'];
                    });
            };

            /**
             * Check if admin is authenticated
             */
            service.isAuthenticated = function() {
                return !!(adminToken && currentAdmin);
            };

            /**
             * Get current admin user
             */
            service.getCurrentUser = function() {
                return currentAdmin;
            };

            /**
             * Check authentication status with server
             */
            service.checkAuth = function() {
                if (!adminToken) {
                    return $q.reject('No token');
                }

                return $http.get(API_BASE + '/admin/me')
                    .then(function(response) {
                        currentAdmin = response.data;
                        return currentAdmin;
                    })
                    .catch(function(error) {
                        // Token might be invalid, clear local data
                        service.logout();
                        throw error;
                    });
            };

            /**
             * Get admin dashboard statistics
             */
            service.getDashboardStats = function() {
                return $http.get(API_BASE + '/admin/dashboard/stats')
                    .then(function(response) {
                        return response.data;
                    });
            };

            /**
             * Get videos for admin management
             */
            service.getVideos = function(page, limit, filters) {
                page = page || 1;
                limit = limit || 20;

                var params = {
                    page: page,
                    limit: limit
                };

                if (filters) {
                    if (filters.category) params.category_id = filters.category;
                    if (filters.status) params.is_active = filters.status === 'active' ? 1 : 0;
                    if (filters.search) params.search = filters.search;
                }

                return $http.get(API_BASE + '/admin/videos', { params: params })
                    .then(function(response) {
                        return response.data;
                    });
            };

            /**
             * Add new video
             */
            service.addVideo = function(videoData) {
                return $http.post(API_BASE + '/admin/videos', videoData)
                    .then(function(response) {
                        return response.data;
                    });
            };

            /**
             * Update video
             */
            service.updateVideo = function(videoId, videoData) {
                return $http.put(API_BASE + '/admin/videos/' + videoId, videoData)
                    .then(function(response) {
                        return response.data;
                    });
            };

            /**
             * Delete video
             */
            service.deleteVideo = function(videoId) {
                return $http.delete(API_BASE + '/admin/videos/' + videoId)
                    .then(function(response) {
                        return response.data;
                    });
            };

            /**
             * Get categories for admin
             */
            service.getCategories = function() {
                return $http.get(API_BASE + '/admin/categories')
                    .then(function(response) {
                        return response.data;
                    });
            };

            /**
             * Add new category
             */
            service.addCategory = function(categoryData) {
                return $http.post(API_BASE + '/admin/categories', categoryData)
                    .then(function(response) {
                        return response.data;
                    });
            };

            /**
             * Update category
             */
            service.updateCategory = function(categoryId, categoryData) {
                return $http.put(API_BASE + '/admin/categories/' + categoryId, categoryData)
                    .then(function(response) {
                        return response.data;
                    });
            };

            /**
             * Delete category
             */
            service.deleteCategory = function(categoryId) {
                return $http.delete(API_BASE + '/admin/categories/' + categoryId)
                    .then(function(response) {
                        return response.data;
                    });
            };

            /**
             * Get users for admin
             */
            service.getUsers = function(page, limit, filters) {
                page = page || 1;
                limit = limit || 20;

                var params = {
                    page: page,
                    limit: limit
                };

                if (filters) {
                    if (filters.role) params.role = filters.role;
                    if (filters.status) params.is_active = filters.status === 'active' ? 1 : 0;
                    if (filters.search) params.search = filters.search;
                }

                return $http.get(API_BASE + '/admin/users', { params: params })
                    .then(function(response) {
                        return response.data;
                    });
            };

            /**
             * Get analytics data
             */
            service.getAnalytics = function(period) {
                period = period || '30d';

                return $http.get(API_BASE + '/admin/analytics', {
                    params: { period: period }
                }).then(function(response) {
                    return response.data;
                });
            };

            /**
             * Get system settings
             */
            service.getSettings = function() {
                return $http.get(API_BASE + '/admin/settings')
                    .then(function(response) {
                        return response.data;
                    });
            };

            /**
             * Update system settings
             */
            service.updateSettings = function(settings) {
                return $http.put(API_BASE + '/admin/settings', settings)
                    .then(function(response) {
                        return response.data;
                    });
            };

            /**
             * Bulk import videos
             */
            service.bulkImportVideos = function(videosData) {
                return $http.post(API_BASE + '/admin/videos/bulk', videosData)
                    .then(function(response) {
                        return response.data;
                    });
            };

            /**
             * Get admin logs
             */
            service.getLogs = function(page, limit, filters) {
                page = page || 1;
                limit = limit || 50;

                var params = {
                    page: page,
                    limit: limit
                };

                if (filters) {
                    if (filters.action) params.action = filters.action;
                    if (filters.date_from) params.date_from = filters.date_from;
                    if (filters.date_to) params.date_to = filters.date_to;
                }

                return $http.get(API_BASE + '/admin/logs', { params: params })
                    .then(function(response) {
                        return response.data;
                    });
            };

            return service;
        }]);
})();