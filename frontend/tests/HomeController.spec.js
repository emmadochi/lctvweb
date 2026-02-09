/**
 * Unit tests for HomeController
 */
describe('HomeController', function() {
    var $controller, $rootScope, $q, scope;
    var VideoService, CategoryService, PlaylistService;

    // Mock data
    var mockCategories = [
        { id: 1, name: 'Sermons', slug: 'sermons' },
        { id: 2, name: 'Worship', slug: 'worship' }
    ];

    var mockVideos = [
        { id: 1, title: 'Video 1', youtube_id: 'abc123' },
        { id: 2, title: 'Video 2', youtube_id: 'def456' }
    ];

    var mockPlaylists = [
        { id: 1, name: 'Sermon Series', video_count: 5 }
    ];

    beforeEach(function() {
        module('ChurchTVApp');

        inject(function(_$controller_, _$rootScope_, _$q_, _VideoService_, _CategoryService_) {
            $controller = _$controller_;
            $rootScope = _$rootScope_;
            $q = _$q_;
            VideoService = _VideoService_;
            CategoryService = _CategoryService_;
            scope = $rootScope.$new();
        });

        // Mock PlaylistService if it exists
        try {
            inject(function(_PlaylistService_) {
                PlaylistService = _PlaylistService_;
            });
        } catch (e) {
            // PlaylistService might not be loaded in tests
            PlaylistService = null;
        }
    });

    describe('Initialization', function() {
        var controller;

        beforeEach(function() {
            // Mock service methods
            spyOn(CategoryService, 'getCategories').and.returnValue($q.resolve(mockCategories));
            spyOn(VideoService, 'getFeaturedVideos').and.returnValue($q.resolve(mockVideos));
            spyOn(VideoService, 'getTrendingVideos').and.returnValue($q.resolve([]));

            if (PlaylistService) {
                spyOn(PlaylistService, 'getFeaturedPlaylists').and.returnValue($q.resolve(mockPlaylists));
            }

            controller = $controller('HomeController', {
                $scope: scope,
                VideoService: VideoService,
                CategoryService: CategoryService
            });
        });

        it('should initialize with default values', function() {
            expect(controller.featuredVideos).toEqual([]);
            expect(controller.categories).toEqual([]);
            expect(controller.playlists).toEqual([]);
        });

        it('should load categories on initialization', function() {
            scope.$digest(); // Trigger promise resolution
            expect(CategoryService.getCategories).toHaveBeenCalled();
            expect(controller.categories).toEqual(mockCategories);
        });

        it('should load featured videos on initialization', function() {
            scope.$digest();
            expect(VideoService.getFeaturedVideos).toHaveBeenCalledWith(12);
            expect(controller.featuredVideos).toEqual(mockVideos);
        });

        it('should load trending videos on initialization', function() {
            scope.$digest();
            expect(VideoService.getTrendingVideos).toHaveBeenCalledWith(12);
        });

        it('should set page title', function() {
            spyOn($rootScope, 'setPageTitle');
            controller = $controller('HomeController', {
                $scope: scope,
                VideoService: VideoService,
                CategoryService: CategoryService
            });
            expect($rootScope.setPageTitle).toHaveBeenCalledWith();
        });
    });

    describe('Navigation methods', function() {
        var controller, $location;

        beforeEach(function() {
            inject(function(_$location_) {
                $location = _$location_;
            });

            controller = $controller('HomeController', {
                $scope: scope,
                VideoService: VideoService,
                CategoryService: CategoryService
            });

            spyOn($location, 'path');
        });

        it('should navigate to video page', function() {
            controller.playVideo(123);
            expect($location.path).toHaveBeenCalledWith('/video/123');
        });

        it('should navigate to category page', function() {
            controller.browseCategory('sermons');
            expect($location.path).toHaveBeenCalledWith('/category/sermons');
        });

        it('should navigate to playlist page', function() {
            controller.playPlaylist(456);
            expect($location.path).toHaveBeenCalledWith('/playlist/456');
        });
    });

    describe('Utility methods', function() {
        var controller;

        beforeEach(function() {
            controller = $controller('HomeController', {
                $scope: scope,
                VideoService: VideoService,
                CategoryService: CategoryService
            });
        });

        describe('formatDuration', function() {
            it('should format seconds to MM:SS', function() {
                expect(controller.formatDuration(65)).toBe('1:05');
                expect(controller.formatDuration(3661)).toBe('1:01:01');
                expect(controller.formatDuration(0)).toBe('0:00');
            });
        });

        describe('formatViews', function() {
            it('should format view counts', function() {
                expect(controller.formatViews(500)).toBe('500');
                expect(controller.formatViews(1500)).toBe('1.5K');
                expect(controller.formatViews(2500000)).toBe('2.5M');
            });
        });

        describe('formatDate', function() {
            it('should format dates appropriately', function() {
                var today = new Date().toISOString().split('T')[0];
                var yesterday = new Date(Date.now() - 86400000).toISOString().split('T')[0];

                expect(controller.formatDate(today)).toBe('Today');
                expect(controller.formatDate(yesterday)).toBe('Yesterday');
                expect(controller.formatDate('2024-01-01T00:00:00Z')).toBe('Jan 1, 2024');
            });
        });
    });

    describe('Error handling', function() {
        var controller;

        beforeEach(function() {
            controller = $controller('HomeController', {
                $scope: scope,
                VideoService: VideoService,
                CategoryService: CategoryService
            });
        });

        it('should handle category loading errors gracefully', function() {
            spyOn(CategoryService, 'getCategories').and.returnValue($q.reject('Server error'));
            spyOn(VideoService, 'getFeaturedVideos').and.returnValue($q.resolve([]));
            spyOn(VideoService, 'getTrendingVideos').and.returnValue($q.resolve([]));

            scope.$digest();
            expect(controller.categories).toEqual([]);
        });

        it('should handle video loading errors gracefully', function() {
            spyOn(CategoryService, 'getCategories').and.returnValue($q.resolve([]));
            spyOn(VideoService, 'getFeaturedVideos').and.returnValue($q.reject('Server error'));
            spyOn(VideoService, 'getTrendingVideos').and.returnValue($q.resolve([]));

            scope.$digest();
            expect(controller.featuredVideos).toEqual([]);
        });
    });
});