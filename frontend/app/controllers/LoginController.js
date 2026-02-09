/**
 * Login Controller
 * Handles user authentication and login page
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .controller('LoginController', ['$scope', '$location', 'AuthService',
            function($scope, $location, AuthService) {

            var vm = this;

            // Controller properties
            vm.isLogin = true; // true for login, false for register
            vm.credentials = {
                email: '',
                password: '',
                password_confirm: '',
                first_name: '',
                last_name: ''
            };
            vm.isLoading = false;
            vm.errorMessage = '';
            vm.successMessage = '';
            vm.loginForm = {};
            vm.currentYear = new Date().getFullYear();
            vm.acceptTerms = false;
            vm.passwordStrength = {
                score: 0,
                label: '',
                color: ''
            };

            // Check if already authenticated
            if (AuthService.isAuthenticated()) {
                // Redirect to home
                $location.path('/');
                return;
            }

            /**
             * Toggle between login and register modes
             */
            vm.toggleMode = function() {
                // Store current email before clearing form
                var currentEmail = vm.credentials.email;

                vm.isLogin = !vm.isLogin;
                vm.errorMessage = '';
                vm.successMessage = '';

                // Clear form but preserve email if it was entered
                vm.clearForm();

                // Restore email if it was previously entered
                if (currentEmail && currentEmail.trim()) {
                    vm.credentials.email = currentEmail.trim();
                }

                // Reset form validation state
                if (vm.loginForm && vm.loginForm.$setPristine) {
                    vm.loginForm.$setPristine();
                    vm.loginForm.$setUntouched();
                }
            };

            /**
             * Check password strength
             */
            vm.checkPasswordStrength = function() {
                var password = vm.credentials.password || '';
                var score = 0;
                var feedback = [];

                // Length check
                if (password.length >= 8) score++;
                else feedback.push('At least 8 characters');

                // Lowercase check
                if (/[a-z]/.test(password)) score++;
                else feedback.push('Lowercase letter');

                // Uppercase check
                if (/[A-Z]/.test(password)) score++;
                else feedback.push('Uppercase letter');

                // Number check
                if (/\d/.test(password)) score++;
                else feedback.push('Number');

                // Special character check
                if (/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) score++;
                else feedback.push('Special character');

                // Set strength based on score
                if (score < 2) {
                    vm.passwordStrength = {
                        score: score,
                        label: 'Weak',
                        color: '#dc3545',
                        feedback: feedback
                    };
                } else if (score < 4) {
                    vm.passwordStrength = {
                        score: score,
                        label: 'Fair',
                        color: '#ffc107',
                        feedback: feedback
                    };
                } else if (score < 5) {
                    vm.passwordStrength = {
                        score: score,
                        label: 'Good',
                        color: '#28a745',
                        feedback: feedback.slice(0, 1) // Show only first missing requirement
                    };
                } else {
                    vm.passwordStrength = {
                        score: score,
                        label: 'Strong',
                        color: '#20c997',
                        feedback: []
                    };
                }
            };

            /**
             * Validate email format
             */
            vm.isValidEmail = function(email) {
                var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return emailRegex.test(email);
            };

            /**
             * Handle form submission
             */
            vm.submit = function() {
                if (vm.isLogin) {
                    vm.login();
                } else {
                    vm.register();
                }
            };

            /**
             * Handle login
             */
            vm.login = function() {
                // Reset messages
                vm.errorMessage = '';
                vm.successMessage = '';

                // Validate form
                if (!vm.credentials.email || !vm.credentials.password) {
                    vm.errorMessage = 'Please enter both email and password';
                    return;
                }

                // Set loading state
                vm.isLoading = true;

                // Attempt login
                AuthService.login(vm.credentials.email, vm.credentials.password)
                    .then(function(user) {
                        // Login successful
                        console.log('User login successful:', user);

                        // Show success message
                        vm.showSuccess('Login successful! Welcome back, ' + user.first_name + '!');

                        // Redirect to home after short delay
                        setTimeout(function() {
                            $location.path('/');
                            $scope.$apply();
                        }, 1500);
                    })
                    .catch(function(error) {
                        // Login failed
                        vm.isLoading = false;
                        vm.errorMessage = error.message || 'Login failed. Please try again.';
                        console.error('User login error:', error);
                    });
            };

            /**
             * Handle registration
             */
            vm.register = function() {
                // Reset messages
                vm.errorMessage = '';
                vm.successMessage = '';

                // Validate form
                if (!vm.credentials.email) {
                    vm.errorMessage = 'Please enter your email address';
                    return;
                }

                if (!vm.isValidEmail(vm.credentials.email)) {
                    vm.errorMessage = 'Please enter a valid email address';
                    return;
                }

                if (!vm.credentials.first_name || !vm.credentials.last_name) {
                    vm.errorMessage = 'Please enter your first and last name';
                    return;
                }

                if (!vm.credentials.password) {
                    vm.errorMessage = 'Please enter a password';
                    return;
                }

                if (vm.credentials.password.length < 8) {
                    vm.errorMessage = 'Password must be at least 8 characters long';
                    return;
                }

                if (vm.passwordStrength.score < 2) {
                    vm.errorMessage = 'Please choose a stronger password';
                    return;
                }

                if (vm.credentials.password !== vm.credentials.password_confirm) {
                    vm.errorMessage = 'Passwords do not match';
                    return;
                }

                if (!vm.acceptTerms) {
                    vm.errorMessage = 'Please accept the terms and conditions';
                    return;
                }

                // Set loading state
                vm.isLoading = true;

                // Prepare registration data (remove confirm password)
                var registrationData = {
                    email: vm.credentials.email,
                    password: vm.credentials.password,
                    first_name: vm.credentials.first_name,
                    last_name: vm.credentials.last_name
                };

                // Attempt registration
                AuthService.register(registrationData)
                    .then(function(user) {
                        // Registration successful
                        console.log('User registration successful:', user);

                        // Show success message with email verification prompt
                        vm.showSuccess('Registration successful! Welcome, ' + user.first_name + '! Please check your email for verification instructions.');

                        // Redirect to home after delay
                        setTimeout(function() {
                            $location.path('/');
                            $scope.$apply();
                        }, 3000);
                    })
                    .catch(function(error) {
                        // Registration failed
                        vm.isLoading = false;
                        vm.errorMessage = error.message || 'Registration failed. Please try again.';
                        console.error('User registration error:', error);
                    });
            };

            /**
             * Show success message
             */
            vm.showSuccess = function(message) {
                vm.successMessage = message;
                vm.isLoading = false;

                // Clear after 5 seconds
                setTimeout(function() {
                    vm.successMessage = '';
                    $scope.$apply();
                }, 5000);
            };

            /**
             * Clear form
             */
            vm.clearForm = function() {
                vm.credentials = {
                    email: '',
                    password: '',
                    password_confirm: '',
                    first_name: '',
                    last_name: ''
                };
                vm.acceptTerms = false;
                vm.passwordStrength = {
                    score: 0,
                    label: '',
                    color: ''
                };
                vm.errorMessage = '';
                vm.successMessage = '';
                if (vm.loginForm.$setPristine) {
                    vm.loginForm.$setPristine();
                    vm.loginForm.$setUntouched();
                }
            };

            /**
             * Handle enter key
             */
            vm.onKeyPress = function(event) {
                if (event.keyCode === 13) { // Enter key
                    vm.submit();
                }
            };

            // No need to watch for auth changes since we redirect after successful login

            // Expose controller to scope
            $scope.vm = vm;
        }]);
})();