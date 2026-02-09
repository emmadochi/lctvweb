/**
 * Push Notification Service
 * Handles push notification subscriptions and management
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .factory('PushService', ['$http', '$q', 'API_BASE', function($http, $q, API_BASE) {

            var service = {};

            // VAPID public key for push notifications (should come from backend)
            var vapidPublicKey = null;

            /**
             * Check if push notifications are supported
             */
            service.isSupported = function() {
                return ('serviceWorker' in navigator &&
                        'PushManager' in window &&
                        'Notification' in window);
            };

            /**
             * Check if user has granted notification permission
             */
            service.hasPermission = function() {
                return Notification.permission === 'granted';
            };

            /**
             * Request notification permission from user
             */
            service.requestPermission = function() {
                return $q(function(resolve, reject) {
                    if (!service.isSupported()) {
                        reject(new Error('Push notifications not supported'));
                        return;
                    }

                    Notification.requestPermission()
                        .then(function(permission) {
                            if (permission === 'granted') {
                                console.log('Notification permission granted');
                                resolve(permission);
                            } else {
                                console.log('Notification permission denied');
                                reject(new Error('Permission denied'));
                            }
                        })
                        .catch(function(error) {
                            console.error('Error requesting notification permission:', error);
                            reject(error);
                        });
                });
            };

            /**
             * Get VAPID public key from server
             */
            service.getVapidPublicKey = function() {
                return $http.get(API_BASE + '/push/vapid-public-key')
                    .then(function(response) {
                        vapidPublicKey = response.data.data;
                        return vapidPublicKey;
                    })
                    .catch(function(error) {
                        console.error('Error getting VAPID key:', error);
                        throw error;
                    });
            };

            /**
             * Subscribe to push notifications
             */
            service.subscribe = function() {
                return $q(function(resolve, reject) {
                    if (!service.isSupported()) {
                        reject(new Error('Push notifications not supported'));
                        return;
                    }

                    // Ensure we have permission
                    if (!service.hasPermission()) {
                        service.requestPermission()
                            .then(function() {
                                return performSubscription();
                            })
                            .then(resolve)
                            .catch(reject);
                    } else {
                        performSubscription()
                            .then(resolve)
                            .catch(reject);
                    }

                    function performSubscription() {
                        return navigator.serviceWorker.ready
                            .then(function(registration) {
                                return registration.pushManager.subscribe({
                                    userVisibleOnly: true,
                                    applicationServerKey: urlBase64ToUint8Array(vapidPublicKey)
                                });
                            })
                            .then(function(subscription) {
                                console.log('Push subscription created:', subscription);
                                return service.registerSubscription(subscription);
                            });
                    }
                });
            };

            /**
             * Unsubscribe from push notifications
             */
            service.unsubscribe = function() {
                return navigator.serviceWorker.ready
                    .then(function(registration) {
                        return registration.pushManager.getSubscription();
                    })
                    .then(function(subscription) {
                        if (subscription) {
                            return subscription.unsubscribe()
                                .then(function() {
                                    console.log('Successfully unsubscribed from push notifications');
                                    return service.unregisterSubscription(subscription);
                                });
                        }
                    });
            };

            /**
             * Register subscription with backend
             */
            service.registerSubscription = function(subscription) {
                var subscriptionData = {
                    endpoint: subscription.endpoint,
                    keys: {
                        p256dh: arrayBufferToBase64(subscription.getKey('p256dh')),
                        auth: arrayBufferToBase64(subscription.getKey('auth'))
                    },
                    user_agent: navigator.userAgent,
                    user_id: getCurrentUserId() // Implement based on your auth system
                };

                return $http.post(API_BASE + '/push/subscribe', subscriptionData)
                    .then(function(response) {
                        console.log('Subscription registered with server');
                        return response.data;
                    })
                    .catch(function(error) {
                        console.error('Error registering subscription:', error);
                        throw error;
                    });
            };

            /**
             * Unregister subscription from backend
             */
            service.unregisterSubscription = function(subscription) {
                var endpoint = subscription.endpoint;

                return $http.post(API_BASE + '/push/unsubscribe', { endpoint: endpoint })
                    .then(function(response) {
                        console.log('Subscription unregistered from server');
                        return response.data;
                    })
                    .catch(function(error) {
                        console.error('Error unregistering subscription:', error);
                        throw error;
                    });
            };

            /**
             * Get current subscription status
             */
            service.getSubscriptionStatus = function() {
                if (!service.isSupported()) {
                    return $q.resolve({ supported: false, subscribed: false });
                }

                return navigator.serviceWorker.ready
                    .then(function(registration) {
                        return registration.pushManager.getSubscription();
                    })
                    .then(function(subscription) {
                        return {
                            supported: true,
                            subscribed: !!subscription,
                            permission: Notification.permission,
                            subscription: subscription
                        };
                    });
            };

            /**
             * Send test notification (admin function)
             */
            service.sendTestNotification = function() {
                return $http.post(API_BASE + '/push/test')
                    .then(function(response) {
                        return response.data;
                    });
            };

            /**
             * Get notification preferences
             */
            service.getPreferences = function() {
                // Get from localStorage or backend
                var prefs = localStorage.getItem('lcmtv_push_prefs');
                return prefs ? JSON.parse(prefs) : {
                    liveStreams: true,
                    newContent: true,
                    reminders: true,
                    updates: false
                };
            };

            /**
             * Update notification preferences
             */
            service.updatePreferences = function(preferences) {
                localStorage.setItem('lcmtv_push_prefs', JSON.stringify(preferences));

                // Could also send to backend for server-side filtering
                return $http.post(API_BASE + '/push/preferences', preferences)
                    .catch(function(error) {
                        console.warn('Could not update server preferences:', error);
                        // Still resolve since local storage worked
                    });
            };

            /**
             * Show local notification (for immediate feedback)
             */
            service.showLocalNotification = function(title, options) {
                if (!service.hasPermission()) {
                    console.warn('Cannot show notification: permission not granted');
                    return;
                }

                return navigator.serviceWorker.ready
                    .then(function(registration) {
                        return registration.showNotification(title, options);
                    });
            };

            // Utility functions

            /**
             * Convert VAPID key to Uint8Array
             */
            function urlBase64ToUint8Array(base64String) {
                const padding = '='.repeat((4 - base64String.length % 4) % 4);
                const base64 = (base64String + padding)
                    .replace(/-/g, '+')
                    .replace(/_/g, '/');

                const rawData = window.atob(base64);
                const outputArray = new Uint8Array(rawData.length);

                for (let i = 0; i < rawData.length; ++i) {
                    outputArray[i] = rawData.charCodeAt(i);
                }
                return outputArray;
            }

            /**
             * Convert ArrayBuffer to base64
             */
            function arrayBufferToBase64(buffer) {
                let binary = '';
                const bytes = new Uint8Array(buffer);
                for (let i = 0; i < bytes.byteLength; i++) {
                    binary += String.fromCharCode(bytes[i]);
                }
                return window.btoa(binary);
            }

            /**
             * Get current user ID (implement based on your auth system)
             */
            function getCurrentUserId() {
                // This should be implemented based on your authentication system
                // For now, return null or get from localStorage/sessionStorage
                try {
                    var user = JSON.parse(localStorage.getItem('churchtv_user'));
                    return user ? user.id : null;
                } catch (e) {
                    return null;
                }
            }

            // Initialize service
            service.initialize = function() {
                // Get VAPID key on service initialization
                if (service.isSupported()) {
                    service.getVapidPublicKey()
                        .catch(function(error) {
                            console.warn('Could not get VAPID key:', error);
                        });
                }
            };

            return service;
        }]);
})();