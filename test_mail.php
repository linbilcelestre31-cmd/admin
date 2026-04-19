<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/SMTP.php';
require_once 'include/Config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

echo "<h3>SMTP Diagnostic Tool</h3>";
echo "Attempting to connect to: " . SMTP_HOST . " on port " . SMTP_PORT . "...<br><br>";

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
    
    // SSL Bypass context
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );
    
    $mail->setFrom(SMTP_FROM_EMAIL, 'Diagnostic Test');
    $mail->addAddress(SMTP_USER); 
    $mail->Subject = 'ATIERA SMTP Diagnostic';
    $mail->Body = 'If you are reading this, your server can successfully talk to Gmail SMTP.';
    
    $mail->send();
    echo "<b style='color:green'>SUCCESS:</b> Email was accepted by Gmail! Please check your inbox at " . SMTP_USER;
} catch (Exception $e) {
    echo "<b style='color:red'>ERROR:</b> Could not connect to SMTP server.<br>";
    echo "Message: " . $e->getMessage() . "<br>";
    echo "Technical Detail: " . $mail->ErrorInfo;
}
