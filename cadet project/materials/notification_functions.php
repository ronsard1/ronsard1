<?php
function createNotification($conn, $cadet_id, $title, $message, $type = 'general') {
    try {
        $query = "INSERT INTO notification (cadet_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())";
        $stmt = $conn->prepare($query);
        return $stmt->execute([$cadet_id, $title, $message, $type]);
    } catch (PDOException $e) {
        error_log("Notification error: " . $e->getMessage());
        return false;
    }
}

function getUnreadCount($conn, $cadet_id) {
    try {
        $query = "SELECT COUNT(*) as count FROM notification WHERE cadet_id = ? AND is_read = 0";
        $stmt = $conn->prepare($query);
        $stmt->execute([$cadet_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    } catch (PDOException $e) {
        error_log("Unread count error: " . $e->getMessage());
        return 0;
    }
}

function getNotifications($conn, $cadet_id, $limit = 10) {
    try {
        $query = "SELECT * FROM notification WHERE cadet_id = ? ORDER BY created_at DESC LIMIT ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$cadet_id, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Get notifications error: " . $e->getMessage());
        return [];
    }
}

function markAsRead($conn, $notification_id, $cadet_id) {
    try {
        $query = "UPDATE notification SET is_read = 1 WHERE id = ? AND cadet_id = ?";
        $stmt = $conn->prepare($query);
        return $stmt->execute([$notification_id, $cadet_id]);
    } catch (PDOException $e) {
        error_log("Mark as read error: " . $e->getMessage());
        return false;
    }
}

function markAllAsRead($conn, $cadet_id) {
    try {
        $query = "UPDATE notification SET is_read = 1 WHERE cadet_id = ? AND is_read = 0";
        $stmt = $conn->prepare($query);
        return $stmt->execute([$cadet_id]);
    } catch (PDOException $e) {
        error_log("Mark all read error: " . $e->getMessage());
        return false;
    }
}
?>