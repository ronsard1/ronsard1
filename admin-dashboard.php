
<?php
/**
 * IO Dashboard - Complete Management System
 * 
 * This file handles all IO operations including:
 * - Material management (checkout, return, send outside)
 * - Vehicle and personnel management
 * - Gate pass creation and tracking
 * - QR code scanning for vehicle movements
 * - Notification system
 * 
 * @category Military Institution
 * @package IO Dashboard
 * @author IO Department
 * @version 1.0
 */

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

// Include required libraries
require_once 'phpqrcode/qrlib.php';  // QR code generation
require_once 'tcpdf/tcpdf.php';      // PDF generation for gate passes

// Get unread notification count for header
try {
    $notification_count = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE is_read = 0")->fetch(PDO::FETCH_ASSOC);
    $notification_count = $notification_count ? $notification_count['count'] : 0;
} catch (PDOException $e) {
    $notification_count = 0;
    error_log("Notification count error: " . $e->getMessage());
}

// Handle gate pass download/view FIRST - before any output
// This ensures clean PDF output without HTML interference
if (isset($_GET['download_gate_pass'])) {
    $pass_id = $_GET['pass_id'];
    $gate_pass_pdf_path = generateGatePassPDF($pass_id, $conn);
    
    if ($gate_pass_pdf_path && file_exists($gate_pass_pdf_path)) {
        // Set headers for PDF download
        header('Content-Description: File Transfer');
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($gate_pass_pdf_path) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($gate_pass_pdf_path));
        readfile($gate_pass_pdf_path);
        exit;
    } else {
        // Store error in session to display after redirect
        $_SESSION['error'] = "Gate pass PDF not found! Please regenerate the gate pass.";
        header("Location: " . str_replace(['?download_gate_pass=1&pass_id=' . $pass_id, '&download_gate_pass=1&pass_id=' . $pass_id], '', $_SERVER['REQUEST_URI']));
        exit;
    }
}

// Handle gate pass viewing
if (isset($_GET['view_gate_pass'])) {
    $pass_id = $_GET['pass_id'];
    $gate_pass_pdf_path = generateGatePassPDF($pass_id, $conn);
    
    if ($gate_pass_pdf_path && file_exists($gate_pass_pdf_path)) {
        // Set headers for PDF viewing in browser
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($gate_pass_pdf_path) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($gate_pass_pdf_path));
        readfile($gate_pass_pdf_path);
        exit;
    } else {
        $_SESSION['error'] = "Gate pass PDF not found! Please regenerate the gate pass.";
        header("Location: " . str_replace(['?view_gate_pass=1&pass_id=' . $pass_id, '&view_gate_pass=1&pass_id=' . $pass_id], '', $_SERVER['REQUEST_URI']));
        exit;
    }
}

// Check for session errors from redirects
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

// Handle POST requests for various operations
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Quick checkout - change status to checked_out
    if (isset($_POST['quick_checkout'])) {
        try {
            $material_id = $_POST['material_id'];
            $cadet_telephone = $_POST['cadet_telephone'];
            
            // First get material details for notification
            $material_stmt = $conn->prepare("SELECT material_code, name FROM materials WHERE material_id = ?");
            $material_stmt->execute([$material_id]);
            $material = $material_stmt->fetch(PDO::FETCH_ASSOC);
            
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
                    
                    // Create notification
                    $notification_message = "📦 Material Checked Out: " . $material['material_code'] . " assigned to " . $cadet['fname'] . " " . $cadet['lname'];
                    createNotification($notification_message, 'material_checkout', $conn);
                    
                    $_SESSION['success'] = "Material checked out successfully to " . $cadet['fname'] . " " . $cadet['lname'] . "!";
                    header("Location: " . $_SERVER['REQUEST_URI']);
                    exit;
                }
            } else {
                $_SESSION['error'] = "No cadet found with this telephone number!";
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit;
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error checking out material: " . $e->getMessage();
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
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
                
                // Create notification
                $notification_message = "📤 Material Sent Outside: " . $material_code . " sent to " . $sent_to_person . " for " . $reason;
                createNotification($notification_message, 'material_outside', $conn);
                
                $_SESSION['success'] = "Material sent outside institution successfully!";
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit;
            } else {
                $_SESSION['error'] = "No cadet found with this telephone number!";
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit;
            }
            
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error sending material outside: " . $e->getMessage();
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        }
    }
    
    // Handle mark as taken for materials sent outside
    if (isset($_POST['mark_taken'])) {
        try {
            $material_id = $_POST['material_id'];
            
            $check_sql = "SELECT material_id, status, name, material_code FROM materials WHERE material_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->execute([$material_id]);
            $material = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$material) {
                $_SESSION['error'] = "Error: Material not found!";
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit;
            } elseif ($material['status'] == 'taken_outside') {
                $_SESSION['error'] = "Info: This material was already confirmed as taken.";
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit;
            } elseif ($material['status'] != 'outside_institution') {
                $_SESSION['error'] = "Error: This material cannot be confirmed as taken. Its current status is '" . $material['status'] . "'.";
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit;
            } else {
                $check_column = $conn->query("SHOW COLUMNS FROM materials LIKE 'taken_date'")->fetch();
                
                if ($check_column) {
                    $update_sql = "UPDATE materials SET status = 'taken_outside', taken_date = NOW() WHERE material_id = ?";
                } else {
                    $update_sql = "UPDATE materials SET status = 'taken_outside' WHERE material_id = ?";
                }
                
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->execute([$material_id]);
                
                if ($update_stmt->rowCount() > 0) {
                    // Create notification
                    $notification_message = "✅ Material Taken Outside: " . $material['material_code'] . " confirmed as taken";
                    createNotification($notification_message, 'material_taken', $conn);
                    
                    $_SESSION['success'] = "Material successfully marked as taken outside!";
                    header("Location: " . $_SERVER['REQUEST_URI']);
                    exit;
                } else {
                    $_SESSION['error'] = "No rows were updated. The material status may not have changed.";
                    header("Location: " . $_SERVER['REQUEST_URI']);
                    exit;
                }
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Database error: " . $e->getMessage();
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        }
    }
    
    // Handle material return to stock
    if (isset($_POST['return_material'])) {
        try {
            $material_id = $_POST['material_id'];
            
            // Get material details for notification
            $material = $conn->query("SELECT material_code, name FROM materials WHERE material_id = $material_id")->fetch(PDO::FETCH_ASSOC);
            
            // Update material status back to available and clear cadet info
            $update_material = "UPDATE materials SET status = 'available', cadet_id = NULL, telephone = NULL, sent_to_person = NULL, sent_to_contact = NULL, reason = NULL, sent_date = NULL, external_notes = NULL, checkout_date = NULL WHERE material_id = ?";
            $stmt = $conn->prepare($update_material);
            $stmt->execute([$material_id]);
            
            // Update checkout record
            $update_checkout = "UPDATE material_checkouts SET status = 'returned', return_date = CURRENT_TIMESTAMP WHERE material_id = ? AND status = 'active'";
            $stmt2 = $conn->prepare($update_checkout);
            $stmt2->execute([$material_id]);
            
            // Create notification
            $notification_message = "🔄 Material Returned: " . $material['material_code'] . " returned to stock";
            createNotification($notification_message, 'material_return', $conn);
            
            $_SESSION['success'] = "Material returned successfully!";
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error returning material: " . $e->getMessage();
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        }
    }
    
    // Handle new worker registration
    if (isset($_POST['add_worker'])) {
        try {
            $fname = $_POST['fname'];
            $lname = $_POST['lname'];
            $telephone = $_POST['telephone'];
            $rank = $_POST['rank'];
            $department = $_POST['department'];
            $soldier_role = $_POST['soldier_role'];
            $specific_duty = $_POST['specific_duty'];
            $email = $_POST['email'];
            
            $insert_query = "INSERT INTO workers (fname, lname, telephone, rank, department, soldier_role, specific_duty, email) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_query);
            $stmt->execute([$fname, $lname, $telephone, $rank, $department, $soldier_role, $specific_duty, $email]);
            
            // Create notification
            $notification_message = "👤 New Soldier Added: " . $fname . " " . $lname . " (" . $rank . ") to " . $department . " department";
            createNotification($notification_message, 'personnel_added', $conn);
            
            $_SESSION['success'] = "Soldier added successfully to " . ucfirst($department) . " department as " . ucfirst($soldier_role) . "!";
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error adding soldier: " . $e->getMessage();
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        }
    }
    
    // Handle vehicle registration
    if (isset($_POST['add_vehicle'])) {
        try {
            $plate_number = $_POST['plate_number'];
            $vehicle_type = $_POST['vehicle_type'];
            $model = $_POST['model'];
            $color = $_POST['color'];
            $capacity = $_POST['capacity'];
            $department = $_POST['department'];
            
            $insert_query = "INSERT INTO vehicles (plate_number, vehicle_type, model, color, capacity, department) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_query);
            $stmt->execute([$plate_number, $vehicle_type, $model, $color, $capacity, $department]);
            
            // Create notification
            $notification_message = "🚗 New Vehicle Added: " . $plate_number . " (" . $vehicle_type . ") to " . $department . " department";
            createNotification($notification_message, 'vehicle_added', $conn);
            
            $_SESSION['success'] = "Vehicle added successfully!";
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error adding vehicle: " . $e->getMessage();
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        }
    }
    
    // Handle gate pass creation
    if (isset($_POST['create_gate_pass'])) {
        try {
            $vehicle_id = $_POST['vehicle_id'];
            $driver_id = $_POST['driver_id'];
            $officer_id = $_POST['officer_id'];
            $purpose = $_POST['purpose'];
            $description = $_POST['description'];
            $destination = $_POST['destination'];
            $time_out = $_POST['time_out'];
            $expected_return = $_POST['expected_return'];
            
            // Generate unique pass number
            $pass_number = "GP" . date('YmdHis') . rand(100, 999);
            
            // Auto-approve by IO (current logged-in admin)
            $approved_by = $_SESSION['user_id'];
            
            $insert_query = "INSERT INTO gate_passes (pass_number, vehicle_id, driver_id, officer_id, purpose, description, destination, time_out, expected_return, approved_by, created_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved')";
            $stmt = $conn->prepare($insert_query);
            $stmt->execute([$pass_number, $vehicle_id, $driver_id, $officer_id, $purpose, $description, $destination, $time_out, $expected_return, $approved_by, $approved_by]);
            
            $pass_id = $conn->lastInsertId();
            
            // Generate QR code data
            $qr_data = "GATEPASS:" . $pass_number . ":" . $pass_id . ":" . $vehicle_id;
            
            // Generate actual QR code image
            $qr_code_path = generateQRCode($qr_data, $pass_number);
            
            $update_qr = "UPDATE gate_passes SET qr_code = ? WHERE pass_id = ?";
            $stmt2 = $conn->prepare($update_qr);
            $stmt2->execute([$qr_data, $pass_id]);
            
            // Generate gate pass as PDF
            $gate_pass_pdf_path = generateGatePassPDF($pass_id, $conn);
            
            // Get officer/driver details for display
            $recipient_query = "SELECT fname, lname FROM workers WHERE worker_id = ?";
            $recipient_stmt = $conn->prepare($recipient_query);
            $recipient_stmt->execute([$officer_id ? $officer_id : $driver_id]);
            $recipient = $recipient_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Store gate pass details for display
            $_SESSION['last_gate_pass'] = [
                'pass_id' => $pass_id,
                'pass_number' => $pass_number,
                'pdf_path' => $gate_pass_pdf_path,
                'recipient_name' => $recipient ? $recipient['fname'] . ' ' . $recipient['lname'] : 'Unknown',
                'time_out' => $time_out,
                'expected_return' => $expected_return,
                'destination' => $destination,
                'purpose' => $purpose
            ];
            
            // Create notification
            $notification_message = "📄 New Gate Pass Created: " . $pass_number . " for " . $destination;
            createNotification($notification_message, 'gate_pass_created', $conn);
            
            $_SESSION['success'] = "Gate pass created successfully! Pass Number: <strong>" . $pass_number . "</strong>";
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
            
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error creating gate pass: " . $e->getMessage();
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        }
    }
    
    // Handle QR code scanning for vehicle movements
    if (isset($_POST['scan_qr_code'])) {
        try {
            $qr_data = $_POST['qr_data'];
            $scan_type = $_POST['scan_type'];
            
            // Parse QR data
            $qr_parts = explode(":", $qr_data);
            if (count($qr_parts) >= 4 && $qr_parts[0] === "GATEPASS") {
                $pass_number = $qr_parts[1];
                $pass_id = $qr_parts[2];
                $vehicle_id = $qr_parts[3];
                
                // Get gate pass details
                $pass_query = "
                    SELECT gp.*, 
                           v.plate_number, v.vehicle_type, v.model,
                           d.fname as driver_fname, d.lname as driver_lname, d.telephone as driver_phone,
                           o.fname as officer_fname, o.lname as officer_lname, o.telephone as officer_phone
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
                    if ($scan_type === 'out') {
                        // Check if already scanned out
                        $check_scan = $conn->query("SELECT * FROM gate_scans WHERE pass_id = $pass_id AND scan_type = 'out'")->fetch();
                        
                        if (!$check_scan) {
                            // Record exit scan
                            $scan_query = "INSERT INTO gate_scans (pass_id, scan_type, scanned_by, notes) VALUES (?, 'out', ?, 'Vehicle exited institution')";
                            $scan_stmt = $conn->prepare($scan_query);
                            $scan_stmt->execute([$pass_id, $_SESSION['user_id']]);
                            
                            // Update gate pass status and actual time out
                            $update_pass = "UPDATE gate_passes SET status = 'outside', actual_time_out = NOW() WHERE pass_id = ?";
                            $update_stmt = $conn->prepare($update_pass);
                            $update_stmt->execute([$pass_id]);
                            
                            // Update vehicle status
                            $update_vehicle = "UPDATE vehicles SET status = 'outside' WHERE vehicle_id = ?";
                            $vehicle_stmt = $conn->prepare($update_vehicle);
                            $vehicle_stmt->execute([$gate_pass['vehicle_id']]);
                            
                            // Create notification
                            $notification_message = "🚗 Vehicle Exit: " . $gate_pass['plate_number'] . " has left the institution. Destination: " . $gate_pass['destination'];
                            createNotification($notification_message, 'vehicle_movement', $conn);
                            
                            $_SESSION['success'] = "Vehicle exit recorded successfully! Plate: " . $gate_pass['plate_number'] . " is now outside institution.";
                            header("Location: " . $_SERVER['REQUEST_URI']);
                            exit;
                        } else {
                            $_SESSION['error'] = "Vehicle already scanned out!";
                            header("Location: " . $_SERVER['REQUEST_URI']);
                            exit;
                        }
                    } else if ($scan_type === 'in') {
                        // Check if scanned out first
                        $check_out_scan = $conn->query("SELECT * FROM gate_scans WHERE pass_id = $pass_id AND scan_type = 'out'")->fetch();
                        
                        if ($check_out_scan) {
                            // Record entry scan
                            $scan_query = "INSERT INTO gate_scans (pass_id, scan_type, scanned_by, notes) VALUES (?, 'in', ?, 'Vehicle returned to institution')";
                            $scan_stmt = $conn->prepare($scan_query);
                            $scan_stmt->execute([$pass_id, $_SESSION['user_id']]);
                            
                            // Update gate pass status
                            $update_pass = "UPDATE gate_passes SET status = 'returned', actual_return = NOW() WHERE pass_id = ?";
                            $update_stmt = $conn->prepare($update_pass);
                            $update_stmt->execute([$pass_id]);
                            
                            // Update vehicle status
                            $update_vehicle = "UPDATE vehicles SET status = 'available' WHERE vehicle_id = ?";
                            $vehicle_stmt = $conn->prepare($update_vehicle);
                            $vehicle_stmt->execute([$gate_pass['vehicle_id']]);
                            
                            // Create notification
                            $notification_message = "✅ Vehicle Return: " . $gate_pass['plate_number'] . " has returned to institution.";
                            createNotification($notification_message, 'vehicle_movement', $conn);
                            
                            $_SESSION['success'] = "Vehicle return recorded successfully! Plate: " . $gate_pass['plate_number'] . " is now back in institution.";
                            header("Location: " . $_SERVER['REQUEST_URI']);
                            exit;
                        } else {
                            $_SESSION['error'] = "Vehicle must be scanned out first before scanning in!";
                            header("Location: " . $_SERVER['REQUEST_URI']);
                            exit;
                        }
                    }
                } else {
                    $_SESSION['error'] = "Invalid gate pass QR code!";
                    header("Location: " . $_SERVER['REQUEST_URI']);
                    exit;
                }
            } else {
                $_SESSION['error'] = "Invalid QR code format!";
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit;
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error processing QR scan: " . $e->getMessage();
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        }
    }
    
    // Handle mark all notifications as read
    if (isset($_POST['mark_all_read'])) {
        try {
            $update_query = "UPDATE notifications SET is_read = 1 WHERE is_read = 0";
            $stmt = $conn->prepare($update_query);
            $stmt->execute();
            
            $_SESSION['success'] = "All notifications marked as read!";
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error updating notifications: " . $e->getMessage();
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        }
    }
}

/**
 * Create a new notification in the database
 * 
 * @param string $message The notification message
 * @param string $type The type of notification
 * @param PDO $conn Database connection
 * @return bool Success status
 */
function createNotification($message, $type, $conn) {
    try {
        // First check if notifications table has is_read column
        $check_column = $conn->query("SHOW COLUMNS FROM notifications LIKE 'is_read'")->fetch();
        
        if ($check_column) {
            $insert_query = "INSERT INTO notifications (message, type, created_at, is_read) VALUES (?, ?, NOW(), 0)";
        } else {
            $insert_query = "INSERT INTO notifications (message, type, created_at) VALUES (?, ?, NOW())";
        }
        
        $stmt = $conn->prepare($insert_query);
        $stmt->execute([$message, $type]);
        return true;
    } catch (PDOException $e) {
        error_log("Notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate QR code image for gate pass
 * 
 * @param string $data The data to encode in QR code
 * @param string $pass_number The gate pass number for filename
 * @return string Path to the generated QR code image
 */
function generateQRCode($data, $pass_number) {
    $qr_codes_dir = __DIR__ . '/qrcodes';
    if (!file_exists($qr_codes_dir)) {
        mkdir($qr_codes_dir, 0777, true);
    }
    
    $filename = $qr_codes_dir . '/qr_' . $pass_number . '.png';
    
    // Generate QR code with higher error correction for better scanning
    QRcode::png($data, $filename, QR_ECLEVEL_H, 8, 2);
    
    return $filename;
}

/**
 * Generate professional PDF gate pass document
 * 
 * @param int $pass_id The gate pass ID
 * @param PDO $conn Database connection
 * @return string|bool Path to PDF file or false on failure
 */
function generateGatePassPDF($pass_id, $conn) {
    // Get gate pass details with related information
    $pass_query = "
        SELECT gp.*, 
               v.plate_number, v.vehicle_type, v.model, v.color,
               d.fname as driver_fname, d.lname as driver_lname, d.rank as driver_rank,
               o.fname as officer_fname, o.lname as officer_lname, o.rank as officer_rank,
               a.fname as approved_fname, a.lname as approved_lname, a.rank as approved_rank
        FROM gate_passes gp
        LEFT JOIN vehicles v ON gp.vehicle_id = v.vehicle_id
        LEFT JOIN workers d ON gp.driver_id = d.worker_id
        LEFT JOIN workers o ON gp.officer_id = o.worker_id
        LEFT JOIN workers a ON gp.approved_by = a.worker_id
        WHERE gp.pass_id = ?
    ";
    $pass_stmt = $conn->prepare($pass_query);
    $pass_stmt->execute([$pass_id]);
    $gate_pass = $pass_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$gate_pass) {
        error_log("Gate pass not found for ID: " . $pass_id);
        return false;
    }
    
    // Create PDF directory with absolute path
    $pdf_dir = __DIR__ . '/gate_pass_pdfs';
    if (!file_exists($pdf_dir)) {
        mkdir($pdf_dir, 0777, true);
    }
    
    $pdf_filename = 'gate_pass_' . $gate_pass['pass_number'] . '.pdf';
    $pdf_path = $pdf_dir . '/' . $pdf_filename;
    
    // Create new PDF document
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Military Institution');
    $pdf->SetAuthor('IO Department');
    $pdf->SetTitle('Gate Pass - ' . $gate_pass['pass_number']);
    $pdf->SetSubject('Official Gate Pass');
    
    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Set margins
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', 'B', 16);
    
    // Header with background
    $pdf->SetFillColor(0, 51, 102);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(0, 20, 'MILITARY INSTITUTION', 0, 1, 'C', true);
    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->Cell(0, 15, 'OFFICIAL GATE PASS', 0, 1, 'C', true);
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, $gate_pass['pass_number'], 0, 1, 'C', true);
    
    // Reset text color
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 10);
    
    // Add some space
    $pdf->Ln(10);
    
    // Vehicle Information
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(0, 8, 'VEHICLE INFORMATION', 0, 1, 'L', true);
    $pdf->SetFont('helvetica', '', 10);
    
    $pdf->Cell(40, 6, 'Plate Number:', 0, 0);
    $pdf->Cell(0, 6, $gate_pass['plate_number'] ?: 'N/A', 0, 1);
    
    $pdf->Cell(40, 6, 'Vehicle Type:', 0, 0);
    $pdf->Cell(0, 6, $gate_pass['vehicle_type'] ?: 'N/A', 0, 1);
    
    $pdf->Cell(40, 6, 'Model:', 0, 0);
    $pdf->Cell(0, 6, $gate_pass['model'] ?: 'N/A', 0, 1);
    
    $pdf->Cell(40, 6, 'Color:', 0, 0);
    $pdf->Cell(0, 6, $gate_pass['color'] ?: 'N/A', 0, 1);
    
    $pdf->Ln(5);
    
    // Personnel Information
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(0, 8, 'PERSONNEL INFORMATION', 0, 1, 'L', true);
    $pdf->SetFont('helvetica', '', 10);
    
    if ($gate_pass['driver_fname']) {
        $pdf->Cell(40, 6, 'Driver:', 0, 0);
        $pdf->Cell(0, 6, $gate_pass['driver_rank'] . ' ' . $gate_pass['driver_fname'] . ' ' . $gate_pass['driver_lname'], 0, 1);
    }
    
    if ($gate_pass['officer_fname']) {
        $pdf->Cell(40, 6, 'Officer:', 0, 0);
        $pdf->Cell(0, 6, $gate_pass['officer_rank'] . ' ' . $gate_pass['officer_fname'] . ' ' . $gate_pass['officer_lname'], 0, 1);
    }
    
    $pdf->Ln(5);
    
    // Mission Details
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(0, 8, 'MISSION DETAILS', 0, 1, 'L', true);
    $pdf->SetFont('helvetica', '', 10);
    
    $pdf->Cell(40, 6, 'Destination:', 0, 0);
    $pdf->Cell(0, 6, $gate_pass['destination'] ?: 'N/A', 0, 1);
    
    $pdf->Cell(40, 6, 'Purpose:', 0, 0);
    $pdf->Cell(0, 6, ucwords(str_replace('_', ' ', $gate_pass['purpose'] ?: 'N/A')), 0, 1);
    
    $pdf->Cell(40, 6, 'Time Out:', 0, 0);
    $pdf->Cell(0, 6, $gate_pass['time_out'] ? date('M j, Y g:i A', strtotime($gate_pass['time_out'])) : 'N/A', 0, 1);
    
    $pdf->Cell(40, 6, 'Expected Return:', 0, 0);
    $pdf->Cell(0, 6, $gate_pass['expected_return'] ? date('M j, Y g:i A', strtotime($gate_pass['expected_return'])) : 'N/A', 0, 1);
    
    $pdf->Ln(3);
    $pdf->Cell(40, 6, 'Description:', 0, 1);
    $pdf->MultiCell(0, 6, $gate_pass['description'] ?: 'No description provided', 0, 'L');
    
    $pdf->Ln(5);
    
    // Approval Section
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(0, 8, 'APPROVAL', 0, 1, 'L', true);
    $pdf->SetFont('helvetica', '', 10);
    
    $pdf->Cell(40, 6, 'Approved By:', 0, 0);
    $pdf->Cell(0, 6, ($gate_pass['approved_rank'] ? $gate_pass['approved_rank'] . ' ' : '') . $gate_pass['approved_fname'] . ' ' . $gate_pass['approved_lname'], 0, 1);
    
    $pdf->Cell(40, 6, 'Issued On:', 0, 0);
    $pdf->Cell(0, 6, $gate_pass['created_at'] ? date('M j, Y g:i A', strtotime($gate_pass['created_at'])) : date('M j, Y g:i A'), 0, 1);
    
    // Add QR code if exists
    $qr_code_path = __DIR__ . '/qrcodes/qr_' . $gate_pass['pass_number'] . '.png';
    if (file_exists($qr_code_path)) {
        $pdf->Ln(10);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'QR CODE FOR SCANNING', 0, 1, 'C');
        
        // Get current Y position
        $current_y = $pdf->GetY();
        
        // Add QR code image
        $pdf->Image($qr_code_path, 80, $current_y, 50, 50, 'PNG', '', 'C', false, 300, '', false, false, 0, false, false, false);
        
        // Move position down after QR code
        $pdf->SetY($current_y + 55);
    }
    
    // Security notice
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->SetTextColor(255, 0, 0);
    $pdf->Cell(0, 6, 'SECURITY NOTICE: This document must be presented at the gate for scanning. Unauthorized use is prohibited.', 0, 1, 'C');
    
    // Save PDF file - Use 'F' parameter with absolute path
    try {
        $pdf->Output($pdf_path, 'F');
        
        // Verify file was created
        if (file_exists($pdf_path)) {
            return $pdf_path;
        } else {
            error_log("PDF file was not created: " . $pdf_path);
            return false;
        }
    } catch (Exception $e) {
        error_log("PDF generation error: " . $e->getMessage());
        return false;
    }
}

// Get available materials from database
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
    error_log("Available materials error: " . $e->getMessage());
}

// Get checked out materials
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

// Get all workers
try {
    $workers = $conn->query("SELECT * FROM workers WHERE status = 'active' ORDER BY department, rank, fname, lname")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $workers = [];
}

// Get all vehicles
try {
    $vehicles = $conn->query("SELECT * FROM vehicles ORDER BY plate_number")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $vehicles = [];
}

// Get gate passes with actual time out and return information from gate_scans table
try {
    $gate_passes = $conn->query("
        SELECT 
            gp.*, 
            v.plate_number, 
            v.vehicle_type, 
            v.model,
            d.fname as driver_fname, 
            d.lname as driver_lname,
            o.fname as officer_fname, 
            o.lname as officer_lname,
            a.fname as approved_fname, 
            a.lname as approved_lname,
            -- Get actual time out from gate_scans (scan_type = 'out')
            (SELECT scan_time FROM gate_scans WHERE pass_id = gp.pass_id AND scan_type = 'out' ORDER BY scan_time DESC LIMIT 1) as actual_time_out,
            -- Get actual return time from gate_scans (scan_type = 'in')  
            (SELECT scan_time FROM gate_scans WHERE pass_id = gp.pass_id AND scan_type = 'in' ORDER BY scan_time DESC LIMIT 1) as actual_return
        FROM gate_passes gp
        LEFT JOIN vehicles v ON gp.vehicle_id = v.vehicle_id
        LEFT JOIN workers d ON gp.driver_id = d.worker_id
        LEFT JOIN workers o ON gp.officer_id = o.worker_id
        LEFT JOIN workers a ON gp.approved_by = a.worker_id
        ORDER BY gp.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $gate_passes = [];
    error_log("Gate passes query error: " . $e->getMessage());
}

// Get gate scans history with vehicle details
try {
    $gate_scans = $conn->query("
        SELECT gs.*, gp.pass_number, v.plate_number, v.vehicle_type, v.model, gp.destination, gp.description,
               w.fname, w.lname
        FROM gate_scans gs
        LEFT JOIN gate_passes gp ON gs.pass_id = gp.pass_id
        LEFT JOIN vehicles v ON gp.vehicle_id = v.vehicle_id
        LEFT JOIN workers w ON gs.scanned_by = w.worker_id
        ORDER BY gs.scan_time DESC
        LIMIT 50
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $gate_scans = [];
}

// Get all notifications
try {
    $all_notifications = $conn->query("
        SELECT * FROM notifications 
        ORDER BY created_at DESC 
        LIMIT 100
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $all_notifications = [];
    error_log("All notifications error: " . $e->getMessage());
}

// Get recent vehicle movements for dashboard (last 10)
try {
    $recent_vehicle_movements = $conn->query("
        SELECT * FROM notifications 
        WHERE type = 'vehicle_movement'
        ORDER BY created_at DESC 
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recent_vehicle_movements = [];
}

// Get statistics for charts - FIXED to count from gate_scans table
try {
    // Count vehicles currently outside (scanned out but not scanned in yet)
    $active_passes = $conn->query("
        SELECT COUNT(DISTINCT gs.pass_id) as count 
        FROM gate_scans gs 
        WHERE gs.scan_type = 'out' 
        AND gs.pass_id NOT IN (
            SELECT pass_id FROM gate_scans WHERE scan_type = 'in'
        )
    ")->fetch(PDO::FETCH_ASSOC);
    $active_passes = $active_passes ? $active_passes['count'] : 0;

    // Count approved passes (not yet scanned out)
    $approved_passes = $conn->query("
        SELECT COUNT(*) as count 
        FROM gate_passes 
        WHERE status = 'approved' 
        AND pass_id NOT IN (SELECT pass_id FROM gate_scans WHERE scan_type = 'out')
    ")->fetch(PDO::FETCH_ASSOC);
    $approved_passes = $approved_passes ? $approved_passes['count'] : 0;

    // Count returned passes (scanned out and then scanned in)
    $returned_passes = $conn->query("
        SELECT COUNT(DISTINCT gs.pass_id) as count 
        FROM gate_scans gs 
        WHERE gs.scan_type = 'in'
    ")->fetch(PDO::FETCH_ASSOC);
    $returned_passes = $returned_passes ? $returned_passes['count'] : 0;

} catch (PDOException $e) {
    $active_passes = $approved_passes = $returned_passes = 0;
    error_log("Gate pass statistics error: " . $e->getMessage());
}

// Get other statistics
$total_materials = $conn->query("SELECT COUNT(*) as count FROM materials")->fetch(PDO::FETCH_ASSOC);
$total_materials = $total_materials ? $total_materials['count'] : 0;

$total_available = $conn->query("SELECT COUNT(*) as count FROM materials WHERE status IS NULL OR status = 'available' OR status = ''")->fetch(PDO::FETCH_ASSOC);
$total_available = $total_available ? $total_available['count'] : 0;

$total_checked_out = $conn->query("SELECT COUNT(*) as count FROM materials WHERE status = 'checked_out'")->fetch(PDO::FETCH_ASSOC);
$total_checked_out = $total_checked_out ? $total_checked_out['count'] : 0;

$total_outside = $conn->query("SELECT COUNT(*) as count FROM materials WHERE status = 'outside_institution'")->fetch(PDO::FETCH_ASSOC);
$total_outside = $total_outside ? $total_outside['count'] : 0;

$total_cadets = count($cadets);
$total_workers = count($workers);
$total_vehicles = count($vehicles);

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
    <title>IO Dashboard - Complete Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.4/minified/html5-qrcode.min.js"></script>
    <style>
        /* Previous CSS remains the same, just adding fixes for notification display */
        .notification-item.unread {
            background: rgba(52, 152, 219, 0.1);
            border-left: 3px solid #3498db;
        }
        
        .notification-dropdown {
            max-height: 70vh;
        }
        
        .notification-list {
            max-height: 50vh;
        }
        
        /* Fix for gate pass buttons */
        .gate-pass-actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .gate-pass-actions .btn {
            white-space: nowrap;
        }
        
        /* Style for actual time columns */
        .actual-time {
            font-size: 0.85rem;
            color: #666;
        }
        
        .actual-time.present {
            color: #28a745;
            font-weight: 600;
        }
        /* Previous CSS remains the same, just adding fixes for notification display */
        .notification-item.unread {
            background: rgba(52, 152, 219, 0.1);
            border-left: 3px solid #3498db;
        }
        
        .notification-dropdown {
            max-height: 70vh;
        }
        
        .notification-list {
            max-height: 50vh;
        }
        
        /* Fix for gate pass buttons */
        .gate-pass-actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .gate-pass-actions .btn {
            white-space: nowrap;
        }
        /* Previous CSS remains the same, just adding fixes for notification display */
        .notification-item.unread {
            background: rgba(52, 152, 219, 0.1);
            border-left: 3px solid #3498db;
        }
        
        .notification-dropdown {
            max-height: 70vh;
        }
        
        .notification-list {
            max-height: 50vh;
        }
        :root {
            --primary: #1a17dcc6;
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
        
        /* Notification Bell Styles */
        .notification-bell {
            position: relative;
            cursor: pointer;
            padding: 0.4rem;
            border-radius: 5px;
            transition: all 0.3s;
            color: var(--sidebar-text);
        }
        
        .notification-bell:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .notification-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger);
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
        
        /* Notification Dropdown */
        .notification-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            width: 400px;
            background: var(--card-bg);
            border-radius: 8px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            z-index: 1001;
            display: none;
            max-height: 500px;
            overflow-y: auto;
        }
        
        .notification-dropdown.active {
            display: block;
            animation: fadeInDown 0.3s ease;
        }
        
        .notification-header {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: between;
            align-items: center;
        }
        
        .notification-header h3 {
            margin: 0;
            font-size: 1rem;
        }
        
        .notification-list {
            max-height: 350px;
            overflow-y: auto;
        }
        
        .notification-item {
            padding: 0.8rem 1rem;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .notification-item:hover {
            background: rgba(0,0,0,0.03);
        }
        
        .notification-item.unread {
            background: rgba(52, 152, 219, 0.1);
        }
        
        .notification-message {
            font-size: 0.9rem;
            margin-bottom: 0.3rem;
        }
        
        .notification-time {
            font-size: 0.8rem;
            color: #666;
        }
        
        .notification-footer {
            padding: 0.8rem;
            text-align: center;
            border-top: 1px solid var(--border-color);
        }
        
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
        .btn-warning:hover { background: #154ec9ff; transform: translateY(-2px); }
        
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
            border-left-color: #3c34deff;
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
            max-width: 400px;
            margin: 0 auto;
        }
        
        .scan-result {
            margin-top: 1rem;
            padding: 1rem;
            border-radius: 6px;
            background: #f8f9fa;
        }

        .gate-pass-card {
            background: white;
            border: 2px solid #333;
            border-radius: 10px;
            padding: 2rem;
            margin: 1rem 0;
            text-align: center;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .qr-display {
            background: white;
            padding: 1.5rem;
            border: 2px solid #333;
            display: inline-block;
            margin: 1rem 0;
            font-family: monospace;
            font-size: 18px;
            letter-spacing: 3px;
        }
        
        .pass-details {
            text-align: left;
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 6px;
            margin: 1rem 0;
        }

        .no-match { color: #999; font-style: italic; }
        .text-muted { color: #6c757d; }

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
        }

        .notifications-panel {
            background: var(--card-bg);
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            max-height: 300px;
            overflow-y: auto;
        }
        
        .notification-item {
            padding: 0.8rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        
        .notification-item:last-child {
            border-bottom: none;
        }
        
        .notification-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.8rem;
            flex-shrink: 0;
        }
        
        .notification-vehicle-out {
            background: #4d5dead1;
        }
        
        .notification-vehicle-in {
            background: #27ae60;
        }
        
        .notification-message {
            flex: 1;
            font-size: 0.9rem;
        }
        
        .notification-time {
            font-size: 0.8rem;
            color: #666;
            margin-top: 0.2rem;
        }
        
        .vehicle-movement-badge {
            padding: 0.3rem 0.6rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
            margin-left: 0.5rem;
        }
        
        .badge-out { background: #e74c3c; color: white; }
        .badge-in { background: #27ae60; color: white; }
     </style>
</head>
<body>
    <!-- Header -->
    <!-- Header Section -->
    <div class="header">
        <div class="header-content">
            <div style="display: flex; align-items: center; gap: 0.8rem;">
                <div class="logo">
                    <i class="fas fa-user-shield"></i>
                    <span>IO Dashboard</span>
                </div>
            </div>
            <div class="header-actions">
                <!-- Notification Bell -->
                <div class="notification-bell" id="notificationBell">
                    <i class="fas fa-bell"></i>
                    <?php if ($notification_count > 0): ?>
                        <span class="notification-count"><?php echo $notification_count; ?></span>
                    <?php endif; ?>
                </div>
                <!-- Theme Toggle -->
                <button class="theme-toggle" id="themeToggle">
                    <i class="fas fa-moon"></i>
                </button>
                <!-- User Role and Info -->
                <span class="user-role">IO</span>
                <span style="font-size: 0.9rem;"><?php echo $_SESSION['full_name']; ?></span>
                <!-- Logout Button -->
                <a href="logout.php" class="btn btn-danger btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
        
        <!-- Notification Dropdown -->
        <div class="notification-dropdown" id="notificationDropdown">
            <div class="notification-header">
                <h3>Notifications (<?php echo $notification_count; ?> unread)</h3>
                <?php if ($notification_count > 0): ?>
                    <form method="POST" action="" style="margin: 0;">
                        <input type="hidden" name="mark_all_read" value="1">
                        <button type="submit" class="btn btn-sm btn-primary">Mark All Read</button>
                    </form>
                <?php endif; ?>
            </div>
            <div class="notification-list">
                <?php if (!empty($all_notifications)): ?>
                    <?php foreach ($all_notifications as $notification): ?>
                        <div class="notification-item <?php echo (isset($notification['is_read']) && $notification['is_read'] == 0) ? 'unread' : ''; ?>">
                            <div class="notification-icon 
                                <?php echo $notification['type'] === 'vehicle_movement' ? 
                                    (strpos($notification['message'], 'Exit') ? 'notification-vehicle-out' : 'notification-vehicle-in') : 
                                    ($notification['type'] === 'material_checkout' ? 'activity-checkout' : 
                                    ($notification['type'] === 'material_return' ? 'activity-return' : 
                                    ($notification['type'] === 'material_outside' ? 'notification-vehicle-out' : 
                                    ($notification['type'] === 'material_taken' ? 'activity-checkout' : 'notification-vehicle-out')))); ?>">
                                <i class="fas fa-<?php 
                                    echo $notification['type'] === 'vehicle_movement' ? 
                                        (strpos($notification['message'], 'Exit') ? 'sign-out-alt' : 'sign-in-alt') : 
                                        ($notification['type'] === 'material_checkout' ? 'hand-holding' : 
                                        ($notification['type'] === 'material_return' ? 'undo' : 
                                        ($notification['type'] === 'material_outside' ? 'external-link-alt' : 
                                        ($notification['type'] === 'material_taken' ? 'check' : 'bell')))); 
                                ?>"></i>
                            </div>
                            <div class="notification-details">
                                <div class="notification-message"><?php echo $notification['message']; ?></div>
                                <div class="notification-time">
                                    <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                                    <?php if (isset($notification['is_read']) && $notification['is_read'] == 0): ?>
                                        <span style="color: #e74c3c; font-weight: bold;"> • New</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="notification-item">
                        <div class="notification-message" style="text-align: center; padding: 2rem;">
                            No notifications available.
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="notification-footer">
                <a href="#" class="btn btn-sm btn-outline" onclick="showPage('notifications')">View All Notifications</a>
            </div>
        </div>
    </div>

    <!-- Main Container -->
    <div class="main-container">
        <!-- Sidebar Navigation -->
        <div class="sidebar">
            <div class="sidebar-content">
                <button class="sidebar-toggle" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i> <span>Menu</span>
                </button>
                <ul class="sidebar-menu">
                    <li><a href="#" class="nav-link active" data-page="dashboard"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                    
                    <!-- Materials Management Dropdown -->
                    <li class="dropdown-toggle">
                        <a href="#" class="nav-link dropdown-main">
                            <i class="fas fa-box"></i> <span>Materials Management</span>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a href="#" class="nav-link" data-page="available-materials"><i class="fas fa-check-circle"></i> <span>Available Materials</span></a></li>
                            <li><a href="#" class="nav-link" data-page="checked-out-materials"><i class="fas fa-user-graduate"></i> <span>Checked Out</span></a></li>
                            <li><a href="#" class="nav-link" data-page="send-outside"><i class="fas fa-external-link-alt"></i> <span>Send Outside</span></a></li>
                            <li><a href="#" class="nav-link" data-page="outside-materials"><i class="fas fa-truck"></i> <span>Outside Institution</span></a></li>
                            <li><a href="#" class="nav-link" data-page="taken-materials"><i class="fas fa-check-circle"></i> <span>Taken Outside</span></a></li>
                        </ul>
                    </li>

                    <!-- Vehicle System Dropdown -->
                    <li class="dropdown-toggle">
                        <a href="#" class="nav-link dropdown-main">
                            <i class="fas fa-car"></i> <span>Vehicle System</span>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a href="#" class="nav-link" data-page="worker-management"><i class="fas fa-users"></i> <span>Military Personnel</span></a></li>
                            <li><a href="#" class="nav-link" data-page="vehicle-management"><i class="fas fa-car"></i> <span>Vehicles</span></a></li>
                            <li><a href="#" class="nav-link" data-page="gate-pass"><i class="fas fa-passport"></i> <span>Create Gate Pass</span></a></li>
                            <li><a href="#" class="nav-link" data-page="gate-pass-reports"><i class="fas fa-chart-bar"></i> <span>Pass Reports</span></a></li>
                            <li><a href="#" class="nav-link" data-page="gatekeeper-scan"><i class="fas fa-qrcode"></i> <span>Scan QR Code</span></a></li>
                        </ul>
                    </li>

                    <!-- Additional Navigation Items -->
                    <li><a href="#" class="nav-link" data-page="cadet-management"><i class="fas fa-users"></i> <span>Cadet Management</span></a></li>
                    <li><a href="#" class="nav-link" data-page="notifications"><i class="fas fa-bell"></i> <span>All Notifications</span></a></li>
                </ul>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="content">
            <!-- Success/Error Messages Display -->
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> 
                    <?php 
                    echo $success; 
                    // Show gate pass actions if a gate pass was just created
                    if (isset($_SESSION['last_gate_pass'])): 
                    ?>
                    <div style="margin-top: 10px; padding: 10px; background: #e8f5e8; border-radius: 5px;">
                        <strong>Gate Pass Actions:</strong>
                        <div class="quick-actions" style="margin-top: 8px;">
                            <a href="?download_gate_pass=1&pass_id=<?php echo $_SESSION['last_gate_pass']['pass_id']; ?>" 
                               class="btn btn-success btn-sm">
                                <i class="fas fa-download"></i> Download PDF
                            </a>
                            <a href="?view_gate_pass=1&pass_id=<?php echo $_SESSION['last_gate_pass']['pass_id']; ?>" 
                               class="btn btn-info btn-sm" 
                               target="_blank">
                                <i class="fas fa-eye"></i> View PDF
                            </a>
                        </div>
                    </div>
                    <?php 
                    endif; 
                    // Clear the session after displaying
                    unset($_SESSION['last_gate_pass']);
                    ?>
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
                        <h1 class="page-title">IO Dashboard</h1>
                        <p class="page-description">Welcome, <?php echo $_SESSION['full_name']; ?>! Complete management overview.</p>
                    </div>
                </div>
                
                <!-- Statistics Cards -->
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
                        <i class="fas fa-car fa-lg"></i>
                        <div class="stat"><?php echo $total_vehicles; ?></div>
                        <p>Total Vehicles</p>
                    </div>
                </div>

                <!-- Vehicle Statistics Cards -->
                <div class="vehicle-stats">
                    <div class="stat-card">
                        <i class="fas fa-users fa-lg"></i>
                        <div class="stat"><?php echo $total_workers; ?></div>
                        <p>Military Personnel</p>
                    </div>
                    
                    <div class="stat-card">
                        <i class="fas fa-passport fa-lg"></i>
                        <div class="stat"><?php echo $approved_passes; ?></div>
                        <p>Approved Gate Passes</p>
                    </div>
                    
                    <div class="stat-card">
                        <i class="fas fa-external-link-alt fa-lg"></i>
                        <div class="stat"><?php echo $active_passes; ?></div>
                        <p>Vehicles Outside</p>
                    </div>
                    
                    <div class="stat-card">
                        <i class="fas fa-undo fa-lg"></i>
                        <div class="stat"><?php echo $returned_passes; ?></div>
                        <p>Vehicles Returned</p>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="quick-actions">
                    <button class="btn btn-primary" onclick="showPage('available-materials')">
                        <i class="fas fa-box"></i> Available Materials
                    </button>
                    <button class="btn btn-info" onclick="showPage('gate-pass')">
                        <i class="fas fa-passport"></i> Create Gate Pass
                    </button>
                    <button class="btn btn-warning" onclick="showPage('vehicle-management')">
                        <i class="fas fa-car"></i> Vehicle Management
                    </button>
                    <button class="btn btn-success" onclick="showPage('worker-management')">
                        <i class="fas fa-users"></i> Personnel Management
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
                        <h3>Gate Pass Status</h3>
                        <div class="chart-wrapper">
                            <canvas id="gatePassChart"></canvas>
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

                <!-- Recent Vehicle Movements - Positioned at Bottom -->
                <div class="notifications-panel" style="margin-top: 2rem;">
                    <h3 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-car"></i> Recent Vehicle Movements
                    </h3>
                    <?php if (!empty($recent_vehicle_movements)): ?>
                        <?php foreach ($recent_vehicle_movements as $notification): ?>
                            <div class="notification-item">
                                <div class="notification-icon <?php echo strpos($notification['message'], 'Exit') ? 'notification-vehicle-out' : 'notification-vehicle-in'; ?>">
                                    <i class="fas fa-<?php echo strpos($notification['message'], 'Exit') ? 'sign-out-alt' : 'sign-in-alt'; ?>"></i>
                                </div>
                                <div class="notification-details">
                                    <div class="notification-message"><?php echo $notification['message']; ?></div>
                                    <div class="notification-time">
                                        <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: #666; padding: 2rem;">No recent vehicle movements.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Gate Pass Reports Page -->
            <div class="page-content" id="gate-pass-reports">
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Gate Pass Reports</h1>
                        <p class="page-description">View and manage all gate passes with actual movement times from gate scans.</p>
                    </div>
                </div>
                
                <div class="table-container">
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Pass Number</th>
                                    <th>Vehicle</th>
                                    <th>Driver</th>
                                    <th>Officer</th>
                                    <th>Destination</th>
                                    <th>Time Out</th>
                                    <th>Expected Return</th>
                                    <th>Actual Time Out</th>
                                    <th>Actual Return</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($gate_passes as $pass): ?>
                                <tr>
                                    <td><strong><?php echo $pass['pass_number']; ?></strong></td>
                                    <td>
                                        <?php echo $pass['plate_number']; ?><br>
                                        <small><?php echo $pass['vehicle_type']; ?> • <?php echo $pass['model']; ?></small>
                                    </td>
                                    <td>
                                        <?php if ($pass['driver_fname']): ?>
                                            <?php echo $pass['driver_fname'] . ' ' . $pass['driver_lname']; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($pass['officer_fname']): ?>
                                            <?php echo $pass['officer_fname'] . ' ' . $pass['officer_lname']; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $pass['destination']; ?></td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($pass['time_out'])); ?></td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($pass['expected_return'])); ?></td>
                                    <td>
                                        <?php if ($pass['actual_time_out']): ?>
                                            <span class="actual-time present">
                                                <?php echo date('M j, Y g:i A', strtotime($pass['actual_time_out'])); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="actual-time">Not scanned out</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($pass['actual_return']): ?>
                                            <span class="actual-time present">
                                                <?php echo date('M j, Y g:i A', strtotime($pass['actual_return'])); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="actual-time">
                                                <?php echo $pass['actual_time_out'] ? 'Not returned yet' : 'Not applicable'; ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php 
                                            echo $pass['status'] == 'approved' ? 'success' : 
                                                 ($pass['status'] == 'outside' ? 'warning' : 
                                                 ($pass['status'] == 'returned' ? 'info' : 'secondary')); 
                                        ?>">
                                            <?php echo ucfirst($pass['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="gate-pass-actions">
                                            <a href="?download_gate_pass=1&pass_id=<?php echo $pass['pass_id']; ?>" 
                                               class="btn btn-success btn-sm">
                                                <i class="fas fa-download"></i> Download
                                            </a>
                                            <a href="?view_gate_pass=1&pass_id=<?php echo $pass['pass_id']; ?>" 
                                               class="btn btn-info btn-sm" 
                                               target="_blank">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
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
                                <?php if (!empty($available_materials)): ?>
                                    <?php foreach ($available_materials as $material): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($material['material_code'] ?? 'N/A'); ?></strong></td>
                                            <td><?php echo htmlspecialchars($material['material_name'] ?? $material['name'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($material['description'] ?? 'No description'); ?></td>
                                            <td><?php echo htmlspecialchars($material['quantity'] ?? '1'); ?></td>
                                            <td>
                                                <?php if (!empty($material['fname'])): ?>
                                                    <strong><?php echo htmlspecialchars($material['fname'] . ' ' . $material['lname']); ?></strong>
                                                <?php else: ?>
                                                    <span style="color: #999;">Not assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($material['telephone'] ?? $material['cadet_phone'] ?? 'N/A'); ?></td>
                                            <td>
                                                <?php if (!empty($material['registered_date'])): ?>
                                                    <?php echo date('M j, Y', strtotime($material['registered_date'])); ?>
                                                <?php else: ?>
                                                    <span style="color: #999;">Not recorded</span>
                                                <?php endif; ?>
                                            </td>
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
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" style="text-align: center; padding: 2rem;">
                                            <p>No available materials found in stock.</p>
                                            <p class="text-muted">All materials are currently checked out or assigned.</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
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
                                    <th>Actions</th>
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
                                        <td>
                                            <form method="POST" action="" style="display: inline;">
                                                <input type="hidden" name="mark_taken" value="1">
                                                <input type="hidden" name="material_id" value="<?php echo $material['material_id']; ?>">
                                                <button type="submit" class="btn btn-success btn-sm">
                                                    <i class="fas fa-check"></i> Mark as Taken
                                                </button>
                                            </form>
                                        </td>
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

            <!-- Worker Management Page -->
            <div class="page-content" id="worker-management">
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Military Personnel Management</h1>
                        <p class="page-description">Manage all soldiers and military staff members.</p>
                    </div>
                    <button class="btn btn-primary" onclick="showModal('addWorkerModal')">
                        <i class="fas fa-user-plus"></i> Add Soldier
                    </button>
                </div>
                
                <!-- Department Filter Tabs -->
                <div class="quick-actions" style="margin-bottom: 1rem;">
                    <button class="btn btn-outline active" onclick="filterWorkers('all')">All Personnel</button>
                    <button class="btn btn-outline" onclick="filterWorkers('transport')">Transport</button>
                    <button class="btn btn-outline" onclick="filterWorkers('mechanized')">Mechanized</button>
                    <button class="btn btn-outline" onclick="filterWorkers('instructor')">Instructor</button>
                    <button class="btn btn-outline" onclick="filterWorkers('maintenance')">Maintenance</button>
                </div>
                
                <!-- Role Filter Tabs -->
                <div class="quick-actions" style="margin-bottom: 1rem;">
                    <button class="btn btn-outline active" onclick="filterByRole('all')">All Roles</button>
                    <button class="btn btn-outline" onclick="filterByRole('driver')">Drivers</button>
                    <button class="btn btn-outline" onclick="filterByRole('instructor')">Instructors</button>
                    <button class="btn btn-outline" onclick="filterByRole('technician')">Technicians</button>
                    <button class="btn btn-outline" onclick="filterByRole('supervisor')">Supervisors</button>
                    <button class="btn btn-outline" onclick="filterByRole('other')">Other</button>
                </div>
                
                <div class="table-container">
                    <div class="table-wrapper">
                        <table id="workersTable">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Rank</th>
                                    <th>Department</th>
                                    <th>Role</th>
                                    <th>Specific Duty</th>
                                    <th>Telephone</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                // Get all workers with new structure
                                $workers = $conn->query("SELECT * FROM workers WHERE status = 'active' ORDER BY department, rank, fname, lname")->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($workers as $worker): 
                                ?>
                                <tr data-department="<?php echo $worker['department']; ?>" data-role="<?php echo $worker['soldier_role']; ?>">
                                    <td>
                                        <strong><?php echo $worker['fname'] . ' ' . $worker['lname']; ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge badge-info"><?php echo $worker['rank']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php 
                                            echo $worker['department'] == 'transport' ? 'primary' : 
                                                 ($worker['department'] == 'mechanized' ? 'warning' : 
                                                 ($worker['department'] == 'instructor' ? 'success' : 'info')); 
                                        ?>">
                                            <?php echo ucfirst($worker['department']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php 
                                            echo $worker['soldier_role'] == 'driver' ? 'warning' : 
                                                 ($worker['soldier_role'] == 'instructor' ? 'success' : 
                                                 ($worker['soldier_role'] == 'technician' ? 'info' : 
                                                 ($worker['soldier_role'] == 'supervisor' ? 'danger' : 'secondary'))); 
                                        ?>">
                                            <?php echo ucfirst($worker['soldier_role']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $worker['specific_duty'] ?: 'N/A'; ?></td>
                                    <td><?php echo $worker['telephone']; ?></td>
                                    <td><?php echo $worker['email'] ?: 'N/A'; ?></td>
                                    <td>
                                        <span class="badge badge-success">Active</span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-info" onclick="editWorker(<?php echo $worker['worker_id']; ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Vehicle Management Page -->
            <div class="page-content" id="vehicle-management">
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Vehicle Management</h1>
                        <p class="page-description">Manage all institution vehicles.</p>
                    </div>
                    <button class="btn btn-primary" onclick="showModal('addVehicleModal')">
                        <i class="fas fa-plus"></i> Add Vehicle
                    </button>
                </div>
                
                <div class="table-container">
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Plate Number</th>
                                    <th>Type</th>
                                    <th>Model</th>
                                    <th>Color</th>
                                    <th>Capacity</th>
                                    <th>Department</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vehicles as $vehicle): ?>
                                <tr>
                                    <td><strong><?php echo $vehicle['plate_number']; ?></strong></td>
                                    <td><?php echo $vehicle['vehicle_type']; ?></td>
                                    <td><?php echo $vehicle['model']; ?></td>
                                    <td><?php echo $vehicle['color']; ?></td>
                                    <td><?php echo $vehicle['capacity']; ?></td>
                                    <td><?php echo $vehicle['department']; ?></td>
                                    <td>
                                        <span class="badge badge-<?php 
                                            echo $vehicle['status'] == 'available' ? 'success' : 
                                                 ($vehicle['status'] == 'in_use' ? 'warning' : 
                                                 ($vehicle['status'] == 'outside' ? 'danger' : 'secondary')); 
                                        ?>">
                                            <?php echo ucfirst($vehicle['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-info">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Gate Pass Creation Page -->
            <div class="page-content" id="gate-pass">
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Create Gate Pass</h1>
                        <p class="page-description">Generate gate passes for vehicles leaving the institution.</p>
                    </div>
                </div>
                
                <div class="form-container">
                    <form method="POST" action="">
                        <input type="hidden" name="create_gate_pass" value="1">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="vehicle_id">Vehicle *</label>
                                <select id="vehicle_id" name="vehicle_id" required>
                                    <option value="">Select Vehicle</option>
                                    <?php foreach ($vehicles as $vehicle): ?>
                                        <option value="<?php echo $vehicle['vehicle_id']; ?>">
                                            <?php echo $vehicle['plate_number'] . ' - ' . $vehicle['vehicle_type'] . ' ' . $vehicle['model']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="driver_id">Driver *</label>
                                <select id="driver_id" name="driver_id" required>
                                    <option value="">Select Driver</option>
                                    <?php 
                                    // Get only drivers from transport department
                                    $drivers = $conn->query("SELECT * FROM workers WHERE soldier_role = 'driver' AND status = 'active' ORDER BY rank, fname, lname")->fetchAll(PDO::FETCH_ASSOC);
                                    foreach ($drivers as $driver): ?>
                                        <option value="<?php echo $driver['worker_id']; ?>" data-email="<?php echo $driver['email']; ?>">
                                            <?php echo $driver['rank'] . ' ' . $driver['fname'] . ' ' . $driver['lname']; ?>
                                            <?php if ($driver['specific_duty']): ?>
                                                - <?php echo $driver['specific_duty']; ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small style="color: #666;">Only personnel with driver role are shown</small>
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="officer_id">Officer In Charge *</label>
                                <select id="officer_id" name="officer_id" required>
                                    <option value="">Select Officer</option>
                                    <?php 
                                    // Get supervisors and higher ranks for officers
                                    $officers = $conn->query("
                                        SELECT * FROM workers 
                                        WHERE (soldier_role = 'supervisor' OR soldier_role = 'instructor' OR rank IN ('Second Lieutenant', 'First Lieutenant', 'Captain', 'Major', 'Lieutenant Colonel', 'Colonel', 'Brigadier General', 'Major General', 'Lieutenant General', 'General'))
                                        AND status = 'active' 
                                        ORDER BY 
                                            CASE 
                                                WHEN rank = 'General' THEN 1
                                                WHEN rank = 'Lieutenant General' THEN 2
                                                WHEN rank = 'Major General' THEN 3
                                                WHEN rank = 'Brigadier General' THEN 4
                                                WHEN rank = 'Colonel' THEN 5
                                                WHEN rank = 'Lieutenant Colonel' THEN 6
                                                WHEN rank = 'Major' THEN 7
                                                WHEN rank = 'Captain' THEN 8
                                                WHEN rank = 'First Lieutenant' THEN 9
                                                WHEN rank = 'Second Lieutenant' THEN 10
                                                ELSE 11
                                            END,
                                            fname, lname
                                    ")->fetchAll(PDO::FETCH_ASSOC);
                                    foreach ($officers as $officer): ?>
                                        <option value="<?php echo $officer['worker_id']; ?>" data-email="<?php echo $officer['email']; ?>">
                                            <?php echo $officer['rank'] . ' ' . $officer['fname'] . ' ' . $officer['lname']; ?>
                                            - <?php echo ucfirst($officer['department']); ?> Dept
                                            <?php if ($officer['specific_duty']): ?>
                                                (<?php echo $officer['specific_duty']; ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small style="color: #666;">Supervisors and commissioned officers are shown</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="destination">Destination *</label>
                                <input type="text" id="destination" name="destination" required placeholder="Where is the vehicle going?">
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="time_out">Time Out *</label>
                                <input type="datetime-local" id="time_out" name="time_out" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="expected_return">Expected Return *</label>
                                <input type="datetime-local" id="expected_return" name="expected_return" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="purpose">Purpose *</label>
                            <select id="purpose" name="purpose" required>
                                <option value="">Select Purpose</option>
                                <option value="training">Training Exercise</option>
                                <option value="field_operation">Field Operation</option>
                                <option value="maintenance">Vehicle Maintenance</option>
                                <option value="supply_transport">Supply Transport</option>
                                <option value="personnel_transport">Personnel Transport</option>
                                <option value="official_visit">Official Visit</option>
                                <option value="other">Other Military Duty</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Mission Description *</label>
                            <textarea id="description" name="description" rows="3" required placeholder="Detailed description of the military mission..."></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-passport"></i> Generate Gate Pass
                        </button>
                    </form>
                </div>
            </div>

            <!-- Gate Pass Reports Page -->
            <div class="page-content" id="gate-pass-reports">
        <div class="page-header">
            <div>
                <h1 class="page-title">Gate Pass Reports</h1>
                <p class="page-description">View and manage all gate passes with download and view options.</p>
            </div>
        </div>
        
        <div class="table-container">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Pass Number</th>
                            <th>Vehicle</th>
                            <th>Driver</th>
                            <th>Officer</th>
                            <th>Destination</th>
                            <th>Time Out</th>
                            <th>Expected Return</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($gate_passes as $pass): ?>
                        <tr>
                            <td><strong><?php echo $pass['pass_number']; ?></strong></td>
                            <td>
                                <?php echo $pass['plate_number']; ?><br>
                                <small><?php echo $pass['vehicle_type']; ?> • <?php echo $pass['model']; ?></small>
                            </td>
                            <td>
                                <?php if ($pass['driver_fname']): ?>
                                    <?php echo $pass['driver_fname'] . ' ' . $pass['driver_lname']; ?>
                                <?php else: ?>
                                    <span class="text-muted">Not assigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($pass['officer_fname']): ?>
                                    <?php echo $pass['officer_fname'] . ' ' . $pass['officer_lname']; ?>
                                <?php else: ?>
                                    <span class="text-muted">Not assigned</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $pass['destination']; ?></td>
                            <td><?php echo date('M j, Y g:i A', strtotime($pass['time_out'])); ?></td>
                            <td><?php echo date('M j, Y g:i A', strtotime($pass['expected_return'])); ?></td>
                            <td>
                                <span class="badge badge-<?php 
                                    echo $pass['status'] == 'approved' ? 'success' : 
                                         ($pass['status'] == 'outside' ? 'warning' : 
                                         ($pass['status'] == 'returned' ? 'info' : 'secondary')); 
                                ?>">
                                    <?php echo ucfirst($pass['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="quick-actions" style="justify-content: flex-start; gap: 5px;">
                                    <a href="?download_gate_pass=1&pass_id=<?php echo $pass['pass_id']; ?>" 
                                       class="btn btn-success btn-sm">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                    <a href="?view_gate_pass=1&pass_id=<?php echo $pass['pass_id']; ?>" 
                                       class="btn btn-info btn-sm" 
                                       target="_blank">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <?php if ($pass['status'] == 'approved'): ?>
                                        <span class="badge badge-warning">Regenerate PDF</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

            <!-- Gatekeeper Scan Page -->
            <div class="page-content" id="gatekeeper-scan">
                <div class="page-header">
                    <div>
                        <h1 class="page-title">QR Code Scanner</h1>
                        <p class="page-description">Scan gate pass QR codes to record vehicle movements.</p>
                    </div>
                </div>
                
                <div class="scan-container">
                    <h3>Scan Gate Pass QR Code</h3>
                    <p>Select scan type and use camera to scan QR code:</p>
                    
                    <div class="form-group" style="max-width: 300px; margin: 0 auto 1rem auto;">
                        <label for="scan_type">Scan Type *</label>
                        <select id="scan_type" name="scan_type" required>
                            <option value="out">Vehicle Exit (Out)</option>
                            <option value="in">Vehicle Return (In)</option>
                        </select>
                    </div>
                    
                    <div id="qr-reader"></div>
                    <div id="qr-reader-results"></div>
                    
                    <div id="scan-result" class="scan-result" style="display: none;">
                        <h4>Scan Result:</h4>
                        <div id="result-content"></div>
                    </div>
                </div>

                <!-- Manual QR Entry -->
                <div class="form-container">
                    <h3>Manual QR Code Entry</h3>
                    <form method="POST" action="">
                        <input type="hidden" name="scan_qr_code" value="1">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="manual_scan_type">Scan Type *</label>
                                <select id="manual_scan_type" name="scan_type" required>
                                    <option value="out">Vehicle Exit (Out)</option>
                                    <option value="in">Vehicle Return (In)</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="qr_data">QR Code Data *</label>
                                <input type="text" id="qr_data" name="qr_data" required placeholder="Enter QR code data manually">
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check"></i> Process Manual Entry
                        </button>
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
                                    <th>Scanned By</th>
                                    <th>Scan Time</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($gate_scans as $scan): ?>
                                <tr>
                                    <td><strong><?php echo $scan['pass_number']; ?></strong></td>
                                    <td><?php echo $scan['plate_number']; ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $scan['scan_type'] == 'out' ? 'warning' : 'success'; ?>">
                                            <?php echo strtoupper($scan['scan_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $scan['fname'] . ' ' . $scan['lname']; ?></td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($scan['scan_time'])); ?></td>
                                    <td><?php echo $scan['notes']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Vehicle Movements Page -->
            <div class="page-content" id="vehicle-movements">
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Vehicle Movement Reports</h1>
                        <p class="page-description">Track all vehicle entries and exits with detailed information.</p>
                    </div>
                </div>
                
                <div class="table-container">
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Pass Number</th>
                                    <th>Vehicle</th>
                                    <th>Type</th>
                                    <th>Movement</th>
                                    <th>Destination</th>
                                    <th>Description</th>
                                    <th>Scanned By</th>
                                    <th>Scan Time</th>
                                    <th>Actual Time Out</th>
                                    <th>Actual Return</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($gate_scans as $scan): ?>
                                <tr>
                                    <td><strong><?php echo $scan['pass_number']; ?></strong></td>
                                    <td>
                                        <strong><?php echo $scan['plate_number']; ?></strong><br>
                                        <small><?php echo $scan['model']; ?></small>
                                    </td>
                                    <td><?php echo $scan['vehicle_type']; ?></td>
                                    <td>
                                        <span class="vehicle-movement-badge <?php echo $scan['scan_type'] == 'out' ? 'badge-out' : 'badge-in'; ?>">
                                            <?php echo $scan['scan_type'] == 'out' ? 'EXIT' : 'ENTRY'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $scan['destination'] ?: 'N/A'; ?></td>
                                    <td><?php echo $scan['description'] ?: 'N/A'; ?></td>
                                    <td><?php echo $scan['fname'] . ' ' . $scan['lname']; ?></td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($scan['scan_time'])); ?></td>
                                    <td>
                                        <?php if ($scan['scan_type'] == 'out'): ?>
                                            <span class="badge badge-success"><?php echo date('M j, Y g:i A', strtotime($scan['scan_time'])); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($scan['scan_type'] == 'in'): ?>
                                            <span class="badge badge-info"><?php echo date('M j, Y g:i A', strtotime($scan['scan_time'])); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
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

            <!-- All Notifications Page -->
            <div class="page-content" id="notifications">
                <div class="page-header">
                    <div>
                        <h1 class="page-title">All Notifications</h1>
                        <p class="page-description">View all system notifications and mark them as read.</p>
                    </div>
                    <form method="POST" action="" style="margin: 0;">
                        <input type="hidden" name="mark_all_read" value="1">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check-double"></i> Mark All as Read
                        </button>
                    </form>
                </div>
                
                <div class="table-container">
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Message</th>
                                    <th>Type</th>
                                    <th>Date & Time</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_notifications as $notification): ?>
                                <tr>
                                    <td><?php echo $notification['message']; ?></td>
                                    <td>
                                        <span class="badge badge-<?php 
                                            echo $notification['type'] === 'vehicle_movement' ? 'info' : 
                                                 ($notification['type'] === 'material_checkout' ? 'warning' : 
                                                 ($notification['type'] === 'material_return' ? 'success' : 
                                                 ($notification['type'] === 'material_outside' ? 'primary' : 
                                                 ($notification['type'] === 'material_taken' ? 'success' : 'secondary')))); 
                                        ?>">
                                            <?php echo str_replace('_', ' ', ucfirst($notification['type'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?></td>
                                    <td>
                                        <?php if ($notification['is_read'] == 0): ?>
                                            <span class="badge badge-danger">Unread</span>
                                        <?php else: ?>
                                            <span class="badge badge-success">Read</span>
                                        <?php endif; ?>
                                    </td>
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

    <!-- Add Worker Modal -->
    <div class="modal" id="addWorkerModal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('addWorkerModal')">&times;</button>
            <h2>Add New Soldier</h2>
            <form method="POST" action="">
                <input type="hidden" name="add_worker" value="1">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="fname">First Name *</label>
                        <input type="text" id="fname" name="fname" required>
                    </div>
                    <div class="form-group">
                        <label for="lname">Last Name *</label>
                        <input type="text" id="lname" name="lname" required>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="telephone">Telephone *</label>
                        <input type="text" id="telephone" name="telephone" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email">
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="rank">Military Rank *</label>
                        <select id="rank" name="rank" required>
                            <option value="">Select Rank</option>
                            <option value="Private">Private</option>
                            <option value="Private First Class">Private First Class</option>
                            <option value="Corporal">Corporal</option>
                            <option value="Sergeant">Sergeant</option>
                            <option value="Staff Sergeant">Staff Sergeant</option>
                            <option value="Sergeant First Class">Sergeant First Class</option>
                            <option value="Master Sergeant">Master Sergeant</option>
                            <option value="First Sergeant">First Sergeant</option>
                            <option value="Sergeant Major">Sergeant Major</option>
                            <option value="Command Sergeant Major">Command Sergeant Major</option>
                            <option value="Second Lieutenant">Second Lieutenant</option>
                            <option value="First Lieutenant">First Lieutenant</option>
                            <option value="Captain">Captain</option>
                            <option value="Major">Major</option>
                            <option value="Lieutenant Colonel">Lieutenant Colonel</option>
                            <option value="Colonel">Colonel</option>
                            <option value="Brigadier General">Brigadier General</option>
                            <option value="Major General">Major General</option>
                            <option value="Lieutenant General">Lieutenant General</option>
                            <option value="General">General</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="department">Department *</label>
                        <select id="department" name="department" required>
                            <option value="">Select Department</option>
                            <option value="transport">Transport</option>
                            <option value="mechanized">Mechanized</option>
                            <option value="instructor">Instructor</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="soldier_role">Role/Position *</label>
                        <select id="soldier_role" name="soldier_role" required>
                            <option value="">Select Role</option>
                            <option value="driver">Driver</option>
                            <option value="instructor">Instructor</option>
                            <option value="technician">Technician</option>
                            <option value="supervisor">Supervisor</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="specific_duty">Specific Duty</label>
                        <input type="text" id="specific_duty" name="specific_duty" placeholder="e.g., Heavy Vehicle Driver, Weapons Instructor">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-user-plus"></i> Add Soldier
                </button>
            </form>
        </div>
    </div>

    <!-- Add Vehicle Modal -->
    <div class="modal" id="addVehicleModal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('addVehicleModal')">&times;</button>
            <h2>Add New Vehicle</h2>
            <form method="POST" action="">
                <input type="hidden" name="add_vehicle" value="1">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="plate_number">Plate Number *</label>
                        <input type="text" id="plate_number" name="plate_number" required>
                    </div>
                    <div class="form-group">
                        <label for="vehicle_type">Vehicle Type *</label>
                        <select id="vehicle_type" name="vehicle_type" required>
                            <option value="">Select Type</option>
                            <option value="car">Car</option>
                            <option value="truck">Truck</option>
                            <option value="bus">Bus</option>
                            <option value="van">Van</option>
                            <option value="motorcycle">Motorcycle</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="model">Model</label>
                        <input type="text" id="model" name="model">
                    </div>
                    <div class="form-group">
                        <label for="color">Color</label>
                        <input type="text" id="color" name="color">
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="capacity">Capacity</label>
                        <input type="number" id="capacity" name="capacity" min="1" value="4">
                    </div>
                    <div class="form-group">
                        <label for="department">Department</label>
                        <input type="text" id="department" name="department">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-success">Add Vehicle</button>
            </form>
        </div>
    </div>

    <script>
        let html5QrcodeScanner;

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
            
            // Notification bell functionality
            const notificationBell = document.getElementById('notificationBell');
            const notificationDropdown = document.getElementById('notificationDropdown');
            
            notificationBell.addEventListener('click', function(e) {
                e.stopPropagation();
                notificationDropdown.classList.toggle('active');
            });
            
            // Close notification dropdown when clicking outside
            document.addEventListener('click', function() {
                notificationDropdown.classList.remove('active');
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
            
            // Initialize dropdown menus
            initializeDropdowns();
            
            // Set default datetime values for gate pass form
            const timeOut = document.getElementById('time_out');
            const expectedReturn = document.getElementById('expected_return');
            if (timeOut && expectedReturn) {
                const now = new Date();
                const later = new Date(now.getTime() + 4 * 60 * 60 * 1000); // 4 hours later for military operations
                
                timeOut.value = formatDateTime(now);
                expectedReturn.value = formatDateTime(later);
            }
            
            // Initialize QR scanner when scan page is active
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                        if (document.getElementById('gatekeeper-scan').classList.contains('active')) {
                            initializeQRScanner();
                        }
                    }
                });
            });
            
            observer.observe(document.getElementById('gatekeeper-scan'), {
                attributes: true,
                attributeFilter: ['class']
            });
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

        function formatDateTime(date) {
            return date.toISOString().slice(0, 16);
        }

        function initializeQRScanner() {
            if (html5QrcodeScanner) {
                html5QrcodeScanner.clear();
            }
            
            html5QrcodeScanner = new Html5QrcodeScanner(
                "qr-reader", 
                { 
                    fps: 10, 
                    qrbox: { width: 250, height: 250 } 
                },
                /* verbose= */ false
            );
            
            html5QrcodeScanner.render(onScanSuccess, onScanFailure);
        }

        function onScanSuccess(decodedText, decodedResult) {
            // Stop scanning after success
            html5QrcodeScanner.clear();
            
            // Show scan result
            document.getElementById('scan-result').style.display = 'block';
            document.getElementById('result-content').innerHTML = `
                <p><strong>QR Data:</strong> ${decodedText}</p>
                <p>Processing scan...</p>
            `;
            
            // Get scan type
            const scanType = document.getElementById('scan_type').value;
            
            // Submit the scan automatically
            submitQRScan(decodedText, scanType);
        }

        function onScanFailure(error) {
            // Handle scan failure, ignore most errors
        }

        function submitQRScan(qrData, scanType) {
            const formData = new FormData();
            formData.append('scan_qr_code', '1');
            formData.append('qr_data', qrData);
            formData.append('scan_type', scanType);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                // Reload page to show result
                window.location.reload();
            })
            .catch(error => {
                document.getElementById('result-content').innerHTML = `
                    <p style="color: red;">Error processing scan: ${error}</p>
                `;
            });
        }

        function initializeCharts() {
            // Materials Distribution Chart
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
            
            // Gate Pass Status Chart
            const gatePassCtx = document.getElementById('gatePassChart').getContext('2d');
            const gatePassChart = new Chart(gatePassCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Approved', 'Outside', 'Returned'],
                    datasets: [{
                        data: [<?php echo $approved_passes; ?>, <?php echo $active_passes; ?>, <?php echo $returned_passes; ?>],
                        backgroundColor: [
                            'rgba(40, 167, 69, 0.8)',
                            'rgba(255, 193, 7, 0.8)',
                            'rgba(23, 162, 184, 0.8)'
                        ],
                        borderColor: [
                            'rgba(40, 167, 69, 1)',
                            'rgba(255, 193, 7, 1)',
                            'rgba(23, 162, 184, 1)'
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

        function showModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
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

        // Filter workers by department
        function filterWorkers(department) {
            const rows = document.querySelectorAll('#workersTable tbody tr');
            const filterButtons = document.querySelectorAll('.quick-actions .btn');
            
            // Update active button in department filter
            filterButtons.forEach(btn => {
                if (btn.parentElement.classList.contains('quick-actions') && !btn.textContent.includes('Role')) {
                    btn.classList.remove('active');
                    if (btn.textContent.toLowerCase().includes(department) || (department === 'all' && btn.textContent.includes('All Personnel'))) {
                        btn.classList.add('active');
                    }
                }
            });
            
            // Show/hide rows based on department
            rows.forEach(row => {
                if (department === 'all') {
                    row.style.display = '';
                } else {
                    if (row.getAttribute('data-department') === department) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                }
            });
        }

        // Filter workers by role
        function filterByRole(role) {
            const rows = document.querySelectorAll('#workersTable tbody tr');
            const filterButtons = document.querySelectorAll('.quick-actions .btn');
            
            // Update active button in role filter
            filterButtons.forEach(btn => {
                if (btn.textContent.includes('Role')) {
                    btn.classList.remove('active');
                    if (btn.textContent.toLowerCase().includes(role) || (role === 'all' && btn.textContent.includes('All Roles'))) {
                        btn.classList.add('active');
                    }
                }
            });
            
            // Show/hide rows based on role
            rows.forEach(row => {
                if (role === 'all') {
                    row.style.display = '';
                } else {
                    if (row.getAttribute('data-role') === role) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                }
            });
        }

        function editWorker(workerId) {
            alert('Edit worker ID: ' + workerId + '\n\nThis would open an edit form in a real implementation.');
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
        
        // Auto-refresh notifications every 30 seconds
        setInterval(function() {
            if (document.getElementById('dashboard').classList.contains('active')) {
                location.reload();
            }
        }, 30000);

        // JavaScript code remains the same
        // Initialize charts and other functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Chart initialization code here
            initializeCharts();
            
            function initializeCharts() {
                // Materials Distribution Chart
                const statusCtx = document.getElementById('statusChart')?.getContext('2d');
                if (statusCtx) {
                    new Chart(statusCtx, {
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
                }
                
                // Gate Pass Status Chart
                const gatePassCtx = document.getElementById('gatePassChart')?.getContext('2d');
                if (gatePassCtx) {
                    new Chart(gatePassCtx, {
                        type: 'doughnut',
                        data: {
                            labels: ['Approved', 'Outside', 'Returned'],
                            datasets: [{
                                data: [<?php echo $approved_passes; ?>, <?php echo $active_passes; ?>, <?php echo $returned_passes; ?>],
                                backgroundColor: [
                                    'rgba(40, 167, 69, 0.8)',
                                    'rgba(255, 193, 7, 0.8)',
                                    'rgba(23, 162, 184, 0.8)'
                                ],
                                borderColor: [
                                    'rgba(40, 167, 69, 1)',
                                    'rgba(255, 193, 7, 1)',
                                    'rgba(23, 162, 184, 1)'
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
        });
    </script>
</body>
</html>