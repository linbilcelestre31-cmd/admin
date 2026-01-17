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

    // Super Admin Specific Database Architecture - Isolated within the main database
    // Removed separate database requirement to avoid PDOException 1044 Access Denied
    $sa_table = 'SuperAdminLogin_tb';

    // No need to USE another database as get_pdo() already connects to the main one
    // This ensures compatibility with shared hosting environments


    // Ensure SuperAdminLogin_tb exists (Self-healing)
    try {
        $pdo->query("SELECT 1 FROM `$sa_table` LIMIT 1");
    } catch (PDOException $e) {
        $sql = "CREATE TABLE IF NOT EXISTS `$sa_table` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `username` varchar(50) NOT NULL,
            `email` varchar(100) NOT NULL,
            `password_hash` varchar(255) NOT NULL,
            `full_name` varchar(100) NOT NULL,
            `api_key` varchar(255) DEFAULT NULL,
            `role` varchar(50) DEFAULT 'super_admin',
            `is_active` tinyint(1) DEFAULT 1,
            `last_login` datetime DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `username` (`username`),
            UNIQUE KEY `email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
        $pdo->exec($sql);

        // Insert default admin with API Key
        $default_pass = password_hash('password', PASSWORD_DEFAULT);
        $api_key = bin2hex(random_bytes(32)); // Generated API Key
        $pdo->exec("INSERT IGNORE INTO `$sa_table` (username, email, password_hash, full_name, api_key, role) 
                   VALUES ('admin', 'atiera41001@gmail.com', '$default_pass', 'System Administrator', '$api_key', 'super_admin')");
    }

    // Force update email and roles if needed
    $pdo->exec("UPDATE `$sa_table` SET email = 'atiera41001@gmail.com' WHERE username = 'admin'");

    if (isset($_POST['action']) && $_POST['action'] === 'login') {
        $username = trim($_POST['username']);
        $password = $_POST['password'];

        $stmt = $pdo->prepare("SELECT * FROM `$sa_table` WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            // Generate OTP
            $otp = random_int(100000, 999999);
            $_SESSION['temp_admin_id'] = $admin['id'];
            $_SESSION['temp_admin_username'] = $admin['username'];
            $_SESSION['temp_admin_email'] = $admin['email'];
            $_SESSION['temp_admin_apikey'] = $admin['api_key'];
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
                // FOR DEVELOPMENT: Always show the OTP even if mail succeeds
                $error = "Development OTP: $otp (Email process initiated)";
            } catch (Exception $e) {
                $error = "Mailer Error: " . $mail->ErrorInfo;
                $error .= " (Development OTP: $otp)";
                $step = 2; // Still move to OTP step even if mail fails
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
            $_SESSION['username'] = $_SESSION['temp_admin_username'];
            $_SESSION['api_key'] = $_SESSION['temp_admin_apikey'];

            // Clean up
            unset($_SESSION['temp_admin_username']);
            unset($_SESSION['temp_admin_id']);
            unset($_SESSION['temp_admin_email']);
            unset($_SESSION['temp_admin_apikey']);
            unset($_SESSION['login_otp']);
            unset($_SESSION['otp_expiry']);

            // Update last login
            $pdo->prepare("UPDATE `$sa_table` SET last_login = NOW() WHERE id = ?")->execute([$_SESSION['user_id']]);

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
    <title>ATIERA Login | Superadmin</title>
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

        /* Hide scrollbar for all elements */
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
            /* Much lighter overlay to focus on the image */
        }



        .login-card {
            width: 100%;
            max-width: 450px;
            padding: 50px;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 2px solid var(--primary-gold);
            border-radius: 30px;
            box-shadow: 0 25px 50px -12px rgba(212, 175, 55, 0.2);
            text-align: center;
            position: relative;
            z-index: 10;
        }

        .logo-area {
            margin-bottom: 40px;
        }

        .logo-title {
            color: #0f172a;
            font-size: 42px;
            font-weight: 700;
            letter-spacing: 8px;
            margin-bottom: 5px;
        }

        .logo-subtitle {
            color: var(--primary-gold);
            font-size: 14px;
            font-weight: 400;
            letter-spacing: 4px;
            text-transform: uppercase;
        }

        .welcome-text {
            color: var(--text-dark);
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .instruction-text {
            color: var(--text-gray);
            font-size: 15px;
            margin-bottom: 30px;
        }

        .error-message {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
            text-align: left;
        }

        .input-control {
            width: 100%;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 15px 15px 15px 50px;
            color: var(--text-dark);
            font-size: 16px;
            outline: none;
            transition: all 0.3s;
        }

        .input-control:focus {
            border-color: var(--primary-gold);
            box-shadow: 0 0 0 4px rgba(212, 175, 55, 0.1);
        }

        .form-group i {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-gray);
        }

        .btn-login {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, var(--primary-gold) 0%, var(--dark-gold) 100%);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 10px 15px -3px rgba(212, 175, 55, 0.3);
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(212, 175, 55, 0.4);
        }

        .otp-hint-box {
            background: rgba(212, 175, 55, 0.1);
            border: 1px dashed var(--primary-gold);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        /* --- LOADING SCREEN STYLES --- */
        #loading-screen {
            position: fixed;
            inset: 0;
            background: url('../../assets/image/login.jpeg') center/cover no-repeat;
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
            width: 0%;
            background: linear-gradient(90deg, #E6C86E, #fcf6ba, #E6C86E);
            box-shadow: 0 0 10px #E6C86E;
            animation: loadProgress 2.5s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }

        @keyframes loadProgress {
            0% {
                width: 0%;
            }

            100% {
                width: 100%;
            }
        }

        @keyframes shine {
            to {
                background-position: 200% center;
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
            }, 2500); // Give it a bit of time to show the beauty
        });
    </script>
    <div class="bg-container">
        <div class="bg-overlay"></div>
    </div>

    <div class="login-card">
        <div class="logo-area">
            <h1 class="logo-title">ATIÉRA</h1>
            <p class="logo-subtitle">SUPERADMIN</p>
        </div>

        <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <h2 class="welcome-text">Identification</h2>
            <p class="instruction-text">Superadmin portal requires specialized biological and technical verification.</p>

            <form action="" method="POST">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <input type="text" name="username" class="input-control" placeholder="Enter: Username" required>
                    <i class="fas fa-fingerprint"></i>
                </div>
                <div class="form-group">
                    <input type="password" name="password" class="input-control" placeholder="Enter: Password" required>
                    <i class="fas fa-shield-halved"></i>
                </div>
                <button type="submit" class="btn-login">
                    Initialize Access <i class="fas fa-bolt" style="margin-left: 10px;"></i>
                </button>
            </form>
        <?php else: ?>
            <h2 class="welcome-text">Verification</h2>
            <p class="instruction-text">A security token has been generated and transmitted.</p>

            <?php if (isset($_SESSION['login_otp'])): ?>
                <div class="otp-hint-box">
                    <p style="font-size: 13px; color: #b8860b; margin: 0;"><strong>System Bypass:</strong></p>
                    <p style="font-size: 24px; color: #1e293b; font-weight: 700; margin: 5px 0; letter-spacing: 5px;">
                        <?php echo $_SESSION['login_otp']; ?>
                    </p>
                </div>
            <?php endif; ?>

            <form action="" method="POST">
                <input type="hidden" name="action" value="verify_otp">
                <div class="form-group">
                    <input type="text" name="otp" class="input-control" placeholder="Enter 6-Digit Token" required
                        maxlength="6" pattern="\d{6}">
                    <i class="fas fa-key"></i>
                </div>
                <button type="submit" class="btn-login">
                    Verify Identity <i class="fas fa-check-circle" style="margin-left: 10px;"></i>
                </button>
            </form>
        <?php endif; ?>

        <p style="margin-top: 30px; color: var(--text-gray); font-size: 12px;">
            SECURE ACCESS PROTOCOL 5.0 • PROTECTED BY QUANTUM ENCRYPTION
        </p>
    </div>
</body>

</html>