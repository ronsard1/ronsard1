<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: admin-login.php");
    exit();
}

require_once "config/database.php";

$database = new Database();
$conn = $database->getConnection();

$success = $error = "";

// Handle quick checkout (direct status change)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Quick checkout - change status to checked_out
    if (isset($_POST['quick_checkout'])) {
        try {
            $material_id = $_POST['material_id'];
            $cadet_telephone = $_POST['cadet_telephone'];
            
            // Find cadet by telephone
            $cadet = $conn->query("SELECT * FROM cadet WHERE number = '$cadet_telephone'")->fetch(PDO::FETCH_ASSOC);
            
            if ($cadet) {
                // Update material status to checked_out and assign to cadet
                $update_query = "UPDATE materials SET status = 'checked_out', cadet_id = ?, telephone = ?, checkout_date = NOW() WHERE material_id = ?";
                $stmt = $conn->prepare($update_query);
                
                if ($stmt->execute([$cadet['cadetid'], $cadet_telephone, $material_id])) {
                    // Record the checkout
                    $checkout_query = "INSERT INTO material_checkouts (material_id, cadet_id, purpose, destination, expected_return_date) VALUES (?, ?, 'quick_checkout', 'campus', DATE_ADD(CURDATE(), INTERVAL 7 DAY))";
                    $checkout_stmt = $conn->prepare($checkout_query);
                    $checkout_stmt->execute([$material_id, $cadet['cadetid']]);
                    
                    $success = "Material checked out successfully to " . $cadet['fname'] . " " . $cadet['lname'] . "!";
                }
            } else {
                $error = "No cadet found with this telephone number!";
            }
        } catch (PDOException $e) {
            $error = "Error checking out material: " . $e->getMessage();
        }
    }
    
    // Handle sending material outside institution
    if (isset($_POST['send_material_outside'])) {
        try {
            $material_name = $_POST['material_name'];
            $material_code = $_POST['material_code'];
            $cadet_telephone = $_POST['cadet_telephone'];
            $sent_to_person = $_POST['sent_to_person'];
            $sent_to_contact = $_POST['sent_to_contact'];
            $reason = $_POST['reason'];
            $external_notes = $_POST['external_notes'];
            
            // Find cadet by telephone
            $cadet = $conn->query("SELECT * FROM cadet WHERE number = '$cadet_telephone'")->fetch(PDO::FETCH_ASSOC);
            
            if ($cadet) {
                // Insert new material record for outside institution
                $insert_query = "INSERT INTO materials (
                    name, material_code, status, cadet_id, telephone, 
                    sent_to_person, sent_to_contact, reason, sent_date, external_notes, registered_date
                ) VALUES (?, ?, 'outside_institution', ?, ?, ?, ?, ?, CURDATE(), ?, NOW())";
                
                $stmt = $conn->prepare($insert_query);
                $stmt->execute([
                    $material_name, $material_code, $cadet['cadetid'], $cadet_telephone,
                    $sent_to_person, $sent_to_contact, $reason, $external_notes
                ]);
                
                $success = "Material sent outside institution successfully!";
            } else {
                $error = "No cadet found with this telephone number!";
            }
            
        } catch (PDOException $e) {
            $error = "Error sending material outside: " . $e->getMessage();
        }
        // Handle mark as taken - IMPROVED VERSION
if (isset($_POST['mark_taken'])) {
    try {
        $material_id = $_POST['material_id'];
        
        // First, let's debug what we're receiving
        error_log("Mark taken requested for material ID: " . $material_id);
        
        // Check the current status and details of the material
        $check_sql = "SELECT material_id, status, name, material_code FROM materials WHERE material_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->execute([$material_id]);
        $material = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        // If the material doesn't exist
        if (!$material) {
            $error = "Error: Material not found!";
            error_log("Material not found for ID: " . $material_id);
        } 
        // If the material status is already 'taken_outside'
        elseif ($material['status'] == 'taken_outside') {
            $error = "Info: This material was already confirmed as taken.";
            error_log("Material already taken: " . $material_id);
        }
        // If the material status is NOT 'outside_institution'
        elseif ($material['status'] != 'outside_institution') {
            $error = "Error: This material cannot be confirmed as taken. Its current status is '" . $material['status'] . "'.";
            error_log("Invalid status for mark_taken: " . $material['status'] . " for material ID: " . $material_id);
        } 
        // If all checks pass, proceed with the status update
        else {
            error_log("Proceeding with mark_taken for material ID: " . $material_id);
            
            // Check if taken_date column exists and use appropriate query
            $check_column = $conn->query("SHOW COLUMNS FROM materials LIKE 'taken_date'")->fetch();
            
            if ($check_column) {
                $update_sql = "UPDATE materials SET status = 'taken_outside', taken_date = NOW() WHERE material_id = ?";
                error_log("Using query with taken_date");
            } else {
                $update_sql = "UPDATE materials SET status = 'taken_outside' WHERE material_id = ?";
                error_log("Using query without taken_date");
            }
            
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->execute([$material_id]);
            $affected_rows = $update_stmt->rowCount();
            
            error_log("Update executed. Affected rows: " . $affected_rows);
            
            if ($affected_rows > 0) {
                // Verify the update
                $verify_sql = "SELECT status FROM materials WHERE material_id = ?";
                $verify_stmt = $conn->prepare($verify_sql);
                $verify_stmt->execute([$material_id]);
                $updated_material = $verify_stmt->fetch(PDO::FETCH_ASSOC);
                
                error_log("Verified new status: " . $updated_material['status']);
                
                logAction($conn, $_SESSION['user_id'], 'mark_taken', "Marked material as taken outside: ID $material_id");
                $success = "Material successfully marked as taken outside! Status updated to: " . $updated_material['status'];
            } else {
                $error = "No rows were updated. The material status may not have changed.";
                error_log("No rows affected in update for material ID: " . $material_id);
                
                // Additional debug: check what's actually in the database
                $debug_sql = "SELECT material_id, status, name FROM materials WHERE material_id = ?";
                $debug_stmt = $conn->prepare($debug_sql);
                $debug_stmt->execute([$material_id]);
                $debug_material = $debug_stmt->fetch(PDO::FETCH_ASSOC);
                error_log("Current database state - ID: " . $debug_material['material_id'] . ", Status: " . $debug_material['status'] . ", Name: " . $debug_material['name']);
            }
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
        error_log("PDOException in mark_taken: " . $e->getMessage());
    }
}
    }
    
    // Handle material return
    if (isset($_POST['return_material'])) {
        try {
            $material_id = $_POST['material_id'];
            
            // Update material status back to available and clear cadet info
            $update_material = "UPDATE materials SET status = 'available', cadet_id = NULL, telephone = NULL, sent_to_person = NULL, sent_to_contact = NULL, reason = NULL, sent_date = NULL, external_notes = NULL, checkout_date = NULL WHERE material_id = ?";
            $stmt = $conn->prepare($update_material);
            $stmt->execute([$material_id]);
            
            // Update checkout record
            $update_checkout = "UPDATE material_checkouts SET status = 'returned', return_date = CURRENT_TIMESTAMP WHERE material_id = ? AND status = 'active'";
            $stmt2 = $conn->prepare($update_checkout);
            $stmt2->execute([$material_id]);
            
            $success = "Material returned successfully!";
        } catch (PDOException $e) {
            $error = "Error returning material: " . $e->getMessage();
        }
    }
}

// Get available materials - FIXED QUERY
try {
    $available_materials = $conn->query("
        SELECT 
            m.material_id,
            m.material_code,
            m.name AS material_name,
            m.description,
            m.size,
            m.quantity,
            m.barcode,
            m.status,
            m.supplier_name,
            m.telephone,
            m.registered_date,
            c.cadetid,
            c.fname,
            c.lname,
            c.rollno,
            c.number AS cadet_phone
        FROM materials m 
        LEFT JOIN cadet c ON m.telephone = c.number 
        WHERE m.status IS NULL OR m.status = 'available' OR m.status = ''
        ORDER BY m.registered_date DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $available_materials = [];
}

// Get checked out materials - FIXED QUERY: Now correctly filters for checked_out status
try {
    $checked_out_materials = $conn->query("
        SELECT 
            m.material_id,
            m.material_code,
            m.name AS material_name,
            m.description,
            m.size,
            m.quantity,
            m.barcode,
            m.status,
            m.supplier_name,
            m.checkout_date,
            m.registered_date,
            c.fname,
            c.lname,
            c.rollno,
            c.number AS cadet_phone
        FROM materials m 
        LEFT JOIN cadet c ON m.telephone = c.number 
        WHERE m.status = 'checked_out'
        ORDER BY m.checkout_date DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $checked_out_materials = [];
}

// Get materials outside institution
try {
    $outside_materials = $conn->query("
        SELECT 
            m.material_id,
            m.material_code,
            m.name AS material_name,
            m.sent_to_person,
            m.sent_to_contact,
            m.reason,
            m.sent_date,
            m.registered_date,
            c.fname,
            c.lname,
            c.rollno,
            c.number AS cadet_phone
        FROM materials m 
        LEFT JOIN cadet c ON m.telephone = c.number 
        WHERE m.status = 'outside_institution'
        ORDER BY m.sent_date DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $outside_materials = [];
}

// Get all cadets
try {
    $cadets = $conn->query("SELECT cadetid, fname, lname, rollno, company, platoon, number FROM cadet ORDER BY fname, lname")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $cadets = [];
}

// Get statistics for charts
$total_materials = $conn->query("SELECT COUNT(*) as count FROM materials")->fetch()['count'];
$total_available = $conn->query("SELECT COUNT(*) as count FROM materials WHERE status IS NULL OR status = 'available' OR status = ''")->fetch()['count'];
$total_checked_out = $conn->query("SELECT COUNT(*) as count FROM materials WHERE status = 'checked_out'")->fetch()['count'];
$total_outside = $conn->query("SELECT COUNT(*) as count FROM materials WHERE status = 'outside_institution'")->fetch()['count'];
$total_cadets = count($cadets);

// Get recent activities (last 7 days)
try {
    $recent_activities = $conn->query("
        (SELECT 'checkout' as type, material_id, checkout_date as date, 'Material Checked Out' as description 
         FROM material_checkouts 
         WHERE checkout_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
         ORDER BY checkout_date DESC 
         LIMIT 10)
        UNION ALL
        (SELECT 'return' as type, material_id, return_date as date, 'Material Returned' as description 
         FROM material_checkouts 
         WHERE return_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
         AND status = 'returned'
         ORDER BY return_date DESC 
         LIMIT 10)
        ORDER BY date DESC 
        LIMIT 15
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recent_activities = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IO Dashboard - Materials Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #e74c3c;
            --secondary: #c0392b;
            --success: #27ae60;
            --warning: #f39c12;
            --danger: #e74c3c;
            --info: #3498db;
            --light: #ecf0f1;
            --dark: #2c3e50;
            --sidebar-width: 70px;
            --sidebar-expanded-width: 250px;
            --header-bg: #2c3e50;
            --sidebar-bg: #34495e;
            --sidebar-text: #ecf0f1;
            --content-bg: #f8f9fa;
            --card-bg: #ffffff;
            --text-color: #2c3e50;
            --border-color: #dee2e6;
            
            /* INCREASED FONT SIZES */
            --base-font-size: 15px;
            --table-font-size: 0.9rem;
            --header-font-size: 1.4rem;
            --card-font-size: 0.95rem;
        }

        .dark-mode {
            --header-bg: #1a1a1a;
            --sidebar-bg: #252525;
            --sidebar-text: #f0f0f0;
            --content-bg: #1a1a1a;
            --card-bg: #2d2d2d;
            --text-color: #f0f0f0;
            --border-color: #444444;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: var(--content-bg);
            color: var(--text-color);
            line-height: 1.6;
            height: 100vh;
            overflow: hidden;
            font-size: var(--base-font-size); /* Increased base font size */
        }
        
        /* Header Styles */
        .header {
            background: var(--header-bg);
            color: white;
            padding: 0.4rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            height: 55px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 100%;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .logo {
            font-size: 1.2rem; /* Increased from 1rem */
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .logo i {
            font-size: 1.4rem; /* Increased from 1.2rem */
            color: #f39c12;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-role {
            background: var(--primary);
            padding: 0.3rem 0.7rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .theme-toggle {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            color: var(--text-color);
            padding: 0.4rem;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.9rem;
        }
        
        /* Main Layout */
        .main-container {
            display: flex;
            height: 100vh;
            padding-top: 55px;
        }
        
        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            color: var(--sidebar-text);
            position: fixed;
            top: 55px;
            left: 0;
            bottom: 0;
            overflow-y: auto;
            transition: all 0.3s ease;
            box-shadow: 3px 0 15px rgba(0,0,0,0.1);
            z-index: 999;
        }

        .sidebar.expanded {
            width: var(--sidebar-expanded-width);
        }
        
        .sidebar-content {
            padding: 1rem 0.6rem;
        }

        .sidebar.expanded .sidebar-content {
            padding: 1rem 1rem;
        }
        
        .sidebar-menu {
            list-style: none;
        }
        
        .sidebar-menu li {
            margin-bottom: 0.3rem;
        }
        
        .sidebar-menu a {
            color: var(--sidebar-text);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 0.7rem; /* Increased padding */
            border-radius: 8px;
            transition: all 0.3s;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            font-size: 0.9rem; /* Increased font size */
        }

        .sidebar-menu a span {
            display: none;
        }

        .sidebar.expanded .sidebar-menu a span {
            display: inline;
        }
        
        .sidebar-menu a:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .sidebar-menu a.active {
            background: var(--primary);
        }
        
        .sidebar-menu a i {
            font-size: 1.1rem; /* Increased icon size */
            width: 20px;
            text-align: center;
        }

        .sidebar-toggle {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.4rem;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 0.8rem;
            width: 100%;
            font-size: 0.85rem;
        }
        
        /* Content Area */
        .content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 1.2rem; /* Increased padding */
            background: var(--content-bg);
            height: calc(100vh - 55px);
            transition: all 0.3s ease;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }

        .sidebar.expanded + .content {
            margin-left: var(--sidebar-expanded-width);
        }
        
        /* Page content */
        .page-content {
            display: none;
            flex: 1;
            flex-direction: column;
        }
        
        .page-content.active {
            display: flex;
            animation: fadeInUp 0.3s ease;
        }
        
        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.2rem; /* Increased margin */
            padding-bottom: 0.8rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .page-title {
            color: var(--dark);
            font-size: var(--header-font-size); /* Increased size */
            font-weight: 600;
        }
        
        .page-description {
            color: #666;
            font-size: 0.9rem; /* Increased size */
            margin-top: 0.2rem;
        }
        
        /* Dashboard Cards */
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.2rem; /* Increased gap */
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: var(--card-bg);
            border-radius: 8px;
            padding: 1.2rem; /* Increased padding */
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            text-align: center;
            border-left: 4px solid var(--primary);
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.12);
        }
        
        .stat {
            font-size: 2rem; /* Increased size */
            font-weight: 700;
            color: var(--primary);
            line-height: 1;
            margin: 0.8rem 0;
        }
        
        /* Chart Container */
        .chart-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem; /* Increased gap */
            margin-bottom: 2rem;
        }
        
        .chart-card {
            background: var(--card-bg);
            border-radius: 8px;
            padding: 1.5rem; /* Increased padding */
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .chart-card h3 {
            margin-bottom: 1rem;
            color: var(--dark);
            font-size: 1.1rem; /* Increased size */
            font-weight: 600;
        }
        
        .chart-wrapper {
            height: 250px; /* Increased height */
            position: relative;
        }
        
        /* Table Container - FULL WIDTH */
        .table-container {
            background: var(--card-bg);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            flex: 1;
            display: flex;
            flex-direction: column;
            margin-bottom: 0;
            width: 100%; /* Full width */
        }
        
        .table-wrapper {
            flex: 1;
            overflow: auto;
            width: 100%;
        }
        
        table {
            width: 100%; /* Full width tables */
            border-collapse: collapse;
            min-width: 1000px;
        }
        
        th, td {
            padding: 0.8rem; /* Increased padding */
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            font-size: var(--table-font-size); /* Increased font size */
        }
        
        th {
            background: var(--sidebar-bg);
            color: var(--sidebar-text);
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
            font-size: 0.85rem; /* Increased size */
            white-space: nowrap;
        }
        
        /* Form Styles */
        .form-container {
            background: var(--card-bg);
            border-radius: 8px;
            padding: 1.5rem; /* Increased padding */
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 1rem;
            font-size: var(--card-font-size); /* Increased font size */
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem; /* Increased gap */
        }
        
        .form-group {
            margin-bottom: 1rem; /* Increased margin */
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.4rem; /* Increased margin */
            font-weight: 600;
            color: var(--dark);
            font-size: 0.9rem; /* Increased size */
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.7rem; /* Increased padding */
            border: 1px solid var(--border-color);
            border-radius: 5px;
            background: var(--card-bg);
            color: var(--text-color);
            transition: all 0.3s;
            font-size: 0.9rem; /* Increased size */
        }
        
        .btn {
            padding: 0.7rem 1.2rem; /* Increased padding */
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
            font-size: 0.9rem; /* Increased size */
        }
        
        .btn-sm {
            padding: 0.5rem 0.9rem; /* Increased padding */
            font-size: 0.85rem; /* Increased size */
        }
        
        /* Search Bar */
        .search-container {
            margin-bottom: 1rem;
        }
        
        .search-box {
            width: 100%;
            padding: 0.7rem; /* Increased padding */
            border: 1px solid var(--border-color);
            border-radius: 5px;
            font-size: 0.9rem; /* Increased size */
            background: var(--card-bg);
            color: var(--text-color);
        }
        
        /* Badges */
        .badge {
            padding: 0.3rem 0.6rem; /* Increased padding */
            border-radius: 12px;
            font-size: 0.8rem; /* Increased size */
            font-weight: 600;
            display: inline-block;
        }
        
        .badge-available { background: #27ae60; color: white; }
        .badge-checked-out { background: #f39c12; color: white; }
        .badge-outside { background: #3498db; color: white; }
        
        /* Alerts */
        .alert {
            padding: 0.8rem; /* Increased padding */
            border-radius: 5px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 0.9rem; /* Increased size */
        }
        
        /* Quick Actions */
        .quick-actions {
            display: flex;
            gap: 0.8rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }
        
        /* Recent Activities */
        .activities-list {
            background: var(--card-bg);
            border-radius: 8px;
            padding: 1.2rem; /* Increased padding */
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            max-height: 350px; /* Increased height */
            overflow-y: auto;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.8rem; /* Increased padding */
            border-bottom: 1px solid var(--border-color);
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 35px; /* Increased size */
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.9rem; /* Increased size */
        }
        
        .activity-checkout {
            background: var(--success);
        }
        
        .activity-return {
            background: var(--info);
        }
        
        .activity-details {
            flex: 1;
        }
        
        .activity-date {
            color: #666;
            font-size: 0.85rem; /* Increased size */
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: var(--card-bg);
            border-radius: 8px;
            padding: 1.5rem;
            width: 95%;
            max-width: 450px; /* Increased width */
            max-height: 90vh;
            overflow-y: auto;
            font-size: var(--card-font-size);
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.3rem; /* Increased size */
            cursor: pointer;
            color: var(--danger);
            float: right;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 1024px) {
            .chart-container {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .content {
                padding: 1rem;
            }
            
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
                width: 100%;
            }
            
            .content {
                margin-left: 0;
            }
        }

        .badge {
            padding: 0.3rem 0.6rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .badge-warning { background: #f39c12; color: white; }
        .badge-success { background: #27ae60; color: white; }
        .badge-info { background: #3498db; color: white; }
        
        .no-match { color: #999; font-style: italic; }
        .text-muted { color: #6c757d; }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            width: 95%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.3rem;
            cursor: pointer;
            color: #e74c3c;
            float: right;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <div style="display: flex; align-items: center; gap: 0.8rem;">
                <div class="logo">
                    <i class="fas fa-user-shield"></i>
                    <span>IO Dashboard</span>
                </div>
            </div>
            <div class="header-actions">
                <button class="theme-toggle" id="themeToggle">
                    <i class="fas fa-moon"></i>
                </button>
                <span class="user-role">IO</span>
                <span style="font-size: 0.9rem;"><?php echo $_SESSION['full_name']; ?></span>
                <a href="logout.php" class="btn btn-danger btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>

    <div class="main-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-content">
                <button class="sidebar-toggle" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <ul class="sidebar-menu">
                    <li><a href="#" class="nav-link active" data-page="dashboard"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                    <li><a href="#" class="nav-link" data-page="available-materials"><i class="fas fa-check-circle"></i> <span>Available</span></a></li>
                    <li><a href="#" class="nav-link" data-page="checked-out-materials"><i class="fas fa-user-graduate"></i> <span>Checked Out</span></a></li>
                    <li><a href="#" class="nav-link" data-page="send-outside"><i class="fas fa-external-link-alt"></i> <span>Send Outside</span></a></li>
                    <li><a href="#" class="nav-link" data-page="outside-materials"><i class="fas fa-truck"></i> <span>Outside</span></a></li>
                    <li><a href="#" class="nav-link" data-page="taken-materials"><i class="fas fa-check-circle"></i> <span>Taken Outside</span></a></li>
                    <li><a href="#" class="nav-link" data-page="cadet-management"><i class="fas fa-users"></i> <span>Cadets</span></a></li>
                </ul>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content">
            <!-- Success/Error Messages -->
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Dashboard Page with Charts -->
            <div class="page-content active" id="dashboard">
                <div class="page-header">
                    <div>
                        <h1 class="page-title">IO Dashboard</h1>
                        <p class="page-description">Welcome, <?php echo $_SESSION['full_name']; ?>! Materials management overview.</p>
                    </div>
                </div>
                
                <div class="dashboard-cards">
                    <div class="stat-card">
                        <i class="fas fa-box fa-lg"></i>
                        <div class="stat"><?php echo $total_materials; ?></div>
                        <p>Total Materials</p>
                    </div>
                    
                    <div class="stat-card">
                        <i class="fas fa-check-circle fa-lg"></i>
                        <div class="stat"><?php echo $total_available; ?></div>
                        <p>Available in Stock</p>
                    </div>
                    
                    <div class="stat-card">
                        <i class="fas fa-user-graduate fa-lg"></i>
                        <div class="stat"><?php echo $total_checked_out; ?></div>
                        <p>With Cadets</p>
                    </div>
                    
                    <div class="stat-card">
                        <i class="fas fa-external-link-alt fa-lg"></i>
                        <div class="stat"><?php echo $total_outside; ?></div>
                        <p>Outside Institution</p>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="chart-container">
                    <div class="chart-card">
                        <h3>Materials Distribution</h3>
                        <div class="chart-wrapper">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-card">
                        <h3>Materials Status Overview</h3>
                        <div class="chart-wrapper">
                            <canvas id="doughnutChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Recent Activities -->
                <div class="card">
                    <div class="card-header">
                        <h2>Recent Activities</h2>
                    </div>
                    <div class="activities-list">
                        <?php if (!empty($recent_activities)): ?>
                            <?php foreach ($recent_activities as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon <?php echo $activity['type'] === 'checkout' ? 'activity-checkout' : 'activity-return'; ?>">
                                        <i class="fas fa-<?php echo $activity['type'] === 'checkout' ? 'hand-holding' : 'undo'; ?>"></i>
                                    </div>
                                    <div class="activity-details">
                                        <strong><?php echo $activity['description']; ?></strong>
                                        <div class="activity-date">
                                            <?php echo date('M j, Y g:i A', strtotime($activity['date'])); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="text-align: center; color: #666; padding: 2rem;">No recent activities found.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="quick-actions">
                    <button class="btn btn-primary" onclick="showPage('available-materials')">
                        <i class="fas fa-box"></i> Available Materials
                    </button>
                    <button class="btn btn-info" onclick="showPage('send-outside')">
                        <i class="fas fa-external-link-alt"></i> Send Outside
                    </button>
                    <button class="btn btn-warning" onclick="showPage('checked-out-materials')">
                        <i class="fas fa-clipboard-check"></i> Checked Out
                    </button>
                </div>
            </div>

            <!-- Available Materials Page -->
            <div class="page-content" id="available-materials">
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Available Materials in Stock</h1>
                        <p class="page-description">All materials currently available in stock. Click checkout to assign to cadets.</p>
                    </div>
                </div>
                
                <div class="search-container">
                    <input type="text" id="availableSearch" class="search-box" placeholder="Search available materials by name, code, or cadet...">
                </div>
                
                <div class="table-container">
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Material Code</th>
                                    <th>Material Name</th>
                                    <th>Description</th>
                                    <th>Quantity</th>
                                    <th>Cadet Name</th>
                                    <th>Phone</th>
                                    <th>Registered Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="availableTable">
                                <?php foreach ($available_materials as $material): ?>
                                    <tr>
                                        <td><strong><?php echo $material['material_code']; ?></strong></td>
                                        <td><?php echo $material['material_name']; ?></td>
                                        <td><?php echo $material['description'] ?: 'No description'; ?></td>
                                        <td><?php echo $material['quantity']; ?></td>
                                        <td>
                                            <?php if ($material['fname']): ?>
                                                <strong><?php echo $material['fname'] . ' ' . $material['lname']; ?></strong>
                                            <?php else: ?>
                                                <span style="color: #999;">Not assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $material['telephone'] ?? 'N/A'; ?></td>
                                        <td><?php echo date('M j, Y', strtotime($material['registered_date'])); ?></td>
                                        <td>
                                            <span class="badge badge-available">Available</span>
                                        </td>
                                        <td>
                                            <button class="btn btn-primary btn-sm" onclick="quickCheckout(<?php echo $material['material_id']; ?>, '<?php echo $material['telephone'] ?? ''; ?>')">
                                                <i class="fas fa-hand-holding"></i> Checkout
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Checked Out Materials Page -->
            <div class="page-content" id="checked-out-materials">
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Checked Out Materials</h1>
                        <p class="page-description">Materials currently checked out from stock to cadets.</p>
                    </div>
                </div>
                
                <div class="table-container">
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Cadet Name</th>
                                    <th>Roll No</th>
                                    <th>Phone</th>
                                    <th>Material Code</th>
                                    <th>Material Name</th>
                                    <th>Quantity</th>
                                    <th>Registered Date</th>
                                    <th>Checkout Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($checked_out_materials as $material): ?>
                                    <tr>
                                        <td>
                                            <?php if ($material['fname']): ?>
                                                <strong><?php echo $material['fname'] . ' ' . $material['lname']; ?></strong>
                                            <?php else: ?>
                                                <span style="color: #999;">Unknown</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $material['rollno'] ?? 'N/A'; ?></td>
                                        <td><?php echo $material['cadet_phone'] ?? 'N/A'; ?></td>
                                        <td><strong><?php echo $material['material_code']; ?></strong></td>
                                        <td><?php echo $material['material_name']; ?></td>
                                        <td><?php echo $material['quantity']; ?></td>
                                        <td><?php echo date('M j, Y', strtotime($material['registered_date'])); ?></td>
                                        <td>
                                            <?php if ($material['checkout_date']): ?>
                                                <?php echo date('M j, Y', strtotime($material['checkout_date'])); ?>
                                            <?php else: ?>
                                                <span style="color: #999;">Not recorded</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-checked-out">Checked Out</span>
                                        </td>
                                        <td>
                                            <button class="btn btn-success btn-sm" onclick="returnMaterial(<?php echo $material['material_id']; ?>)">
                                                <i class="fas fa-undo"></i> Return
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Other pages (Send Outside, Outside Materials, Cadet Management) remain the same but with increased font sizes -->
            <!-- Send Outside Page -->
            <div class="page-content" id="send-outside">
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Send Materials Outside Institution</h1>
                        <p class="page-description">Register materials to be sent outside the institution.</p>
                    </div>
                </div>
                
                <div class="form-container">
                    <h3 style="margin-bottom: 1rem; font-size: 1.2rem;">Send Material Outside Institution</h3>
                    <form method="POST" action="">
                        <input type="hidden" name="send_material_outside" value="1">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="material_name">Material Name *</label>
                                <input type="text" id="material_name" name="material_name" required placeholder="Enter material name">
                            </div>
                            
                            <div class="form-group">
                                <label for="material_code">Material Code *</label>
                                <input type="text" id="material_code" name="material_code" required placeholder="Enter material code">
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="cadet_telephone">Cadet Telephone *</label>
                                <input type="text" id="cadet_telephone" name="cadet_telephone" required placeholder="Enter cadet telephone number">
                            </div>
                            
                            <div class="form-group">
                                <label for="sent_to_person">Sent To Person/Company *</label>
                                <input type="text" id="sent_to_person" name="sent_to_person" required placeholder="Name of person or company">
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="sent_to_contact">Contact Number *</label>
                                <input type="text" id="sent_to_contact" name="sent_to_contact" required placeholder="Contact number">
                            </div>
                            
                            <div class="form-group">
                                <label for="reason">Reason *</label>
                                <select id="reason" name="reason" required>
                                    <option value="">Select reason...</option>
                                    <option value="repair">Repair</option>
                                    <option value="modification">Modification</option>
                                    <option value="external_use">External Use</option>
                                    <option value="calibration">Calibration</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="external_notes">Additional Notes</label>
                            <textarea id="external_notes" name="external_notes" rows="3" placeholder="Details about why the material is being sent out..."></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-external-link-alt"></i> Send Outside
                        </button>
                    </form>
                </div>
            </div>

            <!-- Outside Materials Page -->
            <div class="page-content" id="outside-materials">
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Materials Outside Institution</h1>
                        <p class="page-description">All materials currently sent outside the institution.</p>
                    </div>
                </div>
                
                <div class="table-container">
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Cadet Name</th>
                                    <th>Roll No</th>
                                    <th>Phone</th>
                                    <th>Material</th>
                                    <th>Sent To</th>
                                    <th>Contact</th>
                                    <th>Reason</th>
                                    <th>Sent Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($outside_materials as $material): ?>
                                    <tr>
                                        <td>
                                            <?php if ($material['fname']): ?>
                                                <strong><?php echo $material['fname'] . ' ' . $material['lname']; ?></strong>
                                            <?php else: ?>
                                                <span style="color: #999;">Unknown</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $material['rollno'] ?? 'N/A'; ?></td>
                                        <td><?php echo $material['cadet_phone'] ?? 'N/A'; ?></td>
                                        <td>
                                            <strong><?php echo $material['material_name']; ?></strong><br>
                                            <small><?php echo $material['material_code']; ?></small>
                                        </td>
                                        <td><?php echo $material['sent_to_person']; ?></td>
                                        <td><?php echo $material['sent_to_contact']; ?></td>
                                        <td><?php echo ucfirst($material['reason']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($material['sent_date'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
    
              <!-- Taken Outside Materials Page -->
<div class="page-content" id="taken-materials">
    <div class="page-header">
        <div>
            <h1 class="page-title">Materials Taken Outside</h1>
            <p class="page-description">All materials confirmed as taken outside the institution with complete details.</p>
        </div>
    </div>

    <div class="table-container">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Cadet Name</th>
                        <th>Roll No</th>
                        <th>Phone</th>
                        <th>Material</th>
                        <th>Sent To</th>
                        <th>Contact</th>
                        <th>Reason</th>
                        <th>Sent Date</th>
                        <th>Taken Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // Get taken outside materials
                    $taken_materials = $conn->query("
                        SELECT m.*, c.fname, c.lname, c.rollno, c.number as cadet_phone
                        FROM materials m 
                        LEFT JOIN cadet c ON m.telephone = c.number 
                        WHERE m.status = 'taken_outside'
                        ORDER BY m.taken_date DESC, m.sent_date DESC
                    ")->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!empty($taken_materials)): 
                        foreach ($taken_materials as $material): 
                    ?>
                        <tr>
                            <td>
                                <?php if ($material['fname']): ?>
                                    <strong><?php echo $material['fname'] . ' ' . $material['lname']; ?></strong>
                                <?php else: ?>
                                    <span class="no-match">Not assigned</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $material['rollno'] ?? 'N/A'; ?></td>
                            <td><?php echo $material['telephone']; ?></td>
                            <td>
                                <strong><?php echo $material['name']; ?></strong><br>
                                <small>Code: <?php echo $material['material_code']; ?></small>
                            </td>
                            <td><?php echo $material['sent_to_person'] ?? 'N/A'; ?></td>
                            <td><?php echo $material['sent_to_contact'] ?? 'N/A'; ?></td>
                            <td><?php echo $material['reason'] ?? 'N/A'; ?></td>
                            <td><?php echo $material['sent_date'] ? date('M j, Y', strtotime($material['sent_date'])) : 'N/A'; ?></td>
                            <td>
                                <?php if (isset($material['taken_date']) && $material['taken_date']): ?>
                                    <?php echo date('M j, Y', strtotime($material['taken_date'])); ?>
                                <?php else: ?>
                                    <span class="text-muted">Not recorded</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-success">Taken Outside</span>
                            </td>
                        </tr>
                    <?php 
                        endforeach; 
                    else: 
                    ?>
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 2rem;">
                                <p>No materials confirmed as taken outside yet.</p>
                                <a href="#" class="btn btn-primary nav-link" data-page="outside-materials">
                                    <i class="fas fa-external-link-alt"></i> View Materials Ready for Confirmation
                                </a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
            <!-- Cadet Management Page -->
            <div class="page-content" id="cadet-management">
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Cadet Management</h1>
                        <p class="page-description">View and manage all cadets in the system.</p>
                    </div>
                </div>
                
                <div class="table-container">
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Roll No</th>
                                    <th>Company</th>
                                    <th>Platoon</th>
                                    <th>Phone</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cadets as $cadet): ?>
                                    <tr>
                                        <td><strong><?php echo $cadet['fname'] . ' ' . $cadet['lname']; ?></strong></td>
                                        <td><?php echo $cadet['rollno']; ?></td>
                                        <td><?php echo $cadet['company']; ?></td>
                                        <td><?php echo $cadet['platoon']; ?></td>
                                        <td><?php echo $cadet['number']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Checkout Modal -->
    <div class="modal" id="quickCheckoutModal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('quickCheckoutModal')">&times;</button>
            <h2>Quick Checkout Material</h2>
            <form method="POST" action="">
                <input type="hidden" name="quick_checkout" value="1">
                <input type="hidden" id="checkoutMaterialId" name="material_id">
                
                <div class="form-group">
                    <label for="cadet_telephone">Cadet Telephone Number *</label>
                    <input type="text" id="checkoutCadetTelephone" name="cadet_telephone" required placeholder="Enter cadet telephone number">
                    <small style="color: #666;">Enter the telephone number of the cadet taking this material</small>
                </div>
                
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-check-circle"></i> Checkout Material
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('quickCheckoutModal')">
                    Cancel
                </button>
            </form>
        </div>
    </div>

    <!-- Return Material Modal -->
    <div class="modal" id="returnMaterialModal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('returnMaterialModal')">&times;</button>
            <h2>Return Material</h2>
            <p>Are you sure you want to mark this material as returned?</p>
            <form method="POST" action="">
                <input type="hidden" name="return_material" value="1">
                <input type="hidden" id="returnMaterialId" name="material_id">
                
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-check"></i> Confirm Return
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('returnMaterialModal')">
                    Cancel
                </button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Navigation functionality
            const navLinks = document.querySelectorAll('.nav-link');
            const pageContents = document.querySelectorAll('.page-content');
            
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const pageId = this.getAttribute('data-page');
                    
                    navLinks.forEach(nav => nav.classList.remove('active'));
                    this.classList.add('active');
                    
                    pageContents.forEach(page => page.classList.remove('active'));
                    document.getElementById(pageId).classList.add('active');
                });
            });
            
            // Theme toggle functionality
            const themeToggle = document.getElementById('themeToggle');
            const themeIcon = themeToggle.querySelector('i');
            
            const savedTheme = localStorage.getItem('theme') || 'light';
            if (savedTheme === 'dark') {
                document.body.classList.add('dark-mode');
                themeIcon.classList.remove('fa-moon');
                themeIcon.classList.add('fa-sun');
            }
            
            themeToggle.addEventListener('click', function() {
                if (document.body.classList.contains('dark-mode')) {
                    document.body.classList.remove('dark-mode');
                    localStorage.setItem('theme', 'light');
                    themeIcon.classList.remove('fa-sun');
                    themeIcon.classList.add('fa-moon');
                } else {
                    document.body.classList.add('dark-mode');
                    localStorage.setItem('theme', 'dark');
                    themeIcon.classList.remove('fa-moon');
                    themeIcon.classList.add('fa-sun');
                }
            });
            
            // Search functionality
            initializeSearch('availableSearch', 'availableTable');
            
            // Auto-close alerts after 5 seconds
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                });
            }, 5000);
            
            // Initialize Charts
            initializeCharts();
        });
        
        // Initialize Charts
        function initializeCharts() {
            // Status Distribution Chart
            const statusCtx = document.getElementById('statusChart').getContext('2d');
            const statusChart = new Chart(statusCtx, {
                type: 'bar',
                data: {
                    labels: ['Available', 'Checked Out', 'Outside'],
                    datasets: [{
                        label: 'Materials Count',
                        data: [<?php echo $total_available; ?>, <?php echo $total_checked_out; ?>, <?php echo $total_outside; ?>],
                        backgroundColor: [
                            'rgba(39, 174, 96, 0.8)',
                            'rgba(243, 156, 18, 0.8)',
                            'rgba(52, 152, 219, 0.8)'
                        ],
                        borderColor: [
                            'rgba(39, 174, 96, 1)',
                            'rgba(243, 156, 18, 1)',
                            'rgba(52, 152, 219, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
            
            // Doughnut Chart
            const doughnutCtx = document.getElementById('doughnutChart').getContext('2d');
            const doughnutChart = new Chart(doughnutCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Available', 'Checked Out', 'Outside'],
                    datasets: [{
                        data: [<?php echo $total_available; ?>, <?php echo $total_checked_out; ?>, <?php echo $total_outside; ?>],
                        backgroundColor: [
                            'rgba(39, 174, 96, 0.8)',
                            'rgba(243, 156, 18, 0.8)',
                            'rgba(52, 152, 219, 0.8)'
                        ],
                        borderColor: [
                            'rgba(39, 174, 96, 1)',
                            'rgba(243, 156, 18, 1)',
                            'rgba(52, 152, 219, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
        
        // Search functionality
        function initializeSearch(searchId, tableId) {
            const searchInput = document.getElementById(searchId);
            const tableBody = document.getElementById(tableId);
            
            if (searchInput && tableBody) {
                searchInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    const rows = tableBody.getElementsByTagName('tr');
                    
                    for (let row of rows) {
                        const text = row.textContent.toLowerCase();
                        row.style.display = text.includes(searchTerm) ? '' : 'none';
                    }
                });
            }
        }
        
        // Modal functions
        function quickCheckout(materialId, cadetPhone = '') {
            document.getElementById('checkoutMaterialId').value = materialId;
            const phoneInput = document.getElementById('checkoutCadetTelephone');
            phoneInput.value = cadetPhone;
            if (!cadetPhone) {
                phoneInput.focus();
            }
            document.getElementById('quickCheckoutModal').style.display = 'flex';
        }
        
        function returnMaterial(materialId) {
            document.getElementById('returnMaterialId').value = materialId;
            document.getElementById('returnMaterialModal').style.display = 'flex';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function showPage(pageId) {
            const navLinks = document.querySelectorAll('.nav-link');
            const pageContents = document.querySelectorAll('.page-content');
            
            navLinks.forEach(nav => nav.classList.remove('active'));
            pageContents.forEach(page => page.classList.remove('active'));
            
            document.getElementById(pageId).classList.add('active');
            
            // Find and activate the corresponding nav link
            const correspondingNav = document.querySelector(`[data-page="${pageId}"]`);
            if (correspondingNav) {
                correspondingNav.classList.add('active');
            }
        }
        
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('expanded');
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>