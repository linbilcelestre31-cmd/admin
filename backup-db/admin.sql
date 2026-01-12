-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 11, 2025 at 01:51 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `admin`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`adminati`@`localhost` PROCEDURE `CheckFacilityAvailability` (IN `p_facility_id` INT, IN `p_event_date` DATE, IN `p_start_time` TIME, IN `p_end_time` TIME)   BEGIN
    SELECT COUNT(*) as conflict_count
    FROM `reservations`
    WHERE facility_id = p_facility_id
    AND event_date = p_event_date
    AND status IN ('confirmed', 'pending')
    AND (
        (start_time <= p_start_time AND end_time > p_start_time) OR
        (start_time < p_end_time AND end_time >= p_end_time) OR
        (start_time >= p_start_time AND end_time <= p_end_time)
    );
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `administrators`
--

CREATE TABLE `administrators` (
  `admin_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('super_admin','manager','staff') DEFAULT 'staff',
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `login_attempts` int(11) DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `administrators`
--

INSERT INTO `administrators` (`admin_id`, `username`, `email`, `password_hash`, `full_name`, `role`, `is_active`, `last_login`, `login_attempts`, `locked_until`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'super_admin', 1, NULL, 0, NULL, '2025-10-16 07:25:42', '2025-10-16 07:25:42');

-- --------------------------------------------------------

--
-- Table structure for table `billing`
--

CREATE TABLE `billing` (
  `id` int(11) NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `client_name` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `due_date` date NOT NULL,
  `status` enum('paid','pending','overdue') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `billing`
--

INSERT INTO `billing` (`id`, `invoice_number`, `client_name`, `amount`, `due_date`, `status`, `created_at`) VALUES
(1, 'INV-001', 'Hotel Management Corp', 2500.00, '2023-07-15', 'paid', '2025-10-09 13:06:58'),
(2, 'INV-002', 'Restaurant Owner LLC', 1800.00, '2023-08-05', 'pending', '2025-10-09 13:06:58'),
(3, 'INV-003', 'Hotel Chain International', 5200.00, '2023-06-30', 'overdue', '2025-10-09 13:06:58'),
(4, 'INV-004', 'Boutique Hotel Group', 3200.00, '2023-09-10', 'pending', '2025-10-09 13:06:58');

-- --------------------------------------------------------

--
-- Table structure for table `cases`
--

CREATE TABLE `cases` (
  `id` int(11) NOT NULL,
  `case_id` varchar(20) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('open','pending','closed') DEFAULT 'open',
  `date_filed` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cases`
--

INSERT INTO `cases` (`id`, `case_id`, `title`, `description`, `status`, `date_filed`, `created_at`) VALUES
(1, 'C-001', 'Employment Dispute - Hotel Staff', 'Dispute regarding overtime pay and working conditions for hotel staff members.', 'open', '2023-05-15', '2025-10-09 13:06:58'),
(2, 'C-002', 'Contract Breach - Restaurant Supplier', 'Supplier failed to deliver agreed quantities of ingredients as per contract.', 'pending', '2023-06-22', '2025-10-09 13:06:58'),
(3, 'C-003', 'Customer Injury Claim', 'Customer slipped and fell in restaurant premises, claiming negligence.', 'closed', '2023-04-10', '2025-10-09 13:06:58'),
(4, 'C-004', 'Licensing Agreement Violation', 'Hotel franchise violated terms of licensing agreement with corporate.', 'open', '2023-07-05', '2025-10-09 13:06:58');

-- --------------------------------------------------------

--
-- Table structure for table `contacts`
--

CREATE TABLE `contacts` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contacts`
--

INSERT INTO `contacts` (`id`, `name`, `email`, `phone`, `role`, `created_at`) VALUES
(1, 'John Smith', 'john@hotelchain.com', '(555) 123-4567', 'Hotel Manager', '2025-10-09 13:06:59'),
(2, 'Sarah Johnson', 'sarah@restaurant.com', '(555) 987-6543', 'Restaurant Owner', '2025-10-09 13:06:59'),
(3, 'Michael Brown', 'michael@supplier.com', '(555) 456-7890', 'Supplier', '2025-10-09 13:06:59'),
(4, 'Emily Davis', 'emily@hotelgroup.com', '(555) 111-2222', 'HR Director', '2025-10-09 13:06:59'),
(5, 'Robert Wilson', 'robert@legal.com', '(555) 333-4444', 'Legal Counsel', '2025-10-09 13:06:59');

-- --------------------------------------------------------

--
-- Table structure for table `contracts`
--

CREATE TABLE `contracts` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `case_id` varchar(50) NOT NULL,
  `risk_level` enum('Low','Medium','High') NOT NULL,
  `risk_score` int(11) NOT NULL CHECK (`risk_score` >= 0 and `risk_score` <= 100),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `description` text DEFAULT NULL,
  `risk_factors` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`risk_factors`)),
  `recommendations` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`recommendations`)),
  `analysis_summary` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contracts`
--

INSERT INTO `contracts` (`id`, `name`, `case_id`, `risk_level`, `risk_score`, `created_at`, `description`, `risk_factors`, `recommendations`, `analysis_summary`) VALUES
(1, 'Hotel Lease Agreement.pdf', 'C-001', 'High', 85, '2025-10-12 04:18:43', NULL, NULL, NULL, NULL),
(2, 'Supplier Contract.docx', 'C-002', 'Medium', 60, '2025-10-12 04:18:43', NULL, NULL, NULL, NULL),
(3, 'Employment Agreement.pdf', 'C-003', 'Low', 25, '2025-10-12 04:18:43', NULL, NULL, NULL, NULL),
(4, 'Service Provider Contract.pdf', 'C-004', 'High', 90, '2025-10-12 04:18:43', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `company` varchar(255) DEFAULT NULL,
  `preferences` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `upload_date` date NOT NULL,
  `is_deleted` tinyint(1) DEFAULT 0,
  `deleted_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `facilities`
--

CREATE TABLE `facilities` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` enum('banquet','meeting','conference','outdoor','dining','lounge') NOT NULL,
  `capacity` int(11) NOT NULL,
  `location` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `hourly_rate` decimal(10,2) NOT NULL,
  `amenities` text DEFAULT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `status` enum('active','inactive','maintenance') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `facilities`
--

INSERT INTO `facilities` (`id`, `name`, `type`, `capacity`, `location`, `description`, `hourly_rate`, `amenities`, `image_url`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Grand Ballroom', 'banquet', 300, 'Main Building, 1st Floor', 'Elegant ballroom with crystal chandeliers and marble floors, perfect for weddings and large corporate events. Features state-of-the-art sound system and professional lighting.', 15000.00, 'Stage, Sound System, Lighting, Projector, Catering Kitchen, VIP Lounge', NULL, 'active', '2025-10-18 10:45:08', '2025-10-18 10:45:08'),
(2, 'Executive Boardroom', 'meeting', 20, 'Executive Wing, 3rd Floor', 'Premium meeting space with leather chairs and mahogany table. Ideal for executive meetings and client presentations.', 3500.00, 'Projector, Whiteboard, Video Conferencing, High-Speed WiFi, Refreshment Center', NULL, 'active', '2025-10-18 10:45:08', '2025-10-18 10:45:08'),
(3, 'Sky Garden', 'outdoor', 150, 'Rooftop', 'Beautiful outdoor venue with panoramic city views. Perfect for cocktail parties, receptions, and social gatherings.', 8000.00, 'Garden Setting, Bar Area, Lighting, Sound System, Weather Protection', NULL, 'active', '2025-10-18 10:45:08', '2025-10-18 10:45:08'),
(4, 'Pacific Conference Hall', 'conference', 200, 'Conference Center, 2nd Floor', 'Modern conference facility with theater-style seating. Equipped with advanced audiovisual technology.', 12000.00, 'Projector, Sound System, Stage, Green Room, Registration Area', NULL, 'active', '2025-10-18 10:45:08', '2025-10-18 10:45:08'),
(5, 'Harbor View Dining Room', 'dining', 80, 'Main Building, 5th Floor', 'Intimate dining space with stunning harbor views. Perfect for private dinners and small celebrations.', 6000.00, 'Ocean View, Private Bar, Audio System, Climate Control', NULL, 'active', '2025-10-18 10:45:08', '2025-10-18 10:45:08'),
(6, 'Sunset Lounge', 'lounge', 50, 'Poolside', 'Relaxed poolside venue with comfortable seating and tropical ambiance. Great for casual meetings and social events.', 4500.00, 'Pool Access, Bar, Lounge Furniture, Sound System', NULL, 'active', '2025-10-18 10:45:08', '2025-10-18 10:45:08');

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_logs`
--

CREATE TABLE `maintenance_logs` (
  `id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `maintenance_date` date NOT NULL,
  `assigned_staff` varchar(255) NOT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `status` enum('pending','in-progress','completed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `maintenance_logs`
--

INSERT INTO `maintenance_logs` (`id`, `item_name`, `description`, `maintenance_date`, `assigned_staff`, `contact_number`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Executive Boardroom AC', 'Airconditioning unit is not cooling properly.', '2025-01-08', 'John Doe', '09123456789', 'pending', '2025-01-08 11:45:00', '2025-01-08 11:45:00'),
(2, 'Grand Ballroom Lighting', 'Several bulb replacements needed in the main chandelier.', '2025-01-09', 'Jane Smith', '09223334444', 'in-progress', '2025-01-08 12:00:00', '2025-01-08 12:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `financial_records`
--

CREATE TABLE `financial_records` (
  `id` int(11) NOT NULL,
  `transaction_date` date NOT NULL,
  `type` enum('Income','Expense') NOT NULL,
  `category` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `venue` enum('Hotel','Restaurant','General') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `financial_records`
--

INSERT INTO `financial_records` (`id`, `transaction_date`, `type`, `category`, `description`, `amount`, `venue`, `created_at`) VALUES
(1, '2025-10-24', 'Income', 'Room Revenue', 'Room 101 - Check-out payment', 5500.00, 'Hotel', '2025-10-25 07:41:17'),
(2, '2025-10-24', 'Income', 'Food Sales', 'Restaurant Dinner Service', 1250.75, 'Restaurant', '2025-10-25 07:41:17'),
(3, '2025-10-24', 'Expense', 'Payroll', 'October Staff Payroll', 45000.00, 'General', '2025-10-25 07:41:17'),
(4, '2025-10-23', 'Expense', 'Utilities', 'Electricity bill', 8500.00, 'Hotel', '2025-10-25 07:41:17'),
(5, '2025-10-23', 'Income', 'Event Booking', 'Grand Ballroom Wedding Deposit', 15000.00, 'Hotel', '2025-10-25 07:41:17');

-- --------------------------------------------------------

--
-- Table structure for table `guests`
--

CREATE TABLE `guests` (
  `guest_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `id_type` enum('passport','driver_license','national_id','other') DEFAULT 'national_id',
  `id_number` varchar(50) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `nationality` varchar(50) DEFAULT NULL,
  `special_requests` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `inventory_id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `category` enum('food','beverage','cleaning','amenities','office') DEFAULT 'food',
  `current_stock` decimal(8,2) NOT NULL,
  `unit` varchar(20) NOT NULL,
  `min_stock_level` decimal(8,2) NOT NULL,
  `supplier` varchar(100) DEFAULT NULL,
  `unit_cost` decimal(8,2) DEFAULT NULL,
  `last_restocked` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `menu_categories`
--

CREATE TABLE `menu_categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `is_available` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `menu_categories`
--

INSERT INTO `menu_categories` (`category_id`, `category_name`, `description`, `display_order`, `is_available`, `created_at`) VALUES
(1, 'Appetizers', 'Start your meal right', 1, 1, '2025-10-16 07:25:42'),
(2, 'Main Courses', 'Hearty and delicious entrees', 2, 1, '2025-10-16 07:25:42'),
(3, 'Desserts', 'Sweet endings', 3, 1, '2025-10-16 07:25:42'),
(4, 'Beverages', 'Refreshing drinks', 4, 1, '2025-10-16 07:25:42');

-- --------------------------------------------------------

--
-- Table structure for table `menu_items`
--

CREATE TABLE `menu_items` (
  `item_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(8,2) NOT NULL,
  `preparation_time` int(11) DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `ingredients` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ingredients`)),
  `allergens` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`allergens`)),
  `image_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `menu_items`
--

INSERT INTO `menu_items` (`item_id`, `category_id`, `item_name`, `description`, `price`, `preparation_time`, `is_available`, `ingredients`, `allergens`, `image_url`, `created_at`) VALUES
(1, 1, 'Caesar Salad', 'Fresh romaine lettuce with caesar dressing', 8.99, 10, 1, NULL, NULL, NULL, '2025-10-16 07:25:42'),
(2, 2, 'Grilled Salmon', 'Atlantic salmon with lemon butter sauce', 24.99, 20, 1, NULL, NULL, NULL, '2025-10-16 07:25:42'),
(3, 3, 'Chocolate Lava Cake', 'Warm chocolate cake with molten center', 7.99, 15, 1, NULL, NULL, NULL, '2025-10-16 07:25:42'),
(4, 4, 'Fresh Orange Juice', 'Freshly squeezed orange juice', 4.99, 5, 1, NULL, NULL, NULL, '2025-10-16 07:25:42');

-- --------------------------------------------------------

--
-- Stand-in structure for view `monthly_revenue`
-- (See below for the actual view)
--
CREATE TABLE `monthly_revenue` (
`year` int(4)
,`month` int(2)
,`reservation_count` bigint(21)
,`total_revenue` decimal(32,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `guest_id` int(11) DEFAULT NULL,
  `table_id` int(11) DEFAULT NULL,
  `order_type` enum('dine_in','takeaway','room_service') DEFAULT 'dine_in',
  `status` enum('pending','preparing','ready','served','cancelled','completed') DEFAULT 'pending',
  `total_amount` decimal(8,2) NOT NULL,
  `special_instructions` text DEFAULT NULL,
  `served_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `order_item_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(8,2) NOT NULL,
  `special_instructions` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `reservation_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','credit_card','debit_card','bank_transfer','online') DEFAULT 'cash',
  `payment_status` enum('pending','paid','failed','refunded') DEFAULT 'pending',
  `transaction_id` varchar(255) DEFAULT NULL,
  `payment_date` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `reservation_id`, `amount`, `payment_method`, `payment_status`, `transaction_id`, `payment_date`, `created_at`) VALUES
(1, 1, 60000.00, 'bank_transfer', 'paid', 'TXN001234', '2025-10-18 10:45:09', '2025-10-18 10:45:09'),
(2, 2, 10500.00, 'credit_card', 'paid', 'TXN001235', '2025-10-18 10:45:09', '2025-10-18 10:45:09'),
(3, 4, 54000.00, 'online', 'paid', 'TXN001236', '2025-10-18 10:45:09', '2025-10-18 10:45:09'),
(4, 5, 24000.00, 'cash', 'paid', 'TXN001237', '2025-10-18 10:45:09', '2025-10-18 10:45:09');

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `id` int(11) NOT NULL,
  `facility_id` int(11) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `customer_email` varchar(255) NOT NULL,
  `customer_phone` varchar(50) DEFAULT NULL,
  `event_type` varchar(100) NOT NULL,
  `event_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `guests_count` int(11) NOT NULL,
  `special_requirements` text DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','confirmed','cancelled','completed') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`id`, `facility_id`, `customer_name`, `customer_email`, `customer_phone`, `event_type`, `event_date`, `start_time`, `end_time`, `guests_count`, `special_requirements`, `total_amount`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'Maria Santos', 'maria.santos@email.com', '+639171234567', 'Wedding', '2025-10-25', '14:00:00', '22:00:00', 250, 'Need vegetarian meals for 15 guests', 120000.00, 'confirmed', '2025-10-18 10:45:09', '2025-10-18 10:45:09'),
(2, 2, 'John Smith', 'john.smith@techcorp.com', '+639281234567', 'Business Meeting', '2025-10-21', '09:00:00', '12:00:00', 15, 'Video conference setup with Singapore office', 10500.00, 'confirmed', '2025-10-18 10:45:09', '2025-10-18 10:45:09'),
(3, 3, 'Robert Chen', 'robert.chen@email.com', '+639391234567', 'Birthday Party', '2025-10-28', '18:00:00', '23:00:00', 100, 'Birthday cake and special decorations', 40000.00, 'pending', '2025-10-18 10:45:09', '2025-10-18 10:45:09'),
(4, 4, 'Sarah Johnson', 'sarah.johnson@globaltech.com', '+639451234567', 'Conference', '2025-11-01', '08:00:00', '17:00:00', 150, 'Registration desk and name tags required', 108000.00, 'completed', '2025-10-18 10:45:09', '2025-10-28 11:09:38'),
(5, 5, 'David Lee', 'david.lee@email.com', '+639521234567', 'Anniversary', '2025-10-23', '19:00:00', '23:00:00', 60, 'Romantic setup with candle lights', 24000.00, 'completed', '2025-10-18 10:45:09', '2025-10-18 10:45:09');

--
-- Triggers `reservations`
--
DELIMITER $$
CREATE TRIGGER `before_reservation_update` BEFORE UPDATE ON `reservations` FOR EACH ROW BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `reservation_details`
-- (See below for the actual view)
--
CREATE TABLE `reservation_details` (
`id` int(11)
,`facility_id` int(11)
,`customer_name` varchar(255)
,`customer_email` varchar(255)
,`customer_phone` varchar(50)
,`event_type` varchar(100)
,`event_date` date
,`start_time` time
,`end_time` time
,`guests_count` int(11)
,`special_requirements` text
,`total_amount` decimal(10,2)
,`status` enum('pending','confirmed','cancelled','completed')
,`created_at` timestamp
,`updated_at` timestamp
,`facility_name` varchar(255)
,`facility_location` varchar(255)
,`hourly_rate` decimal(10,2)
,`facility_capacity` int(11)
,`duration_hours` time
);

-- --------------------------------------------------------

--
-- Table structure for table `restaurant_reservations`
--

CREATE TABLE `restaurant_reservations` (
  `res_reservation_id` int(11) NOT NULL,
  `guest_id` int(11) NOT NULL,
  `table_id` int(11) NOT NULL,
  `reservation_date` date NOT NULL,
  `reservation_time` time NOT NULL,
  `party_size` int(11) NOT NULL,
  `special_requests` text DEFAULT NULL,
  `status` enum('confirmed','pending','cancelled','seated','completed') DEFAULT 'pending',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `restaurant_tables`
--

CREATE TABLE `restaurant_tables` (
  `table_id` int(11) NOT NULL,
  `table_number` varchar(10) NOT NULL,
  `capacity` int(11) NOT NULL,
  `location` enum('indoor','outdoor','terrace','private') DEFAULT 'indoor',
  `status` enum('available','occupied','reserved','maintenance') DEFAULT 'available',
  `features` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`features`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `restaurant_tables`
--

INSERT INTO `restaurant_tables` (`table_id`, `table_number`, `capacity`, `location`, `status`, `features`, `created_at`) VALUES
(1, 'T1', 2, 'indoor', 'available', NULL, '2025-10-16 07:25:42'),
(2, 'T2', 4, 'indoor', 'available', NULL, '2025-10-16 07:25:42'),
(3, 'T3', 6, 'outdoor', 'available', NULL, '2025-10-16 07:25:42'),
(4, 'T4', 2, 'terrace', 'available', NULL, '2025-10-16 07:25:42');

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `room_id` int(11) NOT NULL,
  `room_number` varchar(10) NOT NULL,
  `type_id` int(11) NOT NULL,
  `floor` int(11) DEFAULT NULL,
  `view_type` enum('city','garden','pool','ocean','mountain') DEFAULT 'city',
  `status` enum('available','occupied','maintenance','cleaning') DEFAULT 'available',
  `special_features` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`special_features`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`room_id`, `room_number`, `type_id`, `floor`, `view_type`, `status`, `special_features`, `created_at`) VALUES
(1, '101', 1, 1, 'garden', 'available', NULL, '2025-10-16 07:25:42'),
(2, '102', 1, 1, 'city', 'available', NULL, '2025-10-16 07:25:42'),
(3, '201', 2, 2, 'pool', 'available', NULL, '2025-10-16 07:25:42'),
(4, '202', 2, 2, 'mountain', 'available', NULL, '2025-10-16 07:25:42'),
(5, '301', 3, 3, 'ocean', 'available', NULL, '2025-10-16 07:25:42');

-- --------------------------------------------------------

--
-- Table structure for table `room_types`
--

CREATE TABLE `room_types` (
  `type_id` int(11) NOT NULL,
  `type_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `base_price` decimal(10,2) NOT NULL,
  `capacity` int(11) NOT NULL,
  `amenities` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`amenities`)),
  `images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`images`)),
  `is_available` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `room_types`
--

INSERT INTO `room_types` (`type_id`, `type_name`, `description`, `base_price`, `capacity`, `amenities`, `images`, `is_available`, `created_at`) VALUES
(1, 'Standard', 'Comfortable room with basic amenities', 100.00, 2, '[\"WiFi\", \"TV\", \"Air Conditioning\"]', NULL, 1, '2025-10-16 07:25:42'),
(2, 'Deluxe', 'Spacious room with enhanced amenities', 150.00, 3, '[\"WiFi\", \"TV\", \"Air Conditioning\", \"Mini Bar\", \"Balcony\"]', NULL, 1, '2025-10-16 07:25:42'),
(3, 'Suite', 'Luxurious suite with separate living area', 250.00, 4, '[\"WiFi\", \"TV\", \"Air Conditioning\", \"Mini Bar\", \"Balcony\", \"Jacuzzi\", \"Living Room\"]', NULL, 1, '2025-10-16 07:25:42');

-- --------------------------------------------------------

--
-- Table structure for table `staff_schedules`
--

CREATE TABLE `staff_schedules` (
  `schedule_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `shift_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `department` enum('reception','housekeeping','kitchen','service','management') DEFAULT 'reception',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `log_id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `role` enum('admin','manager','staff') DEFAULT 'staff',
  `status` enum('active','inactive') DEFAULT 'active',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `full_name`, `role`, `status`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@atiera-hotel.com', '1234567', 'System Administrator', 'admin', 'active', NULL, '2025-10-18 10:45:09', '2025-10-21 10:50:22'),
(2, 'manager', 'manager@atiera-hotel.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Facilities Manager', 'manager', 'active', NULL, '2025-10-18 10:45:09', '2025-10-18 10:45:09');

-- --------------------------------------------------------

--
-- Structure for view `monthly_revenue`
--
DROP TABLE IF EXISTS `monthly_revenue`;

CREATE ALGORITHM=UNDEFINED DEFINER=`adminati`@`localhost` SQL SECURITY DEFINER VIEW `monthly_revenue`  AS SELECT year(`reservations`.`event_date`) AS `year`, month(`reservations`.`event_date`) AS `month`, count(0) AS `reservation_count`, sum(`reservations`.`total_amount`) AS `total_revenue` FROM `reservations` WHERE `reservations`.`status` = 'confirmed' GROUP BY year(`reservations`.`event_date`), month(`reservations`.`event_date`) ;

-- --------------------------------------------------------

--
-- Structure for view `reservation_details`
--
DROP TABLE IF EXISTS `reservation_details`;

CREATE ALGORITHM=UNDEFINED DEFINER=`adminati`@`localhost` SQL SECURITY DEFINER VIEW `reservation_details`  AS SELECT `r`.`id` AS `id`, `r`.`facility_id` AS `facility_id`, `r`.`customer_name` AS `customer_name`, `r`.`customer_email` AS `customer_email`, `r`.`customer_phone` AS `customer_phone`, `r`.`event_type` AS `event_type`, `r`.`event_date` AS `event_date`, `r`.`start_time` AS `start_time`, `r`.`end_time` AS `end_time`, `r`.`guests_count` AS `guests_count`, `r`.`special_requirements` AS `special_requirements`, `r`.`total_amount` AS `total_amount`, `r`.`status` AS `status`, `r`.`created_at` AS `created_at`, `r`.`updated_at` AS `updated_at`, `f`.`name` AS `facility_name`, `f`.`location` AS `facility_location`, `f`.`hourly_rate` AS `hourly_rate`, `f`.`capacity` AS `facility_capacity`, timediff(`r`.`end_time`,`r`.`start_time`) AS `duration_hours` FROM (`reservations` `r` join `facilities` `f` on(`r`.`facility_id` = `f`.`id`)) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `administrators`
--
ALTER TABLE `administrators`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `contracts`
--
ALTER TABLE `contracts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `facilities`
--
ALTER TABLE `facilities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_facilities_type` (`type`),
  ADD KEY `idx_facilities_status` (`status`);

--
-- Indexes for table `financial_records`
--
ALTER TABLE `financial_records`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `guests`
--
ALTER TABLE `guests`
  ADD PRIMARY KEY (`guest_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_guests_email` (`email`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`inventory_id`);

--
-- Indexes for table `menu_categories`
--
ALTER TABLE `menu_categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `menu_items`
--
ALTER TABLE `menu_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `guest_id` (`guest_id`),
  ADD KEY `table_id` (`table_id`),
  ADD KEY `served_by` (`served_by`),
  ADD KEY `idx_orders_status` (`status`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`order_item_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reservation_id` (`reservation_id`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_reservations_date` (`event_date`),
  ADD KEY `idx_reservations_status` (`status`),
  ADD KEY `idx_reservations_facility_date` (`facility_id`,`event_date`);

--
-- Indexes for table `maintenance_logs`
--
ALTER TABLE `maintenance_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_maintenance_date` (`maintenance_date`),
  ADD KEY `idx_maintenance_status` (`status`);

--
-- Indexes for table `restaurant_reservations`
--
ALTER TABLE `restaurant_reservations`
  ADD PRIMARY KEY (`res_reservation_id`),
  ADD KEY `guest_id` (`guest_id`),
  ADD KEY `table_id` (`table_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `restaurant_tables`
--
ALTER TABLE `restaurant_tables`
  ADD PRIMARY KEY (`table_id`),
  ADD UNIQUE KEY `table_number` (`table_number`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`room_id`),
  ADD UNIQUE KEY `room_number` (`room_number`),
  ADD KEY `type_id` (`type_id`),
  ADD KEY `idx_room_status` (`status`);

--
-- Indexes for table `room_types`
--
ALTER TABLE `room_types`
  ADD PRIMARY KEY (`type_id`);

--
-- Indexes for table `staff_schedules`
--
ALTER TABLE `staff_schedules`
  ADD PRIMARY KEY (`schedule_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_role` (`role`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `administrators`
--
ALTER TABLE `administrators`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `contracts`
--
ALTER TABLE `contracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `facilities`
--
ALTER TABLE `facilities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `financial_records`
--
ALTER TABLE `financial_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `guests`
--
ALTER TABLE `guests`
  MODIFY `guest_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `inventory_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `maintenance_logs`
--
ALTER TABLE `maintenance_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `menu_categories`
--
ALTER TABLE `menu_categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `menu_items`
--
ALTER TABLE `menu_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`id`) ON DELETE CASCADE;

DELIMITER $$
--
-- Events
--
CREATE DEFINER=`adminati`@`localhost` EVENT `CleanupOldPendingReservations` ON SCHEDULE EVERY 1 DAY STARTS '2025-10-18 10:45:09' ON COMPLETION NOT PRESERVE ENABLE DO BEGIN
    DELETE FROM `reservations` 
    WHERE status = 'pending' 
    AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
END$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
