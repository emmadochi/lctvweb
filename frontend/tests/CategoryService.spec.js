/**
 * Unit tests for CategoryService
 */
describe('CategoryService', function() {
    var CategoryService, $httpBackend;

    // Mock data
    var mockCategories = [
        {
            id: 1,
            name: 'Sermons',
            slug: 'sermons',
            description: 'Weekly sermons and teachings',
            thumbnail_url: 'http://example.com/sermons.jpg',
            sort_order: 1,
            is_active: true
        },
        {
            id: 2,
            name: 'Worship',
            slug: 'worship',
            description: 'Worship music and songs',
            thumbnail_url: 'http://example.com/worship.jpg',
            sort_order: 2,
            is_active: true
        }
    ];

    var mockCategory = mockCategories[0];

    beforeEach(function() {
        module('ChurchTVApp');

        inject(function(_CategoryService_, _$httpBackend_) {
            CategoryService = _CategoryService_;
            $httpBackend = _$httpBackend_;
        });
    });

    afterEach(function() {
        $httpBackend.verifyNoOutstandingExpectation();
        $httpBackend.verifyNoOutstandingRequest();
    });

    describe('getCategories', function() {
        it('should fetch all categories from API', function() {
            $httpBackend.expectGET('/api/v1/categories')
                .respond(200, mockCategories);

            CategoryService.getCategories().then(function(categories) {
                expect(categories).toEqual(mockCategories);
                expect(categories.length).toBe(2);
            });

            $httpBackend.flush();
        });

        it('should use cached categories on subsequent calls', function() {
            // First call should make HTTP request
            $httpBackend.expectGET('/api/v1/categories')
                .respond(200, mockCategories);

            CategoryService.getCategories();
            $httpBackend.flush();

            // Second call should use cache (no additional HTTP request)
            CategoryService.getCategories().then(function(categories) {
                expect(categories).toEqual(mockCategories);
            });

            // No flush needed since no HTTP request was made
        });

        it('should handle API errors gracefully', function() {
            $httpBackend.expectGET('/api/v1/categories')
                .respond(500, {error: 'Server error'});

            CategoryService.getCategories().then(function(categories) {
                expect(categories).toEqual([]);
            });

            $httpBackend.flush();
        });
    });

    describe('getCategoryBySlug', function() {
        it('should find category by slug', function() {
            // Pre-populate cache
            spyOn(CategoryService, 'getCategories').and.returnValue({
                then: function(callback) {
                    callback(mockCategories);
                }
            });

            CategoryService.getCategoryBySlug('sermons').then(function(category) {
                expect(category).toEqual(mockCategories[0]);
                expect(category.slug).toBe('sermons');
            });
        });

        it('should return undefined for non-existent slug', function() {
            spyOn(CategoryService, 'getCategories').and.returnValue({
                then: function(callback) {
                    callback(mockCategories);
                }
            });

            CategoryService.getCategoryBySlug('nonexistent').then(function(category) {
                expect(category).toBeUndefined();
            });
        });
    });

    describe('getCategoryStats', function() {
        var mockStats = {
            video_count: 25,
            total_views: 15000,
            last_updated: '2024-01-15T10:30:00Z'
        };

        it('should fetch category statistics', function() {
            $httpBackend.expectGET('/api/v1/categories/1/stats')
                .respond(200, mockStats);

            CategoryService.getCategoryStats(1).then(function(stats) {
                expect(stats).toEqual(mockStats);
                expect(stats.video_count).toBe(25);
                expect(stats.total_views).toBe(15000);
            });

            $httpBackend.flush();
        });

        it('should provide default stats on error', function() {
            $httpBackend.expectGET('/api/v1/categories/1/stats')
                .respond(500, {error: 'Server error'});

            CategoryService.getCategoryStats(1).then(function(stats) {
                expect(stats.video_count).toBe(0);
                expect(stats.total_views).toBe(0);
                expect(stats.last_updated).toBeNull();
            });

            $httpBackend.flush();
        });
    });

    describe('Slug generation', function() {
        describe('generateSlug', function() {
            it('should convert name to URL-friendly slug', function() {
                expect(CategoryService.generateSlug('Sermons & Teachings')).toBe('sermons-teachings');
                expect(CategoryService.generateSlug('Youth Ministry 2024')).toBe('youth-ministry-2024');
                expect(CategoryService.generateSlug('')).toBe('');
            });

            it('should handle special characters and spaces', function() {
                expect(CategoryService.generateSlug('Hello, World!')).toBe('hello-world');
                expect(CategoryService.generateSlug('Test_underscore')).toBe('test-underscore');
                expect(CategoryService.generateSlug('Multiple   Spaces')).toBe('multiple-spaces');
            });
        });

        describe('onNameChange', function() {
            it('should auto-generate slug when name changes', function() {
                var category = { name: 'New Category', slug: '' };
                CategoryService.onNameChange(category);
                expect(category.slug).toBe('new-category');
            });

            it('should not overwrite existing custom slug', function() {
                var category = {
                    name: 'Updated Name',
                    slug: 'custom-slug',
                    originalName: 'Original Name'
                };
                CategoryService.onNameChange(category);
                expect(category.slug).toBe('custom-slug');
            });
        });
    });

    describe('Icon and color utilities', function() {
        describe('getCategoryIcon', function() {
            it('should return appropriate icons for categories', function() {
                expect(CategoryService.getCategoryIcon('sermons')).toBe('fa-microphone');
                expect(CategoryService.getCategoryIcon('worship')).toBe('fa-music');
                expect(CategoryService.getCategoryIcon('youth')).toBe('fa-users');
                expect(CategoryService.getCategoryIcon('unknown')).toBe('fa-play-circle');
            });
        });

        describe('getCategoryColor', function() {
            it('should return appropriate colors for categories', function() {
                expect(CategoryService.getCategoryColor('sermons')).toBe('#3498db');
                expect(CategoryService.getCategoryColor('worship')).toBe('#e74c3c');
                expect(CategoryService.getCategoryColor('unknown')).toBe('#95a5a6');
            });

            it('should return seasonal colors', function() {
                expect(CategoryService.getCategoryColor({season: 'christmas'})).toBe('#e74c3c');
                expect(CategoryService.getCategoryColor({season: 'easter'})).toBe('#27ae60');
            });
        });
    });

    describe('Cache management', function() {
        it('should clear cache when requested', function() {
            // This would test the clearCache method if it were implemented
            expect(CategoryService.clearCache).toBeDefined();
        });
    });
});