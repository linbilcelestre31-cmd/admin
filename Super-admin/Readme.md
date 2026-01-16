# Super Admin Portal 🔐

This directory contains the Super Admin management portal for the ATIERA Administrative System.

## Access Links

To access the Super Admin area, use the following links depending on your environment:

### Live Production
*   **Login Page**: [https://admin.atierahotelandrestaurant.com/admin/Super-admin/auth/login.php](https://admin.atierahotelandrestaurant.com/admin/Super-admin/auth/login.php)
*   **Dashboard**: [https://admin.atierahotelandrestaurant.com/admin/Super-admin/Dashboard.php](https://admin.atierahotelandrestaurant.com/admin/Super-admin/Dashboard.php)

### Local Development (XAMPP)
*   **Login Page**: `http://localhost/admin/Super-admin/auth/login.php`
*   **Dashboard**: `http://localhost/admin/Super-admin/Dashboard.php`

## Features
*   **2-Factor Authentication**: Integrated with PHPMailer for secure OTP verification.
*   **User Management**: Control all administrator and staff accounts.
*   **System Monitoring**: Real-time server and database status.
*   **Audit Logs**: Complete history of system actions.

## Security
Access to the dashboard is restricted to sessions with the `super_admin` role. Unauthorized attempts are automatically redirected to the login page.

### Default Credentials
*   **Username**: `admin`
*   **Password**: `password` (Note: Check the SQL database hash for exact match)
