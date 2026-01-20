<?php
$host = '127.0.0.1';
$user = 'admin_new';
$pass = '123';
$db = 'admin_new';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>