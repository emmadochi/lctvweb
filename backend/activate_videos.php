<?php
require_once 'config/database.php';

$conn = getDBConnection();

// Activate all videos that are currently inactive
echo "Activating inactive videos...\n";
$result = $conn->query('UPDATE videos SET is_active = 1 WHERE is_active = 0');
$affected = $conn->affected_rows;
echo "Activated $affected videos\n";

// Show current video status
echo "\nCurrent video status:\n";
$result = $conn->query('SELECT COUNT(*) as total, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active FROM videos');
$row = $result->fetch_assoc();
echo "Total videos: " . $row['total'] . "\n";
echo "Active videos: " . $row['active'] . "\n";

echo "\nVideos should now be visible on the frontend!\n";
?>