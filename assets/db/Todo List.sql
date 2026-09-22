-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: mysql:3306
-- Generation Time: Sep 22, 2026 at 04:34 PM
-- Server version: 8.0.45
-- PHP Version: 8.3.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `todo_list`
--

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `start_date` date DEFAULT NULL,
  `deadline` date DEFAULT NULL,
  `status` enum('draft','active','archived') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'draft',
  `owner_id` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `project_invitations`
--

CREATE TABLE `project_invitations` (
  `id` int NOT NULL,
  `project_id` int NOT NULL,
  `code` varchar(16) COLLATE utf8mb4_general_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_general_ci NOT NULL,
  `created_by` int NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  `max_uses` int DEFAULT NULL,
  `use_count` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `project_members`
--

CREATE TABLE `project_members` (
  `project_id` int NOT NULL,
  `user_id` int NOT NULL,
  `added_by` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int NOT NULL,
  `name` enum('admin','user') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`) VALUES
(2, 'user');

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `id` int NOT NULL,
  `title` text NOT NULL,
  `description` text NOT NULL,
  `deadline` date DEFAULT NULL,
  `status` enum('open','in_progress','review','done','revision') NOT NULL DEFAULT 'open',
  `attachment` text NOT NULL,
  `work_attachment` text,
  `user_id` int NOT NULL,
  `project_id` int DEFAULT NULL,
  `assigned_to` int DEFAULT NULL,
  `reviewer_id` int DEFAULT NULL,
  `priority` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `label` varchar(100) DEFAULT NULL,
  `review_feedback` text,
  `reviewed_by` int DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `status` enum('pending','active','suspended') NOT NULL DEFAULT 'active',
  `must_reset_password` tinyint(1) NOT NULL DEFAULT '0',
  `activated_at` datetime DEFAULT NULL,
  `password` text NOT NULL,
  `role_id` int NOT NULL DEFAULT '2'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `status`, `must_reset_password`, `activated_at`, `password`, `role_id`) VALUES
(10, 'wanfah', 'wanfah@sawala.tech', 'active', 0, '2026-09-21 09:24:36', '$2y$10$9zx30kIxi.AL4bdRh0nFge65is5rMfjZMFRGeZ/e5Yc4V/8pUAt/G', 2),
(11, 'yaser', 'yaser@sawala.tech', 'active', 0, '2026-09-21 09:47:16', '$2y$10$Xfti6vuQfxkXEfMoXx6FvOowT3loYJWgvSbmpqgQMkcWYzpMoal9u', 2);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `proj_owner_fk` (`owner_id`);

--
-- Indexes for table `project_invitations`
--
ALTER TABLE `project_invitations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `proj_inv_token_unique` (`token`),
  ADD UNIQUE KEY `proj_inv_code_unique` (`project_id`,`code`),
  ADD KEY `proj_inv_project_idx` (`project_id`),
  ADD KEY `proj_inv_created_by_idx` (`created_by`);

--
-- Indexes for table `project_members`
--
ALTER TABLE `project_members`
  ADD PRIMARY KEY (`project_id`,`user_id`),
  ADD KEY `pm_user_fk` (`user_id`),
  ADD KEY `pm_added_by_fk` (`added_by`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tasks_ibfk_1` (`user_id`),
  ADD KEY `task_reviewed_by_fk` (`reviewed_by`),
  ADD KEY `task_project_idx` (`project_id`),
  ADD KEY `task_assigned_idx` (`assigned_to`),
  ADD KEY `task_status_idx` (`status`),
  ADD KEY `task_reviewer_id_fk` (`reviewer_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `users_email_unique` (`email`),
  ADD KEY `role_id` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `project_invitations`
--
ALTER TABLE `project_invitations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `proj_owner_fk` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `project_invitations`
--
ALTER TABLE `project_invitations`
  ADD CONSTRAINT `proj_inv_creator_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `proj_inv_project_fk` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `project_members`
--
ALTER TABLE `project_members`
  ADD CONSTRAINT `pm_added_by_fk` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `pm_project_fk` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `pm_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `task_assigned_fk` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `task_project_fk` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `task_reviewed_by_fk` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `task_reviewer_id_fk` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
