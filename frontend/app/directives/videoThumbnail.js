/**
 * Video Thumbnail Directive
 * AngularJS directive for consistent video thumbnail display
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .directive('videoThumbnail', ['VideoService', function(VideoService) {
            return {
                restrict: 'E',
                scope: {
                    video: '=',
                    showDuration: '@',
                    showOverlay: '@',
                    clickable: '@',
                    size: '@' // small, medium, large
                },
                template: `
                    <div class="video-thumbnail-wrapper"
                         ng-class="{'clickable': clickable !== 'false'}"
                         ng-click="onClick()">
                        <div class="video-thumbnail-container">
                            <img ng-src="{{video.thumbnail_url}}"
                                 alt="{{video.title}}"
                                 loading="lazy"
                                 ng-if="video.thumbnail_url">

                            <!-- Fallback for missing thumbnail -->
                            <div class="thumbnail-placeholder" ng-if="!video.thumbnail_url">
                                <i class="fa fa-video-camera"></i>
                                <span>No Preview</span>
                            </div>

                            <!-- Duration overlay -->
                            <div class="thumbnail-duration" ng-if="showDuration !== 'false' && video.duration">
                                {{formatDuration(video.duration)}}
                            </div>

                            <!-- Play overlay -->
                            <div class="thumbnail-play-overlay" ng-if="showOverlay !== 'false'">
                                <i class="fa fa-play-circle"></i>
                            </div>

                            <!-- Category badge -->
                            <div class="thumbnail-category" ng-if="video.category_name">
                                <i class="fa fa-tag"></i>
                                {{video.category_name}}
                            </div>
                        </div>

                        <!-- Video info (optional, shown outside container) -->
                        <div class="thumbnail-info" ng-if="showInfo !== 'false'">
                            <h4 class="thumbnail-title">{{video.title}}</h4>
                            <div class="thumbnail-meta">
                                <span class="thumbnail-views" ng-if="video.view_count">
                                    <i class="fa fa-eye"></i> {{formatViews(video.view_count)}}
                                </span>
                                <span class="thumbnail-date" ng-if="video.published_at">
                                    <i class="fa fa-calendar"></i> {{formatDate(video.published_at)}}
                                </span>
                            </div>
                        </div>
                    </div>
                `,
                link: function(scope, element, attrs) {
                    // Default values
                    scope.showDuration = scope.showDuration !== undefined ? scope.showDuration : 'true';
                    scope.showOverlay = scope.showOverlay !== undefined ? scope.showOverlay : 'true';
                    scope.clickable = scope.clickable !== undefined ? scope.clickable : 'true';
                    scope.size = scope.size || 'medium';

                    // Add size class
                    element.addClass('thumbnail-' + scope.size);

                    // Click handler
                    scope.onClick = function() {
                        if (scope.clickable !== 'false' && scope.video && scope.video.id) {
                            // Navigate to video page
                            window.location.hash = '#/video/' + scope.video.id;
                        }
                    };

                    // Format duration
                    scope.formatDuration = function(seconds) {
                        if (!seconds || seconds <= 0) return '0:00';

                        var hours = Math.floor(seconds / 3600);
                        var minutes = Math.floor((seconds % 3600) / 60);
                        var remainingSeconds = seconds % 60;

                        if (hours > 0) {
                            return hours + ':' + padZero(minutes) + ':' + padZero(remainingSeconds);
                        } else {
                            return minutes + ':' + padZero(remainingSeconds);
                        }
                    };

                    // Format view count
                    scope.formatViews = function(count) {
                        if (!count || count < 0) return '0';

                        if (count >= 1000000) {
                            return (count / 1000000).toFixed(1) + 'M';
                        } else if (count >= 1000) {
                            return (count / 1000).toFixed(1) + 'K';
                        } else {
                            return count.toString();
                        }
                    };

                    // Format date
                    scope.formatDate = function(dateString) {
                        if (!dateString) return '';

                        var date = new Date(dateString);
                        var now = new Date();
                        var diffTime = Math.abs(now - date);
                        var diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

                        if (diffDays === 1) {
                            return 'Today';
                        } else if (diffDays === 2) {
                            return 'Yesterday';
                        } else if (diffDays <= 7) {
                            return diffDays + ' days ago';
                        } else {
                            return date.toLocaleDateString();
                        }
                    };

                    // Helper function
                    function padZero(num) {
                        return (num < 10 ? '0' : '') + num;
                    }
                }
            };
        }]);
})();