<?php
/**
 * PaymentSetting Model
 * Handles configuration for payment gateways, bank accounts, and crypto wallets
 */

require_once __DIR__ . '/../config/database.php';

class PaymentSetting {
    private static $table = 'payment_settings';

    /**
     * Get all active settings grouped by their type
     */
    public static function getAllActiveGrouped() {
        $conn = getDBConnection();
        $sql = "SELECT id, setting_key, setting_value, setting_group, is_active FROM " . self::$table . " WHERE is_active = 1";
        $result = $conn->query($sql);
        
        $settings = [
            'gateway' => [],
            'bank' => [],
            'crypto' => [],
            'giving_type' => [],
            'general' => []
        ];

        while ($row = $result->fetch_assoc()) {
            $group = $row['setting_group'];
            if (array_key_exists($group, $settings)) {
                $settings[$group][] = $row;
            }
        }

        return $settings;
    }

    /**
     * Get a specific setting by key
     */
    public static function getByKey($key) {
        $conn = getDBConnection();
        $sql = "SELECT * FROM " . self::$table . " WHERE setting_key = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $key);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Create or update a setting
     */
    public static function save($key, $value, $group, $is_active = 1, $is_encrypted = 0, $label = null) {
        $conn = getDBConnection();
        
        // Use key as default label if not provided
        if ($label === null) {
            $label = $key;
        }
        
        // Check if exists
        $existing = self::getByKey($key);
        
        if ($existing) {
            $sql = "UPDATE " . self::$table . " SET setting_value = ?, setting_group = ?, setting_label = ?, is_active = ?, is_encrypted = ?, updated_at = NOW() WHERE setting_key = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssiis", $value, $group, $label, $is_active, $is_encrypted, $key);
        } else {
            $sql = "INSERT INTO " . self::$table . " (setting_key, setting_value, setting_group, setting_label, is_active, is_encrypted) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssii", $key, $value, $group, $label, $is_active, $is_encrypted);
        }

        return $stmt->execute();
    }

    /**
     * Delete a setting
     */
    public static function delete($key) {
        $conn = getDBConnection();
        $sql = "DELETE FROM " . self::$table . " WHERE setting_key = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $key);
        return $stmt->execute();
    }

    /**
     * Delete by ID
     */
    public static function deleteById($id) {
        $conn = getDBConnection();
        $sql = "DELETE FROM " . self::$table . " WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    /**
     * Get settings by group
     */
    public static function getByGroup($group) {
        $conn = getDBConnection();
        $sql = "SELECT * FROM " . self::$table . " WHERE setting_group = ? ORDER BY created_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $group);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $settings = [];
        while ($row = $result->fetch_assoc()) {
            $settings[] = $row;
        }
        return $settings;
    }
}
?>
