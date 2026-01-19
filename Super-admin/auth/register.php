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
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $pdo = get_pdo();
    $sa_table = 'SuperAdminLogin_tb';

    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Check if username or email exists
        $stmt = $pdo->prepare("SELECT id FROM `$sa_table` WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $error = "Username or Email already exists.";
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $api_key = bin2hex(random_bytes(32));

            $stmt = $pdo->prepare("INSERT INTO `$sa_table` (username, email, password_hash, full_name, api_key, role, is_active) VALUES (?, ?, ?, ?, ?, 'super_admin', 1)");
            if ($stmt->execute([$username, $email, $password_hash, $full_name, $api_key])) {

                // Send Welcome Email
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

                    $mail->setFrom(SMTP_FROM_EMAIL, 'ATIERA Systems');
                    $mail->addAddress($email, $full_name);
                    $mail->isHTML(true);
                    $mail->Subject = '🛡️ Super Admin Account Created';

                    $mail->Body = "
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e1e1e1; border-radius: 10px; background-color: #ffffff;'>
                            <div style='text-align: center; margin-bottom: 20px;'>
                                <h2 style='color: #1e3a8a; margin: 0;'>Account Registration Successful</h2>
                                <p style='color: #64748b;'>Super Admin Portal Access Granted</p>
                            </div>
                            <div style='padding: 20px; background-color: #f8fafc; border-radius: 8px;'>
                                <p style='font-size: 16px; color: #334155;'>Hello <strong>$full_name</strong>,</p>
                                <p style='font-size: 14px; color: #334155;'>Your Super Admin account has been successfully created. You can now log in using your credentials.</p>
                                <div style='margin: 20px 0; padding: 15px; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px;'>
                                    <p style='margin: 5px 0; font-size: 13px;'><strong>Username:</strong> $username</p>
                                    <p style='margin: 5px 0; font-size: 13px;'><strong>Security Level:</strong> Super Administrator</p>
                                </div>
                                <p style='font-size: 13px; color: #64748b; line-height: 1.6;'>
                                    Please ensure you keep your credentials secure. For security reasons, never share your password or API key with anyone.
                                </p>
                            </div>
                            <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;'>
                            <p style='font-size: 12px; color: #94a3b8; text-align: center;'>
                                &copy; " . date('Y') . " ATIERA Administrative System. This is an automated security notification.
                            </p>
                        </div>
                    ";

                    $mail->send();
                    $success = "Registration successful! You can now sign in.";
                } catch (Exception $e) {
                    $success = "Registration successful! (Email could not be sent: " . $mail->ErrorInfo . ")";
                }
            } else {
                $error = "Registration failed. Please try again.";
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
    <title>ATIERA Registration | Superadmin</title>
    <link rel="icon" type="image/x-icon" href="../../assets/image/logo2.png">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-gold: #d4af37;
            --soft-gold: #fdf6e3;
            --dark-gold: #b8860b;
            --text-dark: #1e293b;
            --text-gray: #64748b;
            --glass-bg: rgba(255, 251, 235, 0.9);
            --glass-border: rgba(212, 175, 55, 0.3);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Outfit', sans-serif;
        }

        body {
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 8%;
            background: #0f172a;
            overflow: hidden;
        }

        /* Hide scrollbar */
        * {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        *::-webkit-scrollbar {
            display: none;
        }

        .bg-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background: url('../../assets/image/login.jpeg') center/cover no-repeat;
        }

        .bg-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.2);
        }

        .login-card {
            width: 100%;
            max-width: 450px;
            padding: 40px;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 2px solid var(--primary-gold);
            border-radius: 30px;
            box-shadow: 0 25px 50px -12px rgba(212, 175, 55, 0.2);
            text-align: center;
            position: relative;
            z-index: 10;
            max-height: 98vh;
            overflow-y: auto;
        }

        .logo-area {
            margin-bottom: 25px;
        }

        .logo-title {
            color: #0f172a;
            font-size: 32px;
            font-weight: 700;
            letter-spacing: 6px;
            margin-bottom: 5px;
        }

        .logo-subtitle {
            color: var(--primary-gold);
            font-size: 12px;
            font-weight: 400;
            letter-spacing: 3px;
            text-transform: uppercase;
        }

        .welcome-text {
            color: var(--text-dark);
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .instruction-text {
            color: var(--text-gray);
            font-size: 14px;
            margin-bottom: 20px;
        }

        .error-message {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 15px;
            font-size: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .success-message {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 15px;
            font-size: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .form-group {
            margin-bottom: 15px;
            position: relative;
            text-align: left;
        }

        .input-control {
            width: 100%;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 12px 12px 45px;
            color: var(--text-dark);
            font-size: 14px;
            outline: none;
            transition: all 0.3s;
        }

        .input-control:focus {
            border-color: var(--primary-gold);
            box-shadow: 0 0 0 4px rgba(212, 175, 55, 0.1);
        }

        .form-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-gray);
            font-size: 14px;
        }

        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, var(--primary-gold) 0%, var(--dark-gold) 100%);
            border: none;
            border-radius: 10px;
            color: white;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 8px 12px -3px rgba(212, 175, 55, 0.3);
            margin-top: 10px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 20px -5px rgba(212, 175, 55, 0.4);
        }

        .footer-link {
            margin-top: 20px;
            font-size: 13px;
            color: var(--text-gray);
        }

        .footer-link a {
            color: var(--dark-gold);
            text-decoration: none;
            font-weight: 600;
        }

        .footer-link a:hover {
            text-decoration: underline;
        }

        /* --- LOADING SCREEN STYLES --- */
        #loading-screen {
            position: fixed;
            inset: 0;
            background: url('../../assets/image/loading.png') center/cover no-repeat;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 99999;
            transition: opacity 0.8s ease, visibility 0.8s;
        }

        .loader-container {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .brand-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
        }

        .fire {
            position: relative;
            width: 60px;
            height: 60px;
        }

        .fire .flame {
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 60px;
            height: 60px;
            border-radius: 50% 0 50% 50%;
            background: linear-gradient(-45deg, #d4af37, #fcf6ba, #aa771c);
            transform: translateX(-50%) rotate(-45deg);
            box-shadow: 0 0 10px #d4af37, 0 0 20px #aa771c;
            animation: burn 1s infinite alternate ease-in-out;
            opacity: 0.9;
        }

        .fire .flame:nth-child(2) {
            width: 40px;
            height: 40px;
            bottom: 5px;
            background: linear-gradient(-45deg, #fcf6ba, #fff);
            animation: burn 1.5s infinite alternate-reverse ease-in-out;
            z-index: 2;
        }

        @keyframes burn {
            0% {
                transform: translateX(-50%) rotate(-45deg) scale(1);
                border-radius: 50% 0 50% 50%;
            }

            50% {
                transform: translateX(-50%) rotate(-45deg) scale(1.1);
                border-radius: 40% 10% 40% 40%;
            }

            100% {
                transform: translateX(-50%) rotate(-45deg) scale(1);
                border-radius: 50% 0 50% 50%;
            }
        }

        .brand-text-loader {
            font-family: 'Cinzel', serif;
            font-size: 3.5rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 5px;
            background: linear-gradient(90deg, #bf953f, #fcf6ba, #b38728, #fbf5b7, #aa771c);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            animation: shine 5s infinite linear;
            background-size: 200%;
        }

        @keyframes shine {
            to {
                background-position: 200% center;
            }
        }

        .tagline-loader {
            font-family: 'Montserrat', sans-serif;
            color: #E6C86E;
            font-weight: 700;
            letter-spacing: 6px;
            font-size: 0.8rem;
            text-transform: uppercase;
            margin-top: 15px;
            text-align: center;
        }

        .admin-wrapper-loader {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-top: 15px;
        }

        .admin-text-loader {
            font-family: 'Cinzel', serif;
            font-size: 1rem;
            color: #E6C86E;
            letter-spacing: 4px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .wave-text-loader span {
            display: inline-block;
            animation: letterWaveLoader 2s ease-in-out infinite;
            animation-delay: calc(0.1s * var(--i));
        }

        @keyframes letterWaveLoader {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-5px);
            }
        }

        .center-line-loader {
            height: 1px;
            width: 40px;
            background: linear-gradient(90deg, transparent, #d4af37, transparent);
        }

        .loading-line-loader {
            width: 250px;
            height: 2px;
            background: rgba(255, 255, 255, 0.1);
            margin-top: 30px;
            position: relative;
            overflow: hidden;
            border-radius: 4px;
        }

        .loading-progress-loader {
            position: absolute;
            height: 100%;
            width: 100%;
            background: linear-gradient(90deg, #E6C86E, #fcf6ba, #E6C86E);
            box-shadow: 0 0 10px #E6C86E;
            animation: loadProgress 1.5s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }

        @keyframes loadProgress {
            from {
                width: 0%;
            }

            to {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <!-- Loading Screen -->
    <div id="loading-screen">
        <div class="loader-container">
            <div class="brand-wrapper">
                <div class="fire">
                    <div class="flame"></div>
                    <div class="flame"></div>
                </div>
                <h1 class="brand-text-loader">ATIÉRA</h1>
            </div>
            <div class="tagline-loader wave-text-loader">Hotel & Restaurant</div>
            <div class="admin-wrapper-loader">
                <div class="center-line-loader"></div>
                <div class="admin-text-loader wave-text-loader">ADMINISTRATIVE</div>
                <div class="center-line-loader"></div>
            </div>
            <div class="loading-line-loader">
                <div class="loading-progress-loader"></div>
            </div>
        </div>
    </div>

    <script>
        // Wave Text Animation for Loader
        document.querySelectorAll('.wave-text-loader').forEach(container => {
            const text = container.textContent;
            container.innerHTML = '';
            [...text].forEach((letter, index) => {
                const span = document.createElement('span');
                span.textContent = letter === ' ' ? '\u00A0' : letter;
                span.style.setProperty('--i', index);
                container.appendChild(span);
            });
        });

        // Hide Loading Screen
        window.addEventListener('load', () => {
            const loader = document.getElementById('loading-screen');
            setTimeout(() => {
                loader.style.opacity = '0';
                setTimeout(() => {
                    loader.style.visibility = 'hidden';
                }, 800);
            }, 1000);
        });
    </script>

    <div class="bg-container">
        <div class="bg-overlay"></div>
    </div>

    <div class="login-card">
        <div class="logo-area">
            <h1 class="logo-title">ATIÉRA</h1>
            <p class="logo-subtitle">SUPERADMIN REGISTRATION</p>
        </div>

        <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <h2 class="welcome-text">Create Account</h2>
        <p class="instruction-text">Establish your super administrator credentials.</p>

        <form action="" method="POST">
            <input type="hidden" name="action" value="register">
            <div class="form-group">
                <input type="text" name="full_name" class="input-control" placeholder="Full Name" required>
                <i class="fas fa-user-tag"></i>
            </div>
            <div class="form-group">
                <input type="email" name="email" class="input-control" placeholder="Email Address" required>
                <i class="fas fa-envelope"></i>
            </div>
            <div class="form-group">
                <input type="text" name="username" class="input-control" placeholder="Username" required>
                <i class="fas fa-fingerprint"></i>
            </div>
            <div class="form-group">
                <input type="password" name="password" class="input-control" placeholder="Password" required>
                <i class="fas fa-lock"></i>
            </div>
            <div class="form-group">
                <input type="password" name="confirm_password" class="input-control" placeholder="Confirm Password"
                    required>
                <i class="fas fa-shield-halved"></i>
            </div>
            <button type="submit" class="btn-login">
                Create Account <i class="fas fa-user-plus" style="margin-left: 10px;"></i>
            </button>
        </form>

        <div class="footer-link">
            Already have an account? <a href="login.php">Sign In</a>
        </div>

        <p style="margin-top: 30px; color: var(--text-gray); font-size: 11px;">
            SECURE ACCESS PROTOCOL 5.0 • PROTECTED BY QUANTUM ENCRYPTION
        </p>
    </div>
</body>

</html>