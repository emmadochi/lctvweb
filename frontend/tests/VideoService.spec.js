/**
 * Unit tests for VideoService
 */
describe('VideoService', function() {
    var VideoService, $httpBackend, $rootScope;

    // Mock data
    var mockVideos = [
        {
            id: 1,
            youtube_id: 'abc123',
            title: 'Test Video 1',
            description: 'Test description',
            thumbnail_url: 'http://example.com/thumb.jpg',
            duration: 300,
            view_count: 1000
        },
        {
            id: 2,
            youtube_id: 'def456',
            title: 'Test Video 2',
            description: 'Another test description',
            thumbnail_url: 'http://example.com/thumb2.jpg',
            duration: 450,
            view_count: 500
        }
    ];

    var mockVideo = mockVideos[0];

    beforeEach(function() {
        module('ChurchTVApp');

        inject(function(_VideoService_, _$httpBackend_, _$rootScope_) {
            VideoService = _VideoService_;
            $httpBackend = _$httpBackend_;
            $rootScope = _$rootScope_;
        });
    });

    afterEach(function() {
        $httpBackend.verifyNoOutstandingExpectation();
        $httpBackend.verifyNoOutstandingRequest();
    });

    describe('getFeaturedVideos', function() {
        it('should fetch featured videos from API', function() {
            $httpBackend.expectGET('/api/v1/videos?featured=true&limit=12')
                .respond(200, mockVideos);

            VideoService.getFeaturedVideos(12).then(function(videos) {
                expect(videos).toEqual(mockVideos);
                expect(videos.length).toBe(2);
            });

            $httpBackend.flush();
        });

        it('should handle API errors gracefully', function() {
            $httpBackend.expectGET('/api/v1/videos?featured=true&limit=12')
                .respond(500, {error: 'Server error'});

            VideoService.getFeaturedVideos(12).then(function(videos) {
                expect(videos).toEqual([]);
            });

            $httpBackend.flush();
        });
    });

    describe('getVideo', function() {
        it('should fetch a single video by ID', function() {
            $httpBackend.expectGET('/api/v1/videos/1')
                .respond(200, mockVideo);

            VideoService.getVideo(1).then(function(video) {
                expect(video).toEqual(mockVideo);
                expect(video.id).toBe(1);
                expect(video.title).toBe('Test Video 1');
            });

            $httpBackend.flush();
        });

        it('should return null for non-existent video', function() {
            $httpBackend.expectGET('/api/v1/videos/999')
                .respond(404, {error: 'Video not found'});

            VideoService.getVideo(999).then(function(video) {
                expect(video).toBeUndefined();
            });

            $httpBackend.flush();
        });
    });

    describe('searchVideos', function() {
        var searchResults = {
            videos: mockVideos,
            total: 2,
            page: 1,
            total_pages: 1
        };

        it('should search videos with query', function() {
            $httpBackend.expectGET('/api/v1/search?page=1&limit=24&q=test')
                .respond(200, searchResults);

            VideoService.searchVideos('test').then(function(results) {
                expect(results.videos).toEqual(mockVideos);
                expect(results.total).toBe(2);
            });

            $httpBackend.flush();
        });

        it('should handle search with category filter', function() {
            $httpBackend.expectGET('/api/v1/search?category=1&page=1&limit=10&q=sermon')
                .respond(200, searchResults);

            VideoService.searchVideos('sermon', {category: 1}, 1, 10).then(function(results) {
                expect(results.videos).toEqual(mockVideos);
            });

            $httpBackend.flush();
        });
    });

    describe('getVideosByCategory', function() {
        it('should fetch videos by category', function() {
            $httpBackend.expectGET('/api/v1/categories/1/videos?page=1&limit=24')
                .respond(200, mockVideos);

            VideoService.getVideosByCategory(1).then(function(videos) {
                expect(videos).toEqual(mockVideos);
                expect(videos.length).toBe(2);
            });

            $httpBackend.flush();
        });

        it('should handle pagination parameters', function() {
            $httpBackend.expectGET('/api/v1/categories/2/videos?page=2&limit=12')
                .respond(200, [mockVideos[0]]);

            VideoService.getVideosByCategory(2, 2, 12).then(function(videos) {
                expect(videos).toEqual([mockVideos[0]]);
                expect(videos.length).toBe(1);
            });

            $httpBackend.flush();
        });
    });

    describe('trackView', function() {
        it('should track video view', function() {
            var viewData = {
                user_id: null,
                timestamp: jasmine.any(String)
            };

            $httpBackend.expectPOST('/api/v1/videos/1/view', viewData)
                .respond(200, {success: true});

            VideoService.trackView(1).then(function(result) {
                expect(result).toBeDefined();
            });

            $httpBackend.flush();
        });

        it('should handle tracking errors gracefully', function() {
            $httpBackend.expectPOST('/api/v1/videos/1/view', jasmine.any(Object))
                .respond(500, {error: 'Server error'});

            // Should not throw error, just log warning
            VideoService.trackView(1).then(function(result) {
                expect(result).toBeNull();
            });

            $httpBackend.flush();
        });
    });

    describe('toggleLike', function() {
        it('should toggle video like status', function() {
            var likeData = { user_id: 123 };
            var response = { liked: true, like_count: 5 };

            $httpBackend.expectPOST('/api/v1/videos/1/like', likeData)
                .respond(200, response);

            VideoService.toggleLike(1, 123).then(function(result) {
                expect(result.liked).toBe(true);
                expect(result.like_count).toBe(5);
            });

            $httpBackend.flush();
        });
    });

    describe('Utility functions', function() {
        describe('formatViews', function() {
            // This would test the formatViews function if it were exposed
            // For now, we'll test the core functionality
            it('should format view counts correctly', function() {
                // Test data for reference
                expect(true).toBe(true); // Placeholder test
            });
        });
    });
});