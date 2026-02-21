/**
 * Channel Sync Controller
 * Manages automatic YouTube channel synchronization
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .controller('ChannelSyncController', ['$scope', '$timeout', 'AdminService', 'CategoryService',
            function($scope, $timeout, AdminService, CategoryService) {

            var vm = this;

            // Controller properties
            vm.channels = [];
            vm.categories = [];
            vm.syncLogs = [];
            vm.stats = {};
            vm.isLoading = true;

            // Modal states
            vm.showAddModal = false;
            vm.showEditModal = false;
            vm.editingChannel = null;

            // New channel form
            vm.newChannel = {
                channel_id: '',
                channel_name: '',
                category_id: '',
                sync_frequency: 'daily',
                auto_import: true,
                require_approval: false,
                is_active: true,
                max_videos_per_sync: 10
            };

            // Initialize controller
            init();

            function init() {
                // Check authentication
                if (!AdminService.isAuthenticated()) {
                    window.location.href = '#/admin/login';
                    return;
                }

                // Load initial data
                loadCategories();
                loadChannels();
                loadStats();
                loadSyncLogs();

                // Set page title
                $scope.$root.setPageTitle('Channel Synchronization');
            }

            /**
             * Load categories for dropdowns
             */
            function loadCategories() {
                CategoryService.getCategories().then(function(categories) {
                    vm.categories = categories;
                }).catch(function(error) {
                    console.error('Error loading categories:', error);
                    vm.categories = [];
                });
            }

            /**
             * Load channel configurations
             */
            function loadChannels() {
                vm.isLoading = true;

                AdminService.getChannelSyncConfigs()
                    .then(function(response) {
                        vm.channels = response.data || [];
                        vm.isLoading = false;
                    })
                    .catch(function(error) {
                        console.error('Error loading channels:', error);
                        vm.channels = [];
                        vm.isLoading = false;
                        $scope.$root.showError('Failed to load channel configurations');
                    });
            }

            /**
             * Load synchronization statistics
             */
            function loadStats() {
                AdminService.getChannelSyncStats()
                    .then(function(response) {
                        vm.stats = response.data || {};
                        calculateTotals();
                    })
                    .catch(function(error) {
                        console.error('Error loading stats:', error);
                        vm.stats = {};
                    });
            }

            /**
             * Load synchronization logs
             */
            function loadSyncLogs() {
                AdminService.getChannelSyncLogs()
                    .then(function(response) {
                        vm.syncLogs = response.data || [];
                    })
                    .catch(function(error) {
                        console.error('Error loading sync logs:', error);
                        vm.syncLogs = [];
                    });
            }

            /**
             * Calculate totals from logs
             */
            function calculateTotals() {
                vm.totalImported = 0;
                vm.lastSync = 'Never';

                if (vm.syncLogs.length > 0) {
                    vm.syncLogs.forEach(function(log) {
                        vm.totalImported += log.videos_imported || 0;
                    });
                    vm.lastSync = vm.syncLogs[0].started_at;
                }
            }

            /**
             * Open add channel modal
             */
            vm.openAddModal = function() {
                vm.newChannel = {
                    channel_id: '',
                    channel_name: '',
                    category_id: '',
                    sync_frequency: 'daily',
                    auto_import: true,
                    require_approval: false,
                    is_active: true,
                    max_videos_per_sync: 10
                };
                vm.showAddModal = true;
                $timeout(function() {
                    $('#addChannelModal').modal('show');
                });
            };

            /**
             * Close add channel modal
             */
            vm.closeAddModal = function() {
                vm.showAddModal = false;
                $('#addChannelModal').modal('hide');
            };

            /**
             * Add new channel configuration
             */
            vm.addChannel = function() {
                if (!vm.newChannel.channel_id || !vm.newChannel.category_id) {
                    $scope.$root.showError('Channel ID and category are required');
                    return;
                }

                AdminService.createChannelSync(vm.newChannel)
                    .then(function(response) {
                        vm.closeAddModal();
                        loadChannels();
                        loadStats();
                        $scope.$root.showToast('Channel added successfully', 'success');
                    })
                    .catch(function(error) {
                        console.error('Error adding channel:', error);
                        $scope.$root.showError(error.data?.message || 'Failed to add channel');
                    });
            };

            /**
             * Open edit channel modal
             */
            vm.openEditModal = function(channel) {
                vm.editingChannel = angular.copy(channel);
                vm.showEditModal = true;
                $timeout(function() {
                    $('#editChannelModal').modal('show');
                });
            };

            /**
             * Close edit channel modal
             */
            vm.closeEditModal = function() {
                vm.showEditModal = false;
                vm.editingChannel = null;
                $('#editChannelModal').modal('hide');
            };

            /**
             * Update channel configuration
             */
            vm.updateChannel = function() {
                if (!vm.editingChannel) return;

                AdminService.updateChannelSync(vm.editingChannel.id, vm.editingChannel)
                    .then(function(response) {
                        vm.closeEditModal();
                        loadChannels();
                        loadStats();
                        $scope.$root.showToast('Channel configuration updated successfully', 'success');
                    })
                    .catch(function(error) {
                        console.error('Error updating channel:', error);
                        $scope.$root.showError(error.data?.message || 'Failed to update channel configuration');
                    });
            };

            /**
             * Delete channel configuration
             */
            vm.deleteChannel = function(channelId) {
                if (!confirm('Are you sure you want to remove this channel configuration?')) {
                    return;
                }

                AdminService.deleteChannelSync(channelId)
                    .then(function(response) {
                        loadChannels();
                        loadStats();
                        loadSyncLogs();
                        $scope.$root.showToast('Channel configuration removed successfully', 'success');
                    })
                    .catch(function(error) {
                        console.error('Error deleting channel:', error);
                        $scope.$root.showError(error.data?.message || 'Failed to remove channel configuration');
                    });
            };

            /**
             * Run synchronization for all channels
             */
            vm.runAllSync = function() {
                if (!confirm('Run synchronization for all active channels?')) {
                    return;
                }

                $scope.$root.showLoading('Running channel synchronization...');
                
                AdminService.runAllChannelSync()
                    .then(function(response) {
                        $scope.$root.hideLoading();
                        loadChannels();
                        loadStats();
                        loadSyncLogs();
                        $scope.$root.showToast('Channel synchronization completed', 'success');
                        
                        // Show detailed results
                        var results = response.data.results;
                        var message = 'Synchronization completed:\n';
                        results.forEach(function(result) {
                            message += `\n${result.channel}: ${result.imported} imported, ${result.skipped} skipped`;
                        });
                        alert(message);
                    })
                    .catch(function(error) {
                        $scope.$root.hideLoading();
                        console.error('Error running sync:', error);
                        $scope.$root.showError(error.data?.message || 'Failed to run synchronization');
                    });
            };

            /**
             * Run synchronization for specific channel
             */
            vm.runChannelSync = function(channelId) {
                $scope.$root.showLoading('Running channel synchronization...');
                
                AdminService.runChannelSync(channelId)
                    .then(function(response) {
                        $scope.$root.hideLoading();
                        loadChannels();
                        loadStats();
                        loadSyncLogs();
                        $scope.$root.showToast('Channel synchronization completed', 'success');
                        
                        var result = response.data.result;
                        var message = `${result.channel}: ${result.imported} videos imported, ${result.skipped} skipped`;
                        alert(message);
                    })
                    .catch(function(error) {
                        $scope.$root.hideLoading();
                        console.error('Error running channel sync:', error);
                        $scope.$root.showError(error.data?.message || 'Failed to run channel synchronization');
                    });
            };

            // Expose controller to scope
            $scope.vm = vm;
        }]);
})();