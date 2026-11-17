<?php
// Include database configuration and get the logged-in user
require 'includes/config.php';
$u = user();

// Set response content type to JSON
header('Content-Type: application/json');

// Check if a user is logged in
if (!$u) {
    echo '[]';
    exit;
}

// Handle POST request to mark all notifications as read
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Update all notifications belonging to the user
    q('UPDATE notifications SET is_read=1 WHERE user_id=?', [$u['id']]);

    // Return zero unread notifications
    echo json_encode(['n' => 0]);
    exit;
}

// Retrieve the user's latest 30 notifications
echo json_encode(
    q(
        'SELECT id,message,link,is_read,created FROM notifications WHERE user_id=? ORDER BY created DESC,id DESC LIMIT 30',
        [$u['id']]
    )->fetchAll()
);
