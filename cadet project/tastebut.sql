-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 08, 2025 at 08:08 PM
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
-- Database: `tastebut`
--

-- --------------------------------------------------------

--
-- Table structure for table `approved_statements`
--

CREATE TABLE `approved_statements` (
  `id` int(11) NOT NULL,
  `number` int(11) NOT NULL,
  `statementdescription` varchar(255) NOT NULL,
  `statementfile` varchar(255) NOT NULL,
  `cadetid` int(11) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `rank` int(11) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `observers_name` varchar(100) DEFAULT NULL,
  `critical_requirement` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`id`, `user_id`, `action`, `description`, `ip_address`, `created_at`) VALUES
(1, 1, 'login', 'User logged into the system', '::1', '2025-09-23 12:50:09'),
(2, 1, 'login', 'User logged into the system', '::1', '2025-09-23 13:07:11'),
(3, 1, 'login', 'User logged into the system', '::1', '2025-09-23 13:40:43'),
(4, 1, 'login', 'User logged into the system', '::1', '2025-09-23 17:54:02'),
(5, 1, 'login', 'User logged into the system', '::1', '2025-09-23 17:54:05'),
(6, 3, 'login', 'User logged into the system', '::1', '2025-09-23 18:45:26'),
(7, 2, 'login', 'User logged into the system', '::1', '2025-09-23 19:05:44'),
(8, 3, 'login', 'User logged into the system', '::1', '2025-09-23 19:43:51'),
(9, 1, 'login', 'User logged into the system', '::1', '2025-09-23 19:47:37'),
(10, 2, 'login', 'User logged into the system', '::1', '2025-09-24 07:47:14'),
(11, 1, 'login', 'User logged into the system', '::1', '2025-09-24 13:39:04'),
(12, 2, 'login', 'User logged into the system', '::1', '2025-09-24 19:35:02'),
(13, 1, 'login', 'User logged into the system', '::1', '2025-09-25 14:03:03'),
(14, 1, 'add_material', 'Added new material: telephone (MAT-000001)', '::1', '2025-09-25 14:17:02'),
(15, 1, 'assign_material', 'Assigned material to cadet', '::1', '2025-09-25 14:18:02'),
(16, 3, 'login', 'User logged into the system', '::1', '2025-09-25 14:31:01'),
(17, 2, 'login', 'User logged into the system', '::1', '2025-09-25 15:07:14'),
(18, 2, 'login', 'User logged into the system', '::1', '2025-09-25 15:24:07'),
(19, 2, 'login', 'User logged into the system', '::1', '2025-09-25 15:25:09'),
(20, 2, 'login', 'User logged into the system', '::1', '2025-09-25 18:18:45'),
(21, 2, 'register_material', 'Registered new material: pc (MAT-000003)', '::1', '2025-09-25 18:51:57'),
(22, 2, 'login', 'User logged into the system', '::1', '2025-09-26 07:18:04'),
(23, 2, 'register_material', 'Registered new material: xxxzx (MAT-000004)', '::1', '2025-09-26 13:05:14'),
(24, 2, 'login', 'User logged into the system', '::1', '2025-09-26 13:07:20'),
(25, 1, 'login', 'User logged into the system', '::1', '2025-09-26 13:10:47'),
(26, 3, 'login', 'User logged into the system', '::1', '2025-09-26 13:11:47'),
(27, 2, 'login', 'User logged into the system', '::1', '2025-09-26 13:15:06'),
(28, 2, 'register_material', 'Registered new material: shoes (MAT-000005)', '::1', '2025-09-26 13:32:50'),
(29, 2, 'register_material', 'Registered new material: RAM (MAT-000006)', '::1', '2025-09-27 05:59:28'),
(30, 1, 'login', 'User logged into the system', '::1', '2025-09-27 07:17:59'),
(31, 3, 'login', 'User logged into the system', '::1', '2025-09-27 07:18:35'),
(32, 1, 'login', 'User logged into the system', '::1', '2025-09-27 07:20:03'),
(33, 3, 'login', 'User logged into the system', '::1', '2025-09-27 07:56:54'),
(34, 1, 'login', 'User logged into the system', '::1', '2025-09-27 09:16:49'),
(35, 3, 'login', 'User logged into the system', '::1', '2025-09-27 09:43:39'),
(36, 1, 'login', 'User logged into the system', '::1', '2025-09-27 16:44:50'),
(37, 3, 'login', 'User logged into the system', '::1', '2025-09-27 17:23:57'),
(38, 3, 'login', 'User logged into the system', '::1', '2025-09-27 17:59:57'),
(39, 1, 'login', 'User logged into the system', '::1', '2025-09-27 18:29:48'),
(40, 3, 'login', 'User logged into the system', '::1', '2025-09-27 18:30:43'),
(41, 1, 'login', 'Cadet logged into the system', '::1', '2025-09-27 19:36:31'),
(42, 1, 'login', 'Cadet logged into the system', '::1', '2025-09-27 19:37:06'),
(43, 2, 'register_material', 'Registered new material: xxxzx (MAT-000007)', '::1', '2025-09-29 12:55:52'),
(44, 2, 'register_material', 'Registered new material: hard disk (MAT-000008)', '::1', '2025-09-29 12:57:02'),
(45, 2, 'register_material', 'Registered new material: hard disk (MAT-000009)', '::1', '2025-09-29 13:01:51'),
(46, 2, 'register_material', 'Registered new material: cable (MAT-000011)', '::1', '2025-10-06 18:42:40'),
(47, 2, 'register_material', 'Registered new material: screen (MAT-000012)', '::1', '2025-10-06 18:43:33'),
(48, 2, 'register_material', 'Registered new material: keeyboard (MAT-000013)', '::1', '2025-10-06 18:45:03'),
(49, 2, 'register_material', 'Registered new material: mouse (MAT-000014)', '::1', '2025-10-06 18:46:03'),
(50, 2, 'register_material', 'Registered new material: note books  (MAT-000015)', '::1', '2025-10-06 18:47:24'),
(51, 2, 'mark_taken', 'Marked material as taken from institution: ID 15', '::1', '2025-10-07 13:10:46'),
(52, 2, 'mark_taken', 'Marked material as taken from institution: ID 15', '::1', '2025-10-07 13:11:13'),
(53, 2, 'mark_taken', 'Marked material as taken outside: ID 15', '::1', '2025-10-07 13:34:17'),
(54, 2, 'mark_taken', 'Marked material as taken outside: ID 15', '::1', '2025-10-07 13:34:44'),
(55, 2, 'mark_taken', 'Marked material as taken outside: ID 8', '::1', '2025-10-07 13:35:02'),
(56, 2, 'send_outside', 'Sent material outside institution: ID 8', '::1', '2025-10-07 14:02:28'),
(57, 2, 'mark_taken', 'Marked material as taken outside: ID 23', '::1', '2025-10-07 14:25:21'),
(58, 2, 'mark_taken', 'Marked material as taken outside: ID 22', '::1', '2025-10-07 14:37:00'),
(59, 2, 'mark_taken', 'Marked material as taken outside: ID 8', '::1', '2025-10-07 18:37:18'),
(60, 2, 'mark_taken', 'Marked material as taken outside: ID 25', '::1', '2025-10-08 08:34:37'),
(61, 2, 'mark_taken', 'Marked material as taken outside: ID 35', '::1', '2025-10-08 12:48:54'),
(62, 2, 'register_material', 'Registered new material: t-shirt (MAT-000024)', '::1', '2025-10-08 13:13:47'),
(63, 2, 'register_material', 'Registered new material: mouses (MAT-000025)', '::1', '2025-10-08 13:20:04'),
(64, 2, 'register_material', 'Registered new material: pantalo (MAT-000026)', '::1', '2025-10-08 15:14:02');

-- --------------------------------------------------------

--
-- Table structure for table `cadet`
--

CREATE TABLE `cadet` (
  `fname` varchar(255) DEFAULT NULL,
  `lname` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `joindate` date DEFAULT NULL,
  `rollno` int(11) DEFAULT NULL,
  `company` varchar(255) DEFAULT NULL,
  `platoon` varchar(255) DEFAULT NULL,
  `gender` varchar(255) DEFAULT NULL,
  `number` varchar(255) DEFAULT NULL,
  `parentsname` varchar(255) DEFAULT NULL,
  `parentsno` varchar(255) DEFAULT NULL,
  `dateofbirth` date DEFAULT NULL,
  `bloodgrp` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `cadetid` int(11) NOT NULL,
  `intake_name` varchar(255) DEFAULT NULL,
  `stubackground` varchar(255) DEFAULT NULL,
  `rank` varchar(50) DEFAULT NULL,
  `country` varchar(50) DEFAULT NULL,
  `course_title` varchar(100) DEFAULT NULL,
  `course_id_no` int(11) DEFAULT NULL,
  `assessment_date` date DEFAULT NULL,
  `academicp` varchar(255) DEFAULT NULL,
  `physicalp` varchar(255) DEFAULT NULL,
  `pnotes` text DEFAULT NULL,
  `cnotes` text DEFAULT NULL,
  `onotes` text DEFAULT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cadet`
--

INSERT INTO `cadet` (`fname`, `lname`, `email`, `joindate`, `rollno`, `company`, `platoon`, `gender`, `number`, `parentsname`, `parentsno`, `dateofbirth`, `bloodgrp`, `address`, `profile_image`, `cadetid`, `intake_name`, `stubackground`, `rank`, `country`, `course_title`, `course_id_no`, `assessment_date`, `academicp`, `physicalp`, `pnotes`, `cnotes`, `onotes`, `password`) VALUES
('Byiringiro', 'Dieudonne', 'dieudonnebyiringiro2020@gmail.com', '0000-00-00', 223026513, 'A- Company', 'P-2', 'Male', '0786296962', 'Mukasamari angel aline', '0790039602', '0000-00-00', 'O+', NULL, 'profile.PNG', 1, '08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '1234'),
('Niyonzima', 'Elyse', 'dieudonnebyiringiro2020@gmail.com', '0000-00-00', 22323, 'A- Company', 'P-3', 'Male', '0788602555', 'Uwase Alice', '0786296962', '0000-00-00', 'A+', NULL, 'images.png', 2, '08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '12345'),
('Mugisha ', 'Frank', 'gatesiemmy08@gmail.com', '0000-00-00', 147785, 'C- Company', 'P-2', 'Gender', '0788888888', 'Mukasamari angel aline', '0790039458', '0000-00-00', 'O+', NULL, 'images.png', 3, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '1234'),
('BYIRINGIRO', 'Dieudonne', 'dieudonne@gmail.com', '0000-00-00', 8, 'A- Company', 'P-1', 'Male', '5078885858', 'Angel', '0790039602', '0000-00-00', '0+', 'Bugesera', 'images.png', 4, '08', 'Make sure to adjust the data types (e.g., VARCHAR, INT, DATE) according to your specific requirements.', 'Officer Cadet', 'Rwanda', 'Long', 123432, NULL, 'Test 1', 'Test 2', 'Test 3', 'Test 4', 'Test 5', ''),
('Umwalli', 'Ange', 'niyonzima@gmail.com', '0000-00-00', 4587, 'B- Company', 'P-3', 'Gender', '50788847454', 'Uwase Alice', '0790039411', '0000-00-00', 'A+', NULL, 'images.png', 9, '08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, ''),
('Habiyambere', 'Kercy', 'niyonzima@gmail.com', '0000-00-00', 478523, 'A- Company', 'P-3', 'Female', '078884852', 'Uwase Alice', '0790039411', '0000-00-00', 'A+', NULL, 'images.png', 11, '08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, ''),
('Byiringiro', 'Olivier', 'byiringirodie@gmail.com', '0000-00-00', 0, 'B- Company', 'P-3', 'Male', '5078885858', 'Mukasamari angel aline', '0786296962', '0000-00-00', 'A+', NULL, 'images.png', 12, '08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, ''),
('Murenzi', 'Clement', 'byiringirodie@gmail.com', '0000-00-00', 487896, 'C- Company', 'P-2', 'Male', '5078885858', 'Mukasamari angel aline', '0786296962', '0000-00-00', 'A+', NULL, 'images.png', 13, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, ''),
('Nzabamwita', 'Clement', 'boris@gmail.com', '0000-00-00', 487896, 'C- Company', 'P-2', 'Male', '0789022483', 'Mukasamari angel aline', '0786296962', '0000-00-00', 'A+', NULL, 'images.png', 14, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '1111'),
('Muhorakeye ', 'Anne', 'niyonzima@gmail.com', '0000-00-00', 0, 'B- Company', 'P-2', 'Female', '7777777777', 'Angel', '0790039411', '0000-00-00', 'A+', NULL, 'rdf.jpeg', 18, '08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '1111'),
('Muhorakeye ', 'Anet', 'anet12@gamil.com', '0000-00-00', 0, 'A- Company', 'P-1', 'Male', '07886269622', 'Mukamusonera Angelique', '07386269622', '0000-00-00', 'O+', NULL, 'rdf.jpeg', 19, '08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, ''),
('Kamanzi ', 'David', '', '0000-00-00', 0, 'A- Company', 'P-1', 'Gender', '', '', '', '0000-00-00', '', NULL, 'rdf.jpeg', 20, '08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, ''),
('dieudonne', '', 'niyonzima@gmail.com', '0000-00-00', 0, 'A- Company', 'P-1', 'Gender', '', '', '', '0000-00-00', '', NULL, 'download.jpeg', 21, '08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, ''),
('Mutsinzi Frank', 'Frank', 'gatesiemmy08@gmail.com', '0000-00-00', 2124, 'A- Company', 'P-1', 'Male', '072296865', 'Mukamugisha ', '07998626962', '0000-00-00', 'O+', NULL, 'images.png', 22, '08', NULL, NULL, NULL, NULL, NULL, NULL, 'Good performane', '', '', '', '', '1111'),
('Niyomugenga', 'Elyse', 'niyoelyse@gmail.com', '0000-00-00', 54576, 'A- Company', 'P-1', 'Male', '', '', '', '0000-00-00', '', NULL, 'images.png', 23, '08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, ''),
('Hragirimana Emmanuel', 'emma', 'emmanuel@gmail.com', '0000-00-00', 8, 'A- Company', 'P-1', 'Male', '', '', '', '0000-00-00', '', NULL, 'rdf.jpeg', 24, '08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, ''),
('Niyonzima Fils', 'now', 'niyonzimafils@gamil.com', '0000-00-00', 8, 'A- Company', 'P-1', 'Male', '', '', '', '0000-00-00', '', NULL, 'rdf.jpeg', 25, '08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, ''),
('Iradukunda ANGELO', 'gashoka', 'iraangeloo@gamil.com', '0000-00-00', 8, 'A- Company', 'P-1', 'Male', '', '', '', '0000-00-00', '', NULL, 'rdf.jpeg', 26, '08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, ''),
('Mulisa', 'Ivan', 'mulisa', '0000-00-00', 8, 'A- Company', 'P-1', 'Male', '', '', '', '0000-00-00', '', NULL, 'download.jpeg', 27, '08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, ''),
('Rugirangoga', '', '', '0000-00-00', 0, 'A- Company', 'P-1', 'Gender', '', '', '', '0000-00-00', '', NULL, 'download.jpeg', 28, '08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, ''),
('dieudonne', 'Ivan', '', '0000-00-00', 0, 'A- Company', 'P-1', 'Gender', '', '', '', '0000-00-00', '', NULL, 'images.png', 29, '08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, ''),
('Mfurankunda Kevin', 'Elyse', 'mfura@gmail.com', '0000-00-00', 8, 'A- Company', 'P-1', 'Male', '', '', '', '0000-00-00', '', NULL, 'rdf.jpeg', 30, '08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, ''),
('dieudonne', 'Dieudonne', '', '0000-00-00', 0, 'A- Company', 'P-1', 'Gender', '', '', '', '0000-00-00', '', NULL, 'download.jpeg', 31, '08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, ''),
('Mugisha ', '', '', '0000-00-00', 0, 'A- Company', 'P-1', 'Gender', '', '', '', '0000-00-00', '', NULL, 'rdf.jpeg', 32, '08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, ''),
('Rubimbura ', 'david', '', '0000-00-00', 5855666, 'B- Company', 'P-3', 'Gender', '14552232665+', '', '', '0000-00-00', '', NULL, 'rdf.jpeg', 33, '08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, ''),
('Iranzi', 'rehobot', 'byiringirodie@gmail.com', '0000-00-00', 8, 'B- Company', 'P-2', 'Male', '1212121212', 'Mukasamari angel aline', '0790039602', '0000-00-00', 'O+', NULL, 'download.jpeg', 34, '08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, ''),
('Rutwaza', 'Nelson', 'rutwaza@gmail.com', '0000-00-00', 125465665, 'B- Company', 'P-2', 'Male', '21511515151', 'Mukasamari angel aline', '0790039458', '0000-00-00', 'O+', NULL, 'rdf.jpeg', 35, '08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, ''),
('Elisa', 'Elisa1', 'elosa@gmail.com', '0000-00-00', 629529595, 'B- Company', 'P-3', 'Male', '08', 'Uwase Alice', '07862969628', '0000-00-00', 'A+', NULL, 'rdf.jpeg', 36, '08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, ''),
('Samson', 'Emmy', 'samson@gmail.com', '0000-00-00', 8, 'B- Company', 'P-2', 'Male', '08', 'ange', 'ange', '0000-00-00', 'O+', NULL, 'jeshi.PNG', 37, '08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, ''),
('Byiringiro Frank', 'Dieudonne', 'dieudonne1121@gmail.com', '0000-00-00', 788986552, 'A- Company', 'P-1', 'Male', '08', 'Mukamusonera Angelique', '07386269621', '0000-00-00', 'O+', NULL, 'passport1.jpg', 38, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, ''),
('murenzii', 'emma', 'emmamure@gmail.com', '0000-00-00', 5416, 'A- Company', 'P-1', 'Male', '08', 'mama', '0786958981', '0000-00-00', 'O+', NULL, 'me.jpg', 39, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, ''),
('black hat', 'dior', 'blackhat@gmail.com', '0000-00-00', 2147483647, 'A- Company', 'P-1', 'Male', '08', 'me', 'anete', '0000-00-00', 'O+', NULL, 'me.jpg', 40, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, ''),
('Mukamisha', 'angle', 'mukamisha', '0000-00-00', 2147483647, 'A- Company', 'P-1', 'Gender', '08', 'Mukamugisha ', '0786958981', '0000-00-00', 'O+', NULL, 'passport1.jpg', 41, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, ''),
('Mugabe', 'angelo', 'mugabe@gmail.com', '0000-00-00', 781887886, 'A- Company', 'P-1', 'Gender', '08', 'Mukamugisha ', '07386269621', '0000-00-00', 'O+', NULL, 'me.jpg', 42, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, ''),
('Muhumuza', 'Alex', 'muhumuza@gmail.com', '0000-00-00', 78529878, 'A- Company', 'P-1', 'Male', '08', 'mukamisha', '07965245', '0000-00-00', 'O+', NULL, 'Unit 4_Present.pdf', 43, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, ''),
('Mupenzi ', 'Alice', 'mupenzi@gmail.com', '0000-00-00', 786296969, 'A- Company', 'P-1', 'Female', '08', 'Mukamisha Anglelique', '0786296962', '0000-00-00', 'O+', NULL, 'profile.png', 44, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, ''),
('Nzabandora', 'Emmanuel', 'nzabandora@gmail.com', '0000-00-00', 2147483647, 'A- Company', 'P-1', 'Male', '08', 'Sombe Jane', '07862969624', '0000-00-00', 'O+', NULL, 'gatesi.jpg', 45, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, ''),
(NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 46, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'hello', 'heloaganin', 'we go for now', 'we rush', 'thats great', ''),
(NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 47, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'hello', 'heloaganin', 'we go for now', 'we rush', 'thats great', ''),
(NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 48, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'hello', 'heloaganin', 'we go for now', 'we rush', 'thats great', '');

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `cadet_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` enum('unread','read','replied') DEFAULT 'unread',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contact_messages`
--

INSERT INTO `contact_messages` (`id`, `cadet_id`, `subject`, `message`, `status`, `created_at`) VALUES
(1, 3, 'request', ';kjlhkgjfgkhl;jlhkgj', 'unread', '2025-09-27 17:26:14'),
(2, 3, 'request', ';kjlhkgjfgkhl;jlhkgj', 'unread', '2025-09-27 17:59:21'),
(3, 3, 'ikjhgtf', 'ljhkgjkfbhknklkikik', 'unread', '2025-09-27 18:28:24');

-- --------------------------------------------------------

--
-- Table structure for table `intake`
--

CREATE TABLE `intake` (
  `intakeid` int(11) NOT NULL,
  `intake_name` varchar(255) NOT NULL,
  `creation_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `intake`
--

INSERT INTO `intake` (`intakeid`, `intake_name`, `creation_date`) VALUES
(7, '08', '0000-00-00'),
(8, '06', '0000-00-00');

-- --------------------------------------------------------

--
-- Table structure for table `materials`
--

CREATE TABLE `materials` (
  `material_id` int(11) NOT NULL,
  `material_code` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `size` varchar(50) DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `barcode` varchar(100) DEFAULT NULL,
  `status` enum('available','checked_out','outside_institution','taken_outside') DEFAULT 'available',
  `location` varchar(100) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `supplier_name` varchar(255) DEFAULT NULL,
  `supplier_contact` varchar(20) DEFAULT NULL,
  `supplier_email` varchar(100) DEFAULT NULL,
  `registered_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `registered_by` varchar(100) DEFAULT NULL,
  `registered_contact` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `telephone` varchar(15) DEFAULT NULL,
  `receiver_name` varchar(255) DEFAULT NULL,
  `category` varchar(255) DEFAULT NULL,
  `current_location` enum('in_stock','with_cadet','outside_institution') DEFAULT 'in_stock',
  `cadet_id` int(11) DEFAULT NULL,
  `sent_to_person` varchar(255) DEFAULT NULL,
  `sent_to_contact` varchar(20) DEFAULT NULL,
  `reason` varchar(100) DEFAULT NULL,
  `sent_date` date DEFAULT NULL,
  `taken_date` datetime DEFAULT NULL,
  `external_notes` text DEFAULT NULL,
  `checkout_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `materials`
--

INSERT INTO `materials` (`material_id`, `material_code`, `name`, `description`, `size`, `quantity`, `barcode`, `status`, `location`, `supplier_id`, `supplier_name`, `supplier_contact`, `supplier_email`, `registered_date`, `registered_by`, `registered_contact`, `notes`, `telephone`, `receiver_name`, `category`, `current_location`, `cadet_id`, `sent_to_person`, `sent_to_contact`, `reason`, `sent_date`, `taken_date`, `external_notes`, `checkout_date`) VALUES
(3, 'MAT-000001', 'telephone', 'dfgfd', NULL, 1, '3434', 'checked_out', 'remera', NULL, NULL, NULL, NULL, '2025-09-25 14:17:02', 'System Administrator', NULL, NULL, '0786296962', NULL, NULL, 'in_stock', 1, NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-08 09:35:31'),
(4, 'MAT-000002', 'ggg', 'sdfddd', NULL, 1, 'GGG044666', 'checked_out', NULL, NULL, 'sd', '2343343433434343', NULL, '2025-09-25 17:36:40', 'Security Officer', NULL, '', '0788888888', NULL, 'Electronics', 'in_stock', 3, NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-08 09:36:31'),
(7, 'MAT-000003', 'pc', 'I7', '2', 1, '384320', 'checked_out', NULL, NULL, 'jj', '8765434567', NULL, '2025-09-25 18:51:55', 'Security Officer', NULL, 'hj;lkjhkl', '7777777777', NULL, 'Electronics', 'in_stock', 18, NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-07 20:36:29'),
(8, 'MAT-000004', 'xxxzx', 'juijl;jo', '', 1, 'XXXZX398791', 'taken_outside', NULL, NULL, 'jiojiojij', '8765434567', 'dj@gmail.com', '2025-09-26 13:05:14', 'Security Officer', NULL, 'jijkljkl', '0786296962', NULL, 'Sports Equipment', 'with_cadet', 1, 'ronsard', '34567890', 'modification', '2025-10-07', '2025-10-07 20:37:18', 'ertyu', NULL),
(9, 'MAT-000005', 'shoes', '', '', 1, 'SHOES555507', 'checked_out', NULL, NULL, 'ronsard', '345676543', 'dj@gmail.com', '2025-09-26 13:32:50', 'Security Officer', NULL, '', '0788602555', NULL, 'Sports Equipment', 'in_stock', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(11, 'MAT-000006', 'RAM', '8GB', '', 1, 'RAM695393', 'checked_out', NULL, NULL, 'ronsard', '8765434567', 'dj@gmail.com', '2025-09-27 05:59:28', 'Security Officer', NULL, '', '7777777777', NULL, 'Electronics', 'in_stock', 18, NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-08 09:36:06'),
(12, 'MAT-000007', 'xxxzx', 'jijijiji', '', 1, '519893', 'checked_out', NULL, NULL, 'jiji', '8765434567', 'djj@gmail.com', '2025-09-29 12:55:51', NULL, NULL, 'jijijij', '0788888888', NULL, 'Furniture', 'in_stock', 3, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(13, 'MAT-000008', 'hard disk', 'ijiji', '', 1, 'HARDDISK582894', 'checked_out', NULL, NULL, 'ronsardj', '0781121345', 'djjj@gmail.com', '2025-09-29 12:57:02', NULL, NULL, 'jiojioji', '0786296962', NULL, 'Tools', 'in_stock', 1, NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-08 09:35:54'),
(14, 'MAT-000009', 'hard disk', 'jiji', '', 1, 'HARDDISK886163', 'checked_out', NULL, NULL, 'huhu', '89098', 'hdjj@gmail.com', '2025-09-29 13:01:51', NULL, NULL, 'ujijij', '0788888888', NULL, 'Stationery', 'in_stock', 3, NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-06 20:02:20'),
(15, '2345678', 'dd', NULL, NULL, 1, NULL, '', NULL, NULL, NULL, NULL, NULL, '2025-10-06 12:46:03', NULL, NULL, NULL, '0788888888', NULL, NULL, 'in_stock', 3, 'ronsard', '0987654', 'other', '2025-10-06', '2025-10-07 15:34:44', 'ertyuiuytr', NULL),
(16, 'MAT-000011', 'cable', 'jkjjjjjjj', '', 2, 'CABLE116342', 'available', NULL, NULL, 'jiji', '89098', 'djj@gmail.com', '2025-10-06 18:42:39', NULL, NULL, 'kkkkkkkkkk', '7777777777', NULL, 'Electronics', 'in_stock', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(17, 'MAT-000012', 'screen', 'mmmmmmmmmmm', '', 2, 'SCREEN174528', 'checked_out', NULL, NULL, 'dfdjj', '34678', 'jjhdjj@gmail.com', '2025-10-06 18:43:33', NULL, NULL, 'sdfgh', '7777777777', NULL, 'Tools', 'in_stock', 18, NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-06 20:52:33'),
(18, 'MAT-000013', 'keeyboard', 'kkk', '', 1, 'KEEYBOAR265600', 'available', NULL, NULL, 'jiji', '89098', 'idjjj@gmail.com', '2025-10-06 18:45:03', NULL, NULL, 'sddsdds', '072296865', NULL, 'Other', 'in_stock', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(19, 'MAT-000014', 'mouse', 'kkkkkkkkkkkkuh', '', 2, 'MOUSE319625', 'checked_out', NULL, NULL, 'sdd', '345678', 'djere@gmail.com', '2025-10-06 18:46:03', NULL, NULL, 'uuuuuuu', '072296865', NULL, 'Other', 'in_stock', 22, NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-07 11:30:47'),
(20, 'MAT-000015', 'note books ', 'logister', '', 4, 'BOOKS390959', 'available', NULL, NULL, 'ronsard', '34567', 'jjhdjj@gmail.com', '2025-10-06 18:47:23', NULL, NULL, 'iiiiiii\r\n', '072296865', NULL, 'Books', 'in_stock', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(22, '456789', 'pc', NULL, NULL, 1, NULL, 'taken_outside', NULL, NULL, NULL, NULL, NULL, '2025-10-07 13:59:32', NULL, NULL, NULL, '0788888888', NULL, NULL, 'in_stock', 3, 'ronsard', '34567890', 'repair', '2025-10-07', '2025-10-07 16:37:00', 'ertyuiop', NULL),
(23, '4567', 'phone', NULL, NULL, 1, NULL, 'checked_out', NULL, NULL, NULL, NULL, NULL, '2025-10-07 14:00:11', NULL, NULL, NULL, '0788888888', NULL, NULL, 'in_stock', 3, 'rrr', '345678', 'modification', '2025-10-07', '2025-10-07 16:25:20', 'ertyuiop', '2025-10-07 16:58:53'),
(25, '2345', 'pc', NULL, NULL, 1, NULL, 'taken_outside', NULL, NULL, NULL, NULL, NULL, '2025-10-07 18:35:35', NULL, NULL, NULL, '7777777777', NULL, NULL, 'in_stock', 18, 'ruhinda', '34567', 'repair', '2025-10-07', '2025-10-08 10:34:36', 'it will be back in december', NULL),
(26, 'MAT-000019', 'mouse', 'iiiiii', '', 1, 'MOUSE740714', 'available', NULL, NULL, 'jijijjj', '7890988', 'djjjj@gmail.com', '2025-10-08 08:39:37', NULL, NULL, 'iiiiii', '0786296962', NULL, 'Electronics', 'in_stock', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(28, 'MAT-000020', 'sport clothes', 'uuu', '', 1, 'SPORTCLO833429', 'available', NULL, NULL, 'jijiu', '345679', 'duj@gmail.com', '2025-10-08 08:41:01', NULL, NULL, 'uuu', '0786296962', NULL, 'Uniforms', 'in_stock', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(30, 'MAT-000021', 'headset', 'jjj', '', 1, 'HEADSET416842', 'available', NULL, NULL, 'sdj', '99999999', 'idjjjn@gmail.com', '2025-10-08 09:07:35', NULL, NULL, 'nnn', '0786296962', NULL, 'Other', 'in_stock', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(33, 'MAT-000022', 'coustime', 'ooooooo', '', 1, 'COUSTIME533000', 'available', NULL, NULL, 'ronsard', '07811213459', 'dj@gmail.com', '2025-10-08 12:45:57', NULL, NULL, 'ooooooo', '0786296962', NULL, 'Uniforms', 'in_stock', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(35, '234567899', 'computer', NULL, NULL, 1, NULL, 'taken_outside', NULL, NULL, NULL, NULL, NULL, '2025-10-08 12:48:18', NULL, NULL, NULL, '0786296962', NULL, NULL, 'in_stock', 1, 'ronsard', '34567899', 'modification', '2025-10-08', '2025-10-08 14:48:54', 'kkkkkkk', NULL),
(36, 'MAT-000024', 't-shirt', 'jj', '', 1, 'T-SHIRT183115', 'available', NULL, NULL, 'ronsardngoga', '8909888', 'djj@gmail.com', '2025-10-08 13:13:46', NULL, NULL, 'jjjjjjjjjj', '0786296962', NULL, 'Sports Equipment', 'in_stock', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(37, 'MAT-000025', 'mouses', 'fff', '', 2, 'MOUSES577000', 'available', NULL, NULL, 'ronsard', '890989', 'idjjj@gmail.com', '2025-10-08 13:20:04', NULL, NULL, 'iiii', '7777777777', NULL, 'Other', 'in_stock', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(38, 'MAT-000026', 'pantalo', 'kkk', '', 1, 'PANTALO413868', 'checked_out', NULL, NULL, 'dd', '345673', 'dj@gmail.com', '2025-10-08 15:14:01', NULL, NULL, 'ddd', '7777777777', NULL, 'Uniforms', 'in_stock', 18, NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-08 17:19:51');

-- --------------------------------------------------------

--
-- Table structure for table `material_assignments`
--

CREATE TABLE `material_assignments` (
  `assignment_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `cadet_id` int(11) NOT NULL,
  `assigned_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `expected_return_date` date DEFAULT NULL,
  `actual_return_date` date DEFAULT NULL,
  `assigned_by` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('active','returned','overdue') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `material_assignments`
--

INSERT INTO `material_assignments` (`assignment_id`, `material_id`, `cadet_id`, `assigned_date`, `expected_return_date`, `actual_return_date`, `assigned_by`, `notes`, `status`) VALUES
(1, 3, 1, '2025-09-25 14:18:02', '2025-10-02', NULL, 'System Administrator', '', 'active'),
(2, 8, 1, '2025-09-26 13:05:14', NULL, NULL, 'Security Officer', 'Material automatically assigned to cadet (telephone match)', 'active'),
(3, 9, 2, '2025-09-26 13:32:50', NULL, NULL, 'Security Officer', 'Material automatically assigned to cadet (telephone match)', 'active'),
(4, 11, 1, '2025-09-27 05:59:28', NULL, NULL, 'Security Officer', 'Material automatically assigned to cadet (telephone match)', 'active'),
(5, 12, 19, '2025-09-29 12:55:51', NULL, NULL, NULL, 'Material automatically assigned to cadet (telephone match)', 'active'),
(6, 13, 1, '2025-09-29 12:57:02', NULL, NULL, NULL, 'Material automatically assigned to cadet (telephone match)', 'active'),
(7, 14, 3, '2025-09-29 13:01:51', NULL, NULL, NULL, 'Material automatically assigned to cadet (telephone match)', 'active'),
(8, 16, 18, '2025-10-06 18:42:39', NULL, NULL, NULL, 'Material automatically assigned to cadet (telephone match)', 'active'),
(9, 17, 18, '2025-10-06 18:43:33', NULL, NULL, NULL, 'Material automatically assigned to cadet (telephone match)', 'active'),
(10, 18, 22, '2025-10-06 18:45:03', NULL, NULL, NULL, 'Material automatically assigned to cadet (telephone match)', 'active'),
(11, 19, 22, '2025-10-06 18:46:03', NULL, NULL, NULL, 'Material automatically assigned to cadet (telephone match)', 'active'),
(12, 20, 22, '2025-10-06 18:47:24', NULL, NULL, NULL, 'Material automatically assigned to cadet (telephone match)', 'active'),
(13, 36, 1, '2025-10-08 13:13:46', NULL, NULL, NULL, 'Material automatically assigned to cadet (telephone match)', 'active'),
(14, 37, 18, '2025-10-08 13:20:04', NULL, NULL, NULL, 'Material automatically assigned to cadet (telephone match)', 'active'),
(15, 38, 18, '2025-10-08 15:14:02', NULL, NULL, NULL, 'Material automatically assigned to cadet (telephone match)', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `material_checkouts`
--

CREATE TABLE `material_checkouts` (
  `checkout_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `cadet_id` int(11) NOT NULL,
  `checkout_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `purpose` text NOT NULL,
  `expected_return_date` date DEFAULT NULL,
  `checked_out_by` varchar(100) DEFAULT NULL,
  `status` enum('pending','approved','rejected','returned','overdue') DEFAULT 'pending',
  `approved_by` varchar(100) DEFAULT NULL,
  `approved_date` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `material_links`
--

CREATE TABLE `material_links` (
  `link_id` int(11) NOT NULL,
  `material_id` int(11) DEFAULT NULL,
  `cadet_id` int(11) DEFAULT NULL,
  `linked_by` varchar(255) DEFAULT NULL,
  `linked_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `material_links`
--

INSERT INTO `material_links` (`link_id`, `material_id`, `cadet_id`, `linked_by`, `linked_date`, `notes`) VALUES
(1, 7, 1, 'Security Officer', '2025-09-25 18:51:57', 'Material automatically linked to cadet (telephone match)');

-- --------------------------------------------------------

--
-- Table structure for table `material_suppliers`
--

CREATE TABLE `material_suppliers` (
  `supplier_id` int(11) NOT NULL,
  `supplier_name` varchar(100) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `contact_email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification`
--

CREATE TABLE `notification` (
  `id` int(11) NOT NULL,
  `cadet_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `target_page` varchar(50) DEFAULT 'dashboard'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notification`
--

INSERT INTO `notification` (`id`, `cadet_id`, `title`, `message`, `type`, `is_read`, `created_at`, `target_page`) VALUES
(1, 1, 'New Material Assigned', 'Material \'t-shirt\' has been assigned to you', 'new_material', 1, '2025-10-08 13:13:47', 'dashboard'),
(2, 18, 'New Material Assigned', 'Material \'mouses\' has been assigned to you', 'new_material', 1, '2025-10-08 13:20:04', 'dashboard'),
(3, 18, 'New Material Assigned', 'Material \'pantalo\' has been assigned to you', 'new_material', 1, '2025-10-08 15:14:02', 'dashboard');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `cadetid` int(11) DEFAULT NULL,
  `userid` int(11) DEFAULT NULL,
  `notification_type` varchar(255) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `cadetid`, `userid`, `notification_type`, `description`, `timestamp`, `status`) VALUES
(0, 2, NULL, 'Statement', 'User with ID 2 has uploaded a new statement.', '2023-12-18 13:06:27', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `userid` int(11) DEFAULT NULL,
  `permission_name` varchar(255) DEFAULT NULL,
  `role_name` varchar(255) DEFAULT NULL,
  `fname` varchar(255) DEFAULT NULL,
  `lname` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `userid`, `permission_name`, `role_name`, `fname`, `lname`) VALUES
(6, 38, 'statements', 'Instructor', 'Niyonzima ', 'Elyse'),
(7, 39, 'statements', 'admin', 'admin', 'admin'),
(9, 37, 'statements', 'Platoon Commander', 'Byiringiro', 'Dieudonne'),
(10, 41, 'statements', 'Company Commander', 'kwizera', 'james'),
(11, 43, 'statements', 'admin', 'Habimana', 'Mourice'),
(12, 44, 'statements', 'Company Commander', 'Gasasira', 'Emmy'),
(14, 45, 'statements', 'Platoon Commander', 'Izihirwe', 'Blaise'),
(20, 36, 'add cadet', 'Health', '', ''),
(21, NULL, '1 February, 2024', NULL, NULL, NULL),
(22, NULL, '1 February, 2024', NULL, NULL, NULL),
(23, NULL, '1 February, 2024', NULL, NULL, NULL),
(24, 50, 'Instructor', 'Instructor', 'bigango ', 'emmanuel'),
(25, 47, 'Instructor', 'Instructor', 'Sports', 'Officer');

-- --------------------------------------------------------

--
-- Table structure for table `rejected_statements`
--

CREATE TABLE `rejected_statements` (
  `id` int(11) NOT NULL,
  `number` int(11) NOT NULL,
  `statementdescription` varchar(255) NOT NULL,
  `statementfile` varchar(255) NOT NULL,
  `cadetid` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `statements`
--

CREATE TABLE `statements` (
  `statementid` int(11) NOT NULL,
  `number` int(11) DEFAULT NULL,
  `statementdescription` varchar(255) DEFAULT NULL,
  `statementfile` longblob DEFAULT NULL,
  `cadetid` int(11) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `critical_requirement` varchar(255) DEFAULT NULL,
  `status` enum('Effective','Ineffective','None') DEFAULT 'None',
  `rank` int(11) DEFAULT NULL,
  `observers_name` varchar(255) DEFAULT NULL,
  `statestatus` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `statements`
--

INSERT INTO `statements` (`statementid`, `number`, `statementdescription`, `statementfile`, `cadetid`, `date`, `critical_requirement`, `status`, `rank`, `observers_name`, `statestatus`) VALUES
(392, NULL, 'Telling the truth is a fundamental aspect of integrity and trustworthiness. Here are some key points about its importance:', 0x3232333032363531332059325f4353455f47414b4f2e706466, 9, '2025-04-28', '[\"5. Telling the truth\"]', 'Effective', 2, 'Sergeant                       Habimana Mourice', 'Approved'),
(394, NULL, 'A cadet demonstrates honesty by acknowledging and respecting the authority of instructors, engaging in open and constructive communication.', 0x41492d46616369616c205265636f676e6974696f6e2050726f6a6563742050726f706f73616c2e706466, 4, '2025-04-02', '[\"6. Acting honestly\"]', 'Effective', 3, 'Sergeant                       Habimana Mourice', 'Approved'),
(395, NULL, ' Refuses to acknowledge errors, often making excuses or deflecting blame onto others.', 0x41492d46616369616c205265636f676e6974696f6e2050726f6a6563742050726f706f73616c2e706466, 4, '2025-04-01', '[\"7. Admitting mistakes\"]', 'Ineffective', 1, 'Sergeant                       Habimana Mourice', 'Approved');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `userid` int(11) NOT NULL,
  `fname` varchar(100) DEFAULT NULL,
  `lname` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `cadet_id` int(11) DEFAULT NULL,
  `joindate` date DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `number` varchar(20) DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `rank` varchar(50) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `dateofbirth` date DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `role` enum('admin','gatekeeper','student') DEFAULT 'student'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`userid`, `fname`, `lname`, `email`, `cadet_id`, `joindate`, `password`, `number`, `gender`, `rank`, `department`, `dateofbirth`, `profile_image`, `status`, `role`) VALUES
(1, 'System', 'Administrator', 'admin@mms.com', NULL, '2025-09-23', 'admin123', '1234567890', 'Male', 'Head Admin', 'Administration', NULL, NULL, NULL, 'admin'),
(2, 'Security', 'Officer', 'gatekeeper@mms.com', NULL, '2025-09-23', 'gate123', '0987654321', 'Male', 'Senior Guard', 'Security', NULL, NULL, NULL, 'gatekeeper'),
(3, 'John', 'Doe', 'student@mms.com', NULL, '2025-09-23', 'student123', '5551234567', 'Male', 'Cadet', 'Engineering', NULL, NULL, NULL, 'student'),
(4, 'Jane', 'Smith', 'jane.smith@mms.com', NULL, '2025-09-23', 'student123', '5559876543', 'Female', 'Cadet', 'Science', NULL, NULL, NULL, 'student'),
(36, 'Rugirangoga', 'Ronsard', 'ronsard@gmail.com', NULL, '0000-00-00', '$2y$10$HNEwhVB/zSVNOsVLsqt5z.ky.ze9olvwcQlBAINsGBVd0wdmIR5x.', '0788602555', 'Male', 'Major', 'Health', '0000-00-00', NULL, 'unblock', 'student'),
(38, 'Niyonzima', 'Elyse', 'niyonzima@gmail.com', NULL, '0000-00-00', '$2y$10$m7/XNe.eI3dFgvkykNILp.0SI0mUBAGvsGqh4vNaA9lobL4O3QAAq', '0788602558', 'Male', 'sergent', 'Instructor', '0000-00-00', NULL, 'unblock', 'student'),
(41, 'Kwizera', 'James', 'kwizera@gmail.com', NULL, '0000-00-00', '$2y$10$z2RG2MnP5qqSjS5aJtuQZukDm.yPMuBAqrvKErx9JpdG2t/le9wr2', '0785248752', 'Male', 'Captain', 'Company Commander', '0000-00-00', NULL, 'unblock', 'student'),
(43, 'Habimana', 'Mourice', 'admin@gmail.com', NULL, '0000-00-00', '$2y$10$5Kd.1.M91PaZtGJ5SS/txeG2CxSkLi7664yrZogLMSRHbiQD.ueHi', '5078885877', 'Male', 'Sergeant', 'Instructor', '0000-00-00', 'desktopimages.png', NULL, 'student'),
(44, 'Gasasira', 'Emmy', 'company@gmail.com', NULL, '0000-00-00', '$2y$10$ZtrRKdeMf1yO6kMF8WPZZuhm562DM7nZeb4yILUzgc.ntiokXBj42', '5078885819', 'Gender', 'Captain', 'Company Commander', '0000-00-00', NULL, NULL, 'student'),
(45, 'Izihirwe', 'Blaise', 'platoon1@gmail.com', NULL, '0000-00-00', '$2y$10$KyXMFXH3np4iZ5t9icOcouPDUYmJpXaMsv6hDh3LhsJp/F2uhstue', '0785252557', 'Male', 'Lieutnant', 'Platoon Commander', '0000-00-00', NULL, 'unblock', 'student'),
(46, 'Rukundo', 'Kevin', 'admin@gmail.com', NULL, '0000-00-00', 'admin', '', 'Gender', '', 'Select level or Department', '0000-00-00', NULL, NULL, 'student'),
(47, 'Sports', 'Officer', 'sportsofficer@gmail.com', NULL, '0000-00-00', '$2y$10$Pl3kIaGC2lGCZ/Tt54gNHuDEkNov2Ed/ob9elfaYQa8EpCzCI7.K.', '0786296963', 'Male', 'Lieutenant', 'Instructor', '0000-00-00', NULL, NULL, 'student'),
(50, 'biganbo', 'emmy', 'bigango@gmail.com', NULL, '0000-00-00', '$2y$10$Shact5kYcmgjJIhGXe6SzO4zTEg66JK3SgBbzNSy8bsJ16LV1Hdhe', '07862969625678', 'Gender', 'Captain ', 'Platoon Commander', '0000-00-00', NULL, NULL, 'student');

-- --------------------------------------------------------

--
-- Table structure for table `user_cadet_assignment`
--

CREATE TABLE `user_cadet_assignment` (
  `assignment_id` int(11) NOT NULL,
  `userid` int(11) DEFAULT NULL,
  `cadetid` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_cadet_assignment`
--

INSERT INTO `user_cadet_assignment` (`assignment_id`, `userid`, `cadetid`) VALUES
(296, 44, 1),
(297, 44, 2),
(298, 44, 4),
(299, 44, 11),
(300, 44, 19),
(301, 44, 20),
(302, 44, 21),
(303, 44, 22),
(304, 44, 23),
(305, 44, 24),
(306, 44, 25),
(307, 44, 26),
(308, 44, 27),
(309, 44, 28),
(310, 44, 29),
(311, 44, 30),
(312, 44, 31),
(313, 44, 32),
(329, 45, 4),
(330, 45, 19),
(331, 45, 20),
(332, 45, 21),
(333, 45, 22),
(334, 45, 23),
(335, 45, 24),
(336, 45, 25),
(337, 45, 26),
(338, 45, 27),
(339, 45, 28),
(340, 45, 29),
(341, 45, 30),
(342, 45, 31),
(343, 45, 32);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `approved_statements`
--
ALTER TABLE `approved_statements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `cadet`
--
ALTER TABLE `cadet`
  ADD PRIMARY KEY (`cadetid`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cadet_id` (`cadet_id`);

--
-- Indexes for table `intake`
--
ALTER TABLE `intake`
  ADD PRIMARY KEY (`intakeid`);

--
-- Indexes for table `materials`
--
ALTER TABLE `materials`
  ADD PRIMARY KEY (`material_id`),
  ADD UNIQUE KEY `material_code` (`material_code`),
  ADD UNIQUE KEY `barcode` (`barcode`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `cadet_id` (`cadet_id`);

--
-- Indexes for table `material_assignments`
--
ALTER TABLE `material_assignments`
  ADD PRIMARY KEY (`assignment_id`),
  ADD KEY `material_id` (`material_id`),
  ADD KEY `idx_cadet` (`cadet_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `material_checkouts`
--
ALTER TABLE `material_checkouts`
  ADD PRIMARY KEY (`checkout_id`),
  ADD KEY `material_id` (`material_id`),
  ADD KEY `cadet_id` (`cadet_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `material_links`
--
ALTER TABLE `material_links`
  ADD PRIMARY KEY (`link_id`),
  ADD KEY `material_id` (`material_id`),
  ADD KEY `cadet_id` (`cadet_id`);

--
-- Indexes for table `material_suppliers`
--
ALTER TABLE `material_suppliers`
  ADD PRIMARY KEY (`supplier_id`);

--
-- Indexes for table `notification`
--
ALTER TABLE `notification`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cadet_id` (`cadet_id`),
  ADD KEY `idx_is_read` (`is_read`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rejected_statements`
--
ALTER TABLE `rejected_statements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `statements`
--
ALTER TABLE `statements`
  ADD PRIMARY KEY (`statementid`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`userid`);

--
-- Indexes for table `user_cadet_assignment`
--
ALTER TABLE `user_cadet_assignment`
  ADD PRIMARY KEY (`assignment_id`),
  ADD KEY `userid` (`userid`),
  ADD KEY `cadetid` (`cadetid`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `approved_statements`
--
ALTER TABLE `approved_statements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=156;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `cadet`
--
ALTER TABLE `cadet`
  MODIFY `cadetid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `intake`
--
ALTER TABLE `intake`
  MODIFY `intakeid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `materials`
--
ALTER TABLE `materials`
  MODIFY `material_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `material_assignments`
--
ALTER TABLE `material_assignments`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `material_checkouts`
--
ALTER TABLE `material_checkouts`
  MODIFY `checkout_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `material_links`
--
ALTER TABLE `material_links`
  MODIFY `link_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `material_suppliers`
--
ALTER TABLE `material_suppliers`
  MODIFY `supplier_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notification`
--
ALTER TABLE `notification`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `rejected_statements`
--
ALTER TABLE `rejected_statements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `statements`
--
ALTER TABLE `statements`
  MODIFY `statementid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=396;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `userid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `user_cadet_assignment`
--
ALTER TABLE `user_cadet_assignment`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=344;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`userid`) ON DELETE CASCADE;

--
-- Constraints for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD CONSTRAINT `contact_messages_ibfk_1` FOREIGN KEY (`cadet_id`) REFERENCES `cadet` (`cadetid`) ON DELETE CASCADE;

--
-- Constraints for table `materials`
--
ALTER TABLE `materials`
  ADD CONSTRAINT `materials_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `material_suppliers` (`supplier_id`),
  ADD CONSTRAINT `materials_ibfk_2` FOREIGN KEY (`cadet_id`) REFERENCES `cadet` (`cadetid`);

--
-- Constraints for table `material_assignments`
--
ALTER TABLE `material_assignments`
  ADD CONSTRAINT `material_assignments_ibfk_1` FOREIGN KEY (`material_id`) REFERENCES `materials` (`material_id`),
  ADD CONSTRAINT `material_assignments_ibfk_2` FOREIGN KEY (`cadet_id`) REFERENCES `cadet` (`cadetid`);

--
-- Constraints for table `material_checkouts`
--
ALTER TABLE `material_checkouts`
  ADD CONSTRAINT `material_checkouts_ibfk_1` FOREIGN KEY (`material_id`) REFERENCES `materials` (`material_id`),
  ADD CONSTRAINT `material_checkouts_ibfk_2` FOREIGN KEY (`cadet_id`) REFERENCES `cadet` (`cadetid`);

--
-- Constraints for table `material_links`
--
ALTER TABLE `material_links`
  ADD CONSTRAINT `material_links_ibfk_1` FOREIGN KEY (`material_id`) REFERENCES `materials` (`material_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `material_links_ibfk_2` FOREIGN KEY (`cadet_id`) REFERENCES `cadet` (`cadetid`) ON DELETE CASCADE;

--
-- Constraints for table `user_cadet_assignment`
--
ALTER TABLE `user_cadet_assignment`
  ADD CONSTRAINT `user_cadet_assignment_ibfk_1` FOREIGN KEY (`userid`) REFERENCES `users` (`userid`),
  ADD CONSTRAINT `user_cadet_assignment_ibfk_2` FOREIGN KEY (`cadetid`) REFERENCES `cadet` (`cadetid`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
