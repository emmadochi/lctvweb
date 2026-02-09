/**
 * Admin Dashboard Controller
 * Handles admin dashboard functionality and statistics
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .controller('AdminDashboardController', ['$scope', '$location', '$interval', 'AdminService',
            function($scope, $location, $interval, AdminService) {

            var vm = this;

            // Controller properties
            vm.currentAdmin = null;
            vm.dashboardStats = {};
            vm.recentActivity = [];
            vm.isLoading = true;
            vm.activeTab = 'dashboard';

            // Auto-refresh interval
            var refreshInterval = null;

            // Initialize controller
            init();

            function init() {
                // Check authentication
                if (!AdminService.isAuthenticated()) {
                    $location.path('/admin/login');
                    return;
                }

                // Get current admin user
                vm.currentAdmin = AdminService.getCurrentUser();

                // Load dashboard data
                loadDashboardData();

                // Set up auto-refresh every 5 minutes
                refreshInterval = $interval(function() {
                    loadDashboardData();
                }, 300000); // 5 minutes

                // Set page title
                $scope.$root.setPageTitle('Admin Dashboard');
            }

            /**
             * Load dashboard data
             */
            function loadDashboardData() {
                vm.isLoading = true;

                // Load dashboard statistics
                AdminService.getDashboardStats()
                    .then(function(stats) {
                        vm.dashboardStats = stats;
                        vm.isLoading = false;
                    })
                    .catch(function(error) {
                        console.error('Error loading dashboard stats:', error);
                        vm.dashboardStats = {};
                        vm.isLoading = false;
                        $scope.$root.showError('Failed to load dashboard statistics');
                    });

                // Load recent activity (if available)
                loadRecentActivity();
            }

            /**
             * Load recent admin activity
             */
            function loadRecentActivity() {
                AdminService.getLogs(1, 10)
                    .then(function(logs) {
                        vm.recentActivity = logs.logs || [];
                    })
                    .catch(function(error) {
                        console.warn('Could not load recent activity:', error);
                        vm.recentActivity = [];
                    });
            }

            /**
             * Set active tab
             */
            vm.setActiveTab = function(tab) {
                vm.activeTab = tab;
            };

            /**
             * Check if tab is active
             */
            vm.isActiveTab = function(tab) {
                return vm.activeTab === tab;
            };

            /**
             * Navigate to different admin sections
             */
            vm.navigateTo = function(section) {
                switch (section) {
                    case 'videos':
                        $location.path('/admin/videos');
                        break;
                    case 'categories':
                        $location.path('/admin/categories');
                        break;
                    case 'users':
                        $scope.$root.showToast('User management coming soon', 'info');
                        break;
                    case 'analytics':
                        $scope.$root.showToast('Analytics dashboard coming soon', 'info');
                        break;
                    case 'settings':
                        $scope.$root.showToast('Settings panel coming soon', 'info');
                        break;
                }
            };

            /**
             * Logout admin
             */
            vm.logout = function() {
                if (confirm('Are you sure you want to logout?')) {
                    AdminService.logout()
                        .finally(function() {
                            $location.path('/admin/login');
                        });
                }
            };

            /**
             * Format numbers for display
             */
            vm.formatNumber = function(num) {
                if (!num && num !== 0) return '0';

                if (num >= 1000000) {
                    return (num / 1000000).toFixed(1) + 'M';
                } else if (num >= 1000) {
                    return (num / 1000).toFixed(1) + 'K';
                } else {
                    return num.toString();
                }
            };

            /**
             * Format percentage change
             */
            vm.formatChange = function(current, previous) {
                if (!previous || previous === 0) return { value: 0, direction: 'neutral' };

                var change = ((current - previous) / previous) * 100;
                return {
                    value: Math.abs(change),
                    direction: change > 0 ? 'up' : change < 0 ? 'down' : 'neutral'
                };
            };

            /**
             * Get stat card color
             */
            vm.getStatColor = function(statName) {
                var colors = {
                    total_videos: '#3498db',
                    total_views: '#2ecc71',
                    total_users: '#e74c3c',
                    active_users: '#f39c12',
                    total_categories: '#9b59b6'
                };
                return colors[statName] || '#95a5a6';
            };

            /**
             * Get activity icon
             */
            vm.getActivityIcon = function(action) {
                var icons = {
                    'login': 'fa-sign-in',
                    'logout': 'fa-sign-out',
                    'create_video': 'fa-plus',
                    'update_video': 'fa-edit',
                    'delete_video': 'fa-trash',
                    'create_category': 'fa-plus',
                    'update_category': 'fa-edit',
                    'delete_category': 'fa-trash'
                };
                return icons[action] || 'fa-cog';
            };

            /**
             * Format activity timestamp
             */
            vm.formatActivityTime = function(timestamp) {
                var date = new Date(timestamp);
                var now = new Date();
                var diffMs = now - date;
                var diffMins = Math.floor(diffMs / 60000);
                var diffHours = Math.floor(diffMins / 60);
                var diffDays = Math.floor(diffHours / 24);

                if (diffMins < 1) return 'Just now';
                if (diffMins < 60) return diffMins + 'm ago';
                if (diffHours < 24) return diffHours + 'h ago';
                if (diffDays < 7) return diffDays + 'd ago';

                return date.toLocaleDateString();
            };

            /**
             * Refresh dashboard data
             */
            vm.refresh = function() {
                loadDashboardData();
            };

            // Cleanup on destroy
            $scope.$on('$destroy', function() {
                if (refreshInterval) {
                    $interval.cancel(refreshInterval);
                }
            });

            // Expose controller to scope
            $scope.vm = vm;
        }]);
})();