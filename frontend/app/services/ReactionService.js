/**
 * Reaction Service
 * Handles reaction-related API calls and data management
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .factory('ReactionService', ['$http', '$q', 'API_BASE', function($http, $q, API_BASE) {

            var service = {};

            // Cache for reactions to reduce API calls
            var reactionCache = {};
            var cacheExpiry = 2 * 60 * 1000; // 2 minutes (shorter for interactive features)

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
             * Get reactions for a video
             */
            service.getReactionsByVideo = function(videoId) {
                var cacheKey = 'reactions_video_' + videoId;
                var cached = reactionCache[cacheKey];

                if (cached && (Date.now() - cached.timestamp) < cacheExpiry) {
                    return $q.resolve(cached.data);
                }

                return $http.get(API_BASE + '/reactions/video/' + videoId)
                    .then(function(response) {
                        var data = unwrap(response);
                        reactionCache[cacheKey] = { data: data, timestamp: Date.now() };
                        return data;
                    }).catch(function(error) {
                        console.error('Error loading reactions:', error);
                        return {
                            total: 0,
                            reactions: {},
                            top_reaction: null
                        };
                    });
            };

            /**
             * Get user's reaction for a video
             */
            service.getUserReaction = function(videoId) {
                return $http.get(API_BASE + '/reactions/video/' + videoId + '/user')
                    .then(function(response) {
                        return unwrap(response);
                    }).catch(function(error) {
                        console.error('Error loading user reaction:', error);
                        return { reaction: null, has_reacted: false };
                    });
            };

            /**
             * Add or update reaction
             */
            service.react = function(videoId, reactionType) {
                return $http.post(API_BASE + '/reactions/video/' + videoId, {
                    reaction_type: reactionType
                }).then(function(response) {
                    var data = unwrap(response);
                    // Clear cache for this video
                    service.clearVideoCache(videoId);
                    return data;
                }).catch(function(error) {
                    console.error('Error adding reaction:', error);
                    throw error;
                });
            };

            /**
             * Remove reaction
             */
            service.removeReaction = function(videoId) {
                return $http.delete(API_BASE + '/reactions/video/' + videoId)
                    .then(function(response) {
                        var data = unwrap(response);
                        // Clear cache for this video
                        service.clearVideoCache(videoId);
                        return data;
                    }).catch(function(error) {
                        console.error('Error removing reaction:', error);
                        throw error;
                    });
            };

            /**
             * Get reaction stats for multiple videos
             */
            service.getBulkStats = function(videoIds) {
                return $http.post(API_BASE + '/reactions/bulk', {
                    video_ids: videoIds
                }).then(function(response) {
                    return unwrap(response);
                }).catch(function(error) {
                    console.error('Error loading bulk reaction stats:', error);
                    // Return empty stats for all requested videos
                    var emptyStats = {};
                    videoIds.forEach(function(id) {
                        emptyStats[id] = {
                            total: 0,
                            reactions: {},
                            top_reaction: null
                        };
                    });
                    return emptyStats;
                });
            };

            /**
             * Get available reaction types
             */
            service.getReactionTypes = function() {
                var cacheKey = 'reaction_types';
                var cached = reactionCache[cacheKey];

                if (cached && (Date.now() - cached.timestamp) < (24 * 60 * 60 * 1000)) { // 24 hours
                    return $q.resolve(cached.data);
                }

                return $http.get(API_BASE + '/reactions/types')
                    .then(function(response) {
                        var data = unwrap(response);
                        reactionCache[cacheKey] = { data: data, timestamp: Date.now() };
                        return data;
                    }).catch(function(error) {
                        console.error('Error loading reaction types:', error);
                        // Return default reaction types
                        return {
                            types: [
                                { type: 'like', emoji: '👍', name: 'Like' },
                                { type: 'love', emoji: '❤️', name: 'Love' },
                                { type: 'pray', emoji: '🙏', name: 'Pray' },
                                { type: 'amen', emoji: '✨', name: 'Amen' },
                                { type: 'praise', emoji: '🙌', name: 'Praise' },
                                { type: 'thankful', emoji: '🙏', name: 'Thankful' },
                                { type: 'insightful', emoji: '💡', name: 'Insightful' }
                            ],
                            total_types: 7
                        };
                    });
            };

            /**
             * Get reaction leaderboard
             */
            service.getLeaderboard = function(limit) {
                limit = limit || 10;

                return $http.get(API_BASE + '/reactions/leaderboard', {
                    params: { limit: limit }
                }).then(function(response) {
                    return unwrap(response);
                }).catch(function(error) {
                    console.error('Error loading reaction leaderboard:', error);
                    return { leaderboard: [], limit: limit };
                });
            };

            /**
             * Format reaction count for display
             */
            service.formatReactionCount = function(count) {
                if (!count || count < 0) return '0';

                if (count >= 1000000) {
                    return (count / 1000000).toFixed(1) + 'M';
                } else if (count >= 1000) {
                    return (count / 1000).toFixed(1) + 'K';
                } else {
                    return count.toString();
                }
            };

            /**
             * Get dominant reaction emoji for display
             */
            service.getDominantReaction = function(reactions) {
                if (!reactions || Object.keys(reactions).length === 0) {
                    return null;
                }

                var dominant = null;
                var maxCount = 0;

                Object.values(reactions).forEach(function(reaction) {
                    if (reaction.count > maxCount) {
                        maxCount = reaction.count;
                        dominant = reaction;
                    }
                });

                return dominant;
            };

            /**
             * Check if user can react (rate limiting, etc.)
             */
            service.canReact = function(videoId) {
                // Implement rate limiting logic if needed
                // For now, just check if user is authenticated
                try {
                    var user = JSON.parse(localStorage.getItem('churchtv_user'));
                    return !!user;
                } catch (e) {
                    return false;
                }
            };

            /**
             * Clear cache for a specific video
             */
            service.clearVideoCache = function(videoId) {
                var keysToDelete = [];
                Object.keys(reactionCache).forEach(function(key) {
                    if (key.indexOf('reactions_video_' + videoId) === 0) {
                        keysToDelete.push(key);
                    }
                });
                keysToDelete.forEach(function(key) {
                    delete reactionCache[key];
                });
            };

            /**
             * Clear all reaction cache
             */
            service.clearCache = function() {
                reactionCache = {};
            };

            /**
             * Get reaction emoji by type
             */
            service.getReactionEmoji = function(type) {
                var emojiMap = {
                    'like': '👍',
                    'love': '❤️',
                    'pray': '🙏',
                    'amen': '✨',
                    'praise': '🙌',
                    'thankful': '🙏',
                    'insightful': '💡'
                };

                return emojiMap[type] || '👍';
            };

            /**
             * Get reaction name by type
             */
            service.getReactionName = function(type) {
                var nameMap = {
                    'like': 'Like',
                    'love': 'Love',
                    'pray': 'Pray',
                    'amen': 'Amen',
                    'praise': 'Praise',
                    'thankful': 'Thankful',
                    'insightful': 'Insightful'
                };

                return nameMap[type] || 'Like';
            };

            return service;
        }]);
})();