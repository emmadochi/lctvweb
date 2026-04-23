/**
 * Forgot Password Controller
 * Handles requesting a password reset link
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .controller('ForgotPasswordController', ['$scope', '$location', 'AuthService',
            function($scope, $location, AuthService) {

            var vm = this;

            vm.email = '';
            vm.isLoading = false;
            vm.errorMessage = '';
            vm.successMessage = '';
            vm.currentYear = new Date().getFullYear();

            /**
             * Submit reset request
             */
            vm.submit = function() {
                vm.errorMessage = '';
                vm.successMessage = '';

                if (!vm.email) {
                    vm.errorMessage = 'Please enter your email address';
                    return;
                }

                vm.isLoading = true;

                AuthService.requestPasswordReset(vm.email)
                    .then(function(message) {
                        vm.isLoading = false;
                        vm.successMessage = message || 'If an account exists for that email, a reset link has been sent.';
                        vm.email = ''; // Clear email field
                    })
                    .catch(function(error) {
                        vm.isLoading = false;
                        vm.errorMessage = error.message || 'Failed to request password reset. Please try again.';
                        console.error('Forgot password error:', error);
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
