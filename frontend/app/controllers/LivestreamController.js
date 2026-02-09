/**
 * Livestream Controller
 * Handles individual livestream page functionality
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .controller('LivestreamController', ['$scope', '$routeParams', '$location', '$timeout', 'LivestreamService', 'UserService',
            function($scope, $routeParams, $location, $timeout, LivestreamService, UserService) {

            var vm = this;

            // Controller properties
            vm.livestream = null;
            vm.relatedLivestreams = [];
            vm.isLoading = true;
            vm.player = null;
            vm.playerReady = false;
            vm.watchProgress = 0;
            vm.favoriteIds = {};
            vm.viewerCount = 0;
            vm.isLive = false;

            // Initialize controller
            init();

            function init() {
                var livestreamId = $routeParams.id;

                if (!livestreamId) {
                    $location.path('/');
                    return;
                }

                loadLivestream(livestreamId);
                loadRelatedLivestreams();
                loadFavorites();

                // Set page title
                $scope.$root.setPageTitle('Loading Livestream...');

                // Update viewer count periodically
                startViewerCountUpdates();
            }

            /**
             * Load livestream details
             */
            function loadLivestream(livestreamId) {
                vm.isLoading = true;

                LivestreamService.getLivestream(livestreamId).then(function(livestream) {
                    vm.livestream = livestream;
                    vm.isLive = livestream.is_live === 1 || livestream.is_live === true;
                    vm.viewerCount = livestream.viewer_count || 0;

                    vm.isLoading = false;

                    // Set page title
                    $scope.$root.setPageTitle(vm.livestream.title + ' - Live');

                    // Initialize YouTube player for livestream
                    initializePlayer();

                    // Track view
                    trackView();

                }).catch(function(error) {
                    console.error('Error loading livestream:', error);
                    vm.isLoading = false;
                    $scope.$root.showToast('Failed to load livestream', 'error');
                    $location.path('/');
                });
            }

            /**
             * Initialize YouTube player for livestream
             */
            function initializePlayer() {
                if (!vm.livestream || !vm.livestream.youtube_id) {
                    console.warn('No YouTube ID available for livestream');
                    return;
                }

                // Wait for YouTube API to be ready
                if (typeof YT !== 'undefined' && YT.Player) {
                    createPlayer();
                } else {
                    console.log('YouTube API not ready, waiting...');
                    // Wait for YouTube API with timeout
                    window.onYouTubeIframeAPIReady = function() {
                        console.log('YouTube API ready, creating player');
                        createPlayer();
                    };

                    // Timeout after 10 seconds
                    $timeout(function() {
                        if (!vm.player) {
                            console.error('YouTube API failed to load within timeout');
                            $scope.$root.showToast('Failed to load YouTube player. Please refresh the page.', 'error');
                        }
                    }, 10000);
                }
            }

            function createPlayer() {
                console.log('Creating YouTube player for livestream:', vm.livestream.youtube_id);

                if (vm.player) {
                    console.log('Destroying existing player');
                    vm.player.destroy();
                }

                // Ensure the player container exists
                var playerContainer = document.getElementById('livestream-player');
                if (!playerContainer) {
                    console.error('Player container not found');
                    $scope.$root.showToast('Player container not found', 'error');
                    return;
                }

                vm.player = new YT.Player('livestream-player', {
                    videoId: vm.livestream.youtube_id,
                    playerVars: {
                        autoplay: 0, // Disable autoplay due to browser restrictions
                        controls: 1,
                        rel: 0,
                        showinfo: 0,
                        modestbranding: 1,
                        iv_load_policy: 3,
                        playsinline: 1,
                        // Live stream specific settings
                        disablekb: 0,
                        enablejsapi: 1,
                        origin: window.location.origin,
                        widget_referrer: window.location.href
                    },
                    events: {
                        onReady: onPlayerReady,
                        onStateChange: onPlayerStateChange,
                        onError: onPlayerError
                    }
                });
            }

            function onPlayerReady(event) {
                console.log('Livestream player ready');
                // Player is ready, could add additional setup here

                // Hide loading overlay
                vm.playerReady = true;
                $scope.$apply();
            }

            /**
             * Start playback when user clicks play button
             */
            vm.startPlayback = function() {
                if (vm.player && vm.playerReady) {
                    console.log('Starting livestream playback');
                    vm.player.playVideo();
                }
            }

            function onPlayerStateChange(event) {
                // Handle player state changes if needed
                console.log('Player state changed:', event.data);
            }

            function onPlayerError(event) {
                console.error('YouTube player error:', event.data);

                var errorMessage = 'Error loading livestream player';
                switch(event.data) {
                    case 2:
                        errorMessage = 'Invalid video ID';
                        break;
                    case 5:
                        errorMessage = 'HTML5 player error';
                        break;
                    case 100:
                        errorMessage = 'Video not found or private';
                        break;
                    case 101:
                    case 150:
                        errorMessage = 'Video embedding disabled';
                        break;
                }

                $scope.$root.showToast(errorMessage, 'error');
            }

            /**
             * Load related livestreams (other active livestreams)
             */
            function loadRelatedLivestreams() {
                LivestreamService.getAllLivestreams().then(function(livestreams) {
                    // Filter out current livestream and limit to 6
                    vm.relatedLivestreams = livestreams
                        .filter(function(l) {
                            return l.id !== vm.livestream.id;
                        })
                        .slice(0, 6);
                }).catch(function(error) {
                    console.error('Error loading related livestreams:', error);
                    vm.relatedLivestreams = [];
                });
            }

            /**
             * Load user favorites for icon state
             */
            function loadFavorites() {
                UserService.getFavorites().then(function(list) {
                    vm.favoriteIds = {};
                    (list || []).forEach(function(item) {
                        var id = (item && (item.id !== undefined || item.video_id !== undefined))
                            ? (item.id !== undefined ? item.id : item.video_id) : item;
                        vm.favoriteIds[id] = true;
                    });
                });
            }

            /**
             * Track livestream view
             */
            function trackView() {
                if (vm.livestream && vm.livestream.id) {
                    LivestreamService.trackLivestreamView(vm.livestream.id, null); // Pass user ID if available
                }
            }

            /**
             * Update viewer count periodically
             */
            function startViewerCountUpdates() {
                // Update viewer count every 30 seconds
                setInterval(function() {
                    if (vm.livestream && vm.livestream.id) {
                        LivestreamService.getLivestream(vm.livestream.id).then(function(updated) {
                            if (updated && updated.viewer_count !== undefined) {
                                vm.viewerCount = updated.viewer_count;
                                $scope.$apply();
                            }
                        }).catch(function(error) {
                            console.warn('Error updating viewer count:', error);
                        });
                    }
                }, 30000);
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
             * Share livestream functionality
             */
            vm.shareLivestream = function() {
                if (navigator.share && vm.livestream) {
                    navigator.share({
                        title: vm.livestream.title,
                        text: 'Watch this live stream: ' + vm.livestream.title,
                        url: window.location.href
                    }).catch(function(error) {
                        console.log('Error sharing:', error);
                        fallbackShare();
                    });
                } else {
                    fallbackShare();
                }
            };

            function fallbackShare() {
                // Fallback to copying URL to clipboard
                var url = window.location.href;
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(url).then(function() {
                        $scope.$root.showToast('Link copied to clipboard!', 'success');
                    }).catch(function(error) {
                        console.error('Error copying to clipboard:', error);
                        $scope.$root.showToast('Unable to copy link', 'error');
                    });
                } else {
                    // Fallback for older browsers
                    var textArea = document.createElement('textarea');
                    textArea.value = url;
                    document.body.appendChild(textArea);
                    textArea.select();
                    try {
                        document.execCommand('copy');
                        $scope.$root.showToast('Link copied to clipboard!', 'success');
                    } catch (error) {
                        console.error('Error copying to clipboard:', error);
                        $scope.$root.showToast('Unable to copy link', 'error');
                    }
                    document.body.removeChild(textArea);
                }
            }

            /**
             * Navigate to another livestream
             */
            vm.playLivestream = function(livestreamId) {
                $location.path('/livestream/' + livestreamId);
            };

            /**
             * Format viewer count for display
             */
            vm.formatViewerCount = function(count) {
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
             * Format duration (for related videos, not livestreams)
             */
            vm.formatDuration = function(seconds) {
                if (!seconds || seconds <= 0) return 'LIVE';

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
             * Format date for display
             */
            vm.formatDate = function(dateString) {
                if (!dateString) return '';

                var date = new Date(dateString);
                var now = new Date();
                var diffTime = Math.abs(now - date);
                var diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

                if (diffDays === 1) {
                    return 'Today';
                } else if (diffDays === 2) {
                    return 'Yesterday';
                } else if (diffDays <= 7) {
                    return diffDays + ' days ago';
                } else if (diffDays <= 30) {
                    var weeks = Math.floor(diffDays / 7);
                    return weeks + ' week' + (weeks > 1 ? 's' : '') + ' ago';
                } else {
                    return date.toLocaleDateString();
                }
            };

            /**
             * Helper function to pad numbers with zero
             */
            function padZero(num) {
                return (num < 10 ? '0' : '') + num;
            }

            // Cleanup on destroy
            $scope.$on('$destroy', function() {
                if (vm.player) {
                    vm.player.destroy();
                    vm.player = null;
                }
            });

            // Expose controller to scope for template access
            $scope.vm = vm;
        }]);
})();