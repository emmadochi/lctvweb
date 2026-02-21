/**
 * Google Translate Service
 * Professional translation service integration
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .factory('GoogleTranslateService', ['$http', '$q', 'CONFIG',
            function($http, $q, CONFIG) {
                
                var service = {};
                var baseUrl = CONFIG.API_BASE_URL + '/translate';
                
                /**
                 * Translate single text
                 */
                service.translate = function(text, targetLanguage, sourceLanguage) {
                    if (!text || !targetLanguage) {
                        return $q.reject('Text and target language are required');
                    }
                    
                    sourceLanguage = sourceLanguage || 'en';
                    
                    var requestData = {
                        text: text,
                        target: targetLanguage,
                        source: sourceLanguage
                    };
                    
                    return $http.post(baseUrl, requestData)
                        .then(function(response) {
                            return response.data.data.translated_text;
                        })
                        .catch(function(error) {
                            console.error('Translation failed:', error);
                            // Fallback to original text
                            return text;
                        });
                };
                
                /**
                 * Translate multiple texts
                 */
                service.translateBatch = function(texts, targetLanguage, sourceLanguage) {
                    if (!texts || !Array.isArray(texts) || texts.length === 0) {
                        return $q.reject('Texts array is required');
                    }
                    
                    if (!targetLanguage) {
                        return $q.reject('Target language is required');
                    }
                    
                    sourceLanguage = sourceLanguage || 'en';
                    
                    var requestData = {
                        texts: texts,
                        target: targetLanguage,
                        source: sourceLanguage
                    };
                    
                    return $http.post(baseUrl + '/batch', requestData)
                        .then(function(response) {
                            return response.data.data.translated_texts;
                        })
                        .catch(function(error) {
                            console.error('Batch translation failed:', error);
                            // Fallback to original texts
                            return texts;
                        });
                };
                
                /**
                 * Detect language of text
                 */
                service.detectLanguage = function(text) {
                    if (!text) {
                        return $q.reject('Text is required');
                    }
                    
                    var requestData = {
                        text: text
                    };
                    
                    return $http.post(baseUrl + '/detect', requestData)
                        .then(function(response) {
                            return response.data.data.detected_language;
                        })
                        .catch(function(error) {
                            console.error('Language detection failed:', error);
                            return 'en'; // Default fallback
                        });
                };
                
                /**
                 * Get supported languages
                 */
                service.getSupportedLanguages = function(targetLanguage) {
                    targetLanguage = targetLanguage || 'en';
                    
                    return $http.get(baseUrl + '/languages?target=' + targetLanguage)
                        .then(function(response) {
                            return response.data.data.languages;
                        })
                        .catch(function(error) {
                            console.error('Get languages failed:', error);
                            return []; // Return empty array on failure
                        });
                };
                
                /**
                 * Get service status
                 */
                service.getStatus = function() {
                    return $http.get(baseUrl + '/status')
                        .then(function(response) {
                            return response.data.data;
                        })
                        .catch(function(error) {
                            console.error('Get status failed:', error);
                            return {
                                configured: false,
                                service: 'google_translate'
                            };
                        });
                };
                
                /**
                 * Clear translation cache
                 */
                service.clearCache = function() {
                    return $http.post(baseUrl + '/cache/clear')
                        .then(function(response) {
                            return response.data.data;
                        })
                        .catch(function(error) {
                            console.error('Clear cache failed:', error);
                            return null;
                        });
                };
                
                return service;
            }]);
})();