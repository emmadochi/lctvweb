/**
 * Donation Service
 * Handles donation processing, campaigns, and payment integration
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .factory('DonationService', ['$http', '$q', 'API_BASE', function($http, $q, API_BASE) {

            var service = {};

            // Stripe configuration (replace with your actual keys)
            service.stripePublishableKey = 'pk_test_your_stripe_publishable_key_here';
            service.stripe = null;

            // PayPal configuration
            service.paypalClientId = 'your_paypal_client_id_here';

            // Initialize payment providers
            service.initializePayments = function() {
                // Settings will be loaded dynamically via getPaymentSettings()
            };

            /**
             * Get public payment settings
             */
            service.getPaymentSettings = function() {
                return $http.get(API_BASE + '/donations/settings')
                    .then(function(response) {
                        var settings = response.data.data || {};
                        
                        // Dynamically configure providers if keys are available
                        if (settings.gateway) {
                            var stripeKey = settings.gateway.find(s => s.setting_key.toLowerCase().includes('stripe_publishable'));
                            if (stripeKey && typeof Stripe !== 'undefined') {
                                service.stripePublishableKey = stripeKey.setting_value;
                                service.stripe = Stripe(service.stripePublishableKey);
                            }

                            var paypalId = settings.gateway.find(s => s.setting_key.toLowerCase().includes('paypal_client'));
                            if (paypalId) {
                                service.paypalClientId = paypalId.setting_value;
                            }

                            var paystackKey = settings.gateway.find(s => s.setting_key.toLowerCase().includes('paystack_public'));
                            if (paystackKey) {
                                service.paystackPublicKey = paystackKey.setting_value;
                            }
                        }
                        
                        return settings;
                    })
                    .catch(function(error) {
                        console.error('Error loading payment settings:', error);
                        return {};
                    });
            };

            /**
             * Report manual transfer (Bank/Crypto)
             */
            service.reportManualTransfer = function(data) {
                return $http.post(API_BASE + '/donations/report-transfer', data)
                    .then(function(response) {
                        return response.data;
                    });
            };

            /**
             * Get donation campaigns
             */
            service.getCampaigns = function(activeOnly, limit) {
                var params = {};
                if (activeOnly !== undefined) params.active_only = activeOnly;
                if (limit) params.limit = limit;

                return $http.get(API_BASE + '/donations/campaigns', { params: params })
                    .then(function(response) {
                        return response.data.data || { campaigns: [] };
                    })
                    .catch(function(error) {
                        console.error('Error loading donation campaigns:', error);
                        return { campaigns: [] };
                    });
            };

            /**
             * Process a donation
             */
            service.processDonation = function(donationData) {
                // Validate donation data
                if (!service.validateDonationData(donationData)) {
                    return $q.reject('Invalid donation data');
                }

                // Process payment based on method
                if (donationData.payment_method === 'card' && donationData.payment_provider === 'stripe') {
                    return service.processStripePayment(donationData);
                } else if (donationData.payment_method === 'card' && donationData.payment_provider === 'paystack') {
                    return service.processPaystackPayment(donationData);
                } else if (donationData.payment_method === 'paypal') {
                    return service.processPayPalPayment(donationData);
                } else if (donationData.payment_method === 'bank_transfer' || donationData.payment_method === 'crypto') {
                    return service.reportManualTransfer(donationData);
                } else {
                    return service.processManualDonation(donationData);
                }
            };

            /**
             * Process Stripe payment
             */
            service.processStripePayment = function(donationData) {
                var deferred = $q.defer();

                if (!service.stripe) {
                    deferred.reject('Stripe not initialized');
                    return deferred.promise;
                }

                // Create payment intent on server first
                service.createPaymentIntent(donationData).then(function(intent) {
                    // Confirm payment with Stripe
                    service.stripe.confirmCardPayment(intent.client_secret, {
                        payment_method: {
                            card: donationData.cardElement,
                            billing_details: {
                                name: donationData.donor_name,
                                email: donationData.donor_email
                            }
                        }
                    }).then(function(result) {
                        if (result.error) {
                            deferred.reject(result.error.message);
                        } else {
                            // Payment succeeded
                            donationData.transaction_id = result.paymentIntent.id;
                            donationData.transaction_status = 'completed';
                            donationData.payment_provider = 'stripe';

                            service.submitDonation(donationData).then(function(response) {
                                deferred.resolve(response);
                            }).catch(function(error) {
                                deferred.reject(error);
                            });
                        }
                    });
                }).catch(function(error) {
                    deferred.reject(error);
                });

                return deferred.promise;
            };

            /**
             * Process Paystack payment
             */
            service.processPaystackPayment = function(donationData) {
                var deferred = $q.defer();

                if (typeof PaystackPop === 'undefined' || !service.paystackPublicKey) {
                    deferred.reject('Paystack not initialized');
                    return deferred.promise;
                }

                var handler = PaystackPop.setup({
                    key: service.paystackPublicKey,
                    email: donationData.donor_email,
                    amount: donationData.amount * 100, // in kobo
                    currency: donationData.currency || 'USD',
                    callback: function(response) {
                        donationData.transaction_id = response.reference;
                        donationData.transaction_status = 'completed';
                        donationData.payment_provider = 'paystack';

                        service.submitDonation(donationData).then(function(resp) {
                            deferred.resolve(resp);
                        }).catch(function(err) {
                            deferred.reject(err);
                        });
                    },
                    onClose: function() {
                        deferred.reject('Payment window closed');
                    }
                });
                handler.openIframe();

                return deferred.promise;
            };

            /**
             * Process PayPal payment
             */
            service.processPayPalPayment = function(donationData) {
                var deferred = $q.defer();

                // PayPal payment processing would go here
                // For now, just submit as manual donation
                donationData.payment_provider = 'paypal';
                donationData.transaction_status = 'pending';

                service.submitDonation(donationData).then(function(response) {
                    deferred.resolve(response);
                }).catch(function(error) {
                    deferred.reject(error);
                });

                return deferred.promise;
            };

            /**
             * Process manual donation (check, cash, etc.)
             */
            service.processManualDonation = function(donationData) {
                donationData.payment_provider = 'manual';
                donationData.transaction_status = 'pending';

                return service.submitDonation(donationData);
            };

            /**
             * Create payment intent (for Stripe)
             */
            service.createPaymentIntent = function(donationData) {
                return $http.post(API_BASE + '/payments/create-intent', {
                    amount: donationData.amount,
                    currency: donationData.currency || 'usd',
                    donor_email: donationData.donor_email,
                    donor_name: donationData.donor_name
                }).then(function(response) {
                    return response.data.data;
                });
            };

            /**
             * Submit donation to server
             */
            service.submitDonation = function(donationData) {
                return $http.post(API_BASE + '/donations', donationData)
                    .then(function(response) {
                        return response.data.data;
                    });
            };

            /**
             * Get user's donation history
             */
            service.getUserDonations = function(limit, offset) {
                var params = {};
                if (limit) params.limit = limit;
                if (offset) params.offset = offset;

                return $http.get(API_BASE + '/donations', { params: params })
                    .then(function(response) {
                        return response.data.data || { donations: [] };
                    })
                    .catch(function(error) {
                        console.error('Error loading user donations:', error);
                        return { donations: [] };
                    });
            };

            /**
             * Generate tax receipt
             */
            service.generateTaxReceipt = function(donationId) {
                return $http.post(API_BASE + '/donations/receipt', {
                    donation_id: donationId
                }).then(function(response) {
                    return response.data.data;
                });
            };

            /**
             * Validate donation data
             */
            service.validateDonationData = function(data) {
                if (!data.donor_name || !data.donor_email || !data.amount) {
                    return false;
                }

                if (data.amount <= 0) {
                    return false;
                }

                if (!service.isValidEmail(data.donor_email)) {
                    return false;
                }

                if (data.is_recurring && !data.recurring_interval) {
                    return false;
                }

                return true;
            };

            /**
             * Validate email address
             */
            service.isValidEmail = function(email) {
                var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return emailRegex.test(email);
            };

            /**
             * Format currency amount
             */
            service.formatCurrency = function(amount, currency) {
                currency = currency || 'USD';
                return new Intl.NumberFormat('en-US', {
                    style: 'currency',
                    currency: currency.toUpperCase()
                }).format(amount);
            };

            /**
             * Get donation types
             */
            service.getDonationTypes = function() {
                return [
                    { id: 'general', name: 'General Fund', description: 'Support our general ministry operations' },
                    { id: 'building_fund', name: 'Building Fund', description: 'Help us expand our facilities' },
                    { id: 'missions', name: 'Missions', description: 'Support global mission work' },
                    { id: 'youth', name: 'Youth Ministry', description: 'Invest in our youth programs' },
                    { id: 'children', name: 'Children\'s Ministry', description: 'Support our children\'s programs' },
                    { id: 'outreach', name: 'Community Outreach', description: 'Support local community programs' },
                    { id: 'other', name: 'Other', description: 'Specify your donation purpose' }
                ];
            };

            /**
             * Get recurring intervals
             */
            service.getRecurringIntervals = function() {
                return [
                    { id: 'monthly', name: 'Monthly', description: 'Every month' },
                    { id: 'quarterly', name: 'Quarterly', description: 'Every 3 months' },
                    { id: 'yearly', name: 'Yearly', description: 'Every year' }
                ];
            };

            /**
             * Initialize PayPal buttons
             */
            service.initializePayPal = function() {
                if (typeof paypal === 'undefined') return;

                paypal.Buttons({
                    createOrder: function(data, actions) {
                        // Create PayPal order
                        return actions.order.create({
                            purchase_units: [{
                                amount: {
                                    value: service.currentDonation.amount.toString()
                                }
                            }]
                        });
                    },
                    onApprove: function(data, actions) {
                        return actions.order.capture().then(function(details) {
                            // Process successful PayPal payment
                            service.currentDonation.transaction_id = data.orderID;
                            service.currentDonation.transaction_status = 'completed';
                            service.currentDonation.payment_provider = 'paypal';

                            return service.submitDonation(service.currentDonation);
                        });
                    }
                }).render('#paypal-button-container');
            };

            /**
             * Get supported currencies
             */
            service.getSupportedCurrencies = function() {
                return [
                    { code: 'USD', name: 'USD - United States Dollar', symbol: '$' },
                    { code: 'GBP', name: 'GBP - British Pound', symbol: '£' },
                    { code: 'EUR', name: 'EUR - Euro', symbol: '€' },
                    { code: 'NGN', name: 'NGN - Nigerian Naira', symbol: '₦' }
                ];
            };

            /**
             * Get currency symbol by code
             */
            service.getCurrencySymbol = function(code) {
                var currencies = service.getSupportedCurrencies();
                var currency = currencies.find(c => c.code === code.toUpperCase());
                return currency ? currency.symbol : '$';
            };

            /**
             * Calculate suggested donation amounts
             */
            service.getSuggestedAmounts = function() {
                return [
                    { amount: 25, label: 'Basic Support' },
                    { amount: 50, label: 'Regular Support' },
                    { amount: 100, label: 'Generous Support' },
                    { amount: 250, label: 'Major Support' },
                    { amount: 500, label: 'Leadership Support' }
                ];
            };

            /**
             * Get impact information for donations
             */
            service.getImpactInfo = function() {
                return {
                    25: 'Provides meals for 10 families',
                    50: 'Supports one week of youth activities',
                    100: 'Funds a community outreach event',
                    250: 'Supports one month of mission work',
                    500: 'Builds relationships with 50 community members'
                };
            };

            // Initialize on service load
            service.initializePayments();

            return service;
        }]);
})();