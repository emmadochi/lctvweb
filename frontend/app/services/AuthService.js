/**
 * Auth Service
 * Handles user authentication, token management, and session state
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .factory('AuthService', ['$http', '$q', 'API_BASE', function($http, $q, API_BASE) {

            var service = {};

            // Token storage key
            var TOKEN_KEY = 'churchtv_auth_token';
            var USER_KEY = 'churchtv_user';

            // Current user state
            var currentUser = null;
            var currentToken = null;

            // Auth state change callbacks
            var authStateCallbacks = [];

            /**
             * Check if token has valid format (basic validation)
             */
            function isValidTokenFormat(token) {
                // Basic JWT format check (should have 3 parts separated by dots)
                if (!token || typeof token !== 'string') return false;

                var parts = token.split('.');
                if (parts.length !== 3) return false;

                // Check if each part is base64-like
                try {
                    // Try to decode header and payload (not signature for security)
                    atob(parts[0]); // Header
                    JSON.parse(atob(parts[1])); // Payload
                    return true;
                } catch (e) {
                    return false;
                }
            }

            /**
             * Verify token validity with server (background check)
             */
            function verifyTokenWithServer() {
                if (!currentToken) return;

                // Make a lightweight request to verify token using profile endpoint
                // Use a custom request that bypasses the interceptor's automatic logout
                var config = {
                    headers: {
                        'Authorization': 'Bearer ' + currentToken,
                        'X-Auth-Verification': 'true' // Custom header to identify verification requests
                    },
                    // Don't let the interceptor handle this response
                    skipAuthInterceptor: true
                };

                $http.get(API_BASE + '/users/profile', config)
                    .then(function(response) {
                        // Token is valid, update user data if needed
                        console.log('Token verified with server');
                        var userData = response.data.data;
                        if (userData && JSON.stringify(userData) !== JSON.stringify(currentUser)) {
                            currentUser = userData;
                            localStorage.setItem(USER_KEY, JSON.stringify(currentUser));
                            // Notify listeners of auth state change
                            notifyAuthStateChanged(true);
                        }
                    })
                    .catch(function(error) {
                        if (error.status === 401) {
                            console.warn('Token verification failed, user not authenticated');
                            // Only logout if we're still using the same token
                            if (currentToken) {
                                service.logout();
                            }
                        }
                        // For other errors (network issues, server errors), keep current state
                        // This prevents auth flickering due to temporary connectivity issues
                    });
            }

            /**
             * Initialize service - load from localStorage
             */
            service.init = function() {
                try {
                    currentToken = localStorage.getItem(TOKEN_KEY);
                    var userData = localStorage.getItem(USER_KEY);

                    if (userData) {
                        currentUser = JSON.parse(userData);
                    }

                    // Validate token if we have one
                    if (currentToken && currentUser) {
                        // Check if token looks valid (basic check)
                        if (!isValidTokenFormat(currentToken)) {
                            console.warn('Invalid token format detected, clearing auth data');
                            service.logout();
                            return;
                        }

                        // Verify token with server in background after a short delay
                        // This prevents immediate logout on temporary network issues
                        setTimeout(verifyTokenWithServer, 1000);
                    }

                    console.log('AuthService initialized:', {
                        hasToken: !!currentToken,
                        hasUser: !!currentUser,
                        isAuthenticated: service.isAuthenticated()
                    });
                } catch (error) {
                    console.error('Error initializing auth service:', error);
                    service.logout(); // Clear invalid data
                }
            };

            /**
             * Register a new user
             */
            service.register = function(userData) {
                var registrationData = {
                    action: 'register',
                    email: userData.email,
                    password: userData.password,
                    first_name: userData.first_name,
                    last_name: userData.last_name
                };

                return $http.post(API_BASE + '/users', registrationData)
                    .then(function(response) {
                        var data = response.data;

                        if (data.success && data.data) {
                            currentUser = data.data.user;
                            currentToken = data.data.token;

                            // Store in localStorage
                            localStorage.setItem(TOKEN_KEY, currentToken);
                            localStorage.setItem(USER_KEY, JSON.stringify(currentUser));

                            // Set default auth header for future requests
                            $http.defaults.headers.common['Authorization'] = 'Bearer ' + currentToken;

                            // Notify auth state change
                            notifyAuthStateChanged(true);

                            return currentUser;
                        } else {
                            throw new Error(data.message || 'Registration failed');
                        }
                    })
                    .catch(function(error) {
                        var message = error.data && error.data.message ? error.data.message : 'Registration failed';
                        throw new Error(message);
                    });
            };

            /**
             * Login user
             */
            service.login = function(email, password) {
                var loginData = {
                    action: 'login',
                    email: email,
                    password: password
                };

                return $http.post(API_BASE + '/users', loginData)
                    .then(function(response) {
                        var data = response.data;

                        if (data.success && data.data) {
                            currentUser = data.data.user;
                            currentToken = data.data.token;

                            // Store in localStorage
                            localStorage.setItem(TOKEN_KEY, currentToken);
                            localStorage.setItem(USER_KEY, JSON.stringify(currentUser));

                            // Set default auth header for future requests
                            $http.defaults.headers.common['Authorization'] = 'Bearer ' + currentToken;

                            // Notify auth state change
                            notifyAuthStateChanged(true);

                            return currentUser;
                        } else {
                            throw new Error(data.message || 'Login failed');
                        }
                    })
                    .catch(function(error) {
                        var message = error.data && error.data.message ? error.data.message : 'Login failed';
                        throw new Error(message);
                    });
            };

            /**
             * Logout user
             */
            service.logout = function() {
                currentUser = null;
                currentToken = null;

                // Clear localStorage
                localStorage.removeItem(TOKEN_KEY);
                localStorage.removeItem(USER_KEY);

                // Clear auth header
                delete $http.defaults.headers.common['Authorization'];

                // Notify auth state change
                notifyAuthStateChanged(false);
            };

            /**
             * Check if user is authenticated
             */
            service.isAuthenticated = function() {
                return !!(currentUser && currentToken);
            };

            /**
             * Get current user
             */
            service.getCurrentUser = function() {
                return currentUser;
            };

            /**
             * Get current token
             */
            service.getToken = function() {
                return currentToken;
            };

            /**
             * Register auth state change callback
             */
            service.onAuthStateChanged = function(callback) {
                authStateCallbacks.push(callback);

                // Return unsubscribe function
                return function() {
                    var index = authStateCallbacks.indexOf(callback);
                    if (index > -1) {
                        authStateCallbacks.splice(index, 1);
                    }
                };
            };

            /**
             * Notify auth state change callbacks
             */
            function notifyAuthStateChanged(isAuthenticated) {
                console.log('AuthService: Notifying', authStateCallbacks.length, 'callbacks, isAuthenticated:', isAuthenticated);
                authStateCallbacks.forEach(function(callback) {
                    try {
                        callback(isAuthenticated);
                    } catch (error) {
                        console.error('Error in auth state callback:', error);
                    }
                });
            };

            /**
             * Refresh user profile from server
             */
            service.refreshProfile = function() {
                if (!service.isAuthenticated()) {
                    return $q.reject(new Error('Not authenticated'));
                }

                return $http.get(API_BASE + '/users/profile')
                    .then(function(response) {
                        var data = response.data;

                        if (data.success && data.data) {
                            currentUser = data.data;

                            // Update localStorage
                            localStorage.setItem(USER_KEY, JSON.stringify(currentUser));

                            return currentUser;
                        } else {
                            throw new Error(data.message || 'Failed to refresh profile');
                        }
                    })
                    .catch(function(error) {
                        // If token is invalid, logout
                        if (error.status === 401) {
                            service.logout();
                        }
                        throw error;
                    });
            };

            /**
             * Update user profile
             */
            service.updateProfile = function(profileData) {
                if (!service.isAuthenticated()) {
                    return $q.reject(new Error('Not authenticated'));
                }

                return $http.put(API_BASE + '/users/profile', profileData)
                    .then(function(response) {
                        var data = response.data;

                        if (data.success && data.data) {
                            currentUser = data.data;

                            // Update localStorage
                            localStorage.setItem(USER_KEY, JSON.stringify(currentUser));

                            return currentUser;
                        } else {
                            throw new Error(data.message || 'Failed to update profile');
                        }
                    })
                    .catch(function(error) {
                        var message = error.data && error.data.message ? error.data.message : 'Failed to update profile';
                        throw new Error(message);
                    });
            };

            /**
             * Update user password
             */
            service.updatePassword = function(passwordData) {
                if (!service.isAuthenticated()) {
                    return $q.reject(new Error('Not authenticated'));
                }

                return $http.put(API_BASE + '/users/password', passwordData)
                    .then(function(response) {
                        var data = response.data;

                        if (data.success) {
                            return data.message || 'Password updated successfully';
                        } else {
                            throw new Error(data.message || 'Failed to update password');
                        }
                    })
                    .catch(function(error) {
                        var message = error.data && error.data.message ? error.data.message : 'Failed to update password';
                        throw new Error(message);
                    });
            };

            // Initialize service
            service.init();

            return service;
        }]);
})();