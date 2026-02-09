/**
 * Search Controller
 * Handles video search functionality with filters and results
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .controller('SearchController', ['$scope', '$location', '$routeParams', 'SearchService', 'CategoryService', 'UserService',
            function($scope, $location, $routeParams, SearchService, CategoryService, UserService) {

            var vm = this;

            // Controller properties
            vm.query = '';
            vm.results = [];
            vm.isLoading = false;
            vm.favoriteIds = {};
            vm.totalResults = 0;
            vm.currentPage = 1;
            vm.totalPages = 1;
            vm.searchHistory = [];
            vm.suggestions = [];
            vm.showSuggestions = false;

            // Search filters
            vm.filters = {
                category: '',
                sort: 'relevance',
                duration: '',
                date: ''
            };

            // Filter options
            vm.filterOptions = SearchService.getFilterOptions();
            vm.categories = [];

            // Initialize controller
            init();

            function init() {
                // Get search query from URL params or route params
                vm.query = $routeParams.q || $location.search().q || '';

                // Load categories for filter
                loadCategories();
                loadFavorites();

                // Load search history
                vm.searchHistory = SearchService.getSearchHistory();

                // Perform search if query exists
                if (vm.query.trim()) {
                    performSearch();
                }

                // Set page title
                $scope.$root.setPageTitle(vm.query ? 'Search: ' + vm.query : 'Search');
            }

            /**
             * Load categories for filter dropdown
             */
            function loadCategories() {
                CategoryService.getCategories().then(function(categories) {
                    vm.categories = categories;
                });
            }

            /**
             * Perform search
             */
            function performSearch() {
                if (!vm.query.trim()) return;

                vm.isLoading = true;
                vm.showSuggestions = false;

                SearchService.search(vm.query, vm.filters, vm.currentPage, 24)
                    .then(function(response) {
                        vm.results = response.videos || [];
                        vm.totalResults = response.total || 0;
                        vm.totalPages = response.total_pages || 1;
                        vm.currentPage = response.page || 1;
                        vm.isLoading = false;

                        // Track search analytics
                        SearchService.trackSearch(vm.query, vm.totalResults, vm.filters);
                    })
                    .catch(function(error) {
                        console.error('Search error:', error);
                        vm.results = [];
                        vm.totalResults = 0;
                        vm.isLoading = false;
                        $scope.$root.showError('Search failed. Please try again.');
                    });
            }

            /**
             * Handle search input
             */
            vm.search = function() {
                if (!vm.query.trim()) return;

                // Update URL with search query
                $location.search('q', vm.query.trim());
                $location.path('/search');

                // Reset pagination
                vm.currentPage = 1;

                // Perform search
                performSearch();
            };

            /**
             * Clear search
             */
            vm.clearSearch = function() {
                vm.query = '';
                vm.results = [];
                vm.totalResults = 0;
                vm.currentPage = 1;
                vm.filters = {
                    category: '',
                    sort: 'relevance',
                    duration: '',
                    date: ''
                };
                $location.search('q', null);
            };

            /**
             * Apply filters
             */
            vm.applyFilters = function() {
                vm.currentPage = 1;
                performSearch();
            };

            /**
             * Change page
             */
            vm.changePage = function(page) {
                if (page >= 1 && page <= vm.totalPages && page !== vm.currentPage) {
                    vm.currentPage = page;
                    performSearch();
                    window.scrollTo(0, 0);
                }
            };

            /**
             * Get search suggestions
             */
            vm.getSuggestions = function() {
                if (vm.query.length < 2) {
                    vm.suggestions = [];
                    vm.showSuggestions = false;
                    return;
                }

                SearchService.getSuggestions(vm.query).then(function(suggestions) {
                    vm.suggestions = suggestions;
                    vm.showSuggestions = suggestions.length > 0;
                });
            };

            /**
             * Select suggestion
             */
            vm.selectSuggestion = function(suggestion) {
                vm.query = suggestion;
                vm.showSuggestions = false;
                vm.search();
            };

            /**
             * Use search history item
             */
            vm.useHistoryItem = function(item) {
                vm.query = item.query;
                vm.search();
            };

            /**
             * Clear search history
             */
            vm.clearHistory = function() {
                SearchService.clearHistory();
                vm.searchHistory = [];
            };

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
             * Format result count
             */
            vm.formatResultCount = function() {
                if (vm.totalResults === 0) return 'No results';
                if (vm.totalResults === 1) return '1 result';
                return vm.totalResults + ' results';
            };

            /**
             * Format duration for display
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
                var now = new Date();
                var diffTime = Math.abs(now - date);
                var diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

                if (diffDays === 1) {
                    return 'Today';
                } else if (diffDays === 2) {
                    return 'Yesterday';
                } else if (diffDays <= 7) {
                    return diffDays + ' days ago';
                } else {
                    return date.toLocaleDateString();
                }
            };

            /**
             * Check if filter is active
             */
            vm.isFilterActive = function(filterType, value) {
                return vm.filters[filterType] === value;
            };

            /**
             * Get active filter count
             */
            vm.getActiveFilterCount = function() {
                var count = 0;
                if (vm.filters.category) count++;
                if (vm.filters.duration) count++;
                if (vm.filters.date) count++;
                if (vm.filters.sort !== 'relevance') count++;
                return count;
            };

            /**
             * Helper function
             */
            function padZero(num) {
                return (num < 10 ? '0' : '') + num;
            }

            // Hide suggestions when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.search-input-container')) {
                    vm.showSuggestions = false;
                    $scope.$apply();
                }
            });

            // Expose controller to scope
            $scope.vm = vm;
        }]);
})();