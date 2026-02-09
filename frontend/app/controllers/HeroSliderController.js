/**
 * Hero Slider Controller
 * Handles dynamic hero section slider with creative transitions
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .controller('HeroSliderController', ['$scope', '$interval', '$timeout', function($scope, $interval, $timeout) {

            var vm = this;

            // Slider properties
            vm.currentSlide = 1;
            vm.totalSlides = 3;
            vm.isAutoPlaying = true;
            vm.progress = 0;
            vm.slideInterval = null;
            vm.progressInterval = null;

            // Slide duration (8 seconds per slide)
            var SLIDE_DURATION = 8000;
            var PROGRESS_UPDATE_INTERVAL = 100; // Update progress every 100ms

            // Initialize slider
            init();

            function init() {
                // Start auto-play
                startAutoPlay();

                // Start progress tracking
                startProgressTracking();

                // Add keyboard navigation
                addKeyboardNavigation();

                // Add touch/swipe support for mobile
                addTouchSupport();

                // Pause on hover
                addHoverPause();

                console.log('Hero slider initialized');
            }

            /**
             * Start automatic slide transitions
             */
            function startAutoPlay() {
                if (vm.slideInterval) {
                    $interval.cancel(vm.slideInterval);
                }

                vm.slideInterval = $interval(function() {
                    vm.nextSlide();
                }, SLIDE_DURATION);
            }

            /**
             * Start progress bar tracking
             */
            function startProgressTracking() {
                if (vm.progressInterval) {
                    $interval.cancel(vm.progressInterval);
                }

                vm.progressInterval = $interval(function() {
                    vm.progress += (PROGRESS_UPDATE_INTERVAL / SLIDE_DURATION) * 100;
                    if (vm.progress >= 100) {
                        vm.progress = 0;
                    }
                }, PROGRESS_UPDATE_INTERVAL);
            }

            /**
             * Go to next slide
             */
            vm.nextSlide = function() {
                var nextSlide = vm.currentSlide >= vm.totalSlides ? 1 : vm.currentSlide + 1;
                vm.goToSlide(nextSlide);
            };

            /**
             * Go to previous slide
             */
            vm.prevSlide = function() {
                var prevSlide = vm.currentSlide <= 1 ? vm.totalSlides : vm.currentSlide - 1;
                vm.goToSlide(prevSlide);
            };

            /**
             * Go to specific slide
             */
            vm.goToSlide = function(slideNumber) {
                if (slideNumber === vm.currentSlide) return;

                // Remove active class from current slide
                var currentSlideEl = document.querySelector('.hero-slide.active');
                if (currentSlideEl) {
                    currentSlideEl.classList.remove('active');
                    currentSlideEl.classList.add('exiting');
                }

                // Update current slide
                vm.currentSlide = slideNumber;

                // Add active class to new slide
                $timeout(function() {
                    var newSlideEl = document.querySelector('.hero-slide[data-slide="' + slideNumber + '"]');
                    if (newSlideEl) {
                        newSlideEl.classList.add('active');
                        newSlideEl.classList.remove('exiting');

                        // Trigger entrance animation
                        var contentEl = newSlideEl.querySelector('.hero-content');
                        if (contentEl) {
                            contentEl.classList.remove('slide-in-left', 'slide-in-right', 'slide-in-up');
                            void contentEl.offsetWidth; // Trigger reflow
                            contentEl.classList.add(getSlideAnimation(slideNumber));
                        }
                    }

                    // Remove exiting class from previous slides
                    var exitingSlides = document.querySelectorAll('.hero-slide.exiting');
                    exitingSlides.forEach(function(slide) {
                        slide.classList.remove('exiting');
                    });
                }, 50);

                // Reset progress
                vm.progress = 0;

                // Restart auto-play if paused
                if (!vm.isAutoPlaying) {
                    startAutoPlay();
                    vm.isAutoPlaying = true;
                }
            };

            /**
             * Get animation class for slide
             */
            function getSlideAnimation(slideNumber) {
                var animations = {
                    1: 'slide-in-left',
                    2: 'slide-in-right',
                    3: 'slide-in-up'
                };
                return animations[slideNumber] || 'slide-in-left';
            }

            /**
             * Pause auto-play
             */
            vm.pauseAutoPlay = function() {
                if (vm.slideInterval) {
                    $interval.cancel(vm.slideInterval);
                    vm.slideInterval = null;
                }
                vm.isAutoPlaying = false;
            };

            /**
             * Resume auto-play
             */
            vm.resumeAutoPlay = function() {
                if (!vm.isAutoPlaying) {
                    startAutoPlay();
                    vm.isAutoPlaying = true;
                }
            };

            /**
             * Add keyboard navigation
             */
            function addKeyboardNavigation() {
                document.addEventListener('keydown', function(e) {
                    // Only handle if hero section is in view
                    var heroSection = document.querySelector('.hero-section');
                    if (!heroSection) return;

                    var rect = heroSection.getBoundingClientRect();
                    var isInView = rect.top < window.innerHeight && rect.bottom > 0;

                    if (!isInView) return;

                    switch (e.key) {
                        case 'ArrowLeft':
                            e.preventDefault();
                            vm.prevSlide();
                            $scope.$apply();
                            break;
                        case 'ArrowRight':
                            e.preventDefault();
                            vm.nextSlide();
                            $scope.$apply();
                            break;
                        case ' ': // Spacebar
                            e.preventDefault();
                            if (vm.isAutoPlaying) {
                                vm.pauseAutoPlay();
                            } else {
                                vm.resumeAutoPlay();
                            }
                            $scope.$apply();
                            break;
                    }
                });
            }

            /**
             * Add touch/swipe support for mobile
             */
            function addTouchSupport() {
                var heroSection = document.querySelector('.hero-section');
                if (!heroSection) return;

                var startX = 0;
                var startY = 0;
                var isSwiping = false;

                heroSection.addEventListener('touchstart', function(e) {
                    startX = e.touches[0].clientX;
                    startY = e.touches[0].clientY;
                    isSwiping = true;
                    vm.pauseAutoPlay();
                });

                heroSection.addEventListener('touchmove', function(e) {
                    if (!isSwiping) return;

                    var deltaX = e.touches[0].clientX - startX;
                    var deltaY = e.touches[0].clientY - startY;

                    // Only handle horizontal swipes
                    if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > 50) {
                        e.preventDefault();

                        if (deltaX > 0) {
                            // Swipe right - previous slide
                            vm.prevSlide();
                        } else {
                            // Swipe left - next slide
                            vm.nextSlide();
                        }

                        isSwiping = false;
                        $scope.$apply();
                    }
                });

                heroSection.addEventListener('touchend', function() {
                    isSwiping = false;
                    $timeout(function() {
                        vm.resumeAutoPlay();
                    }, 3000); // Resume after 3 seconds
                });
            }

            /**
             * Add hover pause functionality
             */
            function addHoverPause() {
                var heroSection = document.querySelector('.hero-section');
                if (!heroSection) return;

                heroSection.addEventListener('mouseenter', function() {
                    vm.pauseAutoPlay();
                    $scope.$apply();
                });

                heroSection.addEventListener('mouseleave', function() {
                    $timeout(function() {
                        vm.resumeAutoPlay();
                    }, 2000); // Resume after 2 seconds
                    $scope.$apply();
                });
            }

            // Expose controller to scope
            $scope.vm = vm;

            // Cleanup on destroy
            $scope.$on('$destroy', function() {
                if (vm.slideInterval) {
                    $interval.cancel(vm.slideInterval);
                }
                if (vm.progressInterval) {
                    $interval.cancel(vm.progressInterval);
                }
            });
        }]);
})();