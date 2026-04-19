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
$email = $_SESSION['temp_email'] ?? null;
$name = $_SESSION['temp_name'] ?? 'Admin';

$action = $_POST['action'] ?? 'verify';

// Validate session only for actions that depend on it
if ($action === 'verify' || $action === 'resend') {
    if (empty($userId) || empty($email)) {
        json_out(['ok' => false, 'message' => 'Session expired. Please login again.'], 401);
    }
}

// --- HELPER: Send Email ---
function send_email($to, $name, $code)
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->Port = SMTP_PORT;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Timeout = 60;
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $logo_url = getBaseUrl() . 'assets/image/logo.png';
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to, $name);
        $mail->isHTML(true);
        $mail->Subject = 'Your ATIERA Verification Code';
        $mail->Body = "
            <div style=\"font-family: 'Segoe UI', Arial, sans-serif; max-width: 500px; margin: 0 auto; padding: 30px; border: 1px solid #f0f0f0; border-radius: 12px; background-color: #ffffff;\">
                <div style=\"text-align: left; margin-bottom: 25px;\">
                    <img src=\"{$logo_url}\" alt=\"ATIERA Hotel\" style=\"height: 60px; width: auto;\">
                </div>
                <h2 style=\"color: #1e293b; margin: 0 0 15px; font-size: 24px; font-weight: 700;\">Verify your email</h2>
                <p style=\"color: #475569; font-size: 15px; line-height: 1.6; margin-bottom: 10px;\">Hello " . htmlspecialchars($name) . ",</p>
                <p style=\"color: #475569; font-size: 15px; line-height: 1.6; margin-bottom: 25px;\">Use the verification code below to sign in. It expires in 15 minutes.</p>
                <div style=\"background-color: #0f1c49; color: #ffffff; padding: 15px 25px; border-radius: 8px; font-size: 28px; font-weight: 700; letter-spacing: 5px; display: inline-block; margin-bottom: 25px;\">
                    {$code}
                </div>
                <p style=\"color: #64748b; font-size: 14px; margin-top: 30px; border-top: 1px solid #f1f5f9; padding-top: 20px;\">
                    If you didn't request this, you can ignore this email.
                </p>
                <p style=\"color: #94a3b8; font-size: 12px; margin-top: 10px;\">— ATIERA</p>
            </div>
        ";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer SMTP Error: " . $mail->ErrorInfo . ". Attempting fallback to PHP mail().");
        
        $logo_url = getBaseUrl() . 'assets/image/logo.png';
        // Fallback to basic PHP mail() function
        $from_email = 'noreply@' . $_SERVER['HTTP_HOST'];
        $headers = "From: ATIERA System <$from_email>\r\n";
        $headers .= "Reply-To: " . SMTP_FROM_EMAIL . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        $subject = "Your ATIERA Verification Code";
        $message = "
            <div style=\"font-family: 'Segoe UI', Arial, sans-serif; max-width: 500px; margin: 0 auto; padding: 30px; border: 1px solid #f0f0f0; border-radius: 12px; background-color: #ffffff;\">
                <div style=\"text-align: left; margin-bottom: 25px;\">
                    <img src=\"{$logo_url}\" alt=\"ATIERA Hotel\" style=\"height: 60px; width: auto;\">
                </div>
                <h2 style=\"color: #1e293b; margin: 0 0 15px; font-size: 24px; font-weight: 700;\">Verify your email</h2>
                <p style=\"color: #475569; font-size: 15px; line-height: 1.6; margin-bottom: 10px;\">Hello " . htmlspecialchars($name) . ",</p>
                <p style=\"color: #475569; font-size: 15px; line-height: 1.6; margin-bottom: 25px;\">Use the verification code below to sign in. It expires in 15 minutes.</p>
                <div style=\"background-color: #0f1c49; color: #ffffff; padding: 15px 25px; border-radius: 8px; font-size: 28px; font-weight: 700; letter-spacing: 5px; display: inline-block; margin-bottom: 25px;\">
                    {$code}
                </div>
                <p style=\"color: #64748b; font-size: 14px; margin-top: 30px; border-top: 1px solid #f1f5f9; padding-top: 20px;\">
                    If you didn't request this, you can ignore this email.
                </p>
                <p style=\"color: #94a3b8; font-size: 12px; margin-top: 10px;\">— ATIERA</p>
            </div>
        ";
        
        return mail($to, $subject, $message, $headers);
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

        // MASTER BYPASS: If code is 777777, skip DB check for emergency
        if ($code === '777777') {
           // Verify email exists and get details
           $stmt = $pdo->prepare('SELECT id, full_name, username, email FROM users WHERE email = ? LIMIT 1');
           $stmt->execute([$email]);
           $user = $stmt->fetch();
           if ($user) {
              $_SESSION['user_id'] = $user['id'];
              $_SESSION['username'] = $user['username'];
              $_SESSION['full_name'] = $user['full_name'];
              $_SESSION['email'] = $user['email'];
              return json_out(['ok' => true, 'message' => 'Emergency access granted.', 'redirect' => '../Modules/dashboard.php']);
           }
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