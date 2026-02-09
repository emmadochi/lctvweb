/**
 * Category Controller
 * Handles category browsing and filtering
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .controller('CategoryController', ['$scope', '$routeParams', '$location', 'VideoService', 'CategoryService', 'UserService',
            function($scope, $routeParams, $location, VideoService, CategoryService, UserService) {

            var vm = this;

            // Controller properties
            vm.category = null;
            vm.videos = [];
            vm.isLoading = true;
            vm.favoriteIds = {};
            vm.currentPage = 1;
            vm.totalPages = 1;
            vm.totalVideos = 0;
            vm.videosPerPage = 24;

            // Filter options
            vm.sortOptions = [
                { value: 'date', label: 'Most Recent' },
                { value: 'views', label: 'Most Viewed' },
                { value: 'title', label: 'Title A-Z' }
            ];
            vm.selectedSort = 'date';

            // Initialize controller
            init();

            function init() {
                var categorySlug = $routeParams.slug;

                if (!categorySlug) {
                    $location.path('/');
                    return;
                }

                loadCategory(categorySlug);
                loadCategoryVideos(categorySlug, vm.currentPage);
                loadFavorites();

                // Set page title
                $scope.$root.setPageTitle('Loading category...');
            }

            function loadFavorites() {
                UserService.getFavorites().then(function(list) {
                    vm.favoriteIds = {};
                    (list || []).forEach(function(item) {
                        var id = (item && (item.id !== undefined || item.video_id !== undefined))
                            ? (item.id !== undefined ? item.id : item.video_id) : item;
                        vm.favoriteIds[id] = true;
                    });
                });
            }

            /**
             * Load category information
             */
            function loadCategory(slug) {
                CategoryService.getCategoryBySlug(slug).then(function(category) {
                    if (category) {
                        // Add icon to category object
                        category.icon = CategoryService.getCategoryIcon(category.slug);

                        vm.category = category;
                        $scope.$root.setPageTitle(category.name + ' Videos');

                        // Load category stats
                        CategoryService.getCategoryStats(slug).then(function(stats) {
                            vm.categoryStats = stats;
                        });
                    } else {
                        $scope.$root.showError('Category not found');
                        $location.path('/');
                    }
                }).catch(function(error) {
                    console.error('Error loading category:', error);
                    $scope.$root.showError('Category not found');
                    $location.path('/');
                });
            }

            /**
             * Load videos for the category
             */
            function loadCategoryVideos(slug, page) {
                vm.isLoading = true;

                VideoService.getVideosByCategory(slug, page, vm.videosPerPage).then(function(response) {
                    vm.videos = response.videos || response;
                    vm.totalVideos = response.total || vm.videos.length;
                    vm.totalPages = Math.ceil(vm.totalVideos / vm.videosPerPage);
                    vm.currentPage = page;
                    vm.isLoading = false;
                }).catch(function(error) {
                    console.error('Error loading category videos:', error);
                    vm.videos = [];
                    vm.isLoading = false;
                    $scope.$root.showError('Failed to load videos');
                });
            }

            /**
             * Change page
             */
            vm.changePage = function(page) {
                if (page >= 1 && page <= vm.totalPages && page !== vm.currentPage) {
                    vm.currentPage = page;
                    loadCategoryVideos($routeParams.slug, page);
                    // Scroll to top
                    window.scrollTo(0, 0);
                }
            };

            /**
             * Change sort order
             */
            vm.changeSort = function(sort) {
                vm.selectedSort = sort;
                vm.currentPage = 1;
                loadCategoryVideos($routeParams.slug, 1);
            };

            vm.isFavorite = function(videoId) {
                return !!vm.favoriteIds[videoId];
            };

            vm.toggleFavorite = function(video, $event) {
                if ($event) $event.stopPropagation();
                var id = video.id;
                var wasFavorite = !!vm.favoriteIds[id];

                UserService.toggleFavorite(id).then(function(success) {
                    if (success) {
                        vm.favoriteIds[id] = !wasFavorite;
                        if (!wasFavorite) {
                            $scope.$root.showToast('Added to favorites', 'success');
                        } else {
                            $scope.$root.showToast('Removed from favorites', 'info');
                        }
                    } else {
                        $scope.$root.showToast('Failed to update favorites', 'error');
                    }
                }).catch(function(error) {
                    console.error('Error toggling favorite:', error);
                    $scope.$root.showToast('Failed to update favorites', 'error');
                });
            };

            /**
             * Navigate to video
             */
            vm.playVideo = function(videoId) {
                $location.path('/video/' + videoId);
            };

            /**
             * Get pagination range
             */
            vm.getPaginationRange = function() {
                var range = [];
                var start = Math.max(1, vm.currentPage - 2);
                var end = Math.min(vm.totalPages, vm.currentPage + 2);

                for (var i = start; i <= end; i++) {
                    range.push(i);
                }

                return range;
            };

            /**
             * Check if page is active
             */
            vm.isPageActive = function(page) {
                return page === vm.currentPage;
            };

            /**
             * Format video count
             */
            vm.formatVideoCount = function(count) {
                if (!count || count < 0) return '0 videos';

                if (count === 1) return '1 video';
                return count + ' videos';
            };

            /**
             * Format view count
             */
            vm.formatViews = function(views) {
                if (!views || views < 0) return '0 views';

                if (views >= 1000000) {
                    return (views / 1000000).toFixed(1) + 'M views';
                } else if (views >= 1000) {
                    return (views / 1000).toFixed(1) + 'K views';
                }
                return views + ' views';
            };

            /**
             * Format date
             */
            vm.formatDate = function(dateString) {
                if (!dateString) return '';

                var date = new Date(dateString);
                var now = new Date();
                var diffTime = Math.abs(now - date);
                var diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

                if (diffDays === 1) {
                    return 'Today';
                } else if (diffDays === 2) {
                    return 'Yesterday';
                } else if (diffDays <= 7) {
                    return diffDays - 1 + ' days ago';
                } else if (diffDays <= 30) {
                    var weeks = Math.floor(diffDays / 7);
                    return weeks + ' week' + (weeks > 1 ? 's' : '') + ' ago';
                } else if (diffDays <= 365) {
                    var months = Math.floor(diffDays / 30);
                    return months + ' month' + (months > 1 ? 's' : '') + ' ago';
                } else {
                    var years = Math.floor(diffDays / 365);
                    return years + ' year' + (years > 1 ? 's' : '') + ' ago';
                }
            };

            /**
             * Format duration
             */
            vm.formatDuration = function(seconds) {
                if (!seconds || seconds <= 0) return '0:00';

                var hours = Math.floor(seconds / 3600);
                var minutes = Math.floor((seconds % 3600) / 60);
                var secs = seconds % 60;

                if (hours > 0) {
                    return hours + ':' + (minutes < 10 ? '0' : '') + minutes + ':' + (secs < 10 ? '0' : '') + secs;
                } else {
                    return minutes + ':' + (secs < 10 ? '0' : '') + secs;
                }
            };

            // Expose controller to scope
            $scope.vm = vm;
        }]);
})();