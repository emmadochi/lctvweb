/**
 * Comment Service
 * Handles comment-related API calls and data management
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .factory('CommentService', ['$http', '$q', 'API_BASE', function($http, $q, API_BASE) {

            var service = {};

            // Cache for comments to reduce API calls
            var commentCache = {};
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
             * Get comments for a video
             */
            service.getCommentsByVideo = function(videoId, page, limit) {
                page = page || 1;
                limit = limit || 20;

                var cacheKey = 'comments_video_' + videoId + '_page_' + page + '_limit_' + limit;
                var cached = commentCache[cacheKey];

                if (cached && (Date.now() - cached.timestamp) < cacheExpiry) {
                    return $q.resolve(cached.data);
                }

                return $http.get(API_BASE + '/comments/video/' + videoId, {
                    params: { page: page, limit: limit }
                }).then(function(response) {
                    var data = unwrap(response);
                    commentCache[cacheKey] = { data: data, timestamp: Date.now() };
                    return data;
                }).catch(function(error) {
                    console.error('Error loading comments:', error);
                    return {
                        comments: [],
                        total: 0,
                        page: 1,
                        total_pages: 0
                    };
                });
            };

            /**
             * Get single comment with replies
             */
            service.getComment = function(commentId) {
                var cacheKey = 'comment_' + commentId;
                var cached = commentCache[cacheKey];

                if (cached && (Date.now() - cached.timestamp) < cacheExpiry) {
                    return $q.resolve(cached.data);
                }

                return $http.get(API_BASE + '/comments/' + commentId)
                    .then(function(response) {
                        var data = unwrap(response);
                        commentCache[cacheKey] = { data: data, timestamp: Date.now() };
                        return data;
                    }).catch(function(error) {
                        console.error('Error loading comment:', error);
                        throw error;
                    });
            };

            /**
             * Create a new comment
             */
            service.createComment = function(videoId, content, parentId) {
                var commentData = {
                    video_id: videoId,
                    content: content
                };

                if (parentId) {
                    commentData.parent_id = parentId;
                }

                return $http.post(API_BASE + '/comments/video/' + videoId, commentData)
                    .then(function(response) {
                        var data = unwrap(response);
                        // Clear cache for this video's comments
                        service.clearVideoCache(videoId);
                        return data;
                    }).catch(function(error) {
                        console.error('Error creating comment:', error);
                        throw error;
                    });
            };

            /**
             * Update a comment
             */
            service.updateComment = function(commentId, content) {
                return $http.put(API_BASE + '/comments/' + commentId, { content: content })
                    .then(function(response) {
                        var data = unwrap(response);
                        // Clear related caches
                        service.clearCommentCache(commentId);
                        return data;
                    }).catch(function(error) {
                        console.error('Error updating comment:', error);
                        throw error;
                    });
            };

            /**
             * Delete a comment
             */
            service.deleteComment = function(commentId, videoId) {
                return $http.delete(API_BASE + '/comments/' + commentId)
                    .then(function(response) {
                        // Clear related caches
                        service.clearCommentCache(commentId);
                        if (videoId) {
                            service.clearVideoCache(videoId);
                        }
                        return unwrap(response);
                    }).catch(function(error) {
                        console.error('Error deleting comment:', error);
                        throw error;
                    });
            };

            /**
             * Get comments for moderation (admin only)
             */
            service.getCommentsForModeration = function(page, limit) {
                page = page || 1;
                limit = limit || 50;

                return $http.get(API_BASE + '/comments/moderation', {
                    params: { page: page, limit: limit }
                }).then(function(response) {
                    return unwrap(response);
                }).catch(function(error) {
                    console.error('Error loading comments for moderation:', error);
                    throw error;
                });
            };

            /**
             * Approve a comment (admin only)
             */
            service.approveComment = function(commentId) {
                return $http.post(API_BASE + '/comments/' + commentId + '/approve')
                    .then(function(response) {
                        service.clearCommentCache(commentId);
                        return unwrap(response);
                    }).catch(function(error) {
                        console.error('Error approving comment:', error);
                        throw error;
                    });
            };

            /**
             * Reject a comment (admin only)
             */
            service.rejectComment = function(commentId) {
                return $http.post(API_BASE + '/comments/' + commentId + '/reject')
                    .then(function(response) {
                        service.clearCommentCache(commentId);
                        return unwrap(response);
                    }).catch(function(error) {
                        console.error('Error rejecting comment:', error);
                        throw error;
                    });
            };

            /**
             * Format comment date for display
             */
            service.formatCommentDate = function(dateString) {
                if (!dateString) return '';

                var date = new Date(dateString);
                var now = new Date();
                var diffMs = now - date;
                var diffMins = Math.floor(diffMs / (1000 * 60));
                var diffHours = Math.floor(diffMs / (1000 * 60 * 60));
                var diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

                if (diffMins < 1) return 'Just now';
                if (diffMins < 60) return diffMins + 'm ago';
                if (diffHours < 24) return diffHours + 'h ago';
                if (diffDays < 7) return diffDays + 'd ago';

                return date.toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    year: date.getFullYear() !== now.getFullYear() ? 'numeric' : undefined
                });
            };

            /**
             * Validate comment content
             */
            service.validateComment = function(content) {
                if (!content || typeof content !== 'string') {
                    return { valid: false, error: 'Comment content is required' };
                }

                var trimmed = content.trim();

                if (trimmed.length < 2) {
                    return { valid: false, error: 'Comment must be at least 2 characters long' };
                }

                if (trimmed.length > 1000) {
                    return { valid: false, error: 'Comment cannot exceed 1000 characters' };
                }

                return { valid: true };
            };

            /**
             * Clear cache for a specific video
             */
            service.clearVideoCache = function(videoId) {
                var keysToDelete = [];
                Object.keys(commentCache).forEach(function(key) {
                    if (key.indexOf('comments_video_' + videoId) === 0) {
                        keysToDelete.push(key);
                    }
                });
                keysToDelete.forEach(function(key) {
                    delete commentCache[key];
                });
            };

            /**
             * Clear cache for a specific comment
             */
            service.clearCommentCache = function(commentId) {
                var keysToDelete = [];
                Object.keys(commentCache).forEach(function(key) {
                    if (key.indexOf('comment_' + commentId) === 0) {
                        keysToDelete.push(key);
                    }
                });
                keysToDelete.forEach(function(key) {
                    delete commentCache[key];
                });
            };

            /**
             * Clear all comment cache
             */
            service.clearCache = function() {
                commentCache = {};
            };

            return service;
        }]);
})();