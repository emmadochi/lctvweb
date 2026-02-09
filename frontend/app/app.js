/**
 * Church TV Streaming Platform - AngularJS Application
 * Main application configuration and routing
 */

(function() {
    'use strict';

    // Main application module
    angular.module('ChurchTVApp', [
        'ngRoute',
        'ngAnimate',
        'ngSanitize'
    ])

    // Application configuration
    .config(['$routeProvider', '$locationProvider', '$httpProvider', function($routeProvider, $locationProvider, $httpProvider) {

        // HTTP Interceptors for authentication and API error handling
        $httpProvider.interceptors.push(['$q', '$location', 'API_BASE', function($q, $location, API_BASE) {
            return {
                request: function(config) {
                    // Add auth token to API requests
                    if (config.url && config.url.indexOf(API_BASE) === 0) {
                        try {
                            var token = localStorage.getItem('churchtv_auth_token');
                            if (token) {
                                config.headers.Authorization = 'Bearer ' + token;
                            }
                        } catch (error) {
                            console.warn('Error accessing localStorage for auth token:', error);
                        }
                    }
                    return config;
                },

                responseError: function(rejection) {
                    // Log API failures
                    if (rejection.config && rejection.config.url && rejection.config.url.indexOf(API_BASE) === 0) {
                        console.warn('Church TV API request failed:', rejection.config.url, rejection.status, rejection.statusText || rejection.message);

                        // Handle authentication errors
                        if (rejection.status === 401) {
                            console.warn('Authentication failed, clearing local auth data');
                            try {
                                localStorage.removeItem('churchtv_auth_token');
                                localStorage.removeItem('churchtv_user');
                                // Clear auth header
                                delete rejection.config.headers.Authorization;
                            } catch (error) {
                                console.warn('Error clearing auth data:', error);
                            }
                            $location.path('/login');
                        }
                    }
                    return $q.reject(rejection);
                }
            };
        }]);

        // Configure routes
        $routeProvider
            .when('/', {
                templateUrl: 'app/views/pages/home.html',
                controller: 'HomeController',
                controllerAs: 'vm'
            })
            .when('/category/:slug', {
                templateUrl: 'app/views/pages/category.html',
                controller: 'CategoryController',
                controllerAs: 'vm'
            })
            .when('/video/:id', {
                templateUrl: 'app/views/pages/video.html',
                controller: 'VideoController',
                controllerAs: 'vm'
            })
            .when('/livestream/:id', {
                templateUrl: 'app/views/pages/livestream.html',
                controller: 'LivestreamController',
                controllerAs: 'vm'
            })
            .when('/search', {
                templateUrl: 'app/views/pages/search.html',
                controller: 'SearchController',
                controllerAs: 'vm'
            })
            .when('/favorites', {
                templateUrl: 'app/views/pages/favorites.html',
                controller: 'FavoritesController',
                controllerAs: 'vm'
            })
            .when('/login', {
                templateUrl: 'app/views/pages/login.html',
                controller: 'LoginController',
                controllerAs: 'vm'
            })
            .when('/profile', {
                templateUrl: 'app/views/pages/profile.html',
                controller: 'ProfileController',
                controllerAs: 'vm',
                resolve: {
                    auth: ['$q', 'AuthService', '$location', function($q, AuthService, $location) {
                        if (!AuthService.isAuthenticated()) {
                            $location.path('/login');
                            return $q.reject('Not authenticated');
                        }
                        return $q.resolve();
                    }]
                }
            })
            .when('/favorites', {
                templateUrl: 'app/views/pages/favorites.html',
                controller: 'FavoritesController',
                controllerAs: 'vm',
                resolve: {
                    auth: ['$q', 'AuthService', '$location', function($q, AuthService, $location) {
                        if (!AuthService.isAuthenticated()) {
                            $location.path('/login');
                            return $q.reject('Not authenticated');
                        }
                        return $q.resolve();
                    }]
                }
            })
            .when('/login', {
                templateUrl: 'app/views/pages/login.html',
                controller: 'LoginController',
                controllerAs: 'vm'
            })
            .when('/profile', {
                templateUrl: 'app/views/pages/profile.html',
                controller: 'ProfileController',
                controllerAs: 'vm',
                resolve: {
                    auth: ['$q', 'AuthService', '$location', function($q, AuthService, $location) {
                        if (!AuthService.isAuthenticated()) {
                            $location.path('/login');
                            return $q.reject('Not authenticated');
                        }
                        return $q.resolve();
                    }]
                }
            })
            .when('/playlist/:id', {
                templateUrl: 'app/views/pages/playlist.html',
                controller: 'PlaylistController',
                controllerAs: 'vm'
            })
            .when('/admin/login', {
                templateUrl: 'app/views/pages/admin-login.html',
                controller: 'AdminLoginController',
                controllerAs: 'vm'
            })
            .when('/admin', {
                templateUrl: 'app/views/pages/admin-dashboard.html',
                controller: 'AdminDashboardController',
                controllerAs: 'vm'
            })
            .when('/admin/videos', {
                templateUrl: 'app/views/pages/admin-videos.html',
                controller: 'AdminVideosController',
                controllerAs: 'vm'
            })
            .when('/admin/categories', {
                templateUrl: 'app/views/pages/admin-categories.html',
                controller: 'AdminCategoriesController',
                controllerAs: 'vm'
            })
            .when('/watchlater', {
                templateUrl: 'app/views/pages/favorites.html',
                controller: 'FavoritesController',
                controllerAs: 'vm'
            })
            .otherwise({
                redirectTo: '/'
            });

        // Enable HTML5 mode for cleaner URLs (requires server configuration)
        // $locationProvider.html5Mode(true);
    }])

    // Application constants
    // Backend API URL. For XAMPP: use http://localhost/LCMTVWebNew/backend/api
    // If you serve the frontend from XAMPP (e.g. http://localhost/LCMTVWebNew/frontend/), use that same origin.
    .constant('API_BASE', 'http://localhost/LCMTVWebNew/backend/api')
    .constant('YOUTUBE_API_KEY', 'AIzaSyDT1-_m5WdXEAPDd8J-WuPAGrmKgdMeqeY')
    .constant('APP_CONFIG', {
        title: 'Church TV',
        description: 'Modern church streaming platform',
        version: '1.0.0',
        maxVideosPerPage: 24,
        maxSearchResults: 50
    })

    // Root scope configuration
    .run(['$rootScope', '$location', '$timeout', 'API_BASE', 'PushService', 'AnalyticsService', function($rootScope, $location, $timeout, API_BASE, PushService, AnalyticsService) {

        // Global loading state
        $rootScope.loading = false;

        // Global error handling
        $rootScope.errorMessage = '';

        // Show loading overlay
        $rootScope.showLoading = function() {
            $rootScope.loading = true;
        };

        // Hide loading overlay
        $rootScope.hideLoading = function() {
            $timeout(function() {
                $rootScope.loading = false;
            }, 300);
        };

        // Show error message
        $rootScope.showError = function(message) {
            $rootScope.errorMessage = message;
            $('#errorModal').modal('show');
        };

        // Hide error message
        $rootScope.hideError = function() {
            $('#errorModal').modal('hide');
            $rootScope.errorMessage = '';
        };

        // Route change events
        $rootScope.$on('$routeChangeStart', function() {
            $rootScope.showLoading();
        });

        $rootScope.$on('$routeChangeSuccess', function() {
            $rootScope.hideLoading();
        });

        $rootScope.$on('$routeChangeError', function(event, current, previous, rejection) {
            $rootScope.hideLoading();
            $rootScope.showError('Failed to load page. Please try again.');
        });

        // Page title management
        $rootScope.pageTitle = 'Church TV';
        $rootScope.setPageTitle = function(title) {
            $rootScope.pageTitle = title ? title + ' - Church TV' : 'Church TV';
            document.title = $rootScope.pageTitle;
        };

        // User preferences (localStorage)
        $rootScope.userPrefs = {
            autoplay: false,
            quality: 'auto',
            volume: 80
        };

        // Load user preferences
        var savedPrefs = localStorage.getItem('churchtv_prefs');
        if (savedPrefs) {
            try {
                $rootScope.userPrefs = angular.extend($rootScope.userPrefs, JSON.parse(savedPrefs));
            } catch (e) {
                console.warn('Failed to load user preferences:', e);
            }
        }

        // Save user preferences
        $rootScope.saveUserPrefs = function() {
            localStorage.setItem('churchtv_prefs', JSON.stringify($rootScope.userPrefs));
        };

        // Watch for preference changes
        $rootScope.$watch('userPrefs', function(newPrefs, oldPrefs) {
            if (newPrefs !== oldPrefs) {
                $rootScope.saveUserPrefs();
            }
        }, true);

        // Initialize application
        console.log('Church TV Application initialized');
        console.log('Church TV API base:', API_BASE);

        // Initialize push notifications
        PushService.initialize();

        // Initialize analytics tracking
        AnalyticsService.initialize();
    }]);

})();