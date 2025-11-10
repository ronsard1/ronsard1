<?php
session_start();

function checkRole($requiredRole) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../index.php");
        exit();
    }
    
    // Since you don't have a role column in database, we use the session role
    // This assumes the role was set correctly during login
    if ($_SESSION['user_role'] !== $requiredRole) {
        switch($_SESSION['user_role']) {
            case 'admin':
                header("Location: admin-dashboard.php");
                break;
            case 'gatekeeper':
                header("Location: gatekeeper-dashboard.php");
                break;
            case 'student':
                header("Location: student-dashboard.php");
                break;
            default:
                header("Location: ../index.php");
        }
        exit();
    }
    
    return true;
}

function getCurrentUserData($conn, $user_id) {
    // Updated to match your database column names
    $query = "SELECT * FROM users WHERE userid = :userid";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(":userid", $user_id);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Alternative function if you want to determine role from database fields
function determineUserRoleFromDB($conn, $user_id) {
    $query = "SELECT department, rank FROM users WHERE userid = :userid";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(":userid", $user_id);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // Custom logic to determine role based on department/rank
        if ($user['department'] == 'Administration' || $user['rank'] == 'Admin') {
            return 'admin';
        } elseif ($user['department'] == 'Security' || $user['rank'] == 'Gatekeeper') {
            return 'gatekeeper';
        } else {
            return 'student';
        }
    }
    
    return 'student'; // Default role
}

// Enhanced checkRole function that verifies role from database
function checkRoleSecure($conn, $requiredRole) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../index.php");
        exit();
    }
    
    // Verify role from database instead of trusting session
    $actualRole = determineUserRoleFromDB($conn, $_SESSION['user_id']);
    
    if ($actualRole !== $requiredRole) {
        switch($actualRole) {
            case 'admin':
                header("Location: admin-dashboard.php");
                break;
            case 'gatekeeper':
                header("Location: gatekeeper-dashboard.php");
                break;
            case 'student':
                header("Location: student-dashboard.php");
                break;
            default:
                header("Location: ../index.php");
        }
        exit();
    }
    
    // Update session with actual role from database
    $_SESSION['user_role'] = $actualRole;
    
    return true;
}

// Function to check if user has at least one of multiple roles
function checkAnyRole($allowedRoles) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../index.php");
        exit();
    }
    
    if (!in_array($_SESSION['user_role'], $allowedRoles)) {
        // Redirect to appropriate dashboard based on actual role
        switch($_SESSION['user_role']) {
            case 'admin':
                header("Location: admin-dashboard.php");
                break;
            case 'gatekeeper':
                header("Location: gatekeeper-dashboard.php");
                break;
            case 'student':
                header("Location: student-dashboard.php");
                break;
            default:
                header("Location: ../index.php");
        }
        exit();
    }
    
    return true;
}
?>