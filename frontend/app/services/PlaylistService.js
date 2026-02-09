/**
 * Playlist/Series Service
 * Handles sermon series, seasonal content, and playlist management
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .factory('PlaylistService', ['$http', '$q', 'API_BASE', function($http, $q, API_BASE) {

            var service = {};

            // Cache for playlists
            var playlistsCache = null;
            var cacheExpiry = 5 * 60 * 1000; // 5 minutes

            function unwrap(res) {
                var body = res && res.data;
                if (body && body.success === true && body.data !== undefined) {
                    return body.data;
                }
                return body;
            }

            /**
             * Get all active playlists/series
             */
            service.getPlaylists = function() {
                if (playlistsCache && playlistsCache.timestamp &&
                    (Date.now() - playlistsCache.timestamp) < cacheExpiry) {
                    return $q.resolve(playlistsCache.data);
                }

                return $http.get(API_BASE + '/playlists')
                    .then(function(response) {
                        var data = unwrap(response);
                        var list = Array.isArray(data) ? data : [];
                        playlistsCache = { data: list, timestamp: Date.now() };
                        return list;
                    }).catch(function(error) {
                        console.error('Error fetching playlists:', error);
                        return [];
                    });
            };

            /**
             * Get playlist by ID
             */
            service.getPlaylist = function(playlistId) {
                return $http.get(API_BASE + '/playlists/' + playlistId)
                    .then(function(response) {
                        return unwrap(response) || response.data;
                    }).catch(function(error) {
                        console.error('Error fetching playlist:', error);
                        throw error;
                    });
            };

            /**
             * Get playlists by category
             */
            service.getPlaylistsByCategory = function(categoryId) {
                return service.getPlaylists().then(function(playlists) {
                    return playlists.filter(function(playlist) {
                        return playlist.category_id === categoryId;
                    });
                });
            };

            /**
             * Get seasonal playlists (Christmas, Easter, etc.)
             */
            service.getSeasonalPlaylists = function(season) {
                return service.getPlaylists().then(function(playlists) {
                    return playlists.filter(function(playlist) {
                        return playlist.is_seasonal && playlist.season === season;
                    });
                });
            };

            /**
             * Get sermon series
             */
            service.getSermonSeries = function() {
                return service.getPlaylists().then(function(playlists) {
                    return playlists.filter(function(playlist) {
                        return playlist.type === 'sermon_series' || playlist.category_id === 4; // Sermons category
                    });
                });
            };

            /**
             * Get videos in a playlist
             */
            service.getPlaylistVideos = function(playlistId) {
                return $http.get(API_BASE + '/playlists/' + playlistId + '/videos')
                    .then(function(response) {
                        var data = unwrap(response);
                        return Array.isArray(data) ? data : [];
                    }).catch(function(error) {
                        console.error('Error fetching playlist videos:', error);
                        return [];
                    });
            };

            /**
             * Get featured playlists for homepage
             */
            service.getFeaturedPlaylists = function(limit) {
                limit = limit || 6;

                return service.getPlaylists().then(function(playlists) {
                    // Sort by featured status and creation date
                    return playlists
                        .filter(function(playlist) {
                            return playlist.is_featured || playlist.is_active;
                        })
                        .sort(function(a, b) {
                            // Featured first, then by creation date
                            if (a.is_featured && !b.is_featured) return -1;
                            if (!a.is_featured && b.is_featured) return 1;
                            return new Date(b.created_at) - new Date(a.created_at);
                        })
                        .slice(0, limit);
                });
            };

            /**
             * Search playlists
             */
            service.searchPlaylists = function(query) {
                if (!query || query.length < 2) {
                    return $q.resolve([]);
                }

                return service.getPlaylists().then(function(playlists) {
                    var lowercaseQuery = query.toLowerCase();
                    return playlists.filter(function(playlist) {
                        return playlist.name.toLowerCase().includes(lowercaseQuery) ||
                               playlist.description.toLowerCase().includes(lowercaseQuery);
                    });
                });
            };

            /**
             * Get playlist types/categories
             */
            service.getPlaylistTypes = function() {
                return [
                    { value: 'sermon_series', label: 'Sermon Series', icon: 'fa-microphone' },
                    { value: 'seasonal', label: 'Seasonal Content', icon: 'fa-calendar' },
                    { value: 'special_events', label: 'Special Events', icon: 'fa-star' },
                    { value: 'teaching_series', label: 'Teaching Series', icon: 'fa-book' },
                    { value: 'youth_series', label: 'Youth Series', icon: 'fa-users' },
                    { value: 'music_series', label: 'Music Series', icon: 'fa-music' }
                ];
            };

            /**
             * Get seasonal options
             */
            service.getSeasonalOptions = function() {
                return [
                    { value: 'christmas', label: 'Christmas', icon: 'fa-tree' },
                    { value: 'easter', label: 'Easter', icon: 'fa-cross' },
                    { value: 'lent', label: 'Lent', icon: 'fa-pray' },
                    { value: 'advent', label: 'Advent', icon: 'fa-candle-holder-o' },
                    { value: 'summer', label: 'Summer', icon: 'fa-sun-o' },
                    { value: 'thanksgiving', label: 'Thanksgiving', icon: 'fa-apple' },
                    { value: 'new_year', label: 'New Year', icon: 'fa-firework' }
                ];
            };

            /**
             * Clear cache
             */
            service.clearCache = function() {
                playlistsCache = null;
            };

            /**
             * Get playlist icon based on type
             */
            service.getPlaylistIcon = function(playlist) {
                if (playlist.type) {
                    var typeIcons = {
                        'sermon_series': 'fa-microphone',
                        'seasonal': 'fa-calendar',
                        'special_events': 'fa-star',
                        'teaching_series': 'fa-book',
                        'youth_series': 'fa-users',
                        'music_series': 'fa-music'
                    };
                    return typeIcons[playlist.type] || 'fa-play-circle';
                }

                // Fallback based on category
                if (playlist.category_name) {
                    var categoryIcons = {
                        'Sermons': 'fa-microphone',
                        'Worship': 'fa-music',
                        'Youth': 'fa-users',
                        'Bible Study': 'fa-book'
                    };
                    return categoryIcons[playlist.category_name] || 'fa-play-circle';
                }

                return 'fa-play-circle';
            };

            /**
             * Get playlist color theme
             */
            service.getPlaylistColor = function(playlist) {
                if (playlist.season) {
                    var seasonColors = {
                        'christmas': '#e74c3c',
                        'easter': '#27ae60',
                        'lent': '#8e44ad',
                        'advent': '#e67e22',
                        'summer': '#f39c12',
                        'thanksgiving': '#d35400',
                        'new_year': '#3498db'
                    };
                    return seasonColors[playlist.season] || '#3498db';
                }

                return '#3498db';
            };

            return service;
        }]);
})();