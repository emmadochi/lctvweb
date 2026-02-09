/**
 * Livestream Service
 * Handles livestream-related API calls and data management
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .factory('LivestreamService', ['$http', '$q', 'API_BASE', function($http, $q, API_BASE) {

            var service = {};

            // Cache for livestreams to reduce API calls
            var livestreamCache = {};
            var cacheExpiry = 2 * 60 * 1000; // 2 minutes (shorter for live content)

            /**
             * Unwrap backend response: { success, message, data } -> data
             */
            function unwrap(res) {
                var body = res && res.data;
                if (body && body.success === true && body.data !== undefined) {
                    return body.data;
                }
                return body;
            }

            /**
             * Get all active livestreams
             */
            service.getAllLivestreams = function() {
                var cacheKey = 'all_livestreams';
                var cached = livestreamCache[cacheKey];

                if (cached && (Date.now() - cached.timestamp) < cacheExpiry) {
                    return $q.resolve(cached.data);
                }

                return $http.get(API_BASE + '/livestreams')
                    .then(function(response) {
                        var data = unwrap(response);
                        var livestreams = Array.isArray(data) ? data : [];
                        livestreamCache[cacheKey] = { data: livestreams, timestamp: Date.now() };
                        return livestreams;
                    }).catch(function(error) {
                        console.error('Error fetching livestreams:', error);
                        return [];
                    });
            };

            /**
             * Get featured livestream (highest viewer count)
             */
            service.getFeaturedLivestream = function() {
                var cacheKey = 'featured_livestream';
                var cached = livestreamCache[cacheKey];

                if (cached && (Date.now() - cached.timestamp) < cacheExpiry) {
                    return $q.resolve(cached.data);
                }

                return $http.get(API_BASE + '/livestreams/featured')
                    .then(function(response) {
                        var data = unwrap(response);
                        livestreamCache[cacheKey] = { data: data, timestamp: Date.now() };
                        return data;
                    }).catch(function(error) {
                        console.warn('Error fetching featured livestream:', error);
                        return null;
                    });
            };

            /**
             * Get single livestream by ID
             */
            service.getLivestream = function(livestreamId) {
                var cacheKey = 'livestream_' + livestreamId;
                var cached = livestreamCache[cacheKey];

                if (cached && (Date.now() - cached.timestamp) < cacheExpiry) {
                    return $q.resolve(cached.data);
                }

                return $http.get(API_BASE + '/livestreams', {
                    params: { id: livestreamId }
                }).then(function(response) {
                    var data = unwrap(response);
                    livestreamCache[cacheKey] = { data: data, timestamp: Date.now() };
                    return data;
                }).catch(function(error) {
                    console.error('Error fetching livestream:', error);
                    throw error;
                });
            };

            /**
             * Get livestreams by category
             */
            service.getLivestreamsByCategory = function(categoryId) {
                var cacheKey = 'livestreams_category_' + categoryId;
                var cached = livestreamCache[cacheKey];

                if (cached && (Date.now() - cached.timestamp) < cacheExpiry) {
                    return $q.resolve(cached.data);
                }

                return $http.get(API_BASE + '/livestreams', {
                    params: { category: categoryId }
                }).then(function(response) {
                    var data = unwrap(response);
                    var livestreams = Array.isArray(data) ? data : [];
                    livestreamCache[cacheKey] = { data: livestreams, timestamp: Date.now() };
                    return livestreams;
                }).catch(function(error) {
                    console.error('Error fetching livestreams by category:', error);
                    return [];
                });
            };

            /**
             * Track livestream view (if needed for analytics)
             */
            service.trackLivestreamView = function(livestreamId, userId) {
                return $http.post(API_BASE + '/livestreams/' + livestreamId + '/view', {
                    user_id: userId,
                    timestamp: new Date().toISOString()
                }).then(function(response) {
                    return unwrap(response) || response.data;
                }).catch(function(error) {
                    console.warn('Error tracking livestream view:', error);
                    return null;
                });
            };

            /**
             * Clear cache (useful for development or when needing fresh data)
             */
            service.clearCache = function() {
                livestreamCache = {};
            };

            /**
             * Check if there are any active livestreams
             */
            service.hasActiveLivestreams = function() {
                return service.getAllLivestreams().then(function(livestreams) {
                    return livestreams && livestreams.length > 0;
                });
            };

            return service;
        }]);
})();