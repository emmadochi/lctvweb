/**
 * Performance optimizations for Church TV
 * Implements lazy loading, caching, and other optimizations
 */

(function() {
    'use strict';

    // Lazy loading for images
    function initLazyLoading() {
        // Intersection Observer for lazy loading
        if ('IntersectionObserver' in window) {
            var imageObserver = new IntersectionObserver(function(entries, observer) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        var img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove('lazy');
                        imageObserver.unobserve(img);
                    }
                });
            });

            // Observe all lazy images
            var lazyImages = document.querySelectorAll('img[data-src]');
            lazyImages.forEach(function(img) {
                imageObserver.observe(img);
            });
        } else {
            // Fallback for browsers without Intersection Observer
            loadAllImages();
        }
    }

    function loadAllImages() {
        var lazyImages = document.querySelectorAll('img[data-src]');
        lazyImages.forEach(function(img) {
            img.src = img.dataset.src;
            img.classList.remove('lazy');
        });
    }

    // API response caching
    var APICache = {
        storage: {},
        expiry: 5 * 60 * 1000, // 5 minutes default

        set: function(key, data, ttl) {
            var expiry = ttl || this.expiry;
            this.storage[key] = {
                data: data,
                timestamp: Date.now(),
                expiry: expiry
            };
        },

        get: function(key) {
            var item = this.storage[key];
            if (!item) return null;

            if (Date.now() - item.timestamp > item.expiry) {
                delete this.storage[key];
                return null;
            }

            return item.data;
        },

        clear: function(key) {
            if (key) {
                delete this.storage[key];
            } else {
                this.storage = {};
            }
        },

        size: function() {
            return Object.keys(this.storage).length;
        }
    };

    // Debounce function for search and other frequent operations
    function debounce(func, wait, immediate) {
        var timeout;
        return function() {
            var context = this, args = arguments;
            var later = function() {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            var callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    }

    // Image preloading for critical resources
    function preloadCriticalImages() {
        var criticalImages = [
            'assets/images/logo.png',
            'assets/images/favicon.ico'
        ];

        criticalImages.forEach(function(src) {
            var img = new Image();
            img.src = src;
        });
    }

    // Service worker registration for caching (basic implementation)
    function registerServiceWorker() {
        if ('serviceWorker' in navigator) {
            // Register relative to current path/scope.
            navigator.serviceWorker.register('sw.js')
                .then(function(registration) {
                    console.log('ServiceWorker registration successful');
                })
                .catch(function(error) {
                    console.log('ServiceWorker registration failed:', error);
                });
        }
    }

    // Optimize YouTube player loading
    function optimizeYouTubeLoading() {
        // Load YouTube API only when needed
        window.onYouTubeIframeAPIReady = function() {
            console.log('YouTube API loaded');
        };

        // Preload YouTube API when user hovers over video areas
        var videoAreas = document.querySelectorAll('.video-card, .video-thumbnail');
        videoAreas.forEach(function(area) {
            area.addEventListener('mouseenter', function() {
                if (!window.YT) {
                    loadYouTubeAPI();
                }
            }, { once: true });
        });
    }

    function loadYouTubeAPI() {
        if (!window.YT && !document.querySelector('script[src*="youtube.com/iframe_api"]')) {
            var tag = document.createElement('script');
            tag.src = 'https://www.youtube.com/iframe_api';
            var firstScriptTag = document.getElementsByTagName('script')[0];
            firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
        }
    }

    // Memory management and cleanup
    function initMemoryManagement() {
        // Clear caches periodically
        setInterval(function() {
            APICache.clear(); // Clear expired items
            if (APICache.size() > 100) {
                console.warn('API cache is large, consider clearing');
            }
        }, 10 * 60 * 1000); // Every 10 minutes

        // Listen for low memory warnings
        if ('memory' in performance) {
            window.addEventListener('beforeunload', function() {
                // Cleanup on page unload
                APICache.clear();
            });
        }
    }

    // Optimize scroll performance
    function optimizeScrollPerformance() {
        var scrollTimeout;

        window.addEventListener('scroll', function() {
            if (!scrollTimeout) {
                scrollTimeout = setTimeout(function() {
                    // Throttle scroll events
                    handleScroll();
                    scrollTimeout = null;
                }, 16); // ~60fps
            }
        });

        function handleScroll() {
            // Update sticky elements, lazy load content, etc.
            updateStickyElements();
            checkLazyLoadTriggers();
        }

        function updateStickyElements() {
            // Update any sticky navigation or elements
            var scrolled = window.pageYOffset;
            var header = document.querySelector('.main-header');

            if (header && scrolled > 100) {
                header.classList.add('scrolled');
            } else if (header) {
                header.classList.remove('scrolled');
            }
        }

        function checkLazyLoadTriggers() {
            // Trigger lazy loading for content near viewport
            var lazyElements = document.querySelectorAll('.lazy-load-trigger');
            lazyElements.forEach(function(element) {
                var rect = element.getBoundingClientRect();
                if (rect.top < window.innerHeight + 100) {
                    element.classList.add('loaded');
                }
            });
        }
    }

    // Resource hints for better loading
    function addResourceHints() {
        var hints = [
            { rel: 'dns-prefetch', href: '//fonts.googleapis.com' },
            { rel: 'dns-prefetch', href: '//www.youtube.com' },
            { rel: 'dns-prefetch', href: '//www.google.com' },
            { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: true }
        ];

        hints.forEach(function(hint) {
            var link = document.createElement('link');
            link.rel = hint.rel;
            link.href = hint.href;
            if (hint.crossorigin) {
                link.crossOrigin = hint.crossorigin;
            }
            document.head.appendChild(link);
        });
    }

    // Initialize all performance optimizations
    function initPerformanceOptimizations() {
        console.log('Initializing performance optimizations...');

        // Initialize optimizations when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initOptimizations);
        } else {
            initOptimizations();
        }

        function initOptimizations() {
            initLazyLoading();
            preloadCriticalImages();
            optimizeYouTubeLoading();
            initMemoryManagement();
            optimizeScrollPerformance();
            addResourceHints();

            // Register service worker in production
            if (window.location.hostname !== 'localhost') {
                registerServiceWorker();
            }

            console.log('Performance optimizations initialized');
        }
    }

    // Expose performance utilities globally
    window.ChurchTVPerformance = {
        APICache: APICache,
        debounce: debounce,
        loadYouTubeAPI: loadYouTubeAPI,
        initLazyLoading: initLazyLoading,
        preloadCriticalImages: preloadCriticalImages
    };

    // Auto-initialize when script loads
    initPerformanceOptimizations();

})();