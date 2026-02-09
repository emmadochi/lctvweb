/**
 * Profile Controller
 * Handles user profile management
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .controller('ProfileController', ['$scope', '$location', 'AuthService', 'PushService', 'OfflineStorageService', '$timeout',
            function($scope, $location, AuthService, PushService, OfflineStorageService, $timeout) {

            var vm = this;

            // Controller properties
            vm.user = null;
            vm.isLoading = true;
            vm.isSaving = false;
            vm.successMessage = '';
            vm.errorMessage = '';
            vm.profileForm = {};

            // Push notification properties
            vm.pushStatus = null;
            vm.pushPreferences = null;
            vm.isInstalled = false;

            // Offline storage properties
            vm.storageStats = null;
            vm.offlineVideos = [];
            vm.downloadSettings = {
                autoDownload: false,
                wifiOnly: true,
                quality: '720p'
            };

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
                initializePushNotifications();
                checkPWAInstallStatus();
                initializeOfflineStorage();
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

            /**
             * Initialize offline storage
             */
            function initializeOfflineStorage() {
                // Load download settings
                vm.downloadSettings = getDownloadSettings();

                // Load offline videos
                loadOfflineVideos();

                // Load storage stats
                loadStorageStats();
            }

            /**
             * Load offline videos
             */
            function loadOfflineVideos() {
                OfflineStorageService.getAllOfflineVideos()
                    .then(function(videos) {
                        vm.offlineVideos = videos;
                    })
                    .catch(function(error) {
                        console.error('Error loading offline videos:', error);
                        vm.offlineVideos = [];
                    });
            }

            /**
             * Load storage statistics
             */
            function loadStorageStats() {
                OfflineStorageService.getStorageStats()
                    .then(function(stats) {
                        vm.storageStats = stats;
                    })
                    .catch(function(error) {
                        console.error('Error loading storage stats:', error);
                    });
            }

            /**
             * Get download settings from localStorage
             */
            function getDownloadSettings() {
                try {
                    var settings = localStorage.getItem('lcmtv_download_settings');
                    return settings ? JSON.parse(settings) : vm.downloadSettings;
                } catch (error) {
                    console.error('Error loading download settings:', error);
                    return vm.downloadSettings;
                }
            }

            /**
             * Update download settings
             */
            vm.updateDownloadSettings = function() {
                try {
                    localStorage.setItem('lcmtv_download_settings', JSON.stringify(vm.downloadSettings));
                    vm.showSuccessMessage('Download settings updated!');
                } catch (error) {
                    vm.showErrorMessage('Failed to save download settings.');
                    console.error('Error saving download settings:', error);
                }
            };

            /**
             * Play offline video
             */
            vm.playOfflineVideo = function(videoId) {
                // Navigate to video player with offline flag
                $location.path('/video/' + videoId).search({ offline: true });
            };

            /**
             * Remove offline video
             */
            vm.removeOfflineVideo = function(videoId) {
                if (confirm('Are you sure you want to remove this video from offline storage?')) {
                    OfflineStorageService.removeOfflineVideo(videoId)
                        .then(function() {
                            vm.showSuccessMessage('Video removed from offline storage.');
                            loadOfflineVideos(); // Refresh the list
                            loadStorageStats(); // Update storage stats
                        })
                        .catch(function(error) {
                            vm.showErrorMessage('Failed to remove video from offline storage.');
                            console.error('Error removing offline video:', error);
                        });
                }
            };

            /**
             * Clear all downloads
             */
            vm.clearAllDownloads = function() {
                if (confirm('Are you sure you want to remove ALL downloaded content? This action cannot be undone.')) {
                    OfflineStorageService.clearAllOfflineData()
                        .then(function() {
                            vm.showSuccessMessage('All offline content cleared.');
                            vm.offlineVideos = [];
                            loadStorageStats(); // Update storage stats
                        })
                        .catch(function(error) {
                            vm.showErrorMessage('Failed to clear offline content.');
                            console.error('Error clearing offline data:', error);
                        });
                }
            };

            /**
             * Format bytes utility
             */
            vm.formatBytes = function(bytes) {
                if (!bytes || bytes === 0) return '0 B';
                var k = 1024;
                var sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
                var i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
            };

            /**
             * Initialize push notifications
             */
            function initializePushNotifications() {
                // Check push notification support and status
                PushService.getSubscriptionStatus()
                    .then(function(status) {
                        vm.pushStatus = status;
                    })
                    .catch(function(error) {
                        console.error('Error checking push status:', error);
                        vm.pushStatus = { supported: false, subscribed: false };
                    });

                // Load notification preferences
                vm.pushPreferences = PushService.getPreferences();
            }

            /**
             * Check if PWA is installed
             */
            function checkPWAInstallStatus() {
                // Check if running in standalone mode (installed PWA)
                vm.isInstalled = window.matchMedia('(display-mode: standalone)').matches ||
                                window.navigator.standalone === true ||
                                document.referrer.includes('android-app://');
            }

            /**
             * Request notification permission
             */
            vm.requestNotificationPermission = function() {
                PushService.requestPermission()
                    .then(function() {
                        vm.showSuccessMessage('Notification permission granted!');
                        initializePushNotifications(); // Refresh status
                    })
                    .catch(function(error) {
                        vm.showErrorMessage('Notification permission was denied. You can enable it in your browser settings.');
                        console.error('Permission request failed:', error);
                    });
            };

            /**
             * Subscribe to push notifications
             */
            vm.subscribeToNotifications = function() {
                vm.isLoading = true;

                PushService.subscribe()
                    .then(function() {
                        vm.showSuccessMessage('Successfully subscribed to notifications!');
                        initializePushNotifications(); // Refresh status
                    })
                    .catch(function(error) {
                        vm.showErrorMessage('Failed to subscribe to notifications. Please try again.');
                        console.error('Subscription failed:', error);
                    })
                    .finally(function() {
                        vm.isLoading = false;
                    });
            };

            /**
             * Unsubscribe from push notifications
             */
            vm.unsubscribeFromNotifications = function() {
                vm.isLoading = true;

                PushService.unsubscribe()
                    .then(function() {
                        vm.showSuccessMessage('Successfully unsubscribed from notifications.');
                        initializePushNotifications(); // Refresh status
                    })
                    .catch(function(error) {
                        vm.showErrorMessage('Failed to unsubscribe from notifications. Please try again.');
                        console.error('Unsubscription failed:', error);
                    })
                    .finally(function() {
                        vm.isLoading = false;
                    });
            };

            /**
             * Update notification preferences
             */
            vm.updateNotificationPreferences = function() {
                PushService.updatePreferences(vm.pushPreferences)
                    .then(function() {
                        vm.showSuccessMessage('Notification preferences updated successfully!');
                    })
                    .catch(function(error) {
                        vm.showErrorMessage('Failed to update preferences, but they were saved locally.');
                        console.error('Preference update failed:', error);
                    });
            };

            /**
             * Send test notification
             */
            vm.testNotification = function() {
                PushService.sendTestNotification()
                    .then(function() {
                        vm.showSuccessMessage('Test notification sent! Check your notifications.');
                    })
                    .catch(function(error) {
                        vm.showErrorMessage('Failed to send test notification.');
                        console.error('Test notification failed:', error);
                    });
            };

            /**
             * Show success message
             */
            vm.showSuccessMessage = function(message) {
                vm.successMessage = message;
                vm.errorMessage = '';
                $timeout(function() {
                    vm.successMessage = '';
                }, 5000);
            };

            /**
             * Show error message
             */
            vm.showErrorMessage = function(message) {
                vm.errorMessage = message;
                vm.successMessage = '';
                $timeout(function() {
                    vm.errorMessage = '';
                }, 5000);
            };

            // Expose controller to scope
            $scope.vm = vm;
        }]);
})();