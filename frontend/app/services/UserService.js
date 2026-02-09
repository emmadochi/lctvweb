/**
 * User Service
 * Handles user preferences, favorites, and watch history
 * Uses API when authenticated, localStorage as fallback
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .factory('UserService', ['$http', '$q', 'API_BASE', 'AuthService', function($http, $q, API_BASE, AuthService) {

            var service = {};

            // Local storage keys
            var FAVORITES_KEY = 'churchtv_favorites';
            var WATCH_HISTORY_KEY = 'churchtv_watch_history';
            var WATCH_LATER_KEY = 'churchtv_watch_later';
            var USER_PREFERENCES_KEY = 'churchtv_preferences';

            /**
             * Favorites Management
             */
            service.getFavorites = function() {
                if (AuthService.isAuthenticated()) {
                    // Use API
                    return $http.get(API_BASE + '/users/favorites')
                        .then(function(response) {
                            var data = response.data;
                            return data.success && data.data ? data.data : [];
                        })
                        .catch(function(error) {
                            console.error('Error loading favorites from API:', error);
                            // Fallback to localStorage
                            return service.getLocalFavorites();
                        });
                } else {
                    // Use localStorage
                    return $q.resolve(service.getLocalFavorites());
                }
            };

            service.getLocalFavorites = function() {
                try {
                    var favorites = localStorage.getItem(FAVORITES_KEY);
                    return favorites ? JSON.parse(favorites) : [];
                } catch (error) {
                    console.error('Error loading local favorites:', error);
                    return [];
                }
            };

            service.addToFavorites = function(videoId) {
                if (AuthService.isAuthenticated()) {
                    // Use API
                    return $http.post(API_BASE + '/users/favorites', { video_id: videoId })
                        .then(function(response) {
                            return response.data.success;
                        })
                        .catch(function(error) {
                            console.error('Error adding to favorites via API:', error);
                            // Fallback to localStorage
                            return service.addToLocalFavorites(videoId);
                        });
                } else {
                    // Use localStorage
                    return $q.resolve(service.addToLocalFavorites(videoId));
                }
            };

            service.addToLocalFavorites = function(videoId) {
                try {
                    var favorites = service.getLocalFavorites();
                    if (favorites.indexOf(videoId) === -1) {
                        favorites.push(videoId);
                        localStorage.setItem(FAVORITES_KEY, JSON.stringify(favorites));
                        return true;
                    }
                    return false; // Already in favorites
                } catch (error) {
                    console.error('Error adding to local favorites:', error);
                    return false;
                }
            };

            service.removeFromFavorites = function(videoId) {
                if (AuthService.isAuthenticated()) {
                    // Use API
                    return $http.delete(API_BASE + '/users/favorites', { data: { video_id: videoId } })
                        .then(function(response) {
                            return response.data.success;
                        })
                        .catch(function(error) {
                            console.error('Error removing from favorites via API:', error);
                            // Fallback to localStorage
                            return service.removeFromLocalFavorites(videoId);
                        });
                } else {
                    // Use localStorage
                    return $q.resolve(service.removeFromLocalFavorites(videoId));
                }
            };

            service.removeFromLocalFavorites = function(videoId) {
                try {
                    var favorites = service.getLocalFavorites();
                    var index = favorites.indexOf(videoId);
                    if (index > -1) {
                        favorites.splice(index, 1);
                        localStorage.setItem(FAVORITES_KEY, JSON.stringify(favorites));
                        return true;
                    }
                    return false; // Not in favorites
                } catch (error) {
                    console.error('Error removing from local favorites:', error);
                    return false;
                }
            };

            service.isFavorite = function(videoId) {
                // For checking favorite status, we need to get the data
                // This is a bit tricky with promises, so we'll use a synchronous approach
                // by checking localStorage for non-authenticated users
                if (!AuthService.isAuthenticated()) {
                    var favorites = service.getLocalFavorites();
                    return favorites.indexOf(videoId) > -1;
                }

                // For authenticated users, we can't easily check synchronously
                // This should be handled in controllers by checking the full favorites list
                return false;
            };

            service.toggleFavorite = function(videoId) {
                if (service.isFavorite(videoId)) {
                    return service.removeFromFavorites(videoId);
                } else {
                    return service.addToFavorites(videoId);
                }
            };

            /**
             * Watch History Management
             */
            service.getWatchHistory = function(limit) {
                if (AuthService.isAuthenticated()) {
                    // Use API
                    var params = limit ? { limit: limit } : {};
                    return $http.get(API_BASE + '/users/history', { params: params })
                        .then(function(response) {
                            var data = response.data;
                            return data.success && data.data ? data.data : [];
                        })
                        .catch(function(error) {
                            console.error('Error loading watch history from API:', error);
                            // Fallback to localStorage
                            return service.getLocalWatchHistory(limit);
                        });
                } else {
                    // Use localStorage
                    return $q.resolve(service.getLocalWatchHistory(limit));
                }
            };

            service.getLocalWatchHistory = function(limit) {
                try {
                    var history = localStorage.getItem(WATCH_HISTORY_KEY);
                    var parsedHistory = history ? JSON.parse(history) : [];

                    // Sort by most recent first
                    parsedHistory.sort(function(a, b) {
                        return new Date(b.watched_at) - new Date(a.watched_at);
                    });

                    // Limit results if specified
                    if (limit) {
                        parsedHistory = parsedHistory.slice(0, limit);
                    }

                    return parsedHistory;
                } catch (error) {
                    console.error('Error loading local watch history:', error);
                    return [];
                }
            };

            service.addToWatchHistory = function(videoId, videoData) {
                if (AuthService.isAuthenticated()) {
                    // Use API
                    $http.post(API_BASE + '/users/history', { video_id: videoId })
                        .catch(function(error) {
                            console.error('Error adding to watch history via API:', error);
                            // Fallback to localStorage
                            service.addToLocalWatchHistory(videoId, videoData);
                        });
                } else {
                    // Use localStorage
                    service.addToLocalWatchHistory(videoId, videoData);
                }
            };

            service.addToLocalWatchHistory = function(videoId, videoData) {
                try {
                    var history = service.getLocalWatchHistory();

                    // Remove if already exists (to move to top)
                    history = history.filter(function(item) {
                        return item.video_id !== videoId;
                    });

                    // Add to beginning
                    history.unshift({
                        video_id: videoId,
                        title: videoData.title,
                        thumbnail_url: videoData.thumbnail_url,
                        watched_at: new Date().toISOString(),
                        duration: videoData.duration
                    });

                    // Limit history size (keep last 100 items)
                    if (history.length > 100) {
                        history = history.slice(0, 100);
                    }

                    localStorage.setItem(WATCH_HISTORY_KEY, JSON.stringify(history));
                } catch (error) {
                    console.error('Error adding to local watch history:', error);
                }
            };

            service.clearWatchHistory = function() {
                if (AuthService.isAuthenticated()) {
                    // Use API
                    return $http.delete(API_BASE + '/users/history')
                        .then(function(response) {
                            return response.data.success;
                        })
                        .catch(function(error) {
                            console.error('Error clearing watch history via API:', error);
                            // Fallback to localStorage
                            return service.clearLocalWatchHistory();
                        });
                } else {
                    // Use localStorage
                    return $q.resolve(service.clearLocalWatchHistory());
                }
            };

            service.clearLocalWatchHistory = function() {
                try {
                    localStorage.removeItem(WATCH_HISTORY_KEY);
                    return true;
                } catch (error) {
                    console.error('Error clearing local watch history:', error);
                    return false;
                }
            };

            /**
             * Watch Later Queue
             */
            service.getWatchLater = function() {
                try {
                    var watchLater = localStorage.getItem(WATCH_LATER_KEY);
                    return watchLater ? JSON.parse(watchLater) : [];
                } catch (error) {
                    console.error('Error loading watch later:', error);
                    return [];
                }
            };

            service.addToWatchLater = function(videoId, videoData) {
                try {
                    var watchLater = service.getWatchLater();

                    // Check if already exists
                    var exists = watchLater.some(function(item) {
                        return item.video_id === videoId;
                    });

                    if (!exists) {
                        watchLater.push({
                            video_id: videoId,
                            title: videoData.title,
                            thumbnail_url: videoData.thumbnail_url,
                            added_at: new Date().toISOString(),
                            duration: videoData.duration
                        });

                        localStorage.setItem(WATCH_LATER_KEY, JSON.stringify(watchLater));
                        return true;
                    }
                    return false; // Already in watch later
                } catch (error) {
                    console.error('Error adding to watch later:', error);
                    return false;
                }
            };

            service.removeFromWatchLater = function(videoId) {
                try {
                    var watchLater = service.getWatchLater();
                    var filtered = watchLater.filter(function(item) {
                        return item.video_id !== videoId;
                    });

                    if (filtered.length !== watchLater.length) {
                        localStorage.setItem(WATCH_LATER_KEY, JSON.stringify(filtered));
                        return true;
                    }
                    return false; // Not found
                } catch (error) {
                    console.error('Error removing from watch later:', error);
                    return false;
                }
            };

            service.isInWatchLater = function(videoId) {
                var watchLater = service.getWatchLater();
                return watchLater.some(function(item) {
                    return item.video_id === videoId;
                });
            };

            /**
             * User Preferences
             */
            service.getPreferences = function() {
                try {
                    var prefs = localStorage.getItem(USER_PREFERENCES_KEY);
                    return prefs ? JSON.parse(prefs) : {
                        autoplay: false,
                        quality: 'auto',
                        volume: 80,
                        showCaptions: false,
                        playbackSpeed: 1.0
                    };
                } catch (error) {
                    console.error('Error loading preferences:', error);
                    return {};
                }
            };

            service.savePreferences = function(preferences) {
                try {
                    localStorage.setItem(USER_PREFERENCES_KEY, JSON.stringify(preferences));
                    return true;
                } catch (error) {
                    console.error('Error saving preferences:', error);
                    return false;
                }
            };

            /**
             * Clear all user data
             */
            service.clearAllData = function() {
                try {
                    localStorage.removeItem(FAVORITES_KEY);
                    localStorage.removeItem(WATCH_HISTORY_KEY);
                    localStorage.removeItem(WATCH_LATER_KEY);
                    localStorage.removeItem(USER_PREFERENCES_KEY);
                    return true;
                } catch (error) {
                    console.error('Error clearing user data:', error);
                    return false;
                }
            };

            /**
             * Export user data
             */
            service.exportData = function() {
                return {
                    favorites: service.getFavorites(),
                    watchHistory: service.getWatchHistory(),
                    watchLater: service.getWatchLater(),
                    preferences: service.getPreferences(),
                    exportDate: new Date().toISOString()
                };
            };

            return service;
        }]);
})();