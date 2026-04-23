/**
 * Prayer Controller
 * Handles logic for the prayer request page
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .controller('PrayerController', ['$scope', '$rootScope', 'PrayerService', 'AuthService', 'I18nService',
            function($scope, $rootScope, PrayerService, AuthService, I18nService) {
                
            var vm = this;

            // Page properties
            vm.title = 'Prayer Point';
            vm.subTitle = 'Let us stand with you in agreement. "For where two or three gather in my name, there am I with them." - Matthew 18:20';
            
            // Interaction state
            vm.submitting = false;
            vm.success = false;
            vm.error = null;
            vm.prayerRequests = [];
            vm.loadingRequests = false;

            // Form data
            vm.formData = {
                full_name: '',
                email: '',
                city: '',
                country: '',
                phone: '',
                category: 'General',
                request_text: '',
                attachment: null
            };

            // Prayer categories
            vm.categories = [
                'General',
                'Healing',
                'Finance',
                'Family',
                'Careers',
                'Spiritual Growth',
                'Protection',
                'Guidance',
                'Others'
            ];

            // Initialize
            init();

            function init() {
                $rootScope.setPageTitle(vm.title);
                
                // Pre-fill user data if authenticated
                var user = AuthService.getCurrentUser();
                if (user) {
                    vm.formData.full_name = (user.first_name + ' ' + (user.last_name || '')).trim();
                    vm.formData.email = user.email;
                    loadUserRequests();
                }
            }

            /**
             * Load the user's prayer requests history
             */
            function loadUserRequests() {
                if (!AuthService.isAuthenticated()) return;
                
                vm.loadingRequests = true;
                PrayerService.getUserRequests()
                    .then(function(requests) {
                        vm.prayerRequests = requests;
                    })
                    .catch(function(err) {
                        console.error('Failed to load prayer requests:', err);
                    })
                    .finally(function() {
                        vm.loadingRequests = false;
                    });
            }

            /**
             * Submit the prayer request form
             */
            vm.submitForm = function() {
                if ($scope.prayerForm.$invalid) {
                    return;
                }

                vm.submitting = true;
                vm.error = null;

                PrayerService.submitRequest(vm.formData)
                    .then(function(response) {
                        vm.success = true;
                        vm.resetForm();
                        $rootScope.showToast('Your prayer request has been submitted.', 'success');
                        loadUserRequests();
                    })
                    .catch(function(err) {
                        vm.error = 'Failed to submit request. Please try again.';
                        $rootScope.showToast(vm.error, 'danger');
                    })
                    .finally(function() {
                        vm.submitting = false;
                    });
            };

            /**
             * Reset the form data
             */
            vm.resetForm = function() {
                var user = AuthService.getCurrentUser();
                vm.formData = {
                    full_name: user ? (user.first_name + ' ' + (user.last_name || '')).trim() : '',
                    email: user ? user.email : '',
                    city: '',
                    country: '',
                    phone: '',
                    category: 'General',
                    request_text: '',
                    attachment: null
                };
                // Clear the file input
                var fileInput = document.getElementById('attachment');
                if (fileInput) fileInput.value = '';
                if ($scope.prayerForm) {
                    $scope.prayerForm.$setPristine();
                    $scope.prayerForm.$setUntouched();
                }
            };

            /**
             * Get translated text
             */
            vm.t = function(key) {
                return I18nService.translate(key);
            };

            /**
             * Handle file selection from input
             */
            vm.onFileSelect = function(element) {
                if (element.files && element.files.length > 0) {
                    $scope.$apply(function() {
                        vm.formData.attachment = element.files[0];
                    });
                }
            };

            /**
             * Remove selected file
             */
            vm.removeFile = function() {
                vm.formData.attachment = null;
                var fileInput = document.getElementById('attachment');
                if (fileInput) fileInput.value = '';
            };

        }]);
})();
