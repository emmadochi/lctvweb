<?php
/**
 * Video Purchase Model
 * Handles database operations for video purchases
 */

require_once __DIR__ . '/../config/database.php';

class VideoPurchase {
    
    /**
     * Create a new purchase record (initially pending)
     */
    public static function create($data) {
        $conn = getDBConnection();
        
        $stmt = $conn->prepare("INSERT INTO video_purchases (user_id, video_id, amount, currency, transaction_id, payment_status) VALUES (?, ?, ?, ?, ?, 'pending')");
        
        $amount = (float)$data['amount'];
        $currency = $data['currency'] ?? 'USD';
        
        $stmt->bind_param("iidss", 
            $data['user_id'], 
            $data['video_id'], 
            $amount, 
            $currency, 
            $data['transaction_id']
        );
        
        if ($stmt->execute()) {
            return $conn->insert_id;
        }
        
        return false;
    }
    
    /**
     * Update purchase status
     */
    public static function updateStatus($transactionId, $status) {
        $conn = getDBConnection();
        
        $stmt = $conn->prepare("UPDATE video_purchases SET payment_status = ? WHERE transaction_id = ?");
        $stmt->bind_param("ss", $status, $transactionId);
        
        return $stmt->execute();
    }
    
    /**
     * Get purchase by transaction ID
     */
    public static function getByTransactionId($transactionId) {
        $conn = getDBConnection();
        
        $stmt = $conn->prepare("SELECT * FROM video_purchases WHERE transaction_id = ?");
        $stmt->bind_param("s", $transactionId);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_assoc();
    }
    
    /**
     * Get user's purchased videos
     */
    public static function getPurchasedVideos($userId) {
        $conn = getDBConnection();
        
        $stmt = $conn->prepare("SELECT v.* FROM videos v 
                               JOIN video_purchases vp ON v.id = vp.video_id 
                               WHERE vp.user_id = ? AND vp.payment_status = 'completed'");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $videos = [];
        while ($row = $result->fetch_assoc()) {
            $videos[] = $row;
        }
        
        return $videos;
    }
}
