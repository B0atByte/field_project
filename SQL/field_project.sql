-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 09, 2025 at 06:16 PM
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
(4, 'PTT');

-- --------------------------------------------------------

--
-- Table structure for table `department_visibility`
--

CREATE TABLE `department_visibility` (
  `id` int(11) NOT NULL,
  `from_department_id` int(11) DEFAULT NULL,
  `to_department_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` int(11) NOT NULL,
  `contract_number` varchar(50) NOT NULL,
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
  `color` varchar(50) DEFAULT NULL,
  `plate` varchar(20) DEFAULT NULL,
  `province` varchar(50) DEFAULT NULL,
  `os` varchar(50) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `jobs`
--

INSERT INTO `jobs` (`id`, `contract_number`, `assigned_to`, `imported_by`, `product`, `location_info`, `location_area`, `zone`, `due_date`, `overdue_period`, `model`, `model_detail`, `color`, `plate`, `province`, `os`, `status`, `department_id`) VALUES
(224, '364015523', 2, 1, 'KKP 4', 'ทัชากร บางกอกน้อย', 'ต.บางกอกย อ.บางกอกย จ.สมุทรสงคราม', 'บางกอกย', '10', '5', 'MG ', 'ZS 1.5 D', 'ขาว', 'กค 5333', 'สมุทรสงคราม', '427388', 'completed', 2),
(225, '000364015515', 2, 1, 'KKP 4', 'ทัชา กอกน้อย', 'ต.บางกอกน้อย อ.บางกอกน้อย จ.กรุงเทพมหานคร', 'บางกอกน้อย', '10', '10', 'TOYOTA ', 'HILUX REVO 2.4 D-CAB E PRE A/T', 'แดง ดำ', '2ฬศ 444', 'กรุงเทพมหานคร', '677717.26', 'completed', 2),
(226, '000366001283', 2, 1, 'KKP 3', 'โส กอกน้อย', 'ต.จอมทอง อ.จอมทอง จ.กรุงเทพมหานคร', 'จอมทอง', '10', '5', 'MITSUBISHI ', 'MIRAGE', 'เทา', 'ธธ 7111', 'กรุงเทพมหานคร', '123132', 'completed', 2),
(227, '232331283', 2, 1, 'KKP 3', 'โสดา บางกอก', 'ต.ลาดกระบัง อ.ลาดกระบัง จ.พิษณุโลก', 'ลาดกระบัง', '10', '5', 'HONDA CITY', 'CITY V+ CVT A/T', 'เทา', 'ขม 2222', 'พิษณุโลก', '5555', 'completed', 2),
(228, '000366009543', 5, 1, 'KKP SME CAR3X', 'คัลเลอร์ บางกอกน้อย', 'ต.บางบ่อ อ.บางบ่อ จ.กรุงเทพมหานคร', 'บางบ่อ', '10', '5', 'MAZDA 2', '2 Sedan 1.3 C', 'แดง ดำ', '9คม 1111', 'กรุงเทพมหานคร', '1414', 'completed', 2),
(229, '004566002625', NULL, 1, 'KKP SME Freedo', 'อัครินดี บางกอกน้อย', 'ต.บางพลี อ.บางพลี จ.กรุงเทพมหานคร', 'บางพลี', '1', '15', 'MG3 HATCHBACK', 'MG3 HATCHBACK V Sunroof (Two Tone)', 'เทา', '2ผธ 53213', 'กรุงเทพมหานคร', '9999', NULL, 2),
(230, '000967001116', NULL, 1, 'KKP SME Freedo', 'ทัศย์ บางกอกน้อย', 'ต.เมืองนนทบุรี อ.เมืองนนทบุรี จ.กรุงเทพมหานคร', 'เมืองนนทบุรี', '1', '10', 'ISUZU Spark', 'Spark 1.9 Ddi S', 'เทา', '2ฬพ 123', 'กรุงเทพมหานคร', '4534', NULL, 2),
(231, '000366009187', NULL, 1, 'KKP SME CAR3X', 'ศักสิทธิ์ บางกอกน้อย', 'ต.พระประแดง อ.พระประแดง จ.กรุงเทพมหานคร', 'พระประแดง', NULL, '15', 'HONDA JAZZ', 'JAZZ S CVT A/T', 'ขาว', '3ฌผ 132', 'กรุงเทพมหานคร', '4534', NULL, 2),
(232, '000966011163', 2, 1, 'KKP SME Freedo', 'บุบางกอกน้อย', 'ต.คลองเตย อ.คลองเตย จ.กรุงเทพมหานคร', 'คลองเตย', NULL, '10', 'Toyota HILVIG', 'HILVIG Prerunner', 'เทา', '3ฌผ 11', 'กรุงเทพมหานคร', '1256', 'completed', 2),
(233, '004466008222', NULL, 1, 'Home Loan', 'ภัทร บางกอกน้อย', 'ต.จอมทอง อ.จอมทอง จ.กรุงเทพมหานคร', 'จอมทอง', NULL, '1', 'Toyota Veloz', 'Veloz', 'เทา', '3ฌผ 12', 'กรุงเทพมหานคร', '5555', NULL, 2),
(234, '000364015515', 2, 1, 'KKP 4', 'ทัชากร บางกอกน้อย', 'ต.บางกอกย อ.บางกอกย จ.สมุทรสงคราม', 'บางกอกย', '10', '5', 'MG ', 'ZS 1.5 D', 'ขาว', 'กค 5333', 'สมุทรสงคราม', '427388', 'completed', 2),
(235, '000364015515', 5, 1, 'KKP 4', 'ทัชา กอกน้อย', 'ต.บางกอกน้อย อ.บางกอกน้อย จ.กรุงเทพมหานคร', 'บางกอกน้อย', '10', '10', 'TOYOTA ', 'HILUX REVO 2.4 D-CAB E PRE A/T', 'แดง ดำ', '2ฬศ 444', 'กรุงเทพมหานคร', '677717.26', 'completed', 2),
(236, '000366001283', 2, 1, 'KKP 3', 'โส กอกน้อย', 'ต.จอมทอง อ.จอมทอง จ.กรุงเทพมหานคร', 'จอมทอง', '10', '5', 'MITSUBISHI ', 'MIRAGE', 'เทา', 'ธธ 7111', 'กรุงเทพมหานคร', '123132', 'pending', 2),
(237, '000366001283', 5, 1, 'KKP 3', 'โสดา บางกอก', 'ต.ลาดกระบัง อ.ลาดกระบัง จ.พิษณุโลก', 'ลาดกระบัง', '10', '5', 'HONDA CITY', 'CITY V+ CVT A/T', 'เทา', 'ขม 2222', 'พิษณุโลก', '5555', 'completed', 2),
(238, '000366009543', 12, 1, 'KKP SME CAR3X', 'คัลเลอร์ บางกอกน้อย', 'ต.บางบ่อ อ.บางบ่อ จ.กรุงเทพมหานคร', 'บางบ่อ', '10', '5', 'MAZDA 2', '2 Sedan 1.3 C', 'แดง ดำ', '9คม 1111', 'กรุงเทพมหานคร', '1414', 'completed', 2),
(239, '004566002625', NULL, 1, 'KKP SME Freedo', 'อัครินดี บางกอกน้อย', 'ต.บางพลี อ.บางพลี จ.กรุงเทพมหานคร', 'บางพลี', '1', '15', 'MG3 HATCHBACK', 'MG3 HATCHBACK V Sunroof (Two Tone)', 'เทา', '2ผธ 53213', 'กรุงเทพมหานคร', '9999', NULL, 2),
(240, '000967001116', NULL, 1, 'KKP SME Freedo', 'ทัศย์ บางกอกน้อย', 'ต.เมืองนนทบุรี อ.เมืองนนทบุรี จ.กรุงเทพมหานคร', 'เมืองนนทบุรี', '1', '10', 'ISUZU Spark', 'Spark 1.9 Ddi S', 'เทา', '2ฬพ 123', 'กรุงเทพมหานคร', '4534', NULL, 2),
(241, '000366009187', NULL, 1, 'KKP SME CAR3X', 'ศักสิทธิ์ บางกอกน้อย', 'ต.พระประแดง อ.พระประแดง จ.กรุงเทพมหานคร', 'พระประแดง', NULL, '15', 'HONDA JAZZ', 'JAZZ S CVT A/T', 'ขาว', '3ฌผ 132', 'กรุงเทพมหานคร', '4534', NULL, 2),
(242, '000966011163', 2, 1, 'KKP SME Freedo', 'บุบางกอกน้อย', 'ต.คลองเตย อ.คลองเตย จ.กรุงเทพมหานคร', 'คลองเตย', NULL, '10', 'Toyota HILVIG', 'HILVIG Prerunner', 'เทา', '3ฌผ 11', 'กรุงเทพมหานคร', '1256', 'pending', 2),
(243, '004466008222', NULL, 1, 'Home Loan', 'ภัทร บางกอกน้อย', 'ต.จอมทอง อ.จอมทอง จ.กรุงเทพมหานคร', 'จอมทอง', NULL, '1', 'Toyota Veloz', 'Veloz', 'เทา', '3ฌผ 12', 'กรุงเทพมหานคร', '5555', NULL, 2),
(244, '364015523', 12, 1, 'KKP 4', 'ทัชากร บางกอกน้อย', 'ต.บางกอกย อ.บางกอกย จ.สมุทรสงคราม', 'บางกอกย', '10', '5', 'MG ', 'ZS 1.5 D', 'ขาว', 'กค 5333', 'สมุทรสงคราม', '427388', 'pending', 2),
(245, '000364015515', 12, 1, 'KKP 4', 'ทัชา กอกน้อย', 'ต.บางกอกน้อย อ.บางกอกน้อย จ.กรุงเทพมหานคร', 'บางกอกน้อย', '10', '10', 'TOYOTA ', 'HILUX REVO 2.4 D-CAB E PRE A/T', 'แดง ดำ', '2ฬศ 444', 'กรุงเทพมหานคร', '677717.26', 'completed', 2),
(246, '000366001283', 2, 1, 'KKP 3', 'โส กอกน้อย', 'ต.จอมทอง อ.จอมทอง จ.กรุงเทพมหานคร', 'จอมทอง', '10', '5', 'MITSUBISHI ', 'MIRAGE', 'เทา', 'ธธ 7111', 'กรุงเทพมหานคร', '123132', 'pending', 2),
(247, '232331283', NULL, 1, 'KKP 3', 'โสดา บางกอก', 'ต.ลาดกระบัง อ.ลาดกระบัง จ.พิษณุโลก', 'ลาดกระบัง', '10', '5', 'HONDA CITY', 'CITY V+ CVT A/T', 'เทา', 'ขม 2222', 'พิษณุโลก', '5555', NULL, 2),
(248, '000366009543', NULL, 1, 'KKP SME CAR3X', 'คัลเลอร์ บางกอกน้อย', 'ต.บางบ่อ อ.บางบ่อ จ.กรุงเทพมหานคร', 'บางบ่อ', '10', '5', 'MAZDA 2', '2 Sedan 1.3 C', 'แดง ดำ', '9คม 1111', 'กรุงเทพมหานคร', '1414', NULL, 2),
(249, '004566002625', NULL, 1, 'KKP SME Freedo', 'อัครินดี บางกอกน้อย', 'ต.บางพลี อ.บางพลี จ.กรุงเทพมหานคร', 'บางพลี', '1', '15', 'MG3 HATCHBACK', 'MG3 HATCHBACK V Sunroof (Two Tone)', 'เทา', '2ผธ 53213', 'กรุงเทพมหานคร', '9999', NULL, 2),
(250, '000967001116', NULL, 1, 'KKP SME Freedo', 'ทัศย์ บางกอกน้อย', 'ต.เมืองนนทบุรี อ.เมืองนนทบุรี จ.กรุงเทพมหานคร', 'เมืองนนทบุรี', '1', '10', 'ISUZU Spark', 'Spark 1.9 Ddi S', 'เทา', '2ฬพ 123', 'กรุงเทพมหานคร', '4534', NULL, 2),
(251, '000366009187', NULL, 1, 'KKP SME CAR3X', 'ศักสิทธิ์ บางกอกน้อย', 'ต.พระประแดง อ.พระประแดง จ.กรุงเทพมหานคร', 'พระประแดง', NULL, '15', 'HONDA JAZZ', 'JAZZ S CVT A/T', 'ขาว', '3ฌผ 132', 'กรุงเทพมหานคร', '4534', NULL, 2),
(252, '000966011163', NULL, 1, 'KKP SME Freedo', 'บุบางกอกน้อย', 'ต.คลองเตย อ.คลองเตย จ.กรุงเทพมหานคร', 'คลองเตย', NULL, '10', 'Toyota HILVIG', 'HILVIG Prerunner', 'เทา', '3ฌผ 11', 'กรุงเทพมหานคร', '1256', NULL, 2),
(253, '004466008222', NULL, 1, 'Home Loan', 'ภัทร บางกอกน้อย', 'ต.จอมทอง อ.จอมทอง จ.กรุงเทพมหานคร', 'จอมทอง', NULL, '1', 'Toyota Veloz', 'Veloz', 'เทา', '3ฌผ 12', 'กรุงเทพมหานคร', '5555', NULL, 2),
(254, '364015523', 2, 1, 'KKP 4', 'ทัชากร บางกอกน้อย', 'ต.บางกอกย อ.บางกอกย จ.สมุทรสงคราม', 'บางกอกย', '10', '5', 'MG ', 'ZS 1.5 D', 'ขาว', 'กค 5333', 'สมุทรสงคราม', '427388', 'pending', 2),
(255, '000364015515', 12, 1, 'KKP 4', 'ทัชา กอกน้อย', 'ต.บางกอกน้อย อ.บางกอกน้อย จ.กรุงเทพมหานคร', 'บางกอกน้อย', '10', '10', 'TOYOTA ', 'HILUX REVO 2.4 D-CAB E PRE A/T', 'แดง ดำ', '2ฬศ 444', 'กรุงเทพมหานคร', '677717.26', 'completed', 2),
(256, '000366001283', 12, 1, 'KKP 3', 'โส กอกน้อย', 'ต.จอมทอง อ.จอมทอง จ.กรุงเทพมหานคร', 'จอมทอง', '10', '5', 'MITSUBISHI ', 'MIRAGE', 'เทา', 'ธธ 7111', 'กรุงเทพมหานคร', '123132', 'completed', 2),
(257, '232331283', NULL, 1, 'KKP 3', 'โสดา บางกอก', 'ต.ลาดกระบัง อ.ลาดกระบัง จ.พิษณุโลก', 'ลาดกระบัง', '10', '5', 'HONDA CITY', 'CITY V+ CVT A/T', 'เทา', 'ขม 2222', 'พิษณุโลก', '5555', NULL, 2),
(258, '000366009543', 2, 1, 'KKP SME CAR3X', 'คัลเลอร์ บางกอกน้อย', 'ต.บางบ่อ อ.บางบ่อ จ.กรุงเทพมหานคร', 'บางบ่อ', '10', '5', 'MAZDA 2', '2 Sedan 1.3 C', 'แดง ดำ', '9คม 1111', 'กรุงเทพมหานคร', '1414', 'completed', 2),
(259, '004566002625', NULL, 1, 'KKP SME Freedo', 'อัครินดี บางกอกน้อย', 'ต.บางพลี อ.บางพลี จ.กรุงเทพมหานคร', 'บางพลี', '1', '15', 'MG3 HATCHBACK', 'MG3 HATCHBACK V Sunroof (Two Tone)', 'เทา', '2ผธ 53213', 'กรุงเทพมหานคร', '9999', NULL, 2),
(260, '000967001116', NULL, 1, 'KKP SME Freedo', 'ทัศย์ บางกอกน้อย', 'ต.เมืองนนทบุรี อ.เมืองนนทบุรี จ.กรุงเทพมหานคร', 'เมืองนนทบุรี', '1', '10', 'ISUZU Spark', 'Spark 1.9 Ddi S', 'เทา', '2ฬพ 123', 'กรุงเทพมหานคร', '4534', NULL, 2),
(261, '000366009187', NULL, 1, 'KKP SME CAR3X', 'ศักสิทธิ์ บางกอกน้อย', 'ต.พระประแดง อ.พระประแดง จ.กรุงเทพมหานคร', 'พระประแดง', NULL, '15', 'HONDA JAZZ', 'JAZZ S CVT A/T', 'ขาว', '3ฌผ 132', 'กรุงเทพมหานคร', '4534', NULL, 2),
(262, '000966011163', NULL, 1, 'KKP SME Freedo', 'บุบางกอกน้อย', 'ต.คลองเตย อ.คลองเตย จ.กรุงเทพมหานคร', 'คลองเตย', NULL, '10', 'Toyota HILVIG', 'HILVIG Prerunner', 'เทา', '3ฌผ 11', 'กรุงเทพมหานคร', '1256', NULL, 2),
(263, '004466008222', NULL, 1, 'Home Loan', 'ภัทร บางกอกน้อย', 'ต.จอมทอง อ.จอมทอง จ.กรุงเทพมหานคร', 'จอมทอง', NULL, '1', 'Toyota Veloz', 'Veloz', 'เทา', '3ฌผ 12', 'กรุงเทพมหานคร', '5555', NULL, 2);

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
(35, 194, 2, 'พบ', 'พบลูกค้า', '13.8674176,100.7157248', '[\"1748368811_Screenshot 2025-05-28 003355.png\"]', '2025-05-27 18:00:11', '2025-06-05 01:48:21', 'blue'),
(36, 196, 2, 'พบ', 'ๆไำ', '13.8641408,100.7222784', '[]', '2025-06-04 18:48:48', '2025-06-04 20:48:00', 'blue'),
(37, 211, 2, 'ไม่พบ', 'ๆไำ', '13.927403,100.163384', '[]', '2025-06-04 18:49:34', '2025-06-04 20:49:00', 'blue'),
(38, 195, 2, 'ไม่พบ', 'ๆไำำไๆำ', '15.744675,97.688713', '[]', '2025-06-04 18:50:02', '2025-06-04 20:49:00', 'blue'),
(39, 204, 2, 'ไม่พบ', 'ๆไำๆไำๆไำๆไำๆำ', '10.082445,98.743401', '[]', '2025-06-04 18:55:54', '2025-06-04 20:55:00', 'blue'),
(40, 205, 2, 'ไม่พบ', 'ไม่พบลูกค้า', '21.381500,105.899119', '[]', '2025-06-04 19:08:19', '2025-06-04 21:07:00', 'blue'),
(41, 214, 5, 'ไม่พบ', 'QQ', '13.8674176,100.7157248', '[\"1749315831_Screenshot 2025-05-20 003518.png\",\"1749315831_Screenshot 2025-05-20 005435.png\",\"1749315831_Screenshot 2025-05-21 225206.png\",\"1749315831_Screenshot 2025-05-21 225735.png\"]', '2025-06-07 17:03:51', '2025-06-07 19:03:00', 'blue'),
(42, 227, 2, 'พบ', 'เจอลูกค้า', '13.8674176,100.7157248', '[\"1749389291_imag3.png\",\"1749389291_image1.png\",\"1749389291_image2.png\",\"1749389291_image4.png\"]', '2025-06-08 13:28:11', '2025-06-08 15:27:00', 'blue'),
(43, 224, 2, 'ไม่พบ', 'ไม่พบใครทิ้งเบอร์ตืดต่อไว้เเล้ว', '13.736550,100.468426', '[\"1749402346_Screenshot 2024-07-11 233551 - Copy.png\",\"1749402346_Screenshot 2024-07-11 233551.png\",\"1749402346_Screenshot 2024-07-13 175907 - Copy.png\"]', '2025-06-08 17:05:46', '2025-06-08 19:05:00', 'blue'),
(44, 225, 2, 'ไม่พบ', '๐\"ฎ', '13.8674176,100.7157248', '[]', '2025-06-08 17:09:59', '2025-06-08 19:09:00', 'blue'),
(45, 226, 2, 'ไม่พบ', 'ไม่เจอ', '14.053995,99.773369', '[\"1749478287_Screenshot 2024-07-11 233551.png\",\"1749478287_Screenshot 2024-07-13 175907 - Copy.png\",\"1749478287_Screenshot 2024-07-13 175947 - Copy.png\",\"1749478287_Screenshot 2024-07-13 175947.png\"]', '2025-06-09 14:11:27', '2025-06-09 16:11:00', 'blue'),
(46, 232, 2, 'ไม่พบ', 'wwww', '15.101299,105.066311', '[\"1749482427_Screenshot 2025-05-14 035720.png\",\"1749482427_Screenshot 2025-05-14 035823.png\",\"1749482427_Screenshot 2025-05-14 042629.png\"]', '2025-06-09 15:20:27', '2025-06-09 17:19:00', 'blue'),
(47, 235, 5, 'ไม่พบ', 'qqq', '13.8674176,100.7157248', '[]', '2025-06-09 16:00:45', '2025-06-09 18:00:00', 'blue'),
(48, 237, 5, 'ไม่พบ', 'eeeee', '13.955392,98.485222', '[]', '2025-06-09 16:00:59', '2025-06-09 18:00:00', 'blue'),
(49, 245, 12, 'พบ', 'eewww', '13.662001,100.233422', '[]', '2025-06-09 16:04:38', '2025-06-09 18:04:00', 'blue'),
(50, 255, 12, 'ไม่พบ', 'eweqweqwqfe', '13.411662,99.938164', '[]', '2025-06-09 16:04:52', '2025-06-09 18:04:00', 'blue'),
(51, 234, 2, 'ไม่พบ', 'eqwe', '13.918072,99.693718', '[]', '2025-06-09 16:05:43', '2025-06-09 18:05:00', 'blue'),
(52, 256, 12, 'ไม่พบ', 'eeee', '14.981933,98.369866', '[]', '2025-06-09 16:07:21', '2025-06-09 18:07:00', 'blue'),
(53, 258, 2, 'ไม่พบ', 'eeee', '7.659824,99.745572', '[\"1749485330_299998221_1213247782849790_4173287940377394120_n.jpg\"]', '2025-06-09 16:08:50', '2025-06-09 18:08:00', 'blue'),
(54, 228, 5, 'ไม่พบ', 'qweqeq', '4.614753,101.753655', '[\"1749485376_image2.png\",\"1749485376_image4.png\"]', '2025-06-09 16:09:36', '2025-06-09 18:09:00', 'blue'),
(55, 238, 12, 'พบ', 'พบกำลังเจรจา ติดต่อ 123213244', '11.231394,99.236602', '[\"1749485444_imag3.png\",\"1749485444_image1.png\"]', '2025-06-09 16:10:44', '2025-06-09 18:10:00', 'blue');

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
  `marker_color` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `username`, `password`, `role`, `created_at`, `active`, `department_id`, `marker_color`) VALUES
(1, 'Admin', 'admin', '$2y$10$2bFjZ3g2Lf3olS5z/PoU5eLqB8D0pgqKxZbdJch5VjVwOLa7guV.W', 'admin', '2025-05-14 18:49:26', 1, 2, NULL),
(2, 'Field Officer', 'field', '$2y$10$2bFjZ3g2Lf3olS5z/PoU5eLqB8D0pgqKxZbdJch5VjVwOLa7guV.W', 'field', '2025-05-14 18:49:26', 1, NULL, NULL),
(5, '1', '1', '$2y$10$.7doAjtq/TL9TSLICOQtI.QCM0tNb4UAnE/aW/2A9rzB4eIurdN1u', 'field', '2025-05-19 17:30:05', 1, 2, NULL),
(12, '2', '2', '$2y$10$hB/JRxs5ToPiuzaDWn7J8.oCEPT3.0kbQ45EXJTGcQbu.PaQTx39a', 'field', '2025-06-08 13:32:09', 1, 1, NULL);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `department_visibility`
--
ALTER TABLE `department_visibility`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=264;

--
-- AUTO_INCREMENT for table `job_edit_logs`
--
ALTER TABLE `job_edit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `job_logs`
--
ALTER TABLE `job_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

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
  ADD CONSTRAINT `job_edit_logs_ibfk_1` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`),
  ADD CONSTRAINT `job_edit_logs_ibfk_2` FOREIGN KEY (`edited_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
