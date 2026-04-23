/**
 * Prayer Service
 * Handles API calls for prayer requests
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .factory('PrayerService', ['$http', '$q', 'API_BASE', function($http, $q, API_BASE) {
            
            var service = {
                submitRequest: submitRequest,
                getUserRequests: getUserRequests
            };

            return service;

            /**
             * Submit a new prayer request
             * @param {Object} data - {full_name, email, phone, category, request_text}
             * @returns {Promise}
             */
            function submitRequest(data) {
                var payload = data;
                var config = {};

                // Check if we have an attachment to decide between JSON and FormData
                if (data.attachment) {
                    payload = new FormData();
                    angular.forEach(data, function(value, key) {
                        if (value !== undefined && value !== null) {
                            payload.append(key, value);
                        }
                    });
                    
                    config = {
                        transformRequest: angular.identity,
                        headers: { 'Content-Type': undefined }
                    };
                }

                return $http.post(API_BASE + '/prayer-requests', payload, config)
                    .then(function(response) {
                        return response.data;
                    })
                    .catch(function(error) {
                        console.error('Error submitting prayer request:', error);
                        return $q.reject(error);
                    });
            }

            /**
             * Get the current user's prayer requests
             * @returns {Promise}
             */
            function getUserRequests() {
                return $http.get(API_BASE + '/prayer-requests/my')
                    .then(function(response) {
                        return response.data.data || [];
                    })
                    .catch(function(error) {
                        console.error('Error fetching user prayer requests:', error);
                        return $q.reject(error);
                    });
            }
        }]);
})();
