<?php
// Return the number of unread notifications for the current user.
require 'includes/config.php';
header('Content-Type: application/json; charset=utf-8');

$u = user();
$count = $u
    ? (int)q(
        'SELECT COUNT(*) c FROM notifications WHERE user_id=? AND is_read=0',
        [$u['id']]
    )->fetch()['c']
    : 0;

echo json_encode(['n' => $count]);
