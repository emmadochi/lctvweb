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
        // Add smooth scrolling for anchor links
        initializeSmoothScrolling();

        // Initialize tooltips if needed
        initializeTooltips();

        // Handle mobile menu if needed
        initializeMobileMenu();

        // Add keyboard shortcuts
        initializeKeyboardShortcuts();
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

})();