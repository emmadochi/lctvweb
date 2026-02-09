/**
 * Profile Controller
 * Handles user profile management
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .controller('ProfileController', ['$scope', '$location', 'AuthService', '$timeout',
            function($scope, $location, AuthService, $timeout) {

            var vm = this;

            // Controller properties
            vm.user = null;
            vm.isLoading = true;
            vm.isSaving = false;
            vm.successMessage = '';
            vm.errorMessage = '';
            vm.profileForm = {};

            // Profile data
            vm.profileData = {
                first_name: '',
                last_name: ''
            };

            // Password data
            vm.passwordData = {
                current_password: '',
                new_password: '',
                confirm_password: ''
            };

            // Initialize controller
            init();

            function init() {
                // Check authentication
                if (!AuthService.isAuthenticated()) {
                    $location.path('/login');
                    return;
                }

                loadProfile();
            }

            /**
             * Load user profile
             */
            function loadProfile() {
                vm.isLoading = true;

                AuthService.refreshProfile()
                    .then(function(user) {
                        vm.user = user;
                        vm.profileData = {
                            first_name: user.first_name || '',
                            last_name: user.last_name || ''
                        };
                        vm.isLoading = false;
                    })
                    .catch(function(error) {
                        console.error('Error loading profile:', error);
                        vm.errorMessage = 'Failed to load profile';
                        vm.isLoading = false;
                    });
            }

            /**
             * Update profile
             */
            vm.updateProfile = function() {
                // Reset messages
                vm.errorMessage = '';
                vm.successMessage = '';

                // Validate
                if (!vm.profileData.first_name.trim() || !vm.profileData.last_name.trim()) {
                    vm.errorMessage = 'Please enter both first and last name';
                    return;
                }

                vm.isSaving = true;

                AuthService.updateProfile(vm.profileData)
                    .then(function(user) {
                        vm.user = user;
                        vm.showSuccess('Profile updated successfully!');
                        vm.isSaving = false;
                    })
                    .catch(function(error) {
                        vm.errorMessage = error.message || 'Failed to update profile';
                        vm.isSaving = false;
                    });
            };

            /**
             * Update password
             */
            vm.updatePassword = function() {
                // Reset messages
                vm.errorMessage = '';
                vm.successMessage = '';

                // Validate
                if (!vm.passwordData.current_password) {
                    vm.errorMessage = 'Please enter your current password';
                    return;
                }

                if (!vm.passwordData.new_password) {
                    vm.errorMessage = 'Please enter a new password';
                    return;
                }

                if (vm.passwordData.new_password.length < 6) {
                    vm.errorMessage = 'New password must be at least 6 characters long';
                    return;
                }

                if (vm.passwordData.new_password !== vm.passwordData.confirm_password) {
                    vm.errorMessage = 'New passwords do not match';
                    return;
                }

                vm.isSaving = true;

                AuthService.updatePassword({
                    current_password: vm.passwordData.current_password,
                    new_password: vm.passwordData.new_password
                })
                    .then(function(message) {
                        vm.showSuccess(message || 'Password updated successfully!');
                        vm.resetPasswordForm();
                        vm.isSaving = false;
                    })
                    .catch(function(error) {
                        vm.errorMessage = error.message || 'Failed to update password';
                        vm.isSaving = false;
                    });
            };

            /**
             * Reset password form
             */
            vm.resetPasswordForm = function() {
                vm.passwordData = {
                    current_password: '',
                    new_password: '',
                    confirm_password: ''
                };
            };

            /**
             * Show success message
             */
            vm.showSuccess = function(message) {
                vm.successMessage = message;
                // Clear after 3 seconds
                setTimeout(function() {
                    vm.successMessage = '';
                    $scope.$apply();
                }, 3000);
            };

            /**
             * Cancel editing
             */
            vm.cancel = function() {
                // Reset form data
                vm.profileData = {
                    first_name: vm.user.first_name || '',
                    last_name: vm.user.last_name || ''
                };
                vm.errorMessage = '';
                vm.successMessage = '';
            };

            /**
             * Logout
             */
            vm.logout = function() {
                AuthService.logout();
                $location.path('/');
            };

            // Expose controller to scope
            $scope.vm = vm;
        }]);
})();