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

// Get current cadet data
$cadet_id = $_SESSION['user_id'];
$cadet = $conn->query("SELECT * FROM cadet WHERE cadetid = $cadet_id")->fetch(PDO::FETCH_ASSOC);

// Handle contact message
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_message'])) {
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

// Get cadet's assigned materials (materials where telephone matches cadet's number)
try {
    $assigned_materials = $conn->query("
        SELECT m.*, c.fname, c.lname 
        FROM materials m 
        LEFT JOIN cadet c ON m.telephone = c.number 
        WHERE m.telephone = '{$cadet['number']}' 
        ORDER BY m.registered_date DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $assigned_materials = [];
}

// Get cadet's checked out materials (only materials checked out by this cadet)
try {
    $checked_out_materials = $conn->query("
        SELECT m.*, c.fname, c.lname 
        FROM materials m 
        LEFT JOIN cadet c ON m.telephone = c.number 
        WHERE m.status = 'checked_out' AND m.telephone = '{$cadet['number']}'
        ORDER BY m.registered_date DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $checked_out_materials = [];
}

// Get available materials (all available materials in the system)
try {
    $available_materials = $conn->query("
        SELECT m.*, c.fname, c.lname 
        FROM materials m 
        LEFT JOIN cadet c ON m.telephone = c.number 
        WHERE m.status = 'available' 
        AND m.telephone = '{$cadet['number']}'
        ORDER BY m.registered_date DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $available_materials = [];
}

// Get statistics
$total_assigned = count($assigned_materials);
$total_checked_out = count($checked_out_materials);
$total_available = count($available_materials);
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
        
        /* Table Styles */
        .table-container {
            background: var(--card-bg);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
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
                <span><?php echo $cadet['fname'] . ' ' . $cadet['lname']; ?></span>
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
                    <li><a href="#" class="nav-link" data-page="contact"><i class="fas fa-envelope"></i> <span>Contact Admin</span></a></li>
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
                <h1>Cadet Dashboard</h1>
                <p>Welcome back, <?php echo $cadet['fname'] . ' ' . $cadet['lname']; ?>! (Roll No: <?php echo $cadet['rollno']; ?>)</p>
                
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
                                            <span class="material-id"><?php echo $material['material_code']; ?></span>
                                            <span class="badge badge-assigned">My Material</span>
                                        </div>
                                        <div class="material-body">
                                            <h3><?php echo $material['name']; ?></h3>
                                            <div class="material-details">
                                                <p><strong>Description:</strong> <?php echo $material['description'] ?: 'No description'; ?></p>
                                                <p><strong>Size:</strong> <?php echo $material['size'] ?? 'N/A'; ?></p>
                                                <p><strong>Quantity:</strong> <?php echo $material['quantity']; ?></p>
                                                <p><strong>Barcode:</strong> <?php echo $material['barcode'] ?? 'N/A'; ?></p>
                                                <p><strong>Status:</strong> <?php echo ucfirst($material['status']); ?></p>
                                            </div>
                                            <?php if ($material['fname']): ?>
                                                <div class="cadet-info">
                                                    <strong>Assigned Cadet:</strong> <?php echo $material['fname'] . ' ' . $material['lname']; ?>
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
                                            <span class="material-id"><?php echo $material['material_code']; ?></span>
                                            <span class="badge badge-assigned">Assigned to Me</span>
                                        </div>
                                        <div class="material-body">
                                            <h3><?php echo $material['name']; ?></h3>
                                            <div class="material-details">
                                                <p><strong>Description:</strong> <?php echo $material['description'] ?: 'No description'; ?></p>
                                                <p><strong>Size:</strong> <?php echo $material['size'] ?? 'N/A'; ?></p>
                                                <p><strong>Quantity:</strong> <?php echo $material['quantity']; ?></p>
                                                <p><strong>Barcode:</strong> <?php echo $material['barcode'] ?? 'N/A'; ?></p>
                                                <p><strong>Status:</strong> <?php echo ucfirst($material['status']); ?></p>
                                            </div>
                                            <?php if ($material['fname']): ?>
                                                <div class="cadet-info">
                                                    <strong>Assigned Cadet:</strong> <?php echo $material['fname'] . ' ' . $material['lname']; ?>
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
                                            <span class="material-id"><?php echo $material['material_code']; ?></span>
                                            <span class="badge badge-checked-out">Checked Out</span>
                                        </div>
                                        <div class="material-body">
                                            <h3><?php echo $material['name']; ?></h3>
                                            <div class="material-details">
                                                <p><strong>Description:</strong> <?php echo $material['description'] ?: 'No description'; ?></p>
                                                <p><strong>Size:</strong> <?php echo $material['size'] ?? 'N/A'; ?></p>
                                                <p><strong>Quantity:</strong> <?php echo $material['quantity']; ?></p>
                                                <p><strong>Barcode:</strong> <?php echo $material['barcode'] ?? 'N/A'; ?></p>
                                            </div>
                                            <?php if ($material['fname']): ?>
                                                <div class="cadet-info">
                                                    <strong>Checked out by:</strong> <?php echo $material['fname'] . ' ' . $material['lname']; ?>
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
                                            <span class="material-id"><?php echo $material['material_code']; ?></span>
                                            <span class="badge badge-available">Available</span>
                                        </div>
                                        <div class="material-body">
                                            <h3><?php echo $material['name']; ?></h3>
                                            <div class="material-details">
                                                <p><strong>Description:</strong> <?php echo $material['description'] ?: 'No description available'; ?></p>
                                                <p><strong>Size:</strong> <?php echo $material['size'] ?? 'N/A'; ?></p>
                                                <p><strong>Quantity:</strong> <?php echo $material['quantity']; ?></p>
                                                <p><strong>Barcode:</strong> <?php echo $material['barcode'] ?? 'N/A'; ?></p>
                                            </div>
                                            <?php if ($material['fname']): ?>
                                                <div class="cadet-info">
                                                    <strong>Last assigned to:</strong> <?php echo $material['fname'] . ' ' . $material['lname']; ?>
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
            // Navigation functionality
            const navLinks = document.querySelectorAll('.nav-link');
            const pageContents = document.querySelectorAll('.page-content');
            
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const pageId = this.getAttribute('data-page');
                    
                    // Update active navigation link
                    navLinks.forEach(nav => nav.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Show the corresponding page content
                    pageContents.forEach(page => page.classList.remove('active'));
                    document.getElementById(pageId).classList.add('active');
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
    </script>
</body>
</html>