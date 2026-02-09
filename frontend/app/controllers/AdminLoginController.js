/**
 * Admin Login Controller
 * Handles admin authentication and login page
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .controller('AdminLoginController', ['$scope', '$location', 'AdminService',
            function($scope, $location, AdminService) {

            var vm = this;

            // Controller properties
            vm.credentials = {
                email: '',
                password: ''
            };
            vm.isLoading = false;
            vm.errorMessage = '';
            vm.loginForm = {};

            // Check if already authenticated
            if (AdminService.isAuthenticated()) {
                // Redirect to admin dashboard
                $location.path('/admin');
                return;
            }

            /**
             * Handle login form submission
             */
            vm.login = function() {
                // Reset error message
                vm.errorMessage = '';

                // Validate form
                if (!vm.credentials.email || !vm.credentials.password) {
                    vm.errorMessage = 'Please enter both email and password';
                    return;
                }

                // Set loading state
                vm.isLoading = true;

                // Attempt login
                AdminService.login(vm.credentials.email, vm.credentials.password)
                    .then(function(response) {
                        // Login successful
                        console.log('Admin login successful:', response.user);

                        // Show success message
                        vm.showSuccess('Login successful! Redirecting...');

                        // Redirect to admin dashboard after short delay
                        setTimeout(function() {
                            $location.path('/admin');
                            $scope.$apply();
                        }, 1000);
                    })
                    .catch(function(error) {
                        // Login failed
                        vm.isLoading = false;

                        // Handle different error types
                        if (error.status === 401) {
                            vm.errorMessage = 'Invalid email or password';
                        } else if (error.status === 403) {
                            vm.errorMessage = 'Account is disabled or access denied';
                        } else if (error.status === 429) {
                            vm.errorMessage = 'Too many login attempts. Please try again later.';
                        } else {
                            vm.errorMessage = error.data?.message || 'Login failed. Please try again.';
                        }

                        console.error('Admin login error:', error);
                    });
            };

            /**
             * Show success message
             */
            vm.showSuccess = function(message) {
                // Create a simple success indicator
                vm.successMessage = message;

                // Clear after 3 seconds
                setTimeout(function() {
                    vm.successMessage = '';
                    $scope.$apply();
                }, 3000);
            };

            /**
             * Clear form
             */
            vm.clearForm = function() {
                vm.credentials = {
                    email: '',
                    password: ''
                };
                vm.errorMessage = '';
                vm.loginForm.$setPristine();
                vm.loginForm.$setUntouched();
            };

            /**
             * Handle enter key in password field
             */
            vm.onPasswordKeyPress = function(event) {
                if (event.keyCode === 13) { // Enter key
                    vm.login();
                }
            };

            // Watch for authentication state changes
            $scope.$on('admin:authChanged', function(event, isAuthenticated) {
                if (isAuthenticated) {
                    $location.path('/admin');
                }
            });

            // Expose controller to scope
            $scope.vm = vm;
        }]);
})();