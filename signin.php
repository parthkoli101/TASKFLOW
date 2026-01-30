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

function sendResetEmail($to_email, $reset_link) {
    $mail = new PHPMailer(true);
    try {
        $mail->SMTPDebug = SMTP::DEBUG_OFF;
        $mail->isSMTP();
        $mail->Host = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['SMTP_USERNAME'];
        $mail->Password = $_ENV['SMTP_PASSWORD'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $_ENV['SMTP_PORT'];
        $mail->setFrom($_ENV['FROM_EMAIL'], $_ENV['FROM_NAME']);
        $mail->addAddress($to_email);
        $mail->isHTML(true);
        $mail->Subject = 'TaskFlow Password Reset Request';
        $mail->Body = "<html><body style='font-family: Arial, sans-serif;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
                <h2 style='color: #3498db; text-align: center;'>TaskFlow Password Reset</h2>
                <p>You have requested to reset your password. Click the button below to reset your password:</p>
                <div style='text-align: center; margin: 20px 0;'>
                    <a href='$reset_link' style='background-color: #3498db; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>Reset Password</a>
                </div>
                <p style='font-size: 14px; color: #666;'>This link will expire in 1 hour. If you did not request this reset, please ignore this email.</p>
                <p style='font-size: 12px; color: #999;'>If the button doesn't work, copy and paste this link: $reset_link</p>
            </div>
        </body></html>";
        $mail->AltBody = "Click the link to reset your password: $reset_link\n\nThis link will expire in 1 hour.";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Failed to send reset email: " . $mail->ErrorInfo);
        return false;
    }
}

$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : 'show_form');
$login_type = isset($_GET['login_type']) ? $_GET['login_type'] : 'company';
$is_reset_page = basename($_SERVER['PHP_SELF']) === 'reset_password.php';

if ($is_reset_page) {
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
} else {
    if ($action === 'login') {
        if ($login_type === 'company') {
            $company_id = $_POST['company_id'];
            $company_code = $_POST['company_code'];
            $admin_email = $_POST['admin_email'];
            $password = $_POST['password'];
            
            $stmt = $conn->prepare("SELECT admin_email, admin_password, company_code FROM company_registration WHERE company_id = ?");
            $stmt->bind_param("s", $company_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $error_message = "❌ Company not found.";
            } else {
                $company_data = $result->fetch_assoc();
                if (!password_verify($company_code, $company_data['company_code'])) {
                    $error_message = "❌ Invalid company code.";
                } elseif (!password_verify($password, $company_data['admin_password'])) {
                    $error_message = "❌ Invalid password.";
                } elseif ($company_data['admin_email'] !== $admin_email) {
                    $error_message = "❌ Email doesn't match company records.";
                } else {
                    $_SESSION['company_id'] = $company_id;
                    $_SESSION['admin_email'] = $admin_email;
                    header("Location: admin.php");
                    exit();
                }
            }
        } elseif ($login_type === 'employee') {
            $company_id = $_POST['company_id'];
            $company_code = $_POST['company_code'];
            $employee_email = $_POST['employee_email'];
            $password = $_POST['password'];
            
            $stmt = $conn->prepare("SELECT employee_email, employee_password FROM employee_registration WHERE company_id = ? AND employee_email = ?");
            $stmt->bind_param("ss", $company_id, $employee_email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $error_message = "❌ Employee not found.";
            } else {
                $employee_data = $result->fetch_assoc();
                $stmt = $conn->prepare("SELECT company_code FROM company_registration WHERE company_id = ?");
                $stmt->bind_param("s", $company_id);
                $stmt->execute();
                $company_result = $stmt->get_result();
                
                if ($company_result->num_rows === 0) {
                    $error_message = "❌ Company not found.";
                } else {
                    $company_data = $company_result->fetch_assoc();
                    if (!password_verify($company_code, $company_data['company_code'])) {
                        $error_message = "❌ Invalid company code.";
                    } elseif (!password_verify($password, $employee_data['employee_password'])) {
                        $error_message = "❌ Invalid password.";
                    } else {
                        $_SESSION['company_id'] = $company_id;
                        $_SESSION['employee_email'] = $employee_email;
                        header("Location: employee.php");
                        exit();
                    }
                }
            }
        }
    }

    if ($action === 'forgot_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = isset($_POST['email']) ? $_POST['email'] : '';
        $forgot_login_type = isset($_POST['login_type']) ? $_POST['login_type'] : $login_type;
        
        if (!empty($email)) {
            if ($forgot_login_type === 'company') {
                $stmt = $conn->prepare("SELECT admin_email FROM company_registration WHERE admin_email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) {
                    $error_message = "❌ Email not found in company records.";
                } else {
                    $reset_token = bin2hex(random_bytes(32));
                    $expiry = date("Y-m-d H:i:s", strtotime("+1 hour"));
                    
                    $stmt = $conn->prepare("UPDATE company_registration SET reset_token = ?, reset_expiry = ? WHERE admin_email = ?");
                    $stmt->bind_param("sss", $reset_token, $expiry, $email);
                    
                    if ($stmt->execute()) {
                        $current_file = basename($_SERVER['PHP_SELF']);
                        $base_url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
                        $reset_link = $base_url . "/reset_password.php?token=$reset_token&type=company";
                        
                        if (sendResetEmail($email, $reset_link)) {
                            $success_message = "✅ Password reset link sent to your email.";
                        } else {
                            $error_message = "❌ Failed to send reset email. Please try again.";
                        }
                    } else {
                        $error_message = "❌ Database error. Please try again.";
                    }
                }
            } elseif ($forgot_login_type === 'employee') {
                $stmt = $conn->prepare("SELECT employee_email FROM employee_registration WHERE employee_email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) {
                    $error_message = "❌ Email not found in employee records.";
                } else {
                    $reset_token = bin2hex(random_bytes(32));
                    $expiry = date("Y-m-d H:i:s", strtotime("+1 hour"));
                    
                    $stmt = $conn->prepare("UPDATE employee_registration SET reset_token = ?, reset_expiry = ? WHERE employee_email = ?");
                    $stmt->bind_param("sss", $reset_token, $expiry, $email);
                    
                    if ($stmt->execute()) {
                        $current_file = basename($_SERVER['PHP_SELF']);
                        $base_url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
                        $reset_link = $base_url . "/reset_password.php?token=$reset_token&type=employee";
                        
                        if (sendResetEmail($email, $reset_link)) {
                            $success_message = "✅ Password reset link sent to your email.";
                        } else {
                            $error_message = "❌ Failed to send reset email. Please try again.";
                        }
                    } else {
                        $error_message = "❌ Database error. Please try again.";
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_reset_page ? 'TaskFlow - Reset Password' : 'TaskFlow - Sign In'; ?></title>
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
        <?php if (!$is_reset_page): ?>
        .main-container {
            display: flex;
            width: 900px;
            height: 550px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .left-section {
            flex: 1;
            padding: 30px;
            background: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .right-section {
            flex: 1;
            background: #f8f9fa;
            position: relative;
            overflow: hidden;
        }
        <?php else: ?>
        .reset-container {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 450px;
            text-align: center;
        }
        <?php endif; ?>
        .logo {
            font-size: <?php echo $is_reset_page ? '2rem' : '1.5rem'; ?>;
            font-weight: 700;
            color: #3498db;
            margin-bottom: <?php echo $is_reset_page ? '10px' : '5px'; ?>;
            text-align: center;
        }
        .tagline {
            color: #6c757d;
            <?php if (!$is_reset_page): ?>text-align: center;<?php endif; ?>
            margin-bottom: <?php echo $is_reset_page ? '30px' : '15px'; ?>;
            font-size: <?php echo $is_reset_page ? '0.9rem' : '0.8rem'; ?>;
        }
        .form-title {
            font-size: <?php echo $is_reset_page ? '1.5rem' : '1.2rem'; ?>;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: <?php echo $is_reset_page ? '20px' : '15px'; ?>;
            text-align: center;
        }
        .input-group {
            position: relative;
            margin-bottom: <?php echo $is_reset_page ? '20px' : '12px'; ?>;
            <?php if ($is_reset_page): ?>text-align: left;<?php endif; ?>
        }
        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #3498db;
            font-size: <?php echo $is_reset_page ? '16px' : '14px'; ?>;
            z-index: 2;
        }
        .form-input {
            width: 100%;
            padding: <?php echo $is_reset_page ? '15px 15px 15px 45px' : '10px 10px 10px 40px'; ?>;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: <?php echo $is_reset_page ? '14px' : '13px'; ?>;
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
        <?php if (!$is_reset_page): ?>
        .form-row {
            display: flex;
            gap: 8px;
            margin-bottom: 12px;
        }
        .form-row .input-group {
            margin-bottom: 0;
            flex: 1;
        }
        <?php endif; ?>
        .submit-btn {
            width: 100%;
            padding: <?php echo $is_reset_page ? '15px' : '10px'; ?>;
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: <?php echo $is_reset_page ? '14px' : '13px'; ?>;
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
            padding: <?php echo $is_reset_page ? '15px' : '8px'; ?>;
            border-radius: 12px;
            margin-bottom: <?php echo $is_reset_page ? '20px' : '12px'; ?>;
            text-align: center;
            font-weight: 500;
            font-size: <?php echo $is_reset_page ? '14px' : '12px'; ?>;
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
        <?php if (!$is_reset_page): ?>
        .email-info {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 10px;
            border-radius: 12px;
            margin-bottom: 12px;
            text-align: center;
            font-size: 12px;
        }
        <?php else: ?>
        .email-info {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        <?php endif; ?>
        <?php if (!$is_reset_page): ?>
        .side-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            position: absolute;
            top: 0;
            left: 0;
        }
        .forgot-password {
            text-align: center;
            margin-top: 8px;
        }
        .forgot-link {
            color: #3498db;
            text-decoration: none;
            font-size: 12px;
            cursor: pointer;
        }
        .forgot-link:hover {
            text-decoration: underline;
        }
        .login-tabs {
            display: flex;
            margin-bottom: 15px;
            background: #f8f9fa;
            border-radius: 50px;
            padding: 5px;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
        }
        .tab-button {
            flex: 1;
            padding: 8px 16px;
            border: none;
            border-radius: 50px;
            background: transparent;
            color: #6c757d;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 13px;
        }
        .tab-button.active {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.4);
        }
        <?php else: ?>
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
        <?php endif; ?>
        @media (max-width: 768px) {
            <?php if (!$is_reset_page): ?>
            .main-container {
                flex-direction: column;
                width: 95%;
                height: auto;
            }
            .left-section, .right-section {
                padding: 20px;
            }
            .right-section {
                display: none;
            }
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            <?php endif; ?>
        }
    </style>
</head>
<body>
    <?php if ($is_reset_page): ?>
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
                    
                    <a href="login.php" class="back-link">
                        <i class="fas fa-arrow-left"></i> Back to Sign In
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="main-container">
            <div class="left-section">
                <div class="logo">
                    <i class="fas fa-tasks"></i> TaskFlow
                </div>
                <div class="tagline">Streamline Your Workflow</div>
                
                <?php if (isset($success_message)): ?>
                    <div class="message success">
                        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="message error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($action !== 'forgot_password'): ?>
                    <div class="login-tabs">
                        <button class="tab-button <?php echo $login_type === 'company' ? 'active' : ''; ?>" onclick="setLoginType('company')">
                            <i class="fas fa-building"></i> Company
                        </button>
                        <button class="tab-button <?php echo $login_type === 'employee' ? 'active' : ''; ?>" onclick="setLoginType('employee')">
                            <i class="fas fa-user-tie"></i> Employee
                        </button>
                    </div>
                <?php endif; ?>
                
                <div class="form-container">
                    <?php if ($action !== 'forgot_password'): ?>
                        <h2 class="form-title">Sign In</h2>
                        <form method="POST">
                            <input type="hidden" name="action" value="login">
                            <div class="form-row">
                                <div class="input-group">
                                    <i class="fas fa-id-card input-icon"></i>
                                    <input type="text" name="company_id" class="form-input" placeholder="Company ID" required>
                                </div>
                                <div class="input-group">
                                    <i class="fas fa-key input-icon"></i>
                                    <input type="text" name="company_code" class="form-input" placeholder="Company Code" required>
                                </div>
                            </div>
                            <div class="input-group">
                                <i class="fas fa-envelope input-icon"></i>
                                <input type="email" name="<?php echo $login_type === 'company' ? 'admin_email' : 'employee_email'; ?>" class="form-input" placeholder="<?php echo $login_type === 'company' ? 'Admin Email' : 'Employee Email'; ?>" required>
                            </div>
                            <div class="input-group">
                                <i class="fas fa-lock input-icon"></i>
                                <input type="password" name="password" class="form-input" placeholder="Password" required>
                            </div>
                            <button type="submit" class="submit-btn">
                                <i class="fas fa-sign-in-alt"></i> Sign In
                            </button>
                            <div class="forgot-password">
                                <a class="forgot-link" onclick="showForgotForm()">Forgot Password?</a>
                            </div>
                        </form>
                    <?php else: ?>
                        <h2 class="form-title">Reset Password</h2>
                        <form method="POST">
                            <input type="hidden" name="action" value="forgot_password">
                            <input type="hidden" name="login_type" value="<?php echo $login_type; ?>">
                            <div class="input-group">
                                <i class="fas fa-envelope input-icon"></i>
                                <input type="email" name="email" class="form-input" placeholder="<?php echo $login_type === 'company' ? 'Admin Email' : 'Employee Email'; ?>" required>
                            </div>
                            <button type="submit" class="submit-btn">
                                <i class="fas fa-paper-plane"></i> Send Reset Link
                            </button>
                            <div class="forgot-password">
                                <a class="forgot-link" onclick="hideForgotForm()">Back to Sign In</a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            <div class="right-section">
                <img src="taskflow.png" alt="TaskFlow" class="side-image">
            </div>
        </div>
    <?php endif; ?>
    
    <script>
        <?php if ($is_reset_page): ?>
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
        <?php else: ?>
        function setLoginType(type) {
            window.location.href = `?login_type=${type}`;
        }
        
        function showForgotForm() {
            window.location.href = `?login_type=<?php echo $login_type; ?>&action=forgot_password`;
        }
        
        function hideForgotForm() {
            window.location.href = `?login_type=<?php echo $login_type; ?>`;
        }
        <?php endif; ?>
        
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