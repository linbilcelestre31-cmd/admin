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
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

        // SSL Bypass
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to, $name);
        $mail->isHTML(true);
        $mail->Subject = 'Your ATIERA Verification Code';
        $mail->Body = "
            <div style=\"font-family: sans-serif; padding: 20px; color: #1e293b;\">
                <h2 style=\"color: #0f172a;\">Verify Login</h2>
                <p>Hello {$name},</p>
                <p>Please use the following code to complete your login:</p>
                <div style=\"font-size: 24px; font-weight: bold; letter-spacing: 5px; color: #1e40af; margin: 20px 0;\">
                    {$code}
                </div>
                <p>This code expires in 15 minutes.</p>
            </div>
        ";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
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
                $_SESSION['name'] = $row['full_name'];

                // Cleanup codes
                $pdo->prepare('DELETE FROM email_verifications WHERE user_id = ?')->execute([$row['id']]);

                $pdo->commit();
                json_out(['ok' => true, 'message' => 'Registration complete!', 'redirect' => '../Modules/facilities-reservation.php']);
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
            $_SESSION['name'] = $name;
            // Note: Role removed as per previous instructions

            // Cleanup
            unset($_SESSION['temp_user_id']);
            unset($_SESSION['temp_username']);
            unset($_SESSION['temp_email']);
            unset($_SESSION['temp_name']);
            // unset($_SESSION['temp_role']); // Was removed previously

            $pdo->prepare('DELETE FROM email_verifications WHERE user_id = ?')->execute([$userId]);

            json_out(['ok' => true, 'redirect' => '../Modules/facilities-reservation.php']);
        } else {
            json_out(['ok' => false, 'message' => 'Invalid or expired code.'], 400);
        }
    }

} catch (Exception $e) {
    json_out(['ok' => false, 'message' => 'Server error.'], 500);
}
?>