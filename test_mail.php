<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/SMTP.php';
require_once 'include/Config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

echo "<h3>SMTP Diagnostic Tool v2</h3>";
$host = 'smtp.gmail.com';
$port = 587;

echo "1. Resolving DNS for $host... ";
$ip = gethostbyname($host);
if ($ip === $host) {
    echo "<b style='color:red'>FAILED</b> (Could not resolve DNS)<br>";
} else {
    echo "<b style='color:green'>SUCCESS</b> (IP: $ip)<br>";
}

echo "2. Testing socket connection to $host:$port... ";
$connection = @fsockopen($host, $port, $errno, $errstr, 5);
if (is_resource($connection)) {
    echo "<b style='color:green'>SUCCESS</b> (Port is OPEN)<br>";
    fclose($connection);
} else {
    echo "<b style='color:red'>FAILED</b> (Port is CLOSED or BLOCKED: $errstr)<br>";
}

echo "<br>3. Attempting full PHPMailer handshake...<br>";

$mail = new PHPMailer(true);
try {
    $mail->SMTPDebug = 2;
    $mail->Debugoutput = 'echo';
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USER;
    $mail->Password = SMTP_PASS;
    $mail->Port = SMTP_PORT;
    $mail->SMTPSecure = SMTP_SECURE;
    $mail->Timeout = 10;
    
    $mail->SMTPOptions = array(
        'ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true)
    );
    
    $mail->setFrom(SMTP_FROM_EMAIL, 'Diagnostic Test');
    $mail->addAddress(SMTP_USER); 
    $mail->Subject = 'ATIERA SMTP Diagnostic';
    $mail->Body = 'Connection works!';
    
    $mail->send();
    echo "<br><b style='color:green'>PHPMailer SUCCESS!</b> Please check your inbox.";
} catch (Exception $e) {
    echo "<br><b style='color:red'>PHPMailer FAILED:</b> " . $e->getMessage();
}
