<?php
// Central SMTP Configuration
// Change these values to use a different sender email account

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');

define('SMTP_USER', 'atiera41001@gmail.com');
define('SMTP_PASS', 'sqbhijukobzglnwk');
define('SMTP_FROM_EMAIL', 'atiera41001@gmail.com');
define('SMTP_FROM_NAME', 'ATIERA Hotel');

// Base URL detection (helps with subdomains)
function getBaseUrl()
{
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
    $host = $_SERVER['HTTP_HOST'];
    $currentDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    // Go up levels until we reach the 'admin' or project root
    // For admin.atierahotelandrestaurant.com/admin/include/Settings.php, we want /admin/
    $parts = explode('/', trim($currentDir, '/'));
    if (in_array('include', $parts)) {
        $projectRoot = '/' . implode('/', array_slice($parts, 0, array_search('include', $parts)));
    } elseif (in_array('auth', $parts)) {
        $projectRoot = '/' . implode('/', array_slice($parts, 0, array_search('auth', $parts)));
    } elseif (in_array('Modules', $parts)) {
        $projectRoot = '/' . implode('/', array_slice($parts, 0, array_search('Modules', $parts)));
    } elseif (in_array('Super-admin', $parts)) {
        $projectRoot = '/' . implode('/', array_slice($parts, 0, array_search('Super-admin', $parts)));
    } else {
        $projectRoot = $currentDir;
    }
    return $protocol . "://" . $host . '/' . trim($projectRoot, '/') . '/';
}
?>