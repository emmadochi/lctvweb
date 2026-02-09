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
            service.searchVideos = function(query, filters, page, limit) {
                page = page || 1;
                limit = limit || 24;

                var params = {
                    q: query || '',
                    offset: (page - 1) * limit,
                    limit: limit
                };

                // Add filters
                if (filters) {
                    if (filters.category_id) params.category_id = filters.category_id;
                    if (filters.speaker) params.speaker = filters.speaker;
                    if (filters.date_from) params.date_from = filters.date_from;
                    if (filters.date_to) params.date_to = filters.date_to;
                    if (filters.duration_min) params.duration_min = filters.duration_min;
                    if (filters.duration_max) params.duration_max = filters.duration_max;
                    if (filters.content_type) params.content_type = filters.content_type;
                    if (filters.language) params.language = filters.language;
                    if (filters.tags && filters.tags.length > 0) params.tags = filters.tags;
                }

                return $http.get(API_BASE + '/search', {
                    params: params
                }).then(function(response) {
                    var data = unwrap(response);
                    if (data && data.results) {
                        data.results = normalizeVideoList(data.results);
                        return {
                            videos: data.results,
                            total: data.total || 0,
                            page: page,
                            total_pages: data.pagination ? data.pagination.total_pages : 0,
                            pagination: data.pagination
                        };
                    }
                    return { videos: [], total: 0, page: 1, total_pages: 0 };
                }).catch(function(error) {
                    console.error('Error searching videos:', error);
                    return { videos: [], total: 0 };
                });
            };

            /**
             * Get search suggestions
             */
            service.getSearchSuggestions = function(query, limit) {
                return $http.get(API_BASE + '/search/suggestions', {
                    params: { q: query || '', limit: limit || 10 }
                }).then(function(response) {
                    return unwrap(response) || { suggestions: [] };
                }).catch(function(error) {
                    console.error('Error getting search suggestions:', error);
                    return { suggestions: [] };
                });
            };

            /**
             * Get AI-powered recommendations
             */
            service.getRecommendations = function(limit, excludeWatched) {
                return $http.get(API_BASE + '/recommendations', {
                    params: {
                        limit: limit || 10,
                        exclude_watched: excludeWatched !== false
                    }
                }).then(function(response) {
                    var data = unwrap(response);
                    if (data && data.recommendations) {
                        data.recommendations = normalizeVideoList(data.recommendations);
                    }
                    return data || { recommendations: [] };
                }).catch(function(error) {
                    console.error('Error getting recommendations:', error);
                    return { recommendations: [] };
                });
            };

            /**
             * Get smart playlists
             */
            service.getSmartPlaylists = function(limit) {
                return $http.get(API_BASE + '/playlists/smart', {
                    params: { limit: limit || 10 }
                }).then(function(response) {
                    return unwrap(response) || { playlists: [] };
                }).catch(function(error) {
                    console.error('Error getting smart playlists:', error);
                    return { playlists: [] };
                });
            };

            /**
             * Create smart playlist
             */
            service.createSmartPlaylist = function(playlistType, limit) {
                return $http.post(API_BASE + '/playlists/smart', {
                    playlist_type: playlistType,
                    limit: limit || 20
                }).then(function(response) {
                    var data = unwrap(response);
                    if (data && data.playlist && data.playlist.videos) {
                        data.playlist.videos = normalizeVideoList(data.playlist.videos);
                    }
                    return data || { playlist: null };
                }).catch(function(error) {
                    console.error('Error creating smart playlist:', error);
                    return { playlist: null };
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