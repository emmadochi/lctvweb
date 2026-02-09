<?php
/**
 * Notification System Test Script
 * Creates sample notifications to test the system
 */

require_once 'models/Notification.php';

echo "LCMTV Notification System Test\n";
echo "===============================\n\n";

// Test data
$testUserId = 1; // demo user ID

echo "Creating sample notifications...\n\n";

// Create a system notification
$systemNotification = [
    'user_id' => $testUserId,
    'type' => 'system',
    'title' => 'Welcome to LCMTV!',
    'message' => 'Thanks for joining LCMTV. Discover amazing videos in our curated channels!',
    'related_type' => 'system'
];

$notificationId = Notification::create($systemNotification);
if ($notificationId) {
    echo "✓ Created system notification (ID: $notificationId)\n";
} else {
    echo "✗ Failed to create system notification\n";
}

// Create a subscription notification (simulated)
$subscriptionNotification = [
    'user_id' => $testUserId,
    'type' => 'subscription',
    'title' => 'New Videos in Sports',
    'message' => 'Check out the latest sports highlights and game recaps!',
    'related_id' => 2, // Sports category
    'related_type' => 'category'
];

$notificationId2 = Notification::create($subscriptionNotification);
if ($notificationId2) {
    echo "✓ Created subscription notification (ID: $notificationId2)\n";
} else {
    echo "✗ Failed to create subscription notification\n";
}

// Test notification retrieval
echo "\nTesting notification retrieval...\n";

$notifications = Notification::getUserNotifications($testUserId, 10);
echo "Found " . count($notifications) . " notifications for user $testUserId\n";

foreach ($notifications as $notification) {
    echo "  - {$notification['title']}: {$notification['message']} (" . ($notification['is_read'] ? 'read' : 'unread') . ")\n";
}

// Test unread count
$unreadCount = Notification::getUnreadCount($testUserId);
echo "\nUnread count: $unreadCount\n";

// Test marking as read
if (!empty($notifications)) {
    $firstNotification = $notifications[0];
    if (!Notification::markAsRead($firstNotification['id'], $testUserId)) {
        echo "✗ Failed to mark notification as read\n";
    } else {
        echo "✓ Marked notification {$firstNotification['id']} as read\n";

        // Check updated count
        $newUnreadCount = Notification::getUnreadCount($testUserId);
        echo "New unread count: $newUnreadCount\n";
    }
}

// Create some demo notifications for testing
echo "\nCreating demo notifications for testing...\n";

$welcomeNotification = [
    'user_id' => $testUserId,
    'type' => 'system',
    'title' => 'Welcome to LCMTV!',
    'message' => 'Thanks for joining LCMTV! Discover amazing videos in our curated channels.',
    'related_type' => 'system'
];

$notificationId = Notification::create($welcomeNotification);
if ($notificationId) {
    echo "✓ Created welcome notification (ID: $notificationId)\n";
}

$sportsNotification = [
    'user_id' => $testUserId,
    'type' => 'subscription',
    'title' => 'New Videos in Sports',
    'message' => 'Check out the latest sports highlights and game recaps in the Sports category!',
    'related_id' => 2,
    'related_type' => 'category'
];

$notificationId2 = Notification::create($sportsNotification);
if ($notificationId2) {
    echo "✓ Created sports subscription notification (ID: $notificationId2)\n";
}

$videoNotification = [
    'user_id' => $testUserId,
    'type' => 'interaction',
    'title' => 'Video Favorited',
    'message' => 'Someone else also favorited a video you might like!',
    'related_id' => 1,
    'related_type' => 'video'
];

$notificationId3 = Notification::create($videoNotification);
if ($notificationId3) {
    echo "✓ Created video interaction notification (ID: $notificationId3)\n";
}

echo "\nFinal notification check:\n";
$finalNotifications = Notification::getUserNotifications($testUserId, 10);
echo "Total notifications: " . count($finalNotifications) . "\n";
$finalUnread = Notification::getUnreadCount($testUserId);
echo "Unread count: $finalUnread\n";

echo "\nNotification system test complete!\n";
echo "You should now see notifications in the bell icon dropdown.\n";
?>