<?php
session_start();

function checkRole($requiredRole) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../index.php");
        exit();
    }
    
    // Now using database role from session
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
    $query = "SELECT * FROM users WHERE userid = :userid";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(":userid", $user_id);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function checkAnyRole($allowedRoles) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../index.php");
        exit();
    }
    
    if (!in_array($_SESSION['user_role'], $allowedRoles)) {
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