<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

// Remove or comment out the HTTPS enforcement for local development
// Force HTTPS for camera access
/*
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    $redirect_url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header('Location: ' . $redirect_url);
    exit();
}
*/

require_once "config/database.php";
// ... rest of your code

require_once "config/database.php";
require_once "auth/check_role.php";
require_once "notification_functions.php";

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
            
            $query = "INSERT INTO materials (material_code, name, description, size, quantity, barcode, supplier_name, supplier_contact, supplier_email, telephone, category, notes, registered_by, status) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'available')";
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
                    
                    createNotification($conn, $cadet_match['cadetid'], "New Material Assigned", 
                        "Material '{$name}' has been assigned to you", 'new_material');
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
    
    // Handle send outside
    if (isset($_POST['send_outside'])) {
        try {
            $material_id = $_POST['material_id'];
            $sent_to_person = $_POST['sent_to_person'];
            $sent_to_contact = $_POST['sent_to_contact'];
            $reason = $_POST['reason'];
            $external_notes = $_POST['external_notes'];
            
            // Update material to mark as outside institution
            $query = "UPDATE materials SET status = 'outside_institution', sent_to_person = ?, sent_to_contact = ?, reason = ?, external_notes = ?, sent_date = CURDATE() WHERE material_id = ?";
            $stmt = $conn->prepare($query);
            
            if ($stmt->execute([$sent_to_person, $sent_to_contact, $reason, $external_notes, $material_id])) {
                $material_query = $conn->query("SELECT m.telephone, m.name FROM materials m WHERE material_id = $material_id");
                $material_data = $material_query->fetch();
                
                $cadet_match = $conn->query("SELECT cadetid FROM cadet WHERE number = '{$material_data['telephone']}'")->fetch();
                if ($cadet_match) {
                    createNotification($conn, $cadet_match['cadetid'], "Material Sent Outside", 
                        "Your material '{$material_data['name']}' has been sent outside institution", 'material_sent');
                }
                
                logAction($conn, $_SESSION['user_id'], 'send_outside', "Sent material outside institution: ID $material_id");
                $success = "Material sent outside institution successfully!";
            }
        } catch (PDOException $e) {
            $error = "Error sending material outside: " . $e->getMessage();
        }
    }
    
    // Handle mark as taken
    if (isset($_POST['mark_taken'])) {
        try {
            $material_id = $_POST['material_id'];
            
            $check_sql = "SELECT material_id, status, name, material_code, telephone FROM materials WHERE material_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->execute([$material_id]);
            $material = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$material) {
                $error = "Error: Material not found!";
            } elseif ($material['status'] == 'taken_outside') {
                $error = "Info: This material was already confirmed as taken.";
            } elseif ($material['status'] != 'outside_institution') {
                $error = "Error: This material cannot be confirmed as taken. Its current status is '" . $material['status'] . "'.";
            } else {
                $check_column = $conn->query("SHOW COLUMNS FROM materials LIKE 'taken_date'")->fetch();
                if ($check_column) {
                    $update_sql = "UPDATE materials SET status = 'taken_outside', taken_date = NOW() WHERE material_id = ?";
                } else {
                    $update_sql = "UPDATE materials SET status = 'taken_outside' WHERE material_id = ?";
                }
                
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->execute([$material_id]);
                $affected_rows = $update_stmt->rowCount();
                
                if ($affected_rows > 0) {
                    $cadet_match = $conn->query("SELECT cadetid FROM cadet WHERE number = '{$material['telephone']}'")->fetch();
                    if ($cadet_match) {
                        createNotification($conn, $cadet_match['cadetid'], "Material Taken Outside",
                            "Your material '{$material['name']}' has been confirmed as taken outside", 'material_taken');
                    }
                    
                    logAction($conn, $_SESSION['user_id'], 'mark_taken', "Marked material as taken outside: ID $material_id");
                    $success = "Material successfully marked as taken outside!";
                } else {
                    $error = "No rows were updated. The material status may not have changed.";
                }
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
    
    // Handle QR code scanning
    if (isset($_POST['scan_qr_code'])) {
        try {
            $pass_id = $_POST['pass_id'];
            $pass_number = $_POST['pass_number'];
            $vehicle_id = $_POST['vehicle_id'];
            $scan_type = $_POST['scan_type'];
            $scanned_by = $_POST['scanned_by'];
            
            // Get gate pass details
            $pass_query = "
                SELECT gp.*, 
                       v.plate_number, v.vehicle_type, v.model,
                       d.fname as driver_fname, d.lname as driver_lname,
                       o.fname as officer_fname, o.lname as officer_lname
                FROM gate_passes gp
                LEFT JOIN vehicles v ON gp.vehicle_id = v.vehicle_id
                LEFT JOIN workers d ON gp.driver_id = d.worker_id
                LEFT JOIN workers o ON gp.officer_id = o.worker_id
                WHERE gp.pass_id = ?
            ";
            $pass_stmt = $conn->prepare($pass_query);
            $pass_stmt->execute([$pass_id]);
            $gate_pass = $pass_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$gate_pass) {
                $error = "Invalid gate pass QR code!";
            } else {
                if ($scan_type === 'out') {
                    // Check if already scanned out
                    $check_scan = $conn->query("SELECT * FROM gate_scans WHERE pass_id = $pass_id AND scan_type = 'out'")->fetch();
                    
                    if (!$check_scan) {
                        // Record exit scan with actual time
                        $scan_query = "INSERT INTO gate_scans (pass_id, scan_type, scanned_by, notes, scan_time) 
                                       VALUES (?, 'out', ?, 'Vehicle exited institution', NOW())";
                        $scan_stmt = $conn->prepare($scan_query);
                        $scan_stmt->execute([$pass_id, $scanned_by]);
                        
                        // Update gate pass status and actual time out
                        $update_pass = "UPDATE gate_passes SET status = 'outside', actual_time_out = NOW() WHERE pass_id = ?";
                        $update_stmt = $conn->prepare($update_pass);
                        $update_stmt->execute([$pass_id]);
                        
                        // Update vehicle status
                        $update_vehicle = "UPDATE vehicles SET status = 'outside' WHERE vehicle_id = ?";
                        $vehicle_stmt = $conn->prepare($update_vehicle);
                        $vehicle_stmt->execute([$gate_pass['vehicle_id']]);
                        
                        logAction($conn, $_SESSION['user_id'], 'scan_out', "Vehicle exit scanned: {$gate_pass['plate_number']} (Pass: {$gate_pass['pass_number']})");
                        $success = "Vehicle exit recorded successfully! Plate: " . $gate_pass['plate_number'] . " is now outside institution.";
                    } else {
                        $error = "Vehicle already scanned out!";
                    }
                } else if ($scan_type === 'in') {
                    // Check if scanned out first
                    $check_out_scan = $conn->query("SELECT * FROM gate_scans WHERE pass_id = $pass_id AND scan_type = 'out'")->fetch();
                    
                    if ($check_out_scan) {
                        // Check if already scanned in
                        $check_in_scan = $conn->query("SELECT * FROM gate_scans WHERE pass_id = $pass_id AND scan_type = 'in'")->fetch();
                        
                        if (!$check_in_scan) {
                            // Record entry scan with actual time
                            $scan_query = "INSERT INTO gate_scans (pass_id, scan_type, scanned_by, notes, scan_time) 
                                           VALUES (?, 'in', ?, 'Vehicle returned to institution', NOW())";
                            $scan_stmt = $conn->prepare($scan_query);
                            $scan_stmt->execute([$pass_id, $scanned_by]);
                            
                            // Update gate pass status and actual return time
                            $update_pass = "UPDATE gate_passes SET status = 'returned', actual_return = NOW() WHERE pass_id = ?";
                            $update_stmt = $conn->prepare($update_pass);
                            $update_stmt->execute([$pass_id]);
                            
                            // Update vehicle status
                            $update_vehicle = "UPDATE vehicles SET status = 'available' WHERE vehicle_id = ?";
                            $vehicle_stmt = $conn->prepare($update_vehicle);
                            $vehicle_stmt->execute([$gate_pass['vehicle_id']]);
                            
                            logAction($conn, $_SESSION['user_id'], 'scan_in', "Vehicle return scanned: {$gate_pass['plate_number']} (Pass: {$gate_pass['pass_number']})");
                            $success = "Vehicle return recorded successfully! Plate: " . $gate_pass['plate_number'] . " is now back in institution.";
                        } else {
                            $error = "Vehicle already scanned in!";
                        }
                    } else {
                        $error = "Vehicle must be scanned out first before scanning in!";
                    }
                }
            }
        } catch (PDOException $e) {
            $error = "Error processing QR scan: " . $e->getMessage();
        }
    }
    
    // Handle get pass details request
    if (isset($_POST['get_pass_details'])) {
        try {
            $pass_id = $_POST['pass_id'];
            
            $pass_query = "
                SELECT gp.*, 
                       v.plate_number, v.vehicle_type, v.model,
                       d.fname as driver_fname, d.lname as driver_lname, d.rank as driver_rank,
                       o.fname as officer_fname, o.lname as officer_lname, o.rank as officer_rank
                FROM gate_passes gp
                LEFT JOIN vehicles v ON gp.vehicle_id = v.vehicle_id
                LEFT JOIN workers d ON gp.driver_id = d.worker_id
                LEFT JOIN workers o ON gp.officer_id = o.worker_id
                WHERE gp.pass_id = ?
            ";
            $pass_stmt = $conn->prepare($pass_query);
            $pass_stmt->execute([$pass_id]);
            $gate_pass = $pass_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($gate_pass) {
                echo json_encode([
                    'success' => true,
                    'data' => $gate_pass
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Gate pass not found'
                ]);
            }
            exit;
            
        } catch (PDOException $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Error fetching gate pass details: ' . $e->getMessage()
            ]);
            exit;
        }
    }
    
    // Handle manual QR code processing
    if (isset($_POST['process_manual_qr'])) {
        try {
            $qr_data = $_POST['qr_data'];
            $scan_type = $_POST['scan_type'];
            
            // Check if it's a direct gate pass number (starts with GP and has numbers)
            if (preg_match('/^GP\d+$/', $qr_data)) {
                // It's a direct gate pass number - look it up directly
                $pass_query = "
                    SELECT gp.*, 
                           v.plate_number, v.vehicle_type, v.model, v.vehicle_id,
                           d.fname as driver_fname, d.lname as driver_lname,
                           o.fname as officer_fname, o.lname as officer_lname
                    FROM gate_passes gp
                    LEFT JOIN vehicles v ON gp.vehicle_id = v.vehicle_id
                    LEFT JOIN workers d ON gp.driver_id = d.worker_id
                    LEFT JOIN workers o ON gp.officer_id = o.worker_id
                    WHERE gp.pass_number = ?
                ";
                $pass_stmt = $conn->prepare($pass_query);
                $pass_stmt->execute([$qr_data]);
                $gate_pass = $pass_stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$gate_pass) {
                    $error = "Gate pass not found in database: " . $qr_data;
                } else {
                    // Process the scan based on type
                    if ($scan_type === 'out') {
                        // Check if already scanned out
                        $check_scan = $conn->query("SELECT * FROM gate_scans WHERE pass_id = {$gate_pass['pass_id']} AND scan_type = 'out'")->fetch();
                        
                        if (!$check_scan) {
                            // Record exit scan
                            $scan_query = "INSERT INTO gate_scans (pass_id, scan_type, scanned_by, notes, scan_time) 
                                           VALUES (?, 'out', ?, 'Manual entry - Vehicle exited institution', NOW())";
                            $scan_stmt = $conn->prepare($scan_query);
                            $scan_stmt->execute([$gate_pass['pass_id'], $_SESSION['user_id']]);
                            
                            // Update gate pass status
                            $update_pass = "UPDATE gate_passes SET status = 'outside', actual_time_out = NOW() WHERE pass_id = ?";
                            $update_stmt = $conn->prepare($update_pass);
                            $update_stmt->execute([$gate_pass['pass_id']]);
                            
                            // Update vehicle status
                            $update_vehicle = "UPDATE vehicles SET status = 'outside' WHERE vehicle_id = ?";
                            $vehicle_stmt = $conn->prepare($update_vehicle);
                            $vehicle_stmt->execute([$gate_pass['vehicle_id']]);
                            
                            logAction($conn, $_SESSION['user_id'], 'manual_scan_out', "Manual vehicle exit: {$gate_pass['plate_number']} (Pass: {$gate_pass['pass_number']})");
                            $success = "Vehicle exit recorded successfully! Plate: " . $gate_pass['plate_number'] . " is now outside institution.";
                        } else {
                            $error = "Vehicle already scanned out!";
                        }
                    } 
                    else if ($scan_type === 'in') {
                        // Check if scanned out first
                        $check_out_scan = $conn->query("SELECT * FROM gate_scans WHERE pass_id = {$gate_pass['pass_id']} AND scan_type = 'out'")->fetch();
                        
                        if ($check_out_scan) {
                            // Check if already scanned in
                            $check_in_scan = $conn->query("SELECT * FROM gate_scans WHERE pass_id = {$gate_pass['pass_id']} AND scan_type = 'in'")->fetch();
                            
                            if (!$check_in_scan) {
                                // Record entry scan
                                $scan_query = "INSERT INTO gate_scans (pass_id, scan_type, scanned_by, notes, scan_time) 
                                               VALUES (?, 'in', ?, 'Manual entry - Vehicle returned to institution', NOW())";
                                $scan_stmt = $conn->prepare($scan_query);
                                $scan_stmt->execute([$gate_pass['pass_id'], $_SESSION['user_id']]);
                                
                                // Update gate pass status
                                $update_pass = "UPDATE gate_passes SET status = 'returned', actual_return = NOW() WHERE pass_id = ?";
                                $update_stmt = $conn->prepare($update_pass);
                                $update_stmt->execute([$gate_pass['pass_id']]);
                                
                                // Update vehicle status
                                $update_vehicle = "UPDATE vehicles SET status = 'available' WHERE vehicle_id = ?";
                                $vehicle_stmt = $conn->prepare($update_vehicle);
                                $vehicle_stmt->execute([$gate_pass['vehicle_id']]);
                                
                                logAction($conn, $_SESSION['user_id'], 'manual_scan_in', "Manual vehicle return: {$gate_pass['plate_number']} (Pass: {$gate_pass['pass_number']})");
                                $success = "Vehicle return recorded successfully! Plate: " . $gate_pass['plate_number'] . " is now back in institution.";
                            } else {
                                $error = "Vehicle already scanned in!";
                            }
                        } else {
                            $error = "Vehicle must be scanned out first before scanning in!";
                        }
                    }
                }
            } 
            // Parse QR data (format: GATEPASS:PASS_NUMBER:PASS_ID:VEHICLE_ID)
            else if (strpos($qr_data, 'GATEPASS:') === 0) {
                $qr_parts = explode(':', $qr_data);
                
                if (count($qr_parts) >= 4 && $qr_parts[0] === 'GATEPASS') {
                    $pass_number = $qr_parts[1];
                    
                    // Get gate pass details using pass_number
                    $pass_query = "
                        SELECT gp.*, 
                               v.plate_number, v.vehicle_type, v.model, v.vehicle_id,
                               d.fname as driver_fname, d.lname as driver_lname,
                               o.fname as officer_fname, o.lname as officer_lname
                        FROM gate_passes gp
                        LEFT JOIN vehicles v ON gp.vehicle_id = v.vehicle_id
                        LEFT JOIN workers d ON gp.driver_id = d.worker_id
                        LEFT JOIN workers o ON gp.officer_id = o.worker_id
                        WHERE gp.pass_number = ?
                    ";
                    $pass_stmt = $conn->prepare($pass_query);
                    $pass_stmt->execute([$pass_number]);
                    $gate_pass = $pass_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($gate_pass) {
                        // Process the scan (same logic as above)
                        if ($scan_type === 'out') {
                            $check_scan = $conn->query("SELECT * FROM gate_scans WHERE pass_id = {$gate_pass['pass_id']} AND scan_type = 'out'")->fetch();
                            if (!$check_scan) {
                                $scan_query = "INSERT INTO gate_scans (pass_id, scan_type, scanned_by, notes, scan_time) VALUES (?, 'out', ?, 'QR scan - Vehicle exited institution', NOW())";
                                $scan_stmt = $conn->prepare($scan_query);
                                $scan_stmt->execute([$gate_pass['pass_id'], $_SESSION['user_id']]);
                                
                                $update_pass = "UPDATE gate_passes SET status = 'outside', actual_time_out = NOW() WHERE pass_id = ?";
                                $update_stmt = $conn->prepare($update_pass);
                                $update_stmt->execute([$gate_pass['pass_id']]);
                                
                                $update_vehicle = "UPDATE vehicles SET status = 'outside' WHERE vehicle_id = ?";
                                $vehicle_stmt = $conn->prepare($update_vehicle);
                                $vehicle_stmt->execute([$gate_pass['vehicle_id']]);
                                
                                logAction($conn, $_SESSION['user_id'], 'qr_scan_out', "QR vehicle exit: {$gate_pass['plate_number']} (Pass: {$gate_pass['pass_number']})");
                                $success = "Vehicle exit recorded successfully! Plate: " . $gate_pass['plate_number'];
                            } else {
                                $error = "Vehicle already scanned out!";
                            }
                        } else if ($scan_type === 'in') {
                            $check_out_scan = $conn->query("SELECT * FROM gate_scans WHERE pass_id = {$gate_pass['pass_id']} AND scan_type = 'out'")->fetch();
                            if ($check_out_scan) {
                                $check_in_scan = $conn->query("SELECT * FROM gate_scans WHERE pass_id = {$gate_pass['pass_id']} AND scan_type = 'in'")->fetch();
                                if (!$check_in_scan) {
                                    $scan_query = "INSERT INTO gate_scans (pass_id, scan_type, scanned_by, notes, scan_time) VALUES (?, 'in', ?, 'QR scan - Vehicle returned to institution', NOW())";
                                    $scan_stmt = $conn->prepare($scan_query);
                                    $scan_stmt->execute([$gate_pass['pass_id'], $_SESSION['user_id']]);
                                    
                                    $update_pass = "UPDATE gate_passes SET status = 'returned', actual_return = NOW() WHERE pass_id = ?";
                                    $update_stmt = $conn->prepare($update_pass);
                                    $update_stmt->execute([$gate_pass['pass_id']]);
                                    
                                    $update_vehicle = "UPDATE vehicles SET status = 'available' WHERE vehicle_id = ?";
                                    $vehicle_stmt = $conn->prepare($update_vehicle);
                                    $vehicle_stmt->execute([$gate_pass['vehicle_id']]);
                                    
                                    logAction($conn, $_SESSION['user_id'], 'qr_scan_in', "QR vehicle return: {$gate_pass['plate_number']} (Pass: {$gate_pass['pass_number']})");
                                    $success = "Vehicle return recorded successfully! Plate: " . $gate_pass['plate_number'];
                                } else {
                                    $error = "Vehicle already scanned in!";
                                }
                            } else {
                                $error = "Vehicle must be scanned out first before scanning in!";
                            }
                        }
                    } else {
                        $error = "Gate pass not found: " . $pass_number;
                    }
                } else {
                    $error = "Invalid QR code format!";
                }
            }
            else {
                $error = "Invalid input! Please enter a gate pass number (like GP20251112123722169) or a valid QR code.";
            }
        } catch (PDOException $e) {
            $error = "Error processing scan: " . $e->getMessage();
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

// Get outside materials for the dedicated page
$outside_materials = $conn->query("
    SELECT m.*, c.fname, c.lname, c.rollno, c.number as cadet_phone
    FROM materials m 
    LEFT JOIN cadet c ON m.telephone = c.number 
    WHERE m.status IN ('outside_institution', 'taken_outside')
    ORDER BY m.sent_date DESC
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

// Get available materials for send outside form
$available_materials = $conn->query("
    SELECT m.*, c.fname, c.lname 
    FROM materials m 
    LEFT JOIN cadet c ON m.telephone = c.number 
    WHERE m.status = 'available' OR m.status IS NULL OR m.status = ''
")->fetchAll(PDO::FETCH_ASSOC);

// Get vehicle statistics
$vehicle_stats = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM vehicles) as total_vehicles,
        (SELECT COUNT(*) FROM vehicles WHERE status = 'available') as available_vehicles,
        (SELECT COUNT(*) FROM vehicles WHERE status = 'outside') as outside_vehicles,
        (SELECT COUNT(*) FROM gate_passes WHERE status = 'outside') as active_passes,
        (SELECT COUNT(*) FROM gate_passes WHERE DATE(created_at) = CURDATE()) as today_passes
")->fetch(PDO::FETCH_ASSOC);

$gatekeeper_stats = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM materials) as total_materials,
        (SELECT COUNT(*) FROM materials WHERE registered_by = '{$_SESSION['full_name']}') as my_materials,
        (SELECT COUNT(*) FROM materials WHERE DATE(registered_date) = CURDATE()) as today_materials,
        (SELECT COUNT(*) FROM materials WHERE status = 'outside_institution') as outside_pending,
        (SELECT COUNT(*) FROM materials WHERE status = 'taken_outside') as outside_taken
")->fetch(PDO::FETCH_ASSOC);

// Get recent gate scans
$recent_scans = $conn->query("
    SELECT gs.*, gp.pass_number, v.plate_number, v.vehicle_type,
           TIME(gs.scan_time) as scan_time_only,
           DATE(gs.scan_time) as scan_date
    FROM gate_scans gs
    LEFT JOIN gate_passes gp ON gs.pass_id = gp.pass_id
    LEFT JOIN vehicles v ON gp.vehicle_id = v.vehicle_id
    WHERE gs.scanned_by = '{$_SESSION['user_id']}'
    ORDER BY gs.scan_time DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Material categories
$material_categories = ['Electronics', 'Laboratory Equipment', 'Tools', 'Furniture', 'Sports Equipment', 'Books', 'Uniforms', 'Stationery', 'Medical Supplies', 'Other'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gatekeeper Dashboard - Military Institution</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
        :root {
            --primary: #08458ba2;
            --secondary: #c0392b;
            --success: #27ae60;
            --warning: #f39c12;
            --danger: #e74c3c;
            --info: #3498db;
            --light: #ecf0f1;
            --dark: #2c3e50;
            --sidebar-width: 70px;
            --sidebar-expanded-width: 280px;
            --header-bg: #2c3e50;
            --sidebar-bg: #34495e;
            --sidebar-text: #ecf0f1;
            --content-bg: #f8f9fa;
            --card-bg: #ffffff;
            --text-color: #2c3e50;
            --border-color: #dee2e6;
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
            font-size: var(--base-font-size);
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
            font-size: 1.2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .logo i {
            font-size: 1.4rem;
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
        
        /* Sidebar with Auto-hide */
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

        .sidebar:hover,
        .sidebar.expanded {
            width: var(--sidebar-expanded-width);
        }
        
        .sidebar-content {
            padding: 1rem 0.6rem;
        }

        .sidebar:hover .sidebar-content,
        .sidebar.expanded .sidebar-content {
            padding: 1rem 1.5rem;
        }
        
        .sidebar-menu {
            list-style: none;
        }
        
        .sidebar-menu li {
            margin-bottom: 0.3rem;
            position: relative;
        }
        
        .sidebar-menu a {
            color: var(--sidebar-text);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0.8rem;
            border-radius: 8px;
            transition: all 0.3s;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            font-size: 0.9rem;
        }

        .sidebar-menu a span {
            display: none;
        }

        .sidebar:hover .sidebar-menu a span,
        .sidebar.expanded .sidebar-menu a span {
            display: inline;
        }
        
        .sidebar-menu a:hover {
            background: rgba(255,255,255,0.15);
            transform: translateX(5px);
        }
        
        .sidebar-menu a.active {
            background: var(--primary);
            box-shadow: 0 2px 8px rgba(231, 76, 60, 0.3);
        }
        
        .sidebar-menu a i {
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
            transition: transform 0.3s;
        }

        .sidebar-menu a:hover i {
            transform: scale(1.1);
        }

        .sidebar-toggle {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.6rem;
            border-radius: 8px;
            cursor: pointer;
            margin-bottom: 1rem;
            width: 100%;
            font-size: 0.85rem;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .sidebar-toggle:hover {
            background: var(--secondary);
            transform: translateY(-2px);
        }
        
        /* Dropdown Menu Styles */
        .dropdown-menu {
            list-style: none;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease;
            background: rgba(0,0,0,0.1);
            border-radius: 6px;
            margin-left: 10px;
        }

        .dropdown-menu.show {
            max-height: 500px;
        }

        .dropdown-menu a {
            padding: 0.6rem 0.6rem 0.6rem 2.5rem;
            font-size: 0.85rem;
            border-radius: 4px;
            margin: 0.1rem 0.3rem;
        }

        .dropdown-menu a:hover {
            background: rgba(255,255,255,0.1);
            transform: translateX(3px);
        }

        .dropdown-toggle {
            position: relative;
        }

        .dropdown-toggle::after {
            content: '\f107';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            position: absolute;
            right: 15px;
            transition: transform 0.3s;
            display: none;
        }

        .sidebar:hover .dropdown-toggle::after,
        .sidebar.expanded .dropdown-toggle::after {
            display: block;
        }

        .dropdown-toggle.active::after {
            transform: rotate(180deg);
        }

        /* Content Area */
        .content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 1.2rem;
            background: var(--content-bg);
            height: calc(100vh - 55px);
            transition: all 0.3s ease;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }

        .sidebar:hover + .content,
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
            margin-bottom: 1.2rem;
            padding-bottom: 0.8rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .page-title {
            color: var(--dark);
            font-size: var(--header-font-size);
            font-weight: 600;
        }
        
        .page-description {
            color: #666;
            font-size: 0.9rem;
            margin-top: 0.2rem;
        }
        
        /* Dashboard Cards */
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.2rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: var(--card-bg);
            border-radius: 10px;
            padding: 1.2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            text-align: center;
            border-left: 4px solid var(--primary);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .stat {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--primary);
            line-height: 1;
            margin: 0.8rem 0;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }
        
        /* Vehicle Stats */
        .vehicle-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.2rem;
            margin-bottom: 1.5rem;
        }
        
        /* Chart Container */
        .chart-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .chart-card {
            background: var(--card-bg);
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            position: relative;
            overflow: hidden;
        }
        
        .chart-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--info), var(--primary));
        }
        
        .chart-card h3 {
            margin-bottom: 1rem;
            color: var(--dark);
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .chart-wrapper {
            height: 250px;
            position: relative;
        }
        
        /* Table Container */
        .table-container {
            background: var(--card-bg);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            flex: 1;
            display: flex;
            flex-direction: column;
            margin-bottom: 0;
            width: 100%;
        }
        
        .table-wrapper {
            flex: 1;
            overflow: auto;
            width: 100%;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }
        
        th, td {
            padding: 0.8rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            font-size: var(--table-font-size);
        }
        
        th {
            background: var(--sidebar-bg);
            color: var(--sidebar-text);
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
            font-size: 0.85rem;
            white-space: nowrap;
        }
        
        tr:hover {
            background: rgba(52, 152, 219, 0.05);
        }
        
        /* Form Styles */
        .form-container {
            background: var(--card-bg);
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 1rem;
            font-size: var(--card-font-size);
            position: relative;
            overflow: hidden;
        }

        .form-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--success), var(--info));
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.4rem;
            font-weight: 600;
            color: var(--dark);
            font-size: 0.9rem;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.7rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            background: var(--card-bg);
            color: var(--text-color);
            transition: all 0.3s;
            font-size: 0.9rem;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.1);
        }
        
        .btn {
            padding: 0.7rem 1.2rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 0.9rem;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }
        
        .btn-sm {
            padding: 0.5rem 0.9rem;
            font-size: 0.85rem;
        }
        
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--secondary); transform: translateY(-2px); }
        
        .btn-success { background: var(--success); color: white; }
        .btn-success:hover { background: #219653; transform: translateY(-2px); }
        
        .btn-warning { background: var(--warning); color: white; }
        .btn-warning:hover { background: #e67e22; transform: translateY(-2px); }
        
        .btn-info { background: var(--info); color: white; }
        .btn-info:hover { background: #2980b9; transform: translateY(-2px); }
        
        .btn-danger { background: var(--danger); color: white; }
        .btn-danger:hover { background: var(--secondary); transform: translateY(-2px); }
        
        /* Search Bar */
        .search-container {
            margin-bottom: 1rem;
        }
        
        .search-box {
            width: 100%;
            padding: 0.7rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 0.9rem;
            background: var(--card-bg);
            color: var(--text-color);
            transition: all 0.3s;
        }

        .search-box:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.1);
        }
        
        /* Badges */
        .badge {
            padding: 0.3rem 0.6rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .badge-available { background: #27ae60; color: white; }
        .badge-checked-out { background: #f39c12; color: white; }
        .badge-outside { background: #3498db; color: white; }
        .badge-approved { background: #28a745; color: white; }
        .badge-outside { background: #ffc107; color: black; }
        .badge-returned { background: #17a2b8; color: white; }
        .badge-cancelled { background: #dc3545; color: white; }
        
        /* Alerts */
        .alert {
            padding: 0.8rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 0.9rem;
            border-left: 4px solid;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left-color: #28a745;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left-color: #dc3545;
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
            border-radius: 10px;
            padding: 1.2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            max-height: 350px;
            overflow-y: auto;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.8rem;
            border-bottom: 1px solid var(--border-color);
            transition: background 0.3s;
        }

        .activity-item:hover {
            background: rgba(52, 152, 219, 0.05);
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.9rem;
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
            font-size: 0.85rem;
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
            backdrop-filter: blur(5px);
        }
        
        .modal-content {
            background: var(--card-bg);
            border-radius: 10px;
            padding: 1.5rem;
            width: 95%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            font-size: var(--card-font-size);
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            position: relative;
        }

        .modal-content::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--warning));
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.3rem;
            cursor: pointer;
            color: var(--danger);
            float: right;
            transition: transform 0.3s;
        }

        .modal-close:hover {
            transform: scale(1.1);
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

        /* Scan Container */
        .scan-container {
            background: var(--card-bg);
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            position: relative;
            overflow: hidden;
        }

        .scan-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--info), var(--success));
        }
        
        #qr-reader {
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
            border: 2px dashed var(--border-color);
            border-radius: 10px;
            padding: 1rem;
            background: var(--card-bg);
        }
        
        #qr-reader video {
            border-radius: 8px;
            width: 100%;
        }
        
        .scan-result {
            margin-top: 1rem;
            padding: 1rem;
            border-radius: 6px;
            background: var(--light);
            text-align: left;
        }

        .scan-controls {
            display: flex;
            gap: 0.8rem;
            justify-content: center;
            margin-top: 1rem;
            flex-wrap: wrap;
        }

        .scan-type-selector {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            justify-content: center;
        }

        .scan-type-btn {
            padding: 0.6rem 1.2rem;
            border: 2px solid var(--border-color);
            background: var(--card-bg);
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
        }

        .scan-type-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        /* Scan Result Modal Styles */
        .pass-header {
            border-bottom: 2px solid var(--primary);
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .info-box {
            background: var(--light);
            padding: 0.8rem;
            border-radius: 6px;
            border-left: 4px solid var(--primary);
            margin-top: 0.3rem;
        }

        .scan-confirm-actions {
            border-top: 1px solid var(--border-color);
            padding-top: 1.5rem;
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        /* Animation for scanning */
        @keyframes scanning {
            0% { border-color: var(--border-color); }
            50% { border-color: var(--primary); }
            100% { border-color: var(--border-color); }
        }

        .scanning-active #qr-reader {
            animation: scanning 2s infinite;
        }

        /* Success state */
        .scan-success {
            background: rgba(40, 167, 69, 0.1) !important;
            border-color: #28a745 !important;
        }

        .camera-error {
            background: #f8d7da;
            border: 1px solid #1b48ddce;
            border-radius: 6px;
            padding: 1rem;
            margin: 1rem 0;
            text-align: center;
        }

        .camera-error h4 {
            color: #721c24;
            margin-bottom: 0.5rem;
        }

        .camera-error p {
            color: #721c24;
            margin-bottom: 0.5rem;
        }

        .no-match { color: #999; font-style: italic; }
        .text-muted { color: #6c757d; }
        
        /* Success message styles */
        .success-message {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            text-align: center;
            z-index: 2000;
            border: 4px solid #28a745;
            max-width: 400px;
            width: 90%;
        }
        
        .success-message h3 {
            color: #28a745;
            margin-bottom: 1rem;
        }
        
        .success-message p {
            margin-bottom: 1.5rem;
            font-size: 1.1rem;
        }
        
        .success-icon {
            font-size: 3rem;
            color: #28a745;
            margin-bottom: 1rem;
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

            .quick-actions {
                justify-content: center;
            }

            .btn {
                flex: 1;
                min-width: 120px;
                justify-content: center;
            }

            .scan-confirm-actions {
                flex-direction: column;
            }
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
                    <span>Gatekeeper Dashboard</span>
                </div>
            </div>
            <div class="header-actions">
                <button class="theme-toggle" id="themeToggle">
                    <i class="fas fa-moon"></i>
                </button>
                <span class="user-role">Gatekeeper</span>
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
                    <i class="fas fa-bars"></i> <span>Menu</span>
                </button>
                <ul class="sidebar-menu">
                    <li><a href="#" class="nav-link active" data-page="dashboard"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                    
                    <li class="dropdown-toggle">
                        <a href="#" class="nav-link dropdown-main">
                            <i class="fas fa-box"></i> <span>Materials Management</span>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a href="#" class="nav-link" data-page="register-material"><i class="fas fa-plus-circle"></i> <span>Register Material</span></a></li>
                            <li><a href="#" class="nav-link" data-page="materials-list"><i class="fas fa-boxes"></i> <span>All Materials</span></a></li>
                            <li><a href="#" class="nav-link" data-page="outside-materials"><i class="fas fa-external-link-alt"></i> <span>Outside Materials</span></a></li>
                            <li><a href="#" class="nav-link" data-page="taken-materials"><i class="fas fa-check-circle"></i> <span>Taken Outside</span></a></li>
                        </ul>
                    </li>

                    <li class="dropdown-toggle">
                        <a href="#" class="nav-link dropdown-main">
                            <i class="fas fa-car"></i> <span>Vehicle System</span>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a href="#" class="nav-link" data-page="gate-scan"><i class="fas fa-qrcode"></i> <span>Scan Gate Pass</span></a></li>
                            <li><a href="#" class="nav-link" data-page="vehicle-reports"><i class="fas fa-chart-bar"></i> <span>Vehicle Reports</span></a></li>
                        </ul>
                    </li>
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

            <!-- Dashboard Page -->
            <div class="page-content active" id="dashboard">
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Gatekeeper Dashboard</h1>
                        <p class="page-description">Welcome, <?php echo $_SESSION['full_name']; ?>! Complete gate management overview.</p>
                    </div>
                </div>
                
                <div class="dashboard-cards">
                    <div class="stat-card">
                        <i class="fas fa-box fa-lg"></i>
                        <div class="stat"><?php echo $gatekeeper_stats['total_materials']; ?></div>
                        <p>Total Materials</p>
                    </div>
                    
                    <div class="stat-card">
                        <i class="fas fa-user-check fa-lg"></i>
                        <div class="stat"><?php echo $gatekeeper_stats['my_materials']; ?></div>
                        <p>My Registered Materials</p>
                    </div>
                    
                    <div class="stat-card">
                        <i class="fas fa-calendar-day fa-lg"></i>
                        <div class="stat"><?php echo $gatekeeper_stats['today_materials']; ?></div>
                        <p>Today's Materials</p>
                    </div>
                    
                    <div class="stat-card">
                        <i class="fas fa-car fa-lg"></i>
                        <div class="stat"><?php echo $vehicle_stats['total_vehicles']; ?></div>
                        <p>Total Vehicles</p>
                    </div>
                </div>

                <div class="vehicle-stats">
                    <div class="stat-card">
                        <i class="fas fa-paper-plane fa-lg"></i>
                        <div class="stat"><?php echo $gatekeeper_stats['outside_pending']; ?></div>
                        <p>Pending Outside</p>
                    </div>
                    
                    <div class="stat-card">
                        <i class="fas fa-check-circle fa-lg"></i>
                        <div class="stat"><?php echo $gatekeeper_stats['outside_taken']; ?></div>
                        <p>Taken Outside</p>
                    </div>
                    
                    <div class="stat-card">
                        <i class="fas fa-passport fa-lg"></i>
                        <div class="stat"><?php echo $vehicle_stats['today_passes']; ?></div>
                        <p>Today's Gate Passes</p>
                    </div>
                    
                    <div class="stat-card">
                        <i class="fas fa-external-link-alt fa-lg"></i>
                        <div class="stat"><?php echo $vehicle_stats['outside_vehicles']; ?></div>
                        <p>Vehicles Outside</p>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="quick-actions">
                    <button class="btn btn-primary" onclick="showPage('register-material')">
                        <i class="fas fa-plus"></i> Register Material
                    </button>
                    <button class="btn btn-info" onclick="showPage('gate-scan')">
                        <i class="fas fa-qrcode"></i> Scan Gate Pass
                    </button>
                    <button class="btn btn-warning" onclick="showPage('outside-materials')">
                        <i class="fas fa-external-link-alt"></i> Outside Materials
                    </button>
                    <button class="btn btn-success" onclick="showPage('taken-materials')">
                        <i class="fas fa-check-circle"></i> Taken Outside
                    </button>
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
                        <h3>Vehicle Status</h3>
                        <div class="chart-wrapper">
                            <canvas id="vehicleChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Recent Scans -->
                <div class="card">
                    <div class="card-header">
                        <h2>Recent Scans</h2>
                    </div>
                    <div class="activities-list">
                        <?php if (!empty($recent_scans)): ?>
                            <?php foreach ($recent_scans as $scan): ?>
                                <div class="activity-item">
                                    <div class="activity-icon <?php echo $scan['scan_type'] === 'out' ? 'activity-checkout' : 'activity-return'; ?>">
                                        <i class="fas fa-<?php echo $scan['scan_type'] === 'out' ? 'sign-out-alt' : 'sign-in-alt'; ?>"></i>
                                    </div>
                                    <div class="activity-details">
                                        <strong><?php echo $scan['plate_number']; ?> - <?php echo strtoupper($scan['scan_type']); ?></strong>
                                        <div class="activity-date">
                                            <?php echo date('M j, Y g:i A', strtotime($scan['scan_time'])); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="text-align: center; color: #666; padding: 2rem;">No recent scans found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Register Material Page -->
            <div class="page-content" id="register-material">
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Register New Material</h1>
                        <p class="page-description">Add new materials to the institutional inventory system.</p>
                    </div>
                </div>
                
                <div class="form-container">
                    <form method="POST" action="" id="materialForm">
                        <input type="hidden" name="register_material" value="1">
                        
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
                <div class="page-header">
                    <div>
                        <h1 class="page-title">All Materials</h1>
                        <p class="page-description">Complete list of all materials in the system with cadet assignments.</p>
                    </div>
                    <button class="btn btn-primary" onclick="showPage('register-material')">
                        <i class="fas fa-plus"></i> Add New Material
                    </button>
                </div>
                
                <div class="table-container">
                    <div class="table-wrapper">
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
                                    <th>Status</th>
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
                                        <td><?php echo date('M j, Y g:i A', strtotime($material['registered_date'])); ?></td>
                                        <td>
                                            <?php if ($material['status'] == 'available' || $material['status'] == null || $material['status'] == ''): ?>
                                                <span class="badge badge-available">Available</span>
                                            <?php elseif ($material['status'] == 'outside_institution'): ?>
                                                <span class="badge badge-warning">Outside</span>
                                            <?php elseif ($material['status'] == 'taken_outside'): ?>
                                                <span class="badge badge-success">Taken</span>
                                            <?php else: ?>
                                                <span class="badge badge-info"><?php echo ucfirst($material['status']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-warning btn-sm" onclick="editMaterial(<?php echo $material['material_id']; ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <?php if (($material['status'] == 'available' || $material['status'] == null || $material['status'] == '')): ?>
                                                <button class="btn btn-primary btn-sm" onclick="sendOutside(<?php echo $material['material_id']; ?>)">
                                                    <i class="fas fa-paper-plane"></i> Send Out
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Outside Materials Page -->
            <div class="page-content" id="outside-materials">
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Materials Outside Institution</h1>
                        <p class="page-description">Manage materials sent outside the institution. Track pending and confirmed taken items.</p>
                    </div>
                    <button class="btn btn-primary" onclick="showSendOutsideForm()">
                        <i class="fas fa-paper-plane"></i> Send Material Outside
                    </button>
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
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($outside_materials)): ?>
                                    <?php foreach ($outside_materials as $material): ?>
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
                                                <?php if ($material['status'] == 'outside_institution'): ?>
                                                    <span class="badge badge-warning">At Institution</span>
                                                <?php else: ?>
                                                    <span class="badge badge-success">Taken Out</span>
                                                    <?php if (isset($material['taken_date']) && $material['taken_date']): ?>
                                                        <br>
                                                        <small><?php echo date('M j, Y', strtotime($material['taken_date'])); ?></small>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($material['status'] == 'outside_institution'): ?>
                                                    <button class="btn btn-success btn-sm" onclick="markAsTaken(<?php echo $material['material_id']; ?>)">
                                                        <i class="fas fa-check"></i> Mark as Taken
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-muted">Completed</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="10" style="text-align: center; padding: 2rem;">
                                            <p>No materials sent outside institution yet.</p>
                                            <button class="btn btn-primary" onclick="showSendOutsideForm()">
                                                <i class="fas fa-paper-plane"></i> Send First Material Outside
                                            </button>
                                        </td>
                                    </tr>
                                <?php endif; ?>
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
                                                <?php echo date('M j, Y g:i A', strtotime($material['taken_date'])); ?>
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

            <!-- Gate Scan Page -->
            <div class="page-content" id="gate-scan">
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Gate Pass QR Scanner</h1>
                        <p class="page-description">Scan vehicle gate passes to record entries and exits.</p>
                    </div>
                </div>
                
                <div class="scan-container">
                    <h3><i class="fas fa-qrcode"></i> Scan Gate Pass QR Code</h3>
                    <p>Select scan type and use camera to scan QR code:</p>
                    
                    <div class="scan-type-selector">
                        <button class="scan-type-btn active" data-type="out" onclick="setScanType('out')">
                            <i class="fas fa-sign-out-alt"></i> Vehicle Exit (Out)
                        </button>
                        <button class="scan-type-btn" data-type="in" onclick="setScanType('in')">
                            <i class="fas fa-sign-in-alt"></i> Vehicle Return (In)
                        </button>
                    </div>
                    
                    <div id="scanInstructions" class="alert alert-info">
                        <i class="fas fa-car-side"></i>
                        <strong>Scan Vehicle Exit:</strong> Scan QR code when vehicle leaves the institution. The gate pass can only be scanned OUT once.
                    </div>

                    <!-- Mobile Instructions -->
                    <div id="mobileInstructions" style="display: none; margin-bottom: 1rem;"></div>

                    <!-- Camera Selection -->
                    <div class="form-group" style="max-width: 500px; margin: 0 auto 1rem auto;">
                        <label for="cameraSelect"><i class="fas fa-camera"></i> Select Camera</label>
                        <select id="cameraSelect" class="form-control" onchange="changeCamera(this.value)">
                            <option value="">Loading cameras...</option>
                        </select>
                        <small>Select your preferred camera</small>
                    </div>
                    
                    <div id="qr-reader"></div>

                    <!-- Camera Error Display -->
                    <div id="cameraError" class="camera-error" style="display: none;">
                        <h4><i class="fas fa-video-slash"></i> Camera Access Required</h4>
                        <p>To use the QR scanner, please allow camera access in your browser.</p>
                        <p><strong>For external cameras:</strong> Make sure your camera is connected and selected as the default camera.</p>
                        <div class="scan-controls">
                            <button class="btn btn-primary" onclick="retryCamera()">
                                <i class="fas fa-redo"></i> Retry Camera
                            </button>
                            <button class="btn btn-info" onclick="showCameraSettings()">
                                <i class="fas fa-cog"></i> Camera Settings
                            </button>
                            <button class="btn btn-warning" onclick="testMobileCamera()">
                                <i class="fas fa-bug"></i> Test Camera
                            </button>
                        </div>
                    </div>
                    
                    <div class="scan-controls">
                        <button id="startScannerBtn" class="btn btn-primary" onclick="startScanner()">
                            <i class="fas fa-camera"></i> Start Camera Scanner
                        </button>
                        <button id="stopScannerBtn" class="btn btn-secondary" style="display: none;" onclick="stopScanner()">
                            <i class="fas fa-stop"></i> Stop Camera
                        </button>
                    </div>
                </div>

                <!-- Quick Gate Pass Entry -->
                <div class="form-container">
                    <h3><i class="fas fa-keyboard"></i> Quick Gate Pass Entry</h3>
                    <p>Enter gate pass number to record vehicle movement:</p>
                    
                    <form method="POST" action="" id="quickScanForm">
                        <input type="hidden" name="process_manual_qr" value="1">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="gatePassNumber">Gate Pass Number *</label>
                                <input type="text" id="gatePassNumber" name="qr_data" required 
                                       placeholder="Example: GP20251112123722169"
                                       style="font-family: monospace; font-size: 1.2rem; text-align: center; padding: 12px;">
                                <small>Enter the gate pass number exactly as shown on the pass</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="scanType">Action Type *</label>
                                <select id="scanType" name="scan_type" required class="form-control">
                                    <option value="out">🚗 Vehicle Exit (Out)</option>
                                    <option value="in">🏠 Vehicle Return (In)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="scan-controls">
                            <button type="submit" class="btn btn-primary btn-lg" style="padding: 12px 24px; font-size: 1.1rem;">
                                <i class="fas fa-check-circle"></i> Process Gate Pass
                            </button>
                            
                            <button type="button" class="btn btn-outline-info" onclick="fillExample()">
                                <i class="fas fa-bolt"></i> Fill Example
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Recent Scans -->
                <div class="table-container">
                    <div class="page-header">
                        <h3>Recent Scans</h3>
                    </div>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Pass Number</th>
                                    <th>Vehicle</th>
                                    <th>Scan Type</th>
                                    <th>Scan Time</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_scans as $scan): ?>
                                <tr>
                                    <td><strong><?php echo $scan['pass_number']; ?></strong></td>
                                    <td>
                                        <?php echo $scan['plate_number']; ?>
                                        <br><small><?php echo $scan['vehicle_type']; ?></small>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $scan['scan_type'] == 'out' ? 'warning' : 'success'; ?>">
                                            <?php echo strtoupper($scan['scan_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo date('M j, Y', strtotime($scan['scan_date'])); ?>
                                        <br><small><?php echo $scan['scan_time_only']; ?></small>
                                    </td>
                                    <td>
                                        <span class="badge badge-success">Recorded</span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Vehicle Reports Page -->
            <div class="page-content" id="vehicle-reports">
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Vehicle Movement Reports</h1>
                        <p class="page-description">View completed vehicle trips - vehicles that went out and returned to institution.</p>
                    </div>
                    <div class="filter-controls">
                        <select id="statusFilter" class="form-control" onchange="filterVehicleReports()" style="max-width: 200px;">
                            <option value="all">All Vehicles</option>
                            <option value="returned" selected>Completed Trips Only</option>
                            <option value="outside">Currently Outside</option>
                            <option value="approved">Approved (Not Scanned)</option>
                        </select>
                    </div>
                </div>
                
                <div class="table-container">
                    <div class="table-wrapper">
                        <table id="vehicleReportsTable">
                            <thead>
                                <tr>
                                    <th>Pass Number</th>
                                    <th>Vehicle</th>
                                    <th>Driver</th>
                                    <th>Officer</th>
                                    <th>Destination</th>
                                    <th>Mission</th>
                                    <th>Time Out</th>
                                    <th>Actual Time Out</th>
                                    <th>Expected Return</th>
                                    <th>Actual Return</th>
                                    <th>Status</th>
                                    <th>Trip Duration</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Get vehicle reports with status filtering
                                $status_filter = "AND gp.status = 'returned'"; // Default: show only completed trips
                                $vehicle_reports = $conn->query("
                                    SELECT gp.*, 
                                           v.plate_number, v.vehicle_type, v.model, v.vehicle_id,
                                           d.fname as driver_fname, d.lname as driver_lname,
                                           o.fname as officer_fname, o.lname as officer_lname,
                                           TIMESTAMPDIFF(MINUTE, gp.actual_time_out, gp.actual_return) as trip_duration_minutes
                                    FROM gate_passes gp
                                    LEFT JOIN vehicles v ON gp.vehicle_id = v.vehicle_id
                                    LEFT JOIN workers d ON gp.driver_id = d.worker_id
                                    LEFT JOIN workers o ON gp.officer_id = o.worker_id
                                    WHERE 1=1 $status_filter
                                    ORDER BY gp.actual_return DESC
                                ")->fetchAll(PDO::FETCH_ASSOC);
                                
                                if (!empty($vehicle_reports)):
                                    foreach ($vehicle_reports as $pass):
                                        $trip_duration = '';
                                        if ($pass['trip_duration_minutes']) {
                                            $hours = floor($pass['trip_duration_minutes'] / 60);
                                            $minutes = $pass['trip_duration_minutes'] % 60;
                                            $trip_duration = $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";
                                        }
                                ?>
                                <tr data-status="<?php echo $pass['status']; ?>">
                                    <td><strong><?php echo $pass['pass_number']; ?></strong></td>
                                    <td>
                                        <?php echo $pass['plate_number']; ?>
                                        <br><small><?php echo $pass['vehicle_type']; ?> <?php echo $pass['model']; ?></small>
                                    </td>
                                    <td>
                                        <?php echo $pass['driver_fname'] . ' ' . $pass['driver_lname']; ?>
                                        <?php if ($pass['driver_rank']): ?>
                                            <br><small>(<?php echo $pass['driver_rank']; ?>)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($pass['officer_fname']): ?>
                                            <?php echo $pass['officer_fname'] . ' ' . $pass['officer_lname']; ?>
                                            <?php if ($pass['officer_rank']): ?>
                                                <br><small>(<?php echo $pass['officer_rank']; ?>)</small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">No officer</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $pass['destination']; ?></td>
                                    <td>
                                        <?php if ($pass['description']): ?>
                                            <span title="<?php echo htmlspecialchars($pass['description']); ?>">
                                                <?php echo strlen($pass['description']) > 30 ? substr($pass['description'], 0, 30) . '...' : $pass['description']; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">No description</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($pass['time_out'])); ?></td>
                                    <td>
                                        <?php if ($pass['actual_time_out']): ?>
                                            <strong style="color: #27ae60;"><?php echo date('M j, Y g:i A', strtotime($pass['actual_time_out'])); ?></strong>
                                        <?php else: ?>
                                            <span class="text-muted">Not scanned out</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($pass['expected_return'])); ?></td>
                                    <td>
                                        <?php if ($pass['actual_return']): ?>
                                            <strong style="color: #27ae60;"><?php echo date('M j, Y g:i A', strtotime($pass['actual_return'])); ?></strong>
                                        <?php else: ?>
                                            <span class="text-muted">Not returned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php 
                                            echo $pass['status'] == 'approved' ? 'primary' : 
                                                 ($pass['status'] == 'outside' ? 'warning' : 
                                                 ($pass['status'] == 'returned' ? 'success' : 'secondary')); 
                                        ?>">
                                            <?php echo ucfirst($pass['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($trip_duration): ?>
                                            <span class="badge badge-info"><?php echo $trip_duration; ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php 
                                    endforeach;
                                else: 
                                ?>
                                <tr>
                                    <td colspan="12" style="text-align: center; padding: 3rem;">
                                        <div style="color: #666; margin-bottom: 1rem;">
                                            <i class="fas fa-car fa-3x" style="margin-bottom: 1rem;"></i>
                                            <h3>No Completed Trips Found</h3>
                                            <p>There are no vehicles that have completed trips (gone out and returned) yet.</p>
                                        </div>
                                        <div class="scan-controls">
                                            <button class="btn btn-primary" onclick="showPage('gate-scan')">
                                                <i class="fas fa-qrcode"></i> Go to Gate Scanner
                                            </button>
                                            <button class="btn btn-info" onclick="loadAllVehicles()">
                                                <i class="fas fa-list"></i> Show All Vehicles
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Statistics Card -->
                <div class="dashboard-cards" style="margin-top: 1.5rem;">
                    <div class="stat-card">
                        <i class="fas fa-route fa-lg"></i>
                        <div class="stat" id="completedTripsCount"><?php echo count($vehicle_reports); ?></div>
                        <p>Completed Trips</p>
                    </div>
                    
                    <div class="stat-card">
                        <i class="fas fa-clock fa-lg"></i>
                        <div class="stat" id="avgTripDuration">
                            <?php
                            if (!empty($vehicle_reports)) {
                                $total_minutes = 0;
                                $count_with_duration = 0;
                                foreach ($vehicle_reports as $trip) {
                                    if ($trip['trip_duration_minutes']) {
                                        $total_minutes += $trip['trip_duration_minutes'];
                                        $count_with_duration++;
                                    }
                                }
                                if ($count_with_duration > 0) {
                                    $avg_minutes = round($total_minutes / $count_with_duration);
                                    $hours = floor($avg_minutes / 60);
                                    $minutes = $avg_minutes % 60;
                                    echo $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";
                                } else {
                                    echo "0m";
                                }
                            } else {
                                echo "0m";
                            }
                            ?>
                        </div>
                        <p>Avg. Trip Duration</p>
                    </div>
                    
                    <div class="stat-card">
                        <i class="fas fa-car-side fa-lg"></i>
                        <div class="stat" id="todayTrips">
                            <?php
                            $today_trips = $conn->query("
                                SELECT COUNT(*) as count 
                                FROM gate_passes 
                                WHERE DATE(actual_return) = CURDATE() AND status = 'returned'
                            ")->fetch()['count'];
                            echo $today_trips;
                            ?>
                        </div>
                        <p>Today's Returns</p>
                    </div>
                    
                    <div class="stat-card">
                        <i class="fas fa-calendar-week fa-lg"></i>
                        <div class="stat" id="weekTrips">
                            <?php
                            $week_trips = $conn->query("
                                SELECT COUNT(*) as count 
                                FROM gate_passes 
                                WHERE actual_return >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND status = 'returned'
                            ")->fetch()['count'];
                            echo $week_trips;
                            ?>
                        </div>
                        <p>This Week</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Send Outside Modal -->
    <div class="modal" id="sendOutsideModal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('sendOutsideModal')">&times;</button>
            <h2>Send Material Outside Institution</h2>
            <form method="POST" action="">
                <input type="hidden" name="send_outside" value="1">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="material_id">Select Material *</label>
                        <select id="material_id" name="material_id" required>
                            <option value="">Select material...</option>
                            <?php foreach ($available_materials as $material): ?>
                                <option value="<?php echo $material['material_id']; ?>">
                                    <?php echo $material['material_code'] . ' - ' . $material['name']; ?>
                                    <?php if ($material['fname']): ?>
                                        (<?php echo $material['fname'] . ' ' . $material['lname']; ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
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
                    <i class="fas fa-paper-plane"></i> Send Outside
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('sendOutsideModal')">
                    Cancel
                </button>
            </form>
        </div>
    </div>

    <!-- Mark as Taken Modal -->
    <div class="modal" id="markTakenModal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('markTakenModal')">&times;</button>
            <h2>Confirm Material Taken</h2>
            <p>Are you sure you want to mark this material as taken from the institution?</p>
            <form method="POST" action="">
                <input type="hidden" name="mark_taken" value="1">
                <input type="hidden" id="taken_material_id" name="material_id">
                
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-check"></i> Confirm Taken
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('markTakenModal')">
                    Cancel
                </button>
            </form>
        </div>
    </div>

    <!-- Edit Material Modal -->
    <div class="modal" id="editMaterialModal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('editMaterialModal')">&times;</button>
            <h2>Edit Material</h2>
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

    <!-- Scan Result Modal -->
    <div class="modal" id="scanResultModal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('scanResultModal')">&times;</button>
            <div class="pass-header">
                <h2 id="modalTitle">Gate Pass Details</h2>
                <span id="scanTypeBadge" class="badge badge-warning">VEHICLE EXIT</span>
            </div>
            
            <div id="passDetails" style="display: none;">
                <div class="info-box">
                    <strong>Pass Number:</strong> <span id="passNumber">-</span>
                </div>
                <div class="info-box">
                    <strong>Vehicle:</strong> <span id="vehiclePlate">-</span> | <span id="vehicleModel">-</span>
                </div>
                <div class="info-box">
                    <strong>Driver:</strong> <span id="driverName">-</span> (<span id="driverRank">-</span>)
                </div>
                <div class="info-box">
                    <strong>Officer:</strong> <span id="officerName">-</span> <span id="officerRank">-</span>
                </div>
                <div class="info-box">
                    <strong>Destination:</strong> <span id="destination">-</span>
                </div>
                <div class="info-box">
                    <strong>Mission Description:</strong> <span id="missionDescription">-</span>
                </div>
                <div class="info-box">
                    <strong>Time Out:</strong> <span id="timeOut">-</span>
                </div>
                <div class="info-box">
                    <strong>Expected Return:</strong> <span id="expectedReturn">-</span>
                </div>
                
                <div id="scanWarning" class="alert alert-warning" style="display: none; margin-top: 1rem;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span id="warningText">Scan restriction warning</span>
                </div>
                
                <div class="scan-confirm-actions">
                    <button id="confirmScanBtn" class="btn btn-success" onclick="confirmScan()">
                        <i class="fas fa-check-circle"></i> Confirm Scan
                    </button>
                    <button class="btn btn-secondary" onclick="closeModal('scanResultModal')">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </div>
            
            <div id="scanError" style="display: none; text-align: center; padding: 2rem;">
                <i class="fas fa-exclamation-triangle fa-3x" style="color: #e74c3c; margin-bottom: 1rem;"></i>
                <h3>Scan Error</h3>
                <p id="errorText">Error message will appear here</p>
                <button class="btn btn-primary" onclick="closeModal('scanResultModal')" style="margin-top: 1rem;">
                    <i class="fas fa-redo"></i> Try Again
                </button>
            </div>
        </div>
    </div>

    <!-- Success Message Modal -->
    <div id="successModal" class="success-message" style="display: none;">
        <div class="success-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <h3>Success!</h3>
        <p id="successMessageText">Operation completed successfully.</p>
        <button class="btn btn-success" onclick="closeSuccessModal()">
            <i class="fas fa-check"></i> OK
        </button>
    </div>

<script>
    let html5QrCode = null;
    let isScanning = false;
    let currentScanType = 'out';
    let currentScanData = null;
    let hasCameraPermission = false;

    document.addEventListener('DOMContentLoaded', function() {
        // Navigation functionality
        const navLinks = document.querySelectorAll('.nav-link');
        const pageContents = document.querySelectorAll('.page-content');
        
        navLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const pageId = this.getAttribute('data-page');
                
                // Remove active class from all nav links
                navLinks.forEach(nav => nav.classList.remove('active'));
                // Add active class to clicked nav link
                this.classList.add('active');
                
                // Hide all page contents
                pageContents.forEach(page => page.classList.remove('active'));
                // Show selected page content
                document.getElementById(pageId).classList.add('active');
                
                // Stop scanner when leaving scan page
                if (pageId !== 'gate-scan') {
                    stopScanner();
                }
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
        
        // Auto-close alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (!alert.classList.contains('scan-alert')) {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }
            });
        }, 5000);
        
        // Initialize Charts
        initializeCharts();
        
        // Initialize dropdown menus
        initializeDropdowns();
        
        // Initialize scan type
        setScanType('out');
        
        // Check camera permissions when on scan page
        if (document.getElementById('gate-scan')) {
            checkCameraPermissions();
        }

        // Setup mobile manual entry
        setupMobileManualEntry();
        
        // Show browser-specific tips
        showBrowserSpecificTips();
    });

    function initializeDropdowns() {
        const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
        
        dropdownToggles.forEach(toggle => {
            const dropdownMenu = toggle.querySelector('.dropdown-menu');
            const mainLink = toggle.querySelector('.dropdown-main');
            
            mainLink.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Close other dropdowns
                dropdownToggles.forEach(otherToggle => {
                    if (otherToggle !== toggle) {
                        otherToggle.classList.remove('active');
                        otherToggle.querySelector('.dropdown-menu').classList.remove('show');
                    }
                });
                
                // Toggle current dropdown
                toggle.classList.toggle('active');
                dropdownMenu.classList.toggle('show');
            });
        });
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function() {
            dropdownToggles.forEach(toggle => {
                toggle.classList.remove('active');
                toggle.querySelector('.dropdown-menu').classList.remove('show');
            });
        });
    }

    function initializeCharts() {
        // Materials Distribution Chart
        const statusCtx = document.getElementById('statusChart');
        if (statusCtx) {
            const statusChart = new Chart(statusCtx, {
                type: 'bar',
                data: {
                    labels: ['Total Materials', 'My Materials', 'Today\'s Materials', 'Outside Pending', 'Taken Outside'],
                    datasets: [{
                        label: 'Materials Count',
                        data: [
                            <?php echo $gatekeeper_stats['total_materials']; ?>,
                            <?php echo $gatekeeper_stats['my_materials']; ?>,
                            <?php echo $gatekeeper_stats['today_materials']; ?>,
                            <?php echo $gatekeeper_stats['outside_pending']; ?>,
                            <?php echo $gatekeeper_stats['outside_taken']; ?>
                        ],
                        backgroundColor: [
                            'rgba(231, 76, 60, 0.8)',
                            'rgba(52, 152, 219, 0.8)',
                            'rgba(46, 204, 113, 0.8)',
                            'rgba(243, 156, 18, 0.8)',
                            'rgba(155, 89, 182, 0.8)'
                        ],
                        borderColor: [
                            'rgba(231, 76, 60, 1)',
                            'rgba(52, 152, 219, 1)',
                            'rgba(46, 204, 113, 1)',
                            'rgba(243, 156, 18, 1)',
                            'rgba(155, 89, 182, 1)'
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
        }
        
        // Vehicle Status Chart
        const vehicleCtx = document.getElementById('vehicleChart');
        if (vehicleCtx) {
            const vehicleChart = new Chart(vehicleCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Available', 'Outside', 'In Use'],
                    datasets: [{
                        data: [
                            <?php echo $vehicle_stats['available_vehicles']; ?>,
                            <?php echo $vehicle_stats['outside_vehicles']; ?>,
                            <?php echo $vehicle_stats['total_vehicles'] - $vehicle_stats['available_vehicles'] - $vehicle_stats['outside_vehicles']; ?>
                        ],
                        backgroundColor: [
                            'rgba(46, 204, 113, 0.8)',
                            'rgba(243, 156, 18, 0.8)',
                            'rgba(52, 152, 219, 0.8)'
                        ],
                        borderColor: [
                            'rgba(46, 204, 113, 1)',
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
    }

    // Enhanced camera initialization for mobile
    async function initializeMobileCamera() {
        try {
            // Check if we're on mobile
            const isMobile = isMobileDevice();
            
            // Request camera permissions with mobile-specific constraints
            const constraints = {
                video: {
                    facingMode: isMobile ? "environment" : "user",
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                }
            };

            // Test camera access
            const stream = await navigator.mediaDevices.getUserMedia(constraints);
            hasCameraPermission = true;
            
            // Stop the test stream
            stream.getTracks().forEach(track => track.stop());
            
            return true;
        } catch (error) {
            console.error('Camera initialization failed:', error);
            return false;
        }
    }

    // Check camera permissions
    async function checkCameraPermissions() {
        try {
            // Test camera access
            const stream = await navigator.mediaDevices.getUserMedia({ video: true });
            hasCameraPermission = true;
            stream.getTracks().forEach(track => track.stop());
            
            // Initialize camera selector if permission is granted
            initializeCameraSelector();
            
        } catch (error) {
            console.log('Camera permission not granted:', error);
            hasCameraPermission = false;
            showMobilePermissionInstructions();
        }
    }

    // Show mobile permission instructions
    function showMobilePermissionInstructions() {
        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
        const isAndroid = /Android/.test(navigator.userAgent);
        
        let instructions = '';
        
        if (isIOS) {
            instructions = `
                <div class="alert alert-warning">
                    <h4><i class="fas fa-mobile-alt"></i> iOS Camera Setup</h4>
                    <ol>
                        <li>Open <strong>Settings</strong> app</li>
                        <li>Scroll down and tap <strong>Safari</strong> (or your browser)</li>
                        <li>Tap <strong>Camera</strong></li>
                        <li>Select <strong>Allow</strong> or <strong>Ask</strong></li>
                        <li>Return to this page and try again</li>
                    </ol>
                    <p><strong>Alternative:</strong> Use the manual entry option below</p>
                </div>
            `;
        } else if (isAndroid) {
            instructions = `
                <div class="alert alert-warning">
                    <h4><i class="fas fa-mobile-alt"></i> Android Camera Setup</h4>
                    <ol>
                        <li>Tap the <strong>Settings</strong> icon in your browser</li>
                        <li>Go to <strong>Site Settings</strong> or <strong>Permissions</strong></li>
                        <li>Enable <strong>Camera</strong> access</li>
                        <li>Refresh this page and try again</li>
                    </ol>
                    <p><strong>Tip:</strong> Use Chrome browser for best results</p>
                </div>
            `;
        } else {
            instructions = `
                <div class="alert alert-warning">
                    <h4><i class="fas fa-camera"></i> Camera Access Required</h4>
                    <p>Please allow camera access to use the QR scanner.</p>
                    <button class="btn btn-primary" onclick="requestCameraPermission()">
                        <i class="fas fa-camera"></i> Allow Camera Access
                    </button>
                </div>
            `;
        }
        
        const mobileInstructions = document.getElementById('mobileInstructions');
        if (mobileInstructions) {
            mobileInstructions.innerHTML = instructions;
            mobileInstructions.style.display = 'block';
        }
    }

    // Request camera permission
    async function requestCameraPermission() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: true });
            hasCameraPermission = true;
            stream.getTracks().forEach(track => track.stop());
            
            // Hide permission instructions
            const mobileInstructions = document.getElementById('mobileInstructions');
            if (mobileInstructions) {
                mobileInstructions.style.display = 'none';
            }
            
            // Initialize camera selector
            initializeCameraSelector();
            
            showMessage('Camera access granted! You can now start the scanner.', 'success');
            
        } catch (error) {
            console.error('Failed to get camera permission:', error);
            showCameraError('Failed to get camera access. Please check your browser settings and allow camera permissions.');
        }
    }

    // Initialize Camera Selector
    async function initializeCameraSelector() {
        try {
            const cameraSelect = document.getElementById('cameraSelect');
            
            if (!cameraSelect) return;
            
            const cameras = await Html5Qrcode.getCameras();
            
            if (cameras && cameras.length > 0) {
                cameraSelect.innerHTML = '';
                
                // Add environment camera (back camera) first for mobile
                const backCamera = cameras.find(cam => 
                    cam.label.toLowerCase().includes('back') || 
                    cam.label.toLowerCase().includes('rear') ||
                    cam.label.toLowerCase().includes('environment')
                );
                
                if (backCamera) {
                    cameraSelect.innerHTML += `<option value="${backCamera.id}">📷 Back Camera</option>`;
                }
                
                // Add user camera (front camera)
                const frontCamera = cameras.find(cam => 
                    cam.label.toLowerCase().includes('front') || 
                    cam.label.toLowerCase().includes('user') ||
                    cam.label.toLowerCase().includes('selfie')
                );
                
                if (frontCamera) {
                    cameraSelect.innerHTML += `<option value="${frontCamera.id}">🤳 Front Camera</option>`;
                }
                
                // Add other cameras
                cameras.forEach(camera => {
                    if (camera !== backCamera && camera !== frontCamera) {
                        cameraSelect.innerHTML += `<option value="${camera.id}">📹 ${camera.label}</option>`;
                    }
                });
                
                // Auto-select back camera for mobile devices
                if (backCamera && isMobileDevice()) {
                    cameraSelect.value = backCamera.id;
                } else if (cameras.length > 0) {
                    cameraSelect.value = cameras[0].id;
                }
            } else {
                cameraSelect.innerHTML = '<option value="">No cameras found</option>';
            }
        } catch (error) {
            console.error('Error initializing camera selector:', error);
        }
    }

    // Enhanced startScanner function for mobile
    async function startScanner() {
        console.log('Starting mobile camera scanner...');
        
        // Initialize mobile camera first
        const cameraReady = await initializeMobileCamera();
        if (!cameraReady) {
            showMobilePermissionInstructions();
            return;
        }

        try {
            document.getElementById('cameraError').style.display = 'none';
            const mobileInstructions = document.getElementById('mobileInstructions');
            if (mobileInstructions) {
                mobileInstructions.style.display = 'none';
            }
            
            if (html5QrCode && isScanning) {
                await stopScanner();
            }

            html5QrCode = new Html5Qrcode("qr-reader");
            
            // Mobile-optimized configuration
            const config = {
                fps: isMobileDevice() ? 5 : 10, // Lower FPS for mobile
                qrbox: isMobileDevice() ? { width: 200, height: 200 } : { width: 250, height: 250 },
                aspectRatio: 1.0,
                focusMode: "continuous"
            };

            // Use environment camera for mobile (back camera)
            const cameraConfig = isMobileDevice() ? 
                { facingMode: "environment" } : 
                (document.getElementById('cameraSelect').value || { facingMode: "user" });

            await html5QrCode.start(
                cameraConfig,
                config,
                (decodedText, decodedResult) => {
                    // Success callback
                    console.log('QR Code scanned:', decodedText);
                    onScanSuccess(decodedText);
                },
                (errorMessage) => {
                    // Failure callback - ignore common errors
                    if (errorMessage && !errorMessage.includes('NotFoundException')) {
                        console.log('Scan error:', errorMessage);
                    }
                }
            );

            // Update UI
            isScanning = true;
            document.getElementById('startScannerBtn').style.display = 'none';
            document.getElementById('stopScannerBtn').style.display = 'inline-block';
            
            showMessage('Camera started successfully! Point camera at QR code.', 'success');
            
        } catch (error) {
            console.error('Mobile camera start error:', error);
            handleMobileCameraError(error);
        }
    }

    // Enhanced error handling for mobile
    function handleMobileCameraError(error) {
        let errorMessage = 'Camera error: ' + error.message;
        
        if (error.name === 'NotAllowedError') {
            errorMessage = 'Camera access denied. Please allow camera permissions in your browser settings.';
            showMobilePermissionInstructions();
        } else if (error.name === 'NotFoundError') {
            errorMessage = 'No camera found on this device.';
        } else if (error.name === 'NotSupportedError') {
            errorMessage = 'Camera not supported. Try Chrome or Safari on mobile.';
        } else if (error.name === 'NotReadableError') {
            errorMessage = 'Camera is busy. Close other apps using camera.';
        } else if (error.name === 'OverconstrainedError') {
            errorMessage = 'Camera constraints not supported. Trying alternative camera...';
            // Try with simpler constraints
            setTimeout(() => startScannerWithSimpleConstraints(), 1000);
        }
        
        showCameraError(errorMessage);
    }

    // Alternative camera start with simpler constraints
    async function startScannerWithSimpleConstraints() {
        try {
            if (html5QrCode && isScanning) {
                await stopScanner();
            }

            html5QrCode = new Html5Qrcode("qr-reader");
            
            // Simple configuration for problematic devices
            const simpleConfig = {
                fps: 5,
                qrbox: { width: 150, height: 150 }
            };

            await html5QrCode.start(
                { facingMode: "environment" },
                simpleConfig,
                (decodedText, decodedResult) => {
                    onScanSuccess(decodedText);
                },
                () => {} // Empty error callback
            );

            isScanning = true;
            document.getElementById('startScannerBtn').style.display = 'none';
            document.getElementById('stopScannerBtn').style.display = 'inline-block';
            
        } catch (error) {
            showCameraError('Camera not available. Use manual entry instead.');
        }
    }

    // Stop Camera
    async function stopScanner() {
        if (html5QrCode && isScanning) {
            try {
                await html5QrCode.stop();
                html5QrCode.clear();
                html5QrCode = null;
                isScanning = false;
                
                document.getElementById('startScannerBtn').style.display = 'inline-block';
                document.getElementById('stopScannerBtn').style.display = 'none';
                
                showMessage('Camera stopped.', 'info');
            } catch (error) {
                console.error('Error stopping camera:', error);
            }
        }
    }

    // Device detection functions
    function isMobileDevice() {
        return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    }

    function isIOS() {
        return /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
    }

    function isAndroid() {
        return /Android/.test(navigator.userAgent);
    }

    // Show camera error
    function showCameraError(message) {
        const cameraError = document.getElementById('cameraError');
        if (cameraError) {
            cameraError.innerHTML = `
                <h4><i class="fas fa-video-slash"></i> Camera Access Required</h4>
                <p>${message}</p>
                <div class="scan-controls">
                    <button class="btn btn-primary" onclick="retryCamera()">
                        <i class="fas fa-redo"></i> Retry Camera
                    </button>
                    <button class="btn btn-info" onclick="showCameraSettings()">
                        <i class="fas fa-cog"></i> Camera Settings
                    </button>
                    <button class="btn btn-warning" onclick="testMobileCamera()">
                        <i class="fas fa-bug"></i> Test Camera
                    </button>
                </div>
            `;
            cameraError.style.display = 'block';
        }
    }

    // Test mobile camera
    async function testMobileCamera() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ 
                video: { facingMode: "environment" } 
            });
            alert('✅ Camera works! You should see video feed.');
            stream.getTracks().forEach(track => track.stop());
        } catch (error) {
            alert('❌ Camera error: ' + error.message);
        }
    }

    // Retry camera
    function retryCamera() {
        const cameraError = document.getElementById('cameraError');
        if (cameraError) {
            cameraError.style.display = 'none';
        }
        startScanner();
    }

    function showCameraSettings() {
        if (isIOS()) {
            alert('Go to Settings > Safari > Camera and enable access');
        } else if (isAndroid()) {
            alert('Tap the settings icon in your browser and enable camera permissions');
        } else {
            alert('Check your browser settings and allow camera access for this site');
        }
    }

    // QR Scanner Functions
    function setScanType(type) {
        currentScanType = type;
        document.querySelectorAll('.scan-type-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        // Find and activate the clicked button
        const activeBtn = document.querySelector(`.scan-type-btn[data-type="${type}"]`);
        if (activeBtn) {
            activeBtn.classList.add('active');
        }
        
        const scanTypeSelect = document.getElementById('scanType');
        if (scanTypeSelect) {
            scanTypeSelect.value = type;
        }
        updateScanInstructions(type);
    }

    function updateScanInstructions(type) {
        const instructions = document.getElementById('scanInstructions');
        if (!instructions) return;

        if (type === 'out') {
            instructions.innerHTML = `
                <div class="alert alert-info">
                    <i class="fas fa-car-side"></i>
                    <strong>Scan Vehicle Exit:</strong> Scan QR code when vehicle leaves the institution. The gate pass can only be scanned OUT once.
                </div>
            `;
        } else {
            instructions.innerHTML = `
                <div class="alert alert-success">
                    <i class="fas fa-home"></i>
                    <strong>Scan Vehicle Return:</strong> Scan QR code when vehicle returns to institution. The gate pass can only be scanned IN once after being scanned OUT.
                </div>
            `;
        }
    }

    // Scan Success Handler - FOR CAMERA SCANNING
    function onScanSuccess(decodedText) {
        console.log('QR scan successful:', decodedText);
        stopScanner();
        
        try {
            const qrParts = decodedText.split(':');
            
            if (qrParts.length >= 4 && qrParts[0] === 'GATEPASS') {
                const passNumber = qrParts[1];
                const passId = qrParts[2];
                const vehicleId = qrParts[3];
                
                showMessage('Valid gate pass QR code detected! Processing...', 'success');
                
                // Process the QR code scan
                processQRCodeScan(passId, passNumber, vehicleId);
            } else {
                showScanError('Invalid QR code format. Expected: GATEPASS:PASS_NUMBER:PASS_ID:VEHICLE_ID');
            }
        } catch (error) {
            console.error('Error processing QR code:', error);
            showScanError('Error reading QR code: ' + error.message);
        }
    }

    // Process QR Code Scan (Camera)
    function processQRCodeScan(passId, passNumber, vehicleId) {
        try {
            // Create a hidden form and submit it directly
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = ''; // Submit to same page
            form.style.display = 'none';
            
            // Add scan type
            const scanTypeInput = document.createElement('input');
            scanTypeInput.type = 'hidden';
            scanTypeInput.name = 'scan_type';
            scanTypeInput.value = currentScanType;
            form.appendChild(scanTypeInput);
            
            // Add QR data
            const qrDataInput = document.createElement('input');
            qrDataInput.type = 'hidden';
            qrDataInput.name = 'qr_data';
            qrDataInput.value = `GATEPASS:${passNumber}:${passId}:${vehicleId}`;
            form.appendChild(qrDataInput);
            
            // Add action identifier
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'process_manual_qr';
            actionInput.value = '1';
            form.appendChild(actionInput);
            
            document.body.appendChild(form);
            
            showMessage('Processing gate pass...', 'info');
            
            // Submit the form - this will reload the page and process the scan
            setTimeout(() => {
                form.submit();
            }, 1000);
            
        } catch (error) {
            console.error('Error processing QR code:', error);
            showScanError('Error processing scan. Please try again.');
            setTimeout(() => startScanner(), 3000);
        }
    }

    // MANUAL ENTRY FUNCTION - For when camera doesn't work
    function processManualEntry() {
        const gatePassInput = document.getElementById('gatePassNumber');
        const scanTypeSelect = document.getElementById('scanType');
        
        if (!gatePassInput || !scanTypeSelect) {
            showMessage('Error: Form elements not found', 'error');
            return;
        }
        
        const gatePassNumber = gatePassInput.value.trim();
        const scanType = scanTypeSelect.value;
        
        if (!gatePassNumber) {
            showMessage('Please enter a gate pass number', 'error');
            return;
        }
        
        if (!gatePassNumber.startsWith('GP')) {
            showMessage('Invalid gate pass format. Should start with GP', 'error');
            return;
        }
        
        showMessage('Processing manual entry...', 'info');
        
        // Create a hidden form and submit it
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '';
        form.style.display = 'none';
        
        // Add scan type
        const scanTypeInput = document.createElement('input');
        scanTypeInput.type = 'hidden';
        scanTypeInput.name = 'scan_type';
        scanTypeInput.value = scanType;
        form.appendChild(scanTypeInput);
        
        // Add QR data (using the gate pass number directly)
        const qrDataInput = document.createElement('input');
        qrDataInput.type = 'hidden';
        qrDataInput.name = 'qr_data';
        qrDataInput.value = gatePassNumber;
        form.appendChild(qrDataInput);
        
        // Add action identifier
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'process_manual_qr';
        actionInput.value = '1';
        form.appendChild(actionInput);
        
        document.body.appendChild(form);
        
        // Submit the form
        form.submit();
    }

    // Mobile-optimized manual entry setup
    function setupMobileManualEntry() {
        const gatePassInput = document.getElementById('gatePassNumber');
        if (gatePassInput && isMobileDevice()) {
            // Improve mobile UX
            gatePassInput.setAttribute('inputmode', 'numeric');
            gatePassInput.setAttribute('pattern', '[0-9]*');
            gatePassInput.style.fontSize = '16px'; // Prevents zoom on iOS
            
            // Add paste event for mobile
            gatePassInput.addEventListener('paste', function(e) {
                setTimeout(() => {
                    const value = this.value.trim();
                    if (value.startsWith('GP')) {
                        processManualEntry();
                    }
                }, 100);
            });
        }
    }

    // Fill example for manual entry
    function fillExample() {
        const gatePassInput = document.getElementById('gatePassNumber');
        if (gatePassInput) {
            gatePassInput.value = 'GP20251114102908845';
        }
        showMessage('Example filled. Now click "Process Gate Pass"', 'info');
    }

    function showScanError(message) {
        const modal = document.getElementById('scanResultModal');
        const passDetailsDiv = document.getElementById('passDetails');
        const scanErrorDiv = document.getElementById('scanError');
        const errorText = document.getElementById('errorText');
        
        if (!modal || !passDetailsDiv || !scanErrorDiv || !errorText) return;
        
        // Hide details, show error
        passDetailsDiv.style.display = 'none';
        scanErrorDiv.style.display = 'block';
        errorText.textContent = message;
        
        // Show modal
        modal.style.display = 'flex';
        
        // Restart scanner after 3 seconds
        setTimeout(() => {
            closeModal('scanResultModal');
            startScanner();
        }, 3000);
    }

    function formatDateTime(dateTimeString) {
        if (!dateTimeString) return 'N/A';
        const date = new Date(dateTimeString);
        return date.toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function showMessage(message, type) {
        // Remove existing messages
        const existingAlerts = document.querySelectorAll('.scan-alert');
        existingAlerts.forEach(alert => alert.remove());

        // Create new alert
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} scan-alert`;
        alert.innerHTML = `
            <i class="fas fa-${getIconForType(type)}"></i>
            ${message}
        `;

        // Insert after scan container
        const scanContainer = document.querySelector('.scan-container');
        if (scanContainer) {
            scanContainer.parentNode.insertBefore(alert, scanContainer.nextSibling);
        }

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 5000);
    }

    function showSuccessMessage(message) {
        const successModal = document.getElementById('successModal');
        const successMessageText = document.getElementById('successMessageText');
        
        if (successModal && successMessageText) {
            successMessageText.innerHTML = message;
            successModal.style.display = 'block';
        }
    }

    function closeSuccessModal() {
        const successModal = document.getElementById('successModal');
        if (successModal) {
            successModal.style.display = 'none';
        }
    }

    function getIconForType(type) {
        switch(type) {
            case 'success': return 'check-circle';
            case 'error': return 'exclamation-circle';
            case 'warning': return 'exclamation-triangle';
            case 'info': return 'info-circle';
            default: return 'info-circle';
        }
    }

    // Browser detection
    function getBrowserInfo() {
        const ua = navigator.userAgent;
        let browser = 'unknown';
        
        if (ua.includes('Chrome')) browser = 'chrome';
        else if (ua.includes('Safari')) browser = 'safari';
        else if (ua.includes('Firefox')) browser = 'firefox';
        else if (ua.includes('Edge')) browser = 'edge';
        
        return browser;
    }

    function showBrowserSpecificTips() {
        const browser = getBrowserInfo();
        const isMobile = isMobileDevice();
        
        if (isMobile) {
            let tips = '';
            
            switch(browser) {
                case 'safari':
                    tips = '<p>📱 <strong>Safari Tip:</strong> Make sure "Camera" is enabled in Safari Settings</p>';
                    break;
                case 'chrome':
                    tips = '<p>📱 <strong>Chrome Tip:</strong> Tap the camera icon in address bar to allow access</p>';
                    break;
                case 'firefox':
                    tips = '<p>📱 <strong>Firefox Tip:</strong> Go to Site Settings > Camera to enable access</p>';
                    break;
            }
            
            const tipsDiv = document.getElementById('browserTips');
            if (tipsDiv) {
                tipsDiv.innerHTML = tips;
            }
        }
    }

    // Vehicle Reports Filtering
    function filterVehicleReports() {
        const filterValue = document.getElementById('statusFilter').value;
        const rows = document.querySelectorAll('#vehicleReportsTable tbody tr');
        
        rows.forEach(row => {
            if (filterValue === 'all') {
                row.style.display = '';
            } else {
                const rowStatus = row.getAttribute('data-status');
                if (rowStatus === filterValue) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        });
        
        // Update statistics based on filter
        updateVehicleStatistics(filterValue);
    }

    function loadAllVehicles() {
        document.getElementById('statusFilter').value = 'all';
        filterVehicleReports();
        
        // Show loading state
        showMessage('Loading all vehicle records...', 'info');
    }

    function updateVehicleStatistics(filter) {
        const stats = {
            'all': 'All Vehicles',
            'returned': 'Completed Trips',
            'outside': 'Vehicles Outside',
            'approved': 'Approved Passes'
        };
        
        const descriptionElement = document.querySelector('#vehicle-reports .page-description');
        if (descriptionElement) {
            descriptionElement.textContent = `Viewing: ${stats[filter] || 'All Vehicles'}`;
        }
    }

    // Modal functions for outside materials management
    function showSendOutsideForm() {
        document.getElementById('sendOutsideModal').style.display = 'flex';
    }

    function sendOutside(materialId) {
        document.getElementById('material_id').value = materialId;
        showSendOutsideForm();
    }

    function markAsTaken(materialId) {
        document.getElementById('taken_material_id').value = materialId;
        document.getElementById('markTakenModal').style.display = 'flex';
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
        }
        
        // Restart scanner if we're on the scan page
        if (modalId === 'scanResultModal' && document.getElementById('gate-scan').classList.contains('active')) {
            setTimeout(() => startScanner(), 1000);
        }
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
        
        // Stop scanner if leaving scan page
        if (pageId !== 'gate-scan') {
            stopScanner();
        }
        
        // Initialize camera when switching to scan page
        if (pageId === 'gate-scan') {
            checkCameraPermissions();
        }
    }
    
    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        sidebar.classList.toggle('expanded');
    }

    function editMaterial(materialId) {
        alert('Edit functionality would be implemented here for material ID: ' + materialId);
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (event.target === modal) {
                modal.style.display = 'none';
                
                // Restart scanner if we're on the scan page
                if (modal.id === 'scanResultModal' && document.getElementById('gate-scan').classList.contains('active')) {
                    setTimeout(() => startScanner(), 1000);
                }
            }
        });
        
        // Close success modal when clicking outside
        const successModal = document.getElementById('successModal');
        if (event.target === successModal) {
            closeSuccessModal();
        }
    }

    // Initialize mobile-specific settings
    if (isMobileDevice()) {
        document.addEventListener('DOMContentLoaded', function() {
            // Add mobile-specific instructions
            const scanContainer = document.querySelector('.scan-container');
            if (scanContainer) {
                const mobileTips = document.createElement('div');
                mobileTips.className = 'alert alert-info';
                mobileTips.innerHTML = `
                    <i class="fas fa-mobile-alt"></i>
                    <strong>Mobile Tips:</strong> Hold your phone steady and ensure good lighting for better QR code scanning.
                `;
                scanContainer.insertBefore(mobileTips, scanContainer.querySelector('#qr-reader'));
            }
        });
    }
</script>
</body>
</html>