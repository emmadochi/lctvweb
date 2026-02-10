/**
 * Analytics Service
 * Handles client-side analytics tracking and reporting
 */

angular.module('ChurchTVApp').factory('AnalyticsService', ['$http', 'API_BASE', '$window', '$location', '$rootScope', function($http, API_BASE, $window, $location, $rootScope) {
    var service = {};

    // Configuration
    var config = {
        sessionId: null,
        userId: null,
        pageStartTime: null,
        videoStartTime: null,
        currentVideo: null,
        trackingEnabled: true,
        batchSize: 10,
        batchInterval: 30000, // 30 seconds
        eventsQueue: []
    };

    // Initialize analytics tracking
    service.initialize = function() {
        console.log('AnalyticsService: Initializing...');

        // Generate or retrieve session ID
        config.sessionId = service.getSessionId();

        // Get user ID if authenticated
        try {
            var user = JSON.parse(localStorage.getItem('currentUser'));
            if (user && user.id) {
                config.userId = user.id;
            }
        } catch (e) {
            console.warn('AnalyticsService: Could not parse user data', e);
        }

        // Track initial page view
        service.trackPageView();

        // Set up automatic tracking
        service.setupAutomaticTracking();

        // Start batch processing
        service.startBatchProcessing();

        console.log('AnalyticsService: Initialized with session ID:', config.sessionId);
    };

    // Generate or retrieve session ID
    service.getSessionId = function() {
        var sessionId = sessionStorage.getItem('analytics_session_id');
        if (!sessionId) {
            sessionId = 'session_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            sessionStorage.setItem('analytics_session_id', sessionId);
        }
        return sessionId;
    };

    // Set up automatic tracking
    service.setupAutomaticTracking = function() {
        // Track route changes
        $rootScope.$on('$routeChangeSuccess', function(event, current, previous) {
            if (current && current.$$route) {
                service.trackPageView({
                    page_url: $location.path(),
                    page_title: current.$$route.title || document.title
                });
            }
        });

        // Track before unload (page exit)
        $window.addEventListener('beforeunload', function() {
            service.trackPageExit();
        });

        // Track visibility changes (tab switching)
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                service.trackPageExit();
            } else {
                service.trackPageView({ page_url: $location.path() });
            }
        });

        // Track user engagement (clicks, scrolls)
        service.trackUserEngagement();
    };

    // Track page view
    service.trackPageView = function(options) {
        if (!config.trackingEnabled) return;

        config.pageStartTime = Date.now();

        var data = {
            session_id: config.sessionId,
            user_id: config.userId,
            page_url: options ? options.page_url : $location.path(),
            page_title: options ? options.page_title : document.title,
            referrer: document.referrer,
            user_agent: navigator.userAgent,
            screen_resolution: $window.screen.width + 'x' + $window.screen.height,
            viewport_size: $window.innerWidth + 'x' + $window.innerHeight,
            timestamp: new Date().toISOString()
        };

        // Geolocation (optional)
        // IMPORTANT:
        // - Do NOT repeatedly prompt users for location just for analytics.
        // - Only request location if permission is already granted.
        // - If blocked/denied/unavailable, silently continue without location.
        function sendWithoutLocation() {
            service.sendEvent('page_view', data);
        }

        function attachAndSend(position) {
            data.latitude = position.coords.latitude;
            data.longitude = position.coords.longitude;
            service.sendEvent('page_view', data);
        }

        // Some deployments explicitly disable geolocation via Permissions-Policy headers.
        // In that case, any call will fail; skip entirely.
        if (!$window.navigator || !$window.navigator.geolocation) {
            return sendWithoutLocation();
        }

        // Use Permissions API when available to avoid prompting.
        if ($window.navigator.permissions && typeof $window.navigator.permissions.query === 'function') {
            $window.navigator.permissions.query({ name: 'geolocation' }).then(function(result) {
                // Only fetch location if already granted.
                if (result && result.state === 'granted') {
                    $window.navigator.geolocation.getCurrentPosition(
                        attachAndSend,
                        sendWithoutLocation,
                        { enableHighAccuracy: false, timeout: 2000, maximumAge: 5 * 60 * 1000 }
                    );
                } else {
                    sendWithoutLocation();
                }
            }).catch(function() {
                // If permissions query fails, fall back to no-location (avoid prompting).
                sendWithoutLocation();
            });
            return;
        }

        // No Permissions API support: don't prompt; just send without location.
        return sendWithoutLocation();
    };

    // Track page exit
    service.trackPageExit = function() {
        if (!config.pageStartTime) return;

        var timeSpent = Math.round((Date.now() - config.pageStartTime) / 1000);

        service.trackEvent('page_exit', {
            time_spent: timeSpent,
            page_url: $location.path()
        });

        config.pageStartTime = null;
    };

    // Track video view
    service.trackVideoView = function(videoData) {
        if (!config.trackingEnabled || !videoData) return;

        config.videoStartTime = Date.now();
        config.currentVideo = videoData;

        var data = {
            session_id: config.sessionId,
            user_id: config.userId,
            video_id: videoData.id,
            video_title: videoData.title,
            youtube_id: videoData.youtube_id,
            total_duration: videoData.duration || 0,
            referrer: $location.path(),
            device_type: service.getDeviceType(),
            timestamp: new Date().toISOString()
        };

        service.sendEvent('video_view', data);
    };

    // Track video progress
    service.trackVideoProgress = function(progressData) {
        if (!config.currentVideo || !config.videoStartTime) return;

        var watchDuration = Math.round((Date.now() - config.videoStartTime) / 1000);
        var completionRate = progressData.totalDuration > 0 ?
            Math.round((progressData.currentTime / progressData.totalDuration) * 100) : 0;

        service.trackEvent('video_progress', {
            video_id: config.currentVideo.id,
            watch_duration: watchDuration,
            completion_rate: completionRate,
            current_time: progressData.currentTime,
            total_duration: progressData.totalDuration,
            quality: progressData.quality || 'auto'
        });
    };

    // Track video completion
    service.trackVideoCompletion = function() {
        if (!config.currentVideo) return;

        var watchDuration = Math.round((Date.now() - config.videoStartTime) / 1000);

        service.trackEvent('video_complete', {
            video_id: config.currentVideo.id,
            total_watch_time: watchDuration,
            completion_rate: 100
        });

        config.videoStartTime = null;
        config.currentVideo = null;
    };

    // Track user engagement
    service.trackUserEngagement = function() {
        var lastActivity = Date.now();
        var activityEvents = ['mousedown', 'keydown', 'scroll', 'touchstart'];

        activityEvents.forEach(function(eventType) {
            document.addEventListener(eventType, service.debounce(function() {
                var timeSinceLastActivity = Date.now() - lastActivity;
                if (timeSinceLastActivity > 30000) { // 30 seconds
                    service.trackEvent('user_engagement', {
                        engagement_type: eventType,
                        page_url: $location.path(),
                        time_since_last_activity: timeSinceLastActivity
                    });
                }
                lastActivity = Date.now();
            }, 1000));
        });
    };

    // Track social interactions
    service.trackSocialInteraction = function(action, contentType, contentId, metadata) {
        service.trackEvent('social_interaction', {
            action: action, // like, comment, share, favorite, etc.
            content_type: contentType, // video, livestream, comment
            content_id: contentId,
            metadata: metadata || {},
            page_url: $location.path()
        });
    };

    // Track search
    service.trackSearch = function(query, resultsCount, filters) {
        service.trackEvent('search', {
            query: query,
            results_count: resultsCount,
            filters: filters || {},
            page_url: $location.path()
        });
    };

    // Track content engagement
    service.trackContentEngagement = function(action, contentType, contentId, value) {
        service.trackEvent('content_engagement', {
            action: action,
            content_type: contentType,
            content_id: contentId,
            action_value: value,
            page_url: $location.path()
        });
    };

    // Generic event tracking
    service.trackEvent = function(eventType, eventData) {
        if (!config.trackingEnabled) return;

        var data = angular.extend({
            session_id: config.sessionId,
            user_id: config.userId,
            event_type: eventType,
            timestamp: new Date().toISOString(),
            page_url: $location.path(),
            user_agent: navigator.userAgent
        }, eventData);

        service.sendEvent(eventType, data);
    };

    // Send event to server
    service.sendEvent = function(eventType, data) {
        // Add to queue for batch processing
        config.eventsQueue.push({
            type: eventType,
            data: data,
            timestamp: Date.now()
        });

        // Process immediately for critical events
        if (['video_view', 'page_view'].includes(eventType)) {
            service.processBatch();
        }
    };

    // Start batch processing
    service.startBatchProcessing = function() {
        setInterval(function() {
            service.processBatch();
        }, config.batchInterval);
    };

    // Process batch of events
    service.processBatch = function() {
        if (config.eventsQueue.length === 0) return;

        var eventsToSend = config.eventsQueue.splice(0, config.batchSize);

        // Group events by type for API calls
        var eventsByType = {};
        eventsToSend.forEach(function(event) {
            if (!eventsByType[event.type]) {
                eventsByType[event.type] = [];
            }
            eventsByType[event.type].push(event.data);
        });

        // Send batched events
        Object.keys(eventsByType).forEach(function(eventType) {
            var endpoint = service.getEndpointForEventType(eventType);
            var payload = eventsByType[eventType];

            if (endpoint && payload.length > 0) {
                service.sendToAPI(endpoint, payload);
            }
        });
    };

    // Get API endpoint for event type
    service.getEndpointForEventType = function(eventType) {
        var endpoints = {
            'page_view': '/analytics/page-view',
            'video_view': '/analytics/video-view',
            'video_progress': '/analytics/video-view',
            'video_complete': '/analytics/video-view',
            'social_interaction': '/analytics/engagement',
            'content_engagement': '/analytics/engagement',
            'user_engagement': '/analytics/engagement',
            'search': '/analytics/engagement'
        };

        return endpoints[eventType] || '/analytics/engagement';
    };

    // Send data to API
    service.sendToAPI = function(endpoint, data) {
        // Handle single event vs batch
        var payload = Array.isArray(data) && data.length === 1 ? data[0] : data;
        var isBatch = Array.isArray(data) && data.length > 1;

        $http({
            method: 'POST',
            url: API_BASE + endpoint,
            data: payload,
            headers: {
                'Content-Type': 'application/json'
            }
        }).then(function(response) {
            if (isBatch) {
                console.log('AnalyticsService: Sent ' + data.length + ' ' + endpoint + ' events');
            }
        }).catch(function(error) {
            console.warn('AnalyticsService: Failed to send ' + endpoint + ' event:', error);
            // Re-queue failed events
            if (Array.isArray(data)) {
                config.eventsQueue.unshift.apply(config.eventsQueue, data.map(function(item) {
                    return { type: endpoint.split('/').pop(), data: item };
                }));
            }
        });
    };

    // Get device type
    service.getDeviceType = function() {
        var ua = navigator.userAgent;
        if (/(tablet|ipad|playbook|silk)|(android(?!.*mobi))/i.test(ua)) {
            return 'tablet';
        }
        if (/Mobile|Android|iP(hone|od)|IEMobile|BlackBerry|Kindle|Silk-Accelerated|(hpw|web)OS|Opera M(obi|ini)/.test(ua)) {
            return 'mobile';
        }
        return 'desktop';
    };

    // Get browser info
    service.getBrowserInfo = function() {
        var ua = navigator.userAgent;
        var browser = 'unknown';

        if (ua.indexOf('Chrome') > -1) browser = 'Chrome';
        else if (ua.indexOf('Safari') > -1) browser = 'Safari';
        else if (ua.indexOf('Firefox') > -1) browser = 'Firefox';
        else if (ua.indexOf('MSIE') > -1 || ua.indexOf('Trident/') > -1) browser = 'Internet Explorer';
        else if (ua.indexOf('Edge') > -1) browser = 'Edge';

        return browser;
    };

    // Get OS info
    service.getOSInfo = function() {
        var ua = navigator.userAgent;
        var os = 'unknown';

        if (ua.indexOf('Windows NT') > -1) os = 'Windows';
        else if (ua.indexOf('Mac OS X') > -1) os = 'macOS';
        else if (ua.indexOf('Linux') > -1) os = 'Linux';
        else if (ua.indexOf('Android') > -1) os = 'Android';
        else if (ua.indexOf('iPhone') > -1 || ua.indexOf('iPad') > -1) os = 'iOS';

        return os;
    };

    // Utility: Debounce function
    service.debounce = function(func, wait) {
        var timeout;
        return function executedFunction() {
            var context = this;
            var args = arguments;
            var later = function() {
                timeout = null;
                func.apply(context, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    };

    // Enable/disable tracking
    service.setTrackingEnabled = function(enabled) {
        config.trackingEnabled = enabled;
        console.log('AnalyticsService: Tracking ' + (enabled ? 'enabled' : 'disabled'));
    };

    // Get current session info
    service.getSessionInfo = function() {
        return {
            sessionId: config.sessionId,
            userId: config.userId,
            trackingEnabled: config.trackingEnabled,
            queueLength: config.eventsQueue.length
        };
    };

    return service;
}]);