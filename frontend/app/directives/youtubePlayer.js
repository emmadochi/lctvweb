/**
 * YouTube Player Directive
 * AngularJS directive for YouTube iframe player integration
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .directive('youtubePlayer', ['$timeout', function($timeout) {
            return {
                restrict: 'E',
                scope: {
                    videoId: '=',
                    playerVars: '=',
                    onReady: '&',
                    onStateChange: '&',
                    onError: '&',
                    autoplay: '=',
                    width: '@',
                    height: '@'
                },
                template: '<div class="youtube-player-container"></div>',
                link: function(scope, element, attrs) {
                    var player = null;
                    var container = element.find('.youtube-player-container')[0];

                    // Default player variables
                    var defaultPlayerVars = {
                        autoplay: scope.autoplay ? 1 : 0,
                        modestbranding: 1,
                        showinfo: 0,
                        controls: 1,
                        rel: 0,
                        iv_load_policy: 3, // Hide annotations
                        fs: 1, // Allow fullscreen
                        cc_load_policy: 0 // Hide captions by default
                    };

                    // Merge with provided player vars
                    var playerVars = angular.extend({}, defaultPlayerVars, scope.playerVars || {});

                    // Initialize player when YouTube API is ready
                    function initializePlayer() {
                        if (typeof YT === 'undefined' || !YT.Player) {
                            // Retry after delay if API not ready
                            $timeout(initializePlayer, 500);
                            return;
                        }

                        // Destroy existing player
                        if (player) {
                            player.destroy();
                        }

                        // Create new player
                        player = new YT.Player(container, {
                            videoId: scope.videoId,
                            width: scope.width || '100%',
                            height: scope.height || '100%',
                            playerVars: playerVars,
                            events: {
                                onReady: function(event) {
                                    console.log('YouTube player ready for video:', scope.videoId);
                                    if (scope.onReady) {
                                        scope.$apply(function() {
                                            scope.onReady({event: event});
                                        });
                                    }
                                },
                                onStateChange: function(event) {
                                    if (scope.onStateChange) {
                                        scope.$apply(function() {
                                            scope.onStateChange({event: event});
                                        });
                                    }
                                },
                                onError: function(event) {
                                    console.error('YouTube player error:', event.data);
                                    if (scope.onError) {
                                        scope.$apply(function() {
                                            scope.onError({event: event});
                                        });
                                    }
                                }
                            }
                        });
                    }

                    // Watch for video ID changes
                    scope.$watch('videoId', function(newVideoId, oldVideoId) {
                        if (newVideoId && newVideoId !== oldVideoId) {
                            if (player && player.loadVideoById) {
                                player.loadVideoById(newVideoId);
                            } else {
                                initializePlayer();
                            }
                        }
                    });

                    // Watch for autoplay changes
                    scope.$watch('autoplay', function(newAutoplay) {
                        if (player && player.getPlayerState) {
                            var currentState = player.getPlayerState();
                            if (newAutoplay && currentState === YT.PlayerState.PAUSED) {
                                player.playVideo();
                            } else if (!newAutoplay && currentState === YT.PlayerState.PLAYING) {
                                player.pauseVideo();
                            }
                        }
                    });

                    // Initialize player when directive loads
                    if (scope.videoId) {
                        initializePlayer();
                    }

                    // Public methods exposed to parent scope
                    scope.$parent.$on('youtubePlayer:play', function() {
                        if (player && player.playVideo) {
                            player.playVideo();
                        }
                    });

                    scope.$parent.$on('youtubePlayer:pause', function() {
                        if (player && player.pauseVideo) {
                            player.pauseVideo();
                        }
                    });

                    scope.$parent.$on('youtubePlayer:stop', function() {
                        if (player && player.stopVideo) {
                            player.stopVideo();
                        }
                    });

                    // Cleanup on scope destroy
                    scope.$on('$destroy', function() {
                        if (player && player.destroy) {
                            player.destroy();
                        }
                    });

                    // Expose player instance to parent scope if requested
                    if (attrs.playerInstance) {
                        scope.$parent[attrs.playerInstance] = player;
                    }
                }
            };
        }]);
})();