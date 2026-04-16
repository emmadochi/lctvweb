/**
 * Admin Donations Controller
 * Manages giving categories and payment settings
 */
(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .controller('AdminDonationsController', ['$rootScope', '$scope', 'AdminService', function($rootScope, $scope, AdminService) {
            var vm = this;

            vm.isLoading = false;
            vm.settings = {
                giving_type: [],
                bank: [],
                crypto: [],
                gateway: []
            };

            vm.newCategory = {
                key: '',
                value: '',
                group: 'giving_type',
                is_active: 1
            };

            // Initialize
            vm.init = function() {
                vm.loadSettings();
            };

            vm.loadSettings = function() {
                vm.isLoading = true;
                AdminService.getDonationSettings()
                    .then(function(response) {
                        vm.settings = response.data || { giving_type: [], bank: [], crypto: [], gateway: [] };
                    })
                    .catch(function(error) {
                        $rootScope.showToast('Failed to load settings', 'error');
                    })
                    .finally(function() {
                        vm.isLoading = false;
                    });
            };

            vm.addCategory = function() {
                if (!vm.newCategory.key || !vm.newCategory.value) {
                    $rootScope.showToast('Please enter both name and value', 'warning');
                    return;
                }

                vm.isLoading = true;
                AdminService.saveDonationSetting(vm.newCategory)
                    .then(function(response) {
                        $rootScope.showToast('Category added successfully', 'success');
                        vm.newCategory.key = '';
                        vm.newCategory.value = '';
                        vm.loadSettings();
                        $('#addCategoryModal').modal('hide');
                    })
                    .catch(function(error) {
                        $rootScope.showToast('Failed to add category', 'error');
                    })
                    .finally(function() {
                        vm.isLoading = false;
                    });
            };

            vm.deleteSetting = function(setting) {
                if (!confirm('Are you sure you want to delete this category?')) return;

                vm.isLoading = true;
                AdminService.deleteDonationSetting(setting.id)
                    .then(function(response) {
                        $rootScope.showToast('Entry deleted successfully', 'success');
                        vm.loadSettings();
                    })
                    .catch(function(error) {
                        $rootScope.showToast('Failed to delete entry', 'error');
                    })
                    .finally(function() {
                        vm.isLoading = false;
                    });
            };

            vm.toggleStatus = function(setting) {
                var updatedSetting = angular.copy(setting);
                updatedSetting.is_active = setting.is_active ? 0 : 1;
                // Key and Group are used as identifiers in the backend save method
                updatedSetting.key = setting.setting_key;
                updatedSetting.value = setting.setting_value;
                updatedSetting.group = setting.setting_group;

                vm.isLoading = true;
                AdminService.saveDonationSetting(updatedSetting)
                    .then(function(response) {
                        setting.is_active = updatedSetting.is_active;
                        $rootScope.showToast('Status updated', 'success');
                    })
                    .catch(function(error) {
                        $rootScope.showToast('Failed to update status', 'error');
                    })
                    .finally(function() {
                        vm.isLoading = false;
                    });
            };

            vm.init();
        }]);
})();
