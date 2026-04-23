<?php
/**
 * Donation Controller
 * Handles donation processing, campaigns, and donor management
 */

require_once __DIR__ . '/../models/Donation.php';
require_once __DIR__ . '/../utils/Auth.php';
require_once __DIR__ . '/../utils/Response.php';

class DonationController {
    /**
     * Process a new donation
     */
    public static function processDonation() {
        try {
            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return Response::error('Invalid JSON data', 400);
            }

            // Validate required fields
            if (empty($data['donor_name']) || empty($data['donor_email']) || empty($data['amount'])) {
                return Response::error('Missing required fields: donor_name, donor_email, amount', 400);
            }

            // Get current user if authenticated
            $userId = null;
            try {
                $user = Auth::getUserFromToken();
                $userId = $user ? $user['user_id'] : null;
            } catch (Exception $e) {
                // User not authenticated - that's okay for donations
            }

            // Prepare donation data
            $donationData = [
                'user_id' => $userId,
                'donor_name' => trim($data['donor_name']),
                'donor_email' => trim($data['donor_email']),
                'amount' => (float)$data['amount'],
                'currency' => $data['currency'] ?? 'USD',
                'payment_method' => $data['payment_method'] ?? 'card',
                'payment_provider' => $data['payment_provider'] ?? 'stripe',
                'transaction_id' => $data['transaction_id'] ?? null,
                'transaction_status' => $data['transaction_status'] ?? 'pending',
                'is_recurring' => (bool)($data['is_recurring'] ?? false),
                'recurring_interval' => $data['recurring_interval'] ?? null,
                'donation_type' => $data['donation_type'] ?? 'general',
                'donation_purpose' => $data['donation_purpose'] ?? '',
                'is_anonymous' => (bool)($data['is_anonymous'] ?? false),
                'tax_deductible' => (bool)($data['tax_deductible'] ?? true),
                'notes' => $data['notes'] ?? '',
                'ip_address' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'utm_source' => $data['utm_source'] ?? '',
                'utm_medium' => $data['utm_medium'] ?? '',
                'utm_campaign' => $data['utm_campaign'] ?? ''
            ];

            // Add donor information
            if (!empty($data['first_name'])) $donationData['first_name'] = trim($data['first_name']);
            if (!empty($data['last_name'])) $donationData['last_name'] = trim($data['last_name']);
            if (!empty($data['phone'])) $donationData['phone'] = trim($data['phone']);
            if (!empty($data['address_street'])) $donationData['address_street'] = trim($data['address_street']);
            if (!empty($data['address_city'])) $donationData['address_city'] = trim($data['address_city']);
            if (!empty($data['address_state'])) $donationData['address_state'] = trim($data['address_state']);
            if (!empty($data['address_zip'])) $donationData['address_zip'] = trim($data['address_zip']);
            if (!empty($data['address_country'])) $donationData['address_country'] = trim($data['address_country']);

            // Process donation
            $donationId = Donation::create($donationData);

            if ($donationId) {
                return Response::success([
                    'donation_id' => $donationId,
                    'message' => 'Donation processed successfully',
                    'amount' => $donationData['amount'],
                    'currency' => $donationData['currency']
                ]);
            } else {
                return Response::error('Failed to process donation', 500);
            }

        } catch (Exception $e) {
            error_log("DonationController::processDonation - Error: " . $e->getMessage());
            return Response::error('Failed to process donation: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get donation campaigns
     */
    public static function getCampaigns() {
        try {
            $activeOnly = isset($_GET['active_only']) ? (bool)$_GET['active_only'] : true;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

            $campaigns = Donation::getCampaigns($activeOnly, $limit);

            return Response::success([
                'campaigns' => $campaigns,
                'total' => count($campaigns)
            ]);

        } catch (Exception $e) {
            error_log("DonationController::getCampaigns - Error: " . $e->getMessage());
            return Response::error('Failed to get campaigns: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get user's donation history
     */
    public static function getUserDonations() {
        try {
            // Require authentication
            Auth::requireAuth();
            $user = Auth::getUserFromToken();

            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

            $donations = Donation::getByUser($user['user_id'], $limit, $offset);

            return Response::success([
                'donations' => $donations,
                'total' => count($donations),
                'limit' => $limit,
                'offset' => $offset
            ]);

        } catch (Exception $e) {
            error_log("DonationController::getUserDonations - Error: " . $e->getMessage());
            return Response::error('Failed to get donation history: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get donation statistics (admin only)
     */
    public static function getStats() {
        try {
            // Require admin authentication
            Auth::requireAuth();
            $user = Auth::getUserFromToken();

            if (!$user || $user['role'] !== 'admin') {
                return Response::forbidden('Admin access required');
            }

            $period = isset($_GET['period']) ? (int)$_GET['period'] : 30;

            $stats = Donation::getStats($period);

            return Response::success($stats);

        } catch (Exception $e) {
            error_log("DonationController::getStats - Error: " . $e->getMessage());
            return Response::error('Failed to get donation statistics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Generate tax receipt
     */
    public static function generateReceipt() {
        try {
            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return Response::error('Invalid JSON data', 400);
            }

            $donationId = $data['donation_id'] ?? null;

            if (!$donationId) {
                return Response::error('Donation ID is required', 400);
            }

            $receiptNumber = Donation::generateTaxReceipt($donationId);

            if ($receiptNumber) {
                return Response::success([
                    'receipt_number' => $receiptNumber,
                    'message' => 'Tax receipt generated successfully'
                ]);
            } else {
                return Response::error('Failed to generate tax receipt', 400);
            }

        } catch (Exception $e) {
            error_log("DonationController::generateReceipt - Error: " . $e->getMessage());
            return Response::error('Failed to generate receipt: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Process recurring donations (admin/cron job)
     */
    public static function processRecurring() {
        try {
            // This should be called by a cron job, so we'll allow it without authentication
            // In production, you might want to add IP restrictions or API key authentication

            $result = Donation::processRecurringDonations();

            return Response::success([
                'processed' => $result['processed'],
                'failed' => $result['failed'],
                'message' => 'Recurring donations processed'
            ]);

        } catch (Exception $e) {
            error_log("DonationController::processRecurring - Error: " . $e->getMessage());
            return Response::error('Failed to process recurring donations: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Webhook handler for payment providers
     */
    public static function handleWebhook() {
        try {
            $rawInput = file_get_contents('php://input');
            $headers = getallheaders();

            // Identify payment provider (case-insensitive header check)
            $provider = null;
            $lowerHeaders = array_change_key_case($headers, CASE_LOWER);
            
            if (isset($lowerHeaders['stripe-signature'])) {
                $provider = 'stripe';
            } elseif (isset($lowerHeaders['paypal-transmission-id'])) {
                $provider = 'paypal';
            } elseif (isset($lowerHeaders['x-paystack-signature'])) {
                $provider = 'paystack';
            }

            if (!$provider) {
                return Response::error('Unknown payment provider', 400);
            }

            // Process webhook based on provider
            switch ($provider) {
                case 'stripe':
                    $result = self::handleStripeWebhook($rawInput, $headers);
                    break;
                case 'paypal':
                    $result = self::handlePayPalWebhook($rawInput, $headers);
                    break;
                case 'paystack':
                    $result = self::handlePaystackWebhook($rawInput, $headers);
                    break;
                default:
                    return Response::error('Unsupported payment provider', 400);
            }

            return Response::success($result);

        } catch (Exception $e) {
            error_log("DonationController::handleWebhook - Error: " . $e->getMessage());
            return Response::error('Webhook processing failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Handle Stripe webhook
     */
    private static function handleStripeWebhook($payload, $headers) {
        // Verify Stripe signature
        $endpointSecret = getenv('STRIPE_WEBHOOK_SECRET') ?: 'whsec_test_secret';

        try {
            $event = json_decode($payload, true);

            switch ($event['type']) {
                case 'payment_intent.succeeded':
                    $paymentIntent = $event['data']['object'];
                    // Update donation status
                    Donation::updateStatus(null, 'completed', $paymentIntent['id']);
                    break;

                case 'payment_intent.payment_failed':
                    $paymentIntent = $event['data']['object'];
                    Donation::updateStatus(null, 'failed', $paymentIntent['id']);
                    break;

                case 'invoice.payment_succeeded':
                    // Handle recurring payment success
                    $invoice = $event['data']['object'];
                    // Process recurring donation
                    break;
            }

            return ['processed' => true, 'event_type' => $event['type']];

        } catch (Exception $e) {
            throw new Exception('Failed to process Stripe webhook: ' . $e->getMessage());
        }
    }

    /**
     * Handle PayPal webhook
     */
    private static function handlePayPalWebhook($payload, $headers) {
        // Verify PayPal signature and process webhook
        // This would integrate with PayPal's webhook verification

        $event = json_decode($payload, true);

        switch ($event['event_type']) {
            case 'PAYMENT.CAPTURE.COMPLETED':
                // Update donation status
                $resource = $event['resource'];
                Donation::updateStatus(null, 'completed', $resource['id']);
                break;

            case 'PAYMENT.CAPTURE.DENIED':
                $resource = $event['resource'];
                Donation::updateStatus(null, 'failed', $resource['id']);
                break;
        }

        return ['processed' => true, 'event_type' => $event['event_type']];
    }

    private static function handlePaystackWebhook($payload, $headers) {
        $event = json_decode($payload, true);
        
        require_once __DIR__ . '/../models/PaymentSetting.php';
        $setting = PaymentSetting::getByKey('paystack_secret_key');
        $paystackSecret = $setting && $setting['setting_value'] ? $setting['setting_value'] : getenv('PAYSTACK_SECRET_KEY');
        
        // Find signature header (case-insensitive)
        $signature = null;
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'x-paystack-signature') {
                $signature = $value;
                break;
            }
        }

        // Verify signature
        if ($paystackSecret && $signature) {
            if ($signature !== hash_hmac('sha512', $payload, $paystackSecret)) {
                throw new Exception('Invalid Paystack signature');
            }
        }
        
        if (isset($event['event']) && $event['event'] === 'charge.success') {
            $data = $event['data'];
            // Update donation status
            Donation::updateStatus(null, 'completed', $data['reference']);
        }

        return ['processed' => true, 'event' => $event['event'] ?? 'unknown'];
    }

    /**
     * Report a manual transfer (Bank or Crypto)
     */
    public static function reportManualTransfer() {
        try {
            // Support both JSON and Multipart (for file uploads)
            $data = [];
            $contentType = $_SERVER["CONTENT_TYPE"] ?? '';
            
            if (strpos($contentType, 'application/json') !== false) {
                $rawInput = file_get_contents('php://input');
                $data = json_decode($rawInput, true);
            } else {
                $data = $_POST;
            }

            if (empty($data['donor_email']) || empty($data['amount'])) {
                return Response::error('Missing required fields: donor_email, amount', 400);
            }

            $receiptUrl = null;
            if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
                // Ensure the path is relative to the backend root correctly
                $baseDir = dirname(__DIR__);
                $uploadDir = $baseDir . '/uploads/receipts/';
                
                if (!is_dir($uploadDir)) {
                    if (!mkdir($uploadDir, 0755, true)) {
                        error_log("DonationController::reportManualTransfer - Failed to create directory: " . $uploadDir);
                        return Response::error('Server error: Could not create upload directory.', 500);
                    }
                }

                $fileTmpPath = $_FILES['receipt']['tmp_name'];
                $fileName = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", $_FILES['receipt']['name']);
                $destPath = $uploadDir . $fileName;

                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    $receiptUrl = 'uploads/receipts/' . $fileName;
                } else {
                    error_log("DonationController::reportManualTransfer - Failed to move uploaded file to: " . $destPath);
                    return Response::error('Server error: Failed to save receipt file.', 500);
                }
            } elseif (isset($_FILES['receipt']) && $_FILES['receipt']['error'] !== UPLOAD_ERR_NO_FILE) {
                error_log("DonationController::reportManualTransfer - File upload error code: " . $_FILES['receipt']['error']);
                return Response::error('File upload failed. Error code: ' . $_FILES['receipt']['error'], 400);
            }

            // Prepare donation data as "pending"
            $donationData = [
                'user_id' => !empty($data['user_id']) ? (int)$data['user_id'] : null,
                'donor_name' => !empty($data['donor_name']) ? $data['donor_name'] : 'Manual Donor',
                'donor_email' => $data['donor_email'],
                'amount' => (float)$data['amount'],
                'currency' => $data['currency'] ?? 'USD',
                'payment_method' => $data['payment_method'] ?? 'manual',
                'payment_provider' => $data['payment_method'] ?? 'manual',
                'transaction_id' => $data['transaction_id'] ?? 'MAN-' . time(),
                'receipt_url' => $receiptUrl,
                'transaction_status' => 'pending',
                'donation_purpose' => $data['donation_purpose'] ?? 'General',
                'campaign_id' => !empty($data['campaign_id']) ? (int)$data['campaign_id'] : null,
                'notes' => $data['notes'] ?? '',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
            ];

            $donationId = Donation::create($donationData);

            if ($donationId) {
                return Response::success(['donation_id' => $donationId], 'Transfer reported successfully. We will verify and update your status soon.');
            } else {
                error_log("DonationController::reportManualTransfer - Donation::create returned false.");
                return Response::error('Failed to save donation record to database.', 500);
            }
        } catch (Exception $e) {
            error_log("DonationController::reportManualTransfer - Exception: " . $e->getMessage());
            return Response::error('Error reporting transfer: ' . $e->getMessage(), 500);
        }
    }
    /**
     * Approve a pending donation (admin only)
     */
    public static function approveDonation() {
        try {
            // Require admin authentication
            Auth::requireAuth();
            $user = Auth::getUserFromToken();

            if (!$user || ($user['role'] !== 'admin' && $user['role'] !== 'super_admin')) {
                return Response::forbidden('Admin access required');
            }

            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return Response::error('Invalid JSON data', 400);
            }

            $donationId = $data['donation_id'] ?? null;

            if (!$donationId) {
                return Response::error('Donation ID is required', 400);
            }

            // Verify donation exists and is pending
            $donation = Donation::find($donationId);
            if (!$donation) {
                return Response::notFound('Donation not found');
            }

            if ($donation['transaction_status'] === 'completed') {
                return Response::error('Donation is already completed', 400);
            }

            // Update status
            $success = Donation::updateStatus($donationId, 'completed');

            if ($success) {
                return Response::success([
                    'donation_id' => $donationId,
                    'status' => 'completed',
                    'message' => 'Donation approved successfully'
                ]);
            } else {
                return Response::error('Failed to approve donation', 500);
            }

        } catch (Exception $e) {
            error_log("DonationController::approveDonation - Error: " . $e->getMessage());
            return Response::error('Failed to approve donation: ' . $e->getMessage(), 500);
        }
    }
    public static function createCampaign() {
        try {
            // Require admin authentication
            Auth::requireAuth();
            $user = Auth::getUserFromToken();

            if (!$user || $user['role'] !== 'admin') {
                return Response::forbidden('Admin access required');
            }

            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return Response::error('Invalid JSON data', 400);
            }

            // Validate required fields
            if (empty($data['title']) || empty($data['goal_amount'])) {
                return Response::error('Missing required fields: title, goal_amount', 400);
            }

            $conn = getDBConnection();

            $sql = "INSERT INTO donation_campaigns
                    (title, description, goal_amount, current_amount, currency, start_date, end_date, campaign_type, is_active, is_featured, image_url, video_url, thank_you_message, impact_description, created_by)
                    VALUES (?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);

            $title = trim($data['title']);
            $description = $data['description'] ?? '';
            $goalAmount = (float)$data['goal_amount'];
            $currency = $data['currency'] ?? 'USD';
            $startDate = $data['start_date'] ?? date('Y-m-d');
            $endDate = $data['end_date'] ?? null;
            $campaignType = $data['campaign_type'] ?? 'general';
            $isActive = (bool)($data['is_active'] ?? true);
            $isFeatured = (bool)($data['is_featured'] ?? false);
            $imageUrl = $data['image_url'] ?? '';
            $videoUrl = $data['video_url'] ?? '';
            $thankYouMessage = $data['thank_you_message'] ?? '';
            $impactDescription = $data['impact_description'] ?? '';

            $stmt->bind_param("sdsssssiissssssi", $title, $description, $goalAmount, $currency, $startDate, $endDate, $campaignType, $isActive, $isFeatured, $imageUrl, $videoUrl, $thankYouMessage, $impactDescription, $user['user_id']);

            if ($stmt->execute()) {
                $campaignId = $conn->insert_id;

                return Response::success([
                    'campaign_id' => $campaignId,
                    'message' => 'Campaign created successfully'
                ]);
            } else {
                return Response::error('Failed to create campaign', 500);
            }

        } catch (Exception $e) {
            error_log("DonationController::createCampaign - Error: " . $e->getMessage());
            return Response::error('Failed to create campaign: ' . $e->getMessage(), 500);
        }
    }
}
?>