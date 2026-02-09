/**
 * Footer Controller
 * Handles footer functionality and dynamic content
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .controller('FooterController', ['$scope', function($scope) {

            var vm = this;

            // Controller properties
            vm.currentYear = new Date().getFullYear();

            // Expose controller to scope
            $scope.vm = vm;
        }]);
})();