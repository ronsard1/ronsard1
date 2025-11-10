<?php
session_start();
require_once "config/database.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $database = new Database();
    $conn = $database->getConnection();
    
    $telephone = $_POST['telephone'];
    $password = $_POST['password'];
    
    // Check if telephone exists in cadet table
    $query = "SELECT * FROM cadet WHERE number = :telephone";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(":telephone", $telephone);
    $stmt->execute();
    
    if ($stmt->rowCount() == 1) {
        $cadet = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verify password (plain text comparison)
        if ($password === $cadet['password']) {
            // Set session variables for cadet
            $_SESSION['user_id'] = $cadet['cadetid'];
            $_SESSION['user_role'] = 'cadet';
            $_SESSION['telephone'] = $cadet['number'];
            $_SESSION['full_name'] = $cadet['fname'] . ' ' . $cadet['lname'];
            $_SESSION['fname'] = $cadet['fname'];
            $_SESSION['lname'] = $cadet['lname'];
            $_SESSION['email'] = $cadet['email'];
            $_SESSION['rollno'] = $cadet['rollno'];
            $_SESSION['company'] = $cadet['company'];
            $_SESSION['platoon'] = $cadet['platoon'];
            
            // FIXED: Correct redirect path
            header("Location: student-dashboard.php");
            exit();
        } else {
            $error = "Invalid password!";
        }
    } else {
        $error = "Invalid telephone number!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Materials Management System - Cadet Login</title>
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
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        input {
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
            padding: 0.5rem;
            background-color: #fadbd8;
            border-radius: 4px;
        }
        
        .demo-credentials {
            margin-top: 1rem;
            text-align: center;
            font-size: 0.9rem;
            color: #666;
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 4px;
            border-left: 4px solid var(--primary);
        }
        
        .login-options {
            text-align: center;
            margin-top: 1rem;
            padding: 1rem;
            border-top: 1px solid #eee;
        }
        
        .login-options a {
            color: var(--primary);
            text-decoration: none;
            margin: 0 10px;
            font-size: 0.9rem;
        }
        
        .login-options a:hover {
            text-decoration: underline;
        }
        
        .cadet-info {
            background-color: #d1f2eb;
            border-left: 4px solid var(--success);
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        
        .password-toggle {
            position: relative;
        }
        
        .password-toggle i {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <div class="login-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <h2>Cadet Login</h2>
                <p>Enter your telephone number and password</p>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="cadet-info">
                <i class="fas fa-info-circle"></i> 
                <strong>Cadet Login:</strong> Use your registered telephone number and password
            </div>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="telephone">Telephone Number</label>
                    <input type="text" id="telephone" name="telephone" placeholder="Enter your telephone number" required>
                </div>
                
                <div class="form-group password-toggle">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                    <i class="fas fa-eye" id="togglePassword"></i>
                </div>
                
                <button type="submit">
                    <i class="fas fa-sign-in-alt"></i> Login as Cadet
                </button>
            </form>
            
            <div class="login-options">
                <a href="admin-login.php"><i class="fas fa-user-shield"></i> Admin Login</a>
                <a href="gatekeeper-login.php"><i class="fas fa-user-check"></i> Gatekeeper Login</a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Format telephone number input
            const telephoneInput = document.getElementById('telephone');
            
            telephoneInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.startsWith('255') && value.length > 9) {
                    value = '+' + value;
                }
                e.target.value = value;
            });
            
            // Password toggle functionality
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
            
            telephoneInput.focus();
        });
    </script>
</body>
</html>