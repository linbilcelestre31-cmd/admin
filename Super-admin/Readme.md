# Super Admin Database Schema 🗄️

This document contains the SQL queries required to set up the Super Admin and SSO system.

## 1. Super Admin Authentication Table
This table stores the centralized credentials and API keys for the Super Admin users.

```sql
CREATE TABLE IF NOT EXISTS `SuperAdminLogin_tb` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `username` varchar(50) NOT NULL,
    `full_name` varchar(100) NOT NULL,
    `password` varchar(255) NOT NULL,
    `email` varchar(100) NOT NULL,
    `api_key` varchar(255) NOT NULL,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `username` (`username`),
    UNIQUE KEY `api_key` (`api_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default Admin (Password: password)
INSERT INTO `SuperAdminLogin_tb` (`username`, `full_name`, `password`, `email`, `api_key`) 
VALUES ('admin', 'Super Administrator', '$2y$10$8Wk/XfV/P2hP.JzU4.v.XuL6.v.X/X.v.X.v.X.v.X.v.X.v.X.v.', 'atiera41001@gmail.com', SHA2(RAND(), 256));
```

## 2. SSO Department Secrets Table
This table stores the shared secret keys used to sign SSO tokens for different clusters (HR1, HR2, HR3, etc.).

```sql
CREATE TABLE IF NOT EXISTS department_secrets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  department VARCHAR(50) UNIQUE,
  secret_key VARCHAR(255),
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Initialize Department Secrets
INSERT INTO department_secrets (department, secret_key) VALUES
('HR1', SHA2('hr_secret_key_2026', 256)),
('HR2', SHA2('hr_secret_key_2026', 256)),
('HR3', SHA2('hr_secret_key_2026', 256)),
('HR4', SHA2('hr_secret_key_2026', 256)),
('CORE1', SHA2('hr_secret_key_2026', 256)),
('CORE2', SHA2('hr_secret_key_2026', 256)),
('LOG1', SHA2('hr_secret_key_2026', 256)),
('LOG2', SHA2('hr_secret_key_2026', 256))
ON DUPLICATE KEY UPDATE secret_key = VALUES(secret_key);
```

## 3. Documents Archive Table
Used for the Document Management (Archiving) module.

```sql
CREATE TABLE IF NOT EXISTS `documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `category` varchar(50) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_deleted` tinyint(1) DEFAULT 0,
  `deleted_date` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---
**Note:** These tables should be created in the `admin_new` database or the dedicated per-cluster database for SSO integration.
