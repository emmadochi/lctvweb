<?php
require_once 'config/database.php';

$conn = getDBConnection();

echo "=== Recent Videos ===\n";
$result = $conn->query('SELECT id, title, youtube_id, created_at, is_active FROM videos ORDER BY created_at DESC LIMIT 10');
while($row = $result->fetch_assoc()) {
    echo 'ID: ' . $row['id'] . ' | Title: ' . substr($row['title'], 0, 50) . '... | YouTube ID: ' . $row['youtube_id'] . ' | Active: ' . $row['is_active'] . ' | Created: ' . $row['created_at'] . "\n";
}

echo "\n=== Sync Logs ===\n";
$result = $conn->query('SELECT * FROM sync_log ORDER BY started_at DESC LIMIT 5');
while($row = $result->fetch_assoc()) {
    echo 'ID: ' . $row['id'] . ' | Status: ' . $row['status'] . ' | Videos Found: ' . $row['videos_found'] . ' | Imported: ' . $row['videos_imported'] . ' | Started: ' . $row['started_at'] . "\n";
}

echo "\n=== Channel Sync Status ===\n";
$result = $conn->query('SELECT * FROM channel_sync');
while($row = $result->fetch_assoc()) {
    echo 'ID: ' . $row['id'] . ' | Channel ID: ' . $row['channel_id'] . ' | Active: ' . $row['is_active'] . ' | Auto Import: ' . $row['auto_import'] . ' | Max Videos: ' . $row['max_videos_per_sync'] . "\n";
}
?>