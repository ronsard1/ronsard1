<?php
require_once "config/database.php";
require_once "helpers/auth.php";

checkRole('admin');

$database = new Database();
$conn = $database->getConnection();

// Get statistics
$totalMaterials = $conn->query("SELECT COUNT(*) as count FROM materials")->fetch()['count'];
$availableMaterials = $conn->query("SELECT COUNT(*) as count FROM materials WHERE status = 'available'")->fetch()['count'];
$assignedMaterials = $conn->query("SELECT COUNT(*) as count FROM material_assignments WHERE status = 'active'")->fetch()['count'];
$pendingCheckouts = $conn->query("SELECT COUNT(*) as count FROM material_checkouts WHERE status = 'pending'")->fetch()['count'];

// Get recent activities
$recentActivities = $conn->query("
    SELECT 'assignment' as type, ma.assigned_date as date, m.name as material_name, c.full_name as cadet_name 
    FROM material_assignments ma 
    JOIN materials m ON ma.material_id = m.material_id 
    JOIN cadets c ON ma.cadet_id = c.cadetid 
    ORDER BY ma.assigned_date DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Handle material registration
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register_material'])) {
    $material_code = 'MAT-' . str_pad($totalMaterials + 1, 3, '0', STR_PAD_LEFT);
    $name = $_POST['name'];
    $size = $_POST['size'];
    $description = $_POST['description'];
    $quantity = $_POST['quantity'];
    $barcode = $_POST['barcode'];
    $location = $_POST['location'];
    $registered_by = $_POST['registered_by'];
    $registered_contact = $_POST['registered_contact'];
    $notes = $_POST['notes'];
    
    $query = "INSERT INTO materials (material_code, name, description, size, quantity, barcode, location, registered_by, registered_contact, notes) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    
    if ($stmt->execute([$material_code, $name, $description, $size, $quantity, $barcode, $location, $registered_by, $registered_contact, $notes])) {
        $success = "Material registered successfully!";
        header("Location: admin-dashboard.php?success=Material registered successfully");
        exit();
    } else {
        $error = "Error registering material!";
    }
}

// Handle material assignment
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['assign_material'])) {
    $material_id = $_POST['material_id'];
    $cadet_id = $_POST['cadet_id'];
    $expected_return_date = $_POST['expected_return_date'];
    $assigned_by = $_SESSION['full_name'];
    $notes = $_POST['assignment_notes'];
    
    $query = "INSERT INTO material_assignments (material_id, cadet_id, expected_return_date, assigned_by, notes) 
              VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    
    if ($stmt->execute([$material_id, $cadet_id, $expected_return_date, $assigned_by, $notes])) {
        // Update material status
        $conn->query("UPDATE materials SET status = 'assigned' WHERE material_id = $material_id");
        $success = "Material assigned successfully!";
        header("Location: admin-dashboard.php?success=Material assigned successfully");
        exit();
    } else {
        $error = "Error assigning material!";
    }
}

// Get all materials
$materials = $conn->query("SELECT * FROM materials ORDER BY registered_date DESC")->fetchAll(PDO::FETCH_ASSOC);

// Get all cadets
$cadets = $conn->query("SELECT * FROM cadets WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
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
            --primary: #3498db;
            --secondary: #2c3e50;
            --success: #2ecc71;
            --warning: #f39c12;
            --danger: #e74c3c;
            --light: #ecf0f1;
            --dark: #34495e;
            --sidebar-width: 250px;
            --header-bg: var(--secondary);
            --sidebar-bg: #ffffff;
            --sidebar-text: #333333;
            --content-bg: #f5f7fa;
            --card-bg: #ffffff;
            --text-color: #333333;
            --border-color: #dddddd;
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
            transition: all 0.3s ease;
        }
        
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        /* Header Styles */
        header {
            background-color: var(--header-bg);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            display: flex;
            align-items: center;
        }
        
        .logo i {
            margin-right: 10px;
            font-size: 1.8rem;
        }
        
        .user-info {
            display: flex;
            align-items: center;
        }
        
        .user-role {
            background-color: var(--primary);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            margin-right: 1rem;
            font-size: 0.8rem;
        }
        
        nav ul {
            display: flex;
            list-style: none;
        }
        
        nav ul li {
            margin-left: 1.5rem;
        }
        
        nav ul li a {
            color: white;
            text-decoration: none;
            transition: color 0.3s;
            display: flex;
            align-items: center;
        }
        
        nav ul li a i {
            margin-right: 5px;
        }
        
        nav ul li a:hover {
            color: var(--primary);
        }
        
        /* Main Content Area */
        .main-content {
            display: flex;
            margin-top: 20px;
            gap: 20px;
        }
        
        /* Sidebar */
        .sidebar {
            width: 250px;
            background: var(--sidebar-bg);
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            height: fit-content;
        }
        
        .sidebar h3 {
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border-color);
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
            padding: 0.5rem;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background-color: rgba(0,0,0,0.1);
        }
        
        .sidebar-menu a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        /* Content Area */
        .content {
            flex: 1;
        }
        
        /* Dashboard Cards */
        .dashboard {
            padding: 1rem 0;
        }
        
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .card {
            background: var(--card-bg);
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .card-header {
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .stat {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary);
        }
        
        /* Table Styles */
        .table-container {
            background: var(--card-bg);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
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
            background-color: rgba(0,0,0,0.05);
            font-weight: 600;
        }
        
        tr:hover {
            background-color: rgba(0,0,0,0.03);
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 1rem;
            background-color: var(--card-bg);
            color: var(--text-color);
        }
        
        button {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            transition: background-color 0.3s;
            display: inline-flex;
            align-items: center;
        }
        
        button i {
            margin-right: 8px;
        }
        
        button:hover {
            background-color: #2980b9;
        }
        
        .btn-success {
            background-color: var(--success);
        }
        
        .btn-success:hover {
            background-color: #27ae60;
        }
        
        /* Tabs */
        .tabs {
            display: flex;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .tab {
            padding: 0.75rem 1.5rem;
            cursor: pointer;
            border-bottom: 3px solid transparent;
        }
        
        .tab.active {
            border-bottom-color: var(--primary);
            color: var(--primary);
            font-weight: 500;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Status badges */
        .badge {
            padding: 0.35rem 0.65rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-block;
        }
        
        .badge-success {
            background-color: #d1f2eb;
            color: #27ae60;
        }
        
        .badge-warning {
            background-color: #fdebd0;
            color: #e67e22;
        }
        
        .badge-danger {
            background-color: #fadbd8;
            color: #e74c3c;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background-color: #d1f2eb;
            color: #27ae60;
            border: 1px solid #a3e4d7;
        }
        
        .alert-danger {
            background-color: #fadbd8;
            color: #e74c3c;
            border: 1px solid #f5b7b1;
        }
    </style>
</head>
<body>
    <header>
        <div class="container header-content">
            <div class="logo">
                <i class="fas fa-boxes"></i> Materials Management System
            </div>
            <div class="user-info">
                <span class="user-role">Admin</span>
                <span><?php echo $_SESSION['full_name']; ?></span>
            </div>
            <nav>
                <ul>
                    <li><a href="admin-dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container main-content">
        <div class="sidebar">
            <h3>Admin Menu</h3>
            <ul class="sidebar-menu">
                <li><a href="#" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="#register-material"><i class="fas fa-plus-circle"></i> Register Material</a></li>
                <li><a href="#assign-material"><i class="fas fa-user-check"></i> Assign Material</a></li>
                <li><a href="#materials-list"><i class="fas fa-box"></i> View Materials</a></li>
                <li><a href="#cadets-list"><i class="fas fa-users"></i> View Cadets</a></li>
            </ul>
        </div>

        <div class="content">
            <div class="dashboard">
                <h1>Admin Dashboard</h1>
                
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $_GET['success']; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <div class="dashboard-cards">
                    <div class="card">
                        <div class="card-header">
                            <h3>Total Materials</h3>
                            <i class="fas fa-boxes" style="color: var(--primary);"></i>
                        </div>
                        <div class="card-body">
                            <div class="stat"><?php echo $totalMaterials; ?></div>
                            <p>Registered in system</p>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h3>Available Materials</h3>
                            <i class="fas fa-warehouse" style="color: var(--success);"></i>
                        </div>
                        <div class="card-body">
                            <div class="stat"><?php echo $availableMaterials; ?></div>
                            <p>Ready for assignment</p>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h3>Assigned Materials</h3>
                            <i class="fas fa-hand-holding" style="color: var(--warning);"></i>
                        </div>
                        <div class="card-body">
                            <div class="stat"><?php echo $assignedMaterials; ?></div>
                            <p>Currently with cadets</p>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h3>Pending Checkouts</h3>
                            <i class="fas fa-truck-loading" style="color: var(--danger);"></i>
                        </div>
                        <div class="card-body">
                            <div class="stat"><?php echo $pendingCheckouts; ?></div>
                            <p>Awaiting approval</p>
                        </div>
                    </div>
                </div>
                
                <!-- Register Material Form -->
                <div class="card" id="register-material">
                    <div class="card-header">
                        <h2>Register New Material</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="register_material" value="1">
                            <div class="form-group">
                                <label for="name">Material Name</label>
                                <input type="text" id="name" name="name" required>
                            </div>
                            <div class="form-group">
                                <label for="size">Size/Dimensions</label>
                                <input type="text" id="size" name="size" placeholder="e.g., 15.6 inches or 40x40x60 cm">
                            </div>
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" rows="3"></textarea>
                            </div>
                            <div class="form-group">
                                <label for="quantity">Quantity</label>
                                <input type="number" id="quantity" name="quantity" min="1" value="1" required>
                            </div>
                            <div class="form-group">
                                <label for="barcode">Barcode/Serial Number</label>
                                <input type="text" id="barcode" name="barcode">
                            </div>
                            <div class="form-group">
                                <label for="location">Storage Location</label>
                                <input type="text" id="location" name="location" required>
                            </div>
                            <div class="form-group">
                                <label for="registered_by">Registered By</label>
                                <input type="text" id="registered_by" name="registered_by" required>
                            </div>
                            <div class="form-group">
                                <label for="registered_contact">Contact Information</label>
                                <input type="text" id="registered_contact" name="registered_contact" required>
                            </div>
                            <div class="form-group">
                                <label for="notes">Additional Notes</label>
                                <textarea id="notes" name="notes" rows="3"></textarea>
                            </div>
                            <button type="submit" class="btn-success">
                                <i class="fas fa-check-circle"></i> Register Material
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Assign Material Form -->
                <div class="card" id="assign-material">
                    <div class="card-header">
                        <h2>Assign Material to Cadet</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="assign_material" value="1">
                            <div class="form-group">
                                <label for="material_id">Select Material</label>
                                <select id="material_id" name="material_id" required>
                                    <option value="">Select a material</option>
                                    <?php foreach ($materials as $material): ?>
                                        <?php if ($material['status'] == 'available'): ?>
                                            <option value="<?php echo $material['material_id']; ?>">
                                                <?php echo $material['material_code'] . ' - ' . $material['name']; ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="cadet_id">Select Cadet</label>
                                <select id="cadet_id" name="cadet_id" required>
                                    <option value="">Select a cadet</option>
                                    <?php foreach ($cadets as $cadet): ?>
                                        <option value="<?php echo $cadet['cadetid']; ?>">
                                            <?php echo $cadet['full_name'] . ' (' . $cadet['intake'] . ')'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="expected_return_date">Expected Return Date</label>
                                <input type="date" id="expected_return_date" name="expected_return_date" required>
                            </div>
                            <div class="form-group">
                                <label for="assignment_notes">Assignment Notes</label>
                                <textarea id="assignment_notes" name="assignment_notes" rows="3"></textarea>
                            </div>
                            <button type="submit" class="btn-success">
                                <i class="fas fa-user-check"></i> Assign Material
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Materials List -->
                <div class="card" id="materials-list">
                    <div class="card-header">
                        <h2>All Materials</h2>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Material Code</th>
                                        <th>Name</th>
                                        <th>Size</th>
                                        <th>Quantity</th>
                                        <th>Location</th>
                                        <th>Status</th>
                                        <th>Registered Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($materials as $material): ?>
                                        <tr>
                                            <td><?php echo $material['material_code']; ?></td>
                                            <td><?php echo $material['name']; ?></td>
                                            <td><?php echo $material['size']; ?></td>
                                            <td><?php echo $material['quantity']; ?></td>
                                            <td><?php echo $material['location']; ?></td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo $material['status'] == 'available' ? 'success' : 
                                                         ($material['status'] == 'assigned' ? 'warning' : 'danger'); 
                                                ?>">
                                                    <?php echo ucfirst($material['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($material['registered_date'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set default date for expected return (7 days from now)
            const today = new Date();
            const nextWeek = new Date(today);
            nextWeek.setDate(today.getDate() + 7);
            document.getElementById('expected_return_date').valueAsDate = nextWeek;
            
            // Smooth scrolling for sidebar links
            document.querySelectorAll('.sidebar-menu a').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const targetId = this.getAttribute('href').substring(1);
                    const targetElement = document.getElementById(targetId);
                    
                    if (targetElement) {
                        targetElement.scrollIntoView({ behavior: 'smooth' });
                    }
                });
            });
        });
    </script>
</body>
</html>