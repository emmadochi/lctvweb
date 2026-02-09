/**
 * Playlist Controller
 * Handles individual playlist/series page functionality
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .controller('PlaylistController', ['$scope', '$routeParams', '$location', 'PlaylistService', 'VideoService',
            function($scope, $routeParams, $location, PlaylistService, VideoService) {

            var vm = this;

            // Controller properties
            vm.playlist = null;
            vm.videos = [];
            vm.isLoading = true;
            vm.currentVideoIndex = 0;

            // Initialize controller
            init();

            function init() {
                var playlistId = $routeParams.id;

                if (!playlistId) {
                    $location.path('/');
                    return;
                }

                loadPlaylist(playlistId);
                loadPlaylistVideos(playlistId);

                // Set page title
                $scope.$root.setPageTitle('Loading playlist...');
            }

            /**
             * Load playlist details
             */
            function loadPlaylist(playlistId) {
                PlaylistService.getPlaylist(playlistId).then(function(playlist) {
                    vm.playlist = {
                        id: playlist.id,
                        name: playlist.name,
                        description: playlist.description,
                        video_count: playlist.video_ids ? playlist.video_ids.split(',').length : 0,
                        created_at: playlist.created_at,
                        season: playlist.season,
                        season_display: vm.getSeasonDisplayName(playlist.season),
                        icon: PlaylistService.getPlaylistIcon(playlist),
                        color: PlaylistService.getPlaylistColor(playlist),
                        category_name: playlist.category_name
                    };

                    // Set page title
                    $scope.$root.setPageTitle(playlist.name);

                    vm.isLoading = false;
                }).catch(function(error) {
                    console.error('Error loading playlist:', error);
                    $scope.$root.showError('Playlist not found');
                    $location.path('/');
                });
            }

            /**
             * Load videos in the playlist
             */
            function loadPlaylistVideos(playlistId) {
                PlaylistService.getPlaylistVideos(playlistId).then(function(videos) {
                    vm.videos = videos.map(function(video, index) {
                        return {
                            id: video.id,
                            youtube_id: video.youtube_id,
                            title: video.title,
                            description: video.description,
                            thumbnail_url: video.thumbnail_url,
                            duration: video.duration,
                            view_count: video.view_count,
                            published_at: video.published_at,
                            index: index,
                            isPlaying: false
                        };
                    });
                }).catch(function(error) {
                    console.error('Error loading playlist videos:', error);
                    vm.videos = [];
                });
            }

            /**
             * Play video from playlist
             */
            vm.playVideo = function(videoId) {
                // Find the video index in the playlist
                var videoIndex = vm.videos.findIndex(function(video) {
                    return video.id === videoId;
                });

                if (videoIndex !== -1) {
                    vm.currentVideoIndex = videoIndex;
                    // Mark current video as playing
                    vm.videos.forEach(function(video, index) {
                        video.isPlaying = (index === videoIndex);
                    });
                }

                $location.path('/video/' + videoId);
            };

            /**
             * Play next video in playlist
             */
            vm.playNext = function() {
                if (vm.currentVideoIndex < vm.videos.length - 1) {
                    var nextVideo = vm.videos[vm.currentVideoIndex + 1];
                    vm.playVideo(nextVideo.id);
                }
            };

            /**
             * Play previous video in playlist
             */
            vm.playPrevious = function() {
                if (vm.currentVideoIndex > 0) {
                    var prevVideo = vm.videos[vm.currentVideoIndex - 1];
                    vm.playVideo(prevVideo.id);
                }
            };

            /**
             * Check if next video is available
             */
            vm.hasNext = function() {
                return vm.currentVideoIndex < vm.videos.length - 1;
            };

            /**
             * Check if previous video is available
             */
            vm.hasPrevious = function() {
                return vm.currentVideoIndex > 0;
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
             * Format date
             */
            vm.formatDate = function(dateString) {
                if (!dateString) return '';

                var date = new Date(dateString);
                return date.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
            };

            /**
             * Get season display name
             */
            vm.getSeasonDisplayName = function(season) {
                var seasonNames = {
                    'christmas': 'Christmas',
                    'easter': 'Easter',
                    'lent': 'Lent',
                    'advent': 'Advent',
                    'summer': 'Summer',
                    'thanksgiving': 'Thanksgiving',
                    'new_year': 'New Year'
                };
                return seasonNames[season] || season;
            };

            /**
             * Calculate total playlist duration
             */
            vm.getTotalDuration = function() {
                var totalSeconds = vm.videos.reduce(function(total, video) {
                    return total + (video.duration || 0);
                }, 0);

                var hours = Math.floor(totalSeconds / 3600);
                var minutes = Math.floor((totalSeconds % 3600) / 60);

                if (hours > 0) {
                    return hours + 'h ' + minutes + 'm';
                } else {
                    return minutes + 'm';
                }
            };

            /**
             * Helper function
             */
            function padZero(num) {
                return (num < 10 ? '0' : '') + num;
            }

            // Expose controller to scope
            $scope.vm = vm;
        }]);
})();