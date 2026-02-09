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
        // Add install prompt animations
        addInstallAnimations();

        // Add PWA install prompt FIRST (before service worker)
        initializeInstallPrompt();

        // Register service worker for PWA
        registerServiceWorker();

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
                navigator.serviceWorker.register('/LCMTVWebNew/frontend/sw.js?v=1.1')
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
     * Initialize PWA install prompt - Mobile Optimized
     */
    function initializeInstallPrompt() {
        let deferredPrompt;
        let installPromptShown = false;

        // Detect if we're on a mobile device
        const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) ||
                        (window.innerWidth <= 768 && window.innerHeight > window.innerWidth);

        // Detect if we're in standalone mode (already installed)
        const isStandalone = window.matchMedia('(display-mode: standalone)').matches ||
                           window.navigator.standalone === true ||
                           document.referrer.includes('android-app://');

        // Don't show if already installed
        if (isStandalone) {
            console.log('App is already installed, skipping install prompt');
            return;
        }

        window.addEventListener('beforeinstallprompt', function(e) {
            console.log('beforeinstallprompt event fired');

            // Prevent Chrome's default mini-infobar
            e.preventDefault();

            // Store the event for later use
            deferredPrompt = e;

            // Show custom install prompt after user engagement
            showInstallPromptWhenReady();
        });

        // Handle successful installation
        window.addEventListener('appinstalled', function() {
            console.log('PWA was installed successfully');
            hideInstallPrompt();

            // Track installation
            if (typeof gtag !== 'undefined') {
                gtag('event', 'pwa_install', {
                    event_category: 'engagement',
                    event_label: 'pwa_install',
                    custom_map: {'metric1': 'mobile_install'}
                });
            }
        });

        function showInstallPromptWhenReady() {
            // Wait for user engagement before showing prompt
            let userEngaged = false;

            // Consider user engaged after scroll, click, or time
            const engagementEvents = ['scroll', 'click', 'touchstart', 'keydown'];

            function markEngaged() {
                userEngaged = true;
                engagementEvents.forEach(event => {
                    document.removeEventListener(event, markEngaged, { passive: true });
                });

                // Show prompt after engagement
                setTimeout(() => {
                    if (!installPromptShown && deferredPrompt) {
                        showInstallPrompt();
                    }
                }, isMobile ? 2000 : 3000); // Shorter delay on mobile
            }

            // Add engagement listeners
            engagementEvents.forEach(event => {
                document.addEventListener(event, markEngaged, { passive: true, once: true });
            });

            // Fallback: show after 5 seconds if no engagement
            setTimeout(() => {
                if (!userEngaged && !installPromptShown && deferredPrompt) {
                    showInstallPrompt();
                }
            }, 5000);
        }

        function showInstallPrompt() {
            if (installPromptShown) return;

            installPromptShown = true;

            // Create install prompt with mobile-first design
            const installPrompt = document.createElement('div');
            installPrompt.id = 'install-prompt';

            const promptContent = isMobile ? `
                <div class="install-content mobile">
                    <div class="install-header">
                        <div class="install-icon">📺</div>
                        <div class="install-text">
                            <strong>Install LCMTV</strong>
                            <span>Add to home screen for the best experience</span>
                        </div>
                        <button class="close-btn" onclick="dismissInstallPrompt()">✕</button>
                    </div>
                    <div class="install-actions">
                        <button class="install-btn" onclick="installPWA()">Install App</button>
                    </div>
                    <div class="install-features">
                        <small>✓ Offline viewing  ✓ Push notifications  ✓ Full screen video</small>
                    </div>
                </div>
            ` : `
                <div class="install-content desktop">
                    <div class="install-icon">📺</div>
                    <div class="install-text">
                        <strong>Install LCMTV</strong>
                        <span>Get the full app experience with offline viewing</span>
                    </div>
                    <div class="install-actions">
                        <button class="install-btn" onclick="installPWA()">Install</button>
                        <button class="dismiss-btn" onclick="dismissInstallPrompt()">Not Now</button>
                    </div>
                </div>
            `;

            installPrompt.innerHTML = promptContent;

            // Mobile-optimized positioning and styling
            const bottomOffset = isMobile ? '100px' : '20px'; // Above mobile bottom nav
            const maxWidth = isMobile ? '95vw' : '400px';

            installPrompt.style.cssText = `
                position: fixed;
                bottom: ${bottomOffset};
                left: 50%;
                transform: translateX(-50%);
                background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 250, 252, 0.98) 100%);
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                border-radius: 16px;
                box-shadow:
                    0 8px 32px rgba(0, 0, 0, 0.12),
                    0 4px 16px rgba(0, 0, 0, 0.08),
                    0 0 0 1px rgba(255, 255, 255, 0.1);
                z-index: 10000;
                max-width: ${maxWidth};
                width: 100%;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                display: none;
                border: 1px solid rgba(148, 163, 184, 0.2);
            `;

            // Add comprehensive CSS for both mobile and desktop
            const style = document.createElement('style');
            style.textContent = `
                /* Desktop Styles */
                .install-content.desktop {
                    display: flex;
                    align-items: center;
                    padding: 16px 20px;
                    gap: 16px;
                }
                .install-content.desktop .install-icon {
                    font-size: 28px;
                    background: linear-gradient(135deg, #6366f1, #8b5cf6, #ec4899);
                    color: white;
                    width: 56px;
                    height: 56px;
                    border-radius: 16px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
                }
                .install-content.desktop .install-text {
                    flex: 1;
                }
                .install-content.desktop .install-text strong {
                    display: block;
                    color: #1e293b;
                    font-size: 18px;
                    font-weight: 700;
                    margin-bottom: 4px;
                }
                .install-content.desktop .install-text span {
                    display: block;
                    color: #64748b;
                    font-size: 14px;
                }
                .install-content.desktop .install-actions {
                    display: flex;
                    gap: 12px;
                }
                .install-content.desktop .install-btn {
                    background: linear-gradient(135deg, #6366f1, #8b5cf6);
                    color: white;
                    border: none;
                    padding: 12px 24px;
                    border-radius: 12px;
                    font-size: 14px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
                }
                .install-content.desktop .install-btn:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
                }
                .install-content.desktop .dismiss-btn {
                    background: rgba(241, 245, 249, 0.8);
                    color: #64748b;
                    border: 1px solid rgba(148, 163, 184, 0.2);
                    padding: 12px 20px;
                    border-radius: 12px;
                    font-size: 14px;
                    font-weight: 500;
                    cursor: pointer;
                    transition: all 0.3s ease;
                }
                .install-content.desktop .dismiss-btn:hover {
                    background: rgba(241, 245, 249, 1);
                    color: #475569;
                }

                /* Mobile Styles */
                .install-content.mobile {
                    padding: 20px;
                }
                .install-content.mobile .install-header {
                    display: flex;
                    align-items: flex-start;
                    gap: 16px;
                    margin-bottom: 16px;
                    position: relative;
                }
                .install-content.mobile .install-icon {
                    font-size: 32px;
                    background: linear-gradient(135deg, #6366f1, #8b5cf6, #ec4899);
                    color: white;
                    width: 64px;
                    height: 64px;
                    border-radius: 20px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    box-shadow: 0 4px 16px rgba(99, 102, 241, 0.3);
                    flex-shrink: 0;
                }
                .install-content.mobile .install-text {
                    flex: 1;
                    padding-top: 8px;
                }
                .install-content.mobile .install-text strong {
                    display: block;
                    color: #1e293b;
                    font-size: 20px;
                    font-weight: 700;
                    margin-bottom: 6px;
                    line-height: 1.2;
                }
                .install-content.mobile .install-text span {
                    display: block;
                    color: #64748b;
                    font-size: 16px;
                    line-height: 1.3;
                }
                .install-content.mobile .close-btn {
                    background: none;
                    border: none;
                    color: #94a3b8;
                    font-size: 20px;
                    cursor: pointer;
                    padding: 8px;
                    border-radius: 50%;
                    transition: all 0.3s ease;
                    width: 36px;
                    height: 36px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .install-content.mobile .close-btn:hover {
                    background: rgba(241, 245, 249, 0.8);
                    color: #475569;
                }
                .install-content.mobile .install-actions {
                    margin-bottom: 16px;
                }
                .install-content.mobile .install-btn {
                    width: 100%;
                    background: linear-gradient(135deg, #6366f1, #8b5cf6);
                    color: white;
                    border: none;
                    padding: 16px 24px;
                    border-radius: 16px;
                    font-size: 16px;
                    font-weight: 700;
                    cursor: pointer;
                    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                    box-shadow: 0 4px 16px rgba(99, 102, 241, 0.3);
                }
                .install-content.mobile .install-btn:active {
                    transform: scale(0.98);
                    box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3);
                }
                .install-content.mobile .install-features {
                    text-align: center;
                    color: #64748b;
                    font-size: 13px;
                    line-height: 1.4;
                }
                .install-content.mobile .install-features small {
                    color: #10b981;
                    font-weight: 500;
                }

                /* Animations */
                @keyframes slideUpInstall {
                    from {
                        opacity: 0;
                        transform: translateX(-50%) translateY(20px) scale(0.95);
                    }
                    to {
                        opacity: 1;
                        transform: translateX(-50%) translateY(0) scale(1);
                    }
                }

                @keyframes slideDownInstall {
                    from {
                        opacity: 1;
                        transform: translateX(-50%) translateY(0) scale(1);
                    }
                    to {
                        opacity: 0;
                        transform: translateX(-50%) translateY(20px) scale(0.95);
                    }
                }

                /* Responsive adjustments */
                @media (max-width: 480px) {
                    .install-content.mobile .install-icon {
                        width: 56px;
                        height: 56px;
                        font-size: 28px;
                    }
                    .install-content.mobile .install-text strong {
                        font-size: 18px;
                    }
                    .install-content.mobile .install-text span {
                        font-size: 15px;
                    }
                }
            `;

            document.head.appendChild(style);
            document.body.appendChild(installPrompt);

            // Show with enhanced animation
            setTimeout(() => {
                installPrompt.style.display = 'block';
                installPrompt.style.animation = 'slideUpInstall 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
            }, 100);

            // Add haptic feedback on mobile
            if (isMobile && 'vibrate' in navigator) {
                navigator.vibrate(50);
            }

            console.log('Install prompt shown for', isMobile ? 'mobile' : 'desktop');
        }

        function hideInstallPrompt() {
            const prompt = document.getElementById('install-prompt');
            if (prompt) {
                prompt.style.animation = 'slideDownInstall 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
                setTimeout(() => {
                    if (prompt.parentNode) {
                        prompt.parentNode.removeChild(prompt);
                    }
                }, 300);
                installPromptShown = false;
            }
        }

        // Enhanced global functions
        window.installPWA = function() {
            if (deferredPrompt) {
                console.log('Triggering install prompt');

                // Add haptic feedback for install action
                if (isMobile && 'vibrate' in navigator) {
                    navigator.vibrate([50, 50, 50]);
                }

                deferredPrompt.prompt();
                deferredPrompt.userChoice.then(function(choiceResult) {
                    if (choiceResult.outcome === 'accepted') {
                        console.log('User accepted the install prompt');

                        // Track successful installation
                        if (typeof gtag !== 'undefined') {
                            gtag('event', 'pwa_install_success', {
                                event_category: 'engagement',
                                event_label: 'pwa_install_accepted',
                                custom_map: {'metric1': isMobile ? 'mobile' : 'desktop'}
                            });
                        }

                    } else {
                        console.log('User dismissed the install prompt');

                        // Track dismissal
                        if (typeof gtag !== 'undefined') {
                            gtag('event', 'pwa_install_dismissed', {
                                event_category: 'engagement',
                                event_label: 'pwa_install_declined'
                            });
                        }
                    }
                    deferredPrompt = null;
                    hideInstallPrompt();
                });
            } else {
                console.warn('No deferred prompt available');
            }
        };

        window.dismissInstallPrompt = function() {
            console.log('User dismissed install prompt');
            hideInstallPrompt();

            // Remember user's choice for 7 days (shorter than before for mobile)
            const dismissTime = isMobile ? 7 : 30; // 7 days for mobile, 30 for desktop
            localStorage.setItem('installPromptDismissed', Date.now().toString());
            localStorage.setItem('installPromptDismissDays', dismissTime.toString());

            // Track dismissal
            if (typeof gtag !== 'undefined') {
                gtag('event', 'pwa_install_later', {
                    event_category: 'engagement',
                    event_label: 'pwa_install_postponed'
                });
            }
        };

        // Enhanced dismissal check with different periods for mobile/desktop
        const dismissedTime = localStorage.getItem('installPromptDismissed');
        const dismissDays = parseInt(localStorage.getItem('installPromptDismissDays')) || 30;

        if (dismissedTime) {
            const daysSinceDismissed = (Date.now() - parseInt(dismissedTime)) / (1000 * 60 * 60 * 24);
            if (daysSinceDismissed < dismissDays) {
                console.log(`Install prompt dismissed ${Math.round(daysSinceDismissed)} days ago, will show again in ${dismissDays - Math.round(daysSinceDismissed)} days`);
                return; // Don't show again within the specified period
            }
        }

        // Check if we're on a supported browser
        const isSupportedBrowser = ('serviceWorker' in navigator) &&
                                  ('beforeinstallprompt' in window ||
                                   window.matchMedia('(display-mode: standalone)').matches);

        if (!isSupportedBrowser && isMobile) {
            console.log('PWA not supported on this mobile browser');
            // Could show alternative installation instructions for unsupported browsers
        }
    }

    /**
     * Add install prompt animations
     */
    function addInstallAnimations() {
        const animationStyle = document.createElement('style');
        animationStyle.textContent = `
            @keyframes slideUpInstall {
                from {
                    opacity: 0;
                    transform: translateX(-50%) translateY(20px) scale(0.95);
                }
                to {
                    opacity: 1;
                    transform: translateX(-50%) translateY(0) scale(1);
                }
            }

            @keyframes slideDownInstall {
                from {
                    opacity: 1;
                    transform: translateX(-50%) translateY(0) scale(1);
                }
                to {
                    opacity: 0;
                    transform: translateX(-50%) translateY(20px) scale(0.95);
                }
            }

            @keyframes bounceInInstall {
                0% {
                    opacity: 0;
                    transform: translateX(-50%) scale(0.3);
                }
                50% {
                    opacity: 1;
                    transform: translateX(-50%) scale(1.05);
                }
                70% {
                    transform: translateX(-50%) scale(0.9);
                }
                100% {
                    opacity: 1;
                    transform: translateX(-50%) scale(1);
                }
            }
        `;
        document.head.appendChild(animationStyle);
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