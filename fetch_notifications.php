<?php
session_start();
require_once "config/database.php";
require_once "notification_functions.php";

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'cadet') {
    header('HTTP/1.1 403 Forbidden');
    exit();
}

$database = new Database();
$conn = $database->getConnection();
$cadet_id = $_SESSION['user_id'];

$unread_count = getUnreadCount($conn, $cadet_id);
$notifications = getNotifications($conn, $cadet_id, 10);

$formatted_notifications = [];
foreach ($notifications as $notification) {
    $time_ago = time_elapsed_string($notification['created_at']);
    $formatted_notifications[] = [
        'id' => $notification['id'],
        'title' => $notification['title'],
        'message' => $notification['message'],
        'type' => $notification['type'],
        'is_read' => $notification['is_read'],
        'time_ago' => $time_ago
    ];
}

echo json_encode([
    'unread_count' => $unread_count,
    'notifications' => $formatted_notifications
]);
?>