<?php
/**
 * Video Purchase Controller
 * Handles video purchase transactions and Paystack verification
 */

require_once __DIR__ . '/../models/VideoPurchase.php';
require_once __DIR__ . '/../models/Video.php';
require_once __DIR__ . '/../utils/Auth.php';
require_once __DIR__ . '/../utils/Response.php';

class VideoPurchaseController {

    /**
     * Initiate a video purchase
     */
    public static function initiatePurchase() {
        try {
            Auth::requireAuth();
            $user = Auth::getUserFromToken();
            
            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);
            
            $videoId = $data['video_id'] ?? null;
            
            if (!$videoId) {
                return Response::error('Video ID is required', 400);
            }
            
            // Pass the userId to getById to ensure it's correctly formatted
            $video = Video::getById($videoId, $user['user_id']);
            if (!$video || !$video['is_premium']) {
                error_log("initiatePurchase - Invalid video: " . ($video ? 'is_premium=0' : 'not_found') . " for ID: $videoId");
                return Response::error('Invalid or non-premium video', 400);
            }
            
            // Check if already purchased
            if (Video::hasUserPurchased($videoId, $user['user_id'])) {
                // Return a dummy reference or a status that the mobile app handles
                // But better to return success with a flag that allows bypass
                return Response::success([
                    'reference' => 'ALREADY_PURCHASED',
                    'amount' => 0,
                    'already_purchased' => true
                ], 'Video already purchased');
            }
            
            // Generate a unique transaction reference
            $reference = 'VP-' . $videoId . '-' . $user['user_id'] . '-' . time();
            
            // Create pending purchase record
            $purchaseId = VideoPurchase::create([
                'user_id' => $user['user_id'],
                'video_id' => $videoId,
                'amount' => $video['price'],
                'currency' => $data['currency'] ?? 'USD',
                'transaction_id' => $reference
            ]);
            
            if (!$purchaseId) {
                error_log("initiatePurchase - Failed to create purchase record in database for video: $videoId");
                return Response::error('Database error: Could not create purchase record', 500);
            }
            
            return Response::success([
                'reference' => $reference,
                'amount' => $video['price'],
                'email' => $user['email'],
                'video_title' => $video['title']
            ]);
            
        } catch (Exception $e) {
            error_log("VideoPurchaseController::initiatePurchase - Error: " . $e->getMessage());
            return Response::error('Internal failure: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Verify a video purchase (usually called after client receipt)
     */
    public static function verifyPurchase() {
        try {
            Auth::requireAuth();
            $user = Auth::getUserFromToken();
            
            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);
            
            $reference = $data['reference'] ?? null;
            
            if (!$reference) {
                return Response::error('Transaction reference is required', 400);
            }
            
            // In a real production app, we would verify with Paystack API here
            // For now, we trust the client (or assume a webhook will also come)
            // But let's build the structure for Paystack verification
            
            $success = self::verifyWithPaystack($reference);
            
            if ($success) {
                VideoPurchase::updateStatus($reference, 'completed');
                return Response::success(['status' => 'completed'], 'Purchase verified successfully');
            } else {
                return Response::error('Payment verification failed', 400);
            }
            
        } catch (Exception $e) {
            error_log("VideoPurchaseController::verifyPurchase - Error: " . $e->getMessage());
            return Response::error('Verification failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Helper to verify with Paystack API
     */
    private static function verifyWithPaystack($reference) {
        // Get secret key from settings
        $conn = getDBConnection();
        $result = $conn->query("SELECT setting_value FROM payment_settings WHERE setting_key = 'paystack_secret_key'");
        $paystackSecret = $result ? $result->fetch_assoc()['setting_value'] : null;

        if (!$paystackSecret) {
            error_log("Paystack secret key not found");
            return false;
        }

        $url = "https://api.paystack.co/transaction/verify/" . rawurlencode($reference);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer " . $paystackSecret
        ));
        
        $request = curl_exec($ch);
        curl_close($ch);

        if ($request) {
            $result = json_decode($request, true);
            if($result && $result['status'] && $result['data']['status'] === 'success'){
                return true;
            }
        }

        return false;
    }
}
