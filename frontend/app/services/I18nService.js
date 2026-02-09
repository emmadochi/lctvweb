/**
 * Internationalization (i18n) Service
 * Handles language detection, switching, and localization
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .factory('I18nService', ['$rootScope', '$window',
            function($rootScope, $window) {

            var service = {};

            // Supported languages configuration
            service.supportedLanguages = [
                { code: 'en', name: 'English', nativeName: 'English', flag: '🇺🇸', rtl: false },
                { code: 'es', name: 'Spanish', nativeName: 'Español', flag: '🇪🇸', rtl: false },
                { code: 'fr', name: 'French', nativeName: 'Français', flag: '🇫🇷', rtl: false },
                { code: 'pt', name: 'Portuguese', nativeName: 'Português', flag: '🇵🇹', rtl: false },
                { code: 'zh', name: 'Chinese', nativeName: '中文', flag: '🇨🇳', rtl: false },
                { code: 'ar', name: 'Arabic', nativeName: 'العربية', flag: '🇸🇦', rtl: true },
                { code: 'hi', name: 'Hindi', nativeName: 'हिन्दी', flag: '🇮🇳', rtl: false },
                { code: 'ru', name: 'Russian', nativeName: 'Русский', flag: '🇷🇺', rtl: false },
                { code: 'ko', name: 'Korean', nativeName: '한국어', flag: '🇰🇷', rtl: false },
                { code: 'de', name: 'German', nativeName: 'Deutsch', flag: '🇩🇪', rtl: false }
            ];

            // Current language
            service.currentLanguage = 'en';
            service.isRTL = false;

            // Load default translations immediately if available
            if (window.LCTV_TRANSLATIONS) {
                service.currentTranslations = window.LCTV_TRANSLATIONS[service.currentLanguage] || window.LCTV_TRANSLATIONS['en'] || {};
                console.log('I18nService: Loaded translations for', service.currentLanguage, '- Keys:', Object.keys(service.currentTranslations).length);
            } else {
                console.warn('I18nService: window.LCTV_TRANSLATIONS not found');
            }

            // Supported languages cache
            service.supportedLanguagesCache = null;

            // RTL languages
            service.rtlLanguages = ['ar', 'he', 'fa', 'ur'];

            /**
             * Initialize i18n service
             */
            service.initialize = function() {
                // Load default translations immediately if available
                if (window.LCTV_TRANSLATIONS && window.LCTV_TRANSLATIONS['en']) {
                    service.currentTranslations = window.LCTV_TRANSLATIONS['en'];
                }

                // Detect user's preferred language
                var detectedLanguage = service.detectLanguage();

                // Load supported languages first
                service.loadSupportedLanguages().then(function() {
                    // Set initial language and load translations
                    service.setLanguage(detectedLanguage);
                });

                // Listen for language changes
                $rootScope.$on('languageChanged', function(event, language) {
                    service.setLanguage(language);
                });

                console.log('I18nService: Initialized with language:', service.currentLanguage);
            };

            /**
             * Detect user's preferred language
             */
            service.detectLanguage = function() {
                // Priority order: localStorage > browser language > default
                var savedLanguage = localStorage.getItem('lcmtv_language');
                if (savedLanguage && service.isLanguageSupported(savedLanguage)) {
                    return savedLanguage;
                }

                // Check browser language
                var browserLang = service.getBrowserLanguage();
                if (browserLang && service.isLanguageSupported(browserLang)) {
                    return browserLang;
                }

                // Check for language in URL
                var urlLang = service.getLanguageFromUrl();
                if (urlLang && service.isLanguageSupported(urlLang)) {
                    return urlLang;
                }

                return 'en'; // Default fallback
            };

            /**
             * Get browser's preferred language
             */
            service.getBrowserLanguage = function() {
                if (navigator.languages && navigator.languages.length > 0) {
                    return navigator.languages[0].split('-')[0];
                }

                if (navigator.language) {
                    return navigator.language.split('-')[0];
                }

                if (navigator.userLanguage) {
                    return navigator.userLanguage.split('-')[0];
                }

                return null;
            };

            /**
             * Get language from URL parameters
             */
            service.getLanguageFromUrl = function() {
                var urlParams = new URLSearchParams(window.location.search);
                return urlParams.get('lang');
            };

            /**
             * Check if language is supported
             */
            service.isLanguageSupported = function(languageCode) {
                return service.supportedLanguages.some(function(lang) {
                    return lang.code === languageCode;
                });
            };

            /**
             * Set current language
             */
            service.setLanguage = function(languageCode) {
                if (!service.isLanguageSupported(languageCode)) {
                    console.warn('I18nService: Language not supported:', languageCode);
                    languageCode = 'en';
                }

                // Load translations for this language
                return service.loadTranslations(languageCode).then(function(translations) {
                    // Update current language
                    service.currentLanguage = languageCode;

                    // Check if RTL
                    service.isRTL = service.rtlLanguages.includes(languageCode);

                    // Save to localStorage
                    localStorage.setItem('lcmtv_language', languageCode);

                    // Update document attributes for RTL support
                    service.updateDocumentDirection();

                    // Update URL if needed
                    service.updateUrlLanguage(languageCode);

                    // Broadcast language change
                    $rootScope.$broadcast('languageChangeComplete', languageCode);

                    console.log('I18nService: Language set to:', languageCode, '(RTL:', service.isRTL, ')');
                    return languageCode;
                });
            };

            /**
             * Update document direction for RTL languages
             */
            service.updateDocumentDirection = function() {
                var htmlElement = document.documentElement;

                if (service.isRTL) {
                    htmlElement.setAttribute('dir', 'rtl');
                    htmlElement.setAttribute('lang', service.currentLanguage);
                    htmlElement.classList.add('rtl');
                    htmlElement.classList.remove('ltr');
                } else {
                    htmlElement.setAttribute('dir', 'ltr');
                    htmlElement.setAttribute('lang', service.currentLanguage);
                    htmlElement.classList.add('ltr');
                    htmlElement.classList.remove('rtl');
                }
            };

            /**
             * Update URL with language parameter
             */
            service.updateUrlLanguage = function(languageCode) {
                var url = new URL(window.location);

                if (languageCode !== 'en') {
                    url.searchParams.set('lang', languageCode);
                } else {
                    url.searchParams.delete('lang');
                }

                // Update URL without page reload
                window.history.replaceState({}, '', url.toString());
            };

            /**
             * Get language information
             */
            service.getLanguageInfo = function(languageCode) {
                return service.supportedLanguages.find(function(lang) {
                    return lang.code === languageCode;
                });
            };

            /**
             * Get current language info
             */
            service.getCurrentLanguageInfo = function() {
                return service.getLanguageInfo(service.currentLanguage);
            };

            /**
             * Load supported languages (using defaults for now)
             */
            service.loadSupportedLanguages = function() {
                // Use default languages for now to avoid API dependencies
                service.supportedLanguages = service.getDefaultLanguages();
                service.supportedLanguagesCache = service.supportedLanguages;
                return Promise.resolve(service.supportedLanguages);
            };

            /**
             * Get default languages (fallback)
             */
            service.getDefaultLanguages = function() {
                return [
                    { code: 'en', name: 'English', nativeName: 'English', flag: '🇺🇸', rtl: false },
                    { code: 'es', name: 'Spanish', nativeName: 'Español', flag: '🇪🇸', rtl: false },
                    { code: 'fr', name: 'French', nativeName: 'Français', flag: '🇫🇷', rtl: false },
                    { code: 'pt', name: 'Portuguese', nativeName: 'Português', flag: '🇵🇹', rtl: false },
                    { code: 'zh', name: 'Chinese', nativeName: '中文', flag: '🇨🇳', rtl: false },
                    { code: 'ar', name: 'Arabic', nativeName: 'العربية', flag: '🇸🇦', rtl: true },
                    { code: 'hi', name: 'Hindi', nativeName: 'हिन्दी', flag: '🇮🇳', rtl: false },
                    { code: 'ru', name: 'Russian', nativeName: 'Русский', flag: '🇷🇺', rtl: false },
                    { code: 'ko', name: 'Korean', nativeName: '한국어', flag: '🇰🇷', rtl: false },
                    { code: 'de', name: 'German', nativeName: 'Deutsch', flag: '🇩🇪', rtl: false }
                ];
            };

            /**
             * Load translations for a language from API
             */
            service.loadTranslations = function(languageCode) {
                // Use global translations if available, otherwise fallback
                var translations = window.LCTV_TRANSLATIONS || {
                    'en': {
                        'NAV_HOME': 'Home',
                        'NAV_SEARCH': 'Search',
                        'NAV_FAVORITES': 'Favorites',
                        'SEARCH_PLACEHOLDER': 'Search for videos, sermons, worship...',
                        'ACTION_SAVE': 'Save',
                        'ACTION_CANCEL': 'Cancel'
                    }
                };

                var selectedTranslations = translations[languageCode] || translations['en'] || {};

                // Store translations for later use
                service.currentTranslations = selectedTranslations;

                return Promise.resolve(selectedTranslations);
            };

            /**
             * Get all supported languages
             */
            service.getSupportedLanguages = function() {
                return service.supportedLanguagesCache || service.getDefaultLanguages();
            };

            /**
             * Get translation for key
             */
            service.translate = function(key, params) {
                // If no current translations loaded, try to load from global object synchronously
                if (!service.currentTranslations && window.LCTV_TRANSLATIONS) {
                    service.currentTranslations = window.LCTV_TRANSLATIONS[service.currentLanguage] || window.LCTV_TRANSLATIONS['en'] || {};
                }

                var translations = service.currentTranslations || {};

                // If still no translations, provide basic English fallbacks
                if (!translations[key] && !service.currentTranslations) {
                    var fallbacks = {
                        'SEARCH_ADVANCED': 'Advanced Search',
                        'SEARCH_PLACEHOLDER': 'Search for videos, sermons, worship...',
                        'SEARCH_FILTERS': 'Filters',
                        'CATEGORY_SERMONS': 'Sermons',
                        'CATEGORY_WORSHIP': 'Worship'
                    };
                    translations = fallbacks;
                }

                var translation = translations[key] || key;

                // Simple parameter replacement
                if (params) {
                    Object.keys(params).forEach(function(param) {
                        translation = translation.replace(new RegExp('{{' + param + '}}', 'g'), params[param]);
                    });
                }

                return translation;
            };

            /**
             * Format date according to locale
             */
            service.formatDate = function(date, options) {
                if (!date) return '';

                var dateObj = new Date(date);
                return dateObj.toLocaleDateString(service.currentLanguage + '-' + service.currentLanguage.toUpperCase(), options);
            };

            /**
             * Format number according to locale
             */
            service.formatNumber = function(number, options) {
                return new Intl.NumberFormat(service.currentLanguage, options).format(number);
            };

            /**
             * Format currency according to locale
             */
            service.formatCurrency = function(amount, currency) {
                currency = currency || 'USD';
                return new Intl.NumberFormat(service.currentLanguage, {
                    style: 'currency',
                    currency: currency
                }).format(amount);
            };

            /**
             * Get localized category names
             */
            service.getLocalizedCategories = function() {
                return {
                    sermons: service.translate('CATEGORY_SERMONS'),
                    worship: service.translate('CATEGORY_WORSHIP'),
                    children: service.translate('CATEGORY_CHILDREN'),
                    youth: service.translate('CATEGORY_YOUTH'),
                    prayer: service.translate('CATEGORY_PRAYER'),
                    bible_study: service.translate('CATEGORY_BIBLE_STUDY'),
                    special_events: service.translate('CATEGORY_SPECIAL_EVENTS'),
                    music: service.translate('CATEGORY_MUSIC')
                };
            };

            /**
             * Get localized day names
             */
            service.getLocalizedDays = function() {
                var days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
                var localizedDays = {};

                days.forEach(function(day, index) {
                    var date = new Date(2021, 0, 3 + index); // January 3, 2021 was a Sunday
                    localizedDays[day] = date.toLocaleDateString(service.currentLanguage, { weekday: 'long' });
                });

                return localizedDays;
            };

            /**
             * Get localized month names
             */
            service.getLocalizedMonths = function() {
                var months = [];
                for (var i = 0; i < 12; i++) {
                    var date = new Date(2021, i, 1);
                    months.push(date.toLocaleDateString(service.currentLanguage, { month: 'long' }));
                }
                return months;
            };

            /**
             * Export translations for admin management
             */
            service.exportTranslations = function() {
                return {
                    currentLanguage: service.currentLanguage,
                    supportedLanguages: service.supportedLanguages,
                    translations: TRANSLATIONS,
                    isRTL: service.isRTL
                };
            };

            return service;
        }]);

    // Register translate filter
    angular.module('ChurchTVApp')
        .filter('translate', ['I18nService', function(I18nService) {
            return function(key, params) {
                if (!key) return '';
                return I18nService.translate(key, params);
            };
        }]);
})();