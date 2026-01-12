Reminder 



kung mag clone ka wag kana mag download sa github ganito gawin mo 
punta ka sa terminal then cd location ng localhost mo 
then git clone https://github.com/username/repository.git


ito sa system 


first go to the http://localhost/admin-final-code/index.html
and http://localhost/admin-final-code/auth/login.php 

sa password sa login page 
username: admin@atiera-hotel.com
password: 1234567

sa system ito 
http://localhost/admin-final-code/auth/login.php 
email: atiera41001@gmail.com
atierapogiako123

kung gusto mo test ito ganito lang yan 

Email Verfication   
1. punta ka sa setting ng email mo google account mo then hanapin mo yung 2-Step Verification open mo ito 
2. sa search mo nalang ito App passwords tapos  type mo yung gusto mo doon tapos meron ka makita create button

then pag katapos yun makita ganito password 
shmv lrod aueu ehdn 
copy mo yan 
login 105 
verfiy 19
register 14

updated  

double authcation sa login register Verification
buttons Create Read Update Deleted button 
including Desging


Reminder 

first go to the http://localhost/admin-final-code/index.html
and http://localhost/admin-final-code/auth/login.php 


### SQL Commands

#### 1. Create Table `users`
```sql
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### 2. Insert Admin User
User: `admin` / `atiera41001@gmail.com`
Password: `123` (hashed)
```sql
INSERT INTO `users` (`full_name`, `username`, `email`, `password_hash`) VALUES
('admin', 'admin', 'atiera41001@gmail.com', '$2y$10$bgK1qBmMkUhTXD7vpn7LzerdFk/ELZfeBjRGAeJy9zIzzW6xA8kDu');
```

#### 3. Create Table `maintenance_logs`
```sql
CREATE TABLE `maintenance_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `maintenance_date` date NOT NULL,
  `assigned_staff` varchar(255) NOT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `status` enum('pending','in-progress','completed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_maintenance_date` (`maintenance_date`),
  KEY `idx_maintenance_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Sample Data to populate the table
INSERT INTO `maintenance_logs` (`id`, `item_name`, `description`, `maintenance_date`, `assigned_staff`, `contact_number`, `status`) VALUES
(1, 'Executive Boardroom AC', 'Airconditioning unit is not cooling properly.', '2025-01-08', 'John Doe', '09123456789', 'pending'),
(2, 'Grand Ballroom Lighting', 'Several bulb replacements needed in the main chandelier.', '2025-01-09', 'Jane Smith', '09223334444', 'in-progress');
```
