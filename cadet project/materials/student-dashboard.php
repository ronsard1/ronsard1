<?php
session_start();

// Check if user is logged in and is a cadet
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'cadet') {
    header("Location: index.php");
    exit();
}

require_once "config/database.php";

$database = new Database();
$conn = $database->getConnection();

$success = $error = "";

// Get current cadet data - FIXED: Use prepared statement to prevent SQL injection
$cadet_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM cadet WHERE cadetid = ?");
$stmt->execute([$cadet_id]);
$cadet = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle notification actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Mark notification as read
    if (isset($_POST['mark_notification_read'])) {
        try {
            $notification_id = $_POST['notification_id'];
            $target_page = $_POST['target_page'] ?? 'dashboard';
            
           $query = "UPDATE notification SET is_read = 1 WHERE id = ? AND cadet_id = ?";
            $stmt = $conn->prepare($query);
            if ($stmt->execute([$notification_id, $cadet_id])) {
                $success = "Notification marked as read";
                
                // Store target page in session for redirection
                $_SESSION['redirect_page'] = $target_page;
            }
        } catch (PDOException $e) {
            $error = "Error updating notification: " . $e->getMessage();
        }
    }
    
    // Mark all notifications as read
    if (isset($_POST['mark_all_read'])) {
        try {
            $query = "UPDATE notification SET is_read = 1 WHERE cadet_id = ? AND is_read = 0";
            $stmt = $conn->prepare($query);
            if ($stmt->execute([$cadet_id])) {
                $success = "All notifications marked as read";
            }
        } catch (PDOException $e) {
            $error = "Error updating notifications: " . $e->getMessage();
        }
    }
    
    // Handle contact message
    if (isset($_POST['send_message'])) {
        try {
            $subject = $_POST['subject'];
            $message = $_POST['message'];
            
            // Create contact_messages table if it doesn't exist
            $conn->exec("
                CREATE TABLE IF NOT EXISTS contact_messages (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    cadet_id INT NOT NULL,
                    subject VARCHAR(255) NOT NULL,
                    message TEXT NOT NULL,
                    status ENUM('unread', 'read', 'replied') DEFAULT 'unread',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            
            $query = "INSERT INTO contact_messages (cadet_id, subject, message) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($query);
            
            if ($stmt->execute([$cadet_id, $subject, $message])) {
                $success = "Your message has been sent to the administrator successfully!";
            }
        } catch (PDOException $e) {
            $error = "Error sending message: " . $e->getMessage();
        }
    }
}

// Handle page redirection after notification click
if (isset($_SESSION['redirect_page'])) {
    $redirect_page = $_SESSION['redirect_page'];
    unset($_SESSION['redirect_page']);
    
    // Use JavaScript to redirect to the specific page
    echo "<script>sessionStorage.setItem('redirectPage', '" . htmlspecialchars($redirect_page, ENT_QUOTES) . "');</script>";
}

// Get initial notification count
try {
    $unread_count_query = $conn->prepare("SELECT COUNT(*) as count FROM notification WHERE cadet_id = ? AND is_read = 0");
    $unread_count_query->execute([$cadet_id]);
    $unread_count = $unread_count_query->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    $unread_count = 0;
}

// Get notifications for student - include target_page if it exists
try {
    $notifications_query = $conn->prepare("
        SELECT *, 
               COALESCE(target_page, 'dashboard') as target_page 
        FROM notification 
        WHERE cadet_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $notifications_query->execute([$cadet_id]);
    $notifications = $notifications_query->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $notifications = [];
}

// FIXED: Use prepared statements for all material queries to prevent SQL injection
// Get cadet's assigned materials
try {
    $assigned_stmt = $conn->prepare("
        SELECT m.*, c.fname, c.lname 
        FROM materials m 
        LEFT JOIN cadet c ON m.telephone = c.number 
        WHERE m.telephone = ? 
        ORDER BY m.registered_date DESC
    ");
    $assigned_stmt->execute([$cadet['number']]);
    $assigned_materials = $assigned_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $assigned_materials = [];
}

// Get cadet's checked out materials
try {
    $checked_out_stmt = $conn->prepare("
        SELECT m.*, c.fname, c.lname 
        FROM materials m 
        LEFT JOIN cadet c ON m.telephone = c.number 
        WHERE m.status = 'checked_out' AND m.telephone = ?
        ORDER BY m.registered_date DESC
    ");
    $checked_out_stmt->execute([$cadet['number']]);
    $checked_out_materials = $checked_out_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $checked_out_materials = [];
}

// Get available materials
try {
    $available_stmt = $conn->prepare("
        SELECT m.*, c.fname, c.lname 
        FROM materials m 
        LEFT JOIN cadet c ON m.telephone = c.number 
        WHERE m.status = 'available' 
        AND m.telephone = ?
        ORDER BY m.registered_date DESC
    ");
    $available_stmt->execute([$cadet['number']]);
    $available_materials = $available_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $available_materials = [];
}

// Get cadet's materials sent outside
try {
    $sent_outside_stmt = $conn->prepare("
        SELECT m.*, c.fname, c.lname 
        FROM materials m 
        LEFT JOIN cadet c ON m.telephone = c.number 
        WHERE m.status = 'outside_institution' 
        AND m.telephone = ?
        ORDER BY m.sent_date DESC
    ");
    $sent_outside_stmt->execute([$cadet['number']]);
    $sent_outside_materials = $sent_outside_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $sent_outside_materials = [];
}

// Get cadet's materials taken outside
try {
    $taken_outside_stmt = $conn->prepare("
        SELECT m.*, c.fname, c.lname 
        FROM materials m 
        LEFT JOIN cadet c ON m.telephone = c.number 
        WHERE m.status = 'taken_outside' 
        AND m.telephone = ?
        ORDER BY m.taken_date DESC, m.sent_date DESC
    ");
    $taken_outside_stmt->execute([$cadet['number']]);
    $taken_outside_materials = $taken_outside_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $taken_outside_materials = [];
}

// Get statistics
$total_assigned = count($assigned_materials);
$total_checked_out = count($checked_out_materials);
$total_available = count($available_materials);
$total_sent_outside = count($sent_outside_materials);
$total_taken_outside = count($taken_outside_materials);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadet Dashboard - Materials Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #3b82f6;
            --secondary: #1e40af;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #06b6d4;
            --light: #f8fafc;
            --dark: #0f172a;
            --sidebar-width: 260px;
            --header-bg: #1e293b;
            --sidebar-bg: #0f172a;
            --sidebar-text: #e2e8f0;
            --content-bg: #020617;
            --card-bg: #1e293b;
            --text-color: #e2e8f0;
            --border-color: #334155;
        }

        .light-mode {
            --header-bg: #ffffff;
            --sidebar-bg: #f8fafc;
            --sidebar-text: #475569;
            --content-bg: #f1f5f9;
            --card-bg: #ffffff;
            --text-color: #334155;
            --border-color: #e2e8f0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: var(--content-bg);
            color: var(--text-color);
            line-height: 1.6;
            transition: all 0.3s ease;
        }
        
        /* Header Styles */
        header {
            background: var(--header-bg);
            color: var(--text-color);
            padding: 1rem 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            border-bottom: 1px solid var(--border-color);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .logo {
            font-size: 1.6rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .logo i {
            font-size: 2rem;
            color: var(--primary);
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-role {
            background: var(--primary);
            padding: 0.4rem 1rem;
            border-radius: 25px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .theme-toggle {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            color: var(--text-color);
            padding: 0.5rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .theme-toggle:hover {
            background: var(--primary);
            color: white;
        }

        /* Notification Styles */
        .notification-wrapper {
            position: relative;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .notification-badge.hidden {
            display: none;
        }

        .notification-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            width: 350px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            z-index: 1000;
            display: none;
        }

        .notification-dropdown.show {
            display: block;
        }

        .notification-header {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-header h5 {
            margin: 0;
            color: var(--text-color);
        }

        .mark-all-read {
            background: none;
            border: none;
            color: var(--primary);
            cursor: pointer;
            font-size: 0.8rem;
        }

        .notification-body {
            max-height: 400px;
            overflow-y: auto;
        }

        .notification-item {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: background 0.3s;
        }

        .notification-item:hover {
            background: var(--content-bg);
        }

        .notification-item.unread {
            background: rgba(59, 130, 246, 0.1);
            border-left: 4px solid var(--primary);
        }

        .notification-item .notification-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: var(--text-color);
        }

        .notification-item .notification-message {
            font-size: 0.9rem;
            color: var(--text-color);
            opacity: 0.8;
            margin-bottom: 0.5rem;
        }

        .notification-item .notification-time {
            font-size: 0.75rem;
            color: var(--text-color);
            opacity: 0.6;
        }

        .notification-empty {
            padding: 2rem;
            text-align: center;
            color: var(--text-color);
            opacity: 0.6;
        }

        .notification-footer {
            padding: 1rem;
            border-top: 1px solid var(--border-color);
            text-align: center;
        }

        .view-all {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        /* Main Layout */
        .main-container {
            display: flex;
            margin-top: 80px;
            min-height: calc(100vh - 80px);
        }
        
        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            color: var(--sidebar-text);
            position: fixed;
            top: 80px;
            left: 0;
            bottom: 0;
            overflow-y: auto;
            transition: all 0.3s ease;
            box-shadow: 4px 0 20px rgba(0,0,0,0.1);
            border-right: 1px solid var(--border-color);
        }
        
        .sidebar-content {
            padding: 2rem 1.5rem;
        }
        
        .sidebar h3 {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-color);
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary);
        }
        
        .sidebar-menu {
            list-style: none;
        }
        
        .sidebar-menu li {
            margin-bottom: 0.5rem;
        }
        
        .sidebar-menu a {
            color: var(--sidebar-text);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 1rem 1.2rem;
            border-radius: 12px;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .sidebar-menu a:hover {
            background: var(--primary);
            color: white;
            transform: translateX(8px);
        }
        
        .sidebar-menu a.active {
            background: var(--primary);
            color: white;
        }
        
        .sidebar-menu a i {
            font-size: 1.2rem;
            width: 24px;
        }
        
        /* Content Area */
        .content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 2rem;
            background: var(--content-bg);
            min-height: calc(100vh - 80px);
        }
        
        /* Page content */
        .page-content {
            display: none;
            animation: fadeInUp 0.5s ease;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .page-content.active {
            display: block;
        }
        
        /* Dashboard Cards */
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }
        
        .stat-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
            text-align: center;
            border: 1px solid var(--border-color);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
            border-color: var(--primary);
        }
        
        .stat {
            font-size: 2.5rem;
            font-weight: 700;
            line-height: 1;
            margin: 1rem 0;
            background: linear-gradient(135deg, var(--primary), var(--info));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        /* Card Styles */
        .card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
        }
        
        .card:hover {
            box-shadow: 0 12px 30px rgba(0,0,0,0.3);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-color);
        }
        
        .card-header h2 {
            color: var(--text-color);
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        /* Material Grid */
        .material-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }
        
        .material-card {
            background: var(--card-bg);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
        }
        
        .material-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
            border-color: var(--primary);
        }
        
        .material-header {
            padding: 1.5rem;
            background: linear-gradient(135deg, var(--primary), var(--info));
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .material-id {
            font-weight: bold;
            font-size: 1.1rem;
        }
        
        .material-body {
            padding: 1.5rem;
        }
        
        .material-details {
            margin: 1rem 0;
        }
        
        .material-details p {
            margin: 0.5rem 0;
            display: flex;
            justify-content: space-between;
        }
        
        .material-details strong {
            color: var(--primary);
        }
        
        .cadet-info {
            background: var(--content-bg);
            padding: 0.75rem;
            border-radius: 8px;
            margin-top: 1rem;
            border-left: 4px solid var(--success);
        }
        
        /* Badges */
        .badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .badge-assigned { background: #10b981; color: white; }
        .badge-checked-out { background: #06b6d4; color: white; }
        .badge-available { background: #10b981; color: white; }
        .badge-sent-outside { background: #f59e0b; color: white; }
        .badge-taken-outside { background: #8b5cf6; color: white; }
        
        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            border: 1px solid;
        }
        
        .alert-success {
            background: #10b98120;
            color: #10b981;
            border-color: #10b981;
        }
        
        .alert-danger {
            background: #ef444420;
            color: #ef4444;
            border-color: #ef4444;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #94a3b8;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #475569;
        }
        
        /* Contact Form */
        .contact-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .contact-card {
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
            border: 1px solid var(--border-color);
        }
        
        .contact-card i {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }
        
        .form-container {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 2.5rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-color);
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 1rem;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            background: var(--content-bg);
            color: var(--text-color);
            transition: all 0.3s;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .btn-primary { background: var(--primary); color: white; }
        .btn-success { background: var(--success); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        @media (max-width: 1200px) {
            .sidebar {
                transform: translateX(-100%);
                z-index: 999;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .content {
                margin-left: 0;
            }
            
            .dashboard-cards {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
        }
        
        @media (max-width: 768px) {
            .material-grid {
                grid-template-columns: 1fr;
            }
            
            .contact-info {
                grid-template-columns: 1fr;
            }
            
            .header-content {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <div class="logo">
                <i class="fas fa-user-graduate"></i>
                <span>Cadet Portal</span>
            </div>
            <div class="header-actions">
                <button class="theme-toggle" id="themeToggle">
                    <i class="fas fa-moon"></i>
                </button>
                <span class="user-role">Cadet</span>
                <span><?php echo htmlspecialchars($cadet['fname'] . ' ' . $cadet['lname']); ?></span>
                
                <!-- Notification Bell -->
                <div class="notification-wrapper">
                    <a href="#" class="notification-toggle" id="notificationToggle" style="color: var(--text-color); text-decoration: none; padding: 0.5rem; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge <?php echo $unread_count == 0 ? 'hidden' : ''; ?>" id="notificationBadge">
                            <?php echo $unread_count > 0 ? $unread_count : ''; ?>
                        </span>
                    </a>
                    
                    <!-- Notification Dropdown -->
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-header">
                            <h5><i class="fas fa-bell"></i> Notifications</h5>
                            <form method="POST" action="" style="display: inline;">
                                <input type="hidden" name="mark_all_read" value="1">
                                <button type="submit" class="mark-all-read" onclick="markAllNotificationsAsRead()">
                                    Mark all read
                                </button>
                            </form>
                        </div>
                        <div class="notification-body" id="notificationBody">
                            <?php if (!empty($notifications)): ?>
                                <?php foreach ($notifications as $notification): ?>
                                    <!-- FIXED: Use data attributes instead of inline onclick -->
                                    <div class="notification-item <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>" 
                                         data-notification-id="<?php echo $notification['notification_id']; ?>"
                                         data-target-page="<?php echo htmlspecialchars($notification['target_page'], ENT_QUOTES); ?>">
                                        <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                                        <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
                                        <div class="notification-time">
                                            <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                                            <?php if (!$notification['is_read']): ?>
                                                <span style="color: var(--primary); margin-left: 0.5rem;">• New</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="notification-empty">
                                    <i class="fas fa-bell-slash"></i>
                                    <p>No new notifications</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="notification-footer">
                            <a href="#" class="view-all nav-link" data-page="notifications-page" onclick="closeNotificationDropdown()">View All Notifications</a>
                        </div>
                    </div>
                </div>
                
                <a href="logout.php" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </header>

    <div class="main-container">
        <div class="sidebar">
            <div class="sidebar-content">
                <h3>Cadet Menu</h3>
                <ul class="sidebar-menu">
                    <li><a href="#" class="nav-link active" data-page="dashboard"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                    <li><a href="#" class="nav-link" data-page="my-materials"><i class="fas fa-box"></i> <span>My Materials</span></a></li>
                    <li><a href="#" class="nav-link" data-page="checked-out"><i class="fas fa-check-circle"></i> <span>Checked Out</span></a></li>
                    <li><a href="#" class="nav-link" data-page="available-materials"><i class="fas fa-warehouse"></i> <span>Available</span></a></li>
                    <li><a href="#" class="nav-link" data-page="sent-outside"><i class="fas fa-paper-plane"></i> <span>Sent Outside</span></a></li>
                    <li><a href="#" class="nav-link" data-page="taken-outside"><i class="fas fa-external-link-alt"></i> <span>Taken Outside</span></a></li>
                    <li><a href="#" class="nav-link" data-page="notifications-page"><i class="fas fa-bell"></i> <span>Notifications</span></a></li>
                    <li><a href="#" class="nav-link" data-page="contact"><i class="fas fa-envelope"></i> <span>Contact Admin</span></a></li>
                </ul>
            </div>
        </div>

        <div class="content">
            <!-- Success/Error Messages -->
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- All your page content sections remain exactly the same -->
            <!-- Dashboard Page -->
            <div class="page-content active" id="dashboard">
                <h1>Cadet Dashboard</h1>
                <p>Welcome back, <?php echo htmlspecialchars($cadet['fname'] . ' ' . $cadet['lname']); ?>! (Roll No: <?php echo htmlspecialchars($cadet['rollno']); ?>)</p>
                
                <div class="dashboard-cards">
                    <div class="stat-card">
                        <i class="fas fa-box fa-2x"></i>
                        <div class="stat"><?php echo $total_assigned; ?></div>
                        <p>My Materials</p>
                    </div>
                    
                    <div class="stat-card">
                        <i class="fas fa-check-circle fa-2x"></i>
                        <div class="stat"><?php echo $total_checked_out; ?></div>
                        <p>Checked Out</p>
                    </div>
                    
                    <div class="stat-card">
                        <i class="fas fa-warehouse fa-2x"></i>
                        <div class="stat"><?php echo $total_available; ?></div>
                        <p>Available</p>
                    </div>
                    
                    <div class="stat-card">
                        <i class="fas fa-paper-plane fa-2x"></i>
                        <div class="stat"><?php echo $total_sent_outside; ?></div>
                        <p>Sent Outside</p>
                    </div>
                    
                    <div class="stat-card">
                        <i class="fas fa-external-link-alt fa-2x"></i>
                        <div class="stat"><?php echo $total_taken_outside; ?></div>
                        <p>Taken Outside</p>
                    </div>
                    
                    <div class="stat-card">
                        <i class="fas fa-bell fa-2x"></i>
                        <div class="stat"><?php echo $unread_count; ?></div>
                        <p>Unread Notifications</p>
                    </div>
                </div>

                <!-- Recent Materials -->
                <div class="card">
                    <div class="card-header">
                        <h2>My Recent Materials</h2>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($assigned_materials)): ?>
                            <div class="material-grid">
                                <?php foreach (array_slice($assigned_materials, 0, 3) as $material): ?>
                                    <div class="material-card">
                                        <div class="material-header">
                                            <span class="material-id"><?php echo htmlspecialchars($material['material_code']); ?></span>
                                            <span class="badge badge-assigned">My Material</span>
                                        </div>
                                        <div class="material-body">
                                            <h3><?php echo htmlspecialchars($material['name']); ?></h3>
                                            <div class="material-details">
                                                <p><strong>Description:</strong> <?php echo htmlspecialchars($material['description'] ?: 'No description'); ?></p>
                                                <p><strong>Size:</strong> <?php echo htmlspecialchars($material['size'] ?? 'N/A'); ?></p>
                                                <p><strong>Quantity:</strong> <?php echo htmlspecialchars($material['quantity']); ?></p>
                                                <p><strong>Barcode:</strong> <?php echo htmlspecialchars($material['barcode'] ?? 'N/A'); ?></p>
                                                <p><strong>Status:</strong> <?php echo ucfirst($material['status']); ?></p>
                                            </div>
                                            <?php if ($material['fname']): ?>
                                                <div class="cadet-info">
                                                    <strong>Assigned Cadet:</strong> <?php echo htmlspecialchars($material['fname'] . ' ' . $material['lname']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-box-open"></i>
                                <h3>No Materials Assigned</h3>
                                <p>You don't have any materials assigned to you yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- My Materials Page -->
            <div class="page-content" id="my-materials">
                <h1>My Materials</h1>
                <p>Materials assigned to you (matched by telephone number).</p>
                
                <div class="card">
                    <div class="card-body">
                        <?php if (!empty($assigned_materials)): ?>
                            <div class="material-grid">
                                <?php foreach ($assigned_materials as $material): ?>
                                    <div class="material-card">
                                        <div class="material-header">
                                            <span class="material-id"><?php echo htmlspecialchars($material['material_code']); ?></span>
                                            <span class="badge badge-assigned">Assigned to Me</span>
                                        </div>
                                        <div class="material-body">
                                            <h3><?php echo htmlspecialchars($material['name']); ?></h3>
                                            <div class="material-details">
                                                <p><strong>Description:</strong> <?php echo htmlspecialchars($material['description'] ?: 'No description'); ?></p>
                                                <p><strong>Size:</strong> <?php echo htmlspecialchars($material['size'] ?? 'N/A'); ?></p>
                                                <p><strong>Quantity:</strong> <?php echo htmlspecialchars($material['quantity']); ?></p>
                                                <p><strong>Barcode:</strong> <?php echo htmlspecialchars($material['barcode'] ?? 'N/A'); ?></p>
                                                <p><strong>Status:</strong> <?php echo ucfirst($material['status']); ?></p>
                                            </div>
                                            <?php if ($material['fname']): ?>
                                                <div class="cadet-info">
                                                    <strong>Assigned Cadet:</strong> <?php echo htmlspecialchars($material['fname'] . ' ' . $material['lname']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-box-open"></i>
                                <h3>No Materials Assigned</h3>
                                <p>You don't have any materials assigned to you currently.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Checked Out Materials Page -->
            <div class="page-content" id="checked-out">
                <h1>Checked Out Materials</h1>
                <p>All materials currently checked out (status: checked_out).</p>
                
                <div class="card">
                    <div class="card-body">
                        <?php if (!empty($checked_out_materials)): ?>
                            <div class="material-grid">
                                <?php foreach ($checked_out_materials as $material): ?>
                                    <div class="material-card">
                                        <div class="material-header">
                                            <span class="material-id"><?php echo htmlspecialchars($material['material_code']); ?></span>
                                            <span class="badge badge-checked-out">Checked Out</span>
                                        </div>
                                        <div class="material-body">
                                            <h3><?php echo htmlspecialchars($material['name']); ?></h3>
                                            <div class="material-details">
                                                <p><strong>Description:</strong> <?php echo htmlspecialchars($material['description'] ?: 'No description'); ?></p>
                                                <p><strong>Size:</strong> <?php echo htmlspecialchars($material['size'] ?? 'N/A'); ?></p>
                                                <p><strong>Quantity:</strong> <?php echo htmlspecialchars($material['quantity']); ?></p>
                                                <p><strong>Barcode:</strong> <?php echo htmlspecialchars($material['barcode'] ?? 'N/A'); ?></p>
                                            </div>
                                            <?php if ($material['fname']): ?>
                                                <div class="cadet-info">
                                                    <strong>Checked out by:</strong> <?php echo htmlspecialchars($material['fname'] . ' ' . $material['lname']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-clipboard-check"></i>
                                <h3>No Checked Out Materials</h3>
                                <p>There are currently no materials checked out in the system.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Available Materials Page -->
            <div class="page-content" id="available-materials">
                <h1>Available Materials</h1>
                <p>Materials currently available (status: available).</p>
                
                <div class="card">
                    <div class="card-body">
                        <?php if (!empty($available_materials)): ?>
                            <div class="material-grid">
                                <?php foreach ($available_materials as $material): ?>
                                    <div class="material-card">
                                        <div class="material-header">
                                            <span class="material-id"><?php echo htmlspecialchars($material['material_code']); ?></span>
                                            <span class="badge badge-available">Available</span>
                                        </div>
                                        <div class="material-body">
                                            <h3><?php echo htmlspecialchars($material['name']); ?></h3>
                                            <div class="material-details">
                                                <p><strong>Description:</strong> <?php echo htmlspecialchars($material['description'] ?: 'No description available'); ?></p>
                                                <p><strong>Size:</strong> <?php echo htmlspecialchars($material['size'] ?? 'N/A'); ?></p>
                                                <p><strong>Quantity:</strong> <?php echo htmlspecialchars($material['quantity']); ?></p>
                                                <p><strong>Barcode:</strong> <?php echo htmlspecialchars($material['barcode'] ?? 'N/A'); ?></p>
                                            </div>
                                            <?php if ($material['fname']): ?>
                                                <div class="cadet-info">
                                                    <strong>Last assigned to:</strong> <?php echo htmlspecialchars($material['fname'] . ' ' . $material['lname']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-boxes"></i>
                                <h3>No Available Materials</h3>
                                <p>There are currently no materials available.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sent Outside Materials Page -->
            <div class="page-content" id="sent-outside">
                <h1>Materials Sent Outside</h1>
                <p>Your materials that have been sent outside the institution (status: outside_institution).</p>
                
                <div class="card">
                    <div class="card-body">
                        <?php if (!empty($sent_outside_materials)): ?>
                            <div class="material-grid">
                                <?php foreach ($sent_outside_materials as $material): ?>
                                    <div class="material-card">
                                        <div class="material-header">
                                            <span class="material-id"><?php echo htmlspecialchars($material['material_code']); ?></span>
                                            <span class="badge badge-sent-outside">Sent Outside</span>
                                        </div>
                                        <div class="material-body">
                                            <h3><?php echo htmlspecialchars($material['name']); ?></h3>
                                            <div class="material-details">
                                                <p><strong>Description:</strong> <?php echo htmlspecialchars($material['description'] ?: 'No description'); ?></p>
                                                <p><strong>Size:</strong> <?php echo htmlspecialchars($material['size'] ?? 'N/A'); ?></p>
                                                <p><strong>Quantity:</strong> <?php echo htmlspecialchars($material['quantity']); ?></p>
                                                <p><strong>Barcode:</strong> <?php echo htmlspecialchars($material['barcode'] ?? 'N/A'); ?></p>
                                                <p><strong>Sent To:</strong> <?php echo htmlspecialchars($material['sent_to_person'] ?? 'N/A'); ?></p>
                                                <p><strong>Contact:</strong> <?php echo htmlspecialchars($material['sent_to_contact'] ?? 'N/A'); ?></p>
                                                <p><strong>Reason:</strong> <?php echo htmlspecialchars($material['reason'] ?? 'N/A'); ?></p>
                                                <p><strong>Sent Date:</strong> <?php echo $material['sent_date'] ? date('M j, Y', strtotime($material['sent_date'])) : 'N/A'; ?></p>
                                            </div>
                                            <?php if ($material['fname']): ?>
                                                <div class="cadet-info">
                                                    <strong>Assigned Cadet:</strong> <?php echo htmlspecialchars($material['fname'] . ' ' . $material['lname']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-paper-plane"></i>
                                <h3>No Materials Sent Outside</h3>
                                <p>You don't have any materials currently sent outside the institution.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Taken Outside Materials Page -->
            <div class="page-content" id="taken-outside">
                <h1>Materials Taken Outside</h1>
                <p>Your materials that have been confirmed as taken outside the institution (status: taken_outside).</p>
                
                <div class="card">
                    <div class="card-body">
                        <?php if (!empty($taken_outside_materials)): ?>
                            <div class="material-grid">
                                <?php foreach ($taken_outside_materials as $material): ?>
                                    <div class="material-card">
                                        <div class="material-header">
                                            <span class="material-id"><?php echo htmlspecialchars($material['material_code']); ?></span>
                                            <span class="badge badge-taken-outside">Taken Outside</span>
                                        </div>
                                        <div class="material-body">
                                            <h3><?php echo htmlspecialchars($material['name']); ?></h3>
                                            <div class="material-details">
                                                <p><strong>Description:</strong> <?php echo htmlspecialchars($material['description'] ?: 'No description'); ?></p>
                                                <p><strong>Size:</strong> <?php echo htmlspecialchars($material['size'] ?? 'N/A'); ?></p>
                                                <p><strong>Quantity:</strong> <?php echo htmlspecialchars($material['quantity']); ?></p>
                                                <p><strong>Barcode:</strong> <?php echo htmlspecialchars($material['barcode'] ?? 'N/A'); ?></p>
                                                <p><strong>Sent To:</strong> <?php echo htmlspecialchars($material['sent_to_person'] ?? 'N/A'); ?></p>
                                                <p><strong>Contact:</strong> <?php echo htmlspecialchars($material['sent_to_contact'] ?? 'N/A'); ?></p>
                                                <p><strong>Reason:</strong> <?php echo htmlspecialchars($material['reason'] ?? 'N/A'); ?></p>
                                                <p><strong>Sent Date:</strong> <?php echo $material['sent_date'] ? date('M j, Y', strtotime($material['sent_date'])) : 'N/A'; ?></p>
                                                <p><strong>Taken Date:</strong> 
                                                    <?php if (isset($material['taken_date']) && $material['taken_date']): ?>
                                                        <?php echo date('M j, Y', strtotime($material['taken_date'])); ?>
                                                    <?php else: ?>
                                                        N/A
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                            <?php if ($material['fname']): ?>
                                                <div class="cadet-info">
                                                    <strong>Assigned Cadet:</strong> <?php echo htmlspecialchars($material['fname'] . ' ' . $material['lname']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-external-link-alt"></i>
                                <h3>No Materials Taken Outside</h3>
                                <p>You don't have any materials confirmed as taken outside the institution.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Notifications Page -->
            <div class="page-content" id="notifications-page">
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Notifications</h1>
                        <p class="page-description">Stay updated with material assignments and status changes.</p>
                    </div>
                    <?php if ($unread_count > 0): ?>
                        <form method="POST" action="" style="display: inline;">
                            <input type="hidden" name="mark_all_read" value="1">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-check-double"></i> Mark All as Read
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <div class="card-body">
                        <?php if (!empty($notifications)): ?>
                            <div class="notifications-list">
                                <?php foreach ($notifications as $notification): ?>
                                    <!-- FIXED: Also update notifications page to use data attributes -->
                                    <div class="notification-item <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>" 
                                         data-notification-id="<?php echo $notification['id']; ?>"
                                         data-target-page="<?php echo htmlspecialchars($notification['target_page'], ENT_QUOTES); ?>">
                                        <div class="notification-content">
                                            <h4><?php echo htmlspecialchars($notification['title']); ?></h4>
                                            <p><?php echo htmlspecialchars($notification['message']); ?></p>
                                            <div class="notification-time">
                                                <i class="fas fa-clock"></i> 
                                                <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                                                <?php if (!$notification['is_read']): ?>
                                                    <span class="badge badge-success" style="margin-left: 1rem;">New</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div style="text-align: center; padding: 2rem;">
                                <i class="fas fa-bell-slash" style="font-size: 3rem; color: #bdc3c7; margin-bottom: 1rem;"></i>
                                <p>No notifications yet.</p>
                                <p class="text-muted">You'll be notified when materials are assigned or updated.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Contact Page -->
            <div class="page-content" id="contact">
                <h1>Contact Administrator</h1>
                <p>Send a message to the system administrator for assistance.</p>
                
                <div class="contact-info">
                    <div class="contact-card">
                        <i class="fas fa-envelope"></i>
                        <h3>Email Support</h3>
                        <p>admin@materialsystem.com</p>
                    </div>
                    <div class="contact-card">
                        <i class="fas fa-phone"></i>
                        <h3>Phone Support</h3>
                        <p>+255 XXX XXX XXX</p>
                    </div>
                    <div class="contact-card">
                        <i class="fas fa-clock"></i>
                        <h3>Response Time</h3>
                        <p>Within 24 hours</p>
                    </div>
                </div>
                
                <div class="form-container">
                    <form method="POST" action="">
                        <input type="hidden" name="send_message" value="1">
                        
                        <div class="form-group">
                            <label for="subject">Subject *</label>
                            <input type="text" id="subject" name="subject" required placeholder="Enter message subject">
                        </div>
                        
                        <div class="form-group">
                            <label for="message">Message *</label>
                            <textarea id="message" name="message" rows="6" required placeholder="Describe your issue or question in detail..."></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Send Message
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Check if we need to redirect to a specific page after notification click
            const redirectPage = sessionStorage.getItem('redirectPage');
            if (redirectPage) {
                navigateToPage(redirectPage);
                sessionStorage.removeItem('redirectPage');
            }

            // FIXED: Add event delegation for notification clicks
            document.addEventListener('click', function(e) {
                const notificationItem = e.target.closest('.notification-item');
                if (notificationItem) {
                    const notificationId = notificationItem.dataset.notificationId;
                    const targetPage = notificationItem.dataset.targetPage;
                    handleNotificationClick(notificationId, targetPage);
                }
            });

            // Navigation functionality
            const navLinks = document.querySelectorAll('.nav-link');
            const pageContents = document.querySelectorAll('.page-content');
            
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const pageId = this.getAttribute('data-page');
                    navigateToPage(pageId);
                });
            });
            
            // Theme toggle functionality
            const themeToggle = document.getElementById('themeToggle');
            const themeIcon = themeToggle.querySelector('i');
            
            // Check for saved theme or prefer-color-scheme
            const savedTheme = localStorage.getItem('theme') || 'dark';
            if (savedTheme === 'light') {
                document.body.classList.add('light-mode');
                themeIcon.classList.remove('fa-moon');
                themeIcon.classList.add('fa-sun');
            }
            
            themeToggle.addEventListener('click', function() {
                if (document.body.classList.contains('light-mode')) {
                    document.body.classList.remove('light-mode');
                    localStorage.setItem('theme', 'dark');
                    themeIcon.classList.remove('fa-sun');
                    themeIcon.classList.add('fa-moon');
                } else {
                    document.body.classList.add('light-mode');
                    localStorage.setItem('theme', 'light');
                    themeIcon.classList.remove('fa-moon');
                    themeIcon.classList.add('fa-sun');
                }
            });
            
            // Notification functionality
            const notificationToggle = document.getElementById('notificationToggle');
            const notificationDropdown = document.getElementById('notificationDropdown');
            const notificationBadge = document.getElementById('notificationBadge');

            // Toggle notification dropdown
            notificationToggle.addEventListener('click', function(e) {
                e.preventDefault();
                notificationDropdown.classList.toggle('show');
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!notificationToggle.contains(e.target) && !notificationDropdown.contains(e.target)) {
                    notificationDropdown.classList.remove('show');
                }
            });

            // Auto-close alerts after 5 seconds
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                });
            }, 5000);
            
            // Mobile menu toggle for smaller screens
            const header = document.querySelector('header');
            const sidebar = document.querySelector('.sidebar');
            const content = document.querySelector('.content');
            
            // Add mobile menu button for smaller screens
            if (window.innerWidth < 1200) {
                const menuToggle = document.createElement('button');
                menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
                menuToggle.style.background = 'var(--primary)';
                menuToggle.style.color = 'white';
                menuToggle.style.border = 'none';
                menuToggle.style.padding = '0.5rem';
                menuToggle.style.borderRadius = '4px';
                menuToggle.style.cursor = 'pointer';
                menuToggle.style.position = 'fixed';
                menuToggle.style.bottom = '20px';
                menuToggle.style.left = '20px';
                menuToggle.style.zIndex = '1000';
                
                menuToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                });
                
                document.body.appendChild(menuToggle);
                
                // Close sidebar when clicking on content
                content.addEventListener('click', function() {
                    if (window.innerWidth < 1200 && sidebar.classList.contains('active')) {
                        sidebar.classList.remove('active');
                    }
                });
            }
        });

        // Navigation function
        function navigateToPage(pageId) {
            const navLinks = document.querySelectorAll('.nav-link');
            const pageContents = document.querySelectorAll('.page-content');
            
            // Update active navigation link
            navLinks.forEach(nav => nav.classList.remove('active'));
            const targetNav = document.querySelector(`[data-page="${pageId}"]`);
            if (targetNav) {
                targetNav.classList.add('active');
            }
            
            // Show the corresponding page content
            pageContents.forEach(page => page.classList.remove('active'));
            const targetContent = document.getElementById(pageId);
            if (targetContent) {
                targetContent.classList.add('active');
                
                // Scroll to top of the content
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        }

        // Notification functions
        function handleNotificationClick(notificationId, targetPage) {
            // Create a form and submit it
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            form.style.display = 'none';
            
            const markReadInput = document.createElement('input');
            markReadInput.type = 'hidden';
            markReadInput.name = 'mark_notification_read';
            markReadInput.value = '1';
            form.appendChild(markReadInput);
            
            const notificationInput = document.createElement('input');
            notificationInput.type = 'hidden';
            notificationInput.name = 'notification_id';
            notificationInput.value = notificationId;
            form.appendChild(notificationInput);
            
            const targetPageInput = document.createElement('input');
            targetPageInput.type = 'hidden';
            targetPageInput.name = 'target_page';
            targetPageInput.value = targetPage;
            form.appendChild(targetPageInput);
            
            document.body.appendChild(form);
            form.submit();
        }

        function markAllNotificationsAsRead() {
            // This will submit the form that's already in the header
            closeNotificationDropdown();
        }

        function closeNotificationDropdown() {
            document.getElementById('notificationDropdown').classList.remove('show');
        }

        // Auto-refresh notifications every 30 seconds
        setInterval(function() {
            // Refresh the page if user is on notifications page
            if (document.getElementById('notifications-page').classList.contains('active')) {
                location.reload();
            }
        }, 30000);
    </script>
</body>
</html>