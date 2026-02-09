<?php
/**
 * Test Authentication Script
 * Test login and registration functionality
 */

require_once 'config/database.php';
require_once 'models/User.php';
require_once 'utils/Auth.php';

echo "LCMTV Authentication Test\n";
echo "=========================\n\n";

// Test 1: Check if demo user exists
echo "Test 1: Check demo user\n";
$demoUser = User::findByEmail('demo@lcmtv.com');
if ($demoUser) {
    echo "✓ Demo user found: {$demoUser['email']}\n";
    echo "  First Name: {$demoUser['first_name']}\n";
    echo "  Last Name: {$demoUser['last_name']}\n";
    echo "  Role: {$demoUser['role']}\n";
    echo "  Active: " . ($demoUser['is_active'] ? 'Yes' : 'No') . "\n";
} else {
    echo "✗ Demo user not found\n";
}

echo "\n";

// Test 2: Test password verification
echo "Test 2: Test password verification\n";
if ($demoUser && password_verify('demo123', $demoUser['password_hash'])) {
    echo "✓ Password verification successful\n";
} else {
    echo "✗ Password verification failed\n";
}

echo "\n";

// Test 3: Test JWT token generation
echo "Test 3: Test JWT token generation\n";
if ($demoUser) {
    $token = Auth::generateToken($demoUser);
    echo "✓ Token generated: " . substr($token, 0, 50) . "...\n";

    // Test token verification
    $decoded = Auth::verifyToken($token);
    if ($decoded) {
        echo "✓ Token verification successful\n";
        echo "  User ID: {$decoded['user_id']}\n";
        echo "  Email: {$decoded['email']}\n";
    } else {
        echo "✗ Token verification failed\n";
    }
}

echo "\n";

// Test 4: Test API endpoint directly
echo "Test 4: Test API endpoint\n";
$url = 'http://localhost/lcmtvweb/backend/api/users';
$data = json_encode([
    'action' => 'login',
    'email' => 'demo@lcmtv.com',
    'password' => 'demo123'
]);

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => $data
    ]
]);

$result = file_get_contents($url, false, $context);
if ($result) {
    $response = json_decode($result, true);
    if ($response && isset($response['success'])) {
        echo "✓ API call successful\n";
        echo "  Success: " . ($response['success'] ? 'true' : 'false') . "\n";
        if ($response['success']) {
            echo "  Message: {$response['message']}\n";
            echo "  User: {$response['data']['user']['email']}\n";
        } else {
            echo "  Error: {$response['message']}\n";
        }
    } else {
        echo "✗ API returned invalid JSON\n";
    }
} else {
    echo "✗ API call failed\n";
}

echo "\nTest Complete!\n";
?>