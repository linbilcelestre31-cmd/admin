<?php
session_start();
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../../PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../../include/Config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error = '';
$step = 1; // 1: Login, 2: 2FA OTP

// Handle Login Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = get_pdo();

    if (isset($_POST['action']) && $_POST['action'] === 'login') {
        $username = trim($_POST['username']);
        $password = $_POST['password'];

        $stmt = $pdo->prepare("SELECT * FROM administrators WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            // Generate OTP
            $otp = random_int(100000, 999999);
            $_SESSION['temp_admin_id'] = $admin['admin_id'];
            $_SESSION['temp_admin_email'] = $admin['email'];
            $_SESSION['login_otp'] = $otp;
            $_SESSION['otp_expiry'] = time() + (5 * 60); // 5 minutes

            // Send OTP via PHPMailer
            try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = SMTP_HOST;
                $mail->SMTPAuth = true;
                $mail->Username = SMTP_USER;
                $mail->Password = SMTP_PASS;
                $mail->Port = SMTP_PORT;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                );

                $mail->setFrom(SMTP_FROM_EMAIL, 'ATIERA Security');
                $mail->addAddress($admin['email'], $admin['full_name']);
                $mail->isHTML(true);
                $mail->Subject = '🔐 Super Admin Login Verification';

                $mail->Body = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e1e1e1; border-radius: 10px; background-color: #ffffff;'>
                        <div style='text-align: center; margin-bottom: 20px;'>
                            <h2 style='color: #1e3a8a; margin: 0;'>Security Verification</h2>
                            <p style='color: #64748b;'>Super Admin Portal Access</p>
                        </div>
                        <div style='padding: 20px; background-color: #f8fafc; border-radius: 8px; text-align: center;'>
                            <p style='font-size: 16px; color: #334155;'>Your one-time verification code is:</p>
                            <h1 style='font-size: 48px; color: #1e40af; letter-spacing: 15px; margin: 20px 0;'>$otp</h1>
                            <p style='font-size: 14px; color: #94a3b8;'>This code will expire in 5 minutes.</p>
                        </div>
                        <p style='margin-top: 20px; font-size: 14px; color: #64748b;'>
                            If you didn't attempt to log in to the ATIERA Super Admin Portal, please contact the security team immediately.
                        </p>
                        <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;'>
                        <p style='font-size: 12px; color: #94a3b8; text-align: center;'>
                            &copy; " . date('Y') . " ATIERA Administrative System. All rights reserved.
                        </p>
                    </div>
                ";

                $mail->send();
                $step = 2; // Move to OTP step
            } catch (Exception $e) {
                $error = "Mailer Error: " . $mail->ErrorInfo;
                // For development, if mail fails, just show the OTP (REMOVE IN PRODUCTION)
                // $error .= " (Development OTP: $otp)";
            }
        } else {
            $error = "Invalid username or password.";
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'verify_otp') {
        $entered_otp = $_POST['otp'];
        if (time() > $_SESSION['otp_expiry']) {
            $error = "OTP has expired. Please try again.";
            $step = 1;
        } elseif ($entered_otp == $_SESSION['login_otp']) {
            // Success!
            $_SESSION['user_id'] = $_SESSION['temp_admin_id'];
            $_SESSION['role'] = 'super_admin';
            $_SESSION['username'] = $username;

            // Clean up
            unset($_SESSION['temp_admin_id']);
            unset($_SESSION['temp_admin_email']);
            unset($_SESSION['login_otp']);
            unset($_SESSION['otp_expiry']);

            // Update last login
            $pdo->prepare("UPDATE administrators SET last_login = NOW() WHERE admin_id = ?")->execute([$_SESSION['user_id']]);

            header('Location: ../Dashboard.php');
            exit;
        } else {
            $error = "Invalid verification code.";
            $step = 2;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Login | ATIERA</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1e40af;
            --primary-dark: #1e3a8a;
            --accent: #3b82f6;
            --glass: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
            --text-white: #ffffff;
            --text-gray: #94a3b8;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Outfit', sans-serif;
        }

        body {
            background: #0f172a;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            /* Move form to the right */
            overflow: hidden;
            position: relative;
            padding-right: 10%;
            /* Spacing from right */
        }

        /* Generated background container */
        .bg-container {
            position: absolute;
            inset: 0;
            background: #000;
            z-index: -1;
        }

        .bg-overlay {
            position: absolute;
            inset: 0;
            background-image: url('../css/super-admin.jpeg');
            /* Use the JPEG file */
            background-size: cover;
            background-position: center;
            opacity: 1;
        }

        /* Left side glass panel for aesthetics */
        .glass-panel {
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 50%;
            background: linear-gradient(to right, rgba(15, 23, 42, 0.8) 0%, rgba(15, 23, 42, 0) 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding-left: 5%;
            z-index: 5;
            pointer-events: none;
        }

        .glass-panel h1 {
            font-size: 4.5rem;
            color: white;
            font-weight: 800;
            margin: 0;
            text-shadow: 0 4px 10px rgba(0, 0, 0, 0.5);
        }

        .glass-panel p {
            font-size: 1.25rem;
            color: var(--accent);
            letter-spacing: 5px;
            text-transform: uppercase;
        }

        .animated-shapes div {
            position: absolute;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.2) 0%, rgba(59, 130, 246, 0) 70%);
            filter: blur(40px);
            z-index: 0;
            animation: float 20s infinite ease-in-out;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0) scale(1);
            }

            50% {
                transform: translateY(-50px) scale(1.1);
            }
        }

        .login-card {
            width: 100%;
            max-width: 450px;
            padding: 50px;
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 30px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            position: relative;
            z-index: 10;
            text-align: center;
            animation: slideUp 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo-area {
            margin-bottom: 40px;
        }

        .logo-title {
            color: var(--text-white);
            font-size: 32px;
            font-weight: 700;
            letter-spacing: 2px;
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .logo-subtitle {
            color: var(--accent);
            font-size: 14px;
            font-weight: 400;
            letter-spacing: 4px;
            text-transform: uppercase;
        }

        .welcome-text {
            color: var(--text-white);
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .instruction-text {
            color: var(--text-gray);
            font-size: 15px;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
            text-align: left;
        }

        .form-group i {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-gray);
            transition: color 0.3s;
        }

        .input-control {
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 15px 15px 15px 50px;
            color: var(--text-white);
            font-size: 16px;
            outline: none;
            transition: all 0.3s;
        }

        .input-control:focus {
            background: rgba(255, 255, 255, 0.08);
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }

        .input-control:focus+i {
            color: var(--accent);
        }

        .btn-login {
            width: 100%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 16px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
            box-shadow: 0 10px 15px -3px rgba(30, 64, 175, 0.3);
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(30, 64, 175, 0.4);
            filter: brightness(1.1);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .error-message {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            padding: 12px;
            border-radius: 10px;
            font-size: 14px;
            margin-bottom: 20px;
            border: 1px solid rgba(239, 68, 68, 0.2);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .otp-inputs {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 30px 0;
        }

        .otp-field {
            width: 100%;
            letter-spacing: 15px;
            text-align: center;
            padding-left: 20px;
            font-size: 24px;
            font-weight: 700;
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: var(--text-gray);
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s;
        }

        .back-link:hover {
            color: var(--text-white);
        }

        @media (max-width: 480px) {
            .login-card {
                padding: 30px;
                border-radius: 20px;
                margin: 20px;
            }
        }
    </style>
</head>

<body>
    <div class="bg-container">
        <div class="bg-overlay"></div>
        <div class="glass-panel">
            <h1>ATIÉRA</h1>
            <p>ADMINISTRATIVE SYSTEM</p>
        </div>
        <div class="animated-shapes">
            <div style="width: 400px; height: 400px; left: -100px; top: -100px;"></div>
            <div style="width: 300px; height: 300px; right: -50px; bottom: -50px; animation-delay: -5s;"></div>
            <div style="width: 250px; height: 250px; left: 50%; top: 50%; animation-delay: -10s;"></div>
        </div>
    </div>

    <div class="login-card">
        <div class="logo-area">
            <h1 class="logo-title">ATIÉRA</h1>
            <p class="logo-subtitle">ADMINISTRATIVE</p>
        </div>

        <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <h2 class="welcome-text">Super Admin Portal</h2>
            <p class="instruction-text">Enter your credentials to access the secure administrative area.</p>

            <form action="" method="POST">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <input type="text" name="username" class="input-control" placeholder="Username" required
                        autocomplete="username">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="form-group">
                    <input type="password" name="password" class="input-control" placeholder="Password" required
                        autocomplete="current-password">
                    <i class="fas fa-lock"></i>
                </div>
                <button type="submit" class="btn-login">
                    Initialize Access <i class="fas fa-arrow-right" style="margin-left: 10px;"></i>
                </button>
            </form>
        <?php else: ?>
            <h2 class="welcome-text">Two-Factor Auth</h2>
            <p class="instruction-text">We've sent a 6-digit verification code to your registered email ending in
                <strong>
                    <?php echo substr($_SESSION['temp_admin_email'], -10); ?>
                </strong>.
            </p>

            <form action="" method="POST">
                <input type="hidden" name="action" value="verify_otp">
                <div class="form-group">
                    <input type="text" name="otp" class="input-control otp-field" maxlength="6" placeholder="000000"
                        required autofocus>
                </div>
                <button type="submit" class="btn-login">
                    Verify & Login <i class="fas fa-shield-check" style="margin-left: 10px;"></i>
                </button>
            </form>
            <a href="login.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to login</a>
        <?php endif; ?>

        <div style="margin-top: 40px; color: var(--text-gray); font-size: 11px; letter-spacing: 1px;">
            SECURE ACCESS PROTOCOL &bull; ENCRYPT_SESSION_ID v2.4
        </div>
    </div>
</body>

</html>