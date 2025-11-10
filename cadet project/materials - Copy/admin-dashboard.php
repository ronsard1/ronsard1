<?php
require_once "config/database.php";
require_once "auth/check_role.php";

checkRole('admin');

$database = new Database();
$conn = $database->getConnection();

$success = $error = "";

// Handle user management
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Add new user
    if (isset($_POST['add_user'])) {
        try {
            $username = $_POST['username'];
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $full_name = $_POST['full_name'];
            $email = $_POST['email'];
            $role = $_POST['role'];
            $cadet_id = $_POST['cadet_id'] ?? null;
            
            $query = "INSERT INTO users (username, password, full_name, email, role, cadet_id) 
                      VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            
            if ($stmt->execute([$username, $password, $full_name, $email, $role, $cadet_id])) {
                // Create audit log if table exists
                try {
                    $conn->exec("
                        CREATE TABLE IF NOT EXISTS audit_log (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            user_id INT NOT NULL,
                            action VARCHAR(100) NOT NULL,
                            description TEXT,
                            ip_address VARCHAR(45),
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )
                    ");
                    $log_query = "INSERT INTO audit_log (user_id, action, description, ip_address) VALUES (?, 'add_user', ?, ?)";
                    $log_stmt = $conn->prepare($log_query);
                    $log_stmt->execute([$_SESSION['user_id'], "Added new user: $username ($role)", $_SERVER['REMOTE_ADDR']]);
                } catch (Exception $e) {
                    // Audit log failed, but continue
                }
                
                $success = "User added successfully!";
            }
        } catch (PDOException $e) {
            $error = "Error adding user: " . $e->getMessage();
        }
    }
    
    // Update material status
    if (isset($_POST['update_status'])) {
        try {
            $material_id = $_POST['material_id'];
            $status = $_POST['status'];
            
            $query = "UPDATE materials SET status = ? WHERE material_id = ?";
            $stmt = $conn->prepare($query);
            
            if ($stmt->execute([$status, $material_id])) {
                $success = "Material status updated successfully!";
            }
        } catch (PDOException $e) {
            $error = "Error updating status: " . $e->getMessage();
        }
    }
}

// Delete operations
if (isset($_GET['delete_user'])) {
    try {
        $user_id = $_GET['delete_user'];
        $user = $conn->query("SELECT username FROM users WHERE userid = $user_id")->fetch();
        
        $query = "DELETE FROM users WHERE userid = ?";
        $stmt = $conn->prepare($query);
        
        if ($stmt->execute([$user_id])) {
            $success = "User deleted successfully!";
        }
    } catch (PDOException $e) {
        $error = "Error deleting user: " . $e->getMessage();
    }
}

// Get data for admin - Safe query that works even if cadet_id column doesn't exist
try {
    $users = $conn->query("SELECT u.*, c.fname, c.lname FROM users u LEFT JOIN cadet c ON u.cadet_id = c.cadetid")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If join fails, get users without cadet info
    $users = $conn->query("SELECT * FROM users")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($users as &$user) {
        $user['fname'] = 'Not';
        $user['lname'] = 'Linked';
    }
}

$materials = $conn->query("SELECT m.*, c.fname, c.lname FROM materials m LEFT JOIN cadet c ON m.telephone = c.number ORDER BY m.registered_date DESC")->fetchAll(PDO::FETCH_ASSOC);
$cadets = $conn->query("SELECT * FROM cadet ORDER BY fname, lname")->fetchAll(PDO::FETCH_ASSOC);

// Get admin statistics
$admin_stats = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM users WHERE role = 'gatekeeper') as gatekeepers,
        (SELECT COUNT(*) FROM users WHERE role = 'student') as students,
        (SELECT COUNT(*) FROM materials) as total_materials,
        (SELECT COUNT(*) FROM cadet) as total_cadets,
        (SELECT COUNT(*) FROM materials WHERE DATE(registered_date) = CURDATE()) as today_materials
")->fetch(PDO::FETCH_ASSOC);

// Get logs if audit_log table exists
try {
    $logs = $conn->query("SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $logs = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Materials Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            --sidebar-width: 280px;
            --header-bg: #2c3e50;
            --sidebar-bg: #34495e;
            --sidebar-text: #ecf0f1;
            --content-bg: #f8f9fa;
            --card-bg: #ffffff;
            --text-color: #2c3e50;
            --border-color: #dee2e6;
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
        }
        
        /* Header Styles */
        header {
            background: var(--header-bg);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
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
            color: #f39c12;
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
        }
        
        .sidebar-content {
            padding: 2rem 1.5rem;
        }
        
        .sidebar h3 {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid rgba(255,255,255,0.1);
            font-size: 1.1rem;
            font-weight: 600;
            color: #f39c12;
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
            background: rgba(255,255,255,0.1);
            transform: translateX(8px);
        }
        
        .sidebar-menu a.active {
            background: var(--primary);
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
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }
        
        .card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border-left: 5px solid var(--primary);
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }
        
        .stat {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary);
            line-height: 1;
            margin: 1rem 0;
        }
        
        /* Form Styles */
        .form-container {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 2.5rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--dark);
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 1rem;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            background: var(--card-bg);
            color: var(--text-color);
            transition: all 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
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
        .btn-warning { background: var(--warning); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-secondary { background: #95a5a6; color: white; }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }
        
        /* Table Styles */
        .table-container {
            background: var(--card-bg);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        th {
            background: var(--sidebar-bg);
            color: var(--sidebar-text);
            font-weight: 600;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .badge-admin { background: #e74c3c; color: white; }
        .badge-gatekeeper { background: #3498db; color: white; }
        .badge-student { background: #27ae60; color: white; }
        .badge-available { background: #27ae60; color: white; }
        .badge-assigned { background: #f39c12; color: white; }
        
        .no-match {
            color: #e74c3c;
            font-style: italic;
        }
        
        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .alert-success {
            background: #d1f2eb;
            color: #27ae60;
            border: 2px solid #a3e4d7;
        }
        
        .alert-danger {
            background: #fadbd8;
            color: #e74c3c;
            border: 2px solid #f5b7b1;
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
            border-radius: 16px;
            padding: 2rem;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--danger);
        }
        
        @media (max-width: 1200px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <div class="logo">
                <i class="fas fa-cogs"></i>
                <span>Admin Dashboard</span>
            </div>
            <div class="header-actions">
                <span class="user-role">Administrator</span>
                <span><?php echo $_SESSION['full_name']; ?></span>
                <a href="logout.php" class="btn btn-danger btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </header>

    <div class="main-container">
        <div class="sidebar">
            <div class="sidebar-content">
                <h3>Admin Menu</h3>
                <ul class="sidebar-menu">
                    <li><a href="#" class="nav-link active" data-page="dashboard"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                    <li><a href="#" class="nav-link" data-page="user-management"><i class="fas fa-users"></i> <span>User Management</span></a></li>
                    <li><a href="#" class="nav-link" data-page="material-management"><i class="fas fa-boxes"></i> <span>Material Management</span></a></li>
                    <li><a href="#" class="nav-link" data-page="cadet-management"><i class="fas fa-user-graduate"></i> <span>Cadet Management</span></a></li>
                    <li><a href="#" class="nav-link" data-page="system-logs"><i class="fas fa-clipboard-list"></i> <span>System Logs</span></a></li>
                </ul>
            </div>
        </div>

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

            <!-- Dashboard Page -->
            <div class="page-content active" id="dashboard">
                <h1>Admin Dashboard</h1>
                <p>Welcome, <?php echo $_SESSION['full_name']; ?>! System overview and analytics.</p>
                
                <div class="dashboard-cards">
                    <div class="card">
                        <div class="stat"><?php echo $admin_stats['total_users']; ?></div>
                        <p>Total Users</p>
                    </div>
                    
                    <div class="card">
                        <div class="stat"><?php echo $admin_stats['gatekeepers']; ?></div>
                        <p>Gatekeepers</p>
                    </div>
                    
                    <div class="card">
                        <div class="stat"><?php echo $admin_stats['students']; ?></div>
                        <p>Students</p>
                    </div>
                    
                    <div class="card">
                        <div class="stat"><?php echo $admin_stats['total_materials']; ?></div>
                        <p>Total Materials</p>
                    </div>
                    
                    <div class="card">
                        <div class="stat"><?php echo $admin_stats['today_materials']; ?></div>
                        <p>Materials Today</p>
                    </div>
                    
                    <div class="card">
                        <div class="stat"><?php echo $admin_stats['total_cadets']; ?></div>
                        <p>Total Cadets</p>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="card">
                    <div class="card-header">
                        <h3>Recent Material Registrations</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($materials)): ?>
                            <div class="table-container">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Material</th>
                                            <th>Category</th>
                                            <th>Registered By</th>
                                            <th>Receiver</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($materials, 0, 5) as $material): ?>
                                            <tr>
                                                <td><?php echo $material['name']; ?></td>
                                                <td><?php echo $material['category'] ?? 'N/A'; ?></td>
                                                <td><?php echo $material['registered_by']; ?></td>
                                                <td>
                                                    <?php if ($material['fname']): ?>
                                                        <?php echo $material['fname'] . ' ' . $material['lname']; ?>
                                                    <?php else: ?>
                                                        <span class="no-match">No match</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('M j, Y', strtotime($material['registered_date'])); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo $material['status']; ?>">
                                                        <?php echo ucfirst($material['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p>No materials registered yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- User Management Page -->
            <div class="page-content" id="user-management">
                <h1>User Management</h1>
                <p>Manage system users and their permissions.</p>
                
                <div class="card">
                    <div class="card-header">
                        <h2>System Users (<?php echo count($users); ?> users)</h2>
                        <a href="#" class="btn btn-primary nav-link" data-page="add-user">
                            <i class="fas fa-user-plus"></i> Add User
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($users)): ?>
                            <div class="table-container">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Username</th>
                                            <th>Full Name</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Linked Cadet</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td><strong><?php echo $user['username']; ?></strong></td>
                                                <td><?php echo $user['full_name']; ?></td>
                                                <td><?php echo $user['email']; ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo $user['role']; ?>">
                                                        <?php echo ucfirst($user['role']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($user['fname'] && $user['fname'] != 'Not'): ?>
                                                        <?php echo $user['fname'] . ' ' . $user['lname']; ?>
                                                    <?php else: ?>
                                                        <span class="no-match">Not linked</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <button class="btn btn-warning btn-sm" onclick="editUser(<?php echo $user['userid']; ?>)">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </button>
                                                        <a href="?delete_user=<?php echo $user['userid']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this user?')">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p>No users found in the system.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Add User Page -->
            <div class="page-content" id="add-user">
                <h1>Add New User</h1>
                <p>Create new user accounts for the system.</p>
                
                <div class="form-container">
                    <form method="POST" action="">
                        <input type="hidden" name="add_user" value="1">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="username">Username *</label>
                                <input type="text" id="username" name="username" required placeholder="Enter username">
                            </div>
                            
                            <div class="form-group">
                                <label for="password">Password *</label>
                                <input type="password" id="password" name="password" required placeholder="Enter password">
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="full_name">Full Name *</label>
                                <input type="text" id="full_name" name="full_name" required placeholder="Enter full name">
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" name="email" placeholder="Enter email address">
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="role">User Role *</label>
                                <select id="role" name="role" required>
                                    <option value="">Select role</option>
                                    <option value="admin">Administrator</option>
                                    <option value="gatekeeper">Gatekeeper</option>
                                    <option value="student">Student</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="cadet_id">Link to Cadet (Optional)</label>
                                <select id="cadet_id" name="cadet_id">
                                    <option value="">Select cadet</option>
                                    <?php foreach ($cadets as $cadet): ?>
                                        <option value="<?php echo $cadet['cadetid']; ?>">
                                            <?php echo $cadet['fname'] . ' ' . $cadet['lname'] . ' (' . $cadet['rollno'] . ')'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-user-plus"></i> Create User
                        </button>
                        <a href="#" class="btn btn-secondary nav-link" data-page="user-management">
                            <i class="fas fa-arrow-left"></i> Back to Users
                        </a>
                    </form>
                </div>
            </div>

            <!-- Material Management Page -->
            <div class="page-content" id="material-management">
                <h1>Material Management</h1>
                <p>Manage all materials in the system.</p>
                
                <div class="card">
                    <div class="card-header">
                        <h2>All Materials (<?php echo count($materials); ?> items)</h2>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($materials)): ?>
                            <div class="table-container">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Code</th>
                                            <th>Name</th>
                                            <th>Category</th>
                                            <th>Quantity</th>
                                            <th>Receiver</th>
                                            <th>Registered By</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($materials as $material): ?>
                                            <tr>
                                                <td><strong><?php echo $material['material_code']; ?></strong></td>
                                                <td><?php echo $material['name']; ?></td>
                                                <td><?php echo $material['category'] ?? 'N/A'; ?></td>
                                                <td><?php echo $material['quantity']; ?></td>
                                                <td>
                                                    <?php if ($material['fname']): ?>
                                                        <?php echo $material['fname'] . ' ' . $material['lname']; ?>
                                                    <?php else: ?>
                                                        <span class="no-match">No match</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $material['registered_by']; ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo $material['status']; ?>">
                                                        <?php echo ucfirst($material['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <button class="btn btn-warning btn-sm" onclick="editMaterialStatus(<?php echo $material['material_id']; ?>)">
                                                            <i class="fas fa-cog"></i> Status
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p>No materials found in the system.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Cadet Management Page -->
            <div class="page-content" id="cadet-management">
                <h1>Cadet Management</h1>
                <p>Manage cadet information and records.</p>
                
                <div class="card">
                    <div class="card-header">
                        <h2>All Cadets (<?php echo count($cadets); ?> cadets)</h2>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($cadets)): ?>
                            <div class="table-container">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Roll No</th>
                                            <th>Email</th>
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
                                                <td><?php echo $cadet['email']; ?></td>
                                                <td><?php echo $cadet['company']; ?></td>
                                                <td><?php echo $cadet['platoon']; ?></td>
                                                <td><?php echo $cadet['number']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p>No cadets found in the system.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- System Logs Page -->
            <div class="page-content" id="system-logs">
                <h1>System Logs</h1>
                <p>Audit trail and system activity logs.</p>
                
                <div class="card">
                    <div class="card-header">
                        <h2>Recent Activity Logs</h2>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($logs)): ?>
                            <div class="table-container">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Timestamp</th>
                                            <th>Action</th>
                                            <th>Description</th>
                                            <th>IP Address</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($logs as $log): ?>
                                            <tr>
                                                <td><?php echo date('M j, Y H:i', strtotime($log['created_at'])); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo strpos($log['action'], 'delete') !== false ? 'danger' : 'success'; ?>">
                                                        <?php echo ucfirst($log['action']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $log['description']; ?></td>
                                                <td><code><?php echo $log['ip_address']; ?></code></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p>No activity logs found or audit log table not created yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Material Status Modal -->
    <div class="modal" id="editStatusModal">
        <div class="modal-content">
            <div class="card-header">
                <h2>Update Material Status</h2>
                <button onclick="closeModal('editStatusModal')" class="modal-close">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="update_status" value="1">
                <input type="hidden" name="material_id" id="statusMaterialId">
                
                <div class="form-group">
                    <label for="status">Status *</label>
                    <select id="status" name="status" required>
                        <option value="available">Available</option>
                        <option value="assigned">Assigned</option>
                        <option value="checked_out">Checked Out</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Update Status
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('editStatusModal')">
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
        });
        
        function editMaterialStatus(materialId) {
            document.getElementById('statusMaterialId').value = materialId;
            document.getElementById('editStatusModal').style.display = 'flex';
        }
        
        function editUser(userId) {
            alert('Edit user functionality for user ID: ' + userId + '\n\nThis would open a user edit form in a real implementation.');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
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