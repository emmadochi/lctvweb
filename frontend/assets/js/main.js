/**
 * Church TV Main JavaScript
 * Custom JavaScript functionality for the application
 */

(function() {
    'use strict';

    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Church TV Application Loaded');

        // Add any custom JavaScript functionality here
        initializeCustomFeatures();
    });

    /**
     * Initialize custom features
     */
    function initializeCustomFeatures() {
        // Register service worker for PWA
        registerServiceWorker();

        // Add PWA install prompt
        initializeInstallPrompt();

        // Add smooth scrolling for anchor links
        initializeSmoothScrolling();

        // Initialize tooltips if needed
        initializeTooltips();

        // Handle mobile menu if needed
        initializeMobileMenu();

        // Add keyboard shortcuts
        initializeKeyboardShortcuts();

        // Initialize analytics tracking
        initializeAnalyticsTracking();
    }

    /**
     * Register service worker for PWA functionality
     */
    function registerServiceWorker() {
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/sw.js')
                    .then(function(registration) {
                        console.log('Service Worker registered successfully:', registration.scope);

                        // Handle service worker updates
                        registration.addEventListener('updatefound', function() {
                            const newWorker = registration.installing;
                            newWorker.addEventListener('statechange', function() {
                                if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                    // New version available
                                    showUpdateNotification();
                                }
                            });
                        });

                        // Check for updates periodically
                        setInterval(function() {
                            registration.update();
                        }, 60 * 60 * 1000); // Check every hour

                    })
                    .catch(function(error) {
                        console.error('Service Worker registration failed:', error);
                    });
            });
        }
    }

    /**
     * Show update notification when new service worker is available
     */
    function showUpdateNotification() {
        // Create update notification
        const updateToast = document.createElement('div');
        updateToast.className = 'update-notification';
        updateToast.innerHTML = `
            <div class="update-content">
                <span>A new version of LCMTV is available!</span>
                <button class="update-btn" onclick="window.location.reload()">Update Now</button>
            </div>
        `;

        updateToast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #6a0dad;
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            z-index: 10000;
            font-family: Arial, sans-serif;
            max-width: 300px;
        `;

        document.body.appendChild(updateToast);

        // Auto remove after 10 seconds
        setTimeout(function() {
            if (updateToast.parentNode) {
                updateToast.parentNode.removeChild(updateToast);
            }
        }, 10000);
    }

    /**
     * Initialize PWA install prompt
     */
    function initializeInstallPrompt() {
        let deferredPrompt;

        window.addEventListener('beforeinstallprompt', function(e) {
            // Prevent the default prompt
            e.preventDefault();
            deferredPrompt = e;

            // Show custom install button
            showInstallPrompt();
        });

        // Handle successful installation
        window.addEventListener('appinstalled', function() {
            console.log('PWA was installed');
            hideInstallPrompt();

            // Track installation
            if (typeof gtag !== 'undefined') {
                gtag('event', 'pwa_install', {
                    event_category: 'engagement',
                    event_label: 'pwa_install'
                });
            }
        });

        function showInstallPrompt() {
            // Create install prompt
            const installPrompt = document.createElement('div');
            installPrompt.id = 'install-prompt';
            installPrompt.innerHTML = `
                <div class="install-content">
                    <div class="install-icon">📺</div>
                    <div class="install-text">
                        <strong>Install LCMTV</strong>
                        <span>Get the full app experience</span>
                    </div>
                    <div class="install-actions">
                        <button class="install-btn" onclick="installPWA()">Install</button>
                        <button class="dismiss-btn" onclick="dismissInstallPrompt()">Not Now</button>
                    </div>
                </div>
            `;

            installPrompt.style.cssText = `
                position: fixed;
                bottom: 20px;
                left: 20px;
                right: 20px;
                background: white;
                border-radius: 12px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.2);
                z-index: 10000;
                max-width: 400px;
                margin: 0 auto;
                font-family: Arial, sans-serif;
                display: none;
            `;

            // Add CSS for install prompt
            const style = document.createElement('style');
            style.textContent = `
                .install-content {
                    display: flex;
                    align-items: center;
                    padding: 16px;
                    gap: 12px;
                }
                .install-icon {
                    font-size: 24px;
                    background: #6a0dad;
                    color: white;
                    width: 48px;
                    height: 48px;
                    border-radius: 12px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .install-text {
                    flex: 1;
                }
                .install-text strong {
                    display: block;
                    color: #333;
                    font-size: 16px;
                }
                .install-text span {
                    display: block;
                    color: #666;
                    font-size: 14px;
                }
                .install-actions {
                    display: flex;
                    gap: 8px;
                }
                .install-btn {
                    background: #6a0dad;
                    color: white;
                    border: none;
                    padding: 8px 16px;
                    border-radius: 6px;
                    font-size: 14px;
                    cursor: pointer;
                }
                .dismiss-btn {
                    background: #f5f5f5;
                    color: #666;
                    border: none;
                    padding: 8px 16px;
                    border-radius: 6px;
                    font-size: 14px;
                    cursor: pointer;
                }
                @media (max-width: 480px) {
                    .install-content {
                        flex-direction: column;
                        text-align: center;
                    }
                    .install-actions {
                        width: 100%;
                    }
                    .install-btn, .dismiss-btn {
                        flex: 1;
                    }
                }
            `;
            document.head.appendChild(style);
            document.body.appendChild(installPrompt);

            // Show with animation
            setTimeout(() => {
                installPrompt.style.display = 'block';
                installPrompt.style.animation = 'slideUp 0.3s ease-out';
            }, 1000);
        }

        function hideInstallPrompt() {
            const prompt = document.getElementById('install-prompt');
            if (prompt) {
                prompt.style.animation = 'slideDown 0.3s ease-in';
                setTimeout(() => {
                    if (prompt.parentNode) {
                        prompt.parentNode.removeChild(prompt);
                    }
                }, 300);
            }
        }

        // Global functions for button clicks
        window.installPWA = function() {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then(function(choiceResult) {
                    if (choiceResult.outcome === 'accepted') {
                        console.log('User accepted the install prompt');
                    } else {
                        console.log('User dismissed the install prompt');
                    }
                    deferredPrompt = null;
                    hideInstallPrompt();
                });
            }
        };

        window.dismissInstallPrompt = function() {
            hideInstallPrompt();
            // Remember user's choice for 30 days
            localStorage.setItem('installPromptDismissed', Date.now().toString());
        };

        // Check if user previously dismissed
        const dismissedTime = localStorage.getItem('installPromptDismissed');
        if (dismissedTime) {
            const daysSinceDismissed = (Date.now() - parseInt(dismissedTime)) / (1000 * 60 * 60 * 24);
            if (daysSinceDismissed < 30) {
                return; // Don't show again for 30 days
            }
        }
    }

    /**
     * Smooth scrolling for anchor links
     */
    function initializeSmoothScrolling() {
        var anchorLinks = document.querySelectorAll('a[href^="#"]');

        anchorLinks.forEach(function(link) {
            link.addEventListener('click', function(e) {
                var targetId = this.getAttribute('href').substring(1);
                var targetElement = document.getElementById(targetId);

                if (targetElement) {
                    e.preventDefault();
                    targetElement.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    }

    /**
     * Initialize Bootstrap tooltips
     */
    function initializeTooltips() {
        // Initialize tooltips if Bootstrap is loaded
        if (typeof $ !== 'undefined' && $.fn.tooltip) {
            $('[data-toggle="tooltip"]').tooltip();
        }
    }

    /**
     * Initialize mobile menu (if needed)
     */
    function initializeMobileMenu() {
        // Add mobile menu toggle functionality if required
        var navbarToggle = document.querySelector('.navbar-toggle');
        var navbarCollapse = document.querySelector('.navbar-collapse');

        if (navbarToggle && navbarCollapse) {
            navbarToggle.addEventListener('click', function() {
                navbarCollapse.classList.toggle('in');
            });
        }
    }

    /**
     * Initialize keyboard shortcuts
     */
    function initializeKeyboardShortcuts() {
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + K for search
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                var searchInput = document.querySelector('.search-bar input');
                if (searchInput) {
                    searchInput.focus();
                }
            }

            // Escape key to close modals
            if (e.key === 'Escape') {
                $('.modal').modal('hide');
            }
        });
    }

    /**
     * Utility functions
     */
    window.ChurchTV = {
        // Format file size
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            var k = 1024;
            var sizes = ['Bytes', 'KB', 'MB', 'GB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },

        // Copy to clipboard
        copyToClipboard: function(text) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function() {
                    console.log('Copied to clipboard');
                });
            } else {
                // Fallback for older browsers
                var textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
            }
        },

        // Show toast notification
        showToast: function(message, type) {
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
        }
    };

    /**
     * Error handling
     */
    window.addEventListener('error', function(e) {
        console.error('JavaScript Error:', e.error);
        // Could send to error reporting service
    });

    window.addEventListener('unhandledrejection', function(e) {
        console.error('Unhandled Promise Rejection:', e.reason);
        // Could send to error reporting service
    });

    /**
     * Initialize analytics tracking for video events
     */
    function initializeAnalyticsTracking() {
        // Wait for Angular to be ready
        if (typeof angular !== 'undefined') {
            // Use a timeout to ensure Angular is fully loaded
            setTimeout(function() {
                setupVideoAnalyticsTracking();
                setupSearchAnalyticsTracking();
                setupSocialAnalyticsTracking();
            }, 2000);
        }
    }

    /**
     * Set up video analytics tracking
     */
    function setupVideoAnalyticsTracking() {
        try {
            var injector = angular.element(document.body).injector();
            if (injector) {
                var AnalyticsService = injector.get('AnalyticsService');

                // Track YouTube player events if available
                if (typeof YT !== 'undefined' && YT.Player) {
                    // Override the onPlayerReady function to add tracking
                    var originalOnPlayerReady = window.onPlayerReady;
                    window.onPlayerReady = function(event) {
                        if (originalOnPlayerReady) {
                            originalOnPlayerReady(event);
                        }

                        // Track video start
                        var videoData = getCurrentVideoData();
                        if (videoData && AnalyticsService) {
                            AnalyticsService.trackVideoView(videoData);
                        }

                        // Add progress tracking
                        var player = event.target;
                        var progressInterval = setInterval(function() {
                            if (player && player.getCurrentTime && player.getDuration) {
                                var currentTime = player.getCurrentTime();
                                var duration = player.getDuration();

                                if (duration > 0) {
                                    AnalyticsService.trackVideoProgress({
                                        currentTime: currentTime,
                                        totalDuration: duration,
                                        quality: player.getPlaybackQuality ? player.getPlaybackQuality() : 'unknown'
                                    });
                                }
                            }
                        }, 30000); // Track every 30 seconds

                        // Clear interval when video ends
                        player.addEventListener('onStateChange', function(state) {
                            if (state.data === YT.PlayerState.ENDED) {
                                clearInterval(progressInterval);
                                if (AnalyticsService) {
                                    AnalyticsService.trackVideoCompletion();
                                }
                            }
                        });
                    };
                }
            }
        } catch (e) {
            console.warn('Analytics tracking setup failed:', e);
        }
    }

    /**
     * Set up search analytics tracking
     */
    function setupSearchAnalyticsTracking() {
        try {
            var injector = angular.element(document.body).injector();
            if (injector) {
                var AnalyticsService = injector.get('AnalyticsService');

                // Track search form submissions
                var searchForms = document.querySelectorAll('form[action*="search"], form[action*="query"]');
                searchForms.forEach(function(form) {
                    form.addEventListener('submit', function(e) {
                        var searchInput = form.querySelector('input[name="q"], input[name="query"], input[name="search"]');
                        if (searchInput && searchInput.value && AnalyticsService) {
                            AnalyticsService.trackSearch(searchInput.value.trim());
                        }
                    });
                });
            }
        } catch (e) {
            console.warn('Search analytics setup failed:', e);
        }
    }

    /**
     * Set up social interaction analytics tracking
     */
    function setupSocialAnalyticsTracking() {
        try {
            var injector = angular.element(document.body).injector();
            if (injector) {
                var AnalyticsService = injector.get('AnalyticsService');

                // Track like/reaction buttons
                document.addEventListener('click', function(e) {
                    var target = e.target.closest('[data-action]');
                    if (target && AnalyticsService) {
                        var action = target.getAttribute('data-action');
                        var contentType = target.getAttribute('data-type') || 'video';
                        var contentId = target.getAttribute('data-id');

                        if (action && contentId) {
                            AnalyticsService.trackSocialInteraction(action, contentType, contentId);
                        }
                    }
                });

                // Track share buttons
                document.addEventListener('click', function(e) {
                    var target = e.target.closest('.share-btn, [data-share]');
                    if (target && AnalyticsService) {
                        var shareType = target.getAttribute('data-share') || 'unknown';
                        var contentId = target.getAttribute('data-id');

                        if (contentId) {
                            AnalyticsService.trackSocialInteraction('share', 'video', contentId, { share_type: shareType });
                        }
                    }
                });
            }
        } catch (e) {
            console.warn('Social analytics setup failed:', e);
        }
    }

    /**
     * Get current video data for tracking
     */
    function getCurrentVideoData() {
        try {
            // Try to get video data from Angular scope
            var scope = angular.element(document.querySelector('[ng-controller]')).scope();
            if (scope && scope.vm && scope.vm.video) {
                return {
                    id: scope.vm.video.id,
                    title: scope.vm.video.title,
                    youtube_id: scope.vm.video.youtube_id,
                    duration: scope.vm.video.duration
                };
            }

            // Fallback: try to extract from URL or page data
            var urlMatch = window.location.pathname.match(/\/video\/(\d+)/);
            if (urlMatch) {
                return {
                    id: urlMatch[1],
                    title: document.title,
                    youtube_id: null,
                    duration: null
                };
            }
        } catch (e) {
            console.warn('Could not get video data for analytics:', e);
        }

        return null;
    }

    /**
     * Track custom events from external scripts
     */
    window.trackAnalyticsEvent = function(eventType, eventData) {
        try {
            var injector = angular.element(document.body).injector();
            if (injector) {
                var AnalyticsService = injector.get('AnalyticsService');
                if (AnalyticsService && AnalyticsService.trackEvent) {
                    AnalyticsService.trackEvent(eventType, eventData);
                }
            }
        } catch (e) {
            console.warn('Custom analytics event tracking failed:', e);
        }
    };

})();