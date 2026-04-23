/**
 * Admin Prayer Controller
 * Handles management of prayer requests by administrators
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .controller('AdminPrayerController', ['$scope', '$rootScope', '$location', '$timeout', 'AdminService', 
            function($scope, $rootScope, $location, $timeout, AdminService) {
            
            var vm = this;

            // State
            vm.isLoading = false;
            vm.prayerRequests = [];
            vm.filters = {
                status: 'pending' // 'pending', 'answered', 'all'
            };

            // Modal data
            vm.selectedRequest = null;
            vm.adminResponse = '';
            vm.isSubmittingResponse = false;

            /**
             * Initialize controller
             */
            vm.init = function() {
                if (!AdminService.isAuthenticated()) {
                    $location.path('/admin/login');
                    return;
                }
                vm.loadRequests();
            };

            /**
             * Load prayer requests
             */
            vm.loadRequests = function() {
                vm.isLoading = true;
                
                var filterParams = {};
                if (vm.filters.status !== 'all') {
                    filterParams.status = vm.filters.status;
                }

                AdminService.getPrayerRequests(filterParams)
                    .then(function(requests) {
                        vm.prayerRequests = requests;
                    })
                    .catch(function(err) {
                        console.error('Error loading prayer requests:', err);
                        $rootScope.showToast('Failed to load prayer requests', 'danger');
                    })
                    .finally(function() {
                        vm.isLoading = false;
                    });
            };

            /**
             * Filter requests
             */
            vm.applyFilters = function() {
                vm.loadRequests();
            };

            /**
             * Open response modal
             */
            vm.openRespondModal = function(request) {
                vm.selectedRequest = request;
                vm.adminResponse = request.admin_response || '';
                $('#respondModal').modal('show');
            };

            /**
             * Submit response
             */
            vm.submitResponse = function() {
                if (!vm.adminResponse) {
                    $rootScope.showToast('Please enter a response', 'warning');
                    return;
                }

                vm.isSubmittingResponse = true;
                AdminService.respondToPrayerRequest(vm.selectedRequest.id, vm.adminResponse)
                    .then(function() {
                        $rootScope.showToast('Response submitted and user notified', 'success');
                        $('#respondModal').modal('hide');
                        vm.loadRequests();
                    })
                    .catch(function(err) {
                        console.error('Error responding to prayer request:', err);
                        $rootScope.showToast('Failed to submit response', 'danger');
                    })
                    .finally(function() {
                        vm.isSubmittingResponse = false;
                    });
            };

            /**
             * View attachment
             */
            vm.viewAttachment = function(url) {
                if (!url) return;
                // Construct full URL (assuming relative to API base or root)
                // In our PHP backend, we store 'uploads/prayer_requests/...'
                // We need to point to the base URL
                var baseUrl = window.location.origin + window.location.pathname.split('/frontend/')[0] + '/backend/';
                window.open(baseUrl + url, '_blank');
            };

            /**
             * Delete request
             */
            vm.deleteRequest = function(request) {
                if (confirm('Are you sure you want to delete this prayer request from ' + request.full_name + '?')) {
                    AdminService.deletePrayerRequest(request.id)
                        .then(function() {
                            $rootScope.showToast('Prayer request deleted', 'success');
                            vm.loadRequests();
                        })
                        .catch(function(err) {
                            console.error('Error deleting prayer request:', err);
                            $rootScope.showToast('Failed to delete request', 'danger');
                        });
                }
            };

            // Run initialization
            vm.init();

        }]);
})();
