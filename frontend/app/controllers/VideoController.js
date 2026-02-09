/**
 * Video Controller
 * Handles individual video page functionality
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .controller('VideoController', ['$scope', '$routeParams', '$location', '$timeout', 'VideoService', 'UserService', 'AuthService', 'CommentService', 'ReactionService',
            function($scope, $routeParams, $location, $timeout, VideoService, UserService, AuthService, CommentService, ReactionService) {

            var vm = this;

            // Controller properties
            vm.video = null;
            vm.relatedVideos = [];
            vm.isLoading = true;
            vm.player = null;
            vm.isLiked = false;
            vm.likeCount = 0;
            vm.watchProgress = 0;
            vm.favoriteIds = {};

            // Social features properties
            vm.reactions = { total: 0, reactions: {}, top_reaction: null };
            vm.userReaction = null;
            vm.reactionTypes = [];
            vm.canReact = false;

            vm.comments = { comments: [], total: 0, page: 1, total_pages: 0 };
            vm.loadingComments = false;
            vm.canComment = false;
            vm.newComment = '';
            vm.postingComment = false;
            vm.showReply = null;
            vm.replyText = '';

            // Initialize controller
            init();

            function init() {
                var videoId = $routeParams.id;

                if (!videoId) {
                    $location.path('/');
                    return;
                }

                loadVideo(videoId);
                loadRelatedVideos(videoId);
                loadFavorites();
                initializeSocialFeatures(videoId);

                // Set page title
                $scope.$root.setPageTitle('Loading...');
            }

            /**
             * Load video details
             */
            function loadVideo(videoId) {
                vm.isLoading = true;

                VideoService.getVideo(videoId).then(function(video) {
                    vm.video = video;
                    vm.likeCount = video.like_count || 0;

                    // Set page title
                    $scope.$root.setPageTitle(video.title);

                    // Check if user liked this video
                    checkLikeStatus(videoId);

                    // Add to watch history
                    UserService.addToWatchHistory(videoId, video);

                    // Initialize YouTube player after a short delay
                    $timeout(function() {
                        initializeYouTubePlayer(video.youtube_id);
                    }, 500);

                    vm.isLoading = false;
                }).catch(function(error) {
                    console.error('Error loading video:', error);
                    $scope.$root.showError('Video not found or unavailable.');
                    $location.path('/');
                });
            }

            /**
             * Load related videos
             */
            function loadRelatedVideos(videoId) {
                VideoService.getRelatedVideos(videoId, 8).then(function(videos) {
                    vm.relatedVideos = videos;
                }).catch(function(error) {
                    console.error('Error loading related videos:', error);
                    vm.relatedVideos = [];
                });
            }

            function loadFavorites() {
                UserService.getFavorites().then(function(list) {
                    vm.favoriteIds = {};
                    (list || []).forEach(function(item) {
                        var id = (item && (item.id !== undefined || item.video_id !== undefined))
                            ? (item.id !== undefined ? item.id : item.video_id) : item;
                        vm.favoriteIds[id] = true;
                    });

                    // Update current video favorite status
                    if (vm.video) {
                        vm.isFavorited = !!vm.favoriteIds[vm.video.id];
                    }
                });
            }

            vm.isFavorite = function(videoId) {
                return !!vm.favoriteIds[videoId];
            };

            vm.toggleFavorite = function(video, $event) {
                if ($event) $event.stopPropagation();
                var id = video.id;
                var wasFavorite = !!vm.favoriteIds[id];

                UserService.toggleFavorite(id).then(function(success) {
                    if (success) {
                        vm.favoriteIds[id] = !wasFavorite;
                        if (!wasFavorite) {
                            $scope.$root.showToast('Added to favorites', 'success');
                        } else {
                            $scope.$root.showToast('Removed from favorites', 'info');
                        }
                    } else {
                        $scope.$root.showToast('Failed to update favorites', 'error');
                    }
                }).catch(function(error) {
                    console.error('Error toggling favorite:', error);
                    $scope.$root.showToast('Failed to update favorites', 'error');
                });
            };

            /**
             * Check if user liked this video
             */
            function checkLikeStatus(videoId) {
                VideoService.checkLikeStatus(videoId).then(function(response) {
                    vm.isLiked = response.liked || false;
                }).catch(function(error) {
                    console.error('Error checking like status:', error);
                    vm.isLiked = false;
                });
            }

            /**
             * Initialize YouTube Player
             */
            function initializeYouTubePlayer(videoId) {
                // Check if YouTube API is loaded
                if (typeof YT === 'undefined' || !YT.Player) {
                    console.warn('YouTube API not loaded yet, retrying...');
                    $timeout(function() {
                        initializeYouTubePlayer(videoId);
                    }, 1000);
                    return;
                }

                // Destroy existing player
                if (vm.player) {
                    vm.player.destroy();
                }

                // Create new player
                console.log('Creating YouTube player with origin:', window.location.origin);
                vm.player = new YT.Player('youtube-player', {
                    videoId: videoId,
                    playerVars: {
                        autoplay: $scope.$root.userPrefs.autoplay ? 1 : 0,
                        modestbranding: 1,
                        showinfo: 0,
                        controls: 1,
                        rel: 0,
                        iv_load_policy: 3, // Hide annotations
                        fs: 1, // Allow fullscreen
                        cc_load_policy: $scope.$root.userPrefs.showCaptions ? 1 : 0,
                        origin: window.location.origin // Fix origin mismatch for localhost
                    },
                    events: {
                        onReady: onPlayerReady,
                        onStateChange: onPlayerStateChange,
                        onError: onPlayerError
                    }
                });
            }

            /**
             * YouTube Player Events
             */
            function onPlayerReady(event) {
                console.log('YouTube player ready');

                // Set initial volume
                if ($scope.$root.userPrefs.volume) {
                    event.target.setVolume($scope.$root.userPrefs.volume);
                }

                // Set playback speed
                if ($scope.$root.userPrefs.playbackSpeed) {
                    event.target.setPlaybackRate($scope.$root.userPrefs.playbackSpeed);
                }
            }

            function onPlayerStateChange(event) {
                switch (event.data) {
                    case YT.PlayerState.PLAYING:
                        // Track video start/play
                        if (!vm.playbackStarted) {
                            vm.playbackStarted = true;
                            trackVideoEvent('play');
                        }
                        break;

                    case YT.PlayerState.PAUSED:
                        // Could track pause events if needed
                        break;

                    case YT.PlayerState.ENDED:
                        // Track video completion
                        trackVideoEvent('complete');
                        vm.watchProgress = 100;

                        // Auto-play next related video if enabled
                        if ($scope.$root.userPrefs.autoplay && vm.relatedVideos.length > 0) {
                            $timeout(function() {
                                vm.playVideo(vm.relatedVideos[0].id);
                            }, 3000);
                        }
                        break;

                    case YT.PlayerState.BUFFERING:
                        // Update progress periodically
                        startProgressTracking();
                        break;
                }
            }

            function onPlayerError(event) {
                var errorMessage = 'Video playback error';
                switch (event.data) {
                    case 2:
                        errorMessage = 'Invalid video ID';
                        break;
                    case 5:
                        errorMessage = 'HTML5 player error';
                        break;
                    case 100:
                        errorMessage = 'Video not found';
                        break;
                    case 101:
                    case 150:
                        errorMessage = 'Video embedding disabled';
                        break;
                }
                $scope.$root.showError(errorMessage);
            }

            /**
             * Track video progress
             */
            function startProgressTracking() {
                if (vm.progressInterval) {
                    $timeout.cancel(vm.progressInterval);
                }

                vm.progressInterval = $timeout(function trackProgress() {
                    if (vm.player && vm.player.getCurrentTime && vm.player.getDuration) {
                        var currentTime = vm.player.getCurrentTime();
                        var duration = vm.player.getDuration();

                        if (duration > 0) {
                            vm.watchProgress = (currentTime / duration) * 100;

                            // Track watch milestones
                            if (vm.watchProgress >= 25 && !vm.quarterTracked) {
                                vm.quarterTracked = true;
                                trackVideoEvent('25_percent');
                            }
                            if (vm.watchProgress >= 50 && !vm.halfTracked) {
                                vm.halfTracked = true;
                                trackVideoEvent('50_percent');
                            }
                            if (vm.watchProgress >= 75 && !vm.threeQuarterTracked) {
                                vm.threeQuarterTracked = true;
                                trackVideoEvent('75_percent');
                            }
                        }
                    }

                    // Continue tracking
                    vm.progressInterval = $timeout(trackProgress, 5000); // Update every 5 seconds
                }, 1000);
            }

            /**
             * Track video events
             */
            function trackVideoEvent(action) {
                if (vm.video) {
                    VideoService.trackView(vm.video.id).then(function() {
                        console.log('Tracked video event:', action);
                    }).catch(function(error) {
                        console.warn('Failed to track video event:', error);
                    });
                }
            }

            /**
             * Toggle like/dislike
             */
            vm.toggleLike = function() {
                if (!vm.video) return;

                VideoService.toggleLike(vm.video.id).then(function(response) {
                    vm.isLiked = response.liked || false;
                    vm.likeCount = response.like_count || vm.likeCount;
                }).catch(function(error) {
                    console.error('Error toggling like:', error);
                    $scope.$root.showError('Failed to update like status');
                });
            };

            /**
             * Add to favorites
             */
            vm.toggleFavorite = function() {
                if (!vm.video) return;

                // Check current favorite status before toggling
                var wasFavorite = vm.isFavorited;

                UserService.toggleFavorite(vm.video.id).then(function(success) {
                    if (success) {
                        // Update local status
                        vm.isFavorited = !wasFavorite;

                        if (vm.isFavorited) {
                            $scope.$root.showToast('Added to favorites', 'success');
                        } else {
                            $scope.$root.showToast('Removed from favorites', 'info');
                        }

                        // Reload favorites for related videos
                        loadFavorites();
                    } else {
                        $scope.$root.showToast('Failed to update favorites', 'error');
                    }
                }).catch(function(error) {
                    console.error('Error toggling favorite:', error);
                    $scope.$root.showToast('Failed to update favorites', 'error');
                });
            };

            /**
             * Add to watch later
             */
            vm.addToWatchLater = function() {
                if (!vm.video) return;

                var added = UserService.addToWatchLater(vm.video.id, vm.video);
                if (added) {
                    $scope.$root.showToast('Added to Watch Later', 'success');
                } else {
                    $scope.$root.showToast('Already in Watch Later', 'info');
                }
            };

            /**
             * Play related video
             */
            vm.playVideo = function(videoId) {
                $location.path('/video/' + videoId);
            };

            /**
             * Share video
             */
            vm.shareVideo = function() {
                if (!vm.video) return;

                var shareUrl = window.location.origin + '/#/video/' + vm.video.id;

                if (navigator.share) {
                    // Native share API
                    navigator.share({
                        title: vm.video.title,
                        text: 'Check out this video: ' + vm.video.title,
                        url: shareUrl
                    });
                } else {
                    // Fallback: copy to clipboard
                    window.ChurchTV.copyToClipboard(shareUrl);
                    $scope.$root.showToast('Video link copied to clipboard', 'success');
                }
            };

            /**
             * Format duration for display
             */
            vm.formatDuration = function(seconds) {
                if (!seconds || seconds <= 0) return '0:00';

                var hours = Math.floor(seconds / 3600);
                var minutes = Math.floor((seconds % 3600) / 60);
                var remainingSeconds = seconds % 60;

                if (hours > 0) {
                    return hours + ':' + padZero(minutes) + ':' + padZero(remainingSeconds);
                } else {
                    return minutes + ':' + padZero(remainingSeconds);
                }
            };

            /**
             * Format view count
             */
            vm.formatViews = function(count) {
                if (!count || count < 0) return '0 views';

                if (count >= 1000000) {
                    return (count / 1000000).toFixed(1) + 'M views';
                } else if (count >= 1000) {
                    return (count / 1000).toFixed(1) + 'K views';
                } else {
                    return count + ' views';
                }
            };

            /**
             * Format publish date
             */
            vm.formatDate = function(dateString) {
                if (!dateString) return '';

                var date = new Date(dateString);
                return date.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
            };

            /**
             * Helper function
             */
            function padZero(num) {
                return (num < 10 ? '0' : '') + num;
            }


            /**
             * Initialize social features
             */
            function initializeSocialFeatures(videoId) {
                // Check if user can interact
                vm.canReact = ReactionService.canReact(videoId);
                vm.canComment = !!AuthService.isAuthenticated();

                // Load reactions
                loadReactions(videoId);

                // Load reaction types
                ReactionService.getReactionTypes().then(function(data) {
                    vm.reactionTypes = data.types;
                });

                // Load comments
                loadComments(videoId, 1);
            }

            /**
             * Load reactions for the video
             */
            function loadReactions(videoId) {
                ReactionService.getReactionsByVideo(videoId).then(function(reactions) {
                    vm.reactions = reactions;
                });

                if (vm.canReact) {
                    ReactionService.getUserReaction(videoId).then(function(userReaction) {
                        vm.userReaction = userReaction.reaction;
                    });
                }
            }

            /**
             * Load comments for the video
             */
            function loadComments(videoId, page) {
                vm.loadingComments = true;
                CommentService.getCommentsByVideo(videoId, page, 10).then(function(comments) {
                    if (page === 1) {
                        vm.comments = comments;
                    } else {
                        // Append to existing comments
                        vm.comments.comments = vm.comments.comments.concat(comments.comments);
                        vm.comments.page = comments.page;
                        vm.comments.total_pages = comments.total_pages;
                    }
                    vm.loadingComments = false;
                }).catch(function(error) {
                    console.error('Error loading comments:', error);
                    vm.loadingComments = false;
                });
            }

            /**
             * Add a reaction
             */
            vm.addReaction = function(reactionType) {
                if (!vm.canReact) return;

                ReactionService.react(vm.video.id, reactionType).then(function(result) {
                    vm.reactions = result.stats;
                    vm.userReaction = {
                        type: result.reaction_type,
                        emoji: result.emoji
                    };

                    $scope.$root.showToast('Reaction added!', 'success');
                }).catch(function(error) {
                    $scope.$root.showToast('Failed to add reaction', 'error');
                });
            };

            /**
             * Remove user's reaction
             */
            vm.removeReaction = function() {
                if (!vm.canReact || !vm.userReaction) return;

                ReactionService.removeReaction(vm.video.id).then(function(result) {
                    vm.reactions = result.stats;
                    vm.userReaction = null;

                    $scope.$root.showToast('Reaction removed', 'info');
                }).catch(function(error) {
                    $scope.$root.showToast('Failed to remove reaction', 'error');
                });
            };

            /**
             * Post a new comment
             */
            vm.postComment = function() {
                if (!vm.canComment || !vm.newComment || vm.newComment.trim().length < 2) return;

                var validation = CommentService.validateComment(vm.newComment);
                if (!validation.valid) {
                    $scope.$root.showToast(validation.error, 'warning');
                    return;
                }

                vm.postingComment = true;

                CommentService.createComment(vm.video.id, vm.newComment).then(function(comment) {
                    // Add to comments list
                    vm.comments.comments.unshift(comment);
                    vm.comments.total++;
                    vm.newComment = '';

                    $scope.$root.showToast('Comment posted!', 'success');
                }).catch(function(error) {
                    $scope.$root.showToast('Failed to post comment', 'error');
                }).finally(function() {
                    vm.postingComment = false;
                });
            };

            /**
             * Toggle reply form
             */
            vm.toggleReply = function(index) {
                vm.showReply = vm.showReply === index ? null : index;
                vm.replyText = '';
            };

            /**
             * Cancel reply
             */
            vm.cancelReply = function() {
                vm.showReply = null;
                vm.replyText = '';
            };

            /**
             * Post a reply to a comment
             */
            vm.postReply = function(parentId) {
                if (!vm.canComment || !vm.replyText || vm.replyText.trim().length < 2) return;

                var validation = CommentService.validateComment(vm.replyText);
                if (!validation.valid) {
                    $scope.$root.showToast(validation.error, 'warning');
                    return;
                }

                CommentService.createComment(vm.video.id, vm.replyText, parentId).then(function(reply) {
                    // Find parent comment and add reply
                    var parentIndex = vm.comments.comments.findIndex(function(c) {
                        return c.id === parentId;
                    });

                    if (parentIndex !== -1) {
                        if (!vm.comments.comments[parentIndex].replies) {
                            vm.comments.comments[parentIndex].replies = [];
                        }
                        vm.comments.comments[parentIndex].replies.push(reply);
                    }

                    vm.cancelReply();
                    $scope.$root.showToast('Reply posted!', 'success');
                }).catch(function(error) {
                    $scope.$root.showToast('Failed to post reply', 'error');
                });
            };

            /**
             * Edit a comment
             */
            vm.editComment = function(comment) {
                var newContent = prompt('Edit your comment:', comment.content);
                if (newContent && newContent !== comment.content) {
                    var validation = CommentService.validateComment(newContent);
                    if (!validation.valid) {
                        $scope.$root.showToast(validation.error, 'warning');
                        return;
                    }

                    CommentService.updateComment(comment.id, newContent).then(function(updatedComment) {
                        comment.content = updatedComment.content;
                        $scope.$root.showToast('Comment updated!', 'success');
                    }).catch(function(error) {
                        $scope.$root.showToast('Failed to update comment', 'error');
                    });
                }
            };

            /**
             * Delete a comment
             */
            vm.deleteComment = function(commentId) {
                if (!confirm('Are you sure you want to delete this comment?')) return;

                CommentService.deleteComment(commentId, vm.video.id).then(function() {
                    // Remove from comments list
                    vm.comments.comments = vm.comments.comments.filter(function(c) {
                        return c.id !== commentId;
                    });
                    vm.comments.total--;

                    $scope.$root.showToast('Comment deleted', 'info');
                }).catch(function(error) {
                    $scope.$root.showToast('Failed to delete comment', 'error');
                });
            };

            /**
             * Check if user can edit a comment
             */
            vm.canEditComment = function(comment) {
                return vm.canComment && comment.user_id === getCurrentUserId();
            };

            /**
             * Check if user can delete a comment
             */
            vm.canDeleteComment = function(comment) {
                return vm.canComment && comment.user_id === getCurrentUserId();
            };

            /**
             * Load more comments
             */
            vm.loadMoreComments = function() {
                if (vm.comments.page < vm.comments.total_pages) {
                    loadComments(vm.video.id, vm.comments.page + 1);
                }
            };

            /**
             * Format comment date
             */
            vm.formatCommentDate = function(dateString) {
                return CommentService.formatCommentDate(dateString);
            };

            /**
             * Get current user ID
             */
            function getCurrentUserId() {
                try {
                    var user = JSON.parse(localStorage.getItem('churchtv_user'));
                    return user ? user.id : null;
                } catch (e) {
                    return null;
                }
            }

            // Cleanup on destroy
            $scope.$on('$destroy', function() {
                if (vm.player) {
                    vm.player.destroy();
                }
                if (vm.progressInterval) {
                    $timeout.cancel(vm.progressInterval);
                }
            });

            // Expose controller to scope
            $scope.vm = vm;
        }]);
})();