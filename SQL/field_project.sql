-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 06, 2025 at 04:14 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

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
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`) VALUES
(1, 'KL'),
(2, 'Bank'),
(3, 'TC'),
(4, 'PTT'),
(5, 'KKP HP'),
(6, 'KKP OD2'),
(7, 'KKP Car3x'),
(8, 'CardX'),
(9, 'ตรีเพชร'),
(10, 'เงินให้ใจ'),
(11, 'Orico'),
(12, 'NTL'),
(13, 'AB'),
(14, 'Pool'),
(15, 'ICBC OD1'),
(16, 'ICBC OD2-5'),
(17, 'BAY'),
(18, 'ตรีเพชร B1'),
(19, 'ตรีเพชร B2'),
(20, 'Amoney'),
(21, 'ซื้อหนี้'),
(22, 'sup'),
(23, 'IT');

-- --------------------------------------------------------

--
-- Table structure for table `department_visibility`
--

CREATE TABLE `department_visibility` (
  `id` int(11) NOT NULL,
  `from_department_id` int(11) DEFAULT NULL,
  `from_user_id` int(11) DEFAULT NULL,
  `to_department_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `department_visibility`
--

INSERT INTO `department_visibility` (`id`, `from_department_id`, `from_user_id`, `to_department_id`) VALUES
(146, NULL, 45, 1),
(148, NULL, 33, 14),
(149, NULL, 34, 1);

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` int(11) NOT NULL,
  `contract_number` varchar(50) NOT NULL,
  `customer_id_card` varchar(20) DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `imported_by` int(11) DEFAULT NULL,
  `product` varchar(100) NOT NULL,
  `location_info` varchar(255) DEFAULT NULL,
  `location_area` varchar(255) DEFAULT NULL,
  `zone` varchar(100) DEFAULT NULL,
  `due_date` varchar(25) DEFAULT NULL,
  `overdue_period` varchar(50) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `model_detail` varchar(100) DEFAULT NULL,
  `plate` varchar(20) DEFAULT NULL,
  `province` varchar(50) DEFAULT NULL,
  `os` varchar(50) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `priority` varchar(20) DEFAULT 'normal',
  `remark` text DEFAULT NULL,
  `job_order` int(11) DEFAULT 0,
  `is_favorite` tinyint(1) DEFAULT 0,
  `color` varchar(100) DEFAULT NULL
) ;

--
-- Dumping data for table `jobs`
--

INSERT INTO `jobs` (`id`, `contract_number`, `customer_id_card`, `assigned_to`, `imported_by`, `product`, `location_info`, `location_area`, `zone`, `due_date`, `overdue_period`, `model`, `model_detail`, `plate`, `province`, `os`, `status`, `department_id`, `created_at`, `last_updated_by`, `updated_at`, `priority`, `remark`, `job_order`, `is_favorite`, `color`) VALUES
(18358, '164000983', '1749800298977', 46, 1, 'KKP 5', 'ชนันรัตน์ ประทุม', '109/87 ชั้นที่ 4 ห้อง 109/87 ไอคอนโดงามวงศ์วาน ซ. งามวงศ์วาน ถ. งามวงศ์วาน บางเขน เมืองนนทบุรี นนทบุรี 11000', 'บางเขน', '', '', 'BMW', 'X3 xDrive20d Highline 2.0', '3กม 5644', 'กรุงเทพมหานคร', '535,776.95', 'pending', 2, '2025-08-04 23:11:13', 1, NULL, 'normal', '0', 0, 0, 'ดำ'),
(18359, '1640009831', '1749800298977', NULL, 1, 'KL', 'ชนันรัตน์ ประทุม1', '109/87 ชั้นที่ 4 ห้อง 109/87 ไอคอนโดงามวงศ์วาน ซ. งามวงศ์วาน ถ. งามวงศ์วาน บางเขน เมืองนนทบุรี นนทบุรี 11000', 'บางเขน', '2', NULL, 'BMW', 'X3 xDrive20d Highline 2.0', '3กม 5644', 'กรุงเทพมหานคร', '535,776.95', 'pending', 2, '2025-08-04 23:11:13', NULL, NULL, 'normal', NULL, 0, 0, 'ดำ'),
(18360, '164000983', '1749800298977', NULL, 33, '11', 'ชนันรัตน์ ประทุม', '109/87 ชั้นที่ 4 ห้อง 109/87 ไอคอนโดงามวงศ์วาน ซ. งามวงศ์วาน ถ. งามวงศ์วาน บางเขน เมืองนนทบุรี นนทบุรี 11000', 'บางเขน', '2', NULL, 'BMW', 'X3 xDrive20d Highline 2.0', '3กม 5644', 'กรุงเทพมหานคร', '535,776.95', 'pending', 1, '2025-08-04 23:12:57', NULL, NULL, 'normal', NULL, 0, 0, 'ดำ'),
(18361, '1640009831', '1749800298977', NULL, 33, '11', 'ชนันรัตน์ ประทุม1', '109/87 ชั้นที่ 4 ห้อง 109/87 ไอคอนโดงามวงศ์วาน ซ. งามวงศ์วาน ถ. งามวงศ์วาน บางเขน เมืองนนทบุรี นนทบุรี 11000', 'บางเขน', '2', NULL, 'BMW', 'X3 xDrive20d Highline 2.0', '3กม 5644', 'กรุงเทพมหานคร', '535,776.95', 'pending', 1, '2025-08-04 23:12:57', NULL, NULL, 'normal', NULL, 0, 0, 'ดำ'),
(18362, '164000983', '1749800298977', NULL, 34, '22', 'ชนันรัตน์ ประทุม', '109/87 ชั้นที่ 4 ห้อง 109/87 ไอคอนโดงามวงศ์วาน ซ. งามวงศ์วาน ถ. งามวงศ์วาน บางเขน เมืองนนทบุรี นนทบุรี 11000', 'บางเขน', '2', NULL, 'BMW', 'X3 xDrive20d Highline 2.0', '3กม 5644', 'กรุงเทพมหานคร', '535,776.95', 'pending', 14, '2025-08-04 23:15:43', NULL, NULL, 'normal', NULL, 0, 0, 'ดำ'),
(18363, '1640009831', '1749800298977', NULL, 34, '22', 'ชนันรัตน์ ประทุม1', '109/87 ชั้นที่ 4 ห้อง 109/87 ไอคอนโดงามวงศ์วาน ซ. งามวงศ์วาน ถ. งามวงศ์วาน บางเขน เมืองนนทบุรี นนทบุรี 11000', 'บางเขน', '2', NULL, 'BMW', 'X3 xDrive20d Highline 2.0', '3กม 5644', 'กรุงเทพมหานคร', '535,776.95', 'pending', 14, '2025-08-04 23:15:43', NULL, NULL, 'normal', NULL, 0, 0, 'ดำ'),
(18364, '164000983', '1749800298977', NULL, 35, '33', 'ชนันรัตน์ ประทุม', '109/87 ชั้นที่ 4 ห้อง 109/87 ไอคอนโดงามวงศ์วาน ซ. งามวงศ์วาน ถ. งามวงศ์วาน บางเขน เมืองนนทบุรี นนทบุรี 11000', 'บางเขน', '2', NULL, 'BMW', 'X3 xDrive20d Highline 2.0', '3กม 5644', 'กรุงเทพมหานคร', '535,776.95', 'pending', 3, '2025-08-04 23:17:06', NULL, NULL, 'normal', NULL, 0, 0, 'ดำ'),
(18365, '1640009831', '1749800298977', NULL, 35, '33', 'ชนันรัตน์ ประทุม1', '109/87 ชั้นที่ 4 ห้อง 109/87 ไอคอนโดงามวงศ์วาน ซ. งามวงศ์วาน ถ. งามวงศ์วาน บางเขน เมืองนนทบุรี นนทบุรี 11000', 'บางเขน', '2', NULL, 'BMW', 'X3 xDrive20d Highline 2.0', '3กม 5644', 'กรุงเทพมหานคร', '535,776.95', 'pending', 3, '2025-08-04 23:17:06', NULL, NULL, 'normal', NULL, 0, 0, 'ดำ'),
(18366, '164000983', '1749800298977', 46, 36, '44', 'ชนันรัตน์ ประทุม', '109/87 ชั้นที่ 4 ห้อง 109/87 ไอคอนโดงามวงศ์วาน ซ. งามวงศ์วาน ถ. งามวงศ์วาน บางเขน เมืองนนทบุรี นนทบุรี 11000', 'บางเขน', '2', NULL, 'BMW', 'X3 xDrive20d Highline 2.0', '3กม 5644', 'กรุงเทพมหานคร', '535,776.95', 'pending', 4, '2025-08-04 23:27:38', NULL, NULL, 'normal', NULL, 0, 0, 'ดำ'),
(18367, '1640009831', '1749800298977', 46, 36, '44', 'ชนันรัตน์ ประทุม1', '109/87 ชั้นที่ 4 ห้อง 109/87 ไอคอนโดงามวงศ์วาน ซ. งามวงศ์วาน ถ. งามวงศ์วาน บางเขน เมืองนนทบุรี นนทบุรี 11000', 'บางเขน', '2', NULL, 'BMW', 'X3 xDrive20d Highline 2.0', '3กม 5644', 'กรุงเทพมหานคร', '535,776.95', 'completed', 4, '2025-08-04 23:27:38', NULL, NULL, 'normal', NULL, 0, 1, 'ดำ');

-- --------------------------------------------------------

--
-- Table structure for table `job_edit_logs`
--

CREATE TABLE `job_edit_logs` (
  `id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `edited_by` int(11) NOT NULL,
  `change_summary` text DEFAULT NULL,
  `edited_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_edit_logs`
--

INSERT INTO `job_edit_logs` (`id`, `job_id`, `edited_by`, `change_summary`, `edited_at`) VALUES
(42, 18358, 1, '📝 รายการแก้ไข: ครบกำหนด: 2 → , ผู้รับงาน: - → ee, หมายเหตุ:  → ๆำ', '2025-08-04 17:04:42');

-- --------------------------------------------------------

--
-- Table structure for table `job_logs`
--

CREATE TABLE `job_logs` (
  `id` int(11) NOT NULL,
  `job_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `result` varchar(20) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `gps` varchar(100) DEFAULT NULL,
  `images` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `log_time` datetime DEFAULT current_timestamp(),
  `marker_color` varchar(20) DEFAULT 'blue'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `job_logs`
--

INSERT INTO `job_logs` (`id`, `job_id`, `user_id`, `result`, `note`, `gps`, `images`, `created_at`, `log_time`, `marker_color`) VALUES
(65, 353, 16, 'ไม่พบ', 'ไม่พบลูกค้า', '13.738051,100.515976', '[\"1750059926_image.jpg\"]', '2025-06-16 07:45:26', '2025-06-16 09:44:00', 'blue'),
(66, 344, 16, 'ไม่พบ', 'ไม่พบใคร', '13.764313,100.500870', '[\"1750060022_IMG_5959.jpeg\",\"1750060022_IMG_5945.jpeg\",\"1750060022_IMG_5944.jpeg\",\"1750060022_IMG_5939.jpeg\"]', '2025-06-16 07:47:02', '2025-06-16 09:46:00', 'blue'),
(67, 364, 16, 'ไม่พบ', 'qwe', '13.9216468,100.6554352', '[]', '2025-06-16 08:16:06', '2025-06-16 10:15:00', 'blue'),
(68, 390, 16, 'พบ', 'ไม่พบลูกค้า', '13.9216377,100.6554385', '[\"1750157416_IMG_5881.jpg\",\"1750157416_IMG_5895.jpg\",\"1750157416_IMG_5936.jpg\",\"1750157416_IMG_5988.jpg\"]', '2025-06-17 10:50:16', '2025-06-17 12:49:00', 'blue'),
(69, 478, 31, 'ไม่พบ', 'ไม่พบ', '13.8641408,100.7222784', '[]', '2025-06-24 17:56:13', '2025-06-24 19:55:00', 'blue'),
(70, 477, 31, 'พบ', 'พบนั่งคอมอยู่', '13.848784048155172,100.71357210160164', '[\"1750788288_image.jpg\"]', '2025-06-24 18:04:48', '2025-06-24 20:03:00', 'blue'),
(71, 476, 31, 'ไม่พบ', 'ไม่เจอใครเลย', '13.848784048155172,100.71357210160164', '[\"1750788448_IMG_6094.jpeg\",\"1750788448_IMG_6095.jpeg\",\"1750788448_IMG_6096.jpeg\",\"1750788448_IMG_6093.jpeg\"]', '2025-06-24 18:07:28', '2025-06-24 20:06:00', 'blue'),
(72, 503, 31, 'ไม่พบ', 'ไม่พบ', '13.8739712,100.7222784', '[]', '2025-06-29 16:11:31', '2025-06-29 18:11:00', 'blue'),
(73, 500, 31, 'ไม่พบ', 'ไม่พบใคร', '13.848789155203113,100.71357304514733', '[]', '2025-06-29 16:15:41', '2025-06-29 18:15:00', 'blue'),
(74, 513, 31, 'ไม่พบ', 'ไม่พบลูกค้า ในบริเวร', '13.848790152987423,100.71357264274609', '[\"1751391090_IMG_6180.jpeg\",\"1751391090_IMG_6183.jpeg\",\"1751391090_IMG_6182.jpeg\",\"1751391090_IMG_6181.jpeg\"]', '2025-07-01 17:31:30', '2025-07-01 19:30:00', 'blue'),
(75, 524, 31, 'พบ', 'พบเห็น', '13.854479,100.707755', '[\"1751391315_IMG_6184.jpeg\",\"1751391315_IMG_6187.jpeg\",\"1751391315_IMG_6185.jpeg\",\"1751391315_IMG_6182.jpeg\"]', '2025-07-01 17:35:15', '2025-07-01 19:34:00', 'blue'),
(76, 510, 31, 'ไม่พบ', 'ไม่พบเห็น', '13.872930,100.720755', '[\"1751466330_111111.jpg\",\"1751466330_513710731_1360664385002042_1778738362625790070_n.jpg\"]', '2025-07-02 14:25:30', '2025-07-02 16:24:00', 'blue'),
(77, 528, 2, 'ไม่พบ', 'ไม่พบ', '13.84448,100.7091712', '[\"1752602051_513710731_1360664385002042_1778738362625790070_n.jpg\",\"1752602051_ChatGPT Image Jul 9, 2025, 09_09_36 PM.png\"]', '2025-07-15 17:54:11', '2025-07-15 19:53:00', 'blue'),
(78, 11106, 46, 'ไม่พบ', 'ไม่พบ', '13.8641408,100.7091712', '[\"1753030651_513710731_1360664385002042_1778738362625790070_n.jpg\",\"1753030651_ChatGPT Image Jul 9, 2025, 09_09_36 PM.png\"]', '2025-07-20 16:57:31', '2025-07-20 18:50:00', 'blue'),
(79, 11107, 46, 'ไม่พบ', '123', '13.8641408,100.7091712', '[]', '2025-07-20 17:10:09', '2025-07-20 19:10:00', 'blue'),
(80, 11108, 46, 'ไม่พบ', 'qwe', '13.8641408,100.7091712', '[\"1753031452_513710731_1360664385002042_1778738362625790070_n.jpg\",\"1753031452_ChatGPT Image Jul 9, 2025, 09_09_36 PM.png\",\"1753031452_image.png\",\"1753031452_IMG_6345.png\"]', '2025-07-20 17:10:52', '2025-07-20 19:10:00', 'blue'),
(81, 11957, 46, 'ไม่พบ', 'ไม่พบใคร', '13.848801604478709,100.71356916006809', '[\"1753707173_IMG_6447.jpeg\",\"1753707173_IMG_6446.jpeg\",\"1753707173_IMG_6448.jpeg\",\"1753707173_IMG_6449.jpeg\"]', '2025-07-28 12:52:53', '2025-07-28 14:52:00', 'blue'),
(82, 12047, 46, 'ไม่พบ', 'qwe', '13.8510336,100.6993408', '[\"1753708095_111111.jpg\",\"1753708095_513710731_1360664385002042_1778738362625790070_n.jpg\",\"1753708095_ChatGPT Image Jul 9, 2025, 09_09_36 PM.png\",\"1753708095_image.png\"]', '2025-07-28 13:08:15', '2025-07-28 15:08:00', 'blue'),
(83, 12247, 46, 'ไม่พบ', 'qwe', '13.8674176,100.7321088', '[\"1753983587_BPL.png\"]', '2025-07-31 17:39:47', '2025-07-31 19:39:00', 'blue'),
(84, 18268, 46, 'ไม่พบ', 'qwe', '13.8674176,100.7321088', '[]', '2025-07-31 17:42:00', '2025-07-31 19:41:00', 'blue'),
(85, 18269, 46, 'ไม่พบ', 'ไม่พบ', '13.8674176,100.7321088', '[\"1754221967_513710731_1360664385002042_1778738362625790070_n.jpg\",\"1754221967_ChatGPT Image Jul 9, 2025, 09_09_36 PM.png\",\"1754221967_image.png\",\"1754221967_IMG_6345.png\"]', '2025-08-03 11:52:47', '2025-08-03 13:52:00', 'blue'),
(86, 18367, 46, 'ไม่พบ', 'ไม่พบ', '13.8543104,100.7222784', '[\"1754414339_526538689_1291214785933449_2756935227116060061_n.jpg\",\"1754414339_526640586_786571537369125_7724299526385699980_n.jpg\",\"1754414339_527160113_786571534035792_1348565853334815321_n.jpg\"]', '2025-08-05 17:18:59', '2025-08-05 19:18:00', 'blue');

-- --------------------------------------------------------

--
-- Table structure for table `login_logs`
--

CREATE TABLE `login_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `login_time` datetime DEFAULT current_timestamp(),
  `type` enum('login','logout','login_fail') DEFAULT 'login'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_logs`
--

INSERT INTO `login_logs` (`id`, `user_id`, `ip_address`, `user_agent`, `login_time`, `type`) VALUES
(156, 46, '192.168.1.45', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/138.0.7204.156 Mobile/15E148 Safari/604.1', '2025-08-05 00:48:03', 'login'),
(157, 46, '192.168.1.45', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/138.0.7204.156 Mobile/15E148 Safari/604.1', '2025-08-05 00:48:29', 'logout'),
(158, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-05 22:46:32', 'login'),
(159, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-06 00:18:32', 'logout'),
(160, 46, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-06 00:18:34', 'login'),
(161, 46, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-06 00:19:07', 'logout'),
(162, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-06 00:19:18', 'login'),
(163, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-06 00:59:39', 'logout'),
(164, 34, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-06 00:59:41', 'login'),
(165, 34, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-06 00:59:59', 'logout'),
(166, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-06 01:00:49', 'login');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('admin','field','manager') DEFAULT 'field',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `active` tinyint(1) DEFAULT 1,
  `department_id` int(11) DEFAULT NULL,
  `marker_color` varchar(20) DEFAULT NULL,
  `can_delete_jobs` tinyint(1) NOT NULL DEFAULT 0,
  `can_manage_departments` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `username`, `password`, `role`, `created_at`, `active`, `department_id`, `marker_color`, `can_delete_jobs`, `can_manage_departments`) VALUES
(1, 'Admin', 'admin', '$2y$10$eq0UqgZJnULM80BcBESI4uwHAv8tJ34h3S2aQlqfXartk5Lfl67Xa', 'admin', '2025-05-14 18:49:26', 1, 2, NULL, 0, 0),
(33, '11', '11', '$2y$10$lFRQ3/9LizoTz0kXy7FIIOF2ECg0SI4YQ4SL3.mq8d/FAdtYvzvsW', 'manager', '2025-07-16 17:37:19', 1, 1, NULL, 1, 0),
(34, '22', '22', '$2y$10$tAoUe5oriV01yIdJ0OZYcOUN9Uhv.xH3NHpBKmLzBP9YftPbe6sem', 'manager', '2025-07-16 17:37:27', 1, 14, NULL, 0, 0),
(35, '33', '33', '$2y$10$wg3S91N71QPtrmHOMzU.jOCn1popkd9gr/UYl6h34Wy82335GpkVG', 'manager', '2025-07-16 17:37:49', 1, 3, NULL, 1, 0),
(36, '44', '44', '$2y$10$J6KufEhPJdcypQGtvw1es.wQkcLnsw6mXx7g5fr8Y23MzeEEgPqkW', 'manager', '2025-07-16 17:38:02', 1, 4, NULL, 1, 0),
(46, 'ee', 'ee', '$2y$10$EcVm0I2mPcMHxhxqXhR0M.ay7enO4cgbSXV77zzmc8XZ7sS9PJH32', 'field', '2025-07-20 16:44:43', 1, 1, NULL, 0, 0),
(47, 're', 're', '$2y$10$Dn/DKwDxInObWPV4EDUwgehOHYDLap2CMNOiiz.k.lZ/8eukvmEZ2', 'field', '2025-08-05 17:59:23', 1, 2, NULL, 0, 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `department_visibility`
--
ALTER TABLE `department_visibility`
  ADD PRIMARY KEY (`id`),
  ADD KEY `from_department_id` (`from_department_id`),
  ADD KEY `to_department_id` (`to_department_id`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `job_edit_logs`
--
ALTER TABLE `job_edit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `job_id` (`job_id`),
  ADD KEY `edited_by` (`edited_by`);

--
-- Indexes for table `job_logs`
--
ALTER TABLE `job_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `login_logs`
--
ALTER TABLE `login_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `department_id` (`department_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `department_visibility`
--
ALTER TABLE `department_visibility`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=150;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `job_edit_logs`
--
ALTER TABLE `job_edit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `job_logs`
--
ALTER TABLE `job_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

--
-- AUTO_INCREMENT for table `login_logs`
--
ALTER TABLE `login_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=167;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `department_visibility`
--
ALTER TABLE `department_visibility`
  ADD CONSTRAINT `department_visibility_ibfk_1` FOREIGN KEY (`from_department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `department_visibility_ibfk_2` FOREIGN KEY (`to_department_id`) REFERENCES `departments` (`id`);

--
-- Constraints for table `jobs`
--
ALTER TABLE `jobs`
  ADD CONSTRAINT `jobs_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`);

--
-- Constraints for table `job_edit_logs`
--
ALTER TABLE `job_edit_logs`
  ADD CONSTRAINT `job_edit_logs_ibfk_1` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `job_edit_logs_ibfk_2` FOREIGN KEY (`edited_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `login_logs`
--
ALTER TABLE `login_logs`
  ADD CONSTRAINT `login_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
