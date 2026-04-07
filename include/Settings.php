<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';
require_once __DIR__ . '/Config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Function to convert newlines to <br> tags for HTML emails
function nl2br_custom($string)
{
    return str_replace(["\r\n", "\n", "\r"], '<br>', $string);
}

$pdo = get_pdo();

// Function to get email settings from database with enhanced design
function getEmailSettings($pdo)
{
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM email_settings ORDER BY setting_key ASC");
        $settings = [];

        // Define default settings with beautiful formatting
        $defaultSettings = [
            'password_subject' => '🔐 Security Notice: Your ATIERA Password was Updated',
            'password_message' => "Hello {\$full_name},\n\n📧 This is a security notification to let you know that your password for ATIERA Admin Panel has been updated by an administrator.\n\n⚠️ If you did not authorized this change, please contact your system administrator immediately.\n\n🛡️ This is an automated security message - do not reply to this email.",
            'new_account_subject' => '👤 New Account Created: ATIERA Admin Panel',
            'new_account_message' => "Hello {\$full_name},\n\n🎉 Welcome to ATIERA Admin Panel!\n\n📋 An account has been created for you. Here are your credentials:\n\n👤 Username: {\$username}\n🔑 Password: {\$password}\n\n🔗 To complete your registration and set your New Password, please use the activation code sent separately.\n\n📧 For security reasons, please change your password after first login.\n\n🏨 ATIERA Administration Team",
            'setup_subject' => '⚙️ Setup Your ATIERA Account Password',
            'setup_message' => "Hello {\$full_name},\n\n🌟 Congratulations! You have been added as an administrator to ATIERA Admin Panel.\n\n📝 To complete your account setup, please set your New Password using the verification code sent separately.\n\n🔐 Security Tips:\n• Use a strong password with 8+ characters\n• Include uppercase, lowercase, numbers, and symbols\n• Enable two-factor authentication if available\n• Never share your credentials\n\n🏨 ATIERA Security Team"
        ];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        // Merge with defaults for any missing settings
        foreach ($defaultSettings as $key => $value) {
            if (!isset($settings[$key])) {
                $settings[$key] = $value;
            }
        }

        return $settings;

    } catch (PDOException $e) {
        // Return enhanced default settings with beautiful formatting
        return [
            'password_subject' => '🔐 Security Notice: Your ATIERA Password was Updated',
            'password_message' => "Hello {\$full_name},\n\n📧 This is a security notification to let you know that Your password for ATIERA Admin Panel has been updated by an administrator.\n\n⚠️ If you did not authorized this change, please contact your system administrator immediately.\n\n🛡️ This is an automated security message - do not reply to this email.\n\n🔒 Your account security is our top priority!",
            'new_account_subject' => '👤 New Account Created: ATIERA Admin Panel',
            'new_account_message' => "Hello {\$full_name},\n\n🎉 Welcome to ATIERA Admin Panel!\n\n📋 An account has been created for you. Here are your credentials:\n\n👤 Username: {\$username}\n🔑 Password: {\$password}\n\n🔗 To complete your registration and set your New Password, please use the activation code sent separately.\n\n📧 For security reasons, Please change Your password after first login.\n\n🏨 ATIERA Administration Team",
            'setup_subject' => '⚙️ Setup Your ATIERA Account Password',
            'setup_message' => "Hello {\$full_name},\n\n🌟 Congratulations! You have been added as an administrator to ATIERA Admin Panel.\n\n📝 To complete your account setup, Please set your New Password using the verification code sent separately.\n\n🔐 Security Tips:\n• Use a strong password with 8+ characters\n• Include uppercase, lowercase, numbers, and symbols\n• Enable two-factor authentication if available\n• Never share your credentials\n\n🏨 ATIERA Security Team"
        ];
    }
}

$message = '';
$error = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        /* Update User */
        if ($_POST['action'] === 'update_user') {
            $id = intval($_POST['user_id']);
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            $full_name = trim($_POST['full_name']);
            $password = $_POST['password'];

            try {
                if (!empty($password)) {
                    // Update with password
                    $stmt = $pdo->prepare("UPDATE users SET username=?, email=?, full_name=?, password_hash=? WHERE id=?");
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt->execute([$username, $email, $full_name, $hashed_password, $id]);

                    // Send notification email
                    try {
                        $emailSettings = getEmailSettings($pdo);
                        $mail = new PHPMailer(true);
                        $mail->isSMTP();
                        $mail->Host = SMTP_HOST;
                        $mail->SMTPAuth = true;
                        $mail->Username = SMTP_USER;
                        $mail->Password = SMTP_PASS;
                        $mail->Port = SMTP_PORT;
                        $mail->SMTPOptions = array(
                            'ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true)
                        );
                        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                        $mail->addAddress($email, $full_name);
                        $mail->isHTML(true);
                        $mail->Subject = $emailSettings['password_subject'];
                        $mail->Body = "
                            <div style=\"font-family: sans-serif; padding: 20px; color: #1e293b; max-width: 500px; margin: auto; border: 1px solid #e2e8f0; border-radius: 12px;\">
                                <h2 style=\"color: #0f172a;\">Password Changed</h2>
                                <p>Hello " . htmlspecialchars($full_name) . ",</p>
                                <p>" . nl2br_custom(str_replace('{$full_name}', htmlspecialchars($full_name), $emailSettings['password_message'])) . "</p>
                                <div style=\"margin: 20px 0; text-align: center;\">
                                    <a href=\"" . getBaseUrl() . "/auth/login.php\" style=\"background: #1e40af; color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: bold; display: inline-block;\">Go to Login</a>
                                </div>
                            </div>
                        ";
                        $mail->send();
                    } catch (Exception $e) {
                        // Email fail is secondary
                        error_log("Failed to send password change notification: " . $e->getMessage());
                    }
                } else {
                    // Update without password
                    $stmt = $pdo->prepare("UPDATE users SET username=?, email=?, full_name=? WHERE id=?");
                    $stmt->execute([$username, $email, $full_name, $id]);
                }
                $message = "User updated successfully!";
            } catch (PDOException $e) {
                $error = "Error updating user: " . $e->getMessage();
            }
        }
        /* Delete User */ elseif ($_POST['action'] === 'delete_user') {
            $id = intval($_POST['user_id']);
            // Prevent deleting self?
            if ($id == $_SESSION['user_id']) {
                $error = "You cannot delete your own account.";
            } else {
                try {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id=?");
                    $stmt->execute([$id]);
                    $message = "User deleted successfully!";
                } catch (PDOException $e) {
                    $error = "Error deleting user: " . $e->getMessage();
                }
            }
        }
        /* Create User */ elseif ($_POST['action'] === 'create_user') {
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            $full_name = trim($_POST['full_name']);
            $password = $_POST['password'] ?? '';

            if (!empty($username) && !empty($email)) {
                try {
                    // Check if username or email already exists
                    $check = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                    $check->execute([$username, $email]);
                    if ($check->fetch()) {
                        $error = "Error: A user with this username or email already exists.";
                    } else {
                        $pdo->beginTransaction();

                        $baseUrl = getBaseUrl();

                        if (!empty($password)) {
                            // 1. Insert user with manual password
                            $stmt = $pdo->prepare("INSERT INTO users (username, email, full_name, password_hash) VALUES (?, ?, ?, ?)");
                            $stmt->execute([$username, $email, $full_name, password_hash($password, PASSWORD_DEFAULT)]);
                            $newUserId = $pdo->lastInsertId();

                            // 2. Generate and save verification code
                            $code = (string) random_int(100000, 999999);
                            $expiresAt = (new DateTime('+24 hours'))->format('Y-m-d H:i:s');
                            $stmt = $pdo->prepare('INSERT INTO email_verifications (user_id, code, expires_at) VALUES (?, ?, ?)');
                            $stmt->execute([$newUserId, $code, $expiresAt]);

                            // 3. Send Email (Picture 1 Style + Activation Code & Link)
                            $mail = new PHPMailer(true);
                            $mail->isSMTP();
                            $mail->Host = SMTP_HOST;
                            $mail->SMTPAuth = true;
                            $mail->Username = SMTP_USER;
                            $mail->Password = SMTP_PASS;
                            $mail->Port = SMTP_PORT;
                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                            $mail->SMTPOptions = array(
                                'ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true)
                            );

                            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                            $mail->addAddress($email, $full_name);
                            $mail->isHTML(true);
                            $mail->Subject = 'New Account Created: ATIERA Admin Panel';

                            $loginUrl = $baseUrl . "/auth/login.php?verify_new=1&email=" . urlencode($email);

                            $mail->Body = "
                            <div style=\"font-family: sans-serif; padding: 20px; color: #1e293b; max-width: 500px; margin: auto; border: 1px solid #e2e8f0; border-radius: 12px;\">
                                <h2 style=\"color: #0f172a;\">Welcome to ATIERA</h2>
                                <p>Hello " . htmlspecialchars($full_name) . ",</p>
                                <p>An account has been created for you. Here are your credentials:</p>
                                <div style=\"background: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0; margin: 20px 0;\">
                                    <p style=\"margin: 0;\"><strong>Username:</strong> " . htmlspecialchars($username) . "</p>
                                    <p style=\"margin: 5px 0 0;\"><strong>Password:</strong> <span style=\"color: #1e40af; font-weight: bold;\">" . htmlspecialchars($password) . "</span></p>
                                </div>
                                <p>To complete your registration and set your <strong>New Password</strong>, please use the code below:</p>
                                <div style=\"text-align: center; margin: 25px 0; background: #fff; padding: 15px; border-radius: 8px; border: 2px dashed #e2e8f0;\">
                                    <span style=\"font-size: 28px; font-weight: bold; letter-spacing: 8px; color: #1e40af;\">{$code}</span>
                                </div>
                                <div style=\"margin: 20px 0; text-align: center;\">
                                    <a href=\"{{ $loginUrl }}\" style=\"background: #1e40af; color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: bold; display: inline-block;\">Activate & Login</a>
                                </div>
                                <p style=\"font-size: 11px; color: #64748b; text-align: center;\">This security link and code expires in 24 hours.</p>
                            </div>
                        ";
                            $mail->send();
                            $message = "User created! Login details sent to <strong>" . htmlspecialchars($email) . "</strong>";
                        } else {
                            // 1. Insert user (placeholder password)
                            $stmt = $pdo->prepare("INSERT INTO users (username, email, full_name, password_hash) VALUES (?, ?, ?, 'PENDING_REGISTRATION')");
                            $stmt->execute([$username, $email, $full_name]);
                            $newUserId = $pdo->lastInsertId();

                            // 2. Generate Verification Code
                            $code = (string) random_int(100000, 999999);
                            $expiresAt = (new DateTime('+24 hours'))->format('Y-m-d H:i:s');
                            $stmt = $pdo->prepare('INSERT INTO email_verifications (user_id, code, expires_at) VALUES (?, ?, ?)');
                            $stmt->execute([$newUserId, $code, $expiresAt]);

                            // 3. Send Invitation
                            $emailSettings = getEmailSettings($pdo);
                            $mail = new PHPMailer(true);
                            $mail->isSMTP();
                            $mail->Host = SMTP_HOST;
                            $mail->SMTPAuth = true;
                            $mail->Username = SMTP_USER;
                            $mail->Password = SMTP_PASS;
                            $mail->Port = SMTP_PORT;
                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                            $mail->SMTPOptions = array(
                                'ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true)
                            );

                            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                            $mail->addAddress($email, $full_name);
                            $mail->isHTML(true);
                            $mail->Subject = $emailSettings['setup_subject'];

                            $loginUrl = $baseUrl . "/auth/login.php?verify_new=1&email=" . urlencode($email);

                            $mail->Body = "
                            <div style=\"font-family: sans-serif; padding: 20px; color: #1e293b; max-width: 500px; margin: auto; border: 1px solid #e2e8f0; border-radius: 12px;\">
                                <h2 style=\"color: #0f172a;\">Setup Your Password</h2>
                                <p>Hello " . htmlspecialchars($full_name) . ",</p>
                                <p>" . nl2br_custom(str_replace('{$full_name}', htmlspecialchars($full_name), $emailSettings['setup_message'])) . "</p>
                                <div style=\"text-align: center; margin: 30px 0; background: #f8fafc; padding: 20px; border-radius: 8px; border: 2px dashed #e2e8f0;\">
                                    <span style=\"font-size: 32px; font-weight: bold; letter-spacing: 10px; color: #1e40af;\">{$code}</span>
                                </div>
                                <div style=\"margin: 20px 0; text-align: center;\">
                                    <a href=\"{$loginUrl}\" style=\"background: #1e40af; color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: bold; display: inline-block;\">Set New Password</a>
                                </div>
                                <p style=\"font-size: 13px; color: #64748b; text-align: center;\">This security link and code will expire in 24 hours.</p>
                            </div>
                        ";
                            $mail->send();
                            $message = "Invitation sent to <strong>" . htmlspecialchars($email) . "</strong>! They can now activate their account.";
                        }
                        $pdo->commit();
                    }
                } catch (\Exception $e) {
                    if ($pdo->inTransaction())
                        $pdo->rollBack();
                    $error = "Mailer Error: " . (isset($mail) ? $mail->ErrorInfo : $e->getMessage());
                }
            }
        }

        /* Update Admin Email (Security Tab) */ elseif ($_POST['action'] === 'update_security_email') {
            $newEmail = trim($_POST['new_email']);
            $currentPass = $_POST['current_password'];
            $id = $_SESSION['user_id'];

            try {
                // Verify password
                $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $user = $stmt->fetch();

                if ($user && password_verify($currentPass, $user['password_hash'])) {
                    // Check if email exists
                    $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $check->execute([$newEmail, $id]);
                    if ($check->fetch()) {
                        $error = "This email is already in use by another account.";
                    } else {
                        $pdo->prepare("UPDATE users SET email = ? WHERE id = ?")->execute([$newEmail, $id]);
                        $_SESSION['email'] = $newEmail;
                        $message = "Your admin email has been updated to: " . htmlspecialchars($newEmail);
                    }
                } else {
                    $error = "Incorrect password. Email update failed.";
                }
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
        /* Update Email Settings */ elseif ($_POST['action'] === 'update_email_settings') {
            $passwordSubject = trim($_POST['password_subject']);
            $passwordMessage = trim($_POST['password_message']);
            $newAccountSubject = trim($_POST['new_account_subject']);
            $newAccountMessage = trim($_POST['new_account_message']);
            $setupSubject = trim($_POST['setup_subject']);
            $setupMessage = trim($_POST['setup_message']);

            try {
                // Create email_settings table if it doesn't exist
                $pdo->exec("CREATE TABLE IF NOT EXISTS email_settings (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    setting_key VARCHAR(100) UNIQUE,
                    setting_value TEXT,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )");

                // Update or insert each email setting
                $settings = [
                    'password_subject' => $passwordSubject,
                    'password_message' => $passwordMessage,
                    'new_account_subject' => $newAccountSubject,
                    'new_account_message' => $newAccountMessage,
                    'setup_subject' => $setupSubject,
                    'setup_message' => $setupMessage
                ];

                foreach ($settings as $key => $value) {
                    $stmt = $pdo->prepare("INSERT INTO email_settings (setting_key, setting_value) VALUES (?, ?) 
                                          ON DUPLICATE KEY UPDATE setting_value = ?");
                    $stmt->execute([$key, $value, $value]);
                }

                $message = "Email settings updated successfully!";
            } catch (PDOException $e) {
                $error = "Error updating email settings: " . $e->getMessage();
            }
        }
        /* Update Own Password (Security Tab) */ elseif ($_POST['action'] === 'update_own_password') {
            $currentPass = $_POST['current_password'];
            $newPass = $_POST['new_password'];
            $confirmPass = $_POST['confirm_password'];
            $id = $_SESSION['user_id'];

            if ($newPass !== $confirmPass) {
                $error = "New passwords do not match.";
            } else {
                try {
                    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
                    $stmt->execute([$id]);
                    $user = $stmt->fetch();

                    if ($user && password_verify($currentPass, $user['password_hash'])) {
                        $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                        $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$newHash, $id]);
                        $message = "Your password has been updated successfully!";
                    } else {
                        $error = "Incorrect current password.";
                    }
                } catch (PDOException $e) {
                    $error = "Database error: " . $e->getMessage();
                }
            }
        }
        /* Update Security PIN */ elseif ($_POST['action'] === 'update_security_pin') {
            $newPin = $_POST['security_pin'] ?? '';
            $confirmPin = $_POST['confirm_pin'] ?? '';

            if (empty($newPin) || !preg_match('/^\d{4}$/', $newPin)) {
                $error = "PIN must be exactly 4 digits.";
            } elseif ($newPin !== $confirmPin) {
                $error = "PINs do not match.";
            } else {
                try {
                    // Create email_settings table if it doesn't exist
                    $pdo->exec("CREATE TABLE IF NOT EXISTS email_settings (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        setting_key VARCHAR(100) UNIQUE,
                        setting_value TEXT,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    )");

                    $stmt = $pdo->prepare("INSERT INTO email_settings (setting_key, setting_value) VALUES (?, ?) 
                                          ON DUPLICATE KEY UPDATE setting_value = ?");
                    $stmt->execute(['archive_pin', $newPin, $newPin]);
                    $message = "Security PIN updated successfully!";
                } catch (PDOException $e) {
                    $error = "Error updating security PIN: " . $e->getMessage();
                }
            }
        }
    }
}

// Fetch Users
$stmt = $pdo->query("SELECT * FROM users ORDER BY id DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings - Admin</title>
    <link rel="icon" type="image/x-icon" href="../assets/image/logo2.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/facilities-reservation.css">
    <style>
        :root {
            --primary-blue: #1e3a8a;
            --accent-blue: #3b82f6;
            --text-gray: #64748b;
            --bg-gray: #f8fafc;
        }

        .dashboard-layout .main-content {
            margin-left: 280px;
            background-color: var(--bg-gray);
            min-height: 100vh;
        }

        @media screen and (max-width: 991px) {
            .dashboard-layout .main-content {
                margin-left: 0;
            }
        }

        /* Top Header Stylings */
        .top-header {
            background: white;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-title h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            margin: 0;
        }

        .header-subtitle {
            font-size: 0.9rem;
            color: var(--text-gray);
            font-weight: 400;
        }

        /* Tab Navigation */
        .tabs-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            border-bottom: 1px solid #e2e8f0;
            background: white;
            padding: 0 1rem;
        }

        .tabs-list {
            display: flex;
            gap: 30px;
        }

        .tab-btn {
            background: none;
            border: none;
            padding: 1rem 0;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-gray);
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.2s ease;
        }

        .tab-btn:hover {
            color: var(--primary-blue);
        }

        .tab-btn.active {
            color: var(--accent-blue);
            border-bottom-color: var(--accent-blue);
        }

        /* Swap Button */
        .swap-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            color: var(--text-gray);
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .swap-btn:hover {
            background: #e2e8f0;
            color: #1e293b;
        }

        /* Main Content Cards */
        .content-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        /* Security Grid */
        .security-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        .security-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 12px;
            transition: all 0.2s;
            cursor: pointer;
            text-align: left;
        }

        .security-card:hover {
            border-color: var(--accent-blue);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.1);
            transform: translateY(-2px);
        }

        .security-card i {
            font-size: 1.5rem;
            color: var(--accent-blue);
        }

        .security-card h4 {
            margin: 0;
            font-size: 1.1rem;
            color: #1e293b;
        }

        .security-card p {
            margin: 0;
            font-size: 0.85rem;
            color: var(--text-gray);
            line-height: 1.4;
        }

        /* Dashboard View */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 24px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
        }

        /* Modal logic */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            padding: 2rem;
            position: relative;
        }

        .close-modal {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-gray);
        }

        /* Loading Animation */
        .loading-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(255, 255, 255, 0.8);
            z-index: 2000;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--accent-blue);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* General UI Elements */
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
        }

        .btn-primary {
            background: var(--primary-blue);
            color: white;
        }

        .btn-primary:hover {
            background: #1e40af;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-outline {
            background: white;
            border: 1px solid #e2e8f0;
            color: #1e293b;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th {
            text-align: center;
            padding: 12px;
            background: #f8fafc;
            color: #64748b;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
        }

        .table td {
            padding: 12px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.9rem;
            text-align: center;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* Viewing Table Blur */
        .viewing-container {
            position: relative;
        }

        .viewing-blur {
            filter: blur(3px);
            pointer-events: none;
            user-select: none;
            opacity: 0.7;
        }

        .viewing-badge {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(30, 58, 138, 0.9);
            color: white;
            padding: 10px 24px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.95rem;
            z-index: 20;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            white-space: nowrap;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .viewing-badge:hover {
            background: rgba(30, 58, 138, 1);
            transform: translate(-50%, -50%) scale(1.05);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
        }

        .viewing-badge i {
            margin-right: 8px;
            font-size: 1.1rem;
        }

        /* Security Mode Hidden Elements */
        .security-only {
            display: none !important;
        }

        .security-unlocked .security-only {
            display: inline-block !important;
        }

        .security-unlocked .viewing-blur {
            filter: none;
            pointer-events: auto;
            user-select: auto;
        }

        /* Modal Visibility Fix */
        .modal.active {
            display: flex !important;
            align-items: center;
            justify-content: center;
        }

        /* PIN Input Boxes Styles */
        .pin-inputs {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin: 20px 0;
        }

        .pin-box {
            width: 50px;
            height: 50px;
            text-align: center;
            font-size: 1.5rem;
            font-weight: 700;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            background: #f8fafc;
            color: #1e293b;
            transition: all 0.2s;
            outline: none;
        }

        .pin-box:focus {
            border-color: var(--accent-blue);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            transform: translateY(-2px);
        }
    </style>
</head>

<body class="dashboard-layout">
    <div class="container">
        <?php include 'sidebar.php'; ?>

        <main class="main-content">
            <header class="top-header">
                <div class="header-title">
                    <button class="mobile-menu-btn" onclick="toggleSidebar()" style="display:none;">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1>Account Settings</h1>
                    <p class="header-subtitle">Manage Admin Accounts and System Users</p>
                </div>
                <div class="header-actions">
                    <div class="user-info" style="display: flex; align-items: center; gap: 12px; font-weight: 600;">
                        <div
                            style="width: 32px; height: 32px; background: #e2e8f0; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-user" style="font-size: 0.9rem; color: #64748b;"></i>
                        </div>
                        <span>Admin</span>
                    </div>
                </div>
            </header>

            <div class="dashboard-content" style="padding: 0 2rem 2rem 2rem;">
                <?php if ($message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <div class="tabs-container">
                    <div class="tabs-list">
                        <button class="tab-btn active" onclick="switchTab('general')" id="tab-general">Users
                            List</button>
                        <button class="tab-btn" onclick="switchTab('security')" id="tab-security">Security</button>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button class="swap-btn" onclick="openSecurityModal('pin')">
                            <i class="fas fa-key"></i> Security PIN
                        </button>
                        <button class="swap-btn" onclick="toggleLayout()">
                            <i class="fas fa-sync-alt"></i> Swap View
                        </button>
                    </div>
                </div>

                <!-- Users List Tab Content -->
                <div id="content-general">
                    <div class="content-card">
                        <div
                            style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                            <h3 style="font-size: 1.25rem; font-weight: 700; color: #1e293b;">Active Users</h3>
                        </div>

                        <div class="viewing-container">
                            <div class="viewing-badge" onclick="triggerSecurityUnlock()">
                                <i class="fas fa-eye"></i> Viewing Mode Only
                            </div>
                            <div class="viewing-blur" style="overflow-x: auto;">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Full Name</th>
                                            <th>Username</th>
                                            <th>Email</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td style="font-weight: 600; color: #94a3b8;">#<?= $user['id'] ?></td>
                                                <td><?= htmlspecialchars($user['full_name']) ?></td>
                                                <td><?= htmlspecialchars($user['username']) ?></td>
                                                <td><?= htmlspecialchars($user['email']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Security Tab Content -->
            <div id="content-security" style="display: none;">
                <div class="content-card">
                    <h3 style="font-size: 1.25rem; font-weight: 700; color: #1e293b; margin-bottom: 1.5rem;">
                        Security Controls</h3>
                    <div class="security-grid">
                        <div class="security-card" onclick="openSecurityModal('password')">
                            <i class="fas fa-lock"></i>
                            <h4>Change Admin Password</h4>
                            <p>Update the master password for this account to maintain high security.</p>
                        </div>

                        <div class="security-card" onclick="openSecurityModal('pin')">
                            <i class="fas fa-key"></i>
                            <h4>Security PIN (4-Digit)</h4>
                            <p>Manage the 4-digit PIN used for sensitive actions across the system.</p>
                        </div>

                        <div class="security-card" onclick="openSecurityModal('logs')">
                            <i class="fas fa-clipboard-list"></i>
                            <h4>Audit Logs</h4>
                            <p>View comprehensive security events and user access logs.</p>
                        </div>

                        <div class="security-card" onclick="openSecurityModal('email')">
                            <i class="fas fa-envelope-open-text"></i>
                            <h4>Email Templates</h4>
                            <p>Customize the automated emails sent for system notifications.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dashboard Section (Always at bottom) -->
            <div class="content-card" style="margin-top: 0;">
                <h3 style="font-size: 1.25rem; font-weight: 700; color: #1e293b; margin-bottom: 0.75rem;">System
                    Overview</h3>
                <div class="dashboard-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #3b82f6;">
                            <i class="fas fa-server"></i>
                        </div>
                        <div>
                            <div style="font-size: 1.5rem; font-weight: 700;">Online</div>
                            <div style="font-size: 0.85rem; color: #64748b;">System Status</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #10b981;">
                            <i class="fas fa-users"></i>
                        </div>
                        <div>
                            <div style="font-size: 1.5rem; font-weight: 700;"><?= count($users) ?></div>
                            <div style="font-size: 0.85rem; color: #64748b;">Total Administrators</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #f59e0b;">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div>
                            <div style="font-size: 1.5rem; font-weight: 700;">98%</div>
                            <div style="font-size: 0.85rem; color: #64748b;">Security Score</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #6366f1;">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div>
                            <div style="font-size: 1.5rem; font-weight: 700;">247</div>
                            <div style="font-size: 0.85rem; color: #64748b;">Logs Today</div>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 2rem; padding: 1.5rem; background: #f8fafc; border-radius: 12px;">
                    <h4 style="font-size: 1rem; font-weight: 600; margin-bottom: 1rem;">Quick Actions</h4>
                    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                        <button class="btn btn-outline"><i class="fas fa-download"></i> Backup</button>
                        <button class="btn btn-outline"><i class="fas fa-sync"></i> Refresh</button>
                        <button class="btn btn-outline"><i class="fas fa-bell"></i> Alerts</button>
                    </div>
                </div>
            </div>
    </div>
    </main>



    <!-- Edit/Create User Modal -->
    <div class="modal" id="userModal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('userModal')">&times;</span>
            <h3 id="modalTitle" style="margin-top: 0; margin-bottom: 1.5rem; color: var(--primary);">Edit User</h3>
            <form method="POST" id="userForm">
                <input type="hidden" name="action" id="formAction" value="update_user">
                <input type="hidden" name="user_id" id="userId">

                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" id="fullName" class="form-control" required
                        placeholder="Enter full name">
                </div>

                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" id="userName" class="form-control" required
                        placeholder="Choose a username">
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="userEmail" class="form-control" required
                        placeholder="user@example.com">
                </div>

                <div class="form-group" id="passwordGroup">
                    <label>Password <small style="font-weight: 400; color: #718096;">(Leave blank to keep
                            unchanged)</small></label>
                    <input type="password" name="password" class="form-control" placeholder="Enter new password">
                </div>

                <button type="submit" class="btn btn-primary btn-block" style="margin-top: 1rem;">
                    <span class="icon-img-placeholder">💾</span> Save Changes
                </button>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content" style="max-width:400px; text-align:center;">
            <div style="color: #e53e3e; font-size: 3rem; margin-bottom: 1rem;">
                <span class="icon-img-placeholder">⚠️</span>
            </div>
            <h3 style="margin-top: 0; color: #2d3748;">Delete User?</h3>
            <p style="color: #718096; margin-bottom: 1.5rem;">Are you sure you want to delete this user? This action
                cannot be undone.</p>
            <form method="POST">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="user_id" id="deleteUserId">
                <div style="display:flex; gap:10px; justify-content:center;">
                    <button type="button" class="btn btn-outline" style="flex: 1;"
                        onclick="closeModal('deleteModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger" style="flex: 1;">Delete User</button>
                </div>
            </form>
        </div>
    </div>
    <!-- Security: Change Password Modal -->
    <div class="modal" id="securityPasswordModal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('securityPasswordModal')">&times;</span>
            <h3 style="margin-top: 0; color: #2d3748;">Change Password</h3>
            <form method="POST">
                <input type="hidden" name="action" value="update_own_password">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" class="form-control" required minlength="6">
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" required minlength="6">
                </div>
                <button type="submit" class="btn btn-primary btn-block">Update Password</button>
            </form>
        </div>
    </div>

    <!-- Security: PIN Modal -->
    <div class="modal" id="securityPinModal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('securityPinModal')">&times;</span>
            <h3 style="margin-top: 0; color: #2d3748;">Manage Security PIN</h3>
            <p style="color: #718096; font-size: 0.9rem; margin-bottom: 20px;">This 4-digit PIN is used to access
                sensitive records (e.g. Employee Info).</p>
            <form method="POST" id="pinForm">
                <input type="hidden" name="action" value="update_security_pin">

                <div class="form-group">
                    <label>New 4-Digit PIN</label>
                    <div class="pin-inputs" id="newPinInputs">
                        <input type="text" maxlength="1" class="pin-box" data-idx="0">
                        <input type="text" maxlength="1" class="pin-box" data-idx="1">
                        <input type="text" maxlength="1" class="pin-box" data-idx="2">
                        <input type="text" maxlength="1" class="pin-box" data-idx="3">
                    </div>
                    <input type="hidden" name="security_pin" id="realNewPin">
                </div>

                <div class="form-group">
                    <label>Confirm PIN</label>
                    <div class="pin-inputs" id="confirmPinInputs">
                        <input type="text" maxlength="1" class="pin-box" data-idx="0">
                        <input type="text" maxlength="1" class="pin-box" data-idx="1">
                        <input type="text" maxlength="1" class="pin-box" data-idx="2">
                        <input type="text" maxlength="1" class="pin-box" data-idx="3">
                    </div>
                    <input type="hidden" name="confirm_pin" id="realConfirmPin">
                </div>

                <button type="submit" class="btn btn-primary btn-block">Set Security PIN</button>
            </form>
        </div>
    </div>

    <!-- Security: Logs Modal -->
    <div class="modal" id="securityLogsModal">
        <div class="modal-content" style="max-width: 600px;">
            <span class="close-modal" onclick="closeModal('securityLogsModal')">&times;</span>
            <h3 style="margin-top: 0; color: #2d3748; margin-bottom: 15px;">Audit Logs</h3>
            <div style="background: #f7fafc; border-radius: 8px; border: 1px solid #e2e8f0; overflow: hidden;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                    <tr style="background: #edf2f7; text-align: left;">
                        <th style="padding: 10px; border-bottom: 1px solid #e2e8f0;">Action</th>
                        <th style="padding: 10px; border-bottom: 1px solid #e2e8f0;">IP Address</th>
                        <th style="padding: 10px; border-bottom: 1px solid #e2e8f0;">Time</th>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border-bottom: 1px solid #e2e8f0;">User Login</td>
                        <td style="padding: 10px; border-bottom: 1px solid #e2e8f0;">192.168.1.10</td>
                        <td style="padding: 10px; border-bottom: 1px solid #e2e8f0; color: #718096;">Just now</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border-bottom: 1px solid #e2e8f0;">Viewed Employee</td>
                        <td style="padding: 10px; border-bottom: 1px solid #e2e8f0;">192.168.1.10</td>
                        <td style="padding: 10px; border-bottom: 1px solid #e2e8f0; color: #718096;">2 mins ago</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px;">Update Settings</td>
                        <td style="padding: 10px;">192.168.1.10</td>
                        <td style="padding: 10px; color: #718096;">1 hour ago</td>
                    </tr>
                </table>
            </div>
            <button class="btn btn-outline btn-block" style="margin-top: 15px;"
                onclick="closeModal('securityLogsModal')">Close</button>
        </div>
    </div>

    <!-- Security: Sessions Modal -->
    <div class="modal" id="securitySessionsModal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('securitySessionsModal')">&times;</span>
            <h3 style="margin-top: 0; color: #2d3748;">Active Sessions</h3>
            <div style="margin-top: 15px;">
                <div
                    style="display: flex; align-items: center; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 10px;">
                    <div style="font-size: 1.5rem; margin-right: 15px;">💻</div>
                    <div style="flex: 1;">
                        <div style="font-weight: 600;">Windows 10 • Chrome</div>
                        <div style="font-size: 0.8rem; color: #16a34a;">Active Now (This Device)</div>
                    </div>
                </div>
            </div>
            <button class="btn btn-danger btn-block" style="margin-top: 10px;">Logout All Other Sessions</button>
        </div>
    </div>

    <!-- Security: Email Modal -->
    <div class="modal" id="securityEmailModal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('securityEmailModal')">&times;</span>
            <h3 style="margin-top: 0; color: #2d3748;">Email Settings</h3>
            <p style="color: #718096; font-size: 0.9rem; margin-bottom: 20px;">Update email content sent when
                accounts are changed.</p>

            <div style="margin-bottom: 20px;">
                <h4 style="color: #2d3748; font-size: 1rem; font-weight: 600; margin-bottom: 10px;">Password Change
                    Email</h4>
                <div
                    style="background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 15px;">
                    <div style="margin-bottom: 10px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 5px;">Subject:</label>
                        <input type="text" id="passwordSubject" class="form-control"
                            value="Security Notice: Your ATIERA Password was Updated"
                            style="width: 100%; padding: 8px; border: 1px solid #cbd5e0; border-radius: 4px;">
                    </div>
                    <div>
                        <label style="font-weight: 600; display: block; margin-bottom: 5px;">Message:</label>
                        <textarea id="passwordMessage" class="form-control" rows="4"
                            style="width: 100%; padding: 8px; border: 1px solid #cbd5e0; border-radius: 4px; resize: vertical;">Hello {$full_name},

This is a security notification to let you know that your password for the ATIERA Admin Panel has been updated by an administrator.

If you did not authorized this change, please contact your system administrator immediately.</textarea>
                    </div>
                </div>
            </div>

            <div style="margin-bottom: 20px;">
                <h4 style="color: #2d3748; font-size: 1rem; font-weight: 600; margin-bottom: 10px;">New Account
                    Email</h4>
                <div
                    style="background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 15px;">
                    <div style="margin-bottom: 10px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 5px;">Subject:</label>
                        <input type="text" id="newAccountSubject" class="form-control"
                            value="New Account Created: ATIERA Admin Panel"
                            style="width: 100%; padding: 8px; border: 1px solid #cbd5e0; border-radius: 4px;">
                    </div>
                    <div>
                        <label style="font-weight: 600; display: block; margin-bottom: 5px;">Message:</label>
                        <textarea id="newAccountMessage" class="form-control" rows="4"
                            style="width: 100%; padding: 8px; border: 1px solid #cbd5e0; border-radius: 4px; resize: vertical;">Hello {$full_name},

An account has been created for you. Here are your credentials:

Username: {$username}
Password: {$password}

To complete your registration and set your New Password, please use the activation code sent separately.</textarea>
                    </div>
                </div>
            </div>

            <div style="margin-bottom: 20px;">
                <h4 style="color: #2d3748; font-size: 1rem; font-weight: 600; margin-bottom: 10px;">Account Setup
                    Email</h4>
                <div
                    style="background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 15px;">
                    <div style="margin-bottom: 10px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 5px;">Subject:</label>
                        <input type="text" id="setupSubject" class="form-control"
                            value="Setup Your ATIERA Account Password"
                            style="width: 100%; padding: 8px; border: 1px solid #cbd5e0; border-radius: 4px;">
                    </div>
                    <div>
                        <label style="font-weight: 600; display: block; margin-bottom: 5px;">Message:</label>
                        <textarea id="setupMessage" class="form-control" rows="4"
                            style="width: 100%; padding: 8px; border: 1px solid #cbd5e0; border-radius: 4px; resize: vertical;">Hello {$full_name},

You have been added as an administrator. To complete your account setup, please set your New Password using the verification code sent separately.</textarea>
                    </div>
                </div>
            </div>

            <button type="button" class="btn btn-primary btn-block" onclick="saveEmailSettings()">Save Email
                Settings</button>
        </div>
    </div>

    <!-- Security Unlock Modal -->
    <div class="modal" id="securityUnlockModal">
        <div class="modal-content" style="max-width: 400px; text-align: center;">
            <div style="margin-bottom: 20px;">
                <img src="../assets/image/logo.png" alt="Atiera Logo" style="width: 140px; height: auto;">
            </div>
            <h3 style="margin-top: 0; color: #1e293b;">Security Mode</h3>
            <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 24px;">Please enter the system PIN to unlock
                management actions.</p>

            <div class="pin-inputs" id="unlockPinInputs" style="justify-content: center; margin-bottom: 24px;">
                <input type="password" maxlength="1" class="pin-box" data-idx="0">
                <input type="password" maxlength="1" class="pin-box" data-idx="1">
                <input type="password" maxlength="1" class="pin-box" data-idx="2">
                <input type="password" maxlength="1" class="pin-box" data-idx="3">
            </div>

            <div id="unlockErrorMessage"
                style="color: #ef4444; font-size: 0.85rem; margin-top: -15px; margin-bottom: 15px; display: none;">
                Invalid PIN. Access denied.
            </div>

            <div style="display: flex; gap: 12px; justify-content: center;">
                <button class="btn btn-outline" style="min-width: 130px; justify-content: center;"
                    onclick="closeModal('securityUnlockModal')">Cancel</button>
                <button class="btn btn-primary" style="min-width: 130px; justify-content: center;"
                    onclick="verifyManagementUnlock()">Unlock</button>
            </div>
        </div>
    </div>


    <!-- Invitation Loading Overlay -->
    <div id="inviteLoadingOverlay" class="loading-overlay">
        <div class="spinner"></div>
        <h3 style="margin: 0; font-weight: 600; letter-spacing: 0.5px;">Sending Invitation...</h3>
    </div>

    <script src="../assets/Javascript/facilities-reservation.js"></script>
    <script>
        // Form submission loading state
        if (document.getElementById('userForm')) {
            document.getElementById('userForm').addEventListener('submit', function (e) {
                const action = document.getElementById('formAction').value;
                if (action === 'create_user') {
                    document.getElementById('inviteLoadingOverlay').style.display = 'flex';
                }
            });
        }

        function triggerSecurityUnlock() {
            const modal = document.getElementById('securityUnlockModal');
            if (modal) {
                modal.classList.add('active');
                setTimeout(() => {
                    const firstBox = modal.querySelector('.pin-box');
                    if (firstBox) firstBox.focus();
                }, 300);
            }
        }

        function verifyManagementUnlock() {
            const pin = Array.from(document.querySelectorAll('#unlockPinInputs .pin-box')).map(b => b.value).join('');

            // For demonstration, using '1234'. In production, this should be verified via AJAX
            if (pin === '1234') {
                document.body.classList.add('security-unlocked');
                document.querySelector('.viewing-badge').style.display = 'none';
                closeModal('securityUnlockModal');
                // Reset pin boxes
                document.querySelectorAll('#unlockPinInputs .pin-box').forEach(b => b.value = '');
            } else {
                const error = document.getElementById('unlockErrorMessage');
                if (error) error.style.display = 'block';
                setTimeout(() => { if (error) error.style.display = 'none'; }, 3000);
                // Clear inputs
                document.querySelectorAll('#unlockPinInputs .pin-box').forEach(b => b.value = '');
                const first = document.querySelector('#unlockPinInputs .pin-box');
                if (first) first.focus();
            }
        }

        function openEditModal(user) {
            document.getElementById('modalTitle').innerText = 'Edit User';
            document.getElementById('formAction').value = 'update_user';
            document.getElementById('userId').value = user.id;
            document.getElementById('fullName').value = user.full_name;
            document.getElementById('userName').value = user.username;
            document.getElementById('userEmail').value = user.email;
            document.getElementById('passwordGroup').style.display = 'block';
            document.getElementById('userModal').classList.add('active');
        }

        function openCreateModal() {
            document.getElementById('modalTitle').innerText = 'Add New User';
            document.getElementById('formAction').value = 'create_user';
            document.getElementById('userId').value = '';
            document.getElementById('userForm').reset();
            document.getElementById('userEmail').value = '';
            document.getElementById('passwordGroup').style.display = 'block';
            document.getElementById('userModal').classList.add('active');
        }

        function openDeleteModal(id) {
            document.getElementById('deleteUserId').value = id;
            document.getElementById('deleteModal').classList.add('active');
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) modal.classList.remove('active');
        }

        function openSecurityModal(type) {
            if (type === 'password') document.getElementById('securityPasswordModal').classList.add('active');
            if (type === 'pin') document.getElementById('securityPinModal').classList.add('active');
            if (type === 'logs') document.getElementById('securityLogsModal').classList.add('active');
            if (type === 'email') document.getElementById('securityEmailModal').classList.add('active');
        }

        function switchTab(tabName) {
            if (document.getElementById('content-general')) document.getElementById('content-general').style.display = 'none';
            if (document.getElementById('content-security')) document.getElementById('content-security').style.display = 'none';
            if (document.getElementById('tab-general')) document.getElementById('tab-general').classList.remove('active');
            if (document.getElementById('tab-security')) document.getElementById('tab-security').classList.remove('active');

            const targetContent = document.getElementById('content-' + tabName);
            const targetTab = document.getElementById('tab-' + tabName);

            if (targetContent) targetContent.style.display = 'block';
            if (targetTab) targetTab.classList.add('active');
        }

        let isSecurityView = false;
        function toggleLayout() {
            if (isSecurityView) {
                switchTab('general');
                isSecurityView = false;
            } else {
                switchTab('security');
                isSecurityView = true;
            }
        }

        window.onclick = function (event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }

        function setupPinInputs(containerId, hiddenInputId) {
            const container = document.getElementById(containerId);
            if (!container) return;
            const hidden = hiddenInputId ? document.getElementById(hiddenInputId) : null;
            const boxes = container.querySelectorAll('.pin-box');

            boxes.forEach((box, idx) => {
                box.addEventListener('input', (e) => {
                    const val = e.target.value;
                    if (!/^\d$/.test(val)) { e.target.value = ''; return; }
                    if (idx < 3) boxes[idx + 1].focus();
                    if (hidden) updateHidden();
                });

                box.addEventListener('keydown', (e) => {
                    if (e.key === 'Backspace' && !e.target.value) {
                        if (idx > 0) boxes[idx - 1].focus();
                    }
                });
                if (hidden) box.addEventListener('keyup', updateHidden);
            });

            function updateHidden() {
                if (hidden) hidden.value = Array.from(boxes).map(b => b.value).join('');
            }
        }

        setupPinInputs('newPinInputs', 'realNewPin');
        setupPinInputs('confirmPinInputs', 'realConfirmPin');
        setupPinInputs('unlockPinInputs', null);

        // Reset security state on page load
        document.body.classList.remove('security-unlocked');
        const viewingBadge = document.querySelector('.viewing-badge');
        if (viewingBadge) viewingBadge.style.display = 'block';

        // Save Email Settings Function
        function saveEmailSettings() {
            const passwordSubject = document.getElementById('passwordSubject').value;
            const passwordMessage = document.getElementById('passwordMessage').value;
            const newAccountSubject = document.getElementById('newAccountSubject').value;
            const newAccountMessage = document.getElementById('newAccountMessage').value;
            const setupSubject = document.getElementById('setupSubject').value;
            const setupMessage = document.getElementById('setupMessage').value;

            const formData = new FormData();
            formData.append('action', 'update_email_settings');
            formData.append('password_subject', passwordSubject);
            formData.append('password_message', passwordMessage);
            formData.append('new_account_subject', newAccountSubject);
            formData.append('new_account_message', newAccountMessage);
            formData.append('setup_subject', setupSubject);
            formData.append('setup_message', setupMessage);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
                .then(response => response.text())
                .then(data => {
                    closeModal('securityEmailModal');
                    window.location.reload();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error saving settings.');
                });
        }
    </script>
</body>

</html>