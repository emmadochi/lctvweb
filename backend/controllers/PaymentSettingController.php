<?php
/**
 * PaymentSetting Controller
 * Handles administration and public access to payment configurations
 */

require_once __DIR__ . '/../models/PaymentSetting.php';
require_once __DIR__ . '/../utils/Auth.php';
require_once __DIR__ . '/../utils/Response.php';

class PaymentSettingController {
    /**
     * Get active payment methods for public use (Web/Mobile)
     */
    public static function getPublicSettings() {
        try {
            $settings = PaymentSetting::getAllActiveGrouped();
            
            // Mask sensitive data in gateway settings
            foreach ($settings['gateway'] as &$gateway) {
                // If it's a key/secret, mask it or remove it from public view
                // Usually we only send the type and provider name to frontend, 
                // client-side keys (like Stripe Publishable Key) are kept.
                // For now, we'll filters keys that shouldn't be public.
                if (stripos($gateway['setting_key'], 'secret') !== false || stripos($gateway['setting_key'], 'private') !== false) {
                    $gateway['setting_value'] = '********';
                }
            }

            return Response::success($settings);
        } catch (Exception $e) {
            return Response::error('Failed to fetch payment methods: ' . $e->getMessage());
        }
    }

    /**
     * Get all settings (Admin Only)
     */
    public static function getAllSettings() {
        try {
            Auth::requireAuth();
            $user = Auth::getUserFromToken();
            if ($user['role'] !== 'admin' && $user['role'] !== 'super_admin') {
                return Response::forbidden('Admin access required');
            }

            $settings = [
                'gateway' => PaymentSetting::getByGroup('gateway'),
                'bank' => PaymentSetting::getByGroup('bank'),
                'crypto' => PaymentSetting::getByGroup('crypto'),
                'general' => PaymentSetting::getByGroup('general')
            ];

            return Response::success($settings);
        } catch (Exception $e) {
            return Response::error('Failed to fetch settings: ' . $e->getMessage());
        }
    }

    /**
     * Save or update a setting (Admin Only)
     */
    public static function saveSetting() {
        try {
            Auth::requireAuth();
            $user = Auth::getUserFromToken();
            if ($user['role'] !== 'admin' && $user['role'] !== 'super_admin') {
                return Response::forbidden('Admin access required');
            }

            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);

            if (!isset($data['key']) || !isset($data['value']) || !isset($data['group'])) {
                return Response::error('Missing required fields: key, value, group');
            }

            $isActive = isset($data['is_active']) ? (int)$data['is_active'] : 1;
            $isEncrypted = isset($data['is_encrypted']) ? (int)$data['is_encrypted'] : 0;
            $label = isset($data['label']) ? $data['label'] : $data['key'];

            $result = PaymentSetting::save(
                $data['key'],
                $data['value'],
                $data['group'],
                $isActive,
                $isEncrypted,
                $label
            );

            if ($result) {
                return Response::success(null, 'Setting saved successfully');
            } else {
                return Response::error('Failed to save setting');
            }
        } catch (Exception $e) {
            return Response::error('Error saving setting: ' . $e->getMessage());
        }
    }

    /**
     * Delete a setting (Admin Only)
     */
    public static function deleteSetting() {
        try {
            Auth::requireAuth();
            $user = Auth::getUserFromToken();
            if ($user['role'] !== 'admin' && $user['role'] !== 'super_admin') {
                return Response::forbidden('Admin access required');
            }

            $key = $_GET['key'] ?? null;
            $id = $_GET['id'] ?? null;

            if ($id) {
                $result = PaymentSetting::deleteById($id);
            } elseif ($key) {
                $result = PaymentSetting::delete($key);
            } else {
                return Response::error('Missing setting identifier (id or key)');
            }

            if ($result) {
                return Response::success(null, 'Setting deleted successfully');
            } else {
                return Response::error('Failed to delete setting');
            }
        } catch (Exception $e) {
            return Response::error('Error deleting setting: ' . $e->getMessage());
        }
    }
}
?>
