-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 27, 2025 at 05:14 PM
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
-- Database: `car_rental_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `cars`
--

CREATE TABLE `cars` (
  `id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `model` varchar(100) NOT NULL,
  `brand` varchar(50) NOT NULL,
  `year` int(11) NOT NULL,
  `price` int(11) NOT NULL,
  `km_driven` int(11) NOT NULL,
  `fuel_type` enum('Petrol','Diesel','Electric','Hybrid','CNG') NOT NULL,
  `transmission` enum('Automatic','Manual') NOT NULL,
  `main_image` varchar(255) NOT NULL,
  `sub_image1` varchar(255) DEFAULT NULL,
  `sub_image2` varchar(255) DEFAULT NULL,
  `sub_image3` varchar(255) DEFAULT NULL,
  `location` varchar(100) NOT NULL,
  `ownership` enum('First','Second','Third','Other') NOT NULL,
  `insurance_status` enum('Valid','Expired','None') NOT NULL,
  `description` text DEFAULT NULL,
  `is_sold` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cars`
--

INSERT INTO `cars` (`id`, `seller_id`, `model`, `brand`, `year`, `price`, `km_driven`, `fuel_type`, `transmission`, `main_image`, `sub_image1`, `sub_image2`, `sub_image3`, `location`, `ownership`, `insurance_status`, `description`, `is_sold`, `created_at`) VALUES
(1, 3, 'Polo', 'Volkswagen', 2020, 1000000, 56000, 'Petrol', 'Automatic', 'uploads/cars/3_1753451320_main_image_polo1.jpg', 'uploads/cars/3_1753451320_sub_image1_polo2.jpg', 'uploads/cars/3_1753451320_sub_image2_polo3.jpg', 'uploads/cars/3_1753451320_sub_image3_polo4.jpg', 'Changanacheryy', 'First', 'Valid', 'Well Maintained\r\nFully Modded', 0, '2025-07-25 13:48:40'),
(2, 3, 'Swift', 'Maruti Suzuki', 2023, 1100000, 45000, 'Petrol', 'Manual', 'uploads/cars/3_1753451435_main_image_swift1.jpg', 'uploads/cars/3_1753451435_sub_image1_swift2.jpg', 'uploads/cars/3_1753451435_sub_image2_swift3.jpg', 'uploads/cars/3_1753451435_sub_image3_swift4.jpg', 'Kottayam', 'First', 'Valid', 'Well Maintained', 0, '2025-07-25 13:50:35'),
(3, 3, 'M340i', 'BMW', 2024, 6500000, 34000, 'Petrol', 'Automatic', 'uploads/cars/3_1753457812_main_image_bmw1.jpg', 'uploads/cars/3_1753457812_sub_image1_bmw2.jpg', 'uploads/cars/3_1753457812_sub_image2_bmw3.jpg', 'uploads/cars/3_1753457812_sub_image3_bmw4.jpg', 'Kottayam', 'First', 'Valid', 'Well Maintained \r\nShowroom Service', 0, '2025-07-25 15:36:52'),
(4, 3, 'Thar Roxx', 'Mahindra', 2025, 2500000, 12000, 'Petrol', 'Automatic', 'uploads/cars/3_1753458834_main_image_Roxx1.jpg', 'uploads/cars/3_1753458834_sub_image1_Roxx2.jpg', 'uploads/cars/3_1753458834_sub_image2_Roxx3.jpg', 'uploads/cars/3_1753458834_sub_image3_Roxx4.jpg', 'Thiruvalla', 'First', 'Valid', 'Well Maintained\r\n', 0, '2025-07-25 15:53:54');

-- --------------------------------------------------------

--
-- Table structure for table `favorites`
--

CREATE TABLE `favorites` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `car_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `car_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `receiver_id`, `car_id`, `message`, `created_at`, `is_read`) VALUES
(1, 1, 3, 2, 'is it available', '2025-07-25 13:53:10', 1),
(2, 3, 1, 2, 'no', '2025-07-25 13:53:15', 1),
(3, 2, 3, 4, 'is it available', '2025-07-27 09:44:18', 1),
(4, 3, 2, 4, 'Yes', '2025-07-27 09:44:35', 0);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `user_type` enum('admin','seller','buyer') NOT NULL,
  `location` varchar(100) DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `aadhaar_status` enum('not_submitted','pending','approved','rejected') DEFAULT 'not_submitted',
  `aadhaar_path` varchar(255) DEFAULT NULL,
  `aadhaar_rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `phone`, `user_type`, `location`, `profile_pic`, `aadhaar_status`, `aadhaar_path`, `aadhaar_rejection_reason`, `created_at`) VALUES
(1, 'Alan', '$2y$10$e6yrtLl89K/YUo8j1tnerOq9./IgvwIsrtmD1.8L8VbpaEvkwwzi2', 'x9watche@gmail.com', '32424', 'buyer', 'Malapuram', 'https://ui-avatars.com/api/?name=qwerty', 'not_submitted', NULL, NULL, '2025-07-25 13:36:47'),
(2, 'admin', '$2y$10$8yfEt0Cz2LoKv9u4XGCmLOsyDaBs05rFmlz6KmWJR8.PcGI8qDnDm', 'admin@gmail.com', '8765358776', 'admin', 'Changanacheryy', 'https://ui-avatars.com/api/?name=admin', 'not_submitted', NULL, NULL, '2025-07-25 13:44:55'),
(3, 'Mathew Thomas', '$2y$10$l5aVp6snNPzBSBSx42pMJ.l61qPfIo3on.pmV4ZDjnsgfNeGadIGe', 'mathew@gmail.com', '9747459856', 'seller', 'Changanacheryy', 'https://ui-avatars.com/api/?name=Mathew+Thomas', 'approved', 'uploads/aadhaar/3_1753451173_Screenshot 2025-07-23 200031.png', NULL, '2025-07-25 13:45:57');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cars`
--
ALTER TABLE `cars`
  ADD PRIMARY KEY (`id`),
  ADD KEY `seller_id` (`seller_id`);

--
-- Indexes for table `favorites`
--
ALTER TABLE `favorites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_favorite` (`user_id`,`car_id`),
  ADD KEY `car_id` (`car_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`),
  ADD KEY `car_id` (`car_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cars`
--
ALTER TABLE `cars`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `favorites`
--
ALTER TABLE `favorites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cars`
--
ALTER TABLE `cars`
  ADD CONSTRAINT `cars_ibfk_1` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `favorites`
--
ALTER TABLE `favorites`
  ADD CONSTRAINT `favorites_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `favorites_ibfk_2` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
