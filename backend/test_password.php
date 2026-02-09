<?php
/**
 * Test Password Hashing
 */

$testPassword = 'demo123';
$hash = password_hash($testPassword, PASSWORD_DEFAULT);

echo "Test Password: $testPassword\n";
echo "Generated Hash: $hash\n";
echo "Hash Length: " . strlen($hash) . "\n\n";

echo "Verify test:\n";
echo "password_verify('$testPassword', '$hash'): " . (password_verify($testPassword, $hash) ? 'TRUE' : 'FALSE') . "\n";

// Check the actual hash from database
$actualHash = '.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
echo "\nActual hash from DB: $actualHash\n";
echo "Hash Length: " . strlen($actualHash) . "\n";
echo "password_verify('$testPassword', '$actualHash'): " . (password_verify($testPassword, $actualHash) ? 'TRUE' : 'FALSE') . "\n";

// Try different passwords
$possiblePasswords = ['demo123', 'Demo123', 'demo', 'password', '123456'];
echo "\nTesting possible passwords:\n";
foreach ($possiblePasswords as $pwd) {
    if (password_verify($pwd, $actualHash)) {
        echo "✓ Password found: $pwd\n";
        break;
    } else {
        echo "✗ Not $pwd\n";
    }
}
?>