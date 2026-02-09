/**
 * Header Controller
 * Handles header navigation, categories dropdown, and user authentication
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .controller('HeaderController', ['$scope', '$location', '$timeout', 'CategoryService', 'AuthService', 'SearchService',
            function($scope, $location, $timeout, CategoryService, AuthService, SearchService) {

            var vm = this;

            // Controller properties
            vm.categories = [];
            vm.showCategoriesDropdown = false;
            vm.showUserDropdown = false;
            vm.currentUser = null;

            // Authentication state property (updated when auth state changes)
            vm.isAuthenticated = false;

            // Auth loading state to prevent flickering
            vm.authLoading = true;

            // Search functionality
            vm.searchQuery = '';
            vm.showSearchSuggestions = false;
            vm.searchSuggestions = [];
            vm.selectedSuggestionIndex = -1;
            vm.suggestionTimeout = null;

            // Initialize controller
            init();

            function init() {
                loadCategories();
                updateAuthState();

                // Register for auth state changes
                AuthService.onAuthStateChanged(function(isAuthenticated) {
                    updateAuthState();
                    // Use $applyAsync to ensure it runs after current digest cycle
                    $scope.$applyAsync();
                });

                // Also watch for route changes to ensure auth state is updated
                $scope.$on('$routeChangeSuccess', function() {
                    updateAuthState();
                });
            }

            /**
             * Load categories for navigation dropdown
             */
            function loadCategories() {
                CategoryService.getCategories().then(function(categories) {
                    // Limit to top categories for navigation (optional)
                    vm.categories = categories.slice(0, 10); // Show top 10 categories
                }).catch(function(error) {
                    console.error('Error loading categories for navigation:', error);
                    vm.categories = [];
                });
            }

            /**
             * Update authentication state
             */
            function updateAuthState() {
                vm.currentUser = AuthService.getCurrentUser();
                vm.isAuthenticated = AuthService.isAuthenticated();
                vm.authLoading = false; // Auth state has been determined
            }

            /**
             * Toggle categories dropdown visibility
             */
            vm.toggleCategoriesDropdown = function() {
                vm.showCategoriesDropdown = !vm.showCategoriesDropdown;
                vm.showUserDropdown = false; // Close user dropdown
            };

            /**
             * Toggle user dropdown visibility
             */
            vm.toggleUserDropdown = function() {
                vm.showUserDropdown = !vm.showUserDropdown;
                vm.showCategoriesDropdown = false; // Close categories dropdown
            };

            /**
             * Navigate to category page
             */
            vm.navigateToCategory = function(categorySlug) {
                vm.showCategoriesDropdown = false; // Close dropdown
                $location.path('/category/' + categorySlug);
            };

            /**
             * Navigate to login page
             */
            vm.goToLogin = function() {
                $location.path('/login');
            };

            /**
             * Logout user
             */
            vm.logout = function() {
                AuthService.logout();
                vm.currentUser = null;
                vm.showUserDropdown = false;
                $location.path('/');
            };

            /**
             * Navigate and close user dropdown (use from dropdown links so route updates reliably)
             */
            vm.navigateTo = function(path, searchParams) {
                vm.showUserDropdown = false;
                $location.path(path);
                if (searchParams) {
                    $location.search(searchParams);
                }
            };

            /**
             * Handle header search submission
             */
            vm.performHeaderSearch = function() {
                if (vm.searchQuery && vm.searchQuery.trim()) {
                    vm.hideSearchSuggestions();
                    $location.path('/search').search({q: vm.searchQuery.trim()});
                }
            };

            /**
             * Handle search input changes
             */
            vm.onSearchInputChange = function(event) {
                var query = vm.searchQuery.trim();

                // Clear previous timeout
                if (vm.suggestionTimeout) {
                    $timeout.cancel(vm.suggestionTimeout);
                }

                // Hide suggestions if query is too short
                if (query.length < 3) {
                    vm.hideSearchSuggestions();
                    return;
                }

                // Debounce the suggestion request
                vm.suggestionTimeout = $timeout(function() {
                    vm.loadSearchSuggestions(query);
                }, 300);
            };

            /**
             * Load search suggestions
             */
            vm.loadSearchSuggestions = function(query) {
                SearchService.getSuggestions(query).then(function(suggestions) {
                    vm.searchSuggestions = suggestions || [];
                    vm.showSearchSuggestions = vm.searchSuggestions.length > 0;
                    vm.selectedSuggestionIndex = -1;
                    $scope.$applyAsync();
                }).catch(function(error) {
                    vm.hideSearchSuggestions();
                });
            };

            /**
             * Hide search suggestions
             */
            vm.hideSearchSuggestions = function() {
                vm.showSearchSuggestions = false;
                vm.searchSuggestions = [];
                vm.selectedSuggestionIndex = -1;
            };

            /**
             * Select a search suggestion
             */
            vm.selectSearchSuggestion = function(suggestion) {
                // If suggestion has a video_id, navigate directly to video page
                if (suggestion.video_id) {
                    vm.hideSearchSuggestions();
                    $location.path('/video/' + suggestion.video_id);
                } else {
                    // Fallback to search page
                    vm.searchQuery = typeof suggestion === 'string' ? suggestion : suggestion.suggestion;
                    vm.performHeaderSearch();
                }
            };

            /**
             * Handle keyboard navigation for suggestions
             */
            vm.onSearchKeyDown = function(event) {
                if (!vm.showSearchSuggestions || vm.searchSuggestions.length === 0) {
                    return;
                }

                switch (event.keyCode) {
                    case 38: // Up arrow
                        event.preventDefault();
                        vm.selectedSuggestionIndex = Math.max(-1, vm.selectedSuggestionIndex - 1);
                        break;
                    case 40: // Down arrow
                        event.preventDefault();
                        vm.selectedSuggestionIndex = Math.min(vm.searchSuggestions.length - 1, vm.selectedSuggestionIndex + 1);
                        break;
                    case 13: // Enter
                        event.preventDefault();
                        if (vm.selectedSuggestionIndex >= 0) {
                            vm.selectSearchSuggestion(vm.searchSuggestions[vm.selectedSuggestionIndex]);
                        } else {
                            vm.performHeaderSearch();
                        }
                        break;
                    case 27: // Escape
                        vm.hideSearchSuggestions();
                        break;
                }
            };

            /**
             * Get category icon for display
             */
            vm.getCategoryIcon = function(slug) {
                return CategoryService.getCategoryIcon(slug);
            };

            // Close dropdown when clicking outside
            $scope.$on('$locationChangeStart', function() {
                vm.showCategoriesDropdown = false;
                vm.showUserDropdown = false;
                // Update auth state on route changes
                updateAuthState();
            });

            // Auth state changes are handled via callback from AuthService

            // Handle clicks outside dropdown to close it
            angular.element(document).on('click', function(event) {
                var target = event.target;
                var dropdownElement = null;
                var userDropdownElement = null;
                var searchContainer = null;

                // Traverse up the DOM to find dropdowns
                while (target && target !== document) {
                    if (angular.element(target).hasClass('nav-dropdown')) {
                        dropdownElement = target;
                    }
                    if (angular.element(target).hasClass('user-dropdown')) {
                        userDropdownElement = target;
                    }
                    if (angular.element(target).hasClass('search-bar')) {
                        searchContainer = target;
                    }
                    target = target.parentNode;
                }

                if (!dropdownElement && vm.showCategoriesDropdown) {
                    vm.showCategoriesDropdown = false;
                    $scope.$apply();
                }

                if (!userDropdownElement && vm.showUserDropdown) {
                    vm.showUserDropdown = false;
                    $scope.$apply();
                }

                if (!searchContainer && vm.showSearchSuggestions) {
                    vm.hideSearchSuggestions();
                    $scope.$apply();
                }
            });

            // Cleanup event listener on controller destroy
            $scope.$on('$destroy', function() {
                angular.element(document).off('click');
            });

            // Watch for search query changes
            $scope.$watch('headerCtrl.searchQuery', function(newValue, oldValue) {
                if (newValue !== oldValue) {
                    vm.onSearchInputChange();
                }
            });

            // Expose controller to scope
            $scope.headerCtrl = vm;
        }]);
})();