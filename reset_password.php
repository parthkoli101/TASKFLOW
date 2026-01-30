<?php
session_start();
require 'vendor/autoload.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';
use Dotenv\Dotenv;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();
$conn = new mysqli("localhost", "root", "Parth@23102025", "taskflow1");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$token = isset($_GET['token']) ? $_GET['token'] : '';
$type = isset($_GET['type']) ? $_GET['type'] : '';
$valid_token = false;
$email = '';

if (!empty($token) && !empty($type)) {
    if ($type === 'company') {
        $stmt = $conn->prepare("SELECT admin_email, reset_expiry FROM company_registration WHERE reset_token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $data = $result->fetch_assoc();
            if (strtotime($data['reset_expiry']) > time()) {
                $valid_token = true;
                $email = $data['admin_email'];
            }
        }
    } elseif ($type === 'employee') {
        $stmt = $conn->prepare("SELECT employee_email, reset_expiry FROM employee_registration WHERE reset_token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $data = $result->fetch_assoc();
            if (strtotime($data['reset_expiry']) > time()) {
                $valid_token = true;
                $email = $data['employee_email'];
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    $new_password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (strlen($new_password) < 6) {
        $error_message = "❌ Password must be at least 6 characters long.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "❌ Passwords do not match.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        if ($type === 'company') {
            $stmt = $conn->prepare("UPDATE company_registration SET admin_password = ?, reset_token = NULL, reset_expiry = NULL WHERE reset_token = ?");
            $stmt->bind_param("ss", $hashed_password, $token);
        } elseif ($type === 'employee') {
            $stmt = $conn->prepare("UPDATE employee_registration SET employee_password = ?, reset_token = NULL, reset_expiry = NULL WHERE reset_token = ?");
            $stmt->bind_param("ss", $hashed_password, $token);
        }
        
        if ($stmt->execute()) {
            $success_message = "✅ Password reset successfully! You can now sign in with your new password.";
            $password_reset = true;
        } else {
            $error_message = "❌ Failed to reset password. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaskFlow - Reset Password</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #3498db 0%, #2c3e50 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .reset-container {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 450px;
            text-align: center;
        }
        .logo {
            font-size: 2rem;
            font-weight: 700;
            color: #3498db;
            margin-bottom: 10px;
            text-align: center;
        }
        .tagline {
            color: #6c757d;
            margin-bottom: 30px;
            font-size: 0.9rem;
        }
        .form-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 20px;
            text-align: center;
        }
        .input-group {
            position: relative;
            margin-bottom: 20px;
            text-align: left;
        }
        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #3498db;
            font-size: 16px;
            z-index: 2;
        }
        .form-input {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            background: rgba(255, 255, 255, 0.9);
            transition: all 0.3s ease;
            color: #2d3748;
        }
        .form-input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            background: white;
        }
        .form-input::placeholder {
            color: #a0aec0;
            font-weight: 400;
        }
        .submit-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.4);
        }
        .submit-btn:active {
            transform: translateY(0);
        }
        .message {
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
            font-size: 14px;
        }
        .success {
            background: linear-gradient(135deg, #11998e, #38ef7d);
            color: white;
            border: none;
        }
        .error {
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
            color: white;
            border: none;
        }
        .email-info {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .invalid-token {
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .back-link {
            color: #3498db;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .password-requirements {
            text-align: left;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 12px;
            color: #6c757d;
        }
        .password-requirements ul {
            margin: 0;
            padding-left: 20px;
        }
        .password-requirements li {
            margin-bottom: 5px;
        }
        .strength-indicator {
            height: 5px;
            background: #e2e8f0;
            border-radius: 3px;
            margin-top: 5px;
            overflow: hidden;
        }
        .strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
            border-radius: 3px;
        }
        .strength-weak { background: #ff6b6b; width: 25%; }
        .strength-fair { background: #ffa500; width: 50%; }
        .strength-good { background: #3498db; width: 75%; }
        .strength-strong { background: #11998e; width: 100%; }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="logo">
            <i class="fas fa-tasks"></i> TaskFlow
        </div>
        <div class="tagline">Streamline Your Workflow</div>
        
        <?php if (!$valid_token): ?>
            <div class="invalid-token">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Invalid or Expired Link</h3>
                <p>This password reset link is invalid or has expired. Please request a new password reset.</p>
            </div>
            <a href="login.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Sign In
            </a>
        <?php else: ?>
            <?php if (isset($password_reset) && $password_reset): ?>
                <div class="message success">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                </div>
                <a href="login.php" class="back-link">
                    <i class="fas fa-sign-in-alt"></i> Go to Sign In
                </a>
            <?php else: ?>
                <h2 class="form-title">
                    <i class="fas fa-key"></i> Reset Password
                </h2>
                
                <div class="email-info">
                    <i class="fas fa-user"></i> Resetting password for:<br>
                    <strong><?php echo htmlspecialchars($email); ?></strong>
                </div>
                
                <?php if (isset($error_message)): ?>
                    <div class="message error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <div class="password-requirements">
                    <strong>Password Requirements:</strong>
                    <ul>
                        <li>Minimum 6 characters</li>
                        <li>Both passwords must match</li>
                        <li>Use a combination of letters, numbers, and symbols for better security</li>
                    </ul>
                </div>
                
                <form method="POST" id="resetForm">
                    <div class="input-group">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" name="password" id="password" class="form-input" placeholder="New Password" required minlength="6">
                        <div class="strength-indicator">
                            <div class="strength-bar" id="strengthBar"></div>
                        </div>
                    </div>
                    
                    <div class="input-group">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" name="confirm_password" id="confirmPassword" class="form-input" placeholder="Confirm New Password" required minlength="6">
                    </div>
                    
                    <button type="submit" class="submit-btn" id="submitBtn">
                        <i class="fas fa-save"></i> Reset Password
                    </button>
                </form>
                
                <a href="signin.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to Sign In
                </a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <script>
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirmPassword');
        const strengthBar = document.getElementById('strengthBar');
        const submitBtn = document.getElementById('submitBtn');
        
        function checkPasswordStrength(password) {
            let strength = 0;
            if (password.length >= 6) strength++;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            return strength;
        }
        
        function updateStrengthIndicator(strength) {
            strengthBar.className = 'strength-bar';
            if (strength === 1) {
                strengthBar.classList.add('strength-weak');
            } else if (strength === 2) {
                strengthBar.classList.add('strength-fair');
            } else if (strength === 3) {
                strengthBar.classList.add('strength-good');
            } else if (strength >= 4) {
                strengthBar.classList.add('strength-strong');
            }
        }
        
        function validatePasswords() {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            const strength = checkPasswordStrength(password);
            updateStrengthIndicator(strength);
            
            if (password.length >= 6 && password === confirmPassword && confirmPassword !== '') {
                submitBtn.disabled = false;
                submitBtn.style.opacity = '1';
                confirmPasswordInput.style.borderColor = '#11998e';
            } else {
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.6';
                if (confirmPassword !== '' && password !== confirmPassword) {
                    confirmPasswordInput.style.borderColor = '#ff6b6b';
                } else {
                    confirmPasswordInput.style.borderColor = '#e2e8f0';
                }
            }
        }
        
        if (passwordInput && confirmPasswordInput) {
            passwordInput.addEventListener('input', validatePasswords);
            confirmPasswordInput.addEventListener('input', validatePasswords);
            
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.6';
        }
        
        document.querySelectorAll('.form-input').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'translateY(-2px)';
            });
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>