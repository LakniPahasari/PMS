-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:8889
-- Generation Time: May 28, 2026 at 02:36 AM
-- Server version: 8.0.44
-- PHP Version: 8.3.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `PMS`
--

-- --------------------------------------------------------

--
-- Table structure for table `ALERT`
--

CREATE TABLE `ALERT` (
  `alert_id` int NOT NULL,
  `alert_type` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `target_id` int DEFAULT NULL,
  `is_acknowledged` tinyint(1) NOT NULL DEFAULT '0',
  `triggered_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `ALERT`
--

INSERT INTO `ALERT` (`alert_id`, `alert_type`, `message`, `target_id`, `is_acknowledged`, `triggered_at`) VALUES
(1, 'allergy', 'Warning: Patient Amal Perera has a known sulfa allergy. Metformin 500mg allergy notes flagged for review.', 1, 0, '2026-05-23 10:30:05'),
(2, 'low_stock', 'Low stock: \'Amocilline 400g\' has only 4 unit(s) remaining.', NULL, 0, '2026-05-23 21:36:08'),
(3, 'age_restriction', 'ID verification required: Lakni Pahasari was prescribed \'Amocilline 100g\' — customer ID/DOB not yet confirmed.', NULL, 0, '2026-05-23 22:11:22'),
(4, 'low_stock', 'Low stock: \'Amocilline 400g\' will have 2 unit(s) remaining after this prescription.', NULL, 0, '2026-05-23 22:39:54');

-- --------------------------------------------------------

--
-- Table structure for table `AUDIT_LOG`
--

CREATE TABLE `AUDIT_LOG` (
  `log_id` int NOT NULL,
  `system_user_id` int NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `target_table` varchar(50) DEFAULT NULL,
  `target_id` int DEFAULT NULL,
  `details` text,
  `timestamp` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `AUDIT_LOG`
--

INSERT INTO `AUDIT_LOG` (`log_id`, `system_user_id`, `action_type`, `target_table`, `target_id`, `details`, `timestamp`) VALUES
(1, 1, 'prescription_approved', 'PRESCRIPTION', 1, NULL, '2026-05-23 10:31:00'),
(2, 1, 'login', 'SYSTEM_USER', 1, NULL, '2026-05-23 03:00:36'),
(3, 1, 'login', 'SYSTEM_USER', 1, NULL, '2026-05-23 03:04:11'),
(4, 1, 'customer_created', 'CUSTOMER', 2, NULL, '2026-05-23 03:05:30'),
(5, 1, 'customer_created', 'CUSTOMER', 3, NULL, '2026-05-23 03:09:16'),
(6, 1, 'customer_updated', 'CUSTOMER', 3, NULL, '2026-05-23 03:09:40'),
(7, 1, 'login', 'SYSTEM_USER', 1, NULL, '2026-05-23 20:43:23'),
(8, 1, 'stock_created', 'MEDICINE_STOCK', 2, NULL, '2026-05-23 21:35:25'),
(9, 1, 'stock_created', 'MEDICINE_STOCK', 3, NULL, '2026-05-23 21:36:08'),
(10, 1, 'stock_updated', 'MEDICINE_STOCK', 3, NULL, '2026-05-23 21:36:35'),
(11, 1, 'stock_updated', 'MEDICINE_STOCK', 2, NULL, '2026-05-23 21:37:16'),
(12, 1, 'stock_updated', 'MEDICINE_STOCK', 2, NULL, '2026-05-23 21:37:25'),
(13, 1, 'prescription_created', 'PRESCRIPTION', 2, NULL, '2026-05-23 22:11:22'),
(14, 1, 'prescription_updated', 'PRESCRIPTION', 2, NULL, '2026-05-23 22:11:46'),
(15, 1, 'prescription_created', 'PRESCRIPTION', 3, NULL, '2026-05-23 22:39:54'),
(16, 1, 'prescription_created', 'PRESCRIPTION', 4, NULL, '2026-05-23 22:50:36'),
(17, 1, 'prescription_updated', 'PRESCRIPTION', 4, NULL, '2026-05-24 03:14:14'),
(18, 1, 'prescription_updated', 'PRESCRIPTION', 4, '{\"new_status\":\"approved\",\"old_status\":\"approved\"}', '2026-05-24 03:46:35'),
(19, 1, 'prescription_updated', 'PRESCRIPTION', 3, '{\"new_status\":\"pending\",\"old_status\":\"pending\"}', '2026-05-24 03:46:42'),
(20, 1, 'stock_updated', 'MEDICINE_STOCK', 2, NULL, '2026-05-24 03:46:53'),
(21, 1, 'customer_updated', 'CUSTOMER', 3, '{\"Date of Birth\":{\"before\":\"2026-05-23\",\"after\":\"1989-02-23\"}}', '2026-05-24 03:49:07'),
(22, 1, 'stock_updated', 'MEDICINE_STOCK', 3, NULL, '2026-05-24 03:56:50'),
(23, 1, 'payment_created', 'PAYMENT', 2, '{\"prescription_id\":3,\"status\":\"paid\"}', '2026-05-24 04:03:30'),
(24, 1, 'payment_updated', 'PAYMENT', 1, '{\"old_status\":\"unpaid\",\"new_status\":\"awaiting_pickup\"}', '2026-05-24 04:07:07'),
(25, 1, 'prescription_created', 'PRESCRIPTION', 5, NULL, '2026-05-24 04:09:03'),
(26, 1, 'payment_created', 'PAYMENT', 3, '{\"prescription_id\":5,\"status\":\"paid\"}', '2026-05-24 04:09:14');

-- --------------------------------------------------------

--
-- Table structure for table `CUSTOMER`
--

CREATE TABLE `CUSTOMER` (
  `customer_id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `address` text,
  `medical_history` text,
  `allergies` text,
  `account_active` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `CUSTOMER`
--

INSERT INTO `CUSTOMER` (`customer_id`, `name`, `email`, `date_of_birth`, `address`, `medical_history`, `allergies`, `account_active`) VALUES
(1, 'Amal Perera', 'amal.perera@gmail.com', '1990-03-15', '42 Galle Road, Colombo 03', 'Type 2 Diabetes, Hypertension', 'Penicillin, Sulfa drugs', 1),
(2, 'Lakni Pahasari', 'lakni.pahasari@riskonnect.com', '2000-12-08', '89 Fernbank Place', 'None', 'None', 1),
(3, 'John Allen', 'john@gmail.com', '1989-02-23', '89 Fernbank Place', 'Heart stoke', 'Asprin and pencillline', 1);

-- --------------------------------------------------------

--
-- Table structure for table `MEDICINE_STOCK`
--

CREATE TABLE `MEDICINE_STOCK` (
  `stock_id` int NOT NULL,
  `medication_name` varchar(150) NOT NULL,
  `batch_number` varchar(100) DEFAULT NULL,
  `quantity` int NOT NULL DEFAULT '0',
  `unit_price` decimal(10,2) DEFAULT NULL,
  `age_limit` int NOT NULL DEFAULT '0',
  `description` text,
  `allergy_notes` text,
  `expiry_date` date DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `supplier` varchar(150) DEFAULT NULL,
  `requires_id_check` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `MEDICINE_STOCK`
--

INSERT INTO `MEDICINE_STOCK` (`stock_id`, `medication_name`, `batch_number`, `quantity`, `unit_price`, `age_limit`, `description`, `allergy_notes`, `expiry_date`, `category`, `supplier`, `requires_id_check`, `is_active`) VALUES
(1, 'Metformin 500mg', NULL, 200, NULL, 0, 'Oral medication for Type 2 Diabetes. Take 1 tablet twice daily with meals.', 'May cause reactions in patients with sulfa allergy.', '2027-12-31', 'Antidiabetic', 'PharmaCo Lanka Pvt Ltd', 0, 1),
(2, 'Amocilline 100g', 'BT20253245', 200, 2.00, 0, NULL, NULL, '2028-05-23', 'Antibiotic', 'KDH', 1, 1),
(3, 'Amocilline 400g', 'BT20253245', 48, 7.00, 0, NULL, NULL, '2026-05-31', 'Antibiotic', 'KDH', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `PAYMENT`
--

CREATE TABLE `PAYMENT` (
  `payment_id` int NOT NULL,
  `prescription_id` int NOT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `payment_method` enum('cash','card') DEFAULT NULL,
  `payment_status` enum('unpaid','awaiting_pickup','paid') NOT NULL DEFAULT 'unpaid',
  `notes` text,
  `paid_at` datetime DEFAULT NULL,
  `system_user_id` int DEFAULT NULL,
  `billed_date` date NOT NULL,
  `invoice_pdf_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `PAYMENT`
--

INSERT INTO `PAYMENT` (`payment_id`, `prescription_id`, `amount`, `payment_method`, `payment_status`, `notes`, `paid_at`, `system_user_id`, `billed_date`, `invoice_pdf_path`) VALUES
(1, 1, 1250.00, NULL, 'awaiting_pickup', NULL, NULL, NULL, '2026-05-23', '/invoices/2026/05/INV-00001.pdf'),
(2, 3, 84.00, 'cash', 'paid', NULL, '2026-05-24 08:03:30', 1, '2026-05-24', NULL),
(3, 5, 20.00, 'card', 'paid', NULL, '2026-05-24 08:09:14', 1, '2026-05-24', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `PRESCRIPTION`
--

CREATE TABLE `PRESCRIPTION` (
  `prescription_id` int NOT NULL,
  `customer_id` int NOT NULL,
  `system_user_id` int NOT NULL,
  `special_notes` text,
  `rejection_reason` text,
  `next_refill_date` date DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'pending',
  `allergy_checked` tinyint(1) NOT NULL DEFAULT '0',
  `age_id_verified` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `PRESCRIPTION`
--

INSERT INTO `PRESCRIPTION` (`prescription_id`, `customer_id`, `system_user_id`, `special_notes`, `rejection_reason`, `next_refill_date`, `status`, `allergy_checked`, `age_id_verified`, `created_at`, `processed_at`) VALUES
(1, 1, 1, 'Patient to monitor blood sugar levels weekly. Return if side effects occur.', NULL, '2026-06-23', 'approved', 1, 0, '2026-05-23 10:30:00', NULL),
(2, 2, 1, '2 per day', NULL, '2026-06-30', 'pending', 1, 1, '2026-05-23 22:11:22', NULL),
(3, 3, 1, 'Daily', NULL, '2026-05-25', 'pending', 1, 1, '2026-05-23 22:39:54', NULL),
(4, 2, 1, 'Daily dose', NULL, '2026-05-24', 'approved', 1, 1, '2026-05-23 22:50:36', NULL),
(5, 1, 1, NULL, NULL, '2026-06-24', 'pending', 1, 1, '2026-05-24 04:09:03', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `PRESCRIPTION_ITEM`
--

CREATE TABLE `PRESCRIPTION_ITEM` (
  `item_id` int NOT NULL,
  `prescription_id` int NOT NULL,
  `stock_id` int NOT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `dosage` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `PRESCRIPTION_ITEM`
--

INSERT INTO `PRESCRIPTION_ITEM` (`item_id`, `prescription_id`, `stock_id`, `quantity`, `dosage`) VALUES
(10, 4, 2, 50, NULL),
(11, 4, 1, 50, NULL),
(12, 3, 2, 35, NULL),
(13, 3, 3, 2, NULL),
(14, 3, 1, 10, NULL),
(15, 5, 2, 10, NULL),
(16, 5, 1, 4, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `SYSTEM_USER`
--

CREATE TABLE `SYSTEM_USER` (
  `system_user_id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` varchar(20) NOT NULL,
  `branch` varchar(100) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `SYSTEM_USER`
--

INSERT INTO `SYSTEM_USER` (`system_user_id`, `name`, `email`, `password_hash`, `role`, `branch`, `last_login`, `is_active`) VALUES
(1, 'Sarah Johnson', 'sarah.johnson@pharmacy.com', '$2y$10$ISujyD7btEA18DNMojCppeBGDIz9XseNhu9zEVhtuCJIJcArg2Wsy', 'pharmacist', 'Colombo Main Branch', '2026-05-23 20:43:23', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `ALERT`
--
ALTER TABLE `ALERT`
  ADD PRIMARY KEY (`alert_id`);

--
-- Indexes for table `AUDIT_LOG`
--
ALTER TABLE `AUDIT_LOG`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `fk_audit_user` (`system_user_id`);

--
-- Indexes for table `CUSTOMER`
--
ALTER TABLE `CUSTOMER`
  ADD PRIMARY KEY (`customer_id`);

--
-- Indexes for table `MEDICINE_STOCK`
--
ALTER TABLE `MEDICINE_STOCK`
  ADD PRIMARY KEY (`stock_id`);

--
-- Indexes for table `PAYMENT`
--
ALTER TABLE `PAYMENT`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `fk_payment_prescription` (`prescription_id`);

--
-- Indexes for table `PRESCRIPTION`
--
ALTER TABLE `PRESCRIPTION`
  ADD PRIMARY KEY (`prescription_id`),
  ADD KEY `fk_prescription_customer` (`customer_id`),
  ADD KEY `fk_prescription_user` (`system_user_id`);

--
-- Indexes for table `PRESCRIPTION_ITEM`
--
ALTER TABLE `PRESCRIPTION_ITEM`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `prescription_id` (`prescription_id`),
  ADD KEY `stock_id` (`stock_id`);

--
-- Indexes for table `SYSTEM_USER`
--
ALTER TABLE `SYSTEM_USER`
  ADD PRIMARY KEY (`system_user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `ALERT`
--
ALTER TABLE `ALERT`
  MODIFY `alert_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `AUDIT_LOG`
--
ALTER TABLE `AUDIT_LOG`
  MODIFY `log_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `CUSTOMER`
--
ALTER TABLE `CUSTOMER`
  MODIFY `customer_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `MEDICINE_STOCK`
--
ALTER TABLE `MEDICINE_STOCK`
  MODIFY `stock_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `PAYMENT`
--
ALTER TABLE `PAYMENT`
  MODIFY `payment_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `PRESCRIPTION`
--
ALTER TABLE `PRESCRIPTION`
  MODIFY `prescription_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `PRESCRIPTION_ITEM`
--
ALTER TABLE `PRESCRIPTION_ITEM`
  MODIFY `item_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `SYSTEM_USER`
--
ALTER TABLE `SYSTEM_USER`
  MODIFY `system_user_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `AUDIT_LOG`
--
ALTER TABLE `AUDIT_LOG`
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`system_user_id`) REFERENCES `SYSTEM_USER` (`system_user_id`);

--
-- Constraints for table `PAYMENT`
--
ALTER TABLE `PAYMENT`
  ADD CONSTRAINT `fk_payment_prescription` FOREIGN KEY (`prescription_id`) REFERENCES `PRESCRIPTION` (`prescription_id`);

--
-- Constraints for table `PRESCRIPTION`
--
ALTER TABLE `PRESCRIPTION`
  ADD CONSTRAINT `fk_prescription_customer` FOREIGN KEY (`customer_id`) REFERENCES `CUSTOMER` (`customer_id`),
  ADD CONSTRAINT `fk_prescription_user` FOREIGN KEY (`system_user_id`) REFERENCES `SYSTEM_USER` (`system_user_id`);

--
-- Constraints for table `PRESCRIPTION_ITEM`
--
ALTER TABLE `PRESCRIPTION_ITEM`
  ADD CONSTRAINT `prescription_item_ibfk_1` FOREIGN KEY (`prescription_id`) REFERENCES `PRESCRIPTION` (`prescription_id`),
  ADD CONSTRAINT `prescription_item_ibfk_2` FOREIGN KEY (`stock_id`) REFERENCES `MEDICINE_STOCK` (`stock_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
