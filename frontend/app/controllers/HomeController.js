/**
 * Home Controller
 * Handles homepage functionality and featured content
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .controller('HomeController', ['$scope', '$location', 'VideoService', 'LivestreamService', 'CategoryService', 'PlaylistService', 'UserService',
            function($scope, $location, VideoService, LivestreamService, CategoryService, PlaylistService, UserService) {

            var vm = this;

            // Controller properties
            vm.featuredVideos = [];
            vm.trendingVideos = [];
            vm.recentVideos = [];
            vm.heroVideos = [];
            vm.categories = [];
            vm.livestreams = [];
            vm.featuredLivestream = null;
            vm.loading = true;
            vm.loadingLivestreams = false;
            vm.loadingRecent = false;
            vm.searchQuery = '';
            vm.favoriteIds = {};
            vm.currentHeroVideo = 0;

            // Initialize controller
            init();

            function init() {
                loadCategories();
                loadFeaturedVideos();
                loadTrendingVideos();
                loadRecentVideos();
                loadFavorites();
                loadHeroVideos();
                loadLivestreams();

                // Set page title
                $scope.$root.setPageTitle();
            }

            /**
             * Load categories for homepage display
             */
            function loadCategories() {
                CategoryService.getCategories().then(function(categories) {
                    // Add icons and colors to categories
                    vm.categories = categories.map(function(category) {
                        return {
                            id: category.id,
                            name: category.name,
                            slug: category.slug,
                            description: category.description,
                            thumbnail_url: category.thumbnail_url,
                            icon: CategoryService.getCategoryIcon(category.slug),
                            color: CategoryService.getCategoryColor(category.slug)
                        };
                    });
                }).catch(function(error) {
                    console.error('Error loading categories:', error);
                    vm.categories = [];
                });
            }

            /**
             * Load featured videos
             */
            function loadFeaturedVideos() {
                VideoService.getFeaturedVideos(12).then(function(videos) {
                    vm.featuredVideos = videos;
                    updateLoadingState();
                }).catch(function(error) {
                    console.error('Error loading featured videos:', error);
                    vm.featuredVideos = [];
                    updateLoadingState();
                });
            }

            /**
             * Load hero videos (recently added videos for hero background)
             */
            function loadHeroVideos() {
                // Use a mix of featured and trending videos for hero background
                VideoService.getFeaturedVideos(6).then(function(featured) {
                    VideoService.getTrendingVideos(6).then(function(trending) {
                        // Combine and deduplicate videos
                        var allVideos = featured.concat(trending);
                        var uniqueVideos = [];
                        var seenIds = {};

                        allVideos.forEach(function(video) {
                            if (!seenIds[video.id]) {
                                seenIds[video.id] = true;
                                uniqueVideos.push(video);
                            }
                        });

                        vm.heroVideos = uniqueVideos.slice(0, 8); // Limit to 8 videos for hero
                        startHeroVideoCycle();
                    });
                }).catch(function(error) {
                    console.error('Error loading hero videos:', error);
                    vm.heroVideos = [];
                });
            }

            /**
             * Start cycling through hero videos
             */
            function startHeroVideoCycle() {
                if (vm.heroVideos.length === 0) return;

                // Change hero video every 8 seconds
                setInterval(function() {
                    $scope.$apply(function() {
                        vm.currentHeroVideo = (vm.currentHeroVideo + 1) % vm.heroVideos.length;
                    });
                }, 8000);
            }

            /**
             * Navigate to previous hero video
             */
            vm.prevHeroVideo = function() {
                vm.currentHeroVideo = vm.currentHeroVideo > 0 ? vm.currentHeroVideo - 1 : vm.heroVideos.length - 1;
            };

            /**
             * Navigate to next hero video
             */
            vm.nextHeroVideo = function() {
                vm.currentHeroVideo = (vm.currentHeroVideo + 1) % vm.heroVideos.length;
            };

            /**
             * Set specific hero video
             */
            vm.setHeroVideo = function(index) {
                vm.currentHeroVideo = index;
            };

            /**
             * Get hero video progress (for progress bar)
             */
            vm.getHeroProgress = function() {
                if (vm.heroVideos.length === 0) return 0;
                return ((vm.currentHeroVideo + 1) / vm.heroVideos.length) * 100;
            };

            /**
             * Load trending videos
             */
            function loadTrendingVideos() {
                VideoService.getTrendingVideos(12).then(function(videos) {
                    vm.trendingVideos = videos;
                    updateLoadingState();
                }).catch(function(error) {
                    console.error('Error loading trending videos:', error);
                    vm.trendingVideos = [];
                    updateLoadingState();
                });
            }

            /**
             * Load recent videos
             */
            function loadRecentVideos() {
                vm.loadingRecent = true;
                VideoService.getRecentVideos(12).then(function(videos) {
                    vm.recentVideos = videos;
                    vm.loadingRecent = false;
                    updateLoadingState();
                }).catch(function(error) {
                    console.error('Error loading recent videos:', error);
                    vm.recentVideos = [];
                    vm.loadingRecent = false;
                    updateLoadingState();
                });
            }

            /**
             * Load livestreams
             */
            function loadLivestreams() {
                vm.loadingLivestreams = true;
                LivestreamService.getAllLivestreams().then(function(livestreams) {
                    vm.livestreams = livestreams;
                    vm.loadingLivestreams = false;
                    updateLoadingState();
                }).catch(function(error) {
                    console.error('Error loading livestreams:', error);
                    vm.livestreams = [];
                    vm.loadingLivestreams = false;
                    updateLoadingState();
                });

                // Also load featured livestream
                LivestreamService.getFeaturedLivestream().then(function(featured) {
                    vm.featuredLivestream = featured;
                }).catch(function(error) {
                    console.warn('Error loading featured livestream:', error);
                    vm.featuredLivestream = null;
                });
            }


            /**
             * Update loading state when all data is loaded
             */
            function updateLoadingState() {
                // Check if all data is loaded
                if (vm.categories.length >= 0 && vm.featuredVideos.length >= 0 &&
                    vm.trendingVideos.length >= 0 && vm.recentVideos.length >= 0 &&
                    !vm.loadingLivestreams && !vm.loadingRecent) {
                    vm.loading = false;
                }
            }

            /**
             * Load user favorites for icon state (API or localStorage)
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

            /**
             * Navigate to video player
             */
            vm.playVideo = function(videoId) {
                $location.path('/video/' + videoId);
            };

            /**
             * Navigate to livestream player
             */
            vm.playLivestream = function(livestreamId) {
                $location.path('/livestream/' + livestreamId);
            };

            /**
             * Navigate to category page
             */
            vm.browseCategory = function(categorySlug) {
                $location.path('/category/' + categorySlug);
            };

            /**
             * Handle search from homepage
             */
            vm.search = function() {
                if (vm.searchQuery && vm.searchQuery.trim()) {
                    $location.path('/search').search({q: vm.searchQuery.trim()});
                }
            };

            /**
             * Format video duration for display
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
             * Format view count for display
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

            // Expose controller to scope for template access
            $scope.vm = vm;
        }]);
})();