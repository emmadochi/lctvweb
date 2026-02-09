/**
 * Favorites Controller
 * Handles user's favorite videos and watch later queue
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .controller('FavoritesController', ['$scope', '$location', '$q', 'UserService', 'VideoService',
            function($scope, $location, $q, UserService, VideoService) {

            var vm = this;

            // Controller properties
            vm.favorites = [];
            vm.watchLater = [];
            vm.watchHistory = [];
            vm.activeTab = 'favorites'; // favorites, watchlater, history

            // Initialize controller
            init();

            // Check for tab parameter in URL
            var tabParam = $location.search().tab;
            if (tabParam && ['favorites', 'watchlater', 'history'].indexOf(tabParam) !== -1) {
                vm.activeTab = tabParam;
            }

            function init() {
                loadFavorites();
                loadWatchLater();
                loadWatchHistory();

                // Set page title
                $scope.$root.setPageTitle('My Videos');

                // Listen for route changes to refresh data when returning to favorites page
                $scope.$on('$routeChangeSuccess', function(event, current, previous) {
                    if (current && current.$$route && current.$$route.controller === 'FavoritesController') {
                        // Refresh data when navigating to favorites page
                        loadFavorites();
                        loadWatchLater();
                        loadWatchHistory();
                    }
                });
            }

            /**
             * Load user's favorite videos
             */
            function loadFavorites() {
                UserService.getFavorites().then(function(favoritesData) {
                    if (!Array.isArray(favoritesData) || favoritesData.length === 0) {
                        vm.favorites = [];
                        return;
                    }

                    // Check if the data contains full video objects or just IDs
                    var firstItem = favoritesData[0];
                    if (firstItem && typeof firstItem === 'object' && firstItem.id && firstItem.title) {
                        // API returned full video objects
                        vm.favorites = favoritesData;
                    } else {
                        // API returned video IDs, need to fetch full data
                        var videoPromises = favoritesData.map(function(videoId) {
                            return VideoService.getVideo(videoId).catch(function(error) {
                                console.warn('Error loading favorite video ' + videoId + ':', error);
                                return null; // Return null for failed loads
                            });
                        });

                        $q.all(videoPromises).then(function(videos) {
                            // Filter out null values (failed loads)
                            vm.favorites = videos.filter(function(video) {
                                return video !== null;
                            });
                        }).catch(function(error) {
                            console.error('Error loading favorite videos:', error);
                            vm.favorites = [];
                        });
                    }
                }).catch(function(error) {
                    console.error('Error loading favorites:', error);
                    vm.favorites = [];
                });
            }

            /**
             * Load watch later queue
             */
            function loadWatchLater() {
                vm.watchLater = UserService.getWatchLater();
            }

            /**
             * Load watch history
             */
            function loadWatchHistory() {
                UserService.getWatchHistory(50).then(function(history) {
                    vm.watchHistory = history;
                }).catch(function(error) {
                    console.error('Error loading watch history:', error);
                    vm.watchHistory = [];
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
             * Remove from favorites
             */
            vm.removeFromFavorites = function(videoId, event) {
                event.stopPropagation(); // Prevent navigation

                if (UserService.removeFromFavorites(videoId)) {
                    loadFavorites(); // Reload favorites
                    $scope.$root.showToast('Removed from favorites', 'info');
                }
            };

            /**
             * Remove from watch later
             */
            vm.removeFromWatchLater = function(videoId, event) {
                event.stopPropagation(); // Prevent navigation

                if (UserService.removeFromWatchLater(videoId)) {
                    loadWatchLater(); // Reload watch later
                    $scope.$root.showToast('Removed from Watch Later', 'info');
                }
            };

            /**
             * Clear watch history
             */
            vm.clearWatchHistory = function() {
                UserService.clearWatchHistory().then(function(success) {
                    if (success) {
                        vm.watchHistory = [];
                        $scope.$root.showToast('Watch history cleared', 'info');
                    } else {
                        $scope.$root.showToast('Failed to clear watch history', 'error');
                    }
                }).catch(function(error) {
                    console.error('Error clearing watch history:', error);
                    $scope.$root.showToast('Failed to clear watch history', 'error');
                });
            };

            /**
             * Clear all user data
             */
            vm.clearAllData = function() {
                if (confirm('Are you sure you want to clear all your data? This action cannot be undone.')) {
                    UserService.clearAllData();
                    loadFavorites();
                    loadWatchLater();
                    vm.watchHistory = [];
                    $scope.$root.showToast('All data cleared', 'warning');
                }
            };

            /**
             * Play video
             */
            vm.playVideo = function(videoId) {
                $location.path('/video/' + videoId);
            };

            /**
             * Export user data
             */
            vm.exportData = function() {
                var data = UserService.exportData();
                var dataStr = JSON.stringify(data, null, 2);
                var dataBlob = new Blob([dataStr], {type: 'application/json'});

                // Create download link
                var link = document.createElement('a');
                link.href = URL.createObjectURL(dataBlob);
                link.download = 'churchtv_data_' + new Date().toISOString().split('T')[0] + '.json';
                link.click();
            };

            /**
             * Format duration
             */
            vm.formatDuration = function(seconds) {
                if (!seconds || seconds <= 0) return '0:00';

                var hours = Math.floor(seconds / 3600);
                var minutes = Math.floor((seconds % 3600) / 60);
                var remainingSeconds = seconds % 60;

                if (hours > 0) {
                    return hours + ':' + padZero(minutes) + ':' + padZero(remainingSeconds);
                } else {
                    return minutes + ':' + padZero(remainingSeconds);
                }
            };

            /**
             * Format view count
             */
            vm.formatViews = function(count) {
                if (!count || count < 0) return '0';

                if (count >= 1000000) {
                    return (count / 1000000).toFixed(1) + 'M';
                } else if (count >= 1000) {
                    return (count / 1000).toFixed(1) + 'K';
                } else {
                    return count.toString();
                }
            };

            /**
             * Format date
             */
            vm.formatDate = function(dateString) {
                if (!dateString) return '';

                var date = new Date(dateString);
                return date.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
            };

            /**
             * Get tab display info
             */
            vm.getTabInfo = function(tab) {
                switch (tab) {
                    case 'favorites':
                        return {
                            title: 'Favorites',
                            icon: 'fa-heart',
                            count: vm.favorites.length,
                            emptyMessage: 'No favorite videos yet',
                            emptyDescription: 'Videos you mark as favorite will appear here'
                        };
                    case 'watchlater':
                        return {
                            title: 'Watch Later',
                            icon: 'fa-clock-o',
                            count: vm.watchLater.length,
                            emptyMessage: 'No videos in Watch Later',
                            emptyDescription: 'Save videos to watch them later'
                        };
                    case 'history':
                        return {
                            title: 'Watch History',
                            icon: 'fa-history',
                            count: vm.watchHistory.length,
                            emptyMessage: 'No watch history',
                            emptyDescription: 'Videos you\'ve watched will appear here'
                        };
                }
            };

            /**
             * Helper function
             */
            function padZero(num) {
                return (num < 10 ? '0' : '') + num;
            }

            // Expose controller to scope
            $scope.vm = vm;
        }]);
})();