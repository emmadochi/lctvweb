/**
 * Donation Controller
 * Handles donation form processing and payment integration
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .controller('DonationController', ['$scope', '$rootScope', '$timeout', 'DonationService', 'AuthService',
            function($scope, $rootScope, $timeout, DonationService, AuthService) {

            var vm = this;

            // Controller properties
            vm.donation = {
                amount: null,
                currency: 'USD',
                payment_method: 'card',
                donation_type: 'general',
                donor_name: '',
                donor_email: '',
                first_name: '',
                last_name: '',
                phone: '',
                address_street: '',
                address_city: '',
                address_state: '',
                address_zip: '',
                address_country: 'USA',
                is_recurring: false,
                recurring_interval: '',
                tax_deductible: true,
                is_anonymous: false
            };

            vm.campaigns = [];
            vm.userDonations = [];
            vm.suggestedAmounts = [];
            vm.recurringIntervals = [];
            vm.donationTypes = [];
            vm.processing = false;
            vm.cardError = '';
            vm.lastDonation = null;
            vm.selectedCampaign = null;

            // Stripe elements
            vm.cardElement = null;

            // Initialize controller
            init();

            function init() {
                // Load donation data
                loadCampaigns();
                loadSuggestedAmounts();
                loadDonationTypes();
                loadRecurringIntervals();

                // Load user donations if authenticated
                if (AuthService.isAuthenticated()) {
                    loadUserDonations();
                    // Pre-fill user information
                    var user = AuthService.getCurrentUser();
                    if (user) {
                        vm.donation.donor_email = user.email || '';
                        vm.donation.first_name = user.first_name || '';
                        vm.donation.last_name = user.last_name || '';
                        vm.donation.donor_name = (user.first_name + ' ' + user.last_name).trim();
                    }
                }

                // Initialize Stripe Elements
                initializeStripeElements();
            }

            /**
             * Load donation campaigns
             */
            function loadCampaigns() {
                DonationService.getCampaigns(true, 6).then(function(response) {
                    vm.campaigns = response.campaigns || [];
                });
            }

            /**
             * Load user's donation history
             */
            function loadUserDonations() {
                DonationService.getUserDonations(10).then(function(response) {
                    vm.userDonations = response.donations || [];
                });
            }

            /**
             * Load suggested donation amounts
             */
            function loadSuggestedAmounts() {
                vm.suggestedAmounts = DonationService.getSuggestedAmounts();
            }

            /**
             * Load donation types
             */
            function loadDonationTypes() {
                vm.donationTypes = DonationService.getDonationTypes();
            }

            /**
             * Load recurring intervals
             */
            function loadRecurringIntervals() {
                vm.recurringIntervals = DonationService.getRecurringIntervals();
            }

            /**
             * Initialize Stripe Elements
             */
            function initializeStripeElements() {
                $timeout(function() {
                    if (typeof Stripe !== 'undefined' && DonationService.stripe) {
                        var elements = DonationService.stripe.elements();
                        vm.cardElement = elements.create('card', {
                            style: {
                                base: {
                                    fontSize: '16px',
                                    color: '#32325d',
                                    fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
                                    fontSmoothing: 'antialiased',
                                    '::placeholder': {
                                        color: '#aab7c4'
                                    }
                                },
                                invalid: {
                                    color: '#fa755a',
                                    iconColor: '#fa755a'
                                }
                            }
                        });

                        vm.cardElement.mount('#card-element');

                        // Handle card element events
                        vm.cardElement.on('change', function(event) {
                            vm.cardError = event.error ? event.error.message : '';
                            $scope.$apply();
                        });
                    }
                }, 1000);
            }

            /**
             * Select suggested amount
             */
            vm.selectAmount = function(amount) {
                vm.donation.amount = amount;
            };

            /**
             * Select payment method
             */
            vm.selectPaymentMethod = function(method) {
                vm.donation.payment_method = method;
                vm.cardError = '';
            };

            /**
             * Select donation campaign
             */
            vm.selectCampaign = function(campaign) {
                vm.selectedCampaign = campaign;
                vm.donation.donation_type = 'campaign';
                vm.donation.campaign_id = campaign.id;
                // Scroll to donation form
                document.querySelector('.donation-form-section').scrollIntoView({ behavior: 'smooth' });
            };

            /**
             * Get impact information for amount
             */
            vm.getImpactInfo = function(amount) {
                var impactInfo = DonationService.getImpactInfo();
                return impactInfo[amount] || '';
            };

            /**
             * Process donation
             */
            vm.processDonation = function() {
                if (!validateDonation()) {
                    return;
                }

                vm.processing = true;
                vm.cardError = '';

                // Prepare donation data
                var donationData = angular.copy(vm.donation);

                // Combine names
                if (vm.donation.first_name || vm.donation.last_name) {
                    donationData.donor_name = (vm.donation.first_name + ' ' + vm.donation.last_name).trim();
                }

                // Add card element for Stripe payments
                if (vm.donation.payment_method === 'card') {
                    donationData.cardElement = vm.cardElement;
                }

                // Process donation
                DonationService.processDonation(donationData)
                    .then(function(response) {
                        vm.processing = false;
                        vm.lastDonation = donationData;

                        // Show success modal
                        $('#donationSuccessModal').modal('show');

                        // Reset form
                        vm.resetForm();

                        // Reload user donations if authenticated
                        if (AuthService.isAuthenticated()) {
                            loadUserDonations();
                        }

                        // Reload campaigns to update progress
                        loadCampaigns();

                    })
                    .catch(function(error) {
                        vm.processing = false;
                        vm.cardError = error || 'Payment failed. Please try again.';

                        $rootScope.showError('Donation failed: ' + (error || 'Unknown error'));
                    });
            };

            /**
             * Request tax receipt
             */
            vm.requestReceipt = function(donationId) {
                DonationService.generateTaxReceipt(donationId)
                    .then(function(response) {
                        $rootScope.showSuccess('Tax receipt has been generated and emailed to you.');
                    })
                    .catch(function(error) {
                        $rootScope.showError('Failed to generate tax receipt: ' + error);
                    });
            };

            /**
             * Validate donation form
             */
            function validateDonation() {
                // Basic validation
                if (!vm.donation.amount || vm.donation.amount <= 0) {
                    $rootScope.showError('Please enter a valid donation amount.');
                    return false;
                }

                if (!vm.donation.donor_email || !DonationService.isValidEmail(vm.donation.donor_email)) {
                    $rootScope.showError('Please enter a valid email address.');
                    return false;
                }

                if (!vm.donation.first_name || !vm.donation.last_name) {
                    $rootScope.showError('Please enter your first and last name.');
                    return false;
                }

                if (vm.donation.is_recurring && !vm.donation.recurring_interval) {
                    $rootScope.showError('Please select a recurring donation interval.');
                    return false;
                }

                // Payment method specific validation
                if (vm.donation.payment_method === 'card' && !vm.cardElement) {
                    $rootScope.showError('Card information is required.');
                    return false;
                }

                return true;
            }

            /**
             * Reset donation form
             */
            vm.resetForm = function() {
                vm.donation = {
                    amount: null,
                    currency: 'USD',
                    payment_method: 'card',
                    donation_type: 'general',
                    donor_name: '',
                    donor_email: '',
                    first_name: '',
                    last_name: '',
                    phone: '',
                    address_street: '',
                    address_city: '',
                    address_state: '',
                    address_zip: '',
                    address_country: 'USA',
                    is_recurring: false,
                    recurring_interval: '',
                    tax_deductible: true,
                    is_anonymous: false
                };

                vm.selectedCampaign = null;
                vm.cardError = '';

                // Re-initialize card element
                if (vm.cardElement) {
                    vm.cardElement.clear();
                }
            };

            /**
             * Format currency for display
             */
            vm.formatCurrency = function(amount, currency) {
                return DonationService.formatCurrency(amount, currency);
            };

            // Watch for authentication changes
            $scope.$on('auth:login', function() {
                loadUserDonations();
                var user = AuthService.getCurrentUser();
                if (user) {
                    vm.donation.donor_email = user.email || '';
                    vm.donation.first_name = user.first_name || '';
                    vm.donation.last_name = user.last_name || '';
                    vm.donation.donor_name = (user.first_name + ' ' + user.last_name).trim();
                }
            });

            $scope.$on('auth:logout', function() {
                vm.userDonations = [];
                vm.resetForm();
            });

        }]);
})();