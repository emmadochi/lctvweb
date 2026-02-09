/**
 * Enhanced Search Controller
 * Handles advanced search functionality with AI recommendations and smart playlists
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .controller('SearchController', ['$scope', '$location', '$routeParams', '$timeout', 'VideoService', 'CategoryService', 'UserService', 'AuthService',
            function($scope, $location, $routeParams, $timeout, VideoService, CategoryService, UserService, AuthService) {

            var vm = this;

            // Controller properties
            vm.query = '';
            vm.results = { results: [], total: 0, pagination: {} };
            vm.isLoading = false;
            vm.favoriteIds = {};
            vm.totalResults = 0;
            vm.currentPage = 1;
            vm.totalPages = 1;
            vm.suggestions = [];
            vm.showSuggestions = false;
            vm.popularSearches = [];
            vm.showAdvanced = false;

            // Enhanced filters
            vm.filters = {
                category_id: null,
                speaker: '',
                date_from: '',
                date_to: '',
                duration_min: null,
                duration_max: null,
                content_type: '',
                language: '',
                tags: []
            };

            // New features
            vm.recommendations = [];
            vm.smartPlaylists = [];
            vm.availableFilters = {
                categories: [],
                speakers: [],
                content_types: [],
                languages: []
            };

            vm.newTag = '';
            vm.suggestionsTimeout = null;

            // Initialize controller
            init();

            function init() {
                // Get search query from URL params or route params
                vm.query = $routeParams.q || $location.search().q || '';

                // Load initial data
                loadCategories();
                loadFavorites();
                loadPopularSearches();
                loadSmartPlaylists();

                // Load recommendations for authenticated users
                if (AuthService.isAuthenticated()) {
                    loadRecommendations();
                }

                // Perform search if query exists
                if (vm.query.trim()) {
                    performSearch();
                } else {
                    // Show recommendations when no search query
                    loadRecommendations();
                }

                // Set page title
                $scope.$root.setPageTitle(vm.query ? 'Enhanced Search: ' + vm.query : 'Enhanced Search');
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
             * Load categories for filters
             */
            function loadCategories() {
                CategoryService.getCategories().then(function(categories) {
                    vm.availableFilters.categories = categories || [];
                });
            }

            /**
             * Load popular searches
             */
            function loadPopularSearches() {
                VideoService.getPopularSearches(10, 30).then(function(response) {
                    vm.popularSearches = response.popular_searches || [];
                });
            }

            /**
             * Load smart playlists
             */
            function loadSmartPlaylists() {
                VideoService.getSmartPlaylists(6).then(function(response) {
                    vm.smartPlaylists = response.playlists || [];
                });
            }

            /**
             * Load AI recommendations
             */
            function loadRecommendations() {
                VideoService.getRecommendations(12, true).then(function(response) {
                    vm.recommendations = response.recommendations || [];
                });
            }

            /**
             * Perform enhanced search
             */
            function performSearch() {
                if (!vm.query.trim()) return;

                vm.isLoading = true;
                vm.showSuggestions = false;

                VideoService.searchVideos(vm.query, vm.filters, vm.currentPage, 24)
                    .then(function(response) {
                        vm.results = response;
                        vm.totalResults = response.total || 0;
                        vm.totalPages = response.pagination ? response.pagination.total_pages : 1;
                        vm.currentPage = response.pagination ? response.pagination.current_page : 1;
                        vm.isLoading = false;

                        // Update URL
                        $location.search('q', vm.query.trim());

                        console.log('Search completed:', vm.totalResults, 'results');
                    })
                    .catch(function(error) {
                        console.error('Search error:', error);
                        vm.results = { results: [], total: 0 };
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

                // Reset pagination
                vm.currentPage = 1;

                performSearch();
            };

            /**
             * Get search suggestions
             */
            vm.getSuggestions = function(event) {
                if (vm.suggestionsTimeout) {
                    $timeout.cancel(vm.suggestionsTimeout);
                }

                if (!vm.query.trim()) {
                    vm.suggestions = [];
                    return;
                }

                vm.suggestionsTimeout = $timeout(function() {
                    VideoService.getSearchSuggestions(vm.query, 8).then(function(response) {
                        vm.suggestions = response.suggestions || [];
                    });
                }, 300);
            };

            /**
             * Hide suggestions
             */
            vm.hideSuggestions = function() {
                $timeout(function() {
                    vm.showSuggestions = false;
                }, 200);
            };

            /**
             * Select suggestion
             */
            vm.selectSuggestion = function(suggestion) {
                vm.query = typeof suggestion === 'string' ? suggestion : suggestion.suggestion;
                vm.showSuggestions = false;
                vm.search();
            };

            /**
             * Select popular search
             */
            vm.selectPopularSearch = function(search) {
                vm.query = search.query;
                vm.showSuggestions = false;
                vm.search();
            };

            /**
             * Toggle advanced search
             */
            vm.toggleAdvancedSearch = function() {
                vm.showAdvanced = !vm.showAdvanced;
            };

            /**
             * Apply filters
             */
            vm.applyFilters = function() {
                vm.currentPage = 1;
                performSearch();
            };

            /**
             * Clear all filters
             */
            vm.clearFilters = function() {
                vm.filters = {
                    category_id: null,
                    speaker: '',
                    date_from: '',
                    date_to: '',
                    duration_min: null,
                    duration_max: null,
                    content_type: '',
                    language: '',
                    tags: []
                };
                vm.applyFilters();
            };

            /**
             * Set quick filter
             */
            vm.setQuickFilter = function(type) {
                // Reset filters first
                vm.clearFilters();

                switch (type) {
                    case 'sermons':
                        vm.filters.category_id = vm.getCategoryId('sermons');
                        break;
                    case 'worship':
                        vm.filters.category_id = vm.getCategoryId('worship');
                        break;
                    case 'recent':
                        var date = new Date();
                        date.setDate(date.getDate() - 30);
                        vm.filters.date_from = date.toISOString().split('T')[0];
                        break;
                    case 'short':
                        vm.filters.duration_max = 300;
                        break;
                }

                vm.applyFilters();
            };

            /**
             * Get category ID by name
             */
            vm.getCategoryId = function(name) {
                var category = vm.availableFilters.categories.find(function(cat) {
                    return cat.name.toLowerCase().includes(name);
                });
                return category ? category.id : null;
            };

            /**
             * Set duration range
             */
            vm.setDurationRange = function() {
                var range = vm.filters.duration_range;
                vm.filters.duration_min = null;
                vm.filters.duration_max = null;

                if (range === 'short') {
                    vm.filters.duration_max = 300;
                } else if (range === 'medium') {
                    vm.filters.duration_min = 300;
                    vm.filters.duration_max = 1800;
                } else if (range === 'long') {
                    vm.filters.duration_min = 1800;
                }

                vm.applyFilters();
            };

            /**
             * Get recent date (30 days ago)
             */
            vm.getRecentDate = function() {
                var date = new Date();
                date.setDate(date.getDate() - 30);
                return date.toISOString().split('T')[0];
            };

            /**
             * Add tag to filters
             */
            vm.addTag = function(event) {
                if (event.keyCode === 13 && vm.newTag.trim()) { // Enter key
                    if (vm.filters.tags.indexOf(vm.newTag.trim()) === -1) {
                        vm.filters.tags.push(vm.newTag.trim());
                    }
                    vm.newTag = '';
                    vm.applyFilters();
                }
            };

            /**
             * Remove tag from filters
             */
            vm.removeTag = function(tag) {
                var index = vm.filters.tags.indexOf(tag);
                if (index > -1) {
                    vm.filters.tags.splice(index, 1);
                    vm.applyFilters();
                }
            };

            /**
             * Get active filter count
             */
            vm.getActiveFilterCount = function() {
                var count = 0;
                if (vm.filters.category_id) count++;
                if (vm.filters.speaker) count++;
                if (vm.filters.date_from) count++;
                if (vm.filters.date_to) count++;
                if (vm.filters.duration_min || vm.filters.duration_max) count++;
                if (vm.filters.content_type) count++;
                if (vm.filters.language) count++;
                if (vm.filters.tags.length > 0) count++;
                return count;
            };

            /**
             * Load smart playlist
             */
            vm.loadSmartPlaylist = function(playlist) {
                VideoService.createSmartPlaylist(playlist.type, 20).then(function(response) {
                    if (response.playlist && response.playlist.videos) {
                        // Navigate to a playlist view or show results
                        vm.results = {
                            results: response.playlist.videos,
                            total: response.playlist.video_count,
                            pagination: { total_pages: 1, current_page: 1 }
                        };
                        vm.query = response.playlist.title;
                        vm.totalResults = response.playlist.video_count;
                    }
                });
            };

            /**
             * Play video
             */
            vm.playVideo = function(video) {
                $location.path('/video/' + video.id);
            };

            /**
             * Format result count
             */
            vm.formatResultCount = function() {
                if (vm.isLoading) return 'Searching...';
                if (vm.totalResults === 0) return 'No results found';
                if (vm.totalResults === 1) return '1 result';
                return vm.totalResults.toLocaleString() + ' results';
            };

            /**
             * Format duration
             */
            vm.formatDuration = function(seconds) {
                if (!seconds) return '';
                var minutes = Math.floor(seconds / 60);
                var remainingSeconds = seconds % 60;
                return minutes + ':' + (remainingSeconds < 10 ? '0' : '') + remainingSeconds;
            };

            /**
             * Clear search
             */
            vm.clearSearch = function() {
                vm.query = '';
                vm.results = { results: [], total: 0 };
                vm.totalResults = 0;
                vm.clearFilters();
                $location.search('q', null);
                loadRecommendations();
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