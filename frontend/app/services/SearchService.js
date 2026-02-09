/**
 * Search Service
 * Handles video search functionality and search history
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .factory('SearchService', ['$http', 'API_BASE', function($http, API_BASE) {

            var service = {};

            // Search history management
            var MAX_HISTORY_ITEMS = 10;
            var SEARCH_HISTORY_KEY = 'churchtv_search_history';

            function unwrap(res) {
                var body = res && res.data;
                if (body && body.success === true && body.data !== undefined) {
                    return body.data;
                }
                return body;
            }

            /**
             * Search videos with advanced filters
             */
            service.search = function(query, options) {
                options = options || {};

                var params = {
                    q: query,
                    page: options.page || 1,
                    limit: options.limit || 24,
                    sort: options.sort || 'relevance',
                    category: options.category,
                    duration: options.duration,
                    date: options.date
                };

                // Remove undefined parameters
                Object.keys(params).forEach(function(key) {
                    if (params[key] === undefined || params[key] === null) {
                        delete params[key];
                    }
                });

                return $http.get(API_BASE + '/search', {
                    params: params
                }).then(function(response) {
                    if (query && query.trim()) {
                        service.addToHistory(query.trim());
                    }
                    var data = unwrap(response);
                    return data && typeof data === 'object' ? data : {
                        videos: [],
                        total: 0,
                        query: query,
                        page: params.page,
                        total_pages: 0
                    };
                }).catch(function(error) {
                    console.error('Search error:', error);
                    return {
                        videos: [],
                        total: 0,
                        query: query,
                        page: params.page,
                        total_pages: 0
                    };
                });
            };

            /**
             * Get search suggestions
             */
            service.getSuggestions = function(query) {
                if (!query || query.length < 2) {
                    return Promise.resolve([]);
                }
                return $http.get(API_BASE + '/search/suggestions', {
                    params: { q: query }
                }).then(function(response) {
                    var data = unwrap(response);
                    var suggestions = [];
                    if (data && data.suggestions && Array.isArray(data.suggestions)) {
                        suggestions = data.suggestions;
                    }
                    return suggestions;
                }).catch(function(error) {
                    console.error('SearchService: Error getting suggestions:', error);
                    return [];
                });
            };

            /**
             * Get trending search terms
             */
            service.getTrendingSearches = function(limit) {
                limit = limit || 10;
                return $http.get(API_BASE + '/search/trending', {
                    params: { limit: limit }
                }).then(function(response) {
                    var data = unwrap(response);
                    return Array.isArray(data) ? data : [];
                }).catch(function(error) {
                    console.error('Error getting trending searches:', error);
                    return [];
                });
            };

            /**
             * Search history management
             */
            service.getSearchHistory = function() {
                try {
                    var history = localStorage.getItem(SEARCH_HISTORY_KEY);
                    return history ? JSON.parse(history) : [];
                } catch (error) {
                    console.error('Error loading search history:', error);
                    return [];
                }
            };

            service.addToHistory = function(query) {
                try {
                    var history = service.getSearchHistory();

                    // Remove if already exists
                    history = history.filter(function(item) {
                        return item.query !== query;
                    });

                    // Add to beginning
                    history.unshift({
                        query: query,
                        timestamp: new Date().toISOString()
                    });

                    // Limit history size
                    if (history.length > MAX_HISTORY_ITEMS) {
                        history = history.slice(0, MAX_HISTORY_ITEMS);
                    }

                    localStorage.setItem(SEARCH_HISTORY_KEY, JSON.stringify(history));
                } catch (error) {
                    console.error('Error saving to search history:', error);
                }
            };

            service.clearHistory = function() {
                try {
                    localStorage.removeItem(SEARCH_HISTORY_KEY);
                } catch (error) {
                    console.error('Error clearing search history:', error);
                }
            };

            /**
             * Advanced search filters
             */
            service.getFilterOptions = function() {
                return {
                    sortOptions: [
                        { value: 'relevance', label: 'Most Relevant' },
                        { value: 'date', label: 'Most Recent' },
                        { value: 'views', label: 'Most Viewed' },
                        { value: 'rating', label: 'Highest Rated' }
                    ],
                    durationOptions: [
                        { value: 'short', label: 'Under 4 minutes' },
                        { value: 'medium', label: '4-20 minutes' },
                        { value: 'long', label: 'Over 20 minutes' }
                    ],
                    dateOptions: [
                        { value: 'hour', label: 'Last hour' },
                        { value: 'today', label: 'Today' },
                        { value: 'week', label: 'This week' },
                        { value: 'month', label: 'This month' },
                        { value: 'year', label: 'This year' }
                    ]
                };
            };

            /**
             * Search analytics (for future use)
             */
            service.trackSearch = function(query, resultsCount, filters) {
                // Track search analytics
                console.log('Search tracked:', {
                    query: query,
                    results: resultsCount,
                    filters: filters,
                    timestamp: new Date().toISOString()
                });

                // Could send to analytics service
                // return $http.post(API_BASE + '/analytics/search', {...});
            };

            return service;
        }]);
})();