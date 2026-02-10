/**
 * NotFound Controller (404 Page)
 * Shown when the URL path does not match any route
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .controller('NotFoundController', ['$scope', '$location', function($scope, $location) {
            var vm = this;

            vm.goHome = function() {
                $location.path('/');
            };

            vm.goSearch = function() {
                $location.path('/search');
            };

            $scope.vm = vm;
        }]);
})();
