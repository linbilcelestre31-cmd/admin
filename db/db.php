<?php
// Database connection function
function get_pdo()
{
    static $pdo = null;
    if ($pdo instanceof PDO)
        return $pdo;

    $host = 'localhost';
    $db = 'admin_new';
    $user = 'admin_new';
    $pass = '123';
    $charset = 'utf8mb4';

    $dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);
    return $pdo;
}
?>