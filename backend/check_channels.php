<?php
require_once 'config/database.php';

$conn = getDBConnection();
$result = $conn->query('SELECT id, channel_id, channel_name FROM channel_sync');

echo "Configured Channels:\n";
echo "===================\n";
while($row = $result->fetch_assoc()) {
    echo 'ID: ' . $row['id'] . ' | Channel ID: ' . $row['channel_id'] . ' | Name: ' . $row['channel_name'] . "\n";
}
?>