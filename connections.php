<?php
$host = 'localhost';
$user = 'admin_new';
$pass = '123';
$db = 'admin_new';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>