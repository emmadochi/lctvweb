/**
 * Admin Videos Controller
 * Handles video management for administrators
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .controller('AdminVideosController', ['$scope', '$location', '$timeout', 'AdminService', 'CategoryService',
            function($scope, $location, $timeout, AdminService, CategoryService) {

            var vm = this;

            // Controller properties
            vm.videos = [];
            vm.categories = [];
            vm.isLoading = true;
            vm.currentPage = 1;
            vm.totalPages = 1;
            vm.totalVideos = 0;
            vm.videosPerPage = 20;

            // Filters
            vm.filters = {
                search: '',
                category: '',
                status: 'all' // all, active, inactive
            };

            // Modal states
            vm.showAddModal = false;
            vm.showEditModal = false;
            vm.editingVideo = null;

            // New video form
            vm.newVideo = {
                youtube_id: '',
                title: '',
                description: '',
                category_id: '',
                tags: ''
            };

            // Initialize controller
            init();

            function init() {
                // Check authentication
                if (!AdminService.isAuthenticated()) {
                    $location.path('/admin/login');
                    return;
                }

                // Load initial data
                loadCategories();
                loadVideos();

                // Set page title
                $scope.$root.setPageTitle('Video Management');
            }

            /**
             * Load categories for dropdowns
             */
            function loadCategories() {
                CategoryService.getCategories().then(function(categories) {
                    vm.categories = categories;
                }).catch(function(error) {
                    console.error('Error loading categories:', error);
                    vm.categories = [];
                });
            }

            /**
             * Load videos with pagination and filters
             */
            function loadVideos() {
                vm.isLoading = true;

                AdminService.getVideos(vm.currentPage, vm.videosPerPage, vm.filters)
                    .then(function(response) {
                        vm.videos = response.videos || [];
                        vm.totalVideos = response.total || 0;
                        vm.totalPages = response.total_pages || 1;
                        vm.currentPage = response.page || 1;
                        vm.isLoading = false;
                    })
                    .catch(function(error) {
                        console.error('Error loading videos:', error);
                        vm.videos = [];
                        vm.isLoading = false;
                        $scope.$root.showError('Failed to load videos');
                    });
            }

            /**
             * Apply filters and reload videos
             */
            vm.applyFilters = function() {
                vm.currentPage = 1;
                loadVideos();
            };

            /**
             * Clear all filters
             */
            vm.clearFilters = function() {
                vm.filters = {
                    search: '',
                    category: '',
                    status: 'all'
                };
                vm.applyFilters();
            };

            /**
             * Change page
             */
            vm.changePage = function(page) {
                if (page >= 1 && page <= vm.totalPages && page !== vm.currentPage) {
                    vm.currentPage = page;
                    loadVideos();
                }
            };

            /**
             * Open add video modal
             */
            vm.openAddModal = function() {
                vm.newVideo = {
                    youtube_id: '',
                    title: '',
                    description: '',
                    category_id: '',
                    tags: ''
                };
                vm.showAddModal = true;
                $timeout(function() {
                    $('#addVideoModal').modal('show');
                });
            };

            /**
             * Close add video modal
             */
            vm.closeAddModal = function() {
                vm.showAddModal = false;
                $('#addVideoModal').modal('hide');
            };

            /**
             * Add new video
             */
            vm.addVideo = function() {
                if (!vm.newVideo.youtube_id || !vm.newVideo.title) {
                    $scope.$root.showError('YouTube ID and title are required');
                    return;
                }

                // Clean YouTube ID
                vm.newVideo.youtube_id = vm.extractYouTubeId(vm.newVideo.youtube_id);

                AdminService.addVideo(vm.newVideo)
                    .then(function(response) {
                        vm.closeAddModal();
                        loadVideos(); // Reload the list
                        $scope.$root.showToast('Video added successfully', 'success');
                    })
                    .catch(function(error) {
                        console.error('Error adding video:', error);
                        $scope.$root.showError(error.data?.message || 'Failed to add video');
                    });
            };

            /**
             * Open edit video modal
             */
            vm.openEditModal = function(video) {
                vm.editingVideo = angular.copy(video);
                vm.showEditModal = true;
                $timeout(function() {
                    $('#editVideoModal').modal('show');
                });
            };

            /**
             * Close edit video modal
             */
            vm.closeEditModal = function() {
                vm.showEditModal = false;
                vm.editingVideo = null;
                $('#editVideoModal').modal('hide');
            };

            /**
             * Update video
             */
            vm.updateVideo = function() {
                if (!vm.editingVideo) return;

                AdminService.updateVideo(vm.editingVideo.id, vm.editingVideo)
                    .then(function(response) {
                        vm.closeEditModal();
                        loadVideos(); // Reload the list
                        $scope.$root.showToast('Video updated successfully', 'success');
                    })
                    .catch(function(error) {
                        console.error('Error updating video:', error);
                        $scope.$root.showError(error.data?.message || 'Failed to update video');
                    });
            };

            /**
             * Delete video
             */
            vm.deleteVideo = function(video) {
                if (!confirm('Are you sure you want to delete "' + video.title + '"? This action cannot be undone.')) {
                    return;
                }

                AdminService.deleteVideo(video.id)
                    .then(function(response) {
                        loadVideos(); // Reload the list
                        $scope.$root.showToast('Video deleted successfully', 'success');
                    })
                    .catch(function(error) {
                        console.error('Error deleting video:', error);
                        $scope.$root.showError(error.data?.message || 'Failed to delete video');
                    });
            };

            /**
             * Toggle video status (active/inactive)
             */
            vm.toggleStatus = function(video) {
                var newStatus = video.is_active ? 0 : 1;
                var action = newStatus ? 'activate' : 'deactivate';

                AdminService.updateVideo(video.id, { is_active: newStatus })
                    .then(function(response) {
                        video.is_active = newStatus;
                        $scope.$root.showToast('Video ' + action + 'd successfully', 'success');
                    })
                    .catch(function(error) {
                        console.error('Error updating video status:', error);
                        $scope.$root.showError('Failed to ' + action + ' video');
                    });
            };

            /**
             * Extract YouTube ID from various URL formats
             */
            vm.extractYouTubeId = function(url) {
                if (!url) return '';

                // Remove any query parameters or fragments
                url = url.split('?')[0].split('#')[0];

                // Match various YouTube URL formats
                var patterns = [
                    /(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([^&\n?#]+)/,
                    /youtube\.com\/v\/([^&\n?#]+)/
                ];

                for (var i = 0; i < patterns.length; i++) {
                    var match = url.match(patterns[i]);
                    if (match && match[1]) {
                        return match[1];
                    }
                }

                // If it's already just the ID (11 characters)
                if (url.length === 11 && /^[a-zA-Z0-9_-]+$/.test(url)) {
                    return url;
                }

                return url; // Return as-is if we can't extract
            };

            /**
             * Preview YouTube video
             */
            vm.previewVideo = function(youtubeId) {
                if (youtubeId) {
                    window.open('https://www.youtube.com/watch?v=' + youtubeId, '_blank');
                }
            };

            /**
             * Format view count
             */
            vm.formatViews = function(count) {
                if (!count && count !== 0) return '0';

                if (count >= 1000000) {
                    return (count / 1000000).toFixed(1) + 'M';
                } else if (count >= 1000) {
                    return (count / 1000).toFixed(1) + 'K';
                } else {
                    return count.toString();
                }
            };

            /**
             * Get category name by ID
             */
            vm.getCategoryName = function(categoryId) {
                var category = vm.categories.find(function(cat) {
                    return cat.id === categoryId;
                });
                return category ? category.name : 'Uncategorized';
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
             * Bulk import videos (placeholder for future implementation)
             */
            vm.bulkImport = function() {
                $scope.$root.showToast('Bulk import feature coming soon', 'info');
            };

            // Expose controller to scope
            $scope.vm = vm;
        }]);
})();