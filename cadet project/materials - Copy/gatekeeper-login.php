<?php
session_start();
require_once "config/database.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $database = new Database();
    $conn = $database->getConnection();
    
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    $query = "SELECT * FROM users WHERE email = :email AND role = 'gatekeeper'";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(":email", $email);
    $stmt->execute();
    
    if ($stmt->rowCount() == 1) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($password === $user['password']) {
            $_SESSION['user_id'] = $user['userid'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['full_name'] = $user['full_name'];
            
            header("Location: gatekeeper-dashboard.php");
            exit();
        }
    }
    
    $error = "Invalid gatekeeper credentials!";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gatekeeper Login - Materials Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Same styles as above, with gatekeeper-specific colors */
        :root {
            --primary: #f39c12;
            --secondary: #d35400;
        }
        
        /* Rest of the styles same as cadet login */
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <div class="login-icon">
                    <i class="fas fa-user-check"></i>
                </div>
                <h2>Gatekeeper Login</h2>
                <p>Gatekeeper access only</p>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="Gatekeeper email" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Gatekeeper password" required>
                </div>
                
                <button type="submit">
                    <i class="fas fa-sign-in-alt"></i> Login as Gatekeeper
                </button>
            </form>
            
            <div class="login-options">
                <a href="index.php"><i class="fas fa-user-graduate"></i> Cadet Login</a>
                <a href="admin-login.php"><i class="fas fa-user-shield"></i> Admin Login</a>
            </div>
        </div>
    </div>
</body>
</html>