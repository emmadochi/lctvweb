/**
 * Header Controller
 * Handles header navigation, categories dropdown, and user authentication
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .controller('HeaderController', ['$scope', '$location', 'CategoryService', 'AuthService',
            function($scope, $location, CategoryService, AuthService) {

            var vm = this;

            // Controller properties
            vm.categories = [];
            vm.showCategoriesDropdown = false;
            vm.showUserDropdown = false;
            vm.currentUser = null;

            /**
             * Check if user is authenticated (defined before init so updateAuthState can use it)
             */
            vm.isAuthenticated = function() {
                return AuthService.isAuthenticated();
            };

            // Initialize controller
            init();

            function init() {
                loadCategories();
                updateAuthState();

                // Register for auth state changes
                AuthService.onAuthStateChanged(function(isAuthenticated) {
                    updateAuthState();
                    $scope.$apply();
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

                // Traverse up the DOM to find dropdowns
                while (target && target !== document) {
                    if (angular.element(target).hasClass('nav-dropdown')) {
                        dropdownElement = target;
                    }
                    if (angular.element(target).hasClass('user-dropdown')) {
                        userDropdownElement = target;
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
            });

            // Cleanup event listener on controller destroy
            $scope.$on('$destroy', function() {
                angular.element(document).off('click');
            });

            // Expose controller to scope
            $scope.headerCtrl = vm;
        }]);
})();