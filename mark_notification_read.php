<?php
session_start();
require_once "config/database.php";
require_once "notification_functions.php";

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'cadet') {
    header('HTTP/1.1 403 Forbidden');
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$notification_id = $input['notification_id'] ?? null;

if ($notification_id) {
    $database = new Database();
    $conn = $database->getConnection();
    $cadet_id = $_SESSION['user_id'];
    
    markAsRead($conn, $notification_id, $cadet_id);
}

echo json_encode(['success' => true]);
?>