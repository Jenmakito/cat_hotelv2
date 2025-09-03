-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 03, 2025 at 07:11 AM
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
-- Database: `cathotel_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `cats`
--

CREATE TABLE `cats` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `color` varchar(50) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `gender` enum('male','female') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cats`
--

INSERT INTO `cats` (`id`, `user_id`, `name`, `color`, `age`, `gender`) VALUES
(27, 3, 'moo ping', 'ดำ', 3, 'male'),
(28, 7, 'free', 'black', 4, 'female');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `reservation_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `slip_path` varchar(255) DEFAULT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending_approval','approved','rejected') NOT NULL DEFAULT 'pending_approval'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `reservation_id`, `customer_id`, `amount`, `payment_method`, `slip_path`, `payment_date`, `status`) VALUES
(9, 22, 7, 1200.00, 'qrcode', 'uploads/slip_7_22_1756241281_5794.jpg', '2025-08-26 20:48:01', 'approved'),
(10, 23, 3, 2100.00, 'bank_transfer', 'uploads/slip_3_23_1756876163_7450.jpg', '2025-09-03 05:09:23', 'pending_approval');

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `cat_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `date_from` date NOT NULL,
  `date_to` date NOT NULL,
  `total_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `paid` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('pending','pending_approval','confirmed','cancelled','completed') NOT NULL DEFAULT 'pending',
  `checkin_code` varchar(10) DEFAULT NULL,
  `check_in` tinyint(1) NOT NULL DEFAULT 0,
  `check_out` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`id`, `customer_id`, `cat_id`, `room_id`, `date_from`, `date_to`, `total_cost`, `paid`, `status`, `checkin_code`, `check_in`, `check_out`) VALUES
(22, 7, 28, 4, '2025-08-29', '2025-09-01', 1200.00, 1, 'confirmed', 'G7H8I9', 0, 0),
(23, 3, 27, 1, '2025-09-04', '2025-09-18', 2100.00, 1, 'confirmed', '0DM12B', 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `id` int(11) NOT NULL,
  `room_number` varchar(50) NOT NULL,
  `room_type` enum('standard','vip') NOT NULL,
  `status` enum('available','occupied','maintenance') NOT NULL DEFAULT 'available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`id`, `room_number`, `room_type`, `status`) VALUES
(1, 'S01', 'standard', 'occupied'),
(2, 'S02', 'standard', 'available'),
(3, 'S03', 'standard', 'available'),
(4, 'V01', 'vip', 'available'),
(5, 'V02', 'vip', 'available');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','admin') DEFAULT 'user',
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `phone`, `email`, `address`) VALUES
(3, 'test', '$2y$10$7BHm3tT7xK3EHO8YTkgbp.KAeuZj8M7h5LUfYa0EuVnmyVahsc1FC', 'user', '083-200-2001', 'test@oGc.com', 'vp^j'),
(4, 'admin', '$2y$10$.HmYUCy/lRowAk59VXmP1unCMDvyw9HHZPiptnBV1T84QYCvhOhGq', 'admin', NULL, NULL, NULL),
(5, 'hi', '$2y$10$4Xu9YLwu2r1Gl6Ifb1iLYe6koxnb/Ns8Da.3YcC1qG7Lu0zCcg/wS', 'user', '083-200-2002', 'jWTxhk@hyper.cper', ''),
(7, 'test1', '$2y$10$gDyKnfFQJ5AxYOt0LuMxBOM3oPqdgbi9UbvER/kXCuNwx77ohEw6u', 'user', NULL, NULL, NULL),
(8, 'ttt', '$2y$10$ZBlBXOb4zpzUg2UNHs34NORyX7ZyaB5vg62FCMNjFI5b0SFbEVabO', 'user', NULL, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cats`
--
ALTER TABLE `cats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reservation_id` (`reservation_id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `cat_id` (`cat_id`),
  ADD KEY `room_id` (`room_id`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `room_number` (`room_number`);

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
-- AUTO_INCREMENT for table `cats`
--
ALTER TABLE `cats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cats`
--
ALTER TABLE `cats`
  ADD CONSTRAINT `cats_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reservations_ibfk_2` FOREIGN KEY (`cat_id`) REFERENCES `cats` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reservations_ibfk_3` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
