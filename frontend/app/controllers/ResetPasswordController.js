/**
 * Reset Password Controller
 * Handles password reset via emailed token
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .controller('ResetPasswordController', ['$scope', '$location', 'AuthService',
            function($scope, $location, AuthService) {

            var vm = this;

            vm.token = ($location.search().token || '').trim();
            vm.password = '';
            vm.passwordConfirm = '';
            vm.isLoading = false;
            vm.errorMessage = '';
            vm.successMessage = '';
            vm.currentYear = new Date().getFullYear();

            /**
             * Submit new password
             */
            vm.submit = function() {
                vm.errorMessage = '';
                vm.successMessage = '';

                if (!vm.token) {
                    vm.errorMessage = 'Reset token is missing or invalid. Please request a new reset link.';
                    return;
                }

                if (!vm.password) {
                    vm.errorMessage = 'Please enter a new password';
                    return;
                }

                if (vm.password.length < 8) {
                    vm.errorMessage = 'Password must be at least 8 characters long';
                    return;
                }

                if (vm.password !== vm.passwordConfirm) {
                    vm.errorMessage = 'Passwords do not match';
                    return;
                }

                vm.isLoading = true;

                AuthService.resetPassword(vm.token, vm.password)
                    .then(function(message) {
                        vm.isLoading = false;
                        vm.successMessage = message || 'Your password has been reset successfully.';

                        // Redirect to login after short delay
                        setTimeout(function() {
                            $location.path('/login');
                            $scope.$apply();
                        }, 3000);
                    })
                    .catch(function(error) {
                        vm.isLoading = false;
                        vm.errorMessage = error.message || 'Failed to reset password. The link may have expired.';
                        console.error('Reset password error:', error);
                    });
            };

            /**
             * Handle enter key
             */
            vm.onKeyPress = function(event) {
                if (event.keyCode === 13) {
                    vm.submit();
                }
            };

            // Expose controller to scope
            $scope.vm = vm;
        }]);
})();

