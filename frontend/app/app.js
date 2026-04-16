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

            // Angular 1.6+ defaults to hashPrefix('!') which produces URLs like "#!/".
            // Our templates use "#/..." links, so force empty prefix to avoid "#!/#%2F..." double-hash URLs.
            $locationProvider.hashPrefix('');

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

                            // Skip automatic logout for auth verification requests
                            if (rejection.config.headers && rejection.config.headers['X-Auth-Verification']) {
                                console.log('Skipping auto-logout for auth verification request');
                                return $q.reject(rejection);
                            }

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
                .when('/leadership', {
                    templateUrl: 'app/views/pages/leadership.html',
                    controller: 'LeadershipController',
                    controllerAs: 'vm',
                    resolve: {
                        auth: ['$q', 'AuthService', '$location', function($q, AuthService, $location) {
                            if (!AuthService.isAuthenticated()) {
                                $location.path('/login');
                                return $q.reject('Not authenticated');
                            }
                            if (!AuthService.isLeader()) {
                                $location.path('/');
                                return $q.reject('Access denied');
                            }
                            return $q.resolve();
                        }]
                    }
                })
                .when('/donate', {
                    templateUrl: 'app/views/pages/donate.html',
                    controller: 'DonationController',
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
                .when('/reset-password', {
                    templateUrl: 'app/views/pages/reset-password.html',
                    controller: 'ResetPasswordController',
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
                .when('/admin/channel-sync', {
                    templateUrl: 'app/views/pages/admin-channel-sync.html',
                    controller: 'ChannelSyncController',
                    controllerAs: 'vm'
                })
                .when('/admin/donations', {
                    templateUrl: 'app/views/pages/admin-donations.html',
                    controller: 'AdminDonationsController',
                    controllerAs: 'vm'
                })

                .when('/watchlater', {
                    templateUrl: 'app/views/pages/favorites.html',
                    controller: 'FavoritesController',
                    controllerAs: 'vm'
                })
                .when('/404', {
                    templateUrl: 'app/views/pages/404.html',
                    controller: 'NotFoundController',
                    controllerAs: 'vm'
                })
                .otherwise({
                    redirectTo: '/404'
                });

            // Enable HTML5 mode for cleaner URLs (requires server configuration)
            // $locationProvider.html5Mode(true);
        }])

        // Backend API URL. Automatically detected based on project folder name.
        .constant('API_BASE', (function() {
            var path = window.location.pathname;
            var segments = path.split('/');
            var frontendIndex = segments.indexOf('frontend');
            
            // Try to find the folder containing "frontend"
            var projectBase = '/'; // Default root
            
            if (frontendIndex > 0) {
                // Join all segments before "frontend" to get the absolute base path
                // For /LCMTVWebNew/frontend/ -> segments are ['', 'LCMTVWebNew', 'frontend', ...]
                // slice(0, 2) -> ['', 'LCMTVWebNew'], join('/') -> '/LCMTVWebNew'
                projectBase = segments.slice(0, frontendIndex).join('/') + '/';
            } else if (segments.length > 1 && segments[1] !== '') {
                // Fallback for cases where "frontend" isn't in URL but there's a subfolder
                projectBase = '/' + segments[1] + '/';
            }
            
            // Normalize slashes: ensure it starts with / and ends with / (unless just /)
            projectBase = projectBase.replace(/\/+/g, '/');
            if (projectBase.charAt(0) !== '/') projectBase = '/' + projectBase;
            if (projectBase.length > 1 && projectBase.charAt(projectBase.length - 1) !== '/') projectBase += '/';
            
            // Build the full API path
            var apiPath = projectBase + 'backend/api';
            // Final safety check to ensure it's still absolute
            if (apiPath.charAt(0) !== '/') apiPath = '/' + apiPath;
            
            console.log('LCMTV: Detected API Base Path:', apiPath);
            return apiPath;
        })())
        .constant('YOUTUBE_API_KEY', 'AIzaSyDT1-_m5WdXEAPDd8J-WuPAGrmKgdMeqeY')
        .constant('APP_CONFIG', {
            title: 'LCM TV',
            description: 'Modern church streaming platform',
            version: '1.0.0',
            maxVideosPerPage: 24,
            maxSearchResults: 50
        })

        // Root scope configuration
        .run(['$rootScope', '$location', '$timeout', 'API_BASE', 'PushService', 'AnalyticsService', 'I18nService', function($rootScope, $location, $timeout, API_BASE, PushService, AnalyticsService, I18nService) {

            // Global loading state
            $rootScope.loading = false;

            // Global error handling
            $rootScope.errorMessage = '';

            // Show loading overlay
            $rootScope.showLoading = function() {
                $rootScope.loading = true;
                
                // Safety timeout: Auto-hide after 8 seconds if something hangs
                $timeout(function() {
                    if ($rootScope.loading) {
                        console.warn('Loading safety timeout reached. Hiding overlay.');
                        $rootScope.loading = false;
                        // Avoid spamming toast on initial load if we're on a path that likely failed
                        if ($location.path() !== '/' && $location.path() !== '') {
                            $rootScope.showToast('Loading is taking longer than usual...', 'warning');
                        }
                    }
                }, 8000);
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

            // Toast notification function
            $rootScope.showToast = function(message, type) {
                type = type || 'info';

                // Create toast element
                var toast = document.createElement('div');
                toast.className = 'toast toast-' + type;
                toast.innerHTML = '<span>' + message + '</span>';

                // Add to page
                var container = document.querySelector('.toast-container') || document.body;
                container.appendChild(toast);

                // Auto remove after 3 seconds
                setTimeout(function() {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 3000);
            };

            // Route change events
            $rootScope.$on('$routeChangeStart', function() {
                $rootScope.showLoading();
            });

            $rootScope.$on('$routeChangeSuccess', function(event, current, previous) {
                $rootScope.hideLoading();

                // Ensure each new route starts at the top of the page
                $timeout(function() {
                    window.scrollTo(0, 0);
                }, 0);
            });

            $rootScope.$on('$routeChangeError', function(event, current, previous, rejection) {
                $rootScope.hideLoading();
                $rootScope.showError('Failed to load page. Please try again.');
            });

            // Page title management
            $rootScope.pageTitle = 'Church TV';
            $rootScope.setPageTitle = function(title) {
                $rootScope.pageTitle = title ? title + ' - LCM TV' : 'LCM TV';
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

            // Initialize internationalization
            I18nService.initialize();

            // Hide splash screen after initialization
            $timeout(function() {
                var splash = document.getElementById('splash-screen');
                if (splash) {
                    splash.classList.add('fade-out');
                    // Remove from DOM after transition
                    $timeout(function() {
                        if (splash.parentNode) {
                            splash.parentNode.removeChild(splash);
                        }
                    }, 500);
                }
            }, 1500); // Show splash for 1.5 seconds
        }]);

    })();