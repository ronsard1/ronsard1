<?php
session_start();
require_once "config/database.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $database = new Database();
    $conn = $database->getConnection();
    
    $username = $_POST['username'];
    $password = $_POST['password'];
    $role = $_POST['role'];
    
    $query = "SELECT * FROM users WHERE username = :username AND role = :role";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(":username", $username);
    $stmt->bindParam(":role", $role);
    $stmt->execute();
    
    if ($stmt->rowCount() == 1) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            
            switch($user['role']) {
                case 'admin':
                    header("Location: admin-dashboard.php");
                    break;
                case 'gatekeeper':
                    header("Location: gatekeeper-dashboard.php");
                    break;
                case 'student':
                    header("Location: student-dashboard.php");
                    break;
            }
            exit();
        }
    }
    
    $error = "Invalid credentials!";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Materials Management System - Login</title>
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
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
            min-height: 100vh;
        }
        
        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background: linear-gradient(135deg, var(--secondary) 0%, var(--dark) 100%);
        }
        
        .login-box {
            background: white;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            width: 400px;
            padding: 2rem;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        
        .login-icon {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }
        
        .role-selector {
            display: flex;
            margin-bottom: 1.5rem;
            border-radius: 4px;
            overflow: hidden;
            border: 1px solid #ddd;
        }
        
        .role-option {
            flex: 1;
            text-align: center;
            padding: 0.75rem;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .role-option.active {
            background-color: var(--primary);
            color: white;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        input, select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
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
            width: 100%;
        }
        
        button:hover {
            background-color: #2980b9;
        }
        
        .error {
            color: var(--danger);
            text-align: center;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <div class="login-icon">
                    <i class="fas fa-boxes"></i>
                </div>
                <h2>Materials Management System</h2>
                <p>Please log in to continue</p>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="role-selector">
                    <div class="role-option active" data-role="admin">Admin</div>
                    <div class="role-option" data-role="gatekeeper">Gatekeeper</div>
                    <div class="role-option" data-role="student">Student</div>
                </div>
                <input type="hidden" name="role" id="selected-role" value="admin" required>
                
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" placeholder="Enter your username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>
                <button type="submit">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const roleOptions = document.querySelectorAll('.role-option');
            const selectedRole = document.getElementById('selected-role');
            
            roleOptions.forEach(option => {
                option.addEventListener('click', function() {
                    roleOptions.forEach(o => o.classList.remove('active'));
                    this.classList.add('active');
                    selectedRole.value = this.getAttribute('data-role');
                });
            });
        });
    </script>
</body>
</html>