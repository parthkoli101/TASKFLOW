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

function sendOTPEmail($to_email, $otp) {
    $mail = new PHPMailer(true);
    
    try {
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->Host = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['SMTP_USERNAME'];
        $mail->Password = $_ENV['SMTP_PASSWORD'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $_ENV['SMTP_PORT'] ?? 587;
        
        $mail->Timeout = 60;
        $mail->SMTPKeepAlive = true;
        
        $mail->setFrom($_ENV['FROM_EMAIL'] ?? $_ENV['SMTP_USERNAME'], $_ENV['FROM_NAME'] ?? 'TaskFlow');
        $mail->addAddress($to_email);
        $mail->addReplyTo($_ENV['FROM_EMAIL'] ?? $_ENV['SMTP_USERNAME'], $_ENV['FROM_NAME'] ?? 'TaskFlow');
        
        $mail->isHTML(true);
        $mail->Subject = 'Your TaskFlow OTP Verification Code';
        $mail->Body = "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
                <h2 style='color: #3498db; text-align: center;'>TaskFlow Verification</h2>
                <p style='font-size: 16px;'>Hello,</p>
                <p style='font-size: 16px;'>Your OTP verification code is:</p>
                <div style='text-align: center; font-size: 32px; font-weight: bold; color: #3498db; padding: 20px; background: #f8f9fa; border-radius: 10px; margin: 20px 0;'>
                    $otp
                </div>
                <p style='font-size: 14px; color: #666;'>This OTP will expire in 5 minutes.</p>
                <p style='font-size: 14px; color: #666;'>If you did not request this, please ignore this email.</p>
                <hr style='margin: 20px 0; border: none; border-top: 1px solid #ddd;'>
                <p style='font-size: 12px; color: #999; text-align: center;'>© TaskFlow - Streamline Your Workflow</p>
            </div>
        </body>
        </html>";
        
        $mail->AltBody = "Your TaskFlow OTP verification code is: $otp\n\nThis OTP expires in 5 minutes.\n\nIf you did not request this, please ignore this email.";
        
        $result = $mail->send();
        $mail->smtpClose();
        
        return $result;
        
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        error_log("Exception: " . $e->getMessage());
        return false;
    }
}

$action = isset($_POST['action']) ? $_POST['action'] : 'show_form';
$registration_type = isset($_GET['registration_type']) ? $_GET['registration_type'] : 'company';

if ($action === 'send_otp') {
    $otp = rand(100000, 999999);
    $expiry = date("Y-m-d H:i:s", strtotime("+5 minutes"));
    
    if ($registration_type === 'company') {
        $admin_email = filter_var($_POST['admin_email'], FILTER_VALIDATE_EMAIL);
        $company_id = trim($_POST['company_id']);
        
        if (!$admin_email) {
            $error_message = "❌ Invalid email format.";
        } else {
            $stmt = $conn->prepare("SELECT admin_email FROM company_registration WHERE admin_email = ?");
            $stmt->bind_param("s", $admin_email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error_message = "❌ Email already exists.";
            } else {
                $stmt = $conn->prepare("SELECT company_id FROM company_registration WHERE company_id = ?");
                $stmt->bind_param("s", $company_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $error_message = "❌ Company already exists.";
                } else {
                    $_SESSION['temp_data'] = $_POST;
                    $_SESSION['email'] = $admin_email;
                    $_SESSION['otp'] = $otp;
                    $_SESSION['otp_expiry'] = $expiry;
                    
                    if (sendOTPEmail($admin_email, $otp)) {
                        $action = 'verify_otp';
                        $success_message = "✅ OTP sent successfully to $admin_email";
                    } else {
                        $error_message = "❌ Failed to send OTP email. Please check your email settings and try again.";
                    }
                }
            }
            $stmt->close();
        }
        
    } elseif ($registration_type === 'employee') {
        $employee_email = filter_var($_POST['employee_email'], FILTER_VALIDATE_EMAIL);
        $company_id = trim($_POST['company_id']);
        $company_code = $_POST['company_code'];
        
        if (!$employee_email) {
            $error_message = "❌ Invalid email format.";
        } else {
            $stmt = $conn->prepare("SELECT company_id, company_code FROM company_registration WHERE company_id = ?");
            $stmt->bind_param("s", $company_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $error_message = "❌ Company does not exist.";
            } else {
                $company_data = $result->fetch_assoc();
                
                if (!password_verify($company_code, $company_data['company_code'])) {
                    $error_message = "❌ Invalid company code.";
                } else {
                    $stmt = $conn->prepare("SELECT employee_email FROM employee_registration WHERE employee_email = ?");
                    $stmt->bind_param("s", $employee_email);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $error_message = "❌ Employee email already exists.";
                    } else {
                        $_SESSION['temp_data'] = $_POST;
                        $_SESSION['email'] = $employee_email;
                        $_SESSION['otp'] = $otp;
                        $_SESSION['otp_expiry'] = $expiry;
                        
                        if (sendOTPEmail($employee_email, $otp)) {
                            $action = 'verify_otp';
                            $success_message = "✅ OTP sent successfully to $employee_email";
                        } else {
                            $error_message = "❌ Failed to send OTP email. Please check your email settings and try again.";
                        }
                    }
                }
            }
            $stmt->close();
        }
    }
}

if ($action === 'check_otp') {
    if (!isset($_SESSION['email']) || !isset($_SESSION['otp']) || !isset($_SESSION['otp_expiry'])) {
        $error_message = "❌ Session expired. Please start the registration process again.";
        $action = 'show_form';
    } else {
        $email = $_SESSION['email'];
        $entered_otp = trim($_POST['otp']);
        $otp = $_SESSION['otp'];
        $expiry = $_SESSION['otp_expiry'];
        
        if ($entered_otp == $otp && strtotime($expiry) > time()) {
            $temp_data = $_SESSION['temp_data'];
            
            if ($registration_type === 'company') {
                $hashed_password = password_hash($temp_data['admin_password'], PASSWORD_DEFAULT);
                $hashed_company_code = password_hash($temp_data['company_code'], PASSWORD_DEFAULT);
                
                $stmt = $conn->prepare("INSERT INTO company_registration (company_name, company_id, company_code, admin_firstname, admin_lastname, admin_email, admin_password, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
                $stmt->bind_param("sssssss", $temp_data['company_name'], $temp_data['company_id'], $hashed_company_code, $temp_data['admin_firstname'], $temp_data['admin_lastname'], $temp_data['admin_email'], $hashed_password);
                
                if ($stmt->execute()) {
                    unset($_SESSION['temp_data'], $_SESSION['email'], $_SESSION['otp'], $_SESSION['otp_expiry']);
                    $success_message = "✅ OTP Verified Successfully! Registration Complete.";
                    header("Location: admin.php");
                    exit();
                } else {
                    $error_message = "❌ Registration failed. Please try again.";
                }
                $stmt->close();
                
            } elseif ($registration_type === 'employee') {
                $hashed_password = password_hash($temp_data['employee_password'], PASSWORD_DEFAULT);
                
                $stmt = $conn->prepare("INSERT INTO employee_registration (employee_firstname, employee_lastname, employee_email, employee_password, company_id, company_code, is_verified) VALUES (?, ?, ?, ?, ?, ?, 1)");
                $stmt->bind_param("ssssss", $temp_data['employee_firstname'], $temp_data['employee_lastname'], $temp_data['employee_email'], $hashed_password, $temp_data['company_id'], $temp_data['company_code']);
                
                if ($stmt->execute()) {
                    unset($_SESSION['temp_data'], $_SESSION['email'], $_SESSION['otp'], $_SESSION['otp_expiry']);
                    $success_message = "✅ OTP Verified Successfully! Registration Complete.";
                    header("Location: employee.php");
                    exit();
                } else {
                    $error_message = "❌ Registration failed. Please try again.";
                }
                $stmt->close();
            }
        } else {
            $error_message = "❌ Invalid or Expired OTP.";
            $action = 'verify_otp';
        }
    }
}

if ($action === 'resend_otp') {
    if (!isset($_SESSION['email'])) {
        $error_message = "❌ Session expired. Please start the registration process again.";
        $action = 'show_form';
    } else {
        $email = $_SESSION['email'];
        $otp = rand(100000, 999999);
        $expiry = date("Y-m-d H:i:s", strtotime("+5 minutes"));
        
        $_SESSION['otp'] = $otp;
        $_SESSION['otp_expiry'] = $expiry;
        
        if (sendOTPEmail($email, $otp)) {
            $success_message = "✅ New OTP sent to $email";
            $action = 'verify_otp';
        } else {
            $error_message = "❌ Failed to resend OTP. Please try again.";
            $action = 'verify_otp';
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ($action === 'verify_otp') ? 'Verify OTP - TaskFlow' : 'Registration - TaskFlow'; ?></title>
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
            padding: 25px;
            background: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            overflow: hidden;
        }
        .right-section {
            flex: 1;
            background: #f8f9fa;
            position: relative;
            overflow: hidden;
        }
        .logo {
            font-size: 1.6rem;
            font-weight: 700;
            color: #3498db;
            margin-bottom: 8px;
            text-align: center;
        }
        .tagline {
            color: #6c757d;
            text-align: center;
            margin-bottom: 15px;
            font-size: 0.85rem;
        }
        .registration-tabs {
            display: flex;
            margin-bottom: 15px;
            background: #f8f9fa;
            border-radius: 50px;
            padding: 4px;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
        }
        .tab-button {
            flex: 1;
            padding: 6px 14px;
            border: none;
            border-radius: 50px;
            background: transparent;
            color: #6c757d;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 12px;
        }
        .tab-button.active {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.4);
        }
        .form-container {
            width: 100%;
            max-width: 350px;
            margin: 0 auto;
        }
        .form-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 15px;
            text-align: center;
        }
        .input-group {
            position: relative;
            margin-bottom: 12px;
        }
        .input-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #3498db;
            font-size: 14px;
            z-index: 2;
        }
        .form-input {
            width: 100%;
            padding: 9px 9px 9px 35px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 13px;
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
        .form-row {
            display: flex;
            gap: 8px;
            margin-bottom: 12px;
        }
        .form-row .input-group {
            margin-bottom: 0;
            flex: 1;
        }
        .submit-btn {
            width: 100%;
            padding: 10px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 13px;
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
        .otp-input {
            text-align: center;
            font-size: 18px;
            font-weight: 600;
            letter-spacing: 4px;
            padding: 10px 8px;
        }
        .timer-section {
            text-align: center;
            margin: 12px 0;
            padding: 10px;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border-radius: 10px;
            color: white;
        }
        .timer-text {
            font-size: 12px;
            margin-bottom: 4px;
        }
        .timer-display {
            font-size: 18px;
            font-weight: 700;
        }
        .resend-btn {
            width: 100%;
            padding: 8px;
            background: transparent;
            color: #3498db;
            border: 2px solid #3498db;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 8px;
        }
        .resend-btn:hover {
            background: #3498db;
            color: white;
        }
        .message {
            padding: 8px;
            border-radius: 10px;
            margin-bottom: 12px;
            text-align: center;
            font-weight: 500;
            font-size: 12px;
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
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 12px;
            text-align: center;
            font-size: 12px;
        }
        .side-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            position: absolute;
            top: 0;
            left: 0;
        }
        @media (max-width: 768px) {
            .main-container {
                flex-direction: column;
                width: 95%;
                height: auto;
                min-height: auto;
                max-height: none;
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
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="left-section">
            <div class="logo">
                <i class="fas fa-tasks"></i> TaskFlow
            </div>
            <div class="tagline">Streamline Your Workflow</div>
            
            <?php if ($action !== 'verify_otp'): ?>
            <div class="registration-tabs">
                <button class="tab-button <?php echo $registration_type === 'company' ? 'active' : ''; ?>" onclick="setRegistrationType('company')">
                    <i class="fas fa-building"></i> Company
                </button>
                <button class="tab-button <?php echo $registration_type === 'employee' ? 'active' : ''; ?>" onclick="setRegistrationType('employee')">
                    <i class="fas fa-user-tie"></i> Employee
                </button>
            </div>
            <?php endif; ?>
            
            <div class="form-container">
                <?php if (isset($success_message)): ?>
                    <div class="message success">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="message error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($action === 'show_form'): ?>
                    <?php if ($registration_type === 'company'): ?>
                        <h2 class="form-title">Company Registration</h2>
                        <form method="POST">
                            <input type="hidden" name="action" value="send_otp">
                            <div class="input-group">
                                <i class="fas fa-building input-icon"></i>
                                <input type="text" name="company_name" class="form-input" placeholder="Company Name" required>
                            </div>
                            <div class="form-row">
                                <div class="input-group">
                                    <i class="fas fa-id-card input-icon"></i>
                                    <input type="text" name="company_id" class="form-input" placeholder="Company ID" required>
                                </div>
                                <div class="input-group">
                                    <i class="fas fa-key input-icon"></i>
                                    <input type="password" name="company_code" class="form-input" placeholder="Company Code" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="input-group">
                                    <i class="fas fa-user input-icon"></i>
                                    <input type="text" name="admin_firstname" class="form-input" placeholder="First Name" required>
                                </div>
                                <div class="input-group">
                                    <i class="fas fa-user input-icon"></i>
                                    <input type="text" name="admin_lastname" class="form-input" placeholder="Last Name" required>
                                </div>
                            </div>
                            <div class="input-group">
                                <i class="fas fa-envelope input-icon"></i>
                                <input type="email" name="admin_email" class="form-input" placeholder="Admin Email" required>
                            </div>
                            <div class="input-group">
                                <i class="fas fa-lock input-icon"></i>
                                <input type="password" name="admin_password" class="form-input" placeholder="Admin Password" required>
                            </div>
                            <button type="submit" class="submit-btn">
                                <i class="fas fa-paper-plane"></i> Send OTP
                            </button>
                        </form>
                    <?php elseif ($registration_type === 'employee'): ?>
                        <h2 class="form-title">Employee Registration</h2>
                        <form method="POST">
                            <input type="hidden" name="action" value="send_otp">
                            <div class="form-row">
                                <div class="input-group">
                                    <i class="fas fa-user input-icon"></i>
                                    <input type="text" name="employee_firstname" class="form-input" placeholder="First Name" required>
                                </div>
                                <div class="input-group">
                                    <i class="fas fa-user input-icon"></i>
                                    <input type="text" name="employee_lastname" class="form-input" placeholder="Last Name" required>
                                </div>
                            </div>
                            <div class="input-group">
                                <i class="fas fa-envelope input-icon"></i>
                                <input type="email" name="employee_email" class="form-input" placeholder="Employee Email" required>
                            </div>
                            <div class="input-group">
                                <i class="fas fa-lock input-icon"></i>
                                <input type="password" name="employee_password" class="form-input" placeholder="Password" required>
                            </div>
                            <div class="form-row">
                                <div class="input-group">
                                    <i class="fas fa-id-card input-icon"></i>
                                    <input type="text" name="company_id" class="form-input" placeholder="Company ID" required>
                                </div>
                                <div class="input-group">
                                    <i class="fas fa-key input-icon"></i>
                                    <input type="password" name="company_code" class="form-input" placeholder="Company Code" required>
                                </div>
                            </div>
                            <button type="submit" class="submit-btn">
                                <i class="fas fa-paper-plane"></i> Send OTP
                            </button>
                        </form>
                    <?php endif; ?>
                    
                <?php elseif ($action === 'verify_otp' && isset($_SESSION['email'])): ?>
                    <h2 class="form-title">
                        <i class="fas fa-shield-alt"></i> Verify OTP
                    </h2>
                    <div class="email-info">
                        <i class="fas fa-envelope"></i><br>
                        <strong>OTP sent to:</strong><br>
                        <?php echo htmlspecialchars($_SESSION['email']); ?>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="check_otp">
                        <div class="input-group">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="text" name="otp" class="form-input otp-input" placeholder="000000" maxlength="6" pattern="[0-9]{6}" required autocomplete="off">
                        </div>
                        <button type="submit" class="submit-btn">
                            <i class="fas fa-check"></i> Verify OTP
                        </button>
                    </form>
                    <div class="timer-section">
                        <div class="timer-text">OTP expires in:</div>
                        <div class="timer-display" id="time">5:00</div>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="resend_otp">
                        <button type="submit" class="resend-btn" id="resend-btn">
                            <i class="fas fa-redo"></i> Resend OTP
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="right-section">
            <img src="taskflow.png" alt="TaskFlow" class="side-image">
        </div>
    </div>

    <script>
        function setRegistrationType(type) {
            window.location.href = `?registration_type=${type}`;
        }

        <?php if ($action === 'verify_otp'): ?>
        let timer = 300;
        const timerElement = document.getElementById("time");
        const resendBtn = document.getElementById("resend-btn");

        const countdown = setInterval(() => {
            if (timer > 0) {
                timer--;
                const mins = Math.floor(timer / 60);
                const secs = timer % 60;
                timerElement.textContent = `${mins}:${secs < 10 ? '0' : ''}${secs}`;
                
                if (timer <= 60) {
                    timerElement.style.color = '#ff6b6b';
                    timerElement.parentElement.style.background = 'linear-gradient(135deg, #ff6b6b, #ee5a52)';
                }
            } else {
                timerElement.textContent = "Expired";
                timerElement.style.color = '#ff6b6b';
                timerElement.parentElement.style.background = 'linear-gradient(135deg, #ff6b6b, #ee5a52)';
                resendBtn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> OTP Expired - Resend';
                clearInterval(countdown);
            }
        }, 1000);

        document.querySelector('input[name="otp"]').focus();
        
        document.querySelector('input[name="otp"]').addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/[^0-9]/g, '');
            if (e.target.value.length === 6) {
                setTimeout(() => {
                    e.target.closest('form').submit();
                }, 500);
            }
        });
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