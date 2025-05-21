-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 15, 2025 at 12:50 AM
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
-- Database: `field_project`
--

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` int(11) NOT NULL,
  `contract_number` varchar(100) DEFAULT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `customer_address` text DEFAULT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `car_info` text DEFAULT NULL,
  `debt_amount` decimal(10,2) DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `status` enum('pending','completed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `jobs`
--

INSERT INTO `jobs` (`id`, `contract_number`, `customer_name`, `customer_address`, `customer_phone`, `car_info`, `debt_amount`, `assigned_to`, `status`, `created_at`) VALUES
(48, 'C100', 'ปิ่นบุญญา', 'ถ.นาควงษ์', '0 2624 8840', 'Isuzu D-Max สีขาว', 379957.00, 2, 'pending', '2025-05-14 22:50:04'),
(49, 'C101', 'วรรณนิสา', 'ถ.ดาตู', '0-5355-2155', 'Honda Civic สีแดง', 459546.00, 3, 'pending', '2025-05-14 22:50:04'),
(50, 'C102', 'หรรษธร', 'ถ.แนวพญา', '+66 (0) 938 094 132', 'Toyota Vios สีขาว', 348178.00, 3, 'pending', '2025-05-14 22:50:04'),
(51, 'C103', 'นพวรรณ', 'ถนนซาซุม', '05 098 9290', 'Isuzu Vios สีแดง', 454159.00, 3, 'pending', '2025-05-14 22:50:04'),
(52, 'C104', 'โอภาส', 'ถนนหิรัญสาลี', '0-5857-3311', 'Isuzu Civic สีแดง', 423490.00, 3, 'pending', '2025-05-14 22:50:04'),
(53, 'C105', 'จิม', 'ถ.นิยมเซียม', '098 020 6441', 'Honda Civic สีดำ', 473847.00, 2, 'pending', '2025-05-14 22:50:04'),
(54, 'C106', 'ไมล์', 'ถนนนักรบ', '0 5167 5517', 'Toyota Vios สีดำ', 323878.00, 3, 'pending', '2025-05-14 22:50:04'),
(55, 'C107', 'วรนาฎ', 'ถนนด้วงโสน', '+66 5915 1570', 'Toyota D-Max สีขาว', 143863.00, 3, 'pending', '2025-05-14 22:50:04'),
(56, 'C108', 'ยนงคราญ', 'ถ.ฉิมพาลี', '+66 (0) 3124 5838', 'Toyota Civic สีแดง', 329921.00, 2, 'pending', '2025-05-14 22:50:04'),
(57, 'C109', 'อัครพนธ์', 'ถนนสันตะวงศ์', '021662514', 'Toyota Vios สีขาว', 474443.00, 3, 'pending', '2025-05-14 22:50:04'),
(58, 'C110', 'ฐิติยาพร', 'ถ.เดชวา', '02 820 5549', 'Toyota D-Max สีขาว', 490811.00, 2, 'pending', '2025-05-14 22:50:04'),
(59, 'C111', 'กระสุน', 'ถนนถนัดการยนต์', '03 430 5002', 'Toyota Civic สีขาว', 420796.00, 2, 'pending', '2025-05-14 22:50:04'),
(60, 'C112', 'วรรณกร', 'ถนนทับทิมไทย', '+66 666 521 846', 'Honda D-Max สีขาว', 182081.00, 2, 'pending', '2025-05-14 22:50:04'),
(61, 'C113', 'ชรินทร์ทิพย์', 'ถนนนกทอง', '026 525863', 'Honda Civic สีแดง', 130212.00, 2, 'pending', '2025-05-14 22:50:04'),
(62, 'C114', 'นิวิลดาน', 'ถนนศาสตร์ศิลป์', '079 131501', 'Toyota D-Max สีขาว', 134183.00, 2, 'pending', '2025-05-14 22:50:04'),
(63, 'C115', 'วิสาร', 'ถ.หนักแน่น', '041 342580', 'Honda Vios สีดำ', 439417.00, 3, 'pending', '2025-05-14 22:50:04'),
(64, 'C116', 'ทิมาภรณ์', 'ถนนนาถะพินธุ', '+66 (0) 818 334 614', 'Honda D-Max สีแดง', 356496.00, 3, 'pending', '2025-05-14 22:50:04'),
(65, 'C117', 'เบญญาทิพย์', 'ถนนพรมอ่อน', '048812931', 'Honda Civic สีแดง', 122526.00, 2, 'pending', '2025-05-14 22:50:04'),
(66, 'C118', 'ชรินทร์ทิพย์', 'ถ.อุลหัสสา', '+66 912 339 076', 'Honda D-Max สีดำ', 345719.00, 3, 'pending', '2025-05-14 22:50:04'),
(67, 'C119', 'ประไพพักตร์', 'ถ.ตรีครุธพันธุ์', '0-3385-0557', 'Honda Civic สีดำ', 195802.00, 2, 'pending', '2025-05-14 22:50:04');

-- --------------------------------------------------------

--
-- Table structure for table `job_logs`
--

CREATE TABLE `job_logs` (
  `id` int(11) NOT NULL,
  `job_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `gps` varchar(100) DEFAULT NULL,
  `images` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('admin','field') DEFAULT 'field',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `username`, `password`, `role`, `created_at`, `active`) VALUES
(1, 'Admin', 'admin', '$2y$10$2bFjZ3g2Lf3olS5z/PoU5eLqB8D0pgqKxZbdJch5VjVwOLa7guV.W', 'admin', '2025-05-14 18:49:26', 1),
(2, 'Field Officer', 'field', '$2y$10$2bFjZ3g2Lf3olS5z/PoU5eLqB8D0pgqKxZbdJch5VjVwOLa7guV.W', 'field', '2025-05-14 18:49:26', 1),
(3, '123', 'admin1', '$2y$10$U/xP9mN.SAooyx38OPal/eu7oS7tBoEymjaZvsgFGpsOOM3xmcTKK', 'admin', '2025-05-14 19:52:22', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `job_logs`
--
ALTER TABLE `job_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT for table `job_logs`
--
ALTER TABLE `job_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
