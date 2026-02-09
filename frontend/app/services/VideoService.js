/**
 * Video Service
 * Handles video-related API calls and data management
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .factory('VideoService', ['$http', '$q', 'API_BASE', function($http, $q, API_BASE) {

            var service = {};

            // Cache for videos to reduce API calls
            var videoCache = {};
            var cacheExpiry = 5 * 60 * 1000; // 5 minutes

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
             * Normalize video so category is flat (category_id, category_name, category_slug)
             */
            function normalizeVideo(v) {
                if (!v) return v;
                if (v.category && typeof v.category === 'object') {
                    v.category_id = v.category.id;
                    v.category_name = v.category.name;
                    v.category_slug = v.category.slug;
                }
                return v;
            }

            function normalizeVideoList(list) {
                return Array.isArray(list) ? list.map(normalizeVideo) : list;
            }

            /**
             * Get featured videos for homepage
             */
            service.getFeaturedVideos = function(limit) {
                limit = limit || 24;

                return $http.get(API_BASE + '/videos', {
                    params: { limit: limit, featured: true }
                }).then(function(response) {
                    var data = unwrap(response);
                    return normalizeVideoList(Array.isArray(data) ? data : []);
                }).catch(function(error) {
                    console.error('Error fetching featured videos:', error);
                    return [];
                });
            };

            /**
             * Get videos by category
             */
            service.getVideosByCategory = function(categorySlug, page, limit) {
                page = page || 1;
                limit = limit || 24;

                return $http.get(API_BASE + '/categories/' + categorySlug + '/videos', {
                    params: { page: page, limit: limit }
                }).then(function(response) {
                    var data = unwrap(response);
                    if (data && data.videos) {
                        data.videos = normalizeVideoList(data.videos);
                        return data;
                    }
                    return { videos: [], total: 0, page: 1, total_pages: 0 };
                }).catch(function(error) {
                    console.error('Error fetching category videos:', error);
                    return { videos: [], total: 0, page: 1, total_pages: 0 };
                });
            };

            /**
             * Get video details by ID
             */
            service.getVideo = function(videoId) {
                // Check cache first
                var cacheKey = 'video_' + videoId;
                var cached = videoCache[cacheKey];

                if (cached && (Date.now() - cached.timestamp) < cacheExpiry) {
                    return $q.resolve(cached.data);
                }

                return $http.get(API_BASE + '/videos/' + videoId)
                    .then(function(response) {
                        var data = normalizeVideo(unwrap(response));
                        videoCache[cacheKey] = { data: data, timestamp: Date.now() };
                        return data;
                    }).catch(function(error) {
                        console.error('Error fetching video:', error);
                        throw error;
                    });
            };

            /**
             * Search videos
             */
            service.searchVideos = function(query, category, page, limit) {
                page = page || 1;
                limit = limit || 24;

                var params = {
                    q: query,
                    page: page,
                    limit: limit
                };

                if (category) {
                    params.category = category;
                }

                return $http.get(API_BASE + '/search', {
                    params: params
                }).then(function(response) {
                    var data = unwrap(response);
                    if (data && data.videos) {
                        data.videos = normalizeVideoList(data.videos);
                        return data;
                    }
                    return { videos: [], total: 0, page: 1, total_pages: 0 };
                }).catch(function(error) {
                    console.error('Error searching videos:', error);
                    return { videos: [], total: 0 };
                });
            };

            /**
             * Get trending videos
             */
            service.getTrendingVideos = function(limit) {
                limit = limit || 12;

                return $http.get(API_BASE + '/videos/trending', {
                    params: { limit: limit }
                }).then(function(response) {
                    var data = unwrap(response);
                    return normalizeVideoList(Array.isArray(data) ? data : []);
                }).catch(function(error) {
                    console.error('Error fetching trending videos:', error);
                    return [];
                });
            };

            /**
             * Track video view
             */
            service.trackView = function(videoId, userId) {
                return $http.post(API_BASE + '/videos/' + videoId + '/view', {
                    user_id: userId,
                    timestamp: new Date().toISOString()
                }).then(function(response) {
                    return unwrap(response) || response.data;
                }).catch(function(error) {
                    console.warn('Error tracking view:', error);
                    return null;
                });
            };

            /**
             * Get related videos (same category or fallback to featured)
             */
            service.getRelatedVideos = function(videoId, limit) {
                limit = limit || 8;
                return $http.get(API_BASE + '/videos/' + videoId + '/related', {
                    params: { limit: limit }
                }).then(function(response) {
                    var data = unwrap(response);
                    return normalizeVideoList(Array.isArray(data) ? data : []);
                }).catch(function(error) {
                    console.error('Error fetching related videos:', error);
                    return [];
                });
            };

            /**
             * Toggle like/dislike for video
             */
            service.toggleLike = function(videoId, userId) {
                return $http.post(API_BASE + '/videos/' + videoId + '/like', {
                    user_id: userId
                }).then(function(response) {
                    return unwrap(response) || response.data;
                }).catch(function(error) {
                    console.error('Error toggling like:', error);
                    throw error;
                });
            };

            /**
             * Check if user liked video
             */
            service.checkLikeStatus = function(videoId, userId) {
                return $http.get(API_BASE + '/videos/' + videoId + '/like', {
                    params: { user_id: userId }
                }).then(function(response) {
                    var data = unwrap(response);
                    return data && typeof data === 'object' ? data : { liked: false };
                }).catch(function(error) {
                    console.error('Error checking like status:', error);
                    return { liked: false };
                });
            };

            /**
             * Clear cache (useful for development)
             */
            service.clearCache = function() {
                videoCache = {};
            };

            return service;
        }]);
})();