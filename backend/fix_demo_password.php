<?php
/**
 * Fix Demo User Password
 */

require_once 'models/User.php';

$demoEmail = 'demo@lcmtv.com';
$demoPassword = 'demo123';

// Get current user
$user = User::findByEmail($demoEmail);
if (!$user) {
    echo "Demo user not found!\n";
    exit(1);
}

echo "Current user: {$user['email']}\n";
echo "Current hash: {$user['password_hash']}\n";

// Generate new hash
$newHash = password_hash($demoPassword, PASSWORD_DEFAULT);
echo "New hash: $newHash\n";

// Update user
$conn = new mysqli('localhost', 'root', '', 'lcmtv_db');
$stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
$stmt->bind_param("ss", $newHash, $demoEmail);

if ($stmt->execute()) {
    echo "✓ Password updated successfully\n";

    // Verify the update
    $updatedUser = User::findByEmail($demoEmail);
    if ($updatedUser && password_verify($demoPassword, $updatedUser['password_hash'])) {
        echo "✓ Password verification successful\n";
    } else {
        echo "✗ Password verification failed after update\n";
    }
} else {
    echo "✗ Failed to update password: " . $stmt->error . "\n";
}

$stmt->close();
$conn->close();
?>