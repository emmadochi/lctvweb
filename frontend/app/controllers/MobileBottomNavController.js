/**
 * Mobile Bottom Navigation Controller
 * Handles mobile bottom navigation bar and modals
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .controller('MobileBottomNavController', ['$scope', '$location', 'CategoryService', 'AuthService',
            function($scope, $location, CategoryService, AuthService) {

            var vm = this;

            // Controller properties
            vm.categories = [];
            vm.currentUser = null;
            vm.isAuthenticated = false;
            vm.showCategoriesModal = false;
            vm.showProfileModal = false;

            // Initialize controller
            init();

            function init() {
                loadCategories();
                updateAuthState();

                // Register for auth state changes
                AuthService.onAuthStateChanged(function(isAuthenticated) {
                    updateAuthState();
                    $scope.$applyAsync();
                });
            }

            /**
             * Load categories for modal
             */
            function loadCategories() {
                CategoryService.getCategories().then(function(categories) {
                    vm.categories = categories;
                }).catch(function(error) {
                    console.error('Error loading categories for mobile nav:', error);
                    vm.categories = [];
                });
            }

            /**
             * Update authentication state
             */
            function updateAuthState() {
                vm.currentUser = AuthService.getCurrentUser();
                vm.isAuthenticated = AuthService.isAuthenticated();
            }

            /**
             * Toggle categories modal
             */
            vm.toggleCategoriesModal = function() {
                vm.showCategoriesModal = !vm.showCategoriesModal;
                vm.showProfileModal = false; // Close profile modal

                // Prevent body scroll when modal is open
                if (vm.showCategoriesModal) {
                    document.body.style.overflow = 'hidden';
                } else {
                    document.body.style.overflow = '';
                }
            };

            /**
             * Close categories modal
             */
            vm.closeCategoriesModal = function() {
                vm.showCategoriesModal = false;
                document.body.style.overflow = '';
            };

            /**
             * Toggle profile modal
             */
            vm.toggleProfileModal = function() {
                vm.showProfileModal = !vm.showProfileModal;
                vm.showCategoriesModal = false; // Close categories modal

                // Prevent body scroll when modal is open
                if (vm.showProfileModal) {
                    document.body.style.overflow = 'hidden';
                } else {
                    document.body.style.overflow = '';
                }
            };

            /**
             * Close profile modal
             */
            vm.closeProfileModal = function() {
                vm.showProfileModal = false;
                document.body.style.overflow = '';
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
                vm.showProfileModal = false;
                $location.path('/');
            };

            /**
             * Get category icon for display
             */
            vm.getCategoryIcon = function(slug) {
                return CategoryService.getCategoryIcon(slug);
            };

            // Close modals on route change
            $scope.$on('$locationChangeStart', function() {
                vm.showCategoriesModal = false;
                vm.showProfileModal = false;
                document.body.style.overflow = '';
            });

            // Expose controller to scope
            $scope.bottomNavCtrl = vm;
        }]);
})();