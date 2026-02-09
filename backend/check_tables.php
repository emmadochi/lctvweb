<?php
require_once 'config/database.php';

$conn = getDBConnection();

$tables = ['page_views', 'video_views'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        echo "$table table exists\n";
    } else {
        echo "$table table does not exist\n";
    }
}
?>
