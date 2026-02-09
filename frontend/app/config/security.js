/**
 * Security configuration and utilities for Church TV
 * Implements CSP, input validation, and security hardening
 */

(function() {
    'use strict';

    // Content Security Policy configuration
    var CSP_CONFIG = {
        'default-src': "'self'",
        'script-src': "'self' 'unsafe-inline' 'unsafe-eval' https://www.youtube.com https://s.ytimg.com https://www.googleapis.com https://ajax.googleapis.com https://code.jquery.com",
        'style-src': "'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net",
        'font-src': "'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com",
        'img-src': "'self' data: https: http:",
        'connect-src': "'self' http://localhost:* https://localhost:* https://www.youtube.com https://www.googleapis.com https://fonts.googleapis.com https://fonts.gstatic.com https://ajax.googleapis.com https://cdn.jsdelivr.net",
        'frame-src': "'self' https://www.youtube.com https://www.youtube-nocookie.com",
        'object-src': "'none'",
        'base-uri': "'self'",
        'form-action': "'self'"
    };

    // Input validation patterns
    var VALIDATION_PATTERNS = {
        youtubeId: /^[a-zA-Z0-9_-]{11}$/,
        email: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
        url: /^https?:\/\/(www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b([-a-zA-Z0-9()@:%_\+.~#?&//=]*)$/,
        slug: /^[a-z0-9]+(?:-[a-z0-9]+)*$/,
        text: /^[a-zA-Z0-9\s\-_.,!?()]+$/,
        number: /^\d+$/
    };

    // XSS prevention - sanitize HTML content
    function sanitizeHTML(text) {
        if (!text) return '';

        var temp = document.createElement('div');
        temp.textContent = text;
        return temp.innerHTML;
    }

    // SQL injection prevention - basic input sanitization
    function sanitizeSQLInput(input) {
        if (!input) return '';

        // Remove potentially dangerous characters
        return input.replace(/['";\\]/g, '');
    }

    // CSRF protection token
    var csrfToken = null;

    function generateCSRFToken() {
        if (!csrfToken) {
            csrfToken = Math.random().toString(36).substring(2) + Date.now().toString(36);
            sessionStorage.setItem('csrf_token', csrfToken);
        }
        return csrfToken;
    }

    function validateCSRFToken(token) {
        return token === sessionStorage.getItem('csrf_token');
    }

    // Input validation functions
    var validators = {
        isValidYouTubeId: function(id) {
            return VALIDATION_PATTERNS.youtubeId.test(id);
        },

        isValidEmail: function(email) {
            return VALIDATION_PATTERNS.email.test(email);
        },

        isValidUrl: function(url) {
            return VALIDATION_PATTERNS.url.test(url);
        },

        isValidSlug: function(slug) {
            return VALIDATION_PATTERNS.slug.test(slug);
        },

        isValidText: function(text, maxLength) {
            if (!VALIDATION_PATTERNS.text.test(text)) return false;
            if (maxLength && text.length > maxLength) return false;
            return true;
        },

        isValidNumber: function(num, min, max) {
            if (!VALIDATION_PATTERNS.number.test(num.toString())) return false;
            var n = parseInt(num, 10);
            if (min !== undefined && n < min) return false;
            if (max !== undefined && n > max) return false;
            return true;
        },

        // Sanitize user input
        sanitize: function(input, type) {
            if (!input) return '';

            switch (type) {
                case 'html':
                    return sanitizeHTML(input);
                case 'sql':
                    return sanitizeSQLInput(input);
                case 'text':
                    return input.replace(/[<>'"&]/g, '');
                default:
                    return sanitizeHTML(input);
            }
        }
    };

    // Rate limiting for API calls
    var rateLimiter = {
        calls: {},
        limits: {
            search: { max: 10, window: 60000 }, // 10 searches per minute
            login: { max: 5, window: 300000 },  // 5 login attempts per 5 minutes
            general: { max: 100, window: 60000 } // 100 general API calls per minute
        },

        check: function(action) {
            var now = Date.now();
            var limit = this.limits[action] || this.limits.general;

            if (!this.calls[action]) {
                this.calls[action] = [];
            }

            // Remove old calls outside the time window
            this.calls[action] = this.calls[action].filter(function(call) {
                return now - call < limit.window;
            });

            if (this.calls[action].length >= limit.max) {
                return false; // Rate limit exceeded
            }

            this.calls[action].push(now);
            return true;
        },

        reset: function(action) {
            if (action) {
                this.calls[action] = [];
            } else {
                this.calls = {};
            }
        }
    };

    // Security headers configuration
    var securityHeaders = {
        'X-Frame-Options': 'SAMEORIGIN',
        'X-Content-Type-Options': 'nosniff',
        'X-XSS-Protection': '1; mode=block',
        'Referrer-Policy': 'strict-origin-when-cross-origin',
        'Permissions-Policy': 'geolocation=(), microphone=(), camera=()'
    };

    // Initialize security measures
    function initSecurity() {
        console.log('Initializing security measures...');

        // Generate CSRF token
        generateCSRFToken();

        // Add CSP meta tag if not present
        addCSPMetaTag();

        // Initialize input validation
        initInputValidation();

        // Add security event listeners
        initSecurityListeners();

        console.log('Security measures initialized');
    }

    function addCSPMetaTag() {
        // Skip CSP in development (localhost)
        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
            console.log('CSP disabled for development');
            return;
        }

        var cspString = Object.keys(CSP_CONFIG)
            .map(function(directive) {
                return directive + ' ' + CSP_CONFIG[directive];
            })
            .join('; ');

        var metaTag = document.querySelector('meta[http-equiv="Content-Security-Policy"]');
        if (!metaTag) {
            metaTag = document.createElement('meta');
            metaTag.httpEquiv = 'Content-Security-Policy';
            metaTag.content = cspString;
            document.head.appendChild(metaTag);
        }
    }

    function initInputValidation() {
        // Add validation to forms
        var forms = document.querySelectorAll('form');
        forms.forEach(function(form) {
            form.addEventListener('submit', function(e) {
                var isValid = validateForm(form);
                if (!isValid) {
                    e.preventDefault();
                    return false;
                }
            });
        });

        // Add real-time validation to inputs
        var inputs = document.querySelectorAll('input, textarea');
        inputs.forEach(function(input) {
            input.addEventListener('blur', function() {
                validateInput(input);
            });
        });
    }

    function validateForm(form) {
        var inputs = form.querySelectorAll('input[required], textarea[required], select[required]');
        var isValid = true;

        inputs.forEach(function(input) {
            if (!validateInput(input)) {
                isValid = false;
            }
        });

        return isValid;
    }

    function validateInput(input) {
        var value = input.value.trim();
        var type = input.type || input.getAttribute('data-validation-type');
        var isRequired = input.hasAttribute('required');
        var maxLength = input.getAttribute('maxlength');

        // Clear previous validation
        input.classList.remove('invalid', 'valid');

        // Check required fields
        if (isRequired && !value) {
            markInvalid(input, 'This field is required');
            return false;
        }

        // Skip validation if empty and not required
        if (!value && !isRequired) {
            return true;
        }

        // Type-specific validation
        var valid = true;
        switch (type) {
            case 'email':
                valid = validators.isValidEmail(value);
                break;
            case 'url':
                valid = validators.isValidUrl(value);
                break;
            case 'youtube-id':
                valid = validators.isValidYouTubeId(value);
                break;
            case 'slug':
                valid = validators.isValidSlug(value);
                break;
            default:
                valid = validators.isValidText(value, maxLength);
        }

        if (valid) {
            input.classList.add('valid');
            return true;
        } else {
            markInvalid(input, 'Invalid format');
            return false;
        }
    }

    function markInvalid(input, message) {
        input.classList.add('invalid');

        // Add error message
        var errorDiv = input.parentNode.querySelector('.error-message');
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.className = 'error-message';
            input.parentNode.appendChild(errorDiv);
        }
        errorDiv.textContent = message;
        errorDiv.style.display = 'block';
    }

    function initSecurityListeners() {
        // Monitor for suspicious activities
        document.addEventListener('contextmenu', function(e) {
            // Allow context menu in development
            if (window.location.hostname === 'localhost') return;

            // Prevent right-click in production
            e.preventDefault();
        });

        // Monitor for XSS attempts
        window.addEventListener('error', function(e) {
            if (e.message && e.message.includes('script')) {
                console.warn('Potential XSS attempt detected');
            }
        });

        // Monitor for unauthorized access attempts
        window.addEventListener('storage', function(e) {
            if (e.key && e.key.includes('admin') && !isAuthorizedUser()) {
                console.warn('Unauthorized access to admin storage detected');
            }
        });
    }

    function isAuthorizedUser() {
        // Check if user is authorized (implement based on your auth system)
        return !!sessionStorage.getItem('admin_token');
    }

    // Expose security utilities globally
    window.ChurchTVSecurity = {
        validators: validators,
        rateLimiter: rateLimiter,
        generateCSRFToken: generateCSRFToken,
        validateCSRFToken: validateCSRFToken,
        sanitize: function(input, type) {
            return validators.sanitize(input, type);
        },
        validateInput: validateInput,
        checkRateLimit: function(action) {
            return rateLimiter.check(action);
        }
    };

    // Auto-initialize security
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSecurity);
    } else {
        initSecurity();
    }

})();