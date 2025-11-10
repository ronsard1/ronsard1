<?php
require_once "config/database.php";
require_once "auth/check_role.php";

checkRole('gatekeeper');

$database = new Database();
$conn = $database->getConnection();

$success = $error = "";

// Handle material registration
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Register new material
    if (isset($_POST['register_material'])) {
        try {
            // Generate material code
            $material_count = $conn->query("SELECT COUNT(*) as count FROM materials")->fetch()['count'];
            $material_code = 'MAT-' . str_pad($material_count + 1, 6, '0', STR_PAD_LEFT);
            
            // Get form data
            $name = $_POST['name'];
            $description = $_POST['description'];
            $size = $_POST['size'];
            $quantity = $_POST['quantity'];
            $barcode = $_POST['barcode'];
            $supplier_name = $_POST['supplier_name'];
            $supplier_contact = $_POST['supplier_contact'];
            $supplier_email = $_POST['supplier_email'];
            $telephone = $_POST['telephone'];
            $category = $_POST['category'];
            $notes = $_POST['notes'];
            
            $query = "INSERT INTO materials (material_code, name, description, size, quantity, barcode, supplier_name, supplier_contact, supplier_email, telephone, category, notes, registered_by) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            
            if ($stmt->execute([$material_code, $name, $description, $size, $quantity, $barcode, $supplier_name, $supplier_contact, $supplier_email, $telephone, $category, $notes, $_SESSION['full_name']])) {
                $material_id = $conn->lastInsertId();
                
                // Check if telephone matches any cadet
                $cadet_match = $conn->query("SELECT cadetid, fname, lname FROM cadet WHERE number = '$telephone'")->fetch();
                if ($cadet_match) {
                    $log_notes = "Material automatically assigned to cadet (telephone match)";
                    $query = "INSERT INTO material_assignments (material_id, cadet_id, assigned_by, notes) VALUES (?, ?, ?, ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$material_id, $cadet_match['cadetid'], $_SESSION['full_name'], $log_notes]);
                }
                
                logAction($conn, $_SESSION['user_id'], 'register_material', "Registered new material: $name ($material_code)");
                $success = "Material registered successfully! Code: $material_code";
            }
        } catch (PDOException $e) {
            $error = "Error registering material: " . $e->getMessage();
        }
    }
    
    // Update material
    if (isset($_POST['update_material'])) {
        try {
            $material_id = $_POST['material_id'];
            $name = $_POST['name'];
            $description = $_POST['description'];
            $size = $_POST['size'];
            $quantity = $_POST['quantity'];
            $barcode = $_POST['barcode'];
            $supplier_name = $_POST['supplier_name'];
            $supplier_contact = $_POST['supplier_contact'];
            $supplier_email = $_POST['supplier_email'];
            $telephone = $_POST['telephone'];
            $category = $_POST['category'];
            $notes = $_POST['notes'];
            
            $query = "UPDATE materials SET name=?, description=?, size=?, quantity=?, barcode=?, supplier_name=?, supplier_contact=?, supplier_email=?, telephone=?, category=?, notes=? WHERE material_id=?";
            $stmt = $conn->prepare($query);
            
            if ($stmt->execute([$name, $description, $size, $quantity, $barcode, $supplier_name, $supplier_contact, $supplier_email, $telephone, $category, $notes, $material_id])) {
                logAction($conn, $_SESSION['user_id'], 'update_material', "Updated material: $name (ID: $material_id)");
                $success = "Material updated successfully!";
            }
        } catch (PDOException $e) {
            $error = "Error updating material: " . $e->getMessage();
        }
    }
}

// Get materials data with cadet names
$materials = $conn->query("
    SELECT m.*, c.fname, c.lname, c.rollno 
    FROM materials m 
    LEFT JOIN cadet c ON m.telephone = c.number 
    ORDER BY m.registered_date DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Get statistics for charts
$today_materials = $conn->query("
    SELECT category, COUNT(*) as count 
    FROM materials 
    WHERE DATE(registered_date) = CURDATE() 
    GROUP BY category
")->fetchAll(PDO::FETCH_ASSOC);

$weekly_stats = $conn->query("
    SELECT 
        DATE(registered_date) as date,
        COUNT(*) as count
    FROM materials 
    WHERE registered_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(registered_date)
    ORDER BY date
")->fetchAll(PDO::FETCH_ASSOC);

$category_stats = $conn->query("
    SELECT category, COUNT(*) as count 
    FROM materials 
    GROUP BY category 
    ORDER BY count DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Get gatekeeper statistics
$gatekeeper_stats = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM materials) as total_materials,
        (SELECT COUNT(*) FROM materials WHERE registered_by = '{$_SESSION['full_name']}') as my_materials,
        (SELECT COUNT(*) FROM materials WHERE DATE(registered_date) = CURDATE()) as today_materials,
        (SELECT COUNT(*) FROM materials WHERE status = 'available') as available_materials
")->fetch(PDO::FETCH_ASSOC);

// Material categories
$material_categories = ['Electronics', 'Laboratory Equipment', 'Tools', 'Furniture', 'Sports Equipment', 'Books', 'Uniforms', 'Stationery', 'Medical Supplies', 'Other'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gatekeeper Dashboard - Materials Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/gatekeeper.css">
</head>
<body>
    <!-- Window Controls -->
    <div class="window-controls">
        <button class="control-btn minimize" title="Minimize">
            <i class="fas fa-window-minimize"></i>
        </button>
        <button class="control-btn maximize" title="Maximize">
            <i class="fas fa-window-maximize"></i>
        </button>
        <button class="control-btn close" title="Close">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- Theme Selector -->
    <div class="theme-selector" id="themeSelector">
        <div class="theme-option theme-default active" data-theme="default" title="Default Theme"></div>
        <div class="theme-option theme-dark" data-theme="dark" title="Dark Mode"></div>
        <div class="theme-option theme-blue" data-theme="blue" title="Blue Theme"></div>
        <div class="theme-option theme-green" data-theme="green" title="Green Theme"></div>
        <div class="theme-option theme-purple" data-theme="purple" title="Purple Theme"></div>
    </div>

    <header>
        <div class="header-content">
            <div class="logo">
                <i class="fas fa-shield-alt"></i>
                <span>Gatekeeper Dashboard</span>
            </div>
            <div class="header-actions">
                <span class="user-role">Gatekeeper</span>
                <span><?php echo $_SESSION['full_name']; ?></span>
                <button class="theme-toggle" id="themeToggle" title="Change Theme">
                    <i class="fas fa-palette"></i>
                </button>
                <a href="logout.php" class="btn btn-danger btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </header>

    <div class="main-container">
        <div class="sidebar">
            <div class="sidebar-content">
                <h3>Material Management</h3>
                <ul class="sidebar-menu">
                    <li><a href="#" class="nav-link active" data-page="dashboard"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                    <li><a href="#" class="nav-link" data-page="register-material"><i class="fas fa-plus-circle"></i> <span>Register Material</span></a></li>
                    <li><a href="#" class="nav-link" data-page="materials-list"><i class="fas fa-boxes"></i> <span>View Materials</span></a></li>
                    <li><a href="#" class="nav-link" data-page="recent-materials"><i class="fas fa-history"></i> <span>Recently Added</span></a></li>
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
                <h1>Gatekeeper Dashboard</h1>
                <p>Welcome, <?php echo $_SESSION['full_name']; ?>! Manage material registration and inventory.</p>
                
                <div class="dashboard-cards">
                    <div class="card">
                        <div class="stat"><?php echo $gatekeeper_stats['total_materials']; ?></div>
                        <p>Total Materials in System</p>
                    </div>
                    
                    <div class="card">
                        <div class="stat"><?php echo $gatekeeper_stats['my_materials']; ?></div>
                        <p>Materials Registered by You</p>
                    </div>
                    
                    <div class="card">
                        <div class="stat"><?php echo $gatekeeper_stats['today_materials']; ?></div>
                        <p>Materials Registered Today</p>
                    </div>
                    
                    <div class="card">
                        <div class="stat"><?php echo $gatekeeper_stats['available_materials']; ?></div>
                        <p>Available Materials</p>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="charts-grid">
                    <div class="card">
                        <div class="card-header">
                            <h3>Today's Materials by Category</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="todayChart" width="400" height="200"></canvas>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h3>Materials This Week</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="weeklyChart" width="400" height="200"></canvas>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h3>Materials by Category</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="categoryChart" width="400" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Register Material Page -->
            <div class="page-content" id="register-material">
                <h1>Register New Material</h1>
                <p>Add new materials to the institutional inventory system.</p>
                
                <div class="form-container">
                    <form method="POST" action="" id="materialForm">
                        <input type="hidden" name="register_material" value="1">
                        
                        <!-- Barcode Scanner Section -->
                        <div class="barcode-scanner">
                            <h3><i class="fas fa-barcode"></i> Barcode Scanner (Optional)</h3>
                            <div class="scanner-container">
                                <video id="barcode-video" width="400" height="300" style="display: none;"></video>
                                <div class="scanner-overlay" style="display: none;"></div>
                                <div id="scanner-status" class="scanner-status">Scanner ready</div>
                            </div>
                            <div class="scanner-controls">
                                <button type="button" class="btn btn-primary" id="startScanner">
                                    <i class="fas fa-camera"></i> Start Scanner
                                </button>
                                <button type="button" class="btn btn-secondary" id="stopScanner" style="display: none;">
                                    <i class="fas fa-stop"></i> Stop Scanner
                                </button>
                                <button type="button" class="btn btn-success" id="manualEntry">
                                    <i class="fas fa-keyboard"></i> Manual Entry
                                </button>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="name">Material Name *</label>
                                <input type="text" id="name" name="name" required placeholder="Enter material name">
                            </div>
                            
                            <div class="form-group">
                                <label for="category">Category *</label>
                                <select id="category" name="category" required>
                                    <option value="">Select category</option>
                                    <?php foreach ($material_categories as $category): ?>
                                        <option value="<?php echo $category; ?>"><?php echo $category; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="3" placeholder="Detailed description of the material"></textarea>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="size">Size/Dimensions</label>
                                <input type="text" id="size" name="size" placeholder="e.g., 10x20x30 cm">
                            </div>
                            
                            <div class="form-group">
                                <label for="quantity">Quantity *</label>
                                <input type="number" id="quantity" name="quantity" min="1" value="1" required>
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="barcode">Barcode/Serial Number (Optional)</label>
                                <input type="text" id="barcode" name="barcode" placeholder="Scan or enter barcode">
                                <small>Leave empty if product doesn't have barcode</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="telephone">Receiver Telephone *</label>
                                <input type="text" id="telephone" name="telephone" required placeholder="+255XXXXXXXXX">
                                <small>Will automatically match with cadet records</small>
                                <div id="cadet-match" class="cadet-match"></div>
                            </div>
                        </div>
                        
                        <h3>Supplier Information</h3>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="supplier_name">Supplier Name *</label>
                                <input type="text" id="supplier_name" name="supplier_name" required placeholder="Company or individual name">
                            </div>
                            
                            <div class="form-group">
                                <label for="supplier_contact">Supplier Contact *</label>
                                <input type="text" id="supplier_contact" name="supplier_contact" required placeholder="Phone number">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="supplier_email">Supplier Email</label>
                            <input type="email" id="supplier_email" name="supplier_email" placeholder="email@supplier.com">
                        </div>
                        
                        <div class="form-group">
                            <label for="notes">Additional Notes</label>
                            <textarea id="notes" name="notes" rows="3" placeholder="Any special instructions or notes"></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Register Material
                        </button>
                        <button type="reset" class="btn btn-secondary">
                            <i class="fas fa-undo"></i> Clear Form
                        </button>
                    </form>
                </div>
            </div>

            <!-- Materials List Page -->
            <div class="page-content" id="materials-list">
                <h1>All Materials</h1>
                <p>Complete list of all materials in the system with cadet assignments.</p>
                
                <div class="card">
                    <div class="card-header">
                        <h2>Materials Inventory (<?php echo count($materials); ?> items)</h2>
                        <a href="#" class="btn btn-primary nav-link" data-page="register-material">
                            <i class="fas fa-plus"></i> Add New Material
                        </a>
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
                                            <th>Barcode</th>
                                            <th>Receiver Cadet</th>
                                            <th>Telephone</th>
                                            <th>Date Added</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($materials as $material): ?>
                                            <tr>
                                                <td><strong><?php echo $material['material_code']; ?></strong></td>
                                                <td><?php echo $material['name']; ?></td>
                                                <td><?php echo $material['category']; ?></td>
                                                <td><?php echo $material['quantity']; ?></td>
                                                <td><code><?php echo $material['barcode'] ?: 'N/A'; ?></code></td>
                                                <td>
                                                    <?php if ($material['fname']): ?>
                                                        <strong><?php echo $material['fname'] . ' ' . $material['lname']; ?></strong>
                                                        <br><small>Roll: <?php echo $material['rollno']; ?></small>
                                                    <?php else: ?>
                                                        <span class="no-match">No cadet match</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $material['telephone']; ?></td>
                                                <td><?php echo date('M j, Y', strtotime($material['registered_date'])); ?></td>
                                                <td>
                                                    <button class="btn btn-warning btn-sm" onclick="editMaterial(<?php echo $material['material_id']; ?>)">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p>No materials found. <a href="#" class="nav-link" data-page="register-material">Register your first material</a>.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recently Added Materials Page -->
            <div class="page-content" id="recent-materials">
                <h1>Recently Added Materials</h1>
                <p>Materials you've recently registered in the system.</p>
                
                <?php 
                $recent_materials = array_slice($materials, 0, 6);
                ?>
                
                <?php if (!empty($recent_materials)): ?>
                    <div class="material-grid">
                        <?php foreach ($recent_materials as $material): ?>
                            <div class="material-card">
                                <h3><?php echo $material['name']; ?></h3>
                                <p><strong>Code:</strong> <?php echo $material['material_code']; ?></p>
                                <p><strong>Category:</strong> <?php echo $material['category']; ?></p>
                                <p><strong>Quantity:</strong> <?php echo $material['quantity']; ?></p>
                                <p><strong>Barcode:</strong> <?php echo $material['barcode'] ?: 'N/A'; ?></p>
                                <p><strong>Receiver:</strong> 
                                    <?php if ($material['fname']): ?>
                                        <?php echo $material['fname'] . ' ' . $material['lname']; ?> (<?php echo $material['rollno']; ?>)
                                    <?php else: ?>
                                        <span class="no-match">No cadet match for <?php echo $material['telephone']; ?></span>
                                    <?php endif; ?>
                                </p>
                                <p><strong>Supplier:</strong> <?php echo $material['supplier_name']; ?></p>
                                <p><strong>Added:</strong> <?php echo date('M j, Y g:i A', strtotime($material['registered_date'])); ?></p>
                                
                                <div class="material-actions">
                                    <button class="btn btn-warning btn-sm" onclick="editMaterial(<?php echo $material['material_id']; ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body">
                            <p>No materials registered yet. <a href="#" class="nav-link" data-page="register-material">Register your first material</a>.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Edit Material Modal -->
    <div class="modal" id="editMaterialModal">
        <div class="modal-content">
            <div class="card-header">
                <h2>Edit Material</h2>
                <button onclick="closeModal('editMaterialModal')" class="modal-close">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="update_material" value="1">
                <input type="hidden" name="material_id" id="editMaterialId">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="editName">Material Name *</label>
                        <input type="text" id="editName" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="editCategory">Category *</label>
                        <select id="editCategory" name="category" required>
                            <option value="">Select category</option>
                            <?php foreach ($material_categories as $category): ?>
                                <option value="<?php echo $category; ?>"><?php echo $category; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="editDescription">Description</label>
                    <textarea id="editDescription" name="description" rows="3"></textarea>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="editSize">Size/Dimensions</label>
                        <input type="text" id="editSize" name="size">
                    </div>
                    
                    <div class="form-group">
                        <label for="editQuantity">Quantity *</label>
                        <input type="number" id="editQuantity" name="quantity" min="1" required>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="editBarcode">Barcode/Serial Number (Optional)</label>
                        <input type="text" id="editBarcode" name="barcode">
                    </div>
                    
                    <div class="form-group">
                        <label for="editTelephone">Receiver Telephone *</label>
                        <input type="text" id="editTelephone" name="telephone" required>
                    </div>
                </div>
                
                <h3>Supplier Information</h3>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="editSupplierName">Supplier Name *</label>
                        <input type="text" id="editSupplierName" name="supplier_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="editSupplierContact">Supplier Contact *</label>
                        <input type="text" id="editSupplierContact" name="supplier_contact" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="editSupplierEmail">Supplier Email</label>
                    <input type="email" id="editSupplierEmail" name="supplier_email">
                </div>
                
                <div class="form-group">
                    <label for="editNotes">Additional Notes</label>
                    <textarea id="editNotes" name="notes" rows="3"></textarea>
                </div>
                
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Update Material
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('editMaterialModal')">
                    Cancel
                </button>
            </form>
        </div>
    </div>

    <!-- Include JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/quagga/dist/quagga.min.js"></script>
    
    <!-- Include JavaScript Files -->
    <script src="js/theme.js"></script>
    <script src="js/scanner.js"></script>
    <script src="js/charts.js"></script>
    <script src="js/gatekeeper.js"></script>
</body>
</html>