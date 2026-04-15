/**
 * Leadership Controller
 * Handles the exclusive leadership library view
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .controller('LeadershipController', ['$scope', 'VideoService', 'LivestreamService', 'AuthService', 'UserService',
            function($scope, VideoService, LivestreamService, AuthService, UserService) {

            var vm = this;

            // Controller properties
            vm.exclusiveVideos = [];
            vm.exclusiveLivestreams = [];
            vm.loading = true;
            vm.user = AuthService.getCurrentUser();
            vm.favoriteIds = {};

            // Initialize controller
            init();

            function init() {
                loadExclusiveContent();
                loadFavorites();

                // Set page title
                $scope.$root.setPageTitle('Leadership Library');
            }

            /**
             * Load all exclusive leadership content
             */
            function loadExclusiveContent() {
                vm.loading = true;

                var videoPromise = VideoService.getExclusiveVideos(50);
                var livestreamPromise = LivestreamService.getExclusiveLivestreams();

                Promise.all([videoPromise, livestreamPromise]).then(function(results) {
                    $scope.$apply(function() {
                        vm.exclusiveVideos = results[0] || [];
                        vm.exclusiveLivestreams = results[1] || [];
                        vm.loading = false;
                    });
                }).catch(function(error) {
                    console.error('Error loading leadership content:', error);
                    $scope.$apply(function() {
                        vm.loading = false;
                        $scope.$root.showToast('Error loading leadership library', 'error');
                    });
                });
            }

            /**
             * Load user favorites
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

            // Expose controller to scope
            $scope.vm = vm;
        }]);
})();
