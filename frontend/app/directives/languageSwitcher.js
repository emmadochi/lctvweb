/**
 * Language Switcher Directive
 * Provides a dropdown to switch between supported languages
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .directive('languageSwitcher', ['I18nService', '$rootScope', function(I18nService, $rootScope) {
            return {
                restrict: 'E',
                template: `
                    <div class="language-switcher">
                        <div class="dropdown">
                            <button class="btn btn-link dropdown-toggle language-btn" type="button" data-toggle="dropdown">
                                <span class="flag">{{currentLanguage.flag}}</span>
                                <span class="lang-name">{{currentLanguage.nativeName}}</span>
                                <span class="caret"></span>
                            </button>
                            <ul class="dropdown-menu language-menu">
                                <li ng-repeat="lang in supportedLanguages" ng-class="{active: lang.code === currentLanguage.code}">
                                    <a href="#" ng-click="switchLanguage(lang.code)">
                                        <span class="flag">{{lang.flag}}</span>
                                        <span class="lang-name">{{lang.nativeName}}</span>
                                        <span class="lang-english" ng-if="lang.code !== 'en'">({{lang.name}})</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                `,
                controller: ['$scope', function($scope) {
                    // Initialize scope variables
                    $scope.currentLanguage = I18nService.getCurrentLanguageInfo();
                    $scope.supportedLanguages = I18nService.getSupportedLanguages();

                    // Switch language function
                    $scope.switchLanguage = function(languageCode) {
                        I18nService.setLanguage(languageCode);
                    };

                    // Listen for language changes
                    $rootScope.$on('languageChangeComplete', function(event, languageCode) {
                        $scope.currentLanguage = I18nService.getCurrentLanguageInfo();
                    });
                }]
            };
        }]);
})();