<?php
// Test Push Notification Script
// Run this directly in the browser to diagnose FCM errors on the live server

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/PushSubscription.php';

header('Content-Type: text/plain');
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "--- LCMTV Push Notification Diagnostic Test ---\n\n";

// 1. Check Service Account JSON
$jsonPath = __DIR__ . '/../config/firebase-service-account.json';
if (!file_exists($jsonPath)) {
    echo "ERROR: firebase-service-account.json is MISSING!\n";
    echo "Expected path: $jsonPath\n";
    exit;
} else {
    echo "OK: firebase-service-account.json found.\n";
}

// 2. Fetch the latest token from DB
$conn = getDBConnection();
$result = $conn->query("SELECT * FROM push_subscriptions ORDER BY id DESC LIMIT 1");
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $token = $row['endpoint'];
    echo "OK: Found a subscription token in the database for User ID: " . $row['user_id'] . "\n";
    echo "Token (first 30 chars): " . substr($token, 0, 30) . "...\n\n";
    
    // Clean FCM token
    $actualToken = str_replace('fcm:', '', $token);
    
    echo "Attempting to send a test push notification to this device...\n";
    
    // We will simulate PushSubscription::sendFcmV1Notification but with debug output
    try {
        // Prepare payload to diagnose json_decode
        $credentials = json_decode(file_get_contents($jsonPath), true);
        if (!$credentials) {
            echo "ERROR: json_decode failed! Your firebase-service-account.json is not valid JSON.\n";
            echo "JSON Error: " . json_last_error_msg() . "\n";
            exit;
        }
        
        $pk = $credentials['private_key'] ?? '';
        echo "Private Key Check:\n";
        echo "- Length: " . strlen($pk) . "\n";
        echo "- Starts with BEGIN PRIVATE KEY: " . (strpos($pk, '-----BEGIN PRIVATE KEY-----') !== false ? 'YES' : 'NO') . "\n";
        echo "- Contains actual newlines: " . (strpos($pk, "\n") !== false ? 'YES' : 'NO') . "\n";
        echo "- Contains literal '\\n': " . (strpos($pk, '\n') !== false ? 'YES' : 'NO') . "\n\n";

        // Reflection to access private getFcmAccessToken
        $method = new ReflectionMethod('PushSubscription', 'getFcmAccessToken');
        $method->setAccessible(true);
        $accessToken = $method->invoke(null);
        
        if (!$accessToken) {
            echo "ERROR: Failed to generate FCM Access Token. Check your service account JSON file validity.\n";
            exit;
        }
        echo "OK: Successfully generated OAuth2 Access Token for FCM.\n\n";
        
        // Prepare payload
        $credentials = json_decode(file_get_contents($jsonPath), true);
        $projectId = $credentials['project_id'] ?? null;
        
        $url = 'https://fcm.googleapis.com/v1/projects/' . $projectId . '/messages:send';
        
        $message = [
            'message' => [
                'token' => $actualToken,
                'notification' => [
                    'title' => "Diagnostic Test",
                    'body' => "If you see this, push notifications are working perfectly!"
                ],
                'android' => [
                    'priority' => 'high',
                    'notification' => [
                        'color' => '#FF8C00',
                        'channel_id' => 'high_importance_channel'
                    ]
                ]
            ]
        ];
        
        echo "Sending payload to FCM API: $url\n";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "HTTP Status Code: $httpCode\n";
        echo "FCM Response: \n$response\n\n";
        
        if ($httpCode == 200) {
            echo "SUCCESS: The push notification was successfully delivered to Google's FCM servers!\n";
            echo "If it STILL does not show up on the device, the issue is on the mobile app side (e.g. permission denied or channel issue).\n";
        } else {
            echo "FAILED: Google FCM servers rejected the notification. See the response above for details.\n";
        }
        
    } catch (Exception $e) {
        echo "EXCEPTION: " . $e->getMessage() . "\n";
    }
} else {
    echo "ERROR: No push subscriptions found in the database. Open the app on your device to register first.\n";
}