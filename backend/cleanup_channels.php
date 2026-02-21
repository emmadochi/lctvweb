<?php
require_once 'config/database.php';

$conn = getDBConnection();

// Remove duplicate channel entries
echo "Cleaning up duplicate channel entries...\n";
$result = $conn->query("DELETE FROM channel_sync WHERE channel_id = 'UC4QobU6STFB0P71PMvOGN5A'");
echo "Removed duplicate entries\n";

// Check remaining channels
echo "\nRemaining channels:\n";
$result = $conn->query('SELECT id, channel_id, channel_name FROM channel_sync');
while($row = $result->fetch_assoc()) {
    echo 'ID: ' . $row['id'] . ' | Channel ID: ' . $row['channel_id'] . ' | Name: ' . $row['channel_name'] . "\n";
}

echo "\nTo add your actual channel:\n";
echo "1. Go to http://localhost/LCMTVWebNew/backend/admin/\n";
echo "2. Click on 'Channel Sync' in the sidebar\n";
echo "3. Remove the sample channel if it exists\n";
echo "4. Add your actual YouTube channel ID\n";
echo "5. Select the appropriate category\n";
echo "6. Click 'Sync Now' to test\n";
?>